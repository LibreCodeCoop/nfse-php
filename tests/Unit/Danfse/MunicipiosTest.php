<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\Data\Municipios;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * @covers \LibreCodeCoop\NfsePHP\Danfse\Data\Municipios
 */
class MunicipiosTest extends TestCase
{
    public function testLookupReturnsNameAndUf(): void
    {
        self::assertSame('São Paulo - SP', Municipios::lookup('3550308'));
        self::assertSame('Niterói - RJ', Municipios::lookup(3303302));
    }

    public function testLookupReturnsCodeWhenUnknown(): void
    {
        self::assertSame('9999999', Municipios::lookup('9999999'));
    }
}
