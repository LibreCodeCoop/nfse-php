<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/** Draft-only gUnidImob entry and its physical address. */
final readonly class Nt009RealEstateUnitData
{
    /**
     * @param list<Nt009PropertyAdjustmentData> $ajustes
     */
    public function __construct(
        public string $cib,
        public string $cep,
        public string $logradouro,
        public string $numero,
        public ?string $bairro = null,
        public ?string $complemento = null,
        public ?string $inscricaoFiscal = null,
        public array $ajustes = [],
    ) {
    }
}
