<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/** Choice docFiscalOutro inside a document-based base adjustment. */
final readonly class Nt009OtherFiscalReference
{
    public function __construct(
        public string $municipioIbge,
        public string $numeroDocumento,
        public string $descricaoDocumento,
    ) {
    }
}
