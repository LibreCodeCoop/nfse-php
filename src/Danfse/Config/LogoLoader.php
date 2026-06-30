<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Danfse\Config;

/**
 * Reads an image file from disk and builds a base64 data URI for embedding in
 * the DANFSe HTML (dompdf has remote loading disabled).
 */
final class LogoLoader
{
    public static function pathToDataUri(string $path): string
    {
        if (!is_readable($path)) {
            throw new \InvalidArgumentException("Logo file not found or unreadable: {$path}");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException("Could not read logo file: {$path}");
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
}
