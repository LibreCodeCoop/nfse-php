<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\Data\Municipios;
use LibreCodeCoop\NfsePHP\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @covers \LibreCodeCoop\NfsePHP\Danfse\Data\Municipios
 */
class MunicipiosTest extends TestCase
{
    #[DataProvider('lookupProvider')]
    public function testLookup(string|int $code, string $expected): void
    {
        self::assertSame($expected, Municipios::lookup($code));
    }

    /**
     * @return array<string, array{string|int, string}>
     */
    public static function lookupProvider(): array
    {
        return [
            'known code as string' => ['3550308', 'São Paulo - SP'],
            'known code as int' => [3303302, 'Niterói - RJ'],
            'unknown code returns raw' => ['9999999', '9999999'],
        ];
    }
}
