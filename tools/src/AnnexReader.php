<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tools;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Normalizes XLSX rows without guessing the meaning of columns.
 * Spreadsheet formulas are rejected, rather than evaluated from external input.
 */
final class AnnexReader
{
    private Spreadsheet $workbook;

    public function __construct(string $filename)
    {
        if (!is_file($filename)) {
            throw new \InvalidArgumentException('Official workbook not found: ' . $filename);
        }

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(false);
        $this->workbook = $reader->load($filename);
    }

    /**
     * @return list<string>
     */
    public function sheetNames(): array
    {
        return $this->workbook->getSheetNames();
    }

    /**
     * @return list<array<string, string>>
     */
    public function rows(string $sheetName, bool $skipFormulaCells = false): array
    {
        $sheet = null;
        foreach ($this->workbook->getWorksheetIterator() as $candidate) {
            if ($this->normalizedName($candidate->getTitle()) === $this->normalizedName($sheetName)) {
                $sheet = $candidate;
                break;
            }
        }

        if ($sheet === null) {
            throw new \InvalidArgumentException(
                "Missing worksheet {$sheetName}; found: " . implode(', ', $this->sheetNames()),
            );
        }

        $rows = [];
        $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        for ($r = 1; $r <= $sheet->getHighestDataRow(); ++$r) {
            $values = [];
            for ($i = 1; $i <= $lastColumn; ++$i) {
                $name = Coordinate::stringFromColumnIndex($i);
                $cell = $sheet->getCell($name . $r);
                if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                    if ($skipFormulaCells) {
                        // Spreadsheet helper formula: do not execute or trust its cached result.
                        continue;
                    }
                    throw new \UnexpectedValueException("Formula not allowed in {$sheetName}!{$name}{$r}");
                }
                $raw = $cell->getFormattedValue();
                if (!is_scalar($raw) && $raw !== null) {
                    throw new \UnexpectedValueException("Unsupported cell type in {$sheetName}!{$name}{$r}");
                }
                $clean = self::clean((string) $raw);
                if ($clean !== '') {
                    $values[$name] = $clean;
                }
            }
            $rows[] = $values;
        }

        // The official annexes use vertically merged cells for repeated captions.
        foreach ($sheet->getMergeCells() as $range) {
            if (!preg_match('/^([A-Z]+)([0-9]+):([A-Z]+)([0-9]+)$/', $range, $parts)) {
                continue;
            }
            [, $firstColumn, $firstRow, $lastColumnName, $lastRow] = $parts;
            if ($firstColumn !== $lastColumnName) {
                continue;
            }
            $value = $rows[(int) $firstRow - 1][$firstColumn] ?? '';
            if ($value === '') {
                continue;
            }
            for ($r = (int) $firstRow; $r <= (int) $lastRow; ++$r) {
                $rows[$r - 1][$firstColumn] ??= $value;
            }
        }

        return $rows;
    }

    public function close(): void
    {
        $this->workbook->disconnectWorksheets();
    }

    public static function clean(string $text): string
    {
        return trim((string) preg_replace('/\\s+/u', ' ', str_replace("\t", ' ', $text)));
    }

    private function normalizedName(string $name): string
    {
        return mb_strtoupper(strtr($name, [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A',
            'É' => 'E', 'Ê' => 'E', 'Í' => 'I', 'Ó' => 'O',
            'Ô' => 'O', 'Õ' => 'O', 'Ú' => 'U', 'Ç' => 'C',
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o',
            'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c',
        ]), 'UTF-8');
    }
}
