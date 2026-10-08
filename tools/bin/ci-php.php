#!/usr/bin/env php
<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/PhpVersionPolicy.php';

use LibreCodeCoop\NfsePHP\Tools\PhpVersionPolicy;

try {
    $policy = PhpVersionPolicy::fromFile(dirname(__DIR__, 2) . '/composer.json');
    $result = match ($argv[1] ?? '') {
        'minimum' => $policy->minimum(),
        'matrix' => json_encode($policy->matrix(), JSON_THROW_ON_ERROR),
        'primary' => $policy->primary(),
        'validate' => 'PHP CI policy is consistent with root composer.json',
        'assert-runtime' => (function () use ($policy): string {
            $policy->assertRuntime(PHP_VERSION);

            return 'PHP runtime satisfies the root Composer requirement';
        })(),
        default => throw new \InvalidArgumentException(
            'Usage: php tools/bin/ci-php.php {minimum|matrix|primary|validate|assert-runtime}',
        ),
    };
    fwrite(STDOUT, $result . "\n");
} catch (\Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
