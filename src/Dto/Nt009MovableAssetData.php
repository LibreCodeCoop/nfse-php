<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/**
 * One rental movable asset: IBSCBS/bensMoveis, only for cTribNac 99.04.01.
 */
final readonly class Nt009MovableAssetData
{
    public function __construct(
        /** cNCMBemMovel: NCM with exactly 8 digits. */
        public string $ncm,
        /** xNCMBemMovel: human-readable name (1-150 chars). */
        public string $descricao,
        /** qtdNCMBemMovel: positive whole quantity, at most three digits. */
        public int $quantidade,
    ) {
    }
}
