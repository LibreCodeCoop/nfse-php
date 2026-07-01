<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Support;

use LibreCodeCoop\NfsePHP\Config\CertConfig;
use LibreCodeCoop\NfsePHP\SecretStore\NoOpSecretStore;
use LibreCodeCoop\NfsePHP\Support\TemporaryTlsFilesFactory;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * @covers \LibreCodeCoop\NfsePHP\Support\TemporaryTlsFilesFactory
 */
class TemporaryTlsFilesFactoryTest extends TestCase
{
    private string $testCnpj = '11222333000181';

    private string $pfxPath = '';

    protected function tearDown(): void
    {
        if ($this->pfxPath !== '' && is_file($this->pfxPath)) {
            unlink($this->pfxPath);
        }

        parent::tearDown();
    }

    public function testCreateBuildsTemporaryPemFilesFromStoredPfx(): void
    {
        $store = new NoOpSecretStore();
        $this->setupTestCert($store, 'testpass');

        $factory = new TemporaryTlsFilesFactory($store);
        $config = new CertConfig(
            cnpj: $this->testCnpj,
            pfxPath: '/unused/runtime-path.pfx',
            vaultPath: 'pfx/' . $this->testCnpj,
        );

        [$options, $cleanup] = $factory->create($config, ['verify_peer' => true]);

        self::assertTrue($options['verify_peer']);
        self::assertIsString($options['local_cert'] ?? null);
        self::assertIsString($options['local_pk'] ?? null);
        self::assertFileExists($options['local_cert']);
        self::assertFileExists($options['local_pk']);
        self::assertStringContainsString('BEGIN CERTIFICATE', (string) file_get_contents($options['local_cert']));
        self::assertStringContainsString('BEGIN PRIVATE KEY', (string) file_get_contents($options['local_pk']));

        $cleanup();

        self::assertFileDoesNotExist($options['local_cert']);
        self::assertFileDoesNotExist($options['local_pk']);
    }

    public function testCreateKeepsExplicitPemOverridesUntouched(): void
    {
        $store = new NoOpSecretStore();
        $factory = new TemporaryTlsFilesFactory($store);

        $config = new CertConfig(
            cnpj: $this->testCnpj,
            pfxPath: '/unused/runtime-path.pfx',
            vaultPath: 'pfx/' . $this->testCnpj,
            transportCertificatePath: '/tmp/client.crt.pem',
            transportPrivateKeyPath: '/tmp/client.key.pem',
        );

        [$options, $cleanup] = $factory->create($config, ['verify_peer_name' => true]);

        self::assertTrue($options['verify_peer_name']);
        self::assertSame('/tmp/client.crt.pem', $options['local_cert']);
        self::assertSame('/tmp/client.key.pem', $options['local_pk']);

        $cleanup();

        self::assertSame('/tmp/client.crt.pem', $options['local_cert']);
        self::assertSame('/tmp/client.key.pem', $options['local_pk']);
    }

    private function setupTestCert(NoOpSecretStore $store, string $password): void
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
        $ok = openssl_pkcs12_export($cert, $pfxData, $privKey, $password);
        self::assertTrue($ok, 'openssl_pkcs12_export must succeed');

        $this->pfxPath = sys_get_temp_dir() . '/nfse_tls_factory_' . $this->testCnpj . '.pfx';
        file_put_contents($this->pfxPath, $pfxData);

        $store->put('pfx/' . $this->testCnpj, [
            'pfx_path' => $this->pfxPath,
            'password' => $password,
        ]);
    }
}
