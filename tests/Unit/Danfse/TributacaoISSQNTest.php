<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\Enum\TributacaoISSQN;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * @covers \LibreCodeCoop\NfsePHP\Danfse\Enum\TributacaoISSQN
 */
class TributacaoISSQNTest extends TestCase
{
    public function testKnownLabels(): void
    {
        self::assertSame('Operação Tributável', TributacaoISSQN::labelFor('1'));
        self::assertSame('Imunidade', TributacaoISSQN::labelFor('2'));
        self::assertSame('Exportação de Serviço', TributacaoISSQN::labelFor('3'));
        self::assertSame('Não Incidência', TributacaoISSQN::labelFor('4'));
    }

    public function testUnknownValueReturnsDash(): void
    {
        self::assertSame('-', TributacaoISSQN::labelFor(''));
        self::assertSame('-', TributacaoISSQN::labelFor('99'));
        self::assertSame('-', TributacaoISSQN::labelFor('x'));
    }
}
