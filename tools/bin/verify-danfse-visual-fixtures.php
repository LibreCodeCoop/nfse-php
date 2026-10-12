<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use LibreCodeCoop\NfsePHP\Tools\DanfsePdfEvidence;

require dirname(__DIR__) . '/src/DanfsePdfEvidence.php';

$directory = $argv[1] ?? dirname(__DIR__, 2) . '/build/danfse-visual';
if (!is_dir($directory)) {
    fwrite(STDERR, "Missing synthetic PDF evidence directory: $directory\n");
    exit(1);
}

// Poppler diagnostics should not depend on the runner's locale.
putenv('LC_ALL=C');
$failed = false;
foreach (DanfsePdfEvidence::REQUIRED_FILES as $filename) {
    $path = $directory . '/' . $filename;
    if (!is_file($path) || !is_readable($path)) {
        fwrite(STDERR, "$filename: missing or unreadable PDF\n");
        $failed = true;
        continue;
    }

    $outputs = [];
    foreach ([['pdfinfo', $path], ['pdffonts', $path], ['pdftotext', '-layout', $path, '-']] as $command) {
        // argv-array form prevents shell interpretation of filenames.
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            fwrite(STDERR, "$filename: unable to start {$command[0]}\n");
            $failed = true;
            continue 2;
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0 || !is_string($stdout)) {
            fwrite(STDERR, "$filename: {$command[0]} failed (exit $status)\n");
            $failed = true;
            continue 2;
        }
        $outputs[] = $stdout;
    }

    $errors = DanfsePdfEvidence::check($filename, $outputs[0], $outputs[1], $outputs[2]);
    if ($errors !== []) {
        $failed = true;
        foreach ($errors as $error) {
            fwrite(STDERR, "$filename: $error\n");
        }
    } else {
        fwrite(STDOUT, "$filename: A4/1 page, mandatory text and expected embedded fonts verified\n");
    }
}

exit($failed ? 1 : 0);
