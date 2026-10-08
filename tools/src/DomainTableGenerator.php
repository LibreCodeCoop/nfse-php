<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tools;

/**
 * Generate offline versioned domain tables from source workbooks.
 * No part of the runtime library loads PhpSpreadsheet or accesses a portal.
 */
final class DomainTableGenerator
{
    private const UF = [
        '11' => 'RO', '12' => 'AC', '13' => 'AM', '14' => 'RR', '15' => 'PA',
        '16' => 'AP', '17' => 'TO', '21' => 'MA', '22' => 'PI', '23' => 'CE',
        '24' => 'RN', '25' => 'PB', '26' => 'PE', '27' => 'AL', '28' => 'SE',
        '29' => 'BA', '31' => 'MG', '32' => 'ES', '33' => 'RJ', '35' => 'SP',
        '41' => 'PR', '42' => 'SC', '43' => 'RS', '50' => 'MS', '51' => 'MT',
        '52' => 'GO', '53' => 'DF',
    ];

    /** @var array<string, int> */
    public const EXPECTED_COUNTS = [
        'municipios-ibge-v1.00.tsv' => 5571,
        'paises-iso2-v1.00.tsv' => 250,
        'servicos-nacionais-v1.01.tsv' => 338,
        'nbs-v2.0.tsv' => 918,
        'indicadores-operacao-ibscbs-v1.01.tsv' => 26,
    ];

    /** @var array<string, list<string>> */
    private const HEADERS = [
        'municipios-ibge-v1.00.tsv' => [
            '# Municípios IBGE — ANEXO_A-MUNICIPIO_IBGE-PAISES_ISO2-v1.00-SNNFSe-20251210',
            '# Formato: código IBGE<TAB>sigla UF<TAB>nome do município. UF vazia = localidade geral.',
            '# A sigla é derivada dos 2 primeiros dígitos do código (padrão IBGE): o anexo só a',
            '# preenche em 450 das 5570 linhas. A derivação foi conferida contra essas 450, contra',
            '# o enum TSUF do XSD e contra o conjunto de prefixos presentes no anexo.',
        ],
        'paises-iso2-v1.00.tsv' => [
            '# Países ISO 3166-1 alpha-2 — ANEXO_A-MUNICIPIO_IBGE-PAISES_ISO2-v1.00-SNNFSe-20251210',
            '# Formato: código ISO2<TAB>nome',
        ],
        'servicos-nacionais-v1.01.tsv' => [
            '# Lista de Serviços Nacional — ANEXO_B-NBS2-LISTA_SERVICO_NACIONAL-SNNFSe-v1.01-20260122',
            '# Gerado de doc/anexos/, aba LISTA.SERV.NAC. Formato: cTribNac<TAB>descrição',
        ],
        'nbs-v2.0.tsv' => [
            '# Nomenclatura Brasileira de Serviços 2.0 — ANEXO_B-NBS2-LISTA_SERVICO_NACIONAL-SNNFSe-v1.01-20260122',
            '# Aba LISTA.NBS_v2.0. Formato: cNBS (9 dígitos, sem pontos)<TAB>descrição.',
            '# Das 1210 linhas do anexo, 292 são níveis de hierarquia (5, 6, 7 e 8 dígitos) e não',
            '# são valores válidos de cNBS, que o XSD tipa como [0-9]{9}.',
            '# 999999999 vem sem descrição no anexo; é o código genérico, e é o que a NFS-e real',
            '# consultada em produção usa. Mantido com descrição vazia — não se inventa texto.',
        ],
        'indicadores-operacao-ibscbs-v1.01.tsv' => [
            '# Indicadores de operação IBS/CBS — ANEXO_C-INDOP_IBSCBS-SNNFSe-v1.01-20260122',
            '# Formato: cIndOp<TAB>característica do fornecimento<TAB>local a identificar no DF-e',
        ],
        'indicadores-operacao-ibscbs-v1.03.00.tsv' => [
            '# Indicadores de operação IBS/CBS — ANEXO_VII v1.03.00 (NT009 v1.01)',
            '# Formato: cIndOp<TAB>característica do fornecimento<TAB>local a identificar no DF-e',
        ],
    ];

    /**
     * @return array<string, string>
     */
    public function generate(string $annexA, string $annexB, string $annexC, ?string $annexVII = null): array
    {
        $a = new AnnexReader($annexA);
        $b = new AnnexReader($annexB);
        $c = new AnnexReader($annexC);
        try {
            $outputs = [
                'municipios-ibge-v1.00.tsv' => $this->render(
                    'municipios-ibge-v1.00.tsv',
                    $this->municipalities($a),
                ),
                'paises-iso2-v1.00.tsv' => $this->render(
                    'paises-iso2-v1.00.tsv',
                    $this->countries($a),
                ),
                'servicos-nacionais-v1.01.tsv' => $this->render(
                    'servicos-nacionais-v1.01.tsv',
                    $this->codeDescriptions($b, 'LISTA.SERV.NAC.', 6),
                ),
                'nbs-v2.0.tsv' => $this->render(
                    'nbs-v2.0.tsv',
                    $this->codeDescriptions($b, 'LISTA.NBS_v2.0', 9),
                ),
                'indicadores-operacao-ibscbs-v1.01.tsv' => $this->render(
                    'indicadores-operacao-ibscbs-v1.01.tsv',
                    $this->indicators($c),
                ),
            ];
        } finally {
            $a->close();
            $b->close();
            $c->close();
        }

        if ($annexVII !== null) {
            $vii = new AnnexReader($annexVII);
            try {
                $outputs['indicadores-operacao-ibscbs-v1.03.00.tsv'] = $this->render(
                    'indicadores-operacao-ibscbs-v1.03.00.tsv',
                    $this->indicators($vii),
                );
            } finally {
                $vii->close();
            }
        }

        return $outputs;
    }

    /**
     * @param array<string, string> $outputs
     */
    public function validateCounts(array $outputs, ?int $expectedVII = null): void
    {
        if (array_key_exists('indicadores-operacao-ibscbs-v1.03.00.tsv', $outputs)
            && ($expectedVII === null || $expectedVII < 1)) {
            throw new \InvalidArgumentException(
                'Annex VII requires an independently verified --expected-vii count',
            );
        }

        foreach (self::EXPECTED_COUNTS as $file => $expected) {
            $this->assertCount($file, $outputs[$file] ?? '', $expected);
        }

        if (array_key_exists('indicadores-operacao-ibscbs-v1.03.00.tsv', $outputs)) {
            $this->assertCount(
                'indicadores-operacao-ibscbs-v1.03.00.tsv',
                $outputs['indicadores-operacao-ibscbs-v1.03.00.tsv'],
                (int) $expectedVII
            );
        }
    }

    private function assertCount(string $file, string $data, int $expected): void
    {
        $actual = count(array_filter(
            explode("\n", $data),
            static fn (string $line): bool => $line !== '' && !str_starts_with($line, '#')
        ));
        if ($actual !== $expected) {
            throw new \UnexpectedValueException("{$file}: expected {$expected} rows; generated {$actual}");
        }
    }

    /**
     * @return list<list<string>>
     */
    private function municipalities(AnnexReader $book): array
    {
        $rows = [];
        $seen = [];
        foreach ($book->rows('TAB.MUN_IBGE') as $row) {
            $code = self::digits($row['D'] ?? '');
            $name = AnnexReader::clean($row['C'] ?? '');
            if (strlen($code) !== 7 || $name === '') {
                continue;
            }
            $uf = self::UF[substr($code, 0, 2)] ?? null;
            if ($uf === null) {
                throw new \UnexpectedValueException("Unexpected IBGE prefix: {$code}");
            }
            $this->appendCode($rows, $seen, [$code, $uf, $name]);
        }
        foreach ($book->rows('TAB.LOC.GERAL') as $row) {
            $values = array_values($row);
            foreach ($values as $value) {
                $code = self::digits($value);
                if (strlen($code) !== 7) {
                    continue;
                }
                $name = self::longestText($values, [$value]);
                if ($name !== '') {
                    $this->appendCode($rows, $seen, [$code, '', $name]);
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private function countries(AnnexReader $book): array
    {
        $rows = [];
        $seen = [];
        foreach ($book->rows('TAB.PAÍS_ISO2') as $row) {
            $values = array_values($row);
            $code = '';
            foreach ($values as $value) {
                if (preg_match('/^[A-Z]{2}$/D', $value)) {
                    $code = $value;
                    break;
                }
            }
            if ($code === '') {
                continue;
            }
            $name = self::longestText($values, [$code]);
            if ($name !== '') {
                $this->appendCode($rows, $seen, [$code, $name]);
            }
        }

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private function codeDescriptions(AnnexReader $book, string $sheet, int $length): array
    {
        $rows = [];
        $seen = [];
        foreach ($book->rows($sheet) as $row) {
            $values = array_values($row);
            $codeValue = null;
            foreach ($values as $value) {
                if (strlen(self::digits($value)) === $length) {
                    $codeValue = $value;
                    break;
                }
            }
            if ($codeValue === null) {
                continue;
            }
            $code = self::digits($codeValue);
            $description = self::longestText($values, [$codeValue, $code]);
            if ($description !== '' || $code === '999999999') {
                $this->appendCode($rows, $seen, [$code, $description]);
            }
        }

        return $rows;
    }

    /**
     * For the official Anexo C and VII "INDOP" sheet, column F is the supply
     * characteristic, G is cIndOp and H is the location. Never rank free-text
     * fields by length: that can silently misassign tax information.
     *
     * @return list<list<string>>
     */
    private function indicators(AnnexReader $book): array
    {
        $rows = [];
        $seen = [];
        foreach ($book->rows('INDOP') as $row) {
            $rawCode = $row['G'] ?? '';
            $code = self::digits($rawCode);
            if (strlen($code) !== 6 || !preg_match('/^[0-9.]+$/D', $rawCode)) {
                continue;
            }
            $characteristic = $row['F'] ?? '';
            $location = $row['H'] ?? '';
            if ($characteristic === '' || $location === '') {
                throw new \UnexpectedValueException("Incomplete operation indicator {$code} in INDOP F/G/H");
            }
            $this->appendCode($rows, $seen, [$code, $characteristic, $location]);
        }
        if ($rows === []) {
            throw new \UnexpectedValueException('No indicators found in official INDOP columns F/G/H');
        }

        return $rows;
    }

    /**
     * @param list<list<string>> $rows
     * @param array<string, list<string>> $seen
     * @param list<string> $entry
     */
    private function appendCode(array &$rows, array &$seen, array $entry): void
    {
        $code = $entry[0];
        if (isset($seen[$code])) {
            if ($seen[$code] !== $entry) {
                throw new \UnexpectedValueException("Conflicting duplicate official code {$code}");
            }

            return;
        }
        $seen[$code] = $entry;
        $rows[] = $entry;
    }

    /**
     * @param list<string> $values
     * @param list<string> $excluded
     */
    private static function longestText(array $values, array $excluded): string
    {
        $candidates = array_filter(
            $values,
            static fn (string $text): bool => $text !== ''
                && !in_array($text, $excluded, true)
                && preg_match('/^[0-9.\\/\\-]+$/D', $text) !== 1
        );
        usort($candidates, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $candidates[0] ?? '';
    }

    private static function digits(string $value): string
    {
        return (string) preg_replace('/[^0-9]/', '', $value);
    }

    /**
     * @param list<list<string>> $rows
     */
    private function render(string $name, array $rows): string
    {
        $result = self::HEADERS[$name];
        foreach ($rows as $row) {
            $result[] = implode("\t", $row);
        }

        return implode("\n", $result) . "\n";
    }
}
