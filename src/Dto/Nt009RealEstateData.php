<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/** Draft-only IBSCBS/imovel group, Annex VI rows 383-403. */
final readonly class Nt009RealEstateData
{
    /**
     * @param list<Nt009RealEstateUnitData> $unidades
     */
    public function __construct(
        public string $municipioIbge,
        public ?Nt009LeaseData $locacao = null,
        public array $unidades = [],
    ) {
    }
}
