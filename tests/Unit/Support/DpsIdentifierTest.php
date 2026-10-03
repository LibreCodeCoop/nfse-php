<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Support;

use LibreCodeCoop\NfsePHP\Dto\DpsData;
use LibreCodeCoop\NfsePHP\Support\DpsIdentifier;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * @covers \LibreCodeCoop\NfsePHP\Support\DpsIdentifier
 */
final class DpsIdentifierTest extends TestCase
{
    public function testBuildsIdentifierWithCnpjRegistrationType(): void
    {
        $dps = new DpsData(
            cnpjPrestador: '00000000E08G12',
            municipioIbge: '3303302',
            itemListaServico: '0107',
            valorServico: '100.00',
            aliquota: '5.00',
            discriminacao: 'Teste',
            serie: '1',
            numeroDps: '42',
        );

        self::assertSame(
            'DPS3303302200000000E08G1200001000000000000042',
            DpsIdentifier::fromData($dps),
        );
        self::assertSame(
            '3303302200000000E08G1200001000000000000042',
            DpsIdentifier::fromData($dps, false),
        );
    }

    public function testNormalizesOptionalPrefixForApi(): void
    {
        self::assertSame('3303302ABC', DpsIdentifier::forApi('DPS3303302ABC'));
        self::assertSame('3303302ABC', DpsIdentifier::forApi('dps3303302ABC'));
        self::assertSame('3303302ABC', DpsIdentifier::forApi(' 3303302ABC '));
    }

    public function testRejectsEmptyIdentifier(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DpsIdentifier::forApi('DPS');
    }
}
