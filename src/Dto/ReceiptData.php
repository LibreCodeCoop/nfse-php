<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/**
 * Data returned by the SEFIN gateway after a successful NFS-e issuance.
 */
final readonly class ReceiptData
{
    public function __construct(
        /** Número da NFS-e atribuído pela prefeitura. */
        public string $nfseNumber,

        /** Chave de acesso (UUID or similar gateway identifier). */
        public string $chaveAcesso,

        /** Data/hora de competência no formato ISO 8601. */
        public string $dataEmissao,

        /** Código de verificação (optional — some gateways don't return it). */
        public ?string $codigoVerificacao = null,

        /** Raw XML returned by the gateway (useful for storage / audit). */
        public ?string $rawXml = null,
    ) {
    }
}
