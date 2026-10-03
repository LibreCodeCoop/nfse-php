<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Xml;

use LibreCodeCoop\NfsePHP\SecretStore\NoOpSecretStore;
use LibreCodeCoop\NfsePHP\Tests\TestCase;
use LibreCodeCoop\NfsePHP\Xml\DpsSigner;
use LibreCodeCoop\NfsePHP\Xml\XmlSignatureVerifier;

/**
 * @covers \LibreCodeCoop\NfsePHP\Xml\XmlSignatureVerifier
 */
final class XmlSignatureVerifierTest extends TestCase
{
    private const CNPJ = '11222333000181';

    private string $pfxPath = '';

    protected function tearDown(): void
    {
        if ($this->pfxPath !== '' && is_file($this->pfxPath)) {
            unlink($this->pfxPath);
        }
    }

    public function testVerifiesDocumentProducedByDpsSigner(): void
    {
        $signed = $this->signedXml();

        self::assertTrue((new XmlSignatureVerifier())->verify($signed));
    }

    public function testRejectsTamperedSignedContent(): void
    {
        $signed = $this->signedXml();
        $tampered = str_replace(
            '<cMun>3303302</cMun>',
            '<cMun>3550308</cMun>',
            $signed,
        );

        self::assertFalse((new XmlSignatureVerifier())->verify($tampered));
    }

    public function testRejectsTamperedSignatureValue(): void
    {
        $signed = $this->signedXml();
        $doc = new \DOMDocument();
        self::assertTrue($doc->loadXML($signed));

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $signatureValue = $xpath->query('//ds:SignatureValue')->item(0);
        self::assertInstanceOf(\DOMElement::class, $signatureValue);

        $decoded = base64_decode(trim($signatureValue->textContent), true);
        self::assertNotFalse($decoded);
        $decoded[0] = chr(ord($decoded[0]) ^ 0x01);
        $signatureValue->nodeValue = base64_encode($decoded);

        $tampered = $doc->saveXML();
        self::assertIsString($tampered);

        self::assertFalse((new XmlSignatureVerifier())->verify($tampered));
    }

    public function testRejectsUnsignedAndMalformedXml(): void
    {
        $verifier = new XmlSignatureVerifier();

        self::assertFalse($verifier->verify('<DPS><infDPS Id="DPS1"/></DPS>'));
        self::assertFalse($verifier->verify('<not-closed>'));
    }

    private function signedXml(): string
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($privateKey);

        $csr = openssl_csr_new(
            ['commonName' => self::CNPJ],
            $privateKey,
            ['digest_alg' => 'sha256'],
        );
        self::assertNotFalse($csr);

        $certificate = openssl_csr_sign(
            $csr,
            null,
            $privateKey,
            1,
            ['digest_alg' => 'sha256'],
        );
        self::assertNotFalse($certificate);

        $pfx = '';
        self::assertTrue(openssl_pkcs12_export($certificate, $pfx, $privateKey, 'testpass'));

        $this->pfxPath = tempnam(sys_get_temp_dir(), 'nfse_verify_') ?: '';
        self::assertNotSame('', $this->pfxPath);
        self::assertNotFalse(file_put_contents($this->pfxPath, $pfx));

        $store = new NoOpSecretStore();
        $store->put('pfx/' . self::CNPJ, [
            'pfx_path' => $this->pfxPath,
            'password' => 'testpass',
        ]);

        return (new DpsSigner($store))->sign(
            '<DPS><infDPS Id="DPS11222333000181"><cMun>3303302</cMun></infDPS></DPS>',
            self::CNPJ,
        );
    }
}
