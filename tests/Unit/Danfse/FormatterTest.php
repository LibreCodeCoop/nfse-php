<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\Formatter;
use LibreCodeCoop\NfsePHP\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @covers \LibreCodeCoop\NfsePHP\Danfse\Formatter
 */
class FormatterTest extends TestCase
{
    private Formatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new Formatter();
    }

    #[DataProvider('cnpjCpfProvider')]
    public function testCnpjCpf(string $input, string $expected): void
    {
        self::assertSame($expected, $this->formatter->cnpjCpf($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function cnpjCpfProvider(): array
    {
        return [
            'formatted cnpj' => ['11222333000181', '11.222.333/0001-81'],
            'formatted cpf' => ['12345678909', '123.456.789-09'],
            'empty returns dash' => ['', '-'],
            'dash returns dash' => ['-', '-'],
            'unexpected length returns raw' => ['12345', '12345'],
        ];
    }

    #[DataProvider('phoneProvider')]
    public function testPhone(string $input, string $expected): void
    {
        self::assertSame($expected, $this->formatter->phone($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function phoneProvider(): array
    {
        return [
            'eight-digit local' => ['2130001234', '(21) 3000-1234'],
            'nine-digit mobile' => ['11987654321', '(11) 98765-4321'],
        ];
    }

    #[DataProvider('cepProvider')]
    public function testCep(string $input, string $expected): void
    {
        self::assertSame($expected, $this->formatter->cep($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function cepProvider(): array
    {
        return [
            'formatted cep' => ['24020005', '24020-005'],
            'empty returns dash' => ['', '-'],
        ];
    }

    #[DataProvider('dateProvider')]
    public function testDate(string $input, string $expected): void
    {
        self::assertSame($expected, $this->formatter->date($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function dateProvider(): array
    {
        return [
            'iso date' => ['2026-01-15', '15/01/2026'],
            'empty returns dash' => ['', '-'],
        ];
    }

    #[DataProvider('dateTimeProvider')]
    public function testDateTime(string $input, string $expected): void
    {
        self::assertSame($expected, $this->formatter->dateTime($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function dateTimeProvider(): array
    {
        return [
            'iso datetime with offset' => ['2026-01-15T14:30:00-03:00', '15/01/2026 14:30:00'],
        ];
    }

    #[DataProvider('currencyProvider')]
    public function testCurrency(string $input, string $expected): void
    {
        self::assertSame($expected, $this->formatter->currency($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function currencyProvider(): array
    {
        return [
            'thousands' => ['1500.00', 'R$ 1.500,00'],
            'cents' => ['1292.75', 'R$ 1.292,75'],
            'empty returns dash' => ['', '-'],
        ];
    }

    #[DataProvider('codTribNacionalProvider')]
    public function testCodTribNacional(string $input, string $expected): void
    {
        self::assertSame($expected, $this->formatter->codTribNacional($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function codTribNacionalProvider(): array
    {
        return [
            'formatted code' => ['010700', '01.07.00'],
            'empty returns dash' => ['', '-'],
        ];
    }

    #[DataProvider('limitProvider')]
    public function testLimit(string $input, int $max, string $expected): void
    {
        self::assertSame($expected, $this->formatter->limit($input, $max));
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function limitProvider(): array
    {
        return [
            'shorter than limit is untouched' => ['abc', 5, 'abc'],
            'longer than limit is truncated with ellipsis' => ['abcdefgh', 5, 'abcde...'],
        ];
    }
}
