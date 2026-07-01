<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\Formatter;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * @covers \LibreCodeCoop\NfsePHP\Danfse\Formatter
 */
class FormatterTest extends TestCase
{
    private Formatter $formatter;

    protected function setUp(): void
    {
        $this->fmt = new Formatter();
    }

    public function testCnpjIsFormatted(): void
    {
        self::assertSame('11.222.333/0001-81', $this->fmt->cnpjCpf('11222333000181'));
    }

    public function testCpfIsFormatted(): void
    {
        self::assertSame('123.456.789-09', $this->fmt->cnpjCpf('12345678909'));
    }

    public function testCnpjCpfReturnsDashForEmpty(): void
    {
        self::assertSame('-', $this->fmt->cnpjCpf(''));
        self::assertSame('-', $this->fmt->cnpjCpf('-'));
    }

    public function testCnpjCpfReturnsRawWhenLengthUnexpected(): void
    {
        self::assertSame('12345', $this->fmt->cnpjCpf('12345'));
    }

    public function testPhoneWithEightDigitsLocalAndMobile(): void
    {
        self::assertSame('(21) 3000-1234', $this->fmt->phone('2130001234'));
        self::assertSame('(11) 98765-4321', $this->fmt->phone('11987654321'));
    }

    public function testCepIsFormatted(): void
    {
        self::assertSame('24020-005', $this->fmt->cep('24020005'));
        self::assertSame('-', $this->fmt->cep(''));
    }

    public function testDateAndDateTime(): void
    {
        self::assertSame('15/01/2026', $this->fmt->date('2026-01-15'));
        self::assertSame('15/01/2026 14:30:00', $this->fmt->dateTime('2026-01-15T14:30:00-03:00'));
        self::assertSame('-', $this->fmt->date(''));
    }

    public function testCurrency(): void
    {
        self::assertSame('R$ 1.500,00', $this->fmt->currency('1500.00'));
        self::assertSame('R$ 1.292,75', $this->fmt->currency('1292.75'));
        self::assertSame('-', $this->fmt->currency(''));
    }

    public function testCodTribNacional(): void
    {
        self::assertSame('01.07.00', $this->fmt->codTribNacional('010700'));
        self::assertSame('-', $this->fmt->codTribNacional(''));
    }

    public function testLimitTruncatesWithEllipsis(): void
    {
        self::assertSame('abc', $this->fmt->limit('abc', 5));
        self::assertSame('abcde...', $this->fmt->limit('abcdefgh', 5));
    }
}
