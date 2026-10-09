<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/**
 * Optional Annex VI rows 431-433. The caller selects the classification.
 */
final readonly class Nt009RegularTaxData
{
    public function __construct(
        public string $cst,
        public string $classificacao,
    ) {
    }
}
