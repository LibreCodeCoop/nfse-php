<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\Enum\TipoRetencaoISSQN;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * @covers \LibreCodeCoop\NfsePHP\Danfse\Enum\TipoRetencaoISSQN
 */
class TipoRetencaoISSQNTest extends TestCase
{
    public function testKnownLabels(): void
    {
        self::assertSame('Não Retido', TipoRetencaoISSQN::labelFor('1'));
        self::assertSame('Retido pelo Tomador', TipoRetencaoISSQN::labelFor('2'));
        self::assertSame('Retido pelo Intermediário', TipoRetencaoISSQN::labelFor('3'));
    }

    public function testUnknownValueReturnsDash(): void
    {
        self::assertSame('-', TipoRetencaoISSQN::labelFor(''));
        self::assertSame('-', TipoRetencaoISSQN::labelFor('99'));
        self::assertSame('-', TipoRetencaoISSQN::labelFor('x'));
    }
}
