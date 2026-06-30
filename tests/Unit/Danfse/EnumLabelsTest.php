<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\Enum\OpSimpNac;
use LibreCodeCoop\NfsePHP\Danfse\Enum\RegApTribSN;
use LibreCodeCoop\NfsePHP\Danfse\Enum\RegEspTrib;
use LibreCodeCoop\NfsePHP\Danfse\Enum\TpRetISSQN;
use LibreCodeCoop\NfsePHP\Danfse\Enum\TribISSQN;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * @covers \LibreCodeCoop\NfsePHP\Danfse\Enum\OpSimpNac
 * @covers \LibreCodeCoop\NfsePHP\Danfse\Enum\RegApTribSN
 * @covers \LibreCodeCoop\NfsePHP\Danfse\Enum\RegEspTrib
 * @covers \LibreCodeCoop\NfsePHP\Danfse\Enum\TpRetISSQN
 * @covers \LibreCodeCoop\NfsePHP\Danfse\Enum\TribISSQN
 */
class EnumLabelsTest extends TestCase
{
    public function testKnownLabels(): void
    {
        self::assertSame('Não Optante', OpSimpNac::labelFor('1'));
        self::assertSame('Operação Tributável', TribISSQN::labelFor('1'));
        self::assertSame('Retido pelo Tomador', TpRetISSQN::labelFor('2'));
        self::assertSame('Sociedade de Profissionais', RegEspTrib::labelFor('6'));
        self::assertStringContainsString('Simples Nacional', RegApTribSN::labelFor('1'));
    }

    public function testUnknownValuesReturnDash(): void
    {
        self::assertSame('-', OpSimpNac::labelFor(''));
        self::assertSame('-', TribISSQN::labelFor('99'));
        self::assertSame('-', TpRetISSQN::labelFor('x'));
    }
}
