<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Config;

use LibreCodeCoop\NfsePHP\Config\EnvironmentConfig;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * @covers \LibreCodeCoop\NfsePHP\Config\EnvironmentConfig
 */
class EnvironmentConfigTest extends TestCase
{
    public function testDefaultsToProductionUrl(): void
    {
        $config = new EnvironmentConfig();

        self::assertFalse($config->sandboxMode);
        self::assertSame(
            'https://sefin.nfse.gov.br/SefinNacional',
            $config->baseUrl,
        );
    }

    public function testSandboxModeSelectsSandboxUrl(): void
    {
        $config = new EnvironmentConfig(sandboxMode: true);

        self::assertTrue($config->sandboxMode);
        self::assertSame(
            'https://sefin.producaorestrita.nfse.gov.br/SefinNacional',
            $config->baseUrl,
        );
    }

    public function testCustomBaseUrlOverridesMode(): void
    {
        $custom = 'http://localhost:8080/SefinNacional';
        $config = new EnvironmentConfig(sandboxMode: false, baseUrl: $custom);

        self::assertFalse($config->sandboxMode);
        self::assertSame($custom, $config->baseUrl);
    }

    public function testCustomBaseUrlOverridesSandboxUrl(): void
    {
        $custom = 'http://mock-server/SefinNacional';
        $config = new EnvironmentConfig(sandboxMode: true, baseUrl: $custom);

        self::assertSame($custom, $config->baseUrl);
    }

    public function testDanfseBaseUrlDefaultsToProductionAdn(): void
    {
        $config = new EnvironmentConfig(sandboxMode: false);

        self::assertSame('https://adn.nfse.gov.br/danfse', $config->danfseBaseUrl);
    }

    public function testDanfseBaseUrlDefaultsToSandboxAdn(): void
    {
        $config = new EnvironmentConfig(sandboxMode: true);

        self::assertSame('https://adn.producaorestrita.nfse.gov.br/danfse', $config->danfseBaseUrl);
    }

    public function testCustomDanfseBaseUrlOverridesDefault(): void
    {
        $custom = 'http://localhost:9999/danfse';
        $config = new EnvironmentConfig(danfseBaseUrl: $custom);

        self::assertSame($custom, $config->danfseBaseUrl);
    }
}
