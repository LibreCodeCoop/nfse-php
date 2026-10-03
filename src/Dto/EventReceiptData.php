<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/**
 * Event returned by the SEFIN Nacional event query endpoint.
 */
final readonly class EventReceiptData
{
    public function __construct(
        public int $tipoAmbiente,
        public string $versaoAplicativo,
        public string $dataHoraProcessamento,
        public string $rawXml,
    ) {
    }
}
