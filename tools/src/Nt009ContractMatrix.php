<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tools;

/**
 * Deterministic, source-row-aware comparison of DPS layouts.
 *
 * The columns are read from the official Anexo I / Anexo VI spreadsheets:
 * B = parent XML path, C = tag, D = kind, E = type, F = occurrence,
 * G = size, H = description and I = conditional guidance.
 * Guidance is evidence for manual fiscal review, not an executable tax rule.
 */
final class Nt009ContractMatrix
{
    /**
     * @return list<array<string, string>>
     */
    public function compare(string $legacyWorkbook, string $nt009Workbook): array
    {
        $previous = $this->readDpsFields($legacyWorkbook);
        $latest = $this->readDpsFields($nt009Workbook);

        $result = [];
        foreach ($latest as $path => $row) {
            $old = $previous[$path] ?? null;
            $changed = $old !== null && $this->comparisonValues($old) !== $this->comparisonValues($row);
            $result[] = [
                'status' => $old === null ? 'added' : ($changed ? 'changed' : 'unchanged'),
                'path' => $path,
                'old_row' => $old['source_row'] ?? '',
                'new_row' => $row['source_row'],
                'old_kind' => $old['kind'] ?? '',
                'new_kind' => $row['kind'],
                'old_type' => $old['type'] ?? '',
                'new_type' => $row['type'],
                'old_occurrence' => $old['occurrence'] ?? '',
                'new_occurrence' => $row['occurrence'],
                'old_size' => $old['size'] ?? '',
                'new_size' => $row['size'],
                'old_description' => $old['description'] ?? '',
                'new_description' => $row['description'],
                'old_condition' => $old['condition'] ?? '',
                'new_condition' => $row['condition'],
            ];
        }
        foreach ($previous as $path => $old) {
            if (isset($latest[$path])) {
                continue;
            }
            $result[] = [
                'status' => 'removed',
                'path' => $path,
                'old_row' => $old['source_row'],
                'new_row' => '',
                'old_kind' => $old['kind'],
                'new_kind' => '',
                'old_type' => $old['type'],
                'new_type' => '',
                'old_occurrence' => $old['occurrence'],
                'new_occurrence' => '',
                'old_size' => $old['size'],
                'new_size' => '',
                'old_description' => $old['description'],
                'new_description' => '',
                'old_condition' => $old['condition'],
                'new_condition' => '',
            ];
        }

        if ($result === []) {
            throw new \UnexpectedValueException('No official DPS layout fields identified');
        }

        return $result;
    }

    /**
     * @param list<array<string,string>> $rows
     */
    public function toTsv(array $rows): string
    {
        if ($rows === []) {
            throw new \InvalidArgumentException('Empty NT009 contract matrix');
        }
        $keys = array_keys($rows[0]);
        $lines = [
            '# NT009 DPS layout comparison — source: official Anexo I (production) and Anexo VI v1.04.01',
            '# All conditions are textual evidence; production applicability and fiscal validation are separately gated.',
            implode("\t", $keys),
        ];
        foreach ($rows as $row) {
            if (array_keys($row) !== $keys) {
                throw new \UnexpectedValueException('Inconsistent contract-matrix columns');
            }
            $lines[] = implode("\t", array_values($row));
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return array<string, array{source_row:string,kind:string,type:string,occurrence:string,size:string,description:string,condition:string}>
     */
    private function readDpsFields(string $path): array
    {
        $workbook = new AnnexReader($path);
        try {
            $sheetName = null;
            foreach ($workbook->sheetNames() as $candidate) {
                if (str_contains(mb_strtoupper($candidate, 'UTF-8'), 'LEIAUTE DPS')) {
                    $sheetName = $candidate;
                    break;
                }
            }
            if ($sheetName === null) {
                throw new \UnexpectedValueException(
                    'DPS layout worksheet not found in ' . $path . ': ' . implode(', ', $workbook->sheetNames()),
                );
            }
            $result = [];
            foreach ($workbook->rows($sheetName, true) as $offset => $columns) {
                $parent = rtrim($columns['B'] ?? '', '/');
                $tag = $columns['C'] ?? '';
                if (!str_starts_with($parent . '/', 'NFSe/infNFSe/DPS/infDPS/')
                    || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $tag) !== 1) {
                    continue;
                }
                $fullPath = $parent . '/' . $tag;
                $entry = [
                    'source_row' => (string) ($offset + 1),
                    'kind' => $columns['D'] ?? '',
                    'type' => $columns['E'] ?? '',
                    'occurrence' => $columns['F'] ?? '',
                    'size' => $columns['G'] ?? '',
                    'description' => $columns['H'] ?? '',
                    'condition' => $columns['I'] ?? '',
                ];
                if (isset($result[$fullPath])) {
                    if ($this->comparisonValues($result[$fullPath]) !== $this->comparisonValues($entry)) {
                        throw new \UnexpectedValueException('Conflicting official DPS layout definitions: ' . $fullPath);
                    }
                    continue;
                }
                $result[$fullPath] = $entry;
            }
            if ($result === []) {
                throw new \UnexpectedValueException('No official DPS layout fields in ' . $sheetName);
            }

            return $result;
        } finally {
            $workbook->close();
        }
    }

    /**
     * @param array{source_row:string,kind:string,type:string,occurrence:string,size:string,description:string,condition:string} $entry
     * @return list<string>
     */
    private function comparisonValues(array $entry): array
    {
        return [
            $entry['kind'],
            $entry['type'],
            $entry['occurrence'],
            $entry['size'],
            $entry['description'],
            $entry['condition'],
        ];
    }
}
