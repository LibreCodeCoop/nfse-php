<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/**
 * Opt-in, documented NT009 DPS draft input. Not an emission contract.
 *
 * The official classification indicator ind_gIBSCBS must be supplied by the
 * caller from an authoritative tax table; the SDK cannot derive that flag
 * from cClassTrib or the consultative Anexo VIII.
 */
final readonly class Nt009DpsPreviewData
{
    public function __construct(
        /** finNFSe: 0 regular, 1 credit, 2 debit. */
        public int $finalidade,
        /** indDest: 0 same taker, 1 distinct recipient. */
        public int $indicadorDestinatario,
        /** tpNFSeDebito: 01..06, only for finalidade=2. */
        public ?string $tipoNotaDebito = null,
        /** tpNFSeCredito: 01 or 05, only for finalidade=1. */
        public ?string $tipoNotaCredito = null,
        /** indFinal: 0 no, 1 yes, if applicable. */
        public ?int $indicadorUsoPessoal = null,
        /** cIndOp, optional in the published NT009 layout. */
        public ?string $codigoIndicadorOperacao = null,
        /** IBS/CBS CST. Must have 3 digits when IBSCBS is present. */
        public ?string $cst = null,
        /** IBS/CBS cClassTrib. Must have 6 digits when IBSCBS is present. */
        public ?string $classificacaoTributaria = null,
        /** Value from official ind_gIBSCBS. Required when configuring IBSCBS. */
        public ?bool $exigeGrupoIbsCbs = null,
        /** cCredPres, if required by externally verified classification. */
        public ?string $codigoCreditoPresumido = null,
        /** vIBS for an eligible credit/debit adjustment. */
        public ?string $valorAjusteIbs = null,
        /** vCBS for an eligible credit/debit adjustment. */
        public ?string $valorAjusteCbs = null,
        /** regApIBSCBSSN 1, 2 or 3, when applicable. */
        public ?int $regimeApuracaoSimples = null,
    ) {
    }
}
