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

    public function testNationalServicesCanBeSearchedByCodeOrDescription(): void
    {
        $byCode = $this->catalog->searchNationalServices('010101', 10);
        self::assertSame('010101', $byCode[0]['code'] ?? null);
        self::assertSame('Análise e desenvolvimento de sistemas.', $byCode[0]['description'] ?? null);

        $byDescription = $this->catalog->searchNationalServices('desenvolvimento de sistemas', 10);
        self::assertSame('010101', $byDescription[0]['code'] ?? null);
    }

    public function testNationalServicesSearchIsBoundedAndCanListTheCatalog(): void
    {
        $firstTwo = $this->catalog->searchNationalServices(null, 2);

        self::assertCount(2, $firstTwo);
        self::assertSame('010101', $firstTwo[0]['code']);
        self::assertCount(338, $this->catalog->searchNationalServices(null, 500));
    }

    public function testSearchNbsUsesOfficialOfflineCodeAndDescription(): void
    {
        $results = $this->catalog->searchNbs('115011000');

        self::assertSame('115011000', $results[0]['code'] ?? null);
        self::assertCount(918, $this->catalog->searchNbs());
        self::assertSame([], $this->catalog->searchNbs('not-a-service'));
    }

    public function testKnownIbsCbsOperationIndicatorIsAvailableOffline(): void
    {
        $indicator = $this->catalog->operationIndicator('020101');

        self::assertSame('020101', $indicator['code'] ?? null);
        self::assertStringContainsString('bem imóvel', $indicator['characteristic'] ?? '');
        self::assertFalse($this->catalog->hasOperationIndicator('999999'));
    }

    public function testAnnexViiCanBeSelectedWithoutChangingTheDefaultProductionDomain(): void
    {
        $nt009 = OfficialDomainCatalog::OPERATION_INDICATOR_NT009_VERSION;
        self::assertNull($this->catalog->operationIndicator('010101'));
        self::assertSame('010101', $this->catalog->operationIndicator('010101', $nt009)['code'] ?? null);
        self::assertTrue($this->catalog->hasOperationIndicator('010101', $nt009));
        self::assertFalse($this->catalog->hasOperationIndicator('010101'));
        self::assertTrue($this->catalog->hasOperationIndicator('030103'));
        self::assertFalse($this->catalog->hasOperationIndicator('030103', $nt009));
        self::assertSame([
            OfficialDomainCatalog::OPERATION_INDICATOR_VERSION,
            OfficialDomainCatalog::OPERATION_INDICATOR_NT009_VERSION,
        ], OfficialDomainCatalog::operationIndicatorVersions());
    }

    public function testUnknownIndicatorVersionsFailClosed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported NFS-e operation-indicator version');
        $this->catalog->operationIndicator('010101', 'future-unverified');
    }

    public function testAllFortyOfficialNt009IndicatorsAreAvailableOffline(): void
    {
        $table = dirname(__DIR__, 3) . '/resources/domains/indicadores-operacao-ibscbs-v1.03.00.tsv';
        $lines = file($table, FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        $found = [];
        foreach ($lines as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            [$code, $characteristic, $location] = explode("\t", $line);
            self::assertSame(
                ['code' => $code, 'characteristic' => $characteristic, 'location' => $location],
                $this->catalog->operationIndicator($code, OfficialDomainCatalog::OPERATION_INDICATOR_NT009_VERSION),
            );
            $found[$code] = true;
        }
        self::assertCount(40, $found);
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
