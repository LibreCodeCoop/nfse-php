<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/** Choice dFeNacional inside a document-based base adjustment. */
final readonly class Nt009NationalInvoiceReference
{
    public function __construct(
        public string $tipoChaveDfe,
        public string $chaveDfe,
        public ?string $descricaoTipoChaveDfe = null,
    ) {
    }
}
