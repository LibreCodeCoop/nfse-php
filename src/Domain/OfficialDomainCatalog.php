<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Domain;

/**
 * Versioned, offline lookup of normative Sistema Nacional NFS-e domain tables.
 *
 * The advisory RTC Anexo VIII correlation table is intentionally not enforced
 * here because the official portal states that no production business rules
 * are based on it.
 */
final class OfficialDomainCatalog
{
    public const MUNICIPALITY_COUNTRY_VERSION = 'ANEXO_A v1.00 (20251210)';
    public const SERVICE_NBS_VERSION = 'ANEXO_B v1.01 (20260122)';
    public const OPERATION_INDICATOR_VERSION = 'ANEXO_C v1.01 (20260122)';
    public const OPERATION_INDICATOR_NT009_VERSION = 'ANEXO_VII v1.03.00 (NT009 v1.01)';

    /** @var array<string, array<string, list<string>>> */
    private array $cache = [];

    public function __construct(
        private readonly ?string $basePath = null,
    ) {
    }

    /**
     * @return array{code:string,uf:string,name:string}|null
     */
    public function municipality(string $code): ?array
    {
        $row = $this->table('municipios-ibge-v1.00.tsv', 3)[$code] ?? null;

        return $row === null ? null : [
            'code' => $row[0],
            'uf' => $row[1],
            'name' => $row[2],
        ];
    }

    /**
     * @return array{code:string,name:string}|null
     */
    public function country(string $iso2): ?array
    {
        $code = strtoupper(trim($iso2));
        $row = $this->table('paises-iso2-v1.00.tsv', 2)[$code] ?? null;

        return $row === null ? null : [
            'code' => $row[0],
            'name' => $row[1],
        ];
    }

    /**
     * @return array{code:string,description:string}|null
     */
    public function nationalService(string $code): ?array
    {
        $row = $this->table('servicos-nacionais-v1.01.tsv', 2)[$code] ?? null;

        return $row === null ? null : [
            'code' => $row[0],
            'description' => $row[1],
        ];
    }

    /**
     * @return array{code:string,description:string}|null
     */
    public function nbs(string $code): ?array
    {
        $row = $this->table('nbs-v2.0.tsv', 2)[$code] ?? null;

        return $row === null ? null : [
            'code' => $row[0],
            'description' => $row[1],
        ];
    }

    /**
     * @return array{code:string,characteristic:string,location:string}|null
     */
    public function operationIndicator(
        string $code,
        string $version = self::OPERATION_INDICATOR_VERSION,
    ): ?array {
        $file = match ($version) {
            self::OPERATION_INDICATOR_VERSION => 'indicadores-operacao-ibscbs-v1.01.tsv',
            self::OPERATION_INDICATOR_NT009_VERSION => 'indicadores-operacao-ibscbs-v1.03.00.tsv',
            default => throw new \InvalidArgumentException('Unsupported NFS-e operation-indicator version: ' . $version),
        };
        $row = $this->table($file, 3)[$code] ?? null;

        return $row === null ? null : [
            'code' => $row[0],
            'characteristic' => $row[1],
            'location' => $row[2],
        ];
    }

    public function hasMunicipality(string $code): bool
    {
        return $this->municipality($code) !== null;
    }

    public function hasCountry(string $iso2): bool
    {
        return $this->country($iso2) !== null;
    }

    /**
     * @return list<array{code:string,description:string}>
     */
    public function searchNationalServices(?string $query = null, int $limit = 200): array
    {
        $normalizedQuery = mb_strtolower(trim((string) $query), 'UTF-8');
        $limit = max(1, min($limit, 500));
        $results = [];

        foreach ($this->table('servicos-nacionais-v1.01.tsv', 2) as $row) {
            $code = $row[0];
            $description = $row[1];

            if (
                $normalizedQuery !== ''
                && !str_contains(mb_strtolower($code, 'UTF-8'), $normalizedQuery)
                && !str_contains(mb_strtolower($description, 'UTF-8'), $normalizedQuery)
            ) {
                continue;
            }

            $results[] = [
                'code' => $code,
                'description' => $description,
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    public function hasNationalService(string $code): bool
    {
        return $this->nationalService($code) !== null;
    }

    public function hasNbs(string $code): bool
    {
        return $this->nbs($code) !== null;
    }

    public function hasOperationIndicator(
        string $code,
        string $version = self::OPERATION_INDICATOR_VERSION,
    ): bool {
        return $this->operationIndicator($code, $version) !== null;
    }

    /**
     * @return list<string>
     */
    public static function operationIndicatorVersions(): array
    {
        return [
            self::OPERATION_INDICATOR_VERSION,
            self::OPERATION_INDICATOR_NT009_VERSION,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sourceVersions(): array
    {
        return [
            'municipality_country' => self::MUNICIPALITY_COUNTRY_VERSION,
            'service_nbs' => self::SERVICE_NBS_VERSION,
            'operation_indicator' => self::OPERATION_INDICATOR_VERSION,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function table(string $file, int $columns): array
    {
        if (isset($this->cache[$file])) {
            return $this->cache[$file];
        }

        $path = ($this->basePath ?? dirname(__DIR__, 2) . '/resources/domains') . '/' . $file;
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            throw new \RuntimeException('Unable to read official NFS-e domain table: ' . $file);
        }

        $rows = [];

        foreach ($lines as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $row = explode("\t", $line);

            if (count($row) < $columns) {
                throw new \RuntimeException('Malformed official NFS-e domain table row in ' . $file);
            }

            $row = array_slice($row, 0, $columns);
            $rows[$row[0]] = $row;
        }

        return $this->cache[$file] = $rows;
    }
}
