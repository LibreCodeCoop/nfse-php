<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/**
 * Review-only NT009 recipient address. National or foreign fields are mutually
 * exclusive and are validated by the preview builder.
 */
final readonly class Nt009RecipientAddressData
{
    public function __construct(
        public string $logradouro,
        public string $numero,
        public string $bairro,
        public ?string $municipioIbge = null,
        public ?string $cep = null,
        public ?string $paisIso2 = null,
        public ?string $codigoPostalExterior = null,
        public ?string $cidadeExterior = null,
        public ?string $estadoExterior = null,
        public ?string $complemento = null,
    ) {
    }
}
