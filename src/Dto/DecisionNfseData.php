<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/**
 * Complete NFS-e fields supplied by the contributor in the
 * administrative/judicial-decision issuance flow.
 *
 * Municipal authorization for this bypass is a legal/platform prerequisite;
 * the library deliberately does not infer eligibility.
 */
final readonly class DecisionNfseData
{
    public function __construct(
        public DpsData $dps,
        public string $numeroNfse,
        public string $codigoNumerico,
        public string $localEmissao,
        public string $localPrestacao,
        public string $descricaoTributacaoNacional,
        public string $emitenteNome,
        public DecisionIssuerAddressData $emitenteEndereco,
        public string $valorLiquido,
        public string $numeroDfse = '0',
        public ?string $codigoLocalIncidencia = null,
        public ?string $localIncidencia = null,
        public ?string $descricaoTributacaoMunicipal = null,
        public ?string $descricaoNbs = null,
        public string $versaoAplicativo = 'nfse-php',
        public ?int $processoEmissao = null,
        public ?string $emitenteNomeFantasia = null,
        public ?string $emitenteInscricaoMunicipal = null,
        public ?string $emitenteTelefone = null,
        public ?string $emitenteEmail = null,
        public ?string $baseCalculo = null,
        public ?string $aliquotaAplicada = null,
        public ?string $valorIssqn = null,
        public ?string $valorTotalRetido = null,
        public ?string $outrasInformacoes = null,
    ) {
    }
}
