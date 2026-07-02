<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\Enum\TributacaoISSQN;
use LibreCodeCoop\NfsePHP\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @covers \LibreCodeCoop\NfsePHP\Danfse\Enum\TributacaoISSQN
 */
class TributacaoISSQNTest extends TestCase
{
    #[DataProvider('labelProvider')]
    public function testLabelFor(string $value, string $expected): void
    {
        self::assertSame($expected, TributacaoISSQN::labelFor($value));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function labelProvider(): array
    {
        return [
            'operação tributável' => ['1', 'Operação Tributável'],
            'imunidade' => ['2', 'Imunidade'],
            'exportação de serviço' => ['3', 'Exportação de Serviço'],
            'não incidência' => ['4', 'Não Incidência'],
        ];
    }

    #[DataProvider('unknownValueProvider')]
    public function testUnknownValueReturnsDash(string $value): void
    {
        self::assertSame('-', TributacaoISSQN::labelFor($value));
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
