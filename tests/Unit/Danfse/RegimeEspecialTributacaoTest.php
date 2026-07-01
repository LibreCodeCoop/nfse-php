<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\Enum\RegimeEspecialTributacao;
use LibreCodeCoop\NfsePHP\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @covers \LibreCodeCoop\NfsePHP\Danfse\Enum\RegimeEspecialTributacao
 */
class RegimeEspecialTributacaoTest extends TestCase
{
    #[DataProvider('labelProvider')]
    public function testLabelFor(string $value, string $expected): void
    {
        self::assertSame($expected, RegimeEspecialTributacao::labelFor($value));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function labelProvider(): array
    {
        return [
            'nenhum' => ['0', 'Nenhum'],
            'cooperativa' => ['1', 'Ato Cooperado (Cooperativa)'],
            'estimativa' => ['2', 'Estimativa'],
            'microempresa municipal' => ['3', 'Microempresa Municipal'],
            'notário ou registrador' => ['4', 'Notário ou Registrador'],
            'profissional autônomo' => ['5', 'Profissional Autônomo'],
            'sociedade de profissionais' => ['6', 'Sociedade de Profissionais'],
        ];
    }

    #[DataProvider('unknownValueProvider')]
    public function testUnknownValueReturnsDash(string $value): void
    {
        self::assertSame('-', RegimeEspecialTributacao::labelFor($value));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unknownValueProvider(): array
    {
        return [
            'empty' => [''],
            'out of range' => ['99'],
            'non-numeric' => ['x'],
        ];
    }
}
