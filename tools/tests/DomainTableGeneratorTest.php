<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tools\Tests;

use LibreCodeCoop\NfsePHP\Tools\AnnexReader;
use LibreCodeCoop\NfsePHP\Tools\DomainTableGenerator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

final class DomainTableGeneratorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/nfse-domain-' . bin2hex(random_bytes(8));
        mkdir($this->dir, 0o700);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($this->dir);
    }

    public function testGeneratorUsesExplicitColumnsAndProducesDeterministicTables(): void
    {
        [$a, $b, $c] = $this->fixtureFiles();
        $generator = new DomainTableGenerator();
        $first = $generator->generate($a, $b, $c);
        self::assertSame($first, $generator->generate($a, $b, $c));
        self::assertCount(5, $first);
        self::assertStringContainsString("3304557\tRJ\tRio de Janeiro\n", $first['municipios-ibge-v1.00.tsv']);
        self::assertStringContainsString("0000000\t\tÁGUAS MARÍTIMAS\n", $first['municipios-ibge-v1.00.tsv']);
        self::assertStringContainsString("BR\tBrasil\n", $first['paises-iso2-v1.00.tsv']);
        self::assertStringContainsString(
            "010101\tAnálise e desenvolvimento de sistemas.\n",
            $first['servicos-nacionais-v1.01.tsv']
        );
        self::assertStringContainsString("101011100\tServiço NBS válido\n", $first['nbs-v2.0.tsv']);
        self::assertStringNotContainsString("10101100\t", $first['nbs-v2.0.tsv']);
        self::assertStringContainsString(
            "020101\tExecução sobre bem imóvel\tLocalidade do imóvel\n",
            $first['indicadores-operacao-ibscbs-v1.01.tsv']
        );
        self::assertStringContainsString(
            "030101\tExecução sobre bem imóvel\tEstabelecimento do fornecedor\n",
            $first['indicadores-operacao-ibscbs-v1.01.tsv']
        );
    }

    public function testRefusesToAcceptOnlyCardinalityFromPartialOfficialWorkbooks(): void
    {
        [$a, $b, $c] = $this->fixtureFiles();
        $generator = new DomainTableGenerator();
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('expected 5571 rows; generated 2');
        $generator->validateCounts($generator->generate($a, $b, $c));
    }

    public function testNewAnnexVIINeedsIndependentlyVerifiedCountAndKeepsOldSnapshot(): void
    {
        [$a, $b, $c] = $this->fixtureFiles();
        $generator = new DomainTableGenerator();
        $tables = $generator->generate($a, $b, $c, $c);
        self::assertArrayHasKey('indicadores-operacao-ibscbs-v1.03.00.tsv', $tables);
        self::assertArrayHasKey('indicadores-operacao-ibscbs-v1.01.tsv', $tables);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('independently verified');
        $generator->validateCounts($tables);
    }

    public function testDuplicateCodeWithDifferentMeaningIsRejected(): void
    {
        [$a, $b, $c] = $this->fixtureFiles();
        $this->workbook('a-bad.xlsx', [
            'TAB.MUN_IBGE' => [
                ['UF', '', 'Município', 'Código'],
                ['', '', 'Rio de Janeiro', '3304557'],
                ['', '', 'Outro nome', '3304557'],
            ],
            'TAB.LOC.GERAL' => [['Código', 'Descrição']],
            'TAB.PAÍS_ISO2' => [['BR', 'Brasil']],
        ]);
        $generator = new DomainTableGenerator();
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Conflicting duplicate official code 3304557');
        $generator->generate($this->dir . '/a-bad.xlsx', $b, $c);
    }

    public function testSpreadsheetFormulaIsRejectedInsteadOfExecuted(): void
    {
        $path = $this->workbook('formula.xlsx', [
            'INDOP' => [
                ['', '', '', '', '', 'Característica', 'Código', 'Local'],
                ['', '', '', '', '', 'Serviço', '=SUM(1,2)', 'Localidade'],
            ],
        ]);
        $reader = new AnnexReader($path);
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Formula not allowed');
        try {
            $reader->rows('INDOP');
        } finally {
            $reader->close();
        }
    }

    /**
     * @return list<string>
     */
    private function fixtureFiles(): array
    {
        $a = $this->workbook('a.xlsx', [
            'TAB.MUN_IBGE' => [
                ['UF', '', 'Município', 'Código'],
                ['', '', 'Rio de Janeiro', '3304557'],
            ],
            'TAB.LOC.GERAL' => [
                ['Código', 'Descrição'],
                ['0000000', 'ÁGUAS MARÍTIMAS'],
            ],
            'TAB.PAÍS_ISO2' => [
                ['Código', 'País'],
                ['BR', 'Brasil'],
            ],
        ]);
        $b = $this->workbook('b.xlsx', [
            'LISTA.SERV.NAC.' => [
                ['Código', 'Descrição'],
                ['01.01.01', 'Análise e desenvolvimento de sistemas.'],
                ['01.01', 'Cabeçalho'],
            ],
            'LISTA.NBS_v2.0' => [
                ['Código', 'Descrição'],
                ['1.0101.11.00', 'Serviço NBS válido'],
                ['1.0101.11', 'Nível hierárquico'],
            ],
        ]);
        $c = $this->workbook('c.xlsx', [
            'INDOP' => [
                ['', '', '', 'Característica', '', '', 'Código', 'Local'],
                ['', '', '', 'Execução sobre bem imóvel', '', '01', '020101', 'Localidade do imóvel'],
                ['', '', '', '', '', '02', '030101', 'Estabelecimento do fornecedor'],
            ],
        ], ['INDOP' => ['F2:F3']]);

        return [$a, $b, $c];
    }

    /**
     * @param array<string, list<list<string>>> $sheets
     * @param array<string, list<string>> $merges
     */
    private function workbook(string $name, array $sheets, array $merges = []): string
    {
        $workbook = new Spreadsheet();
        $first = true;
        foreach ($sheets as $sheetName => $rows) {
            if ($first) {
                $sheet = $workbook->getActiveSheet();
                $sheet->setTitle($sheetName);
                $first = false;
            } else {
                $sheet = new Worksheet($workbook, $sheetName);
                $workbook->addSheet($sheet);
            }
            foreach ($rows as $rowNumber => $cells) {
                foreach ($cells as $column => $value) {
                    if ($value !== '') {
                        $sheet->setCellValue([$column + 1, $rowNumber + 1], $value);
                    }
                }
            }
            foreach ($merges[$sheetName] ?? [] as $range) {
                $sheet->mergeCells($range);
            }
        }
        $path = $this->dir . '/' . $name;
        (new Xlsx($workbook))->save($path);
        $workbook->disconnectWorksheets();

        return $path;
    }
}
