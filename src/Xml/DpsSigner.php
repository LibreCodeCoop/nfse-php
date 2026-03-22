<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Xml;

use LibreCodeCoop\NfsePHP\Contracts\SecretStoreInterface;
use LibreCodeCoop\NfsePHP\Contracts\XmlSignerInterface;
use LibreCodeCoop\NfsePHP\Exception\PfxImportException;

/**
 * Signs a DPS XML document using the PFX certificate stored for the given CNPJ.
 *
 * Strategy:
 *  1. Try openssl_pkcs12_read() (native PHP — works for modern OpenSSL 3.x PFX).
 *  2. On error:0308010C (legacy PFX format), fall back to CLI re-pack via `openssl pkcs12`.
 *
 * The PFX binary content is retrieved from the SecretStoreInterface and the
 * password is fetched separately, so neither ever appears in plain-text arguments.
 */
class DpsSigner implements XmlSignerInterface
{
    public function __construct(
        private readonly SecretStoreInterface $secretStore,
    ) {
    }

    public function sign(string $xml, string $cnpj): string
    {
        $secret   = $this->secretStore->get('pfx/' . $cnpj);
        $pfxPath  = $secret['pfx_path'] ?? '';
        $password = $secret['password'] ?? '';

        if ($pfxPath === '' || !is_file($pfxPath)) {
            throw new PfxImportException('PFX file not found for CNPJ ' . $cnpj);
        }

        $pfxContent = file_get_contents($pfxPath);
        if ($pfxContent === false) {
            throw new PfxImportException('Cannot read PFX file for CNPJ ' . $cnpj);
        }

        [$privateKeyPem, $certificatePem] = $this->importPfx($pfxContent, $password, $cnpj);

        return $this->signXml($xml, $privateKeyPem, $certificatePem);
    }

    // -------------------------------------------------------------------------

    /**
     * @return array{string, string} [privateKeyPem, certificatePem]
     */
    private function importPfx(string $pfxContent, string $password, string $cnpj): array
    {
        $certs = [];
        $ok    = openssl_pkcs12_read($pfxContent, $certs, $password);

        if (!$ok) {
            $nativeErrors = $this->drainOpenSslErrors();

            try {
                return $this->extractLegacyPemMaterial($pfxContent, $password, $cnpj);
            } catch (PfxImportException $cliException) {
                $nativeError = $nativeErrors !== [] ? implode(' | ', $nativeErrors) : 'unknown OpenSSL error';

                throw new PfxImportException(
                    'Failed to import PFX for CNPJ ' . $cnpj . ': ' . $nativeError . ' (CLI fallback failed: ' . $cliException->getMessage() . ')',
                    previous: $cliException,
                );
            }
        }

        return [$certs['pkey'], $certs['cert']];
    }

    /**
     * @return list<string>
     */
    private function drainOpenSslErrors(): array
    {
        $errors = [];

        while (($error = openssl_error_string()) !== false) {
            $errors[] = $error;
        }

        return $errors;
    }

    /**
     * Extract private key and leaf certificate from a legacy PFX via OpenSSL CLI.
     * The password is passed via environment variable to avoid shell injection.
     *
     * @return array{string, string} [privateKeyPem, certificatePem]
     */
    private function extractLegacyPemMaterial(string $pfxContent, string $password, string $cnpj): array
    {
        $tmpIn  = tempnam(sys_get_temp_dir(), 'nfse_in_');
        $tmpOut = tempnam(sys_get_temp_dir(), 'nfse_out_');

        if ($tmpIn === false || $tmpOut === false) {
            throw new PfxImportException('Failed to allocate temporary files for OpenSSL repack');
        }

        try {
            file_put_contents($tmpIn, $pfxContent);

            // Use env var to avoid password in process list (avoids shell injection)
            putenv('NFSE_PFX_PASS=' . $password);
            $cmd = sprintf(
                'openssl pkcs12 -legacy -in %s -passin env:NFSE_PFX_PASS -nodes -out %s 2>/dev/null',
                escapeshellarg($tmpIn),
                escapeshellarg($tmpOut),
            );

            exec($cmd, result_code: $code);

            if ($code !== 0) {
                throw new PfxImportException('openssl CLI repack failed with exit code ' . $code);
            }

            $result = file_get_contents($tmpOut);

            if ($result === false || $result === '') {
                throw new PfxImportException('openssl CLI repack produced empty output');
            }

            return $this->extractPemParts($result, $cnpj);
        } finally {
            putenv('NFSE_PFX_PASS');

            if (is_file($tmpIn)) {
                unlink($tmpIn);
            }
            if (is_file($tmpOut)) {
                unlink($tmpOut);
            }
        }
    }

    /**
     * @return array{string, string} [privateKeyPem, certificatePem]
     */
    private function extractPemParts(string $pemBundle, string $cnpj): array
    {
        $privateKeyMatched = preg_match(
            '/-----BEGIN(?: ENCRYPTED)? PRIVATE KEY-----.*?-----END(?: ENCRYPTED)? PRIVATE KEY-----/s',
            $pemBundle,
            $privateKeyMatches,
        ) === 1;

        $certificateMatched = preg_match(
            '/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s',
            $pemBundle,
            $certificateMatches,
        ) === 1;

        if (!$privateKeyMatched || !$certificateMatched) {
            throw new PfxImportException('Failed to extract PEM material from legacy PFX for CNPJ ' . $cnpj);
        }

        return [$privateKeyMatches[0], $certificateMatches[0]];
    }

    /**
     * Signs an XML document per ABRASF 2.04 / XML-DSig (RSA-SHA1, enveloped signature).
     *
     * Steps:
     *  1. Locate the element with @Id (infDPS).
     *  2. Compute SHA-1 digest of its canonical (C14N) form.
     *  3. Build the Signature/SignedInfo structure in the ds: namespace.
     *  4. Compute C14N of SignedInfo and RSA-SHA1 sign it.
     *  5. Append SignatureValue and KeyInfo (X509Certificate) to complete Signature.
     */
    private function signXml(string $xml, string $privateKeyPem, string $certificatePem): string
    {
        $dsNs     = 'http://www.w3.org/2000/09/xmldsig#';
        $c14nAlgo = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';
        $sigAlgo  = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';
        $sha1Algo = 'http://www.w3.org/2000/09/xmldsig#sha1';
        $envAlgo  = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;

        if (!$doc->loadXML($xml)) {
            throw new PfxImportException('Cannot parse XML for signing');
        }

        $xpath  = new \DOMXPath($doc);
        $idNode = $xpath->query('//*[@Id]')->item(0);

        if (!$idNode instanceof \DOMElement) {
            throw new PfxImportException('No element with @Id attribute found in DPS XML');
        }

        $refId = $idNode->getAttribute('Id');

        // 1. Digest the reference element (Signature not yet in the document — enveloped transform is a no-op here)
        $refCanonical = $idNode->C14N();
        $digestValue  = base64_encode(hash('sha1', $refCanonical, binary: true));

        // 2. Build Signature element
        $sig = $doc->createElementNS($dsNs, 'Signature');
        $doc->documentElement->appendChild($sig);

        // 2a. SignedInfo
        $signedInfo = $doc->createElementNS($dsNs, 'SignedInfo');
        $sig->appendChild($signedInfo);

        $c14nMethod = $doc->createElementNS($dsNs, 'CanonicalizationMethod');
        $c14nMethod->setAttribute('Algorithm', $c14nAlgo);
        $signedInfo->appendChild($c14nMethod);

        $sigMethod = $doc->createElementNS($dsNs, 'SignatureMethod');
        $sigMethod->setAttribute('Algorithm', $sigAlgo);
        $signedInfo->appendChild($sigMethod);

        $reference = $doc->createElementNS($dsNs, 'Reference');
        $reference->setAttribute('URI', '#' . $refId);
        $signedInfo->appendChild($reference);

        $transforms = $doc->createElementNS($dsNs, 'Transforms');
        $reference->appendChild($transforms);

        $t1 = $doc->createElementNS($dsNs, 'Transform');
        $t1->setAttribute('Algorithm', $envAlgo);
        $transforms->appendChild($t1);

        $t2 = $doc->createElementNS($dsNs, 'Transform');
        $t2->setAttribute('Algorithm', $c14nAlgo);
        $transforms->appendChild($t2);

        $digestMethod = $doc->createElementNS($dsNs, 'DigestMethod');
        $digestMethod->setAttribute('Algorithm', $sha1Algo);
        $reference->appendChild($digestMethod);

        $digestValueEl             = $doc->createElementNS($dsNs, 'DigestValue');
        $digestValueEl->textContent = $digestValue;
        $reference->appendChild($digestValueEl);

        // 3. Canonicalise SignedInfo and sign it
        $signedInfoC14n = $signedInfo->C14N();

        $privKey = openssl_pkey_get_private($privateKeyPem);
        if ($privKey === false) {
            throw new PfxImportException('Cannot load private key for XML signing');
        }

        $rawSignature = '';
        if (!openssl_sign($signedInfoC14n, $rawSignature, $privKey, OPENSSL_ALGO_SHA1)) {
            throw new PfxImportException('openssl_sign failed: ' . (openssl_error_string() ?: 'unknown error'));
        }

        // 4. SignatureValue
        $sigValueEl             = $doc->createElementNS($dsNs, 'SignatureValue');
        $sigValueEl->textContent = base64_encode($rawSignature);
        $sig->appendChild($sigValueEl);

        // 5. KeyInfo / X509Certificate
        $certB64 = preg_replace('/-----[A-Z ]+-----|[\r\n]/', '', $certificatePem) ?? '';

        $keyInfo  = $doc->createElementNS($dsNs, 'KeyInfo');
        $x509Data = $doc->createElementNS($dsNs, 'X509Data');
        $x509Cert = $doc->createElementNS($dsNs, 'X509Certificate');
        $x509Cert->textContent = $certB64;
        $x509Data->appendChild($x509Cert);
        $keyInfo->appendChild($x509Data);
        $sig->appendChild($keyInfo);

        return $doc->saveXML() ?: $xml;
    }
}
