<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/** Draft-only gAjusteBCLocImoveis entry for one property unit. */
final readonly class Nt009PropertyAdjustmentData
{
    public function __construct(
        public string $tipo,
        public string $valor,
        public ?string $descricaoTipo = null,
    ) {
    }
}
