<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/** Choice docOutro inside a document-based base adjustment. */
final readonly class Nt009OtherDocumentReference
{
    public function __construct(
        public string $numero,
        public string $descricao,
    ) {
    }
}
