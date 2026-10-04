<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/**
 * Municipal benefit (BM) declared by an official municipal parameter id.
 */
final readonly class MunicipalBenefitData
{
    public function __construct(
        public string $identificador,
        public string $valorReducaoBaseCalculo = '',
        public string $percentualReducaoBaseCalculo = '',
    ) {
    }
}
