<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

final readonly class DecisionIssuerAddressData
{
    public function __construct(
        public string $logradouro,
        public string $numero,
        public string $bairro,
        public string $municipioIbge,
        public string $uf,
        public string $cep,
        public string $complemento = '',
    ) {
    }
}
