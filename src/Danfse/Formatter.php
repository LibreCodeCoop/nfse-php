<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Danfse;

/**
 * Formatters for Brazilian patterns (CNPJ/CPF, phone, CEP, currency, dates).
 *
 * Every formatter returns '-' for empty input so the template never shows blanks.
 */
final class Formatter
{
    public function cnpjCpf(string $value): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits) ?? $digits;
        }

        if (strlen($digits) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits) ?? $digits;
        }

        return $digits;
    }

    public function phone(string $value): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits) ?? $digits;
        }

        if (strlen($digits) === 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits) ?? $digits;
        }

        return $digits;
    }

    public function cep(string $value): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) === 8) {
            return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $digits) ?? $digits;
        }

        return $digits;
    }

    public function date(string $value): string
    {
        return $this->reformat($value, 'd/m/Y');
    }

    public function dateTime(string $value): string
    {
        return $this->reformat($value, 'd/m/Y H:i:s');
    }

    public function currency(string|float $value): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    /**
     * Format the national taxation code to the XX.XX.XX pattern.
     */
    public function codTribNacional(string $value): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) === 6) {
            return preg_replace('/(\d{2})(\d{2})(\d{2})/', '$1.$2.$3', $digits) ?? $digits;
        }

        return $digits;
    }

    public function limit(string $value, int $limit, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit) . $end;
    }

    private function reformat(string $value, string $format): string
    {
        if ($value === '' || $value === '-') {
            return '-';
        }

        try {
            return (new \DateTimeImmutable($value))->format($format);
        } catch (\Exception) {
            return $value;
        }
    }
}
