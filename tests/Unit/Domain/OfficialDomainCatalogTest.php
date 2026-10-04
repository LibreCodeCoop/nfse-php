<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Domain;

use LibreCodeCoop\NfsePHP\Domain\OfficialDomainCatalog;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * @covers \LibreCodeCoop\NfsePHP\Domain\OfficialDomainCatalog
 */
final class OfficialDomainCatalogTest extends TestCase
{
    private OfficialDomainCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalog = new OfficialDomainCatalog();
    }

    public function testKnownMunicipalityAndCountryAreAvailableOffline(): void
    {
        self::assertSame(
            ['code' => '3304557', 'uf' => 'RJ', 'name' => 'Rio de Janeiro'],
            $this->catalog->municipality('3304557'),
        );
        self::assertSame('US', $this->catalog->country('us')['code'] ?? null);
        self::assertFalse($this->catalog->hasMunicipality('9999999'));
        self::assertFalse($this->catalog->hasCountry('XX'));
    }

    public function testKnownNationalServiceAndNbsAreAvailableOffline(): void
    {
        self::assertSame(
            'Análise e desenvolvimento de sistemas.',
            $this->catalog->nationalService('010101')['description'] ?? null,
        );
        self::assertTrue($this->catalog->hasNbs('101011100'));
        self::assertFalse($this->catalog->hasNationalService('000000'));
        self::assertFalse($this->catalog->hasNbs('000000000'));
    }

    public function testKnownIbsCbsOperationIndicatorIsAvailableOffline(): void
    {
        $indicator = $this->catalog->operationIndicator('020101');

        self::assertSame('020101', $indicator['code'] ?? null);
        self::assertStringContainsString('bem imóvel', $indicator['characteristic'] ?? '');
        self::assertFalse($this->catalog->hasOperationIndicator('999999'));
    }

    public function testCatalogVersionsNameTheOfficialAnnexes(): void
    {
        self::assertSame([
            'municipality_country' => 'ANEXO_A v1.00 (20251210)',
            'service_nbs' => 'ANEXO_B v1.01 (20260122)',
            'operation_indicator' => 'ANEXO_C v1.01 (20260122)',
        ], OfficialDomainCatalog::sourceVersions());
    }

    public function testSnapshotCardinalitiesMatchTheNormalizedAnnexes(): void
    {
        $base = dirname(__DIR__, 3) . '/resources/domains';

        self::assertSame(5571, $this->dataRowCount($base . '/municipios-ibge-v1.00.tsv'));
        self::assertSame(250, $this->dataRowCount($base . '/paises-iso2-v1.00.tsv'));
        self::assertSame(338, $this->dataRowCount($base . '/servicos-nacionais-v1.01.tsv'));
        self::assertSame(918, $this->dataRowCount($base . '/nbs-v2.0.tsv'));
        self::assertSame(26, $this->dataRowCount($base . '/indicadores-operacao-ibscbs-v1.01.tsv'));
    }

    private function dataRowCount(string $path): int
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        self::assertIsArray($lines);

        return count(array_filter(
            $lines,
            static fn (string $line): bool => $line !== '' && !str_starts_with($line, '#'),
        ));
    }
}
