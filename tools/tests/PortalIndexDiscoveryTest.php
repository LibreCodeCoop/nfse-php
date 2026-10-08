<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tools\Tests;

use LibreCodeCoop\NfsePHP\Tools\PortalIndexDiscovery;
use PHPUnit\Framework\TestCase;

final class PortalIndexDiscoveryTest extends TestCase
{
    public function testIdentifiesNewVersionWithoutConfusingHistoricalLinks(): void
    {
        $manifest = tempnam(sys_get_temp_dir(), 'nfse-index-');
        self::assertIsString($manifest);
        try {
            file_put_contents($manifest, json_encode([
                'sources' => [[
                    'id' => 'annex-vii',
                    'index_url' => 'https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc',
                    'url' => 'https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc/anexovii-indop_v1-03-00.xlsx',
                    'file_pattern' => 'anexovii-indop',
                ]],
            ], JSON_THROW_ON_ERROR));
            $html = '<html><a href="/nfse/pt-br/biblioteca/documentacao-tecnica/rtc/anexovii-indop_v1-02-00.xlsx">old</a>'
                . '<a href="/nfse/pt-br/biblioteca/documentacao-tecnica/rtc/anexovii-indop_v1-04-00.xlsx/view">new</a>'
                . '<a href="https://example.org/nfse/anexovii-indop_v9-00-00.xlsx">other</a></html>';
            $discovered = (new PortalIndexDiscovery())->findNewer($manifest,
                static fn (string $url): string => $html);
            self::assertCount(1, $discovered);
            self::assertSame('1.4.0', $discovered[0]['version']);
            self::assertStringEndsWith('v1-04-00.xlsx', $discovered[0]['url']);
        } finally {
            unlink($manifest);
        }
    }
}
