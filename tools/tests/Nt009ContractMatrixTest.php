<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tools\Tests;

use LibreCodeCoop\NfsePHP\Tools\Nt009ContractMatrix;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

final class Nt009ContractMatrixTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/nfse-matrix-' . bin2hex(random_bytes(8));
        mkdir($this->dir, 0o700);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testChangedOccurrencesMovedFieldsAndRemovedFieldsAreExplicit(): void
    {
        $old = $this->makeXlsx('old.xlsx', [
            ['NFSe/infNFSe/DPS/infDPS/IBSCBS/', 'finNFSe', 'E', 'N', '1-1', '1', 'Finalidade', ''],
            ['NFSe/infNFSe/DPS/infDPS/IBSCBS/valores/trib/gIBSCBS/', 'CST', 'E', 'N', '1-1', '3', 'CST', ''],
            ['NFSe/infNFSe/DPS/infDPS/IBSCBS/', 'cIndOp', 'E', 'N', '1-1', '6', 'Indicador', ''],
        ]);
        $new = $this->makeXlsx('new.xlsx', [
            ['NFSe/infNFSe/DPS/infDPS/', 'finNFSe', 'E', 'N', '1-1', '1', 'Finalidade', ''],
            ['NFSe/infNFSe/DPS/infDPS/IBSCBS/valores/trib/', 'CST', 'E', 'N', '1-1', '3', 'CST', ''],
            ['NFSe/infNFSe/DPS/infDPS/IBSCBS/', 'cIndOp', 'E', 'N', '0-1', '6', 'Indicador', 'Condicional'],
        ]);
        $matrix = new Nt009ContractMatrix();
        $rows = $matrix->compare($old, $new);
        self::assertCount(5, $rows);
        self::assertSame(
            ['added', 'added', 'changed', 'removed', 'removed'],
            array_column($rows, 'status')
        );
        self::assertSame('1-1', $rows[2]['old_occurrence']);
        self::assertSame('0-1', $rows[2]['new_occurrence']);
        self::assertSame('Condicional', $rows[2]['new_condition']);
        self::assertStringContainsString('NFSe/infNFSe/DPS/infDPS/finNFSe', $matrix->toTsv($rows));
        self::assertStringContainsString('old_occurrence', $matrix->toTsv($rows));
    }

    public function testRejectsMissingLayoutWorksheet(): void
    {
        $filename = $this->makeXlsx('bad.xlsx', [
            ['NFSe/infNFSe/DPS/infDPS/', 'finNFSe', 'E', 'N', '1-1', '1', '', ''],
        ], 'Unrelated');
        $matrix = new Nt009ContractMatrix();
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('DPS layout worksheet not found');
        $matrix->compare($filename, $filename);
    }

    /**
     * @param list<list<string>> $fields
     */
    private function makeXlsx(string $file, array $fields, string $sheetName = 'LEIAUTE DPS_NFS-e - RT'): string
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle($sheetName);
        foreach ($fields as $index => $row) {
            foreach ($row as $col => $value) {
                if ($value !== '') {
                    $sheet->setCellValue([$col + 2, $index + 1], $value);
                }
            }
        }
        $path = $this->dir . '/' . $file;
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }
}
