<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\Enum\RegimeApuracaoTributariaSN;
use LibreCodeCoop\NfsePHP\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @covers \LibreCodeCoop\NfsePHP\Danfse\Enum\RegimeApuracaoTributariaSN
 */
class RegimeApuracaoTributariaSNTest extends TestCase
{
    #[DataProvider('labelProvider')]
    public function testLabelFor(string $value, string $expected): void
    {
        self::assertSame($expected, RegimeApuracaoTributariaSN::labelFor($value));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function labelProvider(): array
    {
        return [
            'federais e municipal pelo SN' => [
                '1',
                'Regime de apuração dos tributos federais e municipal pelo Simples Nacional',
            ],
            'federais pelo SN e ISSQN pela NFS-e' => [
                '2',
                'Regime de apuração dos tributos federais pelo SN e o ISSQN pela NFS-e conforme respectiva legislação municipal do tributo',
            ],
            'federais e municipal pela NFS-e' => [
                '3',
                'Regime de apuração dos tributos federais e municipal pela NFS-e conforme respectivas legislações federal e municipal de cada tributo',
            ],
        ];
    }

    #[DataProvider('unknownValueProvider')]
    public function testUnknownValueReturnsDash(string $value): void
    {
        self::assertSame('-', RegimeApuracaoTributariaSN::labelFor($value));
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
