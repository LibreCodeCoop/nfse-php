<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Danfse\Enum;

enum TribISSQN: int
{
    use LabelFromIntValue;

    case TRIBUTAVEL = 1;
    case IMUNIDADE = 2;
    case EXPORTACAO = 3;
    case NAO_INCIDENCIA = 4;

    public function label(): string
    {
        return match ($this) {
            self::TRIBUTAVEL => 'Operação Tributável',
            self::IMUNIDADE => 'Imunidade',
            self::EXPORTACAO => 'Exportação de Serviço',
            self::NAO_INCIDENCIA => 'Não Incidência',
        };
    }
}
