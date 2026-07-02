<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Danfse\Enum;

enum OptanteSimplesNacional: int
{
    use LabelFromIntValue;

    case NAO_OPTANTE = 1;
    case MEI = 2;
    case ME_EPP = 3;

    public function label(): string
    {
        return match ($this) {
            self::NAO_OPTANTE => 'Não Optante',
            self::MEI => 'Optante - Microempreendedor Individual (MEI)',
            self::ME_EPP => 'Optante - Microempresa ou Empresa de Pequeno Porte (ME/EPP)',
        };
    }
}
