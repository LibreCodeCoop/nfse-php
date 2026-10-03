<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Config;

use LibreCodeCoop\NfsePHP\Config\EnvironmentConfig;
use LibreCodeCoop\NfsePHP\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @covers \LibreCodeCoop\NfsePHP\Config\EnvironmentConfig
 */
class EnvironmentConfigTest extends TestCase
{
    #[DataProvider('modeUrlProvider')]
    public function testModeSelectsBaseUrl(bool $sandboxMode, string $expectedUrl): void
    {
        $config = new EnvironmentConfig(sandboxMode: $sandboxMode);

        self::assertSame($sandboxMode, $config->sandboxMode);
        self::assertSame($expectedUrl, $config->baseUrl);
    }

    /**
     * @return array<string, array{bool, string}>
     */
    public static function modeUrlProvider(): array
    {
        return [
            'production by default' => [false, 'https://sefin.nfse.gov.br/SefinNacional'],
            'sandbox mode' => [true, 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional'],
        ];
    }

    public function testCustomBaseUrlOverridesMode(): void
    {
        $custom = 'http://localhost:8080/SefinNacional';
        $config = new EnvironmentConfig(sandboxMode: false, baseUrl: $custom);

        self::assertFalse($config->sandboxMode);
        self::assertSame($custom, $config->baseUrl);
    }

    public function testRequestTimeoutDefaultsToThirtySeconds(): void
    {
        self::assertSame(30, (new EnvironmentConfig())->requestTimeoutSeconds);
    }

    public function testRequestTimeoutIsConfigurable(): void
    {
        self::assertSame(12, (new EnvironmentConfig(requestTimeoutSeconds: 12))->requestTimeoutSeconds);
    }

    public function testRequestTimeoutRejectsNonPositiveValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new EnvironmentConfig(requestTimeoutSeconds: 0);
    }

    public function testCustomBaseUrlIsNormalizedWithoutTrailingSlash(): void
    {
        $config = new EnvironmentConfig(baseUrl: 'http://localhost:8080/SefinNacional/');

        self::assertSame('http://localhost:8080/SefinNacional', $config->baseUrl);
    }

    public function testCustomBaseUrlOverridesSandboxUrl(): void
    {
        $custom = 'http://mock-server/SefinNacional';
        $config = new EnvironmentConfig(sandboxMode: true, baseUrl: $custom);

        self::assertSame($custom, $config->baseUrl);
    }
}
