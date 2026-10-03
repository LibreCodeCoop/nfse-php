<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Xml;

/**
 * Verifies enveloped XMLDSig signatures embedded in NFS-e documents.
 *
 * The verifier trusts the certificate embedded in KeyInfo only for cryptographic
 * integrity. Certificate-chain trust/revocation remains a separate concern.
 */
final class XmlSignatureVerifier
{
    private const DS_NS = 'http://www.w3.org/2000/09/xmldsig#';

    public function verify(string $xml): bool
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;

        $previous = libxml_use_internal_errors(true);
        try {
            if (!$doc->loadXML($xml, LIBXML_NONET)) {
                return false;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ds', self::DS_NS);

        $signature = $xpath->query('//ds:Signature')->item(0);
        if (!$signature instanceof \DOMElement) {
            return false;
        }

        $signedInfo = $xpath->query('./ds:SignedInfo', $signature)->item(0);
        $signatureValueNode = $xpath->query('./ds:SignatureValue', $signature)->item(0);
        $certificateNode = $xpath->query('./ds:KeyInfo/ds:X509Data/ds:X509Certificate', $signature)->item(0);

        if (
            !$signedInfo instanceof \DOMElement
            || !$signatureValueNode instanceof \DOMElement
            || !$certificateNode instanceof \DOMElement
        ) {
            return false;
        }

        if (!$this->verifyReferences($doc, $xpath, $signedInfo)) {
            return false;
        }

        $canonicalizationMethod = $xpath
            ->query('./ds:CanonicalizationMethod', $signedInfo)
            ->item(0);
        $signatureMethod = $xpath
            ->query('./ds:SignatureMethod', $signedInfo)
            ->item(0);

        if (
            !$canonicalizationMethod instanceof \DOMElement
            || !$signatureMethod instanceof \DOMElement
        ) {
            return false;
        }

        $signedInfoCanonical = $this->canonicalize(
            $signedInfo,
            $canonicalizationMethod->getAttribute('Algorithm'),
        );
        if ($signedInfoCanonical === null) {
            return false;
        }

        $signatureValue = base64_decode(trim($signatureValueNode->textContent), true);
        if ($signatureValue === false) {
            return false;
        }

        $certificateBody = preg_replace('/\s+/', '', $certificateNode->textContent) ?? '';
        if ($certificateBody === '') {
            return false;
        }

        $certificatePem = "-----BEGIN CERTIFICATE-----\n"
            . chunk_split($certificateBody, 64, "\n")
            . "-----END CERTIFICATE-----\n";

        $publicKey = openssl_pkey_get_public($certificatePem);
        if ($publicKey === false) {
            return false;
        }

        $opensslAlgorithm = $this->signatureAlgorithm(
            $signatureMethod->getAttribute('Algorithm'),
        );
        if ($opensslAlgorithm === null) {
            return false;
        }

        return openssl_verify(
            $signedInfoCanonical,
            $signatureValue,
            $publicKey,
            $opensslAlgorithm,
        ) === 1;
    }

    private function verifyReferences(
        \DOMDocument $doc,
        \DOMXPath $xpath,
        \DOMElement $signedInfo,
    ): bool {
        $references = $xpath->query('./ds:Reference', $signedInfo);
        if ($references === false || $references->length === 0) {
            return false;
        }

        foreach ($references as $reference) {
            if (!$reference instanceof \DOMElement) {
                return false;
            }

            $uri = $reference->getAttribute('URI');
            if (!str_starts_with($uri, '#') || strlen($uri) <= 1) {
                return false;
            }

            $id = substr($uri, 1);
            $referencedNodes = $xpath->query('//*[@Id=' . $this->xpathLiteral($id) . ']');
            $referencedNode = $referencedNodes?->item(0);

            if (!$referencedNode instanceof \DOMElement) {
                return false;
            }

            $digestMethod = $xpath->query('./ds:DigestMethod', $reference)->item(0);
            $digestValueNode = $xpath->query('./ds:DigestValue', $reference)->item(0);

            if (
                !$digestMethod instanceof \DOMElement
                || !$digestValueNode instanceof \DOMElement
            ) {
                return false;
            }

            $hashAlgorithm = $this->digestAlgorithm($digestMethod->getAttribute('Algorithm'));
            if ($hashAlgorithm === null) {
                return false;
            }

            $canonicalizationAlgorithm = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';
            $transforms = $xpath->query('./ds:Transforms/ds:Transform', $reference);
            if ($transforms !== false) {
                foreach ($transforms as $transform) {
                    if (!$transform instanceof \DOMElement) {
                        continue;
                    }

                    $algorithm = $transform->getAttribute('Algorithm');
                    if ($this->isCanonicalizationAlgorithm($algorithm)) {
                        $canonicalizationAlgorithm = $algorithm;
                    }
                }
            }

            $canonical = $this->canonicalizeReference(
                $doc,
                $referencedNode,
                $canonicalizationAlgorithm,
            );
            if ($canonical === null) {
                return false;
            }

            $actualDigest = base64_encode(hash($hashAlgorithm, $canonical, true));
            $expectedDigest = trim($digestValueNode->textContent);

            if (!hash_equals($expectedDigest, $actualDigest)) {
                return false;
            }
        }

        return true;
    }

    private function canonicalizeReference(
        \DOMDocument $source,
        \DOMElement $referencedNode,
        string $algorithm,
    ): ?string {
        $copy = new \DOMDocument('1.0', 'UTF-8');
        $imported = $copy->importNode($referencedNode, true);
        $copy->appendChild($imported);

        $xpath = new \DOMXPath($copy);
        $xpath->registerNamespace('ds', self::DS_NS);

        $signatures = $xpath->query('.//ds:Signature', $copy->documentElement);
        if ($signatures !== false) {
            $toRemove = [];
            foreach ($signatures as $signature) {
                if ($signature instanceof \DOMNode) {
                    $toRemove[] = $signature;
                }
            }

            foreach ($toRemove as $signature) {
                $signature->parentNode?->removeChild($signature);
            }
        }

        return $copy->documentElement instanceof \DOMElement
            ? $this->canonicalize($copy->documentElement, $algorithm)
            : null;
    }

    private function canonicalize(\DOMNode $node, string $algorithm): ?string
    {
        return match ($algorithm) {
            'http://www.w3.org/TR/2001/REC-xml-c14n-20010315' => $node->C14N(false, false) ?: null,
            'http://www.w3.org/TR/2001/REC-xml-c14n-20010315#WithComments' => $node->C14N(false, true) ?: null,
            'http://www.w3.org/2001/10/xml-exc-c14n#' => $node->C14N(true, false) ?: null,
            'http://www.w3.org/2001/10/xml-exc-c14n#WithComments' => $node->C14N(true, true) ?: null,
            default => null,
        };
    }

    private function isCanonicalizationAlgorithm(string $algorithm): bool
    {
        return in_array($algorithm, [
            'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
            'http://www.w3.org/TR/2001/REC-xml-c14n-20010315#WithComments',
            'http://www.w3.org/2001/10/xml-exc-c14n#',
            'http://www.w3.org/2001/10/xml-exc-c14n#WithComments',
        ], true);
    }

    private function digestAlgorithm(string $algorithm): ?string
    {
        return match ($algorithm) {
            'http://www.w3.org/2000/09/xmldsig#sha1' => 'sha1',
            'http://www.w3.org/2001/04/xmlenc#sha256' => 'sha256',
            default => null,
        };
    }

    private function signatureAlgorithm(string $algorithm): int|string|null
    {
        return match ($algorithm) {
            'http://www.w3.org/2000/09/xmldsig#rsa-sha1' => OPENSSL_ALGO_SHA1,
            'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256' => OPENSSL_ALGO_SHA256,
            default => null,
        };
    }

    private function xpathLiteral(string $value): string
    {
        if (!str_contains($value, "'")) {
            return "'" . $value . "'";
        }

        if (!str_contains($value, '"')) {
            return '"' . $value . '"';
        }

        $parts = explode("'", $value);
        $encoded = array_map(
            static fn (string $part): string => "'" . $part . "'",
            $parts,
        );

        return 'concat(' . implode(', "\'", ', $encoded) . ')';
    }
}
