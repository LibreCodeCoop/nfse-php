<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\Enum\RegimeApuracaoTributariaSN;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * @covers \LibreCodeCoop\NfsePHP\Danfse\Enum\RegimeApuracaoTributariaSN
 */
class RegimeApuracaoTributariaSNTest extends TestCase
{
    public function testKnownLabels(): void
    {
        self::assertStringContainsString('Simples Nacional', RegimeApuracaoTributariaSN::labelFor('1'));
        self::assertStringContainsString('ISSQN pela NFS-e', RegimeApuracaoTributariaSN::labelFor('2'));
        self::assertStringContainsString('NFS-e', RegimeApuracaoTributariaSN::labelFor('3'));
    }

    public function testUnknownValueReturnsDash(): void
    {
        self::assertSame('-', RegimeApuracaoTributariaSN::labelFor(''));
        self::assertSame('-', RegimeApuracaoTributariaSN::labelFor('99'));
        self::assertSame('-', RegimeApuracaoTributariaSN::labelFor('x'));
    }
}
