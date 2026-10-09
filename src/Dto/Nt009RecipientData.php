<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/**
 * Explicit destination identity for indDest=1; never inferred from the taker.
 */
final readonly class Nt009RecipientData
{
    public function __construct(
        public string $nome,
        public ?string $cnpj = null,
        public ?string $cpf = null,
        public ?string $nif = null,
        public ?int $codigoNaoNif = null,
        public ?Nt009RecipientAddressData $endereco = null,
        public ?string $telefone = null,
        public ?string $email = null,
    ) {
    }
}
