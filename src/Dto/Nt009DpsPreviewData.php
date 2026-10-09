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
    /**
     * @param list<Nt009LinkedPaymentData> $pagamentosVinculados
     * @param list<Nt009MovableAssetData> $bensMoveis
     * @param list<string> $notasPagamentoAntecipado
     */
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
        /** Recipient identity is required when indDest=1. */
        public ?Nt009RecipientData $destinatario = null,
        /** Explicit new base-adjustment form; never repurpose legacy vDedRed. */
        public ?Nt009BaseAdjustmentData $ajusteBase = null,
        /** Up to 1000 movable-rental items for cTribNac 99.04.01. */
        public array $bensMoveis = [],
        /** Up to 99 transaction records under IBSCBS/gPgtoVinc. */
        public array $pagamentosVinculados = [],
        /** Optional property operation details, Annex VI rows 383-403. */
        public ?Nt009RealEstateData $imovel = null,
        /** Optional condominium billing, Annex VI rows 408-421. */
        public ?Nt009CondominiumData $condominios = null,
        /** Official cClassTrib ind_gEstornoCred, supplied by the caller. */
        public ?bool $exigeEstornoCredito = null,
        /** IBS credit reversal amount (Anexo VI, row 439). */
        public ?string $valorEstornoIbs = null,
        /** CBS credit reversal amount (Anexo VI, row 440). */
        public ?string $valorEstornoCbs = null,
        /** 1-99 prior NFS-e payment-reference keys, 50 characters each. */
        public array $notasPagamentoAntecipado = [],
    ) {
    }
}
