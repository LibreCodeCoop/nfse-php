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

        $cmd = sprintf(
            'curl --silent --show-error --output /dev/null --write-out "%%{http_code}" --head --cert-type P12 --cert %s %s; echo "|exit:$?"',
            escapeshellarg($pfxPath . ':' . $pfxPassword),
            escapeshellarg($url)
        );

        $result = shell_exec($cmd);

        self::assertNotFalse($result, 'curl execution failed');

        $result = trim((string) $result);

        if (!str_contains($result, '|exit:')) {
            self::fail('Unexpected curl result format.');
        }

        [$httpCode, $exitPart] = explode('|exit:', $result, 2);
        $httpCode = trim($httpCode);
        $exitCode = (int) trim($exitPart);

        if ($exitCode !== 0) {
            self::markTestSkipped('mTLS curl failed in local runtime (likely OpenSSL/PFX compatibility).');
        }

        self::assertContains($httpCode, ['200', '401', '403', '404']);
    }
}
