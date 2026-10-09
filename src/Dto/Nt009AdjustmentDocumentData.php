<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/**
 * One document-backed vAjusteBC entry (Annex VI rows 297-335).
 * The reference is a discriminated PHP union; no arbitrary XML is accepted.
 */
final readonly class Nt009AdjustmentDocumentData
{
    public function __construct(
        /** tpAjusteBC: numeric official type (domain-specific applicability deferred). */
        public string $tipo,
        /** vTotDoc, represented as decimal string to avoid float calculations. */
        public string $valorTotalDocumento,
        /** vAjusteAplic, represented as decimal string. */
        public string $valorAjustado,
        public Nt009NationalInvoiceReference|Nt009OtherFiscalReference|Nt009OtherDocumentReference $referencia,
        public ?string $descricaoTipo = null,
        public ?string $dataEmissao = null,
        public ?string $dataCompetencia = null,
        public ?Nt009RecipientData $fornecedor = null,
    ) {
    }
}
