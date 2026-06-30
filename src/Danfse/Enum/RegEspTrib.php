<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Danfse\Enum;

enum RegEspTrib: int
{
    case NENHUM = 0;
    case COOPERATIVA = 1;
    case ESTIMATIVA = 2;
    case MICROEMPRESA_MUNICIPAL = 3;
    case NOTARIO_REGISTRADOR = 4;
    case PROFISSIONAL_AUTONOMO = 5;
    case SOCIEDADE_PROFISSIONAIS = 6;

    public function label(): string
    {
        return match ($this) {
            self::NENHUM => 'Nenhum',
            self::COOPERATIVA => 'Ato Cooperado (Cooperativa)',
            self::ESTIMATIVA => 'Estimativa',
            self::MICROEMPRESA_MUNICIPAL => 'Microempresa Municipal',
            self::NOTARIO_REGISTRADOR => 'Notário ou Registrador',
            self::PROFISSIONAL_AUTONOMO => 'Profissional Autônomo',
            self::SOCIEDADE_PROFISSIONAIS => 'Sociedade de Profissionais',
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
