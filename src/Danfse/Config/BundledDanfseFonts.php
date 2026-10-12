<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Danfse\Config;

use LibreCodeCoop\NfsePHP\Exception\ArtifactException;
use LibreCodeCoop\NfsePHP\Exception\NfseErrorCode;

/**
 * @internal Default, packaged OFL font set; no OS fonts or runtime downloads.
 */
final class BundledDanfseFonts
{
    private const FILE_SHA256 = [
        'LiberationSans-Regular.ttf' => '76d04c18ea243f426b7de1f3ad208e927008f961dc5945e5aad352d0dfde8ee8',
        'LiberationSans-Bold.ttf' => '788abee4c806d660e8aee46689dd8540cd4bb98da03dcc9d171ce3efd99a9173',
    ];

    private static ?DanfseFonts $cached = null;

    public static function get(): DanfseFonts
    {
        $fontDir = __DIR__ . '/../Assets/fonts';
        foreach (self::FILE_SHA256 as $filename => $checksum) {
            self::assertValidFile($fontDir . '/' . $filename, $checksum);
        }

        if (self::$cached !== null) {
            if (!is_dir(self::$cached->fontCacheDirectory) || !is_writable(self::$cached->fontCacheDirectory)) {
                throw self::error('Default font cache became unavailable.');
            }

            return self::$cached;
        }

        $cache = sys_get_temp_dir() . '/nfse-danfse-liberation-' . bin2hex(random_bytes(12));
        if (!mkdir($cache, 0o700) || !is_writable($cache)) {
            throw self::error('Unable to create a private Dompdf font cache.');
        }

        // Dompdf writes font metrics during first use. Keep the cache private
        // per PHP process and clean it at shutdown; never write to vendor/.
        register_shutdown_function(static function () use ($cache): void {
            foreach (glob($cache . '/*') ?: [] as $file) {
                if (is_file($file) && !is_link($file)) {
                    @unlink($file);
                }
            }
            @rmdir($cache);
        });

        return self::$cached = new DanfseFonts(
            $fontDir . '/LiberationSans-Regular.ttf',
            $fontDir . '/LiberationSans-Bold.ttf',
            $fontDir . '/LiberationSans-Regular.ttf',
            $cache,
        );
    }

    /**
     * @internal Integrity check shared by production and negative unit tests.
     */
    public static function assertValidFile(string $path, string $sha256): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw self::error('Bundled Liberation Sans font missing or unreadable: ' . basename($path));
        }

        $actual = hash_file('sha256', $path);
        if (!is_string($actual) || !hash_equals($sha256, $actual)) {
            throw self::error('Bundled Liberation Sans font failed integrity check: ' . basename($path));
        }
    }

    private static function error(string $message): ArtifactException
    {
        return new ArtifactException($message, NfseErrorCode::ArtifactRetrievalFailed);
    }
}
