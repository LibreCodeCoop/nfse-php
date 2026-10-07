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
        /** CNPJ do prestador de serviço (14 chars; numeric or alphanumeric). */
        public string $cnpjPrestador,

        /** Código IBGE do município do prestador (7 digits). */
        public string $municipioIbge,

        /** Optional provider phone published in the DPS. */
        public string $prestadorTelefone = '',

        /** Optional provider e-mail published in the DPS. */
        public string $prestadorEmail = '',

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

        /** CNPJ (14 chars, numeric or alphanumeric) or CPF (11 digits) do tomador. Empty string for foreign. */
        public string $documentoTomador = '',

        /** NIF (foreign tax identifier) of the service taker. */
        public string $tomadorNif = '',

        /** Reason for not providing a foreign NIF: 0 unknown, 1 exempt, 2 not required. */
        public ?int $tomadorCodigoNaoNif = null,

        /** ISO 3166-1 alpha-2 country code for a foreign service taker address. */
        public string $tomadorPaisCodigo = '',

        /** Foreign postal code (1-11 characters). */
        public string $tomadorCodigoPostalExterior = '',

        /** Foreign city name. */
        public string $tomadorCidadeExterior = '',

        /** Foreign state, province or region. */
        public string $tomadorEstadoExterior = '',

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

        /** Tributação do ISSQN: 1 tributável, 2 imunidade, 3 exportação, 4 não incidência. */
        public int $tributacaoIssqn = 1,

        /** ISO alpha-2 country where the service result occurred for applicable export scenarios. */
        public string $issqnPaisResultado = '',

        /** ISSQN immunity type (1-5 for national emitter flows). */
        public ?int $issqnTipoImunidade = null,

        /** Suspended enforceability type: 1 judicial decision, 2 administrative proceeding. */
        public ?int $issqnTipoSuspensao = null,

        /** 30-digit judicial/administrative proceeding number for suspended enforceability. */
        public string $issqnNumeroProcessoSuspensao = '',

        /** Tipo de retenção do ISSQN: 1 não retido, 2 tomador, 3 intermediário. */
        public int $tipoRetencaoIss = 1,

        /** Indicador de tributação total. */
        public int $indicadorTributacao = 0,

        /** Percentual total estimado de tributos federais. */
        public string $totalTributosPercentualFederal = '',

        /** Percentual total estimado de tributos estaduais. */
        public string $totalTributosPercentualEstadual = '',

        /** Percentual total estimado de tributos municipais. */
        public string $totalTributosPercentualMunicipal = '',

        /**
         * @deprecated Use tipoRetencaoIss. Retention and ISSQN taxation are independent fields.
         */
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

        /** Finalidade da NFS-e para IBS/CBS. Null keeps the IBSCBS group disabled. */
        public ?int $ibsCbsFinalidade = null,

        /** Indicador de uso/consumo pessoal para IBS/CBS. */
        public ?int $ibsCbsIndFinal = null,

        /** Código indicador da operação (cIndOp), conforme tabela oficial. */
        public string $ibsCbsCodigoIndicadorOperacao = '',

        /** Indicador do destinatário dos serviços. */
        public ?int $ibsCbsIndDest = null,

        /** Código de Situação Tributária do IBS/CBS (CST). */
        public string $ibsCbsCst = '',

        /** Código de Classificação Tributária do IBS/CBS. */
        public string $ibsCbsClassificacaoTributaria = '',

        /**
         * Código de tributação municipal (cTribMun).
         *
         * Null preserves the legacy behavior of using itemListaServico.
         * An empty string explicitly omits cTribMun.
         */
        public ?string $codigoTributacaoMunicipal = null,

        /** Optional official NFS-e substitution reference for replacement DPS issuance. */
        public ?SubstitutionData $substituicao = null,

        /** Optional standard DPS deduction/reduction (pDR or vDR). */
        public ?DeductionReductionData $deducaoReducao = null,

        /** Optional municipal benefit identified by the official municipal parameter id. */
        public ?MunicipalBenefitData $beneficioMunicipal = null,
    ) {
    }
}
