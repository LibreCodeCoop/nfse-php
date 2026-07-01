<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\Enum\TipoRetencaoISSQN;
use LibreCodeCoop\NfsePHP\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @covers \LibreCodeCoop\NfsePHP\Danfse\Enum\TipoRetencaoISSQN
 */
class TipoRetencaoISSQNTest extends TestCase
{
    #[DataProvider('labelProvider')]
    public function testLabelFor(string $value, string $expected): void
    {
        self::assertSame($expected, TipoRetencaoISSQN::labelFor($value));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function labelProvider(): array
    {
        return [
            'não retido' => ['1', 'Não Retido'],
            'retido pelo tomador' => ['2', 'Retido pelo Tomador'],
            'retido pelo intermediário' => ['3', 'Retido pelo Intermediário'],
        ];
    }

    #[DataProvider('unknownValueProvider')]
    public function testUnknownValueReturnsDash(string $value): void
    {
        self::assertSame('-', TipoRetencaoISSQN::labelFor($value));
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
