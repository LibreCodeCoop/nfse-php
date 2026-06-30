<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Danfse\Enum;

enum TpRetISSQN: int
{
    case NAO_RETIDO = 1;
    case RETIDO_TOMADOR = 2;
    case RETIDO_INTERMEDIARIO = 3;

    public function label(): string
    {
        return match ($this) {
            self::NAO_RETIDO => 'Não Retido',
            self::RETIDO_TOMADOR => 'Retido pelo Tomador',
            self::RETIDO_INTERMEDIARIO => 'Retido pelo Intermediário',
        };
    }

    public static function labelFor(string $value): string
    {
        if (!is_numeric($value)) {
            return '-';
        }

        return self::tryFrom((int) $value)?->label() ?? '-';
    }
}
