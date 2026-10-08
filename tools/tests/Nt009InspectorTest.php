<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tools\Tests;

use LibreCodeCoop\NfsePHP\Tools\Nt009Inspector;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

final class Nt009InspectorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/nfse-nt009-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($this->directory);
    }

    public function testComparesCompleteTripletsAndPreservesLayoutRowEvidence(): void
    {
        $vi = $this->workbook('vi.xlsx', 'LEIAUTE DPS_NFS-e - RT', [
            ['Tag', 'Cardinalidade', 'Condição'],
            ['IBSCBS', '0-1', 'condicional'],
            ['cIndOp', '0-1', 'condicional'],
            ['CST', '1-1', 'dentro de trib'],
        ]);
        $vii = $this->workbook('vii.xlsx', 'cIndOp Public', [
            ['Código indOp', 'Tipo de operação', 'Característica do fornecimento',
                'Local do fornecimento a ser identificado no DFe'],
            ['020101', 'Bem imóvel', 'Descrição alterada', 'Imóvel'],
            ['040101', 'Eventos', 'Serviço novo', 'Evento'],
        ]);
        $legacy = $this->directory . '/previous.tsv';
        file_put_contents($legacy, "# Previous production snapshot\n"
            . "020101\tDescrição antiga\tImóvel\n"
            . "030101\tOutro serviço\tPessoa\n");

        $result = (new Nt009Inspector())->inspect($vi, $vii, $legacy);
        self::assertSame(2, $result['annex_vii_count']);
        self::assertSame(2, $result['previous_count']);
        self::assertSame(['Serviço novo', 'Evento'], $result['added_codes']['040101']);
        self::assertSame(['Outro serviço', 'Pessoa'], $result['removed_codes']['030101']);
        self::assertSame(['Descrição antiga', 'Imóvel'], $result['changed_codes']['020101']['old']);
        self::assertSame(['Descrição alterada', 'Imóvel'], $result['changed_codes']['020101']['new']);
        self::assertSame('cIndOp', $result['layout_rows'][1]['tag']);
        self::assertSame('0-1', $result['layout_rows'][1]['raw_columns']['B']);
    }

    /**
     * @param list<list<string>> $rows
     */
    private function workbook(string $basename, string $sheetName, array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);
        foreach ($rows as $i => $row) {
            foreach ($row as $j => $value) {
                if ($value !== '') {
                    $sheet->setCellValue([$j + 1, $i + 1], $value);
                }
            }
        }
        $file = $this->directory . '/' . $basename;
        (new Xlsx($spreadsheet))->save($file);
        $spreadsheet->disconnectWorksheets();

        return $file;
    }
}
