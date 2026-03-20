<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Integration\Xml;

use LibreCodeCoop\NfsePHP\Exception\PfxImportException;
use LibreCodeCoop\NfsePHP\SecretStore\NoOpSecretStore;
use LibreCodeCoop\NfsePHP\Tests\Support\LoadsLocalEnv;
use LibreCodeCoop\NfsePHP\Tests\TestCase;
use LibreCodeCoop\NfsePHP\Xml\DpsSigner;

/**
 * Optional integration test:
 * - Uses real PFX when env vars are available
 * - Skips cleanly when env vars are absent
 */
class DpsSignerIntegrationTest extends TestCase
{
    use LoadsLocalEnv;

    public function testSignsXmlWithConfiguredPfxWhenEnvIsPresent(): void
    {
        self::loadLocalEnv();

        $cnpj = getenv('NFS_TEST_CNPJ') ?: '11222333000181';
        $pfxPath = getenv('NFSE_MTLS_PFX_PATH') ?: '';
        $pfxPassword = getenv('NFSE_MTLS_PFX_PASSWORD') ?: '';

        if ($pfxPath === '' || $pfxPassword === '') {
            self::markTestSkipped('Set NFSE_MTLS_PFX_PATH and NFSE_MTLS_PFX_PASSWORD to run real-PFX integration test.');
        }

        if (!str_starts_with($pfxPath, '/')) {
            $pfxPath = dirname(__DIR__, 3) . '/' . ltrim($pfxPath, '/');
        }

        if (!is_file($pfxPath)) {
            self::markTestSkipped('Configured PFX file does not exist for integration test.');
        }

        $store = new NoOpSecretStore();
        $store->put('pfx/' . $cnpj, [
            'pfx_path' => $pfxPath,
            'password' => $pfxPassword,
        ]);

        $signer = new DpsSigner($store);
        $xml = '<DPS><infDPS Id="DPS123"><x>abc</x></infDPS></DPS>';

        try {
            $signed = $signer->sign($xml, $cnpj);
        } catch (PfxImportException $e) {
            $message = strtolower($e->getMessage());

            // Local OpenSSL runtime may not support legacy PKCS#12 algorithms.
            if (str_contains($message, 'digital envelope routines') || str_contains($message, 'asn1 encoding routines')) {
                self::markTestSkipped('Local OpenSSL runtime cannot import this PFX format.');
            }

            throw $e;
        }

        self::assertStringContainsString('<Signature', $signed);
        self::assertStringContainsString('DigestValue', $signed);
    }
}
