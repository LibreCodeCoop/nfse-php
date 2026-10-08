<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tools;

/**
 * Reviewable data and layout-row evidence for the NT009 source files.
 *
 * An annex spreadsheet is not an executable XSD. The presence of a documented
 * tag is NOT treated as proof that this tag is mandatory or deployed.
 */
final class Nt009Inspector
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(string $annexVI, string $annexVII, string $legacyTable): array
    {
        $reader = new AnnexReader($annexVI);
        $layout = null;
        foreach ($reader->sheetNames() as $name) {
            if (str_contains(mb_strtoupper($name, 'UTF-8'), 'LEIAUTE DPS')) {
                $layout = $name;
                break;
            }
        }
        if ($layout === null) {
            throw new \UnexpectedValueException('NT009 Annex VI DPS layout worksheet is missing');
        }
        try {
            $matches = [];
            foreach ($reader->rows($layout, true) as $index => $columns) {
                foreach ($columns as $value) {
                    if (in_array($value, [
                        'IBSCBS', 'cIndOp', 'CST', 'cClassTrib', 'gIBSCBS',
                        'finNFSe', 'indDest', 'dest', 'regApIBSCBSSN',
                        'tpNFSeDebito', 'tpNFSeCredito', 'gPgtoVinc',
                        'gIBSCBSAjuste', 'indDoacao', 'indZFMALC',
                    ], true)) {
                        $matches[] = [
                            'worksheet' => $layout,
                            'row' => $index + 1,
                            'tag' => $value,
                            'raw_columns' => $columns,
                        ];
                    }
                }
            }
            if ($matches === []) {
                throw new \UnexpectedValueException('No NT009 DPS field rows found');
            }
        } finally {
            $reader->close();
        }

        $vii = (new DomainTableGenerator())->indicatorRows($annexVII);
        $old = $this->readTsv($legacyTable);
        $latest = [];
        foreach ($vii as $row) {
            if (isset($latest[$row[0]])) {
                throw new \UnexpectedValueException("Repeated Annex VII indicator {$row[0]}");
            }
            $latest[$row[0]] = [$row[1], $row[2]];
        }
        $added = [];
        $changed = [];
        $removed = [];
        foreach ($latest as $code => $fields) {
            if (!isset($old[$code])) {
                $added[$code] = $fields;
            } elseif ($old[$code] !== $fields) {
                $changed[$code] = ['old' => $old[$code], 'new' => $fields];
            }
        }
        foreach ($old as $code => $fields) {
            if (!isset($latest[$code])) {
                $removed[$code] = $fields;
            }
        }
        ksort($added);
        ksort($changed);
        ksort($removed);

        return [
            'note' => 'Source-level audit only. Validate conditionality, XSD, environment and effective date before emission.',
            'layout_rows' => $matches,
            'annex_vii_count' => count($latest),
            'previous_count' => count($old),
            'added_codes' => $added,
            'changed_codes' => $changed,
            'removed_codes' => $removed,
            'annex_vii_rows' => $latest,
        ];
    }

    /**
     * Verify every code, characteristic and location in the frozen Annex VII
     * snapshot against an original XLSX file, preserving the official row order.
     */
    public function verifyIndicatorSnapshot(string $workbook, string $snapshot, int $expectedCount): int
    {
        if ($expectedCount < 1) {
            throw new \InvalidArgumentException('Expected indicator cardinality must be positive');
        }
        $rows = (new DomainTableGenerator())->indicatorRows($workbook);
        $published = [];
        foreach ($rows as $row) {
            if (isset($published[$row[0]])) {
                throw new \UnexpectedValueException('Duplicate indicator in official Annex VII: ' . $row[0]);
            }
            $published[$row[0]] = [$row[1], $row[2]];
        }
        $committed = $this->readTsv($snapshot);
        if (count($published) !== $expectedCount || count($committed) !== $expectedCount
            || $published !== $committed) {
            throw new \UnexpectedValueException('Versioned Annex VII does not match original XLSX triplets');
        }

        return count($published);
    }

    /**
     * @return array<string, list<string>>
     */
    private function readTsv(string $filename): array
    {
        $lines = @file($filename, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new \RuntimeException('Missing previous official domain snapshot');
        }
        $result = [];
        foreach ($lines as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $fields = explode("\t", $line);
            if (count($fields) !== 3 || !preg_match('/^[0-9]{6}$/D', $fields[0])) {
                throw new \UnexpectedValueException('Malformed versioned legacy indicator snapshot');
            }
            if (isset($result[$fields[0]])) {
                throw new \UnexpectedValueException('Duplicate legacy indicator code');
            }
            $result[$fields[0]] = [$fields[1], $fields[2]];
        }

        return $result;
    }
}
