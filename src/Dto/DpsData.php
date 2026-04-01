<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/**
 * Documento Padrão de Serviço — the payload submitted to the SEFIN gateway.
 *
 * All monetary values are in BRL, represented as strings to avoid floating-point issues.
 */
final readonly class DpsData
{
    public function __construct(
        /** CNPJ do prestador de serviço (only digits, 14 chars). */
        public string $cnpjPrestador,

        /** Código IBGE do município do prestador (7 digits). */
        public string $municipioIbge,

        /** Item da lista de serviços — LC 116/2003. */
        public string $itemListaServico,

        /** Valor total do serviço em reais (e.g. "1500.00"). */
        public string $valorServico,

        /** Alíquota do ISS em percentual (e.g. "5.00"). */
        public string $aliquota,

        /** Descrição do serviço prestado. */
        public string $discriminacao,

        /** Tipo de ambiente (1-Produção | 2-Homologação). */
        public int $tipoAmbiente = 2,

        /** Application version string written into the DPS. */
        public string $versaoAplicativo = 'akaunting-nfse',

        /** Série do DPS (1-5 digits). */
        public string $serie = '00001',

        /** Número sequencial do DPS. */
        public string $numeroDps = '1',

        /** Competence date in YYYY-MM-DD format. Defaults to emission date when null. */
        public ?string $dataCompetencia = null,

        /** Tipo de emissão do DPS. */
        public int $tipoEmissao = 1,

        /** Código de tributação nacional do serviço (6 digits). */
        public string $codigoTributacaoNacional = '000000',

        /** CNPJ ou CPF do tomador (only digits, 11 or 14 chars). Empty string for foreign. */
        public string $documentoTomador = '',

        /** Nome / Razão Social do tomador. */
        public string $nomeTomador = '',

        /** Código IBGE do município do tomador (7 digits). */
        public string $tomadorCodigoMunicipio = '',

        /** CEP do tomador (8 digits). */
        public string $tomadorCep = '',

        /** Logradouro do tomador. */
        public string $tomadorLogradouro = '',

        /** Número do tomador. */
        public string $tomadorNumero = '',

        /** Complemento do endereço do tomador. */
        public string $tomadorComplemento = '',

        /** Bairro do tomador. */
        public string $tomadorBairro = '',

        /** Inscrição municipal do tomador. */
        public string $tomadorInscricaoMunicipal = '',

        /** Telefone do tomador. */
        public string $tomadorTelefone = '',

        /** E-mail do tomador. */
        public string $tomadorEmail = '',

        /** Whether the provider opts into Simples Nacional. */
        public int $opcaoSimplesNacional = 1,

        /** Regime especial de tributação. */
        public int $regimeEspecialTributacao = 0,

        /** Tipo de retenção do ISSQN. */
        public int $tipoRetencaoIss = 1,

        /** Indicador de tributação total. */
        public int $indicadorTributacao = 0,

        /** Percentual total estimado de tributos federais. */
        public string $totalTributosPercentualFederal = '',

        /** Percentual total estimado de tributos estaduais. */
        public string $totalTributosPercentualEstadual = '',

        /** Percentual total estimado de tributos municipais. */
        public string $totalTributosPercentualMunicipal = '',

        /** Whether ISS is retained at source. */
        public bool $issRetido = false,

        /** Situação Tributária do PIS/COFINS (CST). */
        public string $federalPiscofinsSituacaoTributaria = '',

        /** Tipo de retenção do PIS/COFINS/CSLL. */
        public string $federalPiscofinsTipoRetencao = '',

        /** Base de cálculo do PIS/COFINS. */
        public string $federalPiscofinsBaseCalculo = '',

        /** Alíquota do PIS. */
        public string $federalPiscofinsAliquotaPis = '',

        /** Valor do PIS. */
        public string $federalPiscofinsValorPis = '',

        /** Alíquota do COFINS. */
        public string $federalPiscofinsAliquotaCofins = '',

        /** Valor do COFINS. */
        public string $federalPiscofinsValorCofins = '',

        /** Valor do IRRF. */
        public string $federalValorIrrf = '',

        /** Valor das contribuições sociais retidas (CSLL). */
        public string $federalValorCsll = '',

        /** Valor da contribuição previdenciária retida. */
        public string $federalValorCp = '',
    ) {
    }
}
