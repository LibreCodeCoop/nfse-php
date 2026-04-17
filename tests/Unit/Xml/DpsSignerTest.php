<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Xml;

use LibreCodeCoop\NfsePHP\Exception\PfxImportException;
use LibreCodeCoop\NfsePHP\SecretStore\NoOpSecretStore;
use LibreCodeCoop\NfsePHP\Tests\TestCase;
use LibreCodeCoop\NfsePHP\Xml\DpsSigner;

/**
 * @covers \LibreCodeCoop\NfsePHP\Xml\DpsSigner
 */
class DpsSignerTest extends TestCase
{
    private DpsSigner $signer;

    private NoOpSecretStore $store;

    private string $testCnpj = '11222333000181';

    private string $testXml = '<DPS><infDPS Id="DPS11222333000181"><cMun>3303302</cMun></infDPS></DPS>';

    private string $pfxPath = '';

    protected function setUp(): void
    {
        $this->store = new NoOpSecretStore();
        $this->signer = new DpsSigner($this->store);
        $this->setupTestCert();
    }

    protected function tearDown(): void
    {
        if ($this->pfxPath !== '' && is_file($this->pfxPath)) {
            unlink($this->pfxPath);
        }
    }

    private function setupTestCert(): void
    {
        $privKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($privKey, 'openssl_pkey_new must succeed in this environment');

        $csr = openssl_csr_new(
            ['commonName' => $this->testCnpj],
            $privKey,
            ['digest_alg' => 'sha256'],
        );
        self::assertNotFalse($csr, 'openssl_csr_new must succeed');

        $cert = openssl_csr_sign($csr, null, $privKey, 1, ['digest_alg' => 'sha256']);
        self::assertNotFalse($cert, 'openssl_csr_sign must succeed');

        $pfxData = '';
        $ok = openssl_pkcs12_export($cert, $pfxData, $privKey, 'testpass');
        self::assertTrue($ok, 'openssl_pkcs12_export must succeed');

        $this->pfxPath = sys_get_temp_dir() . '/nfse_test_' . $this->testCnpj . '.pfx';
        file_put_contents($this->pfxPath, $pfxData);

        $this->store->put('pfx/' . $this->testCnpj, [
            'pfx_path'  => $this->pfxPath,
            'password'  => 'testpass',
        ]);
    }

    public function testSignReturnsXmlContainingSignatureElement(): void
    {
        $signed = $this->signer->sign($this->testXml, $this->testCnpj);

        self::assertStringContainsString('<Signature', $signed);
    }

    public function testSignReturnsXmlContainingDigestValue(): void
    {
        $signed = $this->signer->sign($this->testXml, $this->testCnpj);

        self::assertStringContainsString('DigestValue', $signed);
    }

    public function testSignReturnsXmlContainingSignatureValue(): void
    {
        $signed = $this->signer->sign($this->testXml, $this->testCnpj);

        self::assertStringContainsString('SignatureValue', $signed);
    }

    public function testSignReturnsXmlContainingX509Certificate(): void
    {
        $signed = $this->signer->sign($this->testXml, $this->testCnpj);

        self::assertStringContainsString('X509Certificate', $signed);
    }

    public function testSignThrowsPfxImportExceptionWhenFileNotFound(): void
    {
        $store = new NoOpSecretStore();
        $store->put('pfx/99999999999999', [
            'pfx_path' => '/nonexistent/path/cert.pfx',
            'password' => 'x',
        ]);

        $signer = new DpsSigner($store);

        $this->expectException(PfxImportException::class);
        $signer->sign($this->testXml, '99999999999999');
    }

    public function testSignedXmlIsStillValidXml(): void
    {
        $signed = $this->signer->sign($this->testXml, $this->testCnpj);

        $doc = new \DOMDocument();
        self::assertTrue($doc->loadXML($signed), 'Signed output must be valid XML');
    }

    public function testSignedXmlIncludesExplicitUtf8EncodingDeclaration(): void
    {
        $signed = $this->signer->sign($this->testXml, $this->testCnpj);

        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $signed);
    }

    public function testSignatureElementIsAppendedToDpsRoot(): void
    {
        $signed = $this->signer->sign($this->testXml, $this->testCnpj);

        $doc = new \DOMDocument();
        $doc->loadXML($signed);

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $nodes = $xpath->query('/DPS/ds:Signature');

        self::assertSame(1, $nodes->length, 'Signature must be a direct child of DPS root');
    }

    public function testExtractPemPartsReturnsPrivateKeyAndCertificateFromCliBundle(): void
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($privateKey);

        $certificateRequest = openssl_csr_new(
            ['commonName' => $this->testCnpj],
            $privateKey,
            ['digest_alg' => 'sha256'],
        );
        self::assertNotFalse($certificateRequest);

        $certificate = openssl_csr_sign($certificateRequest, null, $privateKey, 1, ['digest_alg' => 'sha256']);
        self::assertNotFalse($certificate);

        $privateKeyPem = '';
        self::assertTrue(openssl_pkey_export($privateKey, $privateKeyPem));

        $certificatePem = '';
        self::assertTrue(openssl_x509_export($certificate, $certificatePem));

        $pemBundle = "Bag Attributes\nlocalKeyID: 01 02 03\n" . $certificatePem . "\n" . $privateKeyPem;

        $method = new \ReflectionMethod(DpsSigner::class, 'extractPemParts');
        $method->setAccessible(true);

        $parts = $method->invoke($this->signer, $pemBundle, $this->testCnpj);

        self::assertSame(rtrim($privateKeyPem), $parts[0]);
        self::assertSame(rtrim($certificatePem), $parts[1]);
    }

    public function testExtractLegacyPemMaterialViaCLIReturnsPrivateKeyAndCertificate(): void
    {
        $method = new \ReflectionMethod(DpsSigner::class, 'extractLegacyPemMaterial');
        $method->setAccessible(true);

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        $csr = openssl_csr_new(['commonName' => $this->testCnpj], $key, ['digest_alg' => 'sha256']);
        self::assertNotFalse($csr);
        $cert = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
        self::assertNotFalse($cert);
        $pfxData = '';
        $ok = openssl_pkcs12_export($cert, $pfxData, $key, 'legacypass');
        self::assertTrue($ok, 'openssl_pkcs12_export must succeed');

        [$privateKeyPem, $certificatePem] = $method->invoke($this->signer, $pfxData, 'legacypass', $this->testCnpj);

        self::assertStringContainsString('-----BEGIN PRIVATE KEY-----', $privateKeyPem);
        self::assertStringContainsString('-----END PRIVATE KEY-----', $privateKeyPem);
        self::assertStringContainsString('-----BEGIN CERTIFICATE-----', $certificatePem);
        self::assertStringContainsString('-----END CERTIFICATE-----', $certificatePem);
    }
}
