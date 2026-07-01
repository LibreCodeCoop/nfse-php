<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\Enum\OptanteSimplesNacional;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * @covers \LibreCodeCoop\NfsePHP\Danfse\Enum\OptanteSimplesNacional
 */
class OptanteSimplesNacionalTest extends TestCase
{
    public function testKnownLabels(): void
    {
        self::assertSame('Não Optante', OptanteSimplesNacional::labelFor('1'));
        self::assertStringContainsString('MEI', OptanteSimplesNacional::labelFor('2'));
        self::assertStringContainsString('ME/EPP', OptanteSimplesNacional::labelFor('3'));
    }

    public function testUnknownValueReturnsDash(): void
    {
        self::assertSame('-', OptanteSimplesNacional::labelFor(''));
        self::assertSame('-', OptanteSimplesNacional::labelFor('99'));
        self::assertSame('-', OptanteSimplesNacional::labelFor('x'));
    }
}
