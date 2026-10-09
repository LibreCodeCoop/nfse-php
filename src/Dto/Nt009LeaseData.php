<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/** Draft-only property lease group gLocacao. */
final readonly class Nt009LeaseData
{
    public function __construct(
        public string $percentualCopropriedade,
        public string $valorTotalOperacao,
        public ?string $descontoIncondicional = null,
        public ?string $descontoCondicional = null,
        public ?string $dataVencimentoOriginal = null,
    ) {
    }
}
