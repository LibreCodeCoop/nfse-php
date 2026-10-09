<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/**
 * Optional Annex VI rows 434-437. Exact textual percentages, not calculated.
 */
final readonly class Nt009DeferralData
{
    public function __construct(
        public string $percentualUf,
        public string $percentualMunicipio,
        public string $percentualCbs,
    ) {
    }
}
