<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Dto;

use LibreCodeCoop\NfsePHP\Dto\SubstitutionData;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

final class SubstitutionDataTest extends TestCase
{
    public function testAcceptsOfficialSubstitutionReasons(): void
    {
        foreach (['01', '02', '03', '04', '05', '99'] as $reason) {
            $data = new SubstitutionData(
                chaveNfseSubstituida: str_repeat('1', 50),
                codigoMotivo: $reason,
            );

            self::assertSame($reason, $data->codigoMotivo);
        }
    }

    public function testRejectsInvalidAccessKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exactly 50 digits');

        new SubstitutionData('ACCESS-KEY', '01');
    }

    public function testRejectsUnknownReasonCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('01, 02, 03, 04, 05 or 99');

        new SubstitutionData(str_repeat('1', 50), '98');
    }

    public function testRejectsShortDescriptionWhenInformed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('between 15 and 255');

        new SubstitutionData(str_repeat('1', 50), '99', 'too short');
    }

    public function testAcceptsValidOptionalDescription(): void
    {
        $description = 'Correcao dos dados fiscais informados.';

        $data = new SubstitutionData(str_repeat('1', 50), '99', $description);

        self::assertSame($description, $data->descricaoMotivo);
    }
}
