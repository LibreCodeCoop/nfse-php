<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Xml;

use LibreCodeCoop\NfsePHP\Contracts\CertificateRevocationCheckerInterface;
use LibreCodeCoop\NfsePHP\SecretStore\NoOpSecretStore;
use LibreCodeCoop\NfsePHP\Tests\TestCase;
use LibreCodeCoop\NfsePHP\Xml\CertificateTrustValidator;
use LibreCodeCoop\NfsePHP\Xml\DpsSigner;

/**
 * @covers \LibreCodeCoop\NfsePHP\Xml\CertificateTrustValidator
 * @covers \LibreCodeCoop\NfsePHP\Dto\CertificateTrustResult
 */
final class CertificateTrustValidatorTest extends TestCase
{
    private const CNPJ = '11222333000181';

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->temporaryFiles = [];
    }

    public function testTrustedChainIsDistinctFromSignatureIntegrity(): void
    {
        [$signedXml, $rootPath] = $this->signedXmlWithTestCa();

        $result = (new CertificateTrustValidator([$rootPath]))->validate($signedXml);

        self::assertTrue($result->signatureValid);
        self::assertTrue($result->certificatePresent);
        self::assertTrue($result->certificateTimeValid);
        self::assertTrue($result->chainTrusted);
        self::assertSame('not_checked', $result->revocationStatus);
        self::assertTrue($result->isTrusted());
    }

    public function testValidSignatureWithNoConfiguredAnchorIsUntrusted(): void
    {
        [$signedXml] = $this->signedXmlWithTestCa();

        $result = (new CertificateTrustValidator())->validate($signedXml);

        self::assertTrue($result->signatureValid);
        self::assertTrue($result->certificatePresent);
        self::assertFalse($result->chainTrusted);
        self::assertFalse($result->isTrusted());
        self::assertContains('No certificate trust anchors were configured.', $result->errors);
    }

    public function testBrokenSignatureIsReportedBeforeTrust(): void
    {
        [$signedXml, $rootPath] = $this->signedXmlWithTestCa();
        $tampered = str_replace('<cMun>3303302</cMun>', '<cMun>3550308</cMun>', $signedXml);

        $result = (new CertificateTrustValidator([$rootPath]))->validate($tampered);

        self::assertFalse($result->signatureValid);
        self::assertTrue($result->certificatePresent);
        self::assertFalse($result->isTrusted());
        self::assertContains('XMLDSig integrity verification failed.', $result->errors);
    }

    public function testMissingCertificateIsReportedExplicitly(): void
    {
        $result = (new CertificateTrustValidator())->validate(
            '<DPS><infDPS Id="DPS1"><cMun>3303302</cMun></infDPS></DPS>',
        );

        self::assertFalse($result->signatureValid);
        self::assertFalse($result->certificatePresent);
        self::assertFalse($result->isTrusted());
        self::assertContains(
            'XML signature does not contain an X509 certificate in KeyInfo.',
            $result->errors,
        );
    }

    public function testCertificateValidityWindowIsEvaluatedAtExplicitTime(): void
    {
        [$signedXml, $rootPath, $validFrom, $validTo] = $this->signedXmlWithTestCa();

        $notYetValid = (new CertificateTrustValidator(
            [$rootPath],
            validationTime: $validFrom - 60,
        ))->validate($signedXml);
        self::assertFalse($notYetValid->certificateTimeValid);
        self::assertContains('Signing certificate is not yet valid.', $notYetValid->errors);

        $expired = (new CertificateTrustValidator(
            [$rootPath],
            validationTime: $validTo + 60,
        ))->validate($signedXml);
        self::assertFalse($expired->certificateTimeValid);
        self::assertContains('Signing certificate is expired.', $expired->errors);
    }

    public function testRevokedCertificateMakesTrustResultUntrusted(): void
    {
        [$signedXml, $rootPath] = $this->signedXmlWithTestCa();

        $checker = new class () implements CertificateRevocationCheckerInterface {
            public function check(string $certificatePem): string
            {
                return 'revoked';
            }
        };

        $result = (new CertificateTrustValidator([$rootPath], $checker))->validate($signedXml);

        self::assertSame('revoked', $result->revocationStatus);
        self::assertFalse($result->isTrusted());
        self::assertContains('Signing certificate is revoked.', $result->errors);
    }

    public function testRevocationCheckerFailureIsExplicitAndNonFatal(): void
    {
        [$signedXml, $rootPath] = $this->signedXmlWithTestCa();

        $checker = new class () implements CertificateRevocationCheckerInterface {
            public function check(string $certificatePem): string
            {
                throw new \RuntimeException('synthetic timeout');
            }
        };

        $result = (new CertificateTrustValidator([$rootPath], $checker))->validate($signedXml);

        self::assertSame('unknown', $result->revocationStatus);
        self::assertTrue($result->signatureValid);
        self::assertTrue($result->chainTrusted);
        self::assertFalse($result->isTrusted());
        self::assertContains(
            'Certificate revocation check failed: synthetic timeout',
            $result->errors,
        );
    }

    /**
     * @return array{string,string,int,int}
     */
    private function signedXmlWithTestCa(): array
    {
        $configPath = $this->temporaryFile('nfse_openssl_', $this->opensslConfig());

        $rootKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => $configPath,
        ]);
        self::assertNotFalse($rootKey);

        $rootCsr = openssl_csr_new(
            ['commonName' => 'NFS-e Synthetic Test Root'],
            $rootKey,
            ['digest_alg' => 'sha256', 'config' => $configPath],
        );
        self::assertNotFalse($rootCsr);

        $rootCertificate = openssl_csr_sign(
            $rootCsr,
            null,
            $rootKey,
            30,
            [
                'digest_alg' => 'sha256',
                'config' => $configPath,
                'x509_extensions' => 'v3_ca',
            ],
        );
        self::assertNotFalse($rootCertificate);

        $leafKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => $configPath,
        ]);
        self::assertNotFalse($leafKey);

        $leafCsr = openssl_csr_new(
            ['commonName' => self::CNPJ],
            $leafKey,
            ['digest_alg' => 'sha256', 'config' => $configPath],
        );
        self::assertNotFalse($leafCsr);

        $leafCertificate = openssl_csr_sign(
            $leafCsr,
            $rootCertificate,
            $rootKey,
            2,
            [
                'digest_alg' => 'sha256',
                'config' => $configPath,
                'x509_extensions' => 'v3_leaf',
            ],
        );
        self::assertNotFalse($leafCertificate);

        $rootPem = '';
        self::assertTrue(openssl_x509_export($rootCertificate, $rootPem));
        $rootPath = $this->temporaryFile('nfse_root_', $rootPem);

        $pfx = '';
        self::assertTrue(openssl_pkcs12_export(
            $leafCertificate,
            $pfx,
            $leafKey,
            'testpass',
            ['extracerts' => [$rootCertificate]],
        ));
        $pfxPath = $this->temporaryFile('nfse_leaf_', $pfx);

        $store = new NoOpSecretStore();
        $store->put('pfx/' . self::CNPJ, [
            'pfx_path' => $pfxPath,
            'password' => 'testpass',
        ]);

        $signedXml = (new DpsSigner($store))->sign(
            '<DPS><infDPS Id="DPS11222333000181"><cMun>3303302</cMun></infDPS></DPS>',
            self::CNPJ,
        );

        $parsed = openssl_x509_parse($leafCertificate);
        self::assertIsArray($parsed);
        $validFrom = (int) ($parsed['validFrom_time_t'] ?? 0);
        $validTo = (int) ($parsed['validTo_time_t'] ?? 0);
        self::assertGreaterThan(0, $validFrom);
        self::assertGreaterThan($validFrom, $validTo);

        return [$signedXml, $rootPath, $validFrom, $validTo];
    }

    private function temporaryFile(string $prefix, string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix) ?: '';
        self::assertNotSame('', $path);
        self::assertNotFalse(file_put_contents($path, $contents));
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function opensslConfig(): string
    {
        return <<<'INI'
[ req ]
distinguished_name = req_distinguished_name
prompt = no

[ req_distinguished_name ]
CN = placeholder

[ v3_ca ]
basicConstraints = critical,CA:TRUE
keyUsage = critical,keyCertSign,cRLSign
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid:always,issuer

[ v3_leaf ]
basicConstraints = critical,CA:FALSE
keyUsage = critical,digitalSignature
extendedKeyUsage = clientAuth
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid,issuer
INI;
    }
}
