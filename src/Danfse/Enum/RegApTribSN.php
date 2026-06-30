<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Danfse\Enum;

enum RegApTribSN: int
{
    use LabelFromIntValue;

    case SN_FEDERAL_MUNICIPAL = 1;
    case SN_FEDERAL_ISSQN_NFSE = 2;
    case NFSE_FEDERAL_MUNICIPAL = 3;

    public function label(): string
    {
        return match ($this) {
            self::SN_FEDERAL_MUNICIPAL => 'Regime de apuração dos tributos federais e municipal pelo Simples Nacional',
            self::SN_FEDERAL_ISSQN_NFSE => 'Regime de apuração dos tributos federais pelo SN e o ISSQN pela NFS-e conforme respectiva legislação municipal do tributo',
            self::NFSE_FEDERAL_MUNICIPAL => 'Regime de apuração dos tributos federais e municipal pela NFS-e conforme respectivas legislações federal e municipal de cada tributo',
        };
    }
}
