<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Architecture;

use LibreCodeCoop\NfsePHP\Tests\TestCase;

final class ProtocolBoundaryTest extends TestCase
{
    /**
     * @dataProvider protocolClientProvider
     */
    public function testProtocolClientsUseInjectedTransportInsteadOfDirectNetworkCalls(string $path): void
    {
        $content = (string) file_get_contents(dirname(__DIR__, 3) . '/' . $path);

        self::assertStringContainsString('HttpTransportInterface', $content, $path);
        self::assertStringContainsString('NativeStreamTransport', $content, $path);
        self::assertStringNotContainsString('file_get_contents(', $content, $path);
        self::assertStringNotContainsString('curl_', $content, $path);
        self::assertStringNotContainsString('stream_context_create(', $content, $path);
    }

    public function testNativeTransportOwnsThePhpStreamBoundary(): void
    {
        $content = (string) file_get_contents(
            dirname(__DIR__, 3) . '/src/Http/NativeStreamTransport.php',
        );

        self::assertStringContainsString('implements HttpTransportInterface', $content);
        self::assertStringContainsString('stream_context_create(', $content);
        self::assertStringContainsString('file_get_contents(', $content);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function protocolClientProvider(): array
    {
        return [
            'SEFIN' => ['src/Http/NfseClient.php'],
            'ADN distribution' => ['src/Http/AdnClient.php'],
            'municipal parameters' => ['src/Http/MunicipalParametersClient.php'],
        ];
    }
}
