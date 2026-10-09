<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/**
 * One entry of IBSCBS/gPgtoVinc/pgto, as published in Annex VI v1.04.01.
 */
final readonly class Nt009LinkedPaymentData
{
    public function __construct(
        /** nPag: unique 1-3 digit payment number. */
        public string $numeroPagamento,
        /** idTransacao: unique 2-35 character financial transaction identifier. */
        public string $identificadorTransacao,
        /** tpMeioPgto: published means of payment, e.g. 17 (dynamic PIX). */
        public string $tipoMeioPagamento,
        /** CNPJReceb: 14-character recipient CNPJ, potentially alphanumeric. */
        public string $cnpjRecebedor,
        /** CNPJBasePSP: 8-character base of the payment service provider CNPJ. */
        public string $cnpjBasePsp,
    ) {
    }
}
