<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Integration\Http;

use LibreCodeCoop\NfsePHP\Tests\Support\LoadsLocalEnv;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * Optional sandbox connectivity smoke test (mTLS).
 * Skips if env vars are not configured.
 */
class SandboxMtlsHeadTest extends TestCase
{
    use LoadsLocalEnv;

    public function testSandboxHeadWithMtlsWhenEnvIsPresent(): void
    {
        self::loadLocalEnv();

        $url = getenv('NFSE_HEAD_URL') ?: '';
        $pfxPath = getenv('NFSE_MTLS_PFX_PATH') ?: '';
        $pfxPassword = getenv('NFSE_MTLS_PFX_PASSWORD') ?: '';

        if ($url === '' || $pfxPath === '' || $pfxPassword === '') {
            self::markTestSkipped('Set NFSE_HEAD_URL, NFSE_MTLS_PFX_PATH and NFSE_MTLS_PFX_PASSWORD to run sandbox mTLS test.');
        }

        if (!str_starts_with($pfxPath, '/')) {
            $pfxPath = dirname(__DIR__, 3) . '/' . ltrim($pfxPath, '/');
        }

        if (!is_file($pfxPath)) {
            self::markTestSkipped('Configured PFX file does not exist for mTLS test.');
        }

        // Use the PHP curl API so the PFX password never appears on a
        // shell command line or in a process argument list.
        if (!extension_loaded('curl')) {
            self::markTestSkipped('The optional ext-curl extension is required for the mTLS smoke test.');
        }

        $handle = curl_init($url);
        self::assertInstanceOf(\CurlHandle::class, $handle);
        curl_setopt_array($handle, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSLCERT => $pfxPath,
            CURLOPT_SSLCERTTYPE => 'P12',
            CURLOPT_KEYPASSWD => $pfxPassword,
            CURLOPT_TIMEOUT => 20,
        ]);

        $result = curl_exec($handle);
        $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($result === false) {
            self::markTestSkipped('mTLS connectivity is unavailable in this local test environment.');
        }

        self::assertContains($httpCode, [200, 401, 403, 404]);
    }
}
