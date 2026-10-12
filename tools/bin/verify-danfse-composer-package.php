<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

/**
 * Composer distribution smoke test: inspect the actual generated archive,
 * not merely files present in the source checkout.
 *
 * Run locally with: php tools/bin/verify-danfse-composer-package.php
 */
$root = dirname(__DIR__, 2);
$dir = sys_get_temp_dir() . '/nfse-danfse-archive-' . bin2hex(random_bytes(8));
if (!mkdir($dir, 0o700)) {
    throw new RuntimeException('Could not create temporary Composer archive directory.');
}

try {
    $command = [
        'composer',
        'archive',
        '--format=tar',
        '--dir=' . $dir,
        '--file=nfse-danfse-test',
    ];

    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start composer archive.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0) {
        throw new RuntimeException('Composer archive failed (exit ' . $exit . '): ' . trim((string) $stderr));
    }

    $archives = glob($dir . '/*.tar') ?: [];
    if (count($archives) !== 1) {
        throw new RuntimeException('Composer must generate exactly one TAR package: ' . (string) $stdout);
    }

    // Expected hashes originate from the independently verified 2.1.5 TTFs.
    $required = [
        'src/Danfse/Assets/fonts/LiberationSans-Regular.ttf'
            => '76d04c18ea243f426b7de1f3ad208e927008f961dc5945e5aad352d0dfde8ee8',
        'src/Danfse/Assets/fonts/LiberationSans-Bold.ttf'
            => '788abee4c806d660e8aee46689dd8540cd4bb98da03dcc9d171ce3efd99a9173',
        'src/Danfse/Assets/fonts/NOTICE.md' => null,
        'LICENSES/OFL-1.1.txt' => null,
        'REUSE.toml' => null,
        'src/Danfse/Config/BundledDanfseFonts.php' => null,
        'composer.json' => null,
    ];
    $found = [];
    foreach (new RecursiveIteratorIterator(new PharData($archives[0])) as $entry) {
        if (!$entry instanceof PharFileInfo || !$entry->isFile()) {
            continue;
        }

        $name = $entry->getPathname();
        foreach ($required as $relative => $checksum) {
            if ($name !== $relative && !str_ends_with($name, '/' . $relative)) {
                continue;
            }
            if (isset($found[$relative])) {
                throw new RuntimeException('Duplicate package entry: ' . $relative);
            }
            if ($checksum !== null && !hash_equals($checksum, hash('sha256', $entry->getContent()))) {
                throw new RuntimeException('Packaged font checksum mismatch: ' . $relative);
            }
            $found[$relative] = true;
        }
    }

    foreach (array_keys($required) as $relative) {
        if (!isset($found[$relative])) {
            throw new RuntimeException('Missing file in Composer package: ' . $relative);
        }
    }
    fwrite(STDOUT, "Composer archive includes verified Liberation Sans TTFs, license, provenance and renderer.\n");
} finally {
    foreach (glob($dir . '/*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($dir);
}
