<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\Enum\RegimeEspecialTributacao;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * @covers \LibreCodeCoop\NfsePHP\Danfse\Enum\RegimeEspecialTributacao
 */
class RegimeEspecialTributacaoTest extends TestCase
{
    public function testKnownLabels(): void
    {
        self::assertSame('Nenhum', RegimeEspecialTributacao::labelFor('0'));
        self::assertSame('Sociedade de Profissionais', RegimeEspecialTributacao::labelFor('6'));
        self::assertSame('Profissional Autônomo', RegimeEspecialTributacao::labelFor('5'));
    }

    public function testUnknownValueReturnsDash(): void
    {
        self::assertSame('-', RegimeEspecialTributacao::labelFor(''));
        self::assertSame('-', RegimeEspecialTributacao::labelFor('99'));
        self::assertSame('-', RegimeEspecialTributacao::labelFor('x'));
    }
}
