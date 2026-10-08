<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tools\Tests;

use PHPUnit\Framework\TestCase;

final class OfficialAnnexViiSnapshotTest extends TestCase
{
    public function testOfflineAnnexViiSnapshotKeepsFortyUniqueVerbatimCodes(): void
    {
        $filename = dirname(__DIR__, 2) . '/resources/domains/indicadores-operacao-ibscbs-v1.03.00.tsv';
        $lines = file($filename, FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);

        $codes = [];
        foreach ($lines as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = explode("\t", $line);
            self::assertCount(3, $parts);
            self::assertMatchesRegularExpression('/^[0-9]{6}$/D', $parts[0]);
            self::assertNotSame('', $parts[1]);
            self::assertNotSame('', $parts[2]);
            self::assertArrayNotHasKey($parts[0], $codes);
            $codes[$parts[0]] = $parts;
        }

        self::assertCount(40, $codes);
        self::assertArrayHasKey('010101', $codes);
        self::assertArrayHasKey('020102', $codes);
        self::assertArrayHasKey('130201', $codes);
    }
}
