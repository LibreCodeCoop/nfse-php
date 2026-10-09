<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/** Draft-only condominium group; source Annex VI 408-421. */
final readonly class Nt009CondominiumData
{
    /**
     * @param list<Nt009CondominiumChargeData> $cobrancas
     * @param list<Nt009CondominiumDiscountData> $descontos
     */
    public function __construct(
        public string $vencimentoOriginal,
        public array $cobrancas,
        public array $descontos = [],
    ) {
    }
}
