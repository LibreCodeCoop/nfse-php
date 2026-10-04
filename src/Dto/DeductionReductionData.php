<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/**
 * Standard deduction/reduction mode for DPS valores/vDedRed.
 *
 * The official XSD permits either pDR or vDR in this mode. Document-backed
 * deductions are modeled separately so callers cannot accidentally mix modes.
 */
final readonly class DeductionReductionData
{
    /**
     * @param list<DeductionDocumentData> $documentos
     */
    public function __construct(
        public string $percentual = '',
        public string $valor = '',
        public array $documentos = [],
    ) {
    }
}
