<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Danfse;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use LibreCodeCoop\NfsePHP\Danfse\Config\DanfseConfig;
use LibreCodeCoop\NfsePHP\Danfse\Data\Municipios;
use LibreCodeCoop\NfsePHP\Danfse\Enum\Ambiente;
use LibreCodeCoop\NfsePHP\Danfse\Enum\OptanteSimplesNacional;
use LibreCodeCoop\NfsePHP\Danfse\Enum\RegimeApuracaoTributariaSN;
use LibreCodeCoop\NfsePHP\Danfse\Enum\RegimeEspecialTributacao;
use LibreCodeCoop\NfsePHP\Danfse\Enum\TipoRetencaoISSQN;
use LibreCodeCoop\NfsePHP\Danfse\Enum\TributacaoISSQN;

/**
 * Builds the flat data array consumed by the HTML template and renders it.
 *
 * Works directly off the associative array produced by {@see XmlToArray},
 * navigating it defensively so missing fields never raise errors.
 */
final class DanfseTemplate
{
    private const QR_BASE_URL_PROD = 'https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=';
    private const QR_BASE_URL_SANDBOX = 'https://www.producaorestrita.nfse.gov.br/ConsultaPublica/?tpc=1&chave=';

    private readonly Formatter $formatter;

    public function __construct()
    {
        $this->formatter = new Formatter();
    }

    /**
     * Render the full HTML document.
     *
     * @param array<string, mixed> $nfse
     */
    public function render(
        array $nfse,
        DanfseConfig $config,
        ?int $serviceDescriptionLimit = null,
        ?int $complementaryTextLimit = null,
    ): string {
        $data          = $this->buildData($nfse, $complementaryTextLimit);
        if ($serviceDescriptionLimit !== null) {
            $data['servico']['descricao'] = $this->formatter->limit(
                (string) $data['servico']['descricao'],
                $serviceDescriptionLimit,
            );
        }
        $logo          = $config->logoDataUri;
        $municipality  = $config->municipality;
        // This is an explicitly supplied, externally verified state; it
        // cannot be inferred from an unchanged authorized NFS-e XML.
        $fiscalState  = $config->fiscalState;
        $ambiente      = Ambiente::fromValue((string) $data['ambiente']);
        $isHomologacao = $ambiente->isHomologacao();
        $qrCode        = $this->generateQrCode((string) $data['chave_acesso'], $ambiente);

        array_walk_recursive($data, static function (mixed &$value): void {
            if (is_string($value)) {
                $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        });

        ob_start();
        include __DIR__ . '/HTMLTemplate.php';

        return (string) ob_get_clean();
    }

    /**
     * Flatten the NFS-e array into the template's data structure.
     *
     * @param array<string, mixed> $nfse
     * @return array<string, mixed>
     */
    public function buildData(array $nfse, ?int $complementaryTextLimit = null): array
    {
        $infNfse = $this->node($nfse, 'infNFSe');
        $infDps  = $this->node($infNfse, 'DPS', 'infDPS');
        $prest  = $this->node($infDps, 'prest');
        $regTrib = $this->node($prest, 'regTrib');
        $emit    = $this->node($infNfse, 'emit');
        $enderEmit = $this->node($emit, 'enderNac');
        $toma    = $this->node($infDps, 'toma');
        $endToma = $this->node($toma, 'end');
        $dest    = $this->node($infDps, 'IBSCBS', 'dest');
        $interm  = $this->node($infDps, 'interm');
        $endInterm = $this->node($interm, 'end');
        $serv    = $this->node($infDps, 'serv');
        $cServ   = $this->node($serv, 'cServ');
        $valores = $this->node($infDps, 'valores');
        $authorizedValues = $this->node($infNfse, 'valores');
        $tribMun = $this->node($valores, 'trib', 'tribMun');
        $tribFed = $this->node($valores, 'trib', 'tribFed');
        $totTrib = $this->node($valores, 'trib', 'totTrib', 'pTotTrib');
        $ibsCbs = $this->node($infNfse, 'IBSCBS');
        $ibsCbsValores = $this->node($ibsCbs, 'valores');
        $ibsCbsTotais = $this->node($ibsCbs, 'totCIBS');

        // The authorized DPS is the primary source for the provider. The
        // authorized infNFSe/emit enriches omitted legacy address/name data.
        $provider = $this->personDetails($prest);
        $provider['nome'] = $this->val($prest, 'xNome') ?: $this->val($emit, 'xNome') ?: '-';
        $provider['cnpj_cpf'] = $this->formattedDocument($prest);
        $provider['im'] = $this->val($prest, 'IM') ?: $this->val($emit, 'IM') ?: '-';
        $provider['telefone'] = $this->formatter->phone($this->val($prest, 'fone') ?: $this->val($emit, 'fone'));
        $provider['email'] = strtolower($this->val($prest, 'email') ?: $this->val($emit, 'email'));
        if ($this->node($prest, 'end') === []) {
            $provider['endereco'] = $this->address($enderEmit);
            $provider['municipio'] = $this->cityWithUf(
                $this->val($infNfse, 'xLocEmi'),
                $this->val($enderEmit, 'UF'),
            );
            $provider['cep'] = $this->formatter->cep($this->val($enderEmit, 'CEP'));
            $provider['ibge_cep'] = $this->ibgeCep($this->val($enderEmit, 'cMun'), $this->val($enderEmit, 'CEP'));
        }
        $provider['simples_nacional'] = OptanteSimplesNacional::labelFor($this->val($regTrib, 'opSimpNac'));
        $provider['regime_sn'] = RegimeApuracaoTributariaSN::labelFor($this->val($regTrib, 'regApTribSN'));

        // NT 008 note 5: each optional municipal row is suppressed only
        // when all four of its source fields are absent. A literal '0' is
        // supplied data and must not be treated as absent.
        $regimeEspecial = $this->val($regTrib, 'regEspTrib');
        $tipoImunidade = $this->val($tribMun, 'tpImunidade');
        $tipoSuspensao = $this->val($tribMun, 'exigSusp', 'tpSusp');
        $numeroProcesso = $this->val($tribMun, 'exigSusp', 'nProcesso');

        $tipoBeneficio = $this->val($authorizedValues, 'tpBM');
        $calculoBeneficio = $this->firstPresent(
            $this->val($authorizedValues, 'vCalcBM'),
            $this->val($tribMun, 'BM', 'vCalcBM'),
            $this->val($tribMun, 'BM', 'vRedBCBM'),
        );
        // The official display table chooses the explicit DPS vDR, or the
        // calculated deduction and reimbursement amounts supplied in the
        // authorized NFS-e. It does not re-derive these from service prices.
        $explicitDeductions = $this->val($valores, 'vDedRed', 'vDR');
        $totalDeducoes = $explicitDeductions !== ''
            ? $this->currencyOrDash($explicitDeductions)
            : $this->sumCurrency(
                $this->val($authorizedValues, 'vCalcDR'),
                $this->val($ibsCbsValores, 'vCalcReeRepRes'),
            );
        $descontoIncondicionado = $this->val($valores, 'vDescCondIncond', 'vDescIncond');

        $id          = $this->val($infNfse, 'Id');
        $chaveAcesso = str_starts_with($id, 'NFS') ? substr($id, 3) : $id;

        return [
            'chave_acesso' => $chaveAcesso,
            'numero_nfse'  => $this->val($infNfse, 'nNFSe') ?: '-',
            'competencia'  => $this->formatter->date($this->val($infDps, 'dCompet')),
            'emissao_nfse' => $this->formatter->dateTime($this->val($infNfse, 'dhProc')),
            'numero_dps'   => $this->val($infDps, 'nDPS') ?: '-',
            'serie_dps'    => $this->val($infDps, 'serie') ?: '-',
            'emissao_dps'  => $this->formatter->dateTime($this->val($infDps, 'dhEmi')),
            'ambiente'      => Ambiente::fromValue($this->val($infDps, 'tpAmb'))->value,
            'ambiente_gerador' => $this->val($infNfse, 'ambGer') ?: '-',
            'municipio_emissao' => $this->headerMunicipality(
                $this->val($infNfse, 'xLocEmi'),
                $this->val($enderEmit, 'UF'),
                $this->val($cServ, 'cTribNac'),
            ),
            'emitente_nfse' => $this->emitterLabel($this->val($infDps, 'tpEmit')),
            'situacao_nfse' => $this->statusLabel($this->val($infNfse, 'cStat')),
            'finalidade_nfse' => $this->purposeLabel($this->val($infDps, 'IBSCBS', 'finNFSe')),

            'emitente' => $provider,
            'tomador' => $toma === [] ? null : $this->personDetails($toma),
            'destinatario' => $dest === [] ? null : array_merge(
                $this->personDetails($dest),
                ['mesmo_tomador' => $this->samePerson($dest, $toma)],
            ),
            // The official XSD's indDest=0 is an explicit declaration
            // that dest=toma, even if there is no separate dest group.
            // When indDest=1, never override that with a name or inferred
            // equality. Only legacy XML lacking indDest uses identity
            // comparison as a presentation fallback.
            'destinatario_mesmo_tomador' => match ($this->val($infDps, 'IBSCBS', 'indDest')) {
                '0' => true,
                '1' => false,
                default => $dest !== [] && $this->samePerson($dest, $toma),
            },
            'intermediario' => $interm === [] ? null : $this->personDetails($interm),

            'servico' => [
                'codigo_trib_nacional'  => $this->formatter->codTribNacional($this->val($cServ, 'cTribNac')),
                // NT008 2.4.5 prefers an authorized municipal description
                // over the national one. The length is suggested, not a new
                // tax-code validation or data mutation.
                'desc_trib_nacional'    => $this->formatter->limit(
                    $this->val($infNfse, 'xTribMun') ?: $this->val($infNfse, 'xTribNac'),
                    167,
                ),
                'codigo_trib_municipal' => $this->val($cServ, 'cTribMun') ?: '-',
                'codigo_nbs'           => $this->formattedNbs($this->val($cServ, 'cNBS')),
                'desc_trib_municipal'   => $this->formatter->limit(trim($this->val($infNfse, 'xTribMun')), 60),
                'local_prestacao'       => $this->locationWithUfAndCountry(
                    $this->val($infNfse, 'xLocPrestacao'),
                    $this->val($serv, 'locPrest', 'cLocPrestacao'),
                    $this->val($serv, 'locPrest', 'cPaisPrestacao'),
                ),
                'pais_prestacao'        => $this->val($serv, 'locPrest', 'cPaisPrestacao') ?: '-',
                'descricao'             => $this->val($cServ, 'xDescServ') ?: '-',
            ],

            'tributacao_municipal' => [
                'tributacao_issqn'     => TributacaoISSQN::labelFor($this->val($tribMun, 'tribISSQN')),
                'municipio_incidencia' => $this->locationWithUfAndCountry(
                    $this->val($infNfse, 'xLocIncid'),
                    $this->val($infNfse, 'cLocIncid'),
                    $this->val($tribMun, 'cPaisResult'),
                ),
                'regime_especial'      => RegimeEspecialTributacao::labelFor($regimeEspecial),
                'tipo_imunidade'       => $this->immunityLabel($tipoImunidade),
                'suspensao_issqn'      => $this->suspensionLabel($tipoSuspensao),
                'numero_processo'      => $numeroProcesso !== '' ? $numeroProcesso : '-',
                'mostrar_linha_regime' => $this->hasAnyPresent($regimeEspecial, $tipoImunidade, $tipoSuspensao, $numeroProcesso),
                'beneficio_municipal'  => $this->municipalBenefitLabel($tipoBeneficio),
                'calculo_bm'           => $this->currencyOrDash($calculoBeneficio),
                'total_deducoes'       => $totalDeducoes,
                'desconto_incondicionado' => $this->currencyOrDash($descontoIncondicionado),
                'mostrar_linha_beneficio' => $this->hasAnyPresent($tipoBeneficio, $calculoBeneficio, $totalDeducoes !== '-' ? $totalDeducoes : '', $descontoIncondicionado),
                // Note 4 permits the concise no-ISS block when the XML
                // expressly identifies the operation as not incident.
                'nao_sujeito_issqn' => $this->val($tribMun, 'tribISSQN') === '4',
                'valor_servico'        => $this->formatter->currency($this->val($valores, 'vServPrest', 'vServ')),
                'bc_issqn'             => $this->currencyOrDash($this->val($authorizedValues, 'vBC')),
                'aliquota'             => $this->percentOrDash($this->val($authorizedValues, 'pAliqAplic')),
                'retencao_issqn'       => TipoRetencaoISSQN::labelFor($this->val($tribMun, 'tpRetISSQN')),
                'issqn_apurado'        => $this->currencyOrDash($this->val($authorizedValues, 'vISSQN')),
            ],

            'tributacao_federal' => [
                // NT008 note 6: optional PIS/COFINS line is present only
                // for actual issue competence through calendar year 2026.
                'mostrar_linha_2026' => $this->showFederalDetailsForCompetence(
                    $this->val($infDps, 'dCompet'),
                ),
                'irrf'   => $this->currencyOrDash($this->val($tribFed, 'vRetIRRF')),
                'cp'     => $this->currencyOrDash($this->val($tribFed, 'vRetCP')),
                'contribuicoes_sociais' => $this->currencyOrDash($this->val($tribFed, 'vRetCSLL')),
                // Alias de compatibilidade para consumidores de buildData().
                'csll'   => $this->currencyOrDash($this->val($tribFed, 'vRetCSLL')),
                'pis'    => $this->currencyOrDash($this->val($tribFed, 'piscofins', 'vPis')),
                'cofins' => $this->currencyOrDash($this->val($tribFed, 'piscofins', 'vCofins')),
                'descricao_retencao' => $this->pisCofinsRetentionLabel(
                    $this->val($tribFed, 'piscofins', 'tpRetPisCofins'),
                ),
            ],

            'totais' => [
                'valor_servico'           => $this->formatter->currency($this->val($valores, 'vServPrest', 'vServ')),
                'desconto_condicionado'   => $this->currencyOrDash($this->val($tribMun, 'vDescCond')),
                'desconto_incondicionado' => $this->currencyOrDash($this->val($tribMun, 'vDescIncond')),
                'issqn_retido'            => ($this->val($authorizedValues, 'vISSQN') !== '' && ($this->val($tribMun, 'tpRetISSQN') ?: '1') !== '1')
                    ? $this->formatter->currency($this->val($authorizedValues, 'vISSQN'))
                    : '-',
                'retencoes_federais' => $this->sumCurrency(
                    $this->val($tribFed, 'vRetIRRF'),
                    $this->val($tribFed, 'vRetCP'),
                    $this->val($tribFed, 'vRetCSLL'),
                ),
                'pis_cofins' => $this->sumCurrency(
                    $this->val($tribFed, 'piscofins', 'vPis'),
                    $this->val($tribFed, 'piscofins', 'vCofins'),
                ),
                'valor_liquido' => $this->formatter->currency($this->val($infNfse, 'valores', 'vLiq')),
            ],

            'totais_tributos' => [
                'federais'   => $this->percentOrDash($this->val($totTrib, 'pTotTribFed')),
                'estaduais'  => $this->percentOrDash($this->val($totTrib, 'pTotTribEst')),
                'municipais' => $this->percentOrDash($this->val($totTrib, 'pTotTribMun')),
            ],

            'ibs_cbs' => [
                'cst_classificacao' => $this->joinedOrDash(
                    $this->val($infDps, 'IBSCBS', 'valores', 'trib', 'gIBSCBS', 'CST'),
                    $this->val($infDps, 'IBSCBS', 'valores', 'trib', 'gIBSCBS', 'cClassTrib'),
                ),
                'indicador_operacao' => $this->val($infDps, 'IBSCBS', 'cIndOp') ?: '-',
                'localidade_incidencia' => $this->val($ibsCbs, 'xLocalidadeIncid')
                    ?: $this->val($ibsCbs, 'cLocalidadeIncid')
                    ?: '-',
                'exclusoes_reducoes' => $this->sumCurrency(
                    $this->val($infNfse, 'valores', 'vISSQN'),
                    $this->val($tribFed, 'piscofins', 'vPis'),
                    $this->val($tribFed, 'piscofins', 'vCofins'),
                ),
                'base_calculo' => $this->currencyOrDash($this->val($ibsCbsValores, 'vBC')),
                'aliquota_ibs_uf' => $this->percentOrDash($this->val($ibsCbsValores, 'uf', 'pIBSUF')),
                'aliquota_ibs_municipal' => $this->percentOrDash($this->val($ibsCbsValores, 'mun', 'pIBSMun')),
                'aliquota_cbs' => $this->percentOrDash($this->val($ibsCbsValores, 'fed', 'pCBS')),
                'total_ibs' => $this->currencyOrDash($this->val($ibsCbsTotais, 'gIBS', 'vIBSTot')),
                'total_cbs' => $this->currencyOrDash($this->val($ibsCbsTotais, 'gCBS', 'vCBS')),
                'total_ibs_cbs' => $ibsCbs === [] ? '-' : $this->sumCurrency(
                    $this->val($ibsCbsTotais, 'gIBS', 'vIBSTot'),
                    $this->val($ibsCbsTotais, 'gCBS', 'vCBS'),
                ),
                'valor_total_nfse' => $this->currencyOrDash($this->val($ibsCbsTotais, 'vTotNF')),
            ],

            'informacoes_complementares' => $this->complementaryInformation(
                $this->val($serv, 'infoCompl', 'xInfComp'),
                $this->percentOrDash($this->val($totTrib, 'pTotTribFed')),
                $this->percentOrDash($this->val($totTrib, 'pTotTribEst')),
                $this->percentOrDash($this->val($totTrib, 'pTotTribMun')),
                $complementaryTextLimit,
            ),
        ];
    }

    /**
     * Presentation fields for people in the authorized DPS, keeping national
     * IBGE/CEP and foreign city/postal codes distinct. Missing values stay
     * missing: no municipality, state or identity is guessed.
     *
     * @param array<string,mixed> $person
     * @return array<string,string>
     */
    private function personDetails(array $person): array
    {
        $address = $this->node($person, 'end');
        $national = $this->node($address, 'endNac');
        $foreign = $this->node($address, 'endExt');

        $municipio = '-';
        $ibgeCep = '-';
        $cep = '-';

        if ($national !== []) {
            $municipio = $this->city($this->val($national, 'cMun'));
            $ibgeCep = $this->ibgeCep($this->val($national, 'cMun'), $this->val($national, 'CEP'));
            $cep = $this->formatter->cep($this->val($national, 'CEP'));
        } elseif ($foreign !== []) {
            $city = $this->val($foreign, 'xCidade');
            $region = $this->val($foreign, 'xEstProvReg');
            $postal = $this->val($foreign, 'cEndPost');
            $municipio = $city !== '' ? $city . ($region !== '' ? ' - ' . $region : '') : '-';
            $ibgeCep = '- / ' . ($postal !== '' ? $postal : '-');
            $cep = $postal !== '' ? $postal : '-';
        }

        return [
            'nome' => $this->val($person, 'xNome') ?: '-',
            'cnpj_cpf' => $this->formattedDocument($person),
            'im' => $this->val($person, 'IM') ?: $this->val($person, 'IMPrestMun') ?: '-',
            'telefone' => $this->formatter->phone($this->val($person, 'fone')),
            'email' => strtolower($this->val($person, 'email')),
            'endereco' => $this->address($address),
            'municipio' => $municipio,
            'ibge_cep' => $ibgeCep,
            'cep' => $cep,
        ];
    }

    /**
     * Equal verified identifiers permit the NT008 optional same-taker label.
     * Names or addresses alone do not establish fiscal identity.
     *
     * @param array<string,mixed> $left
     * @param array<string,mixed> $right
     */
    private function samePerson(array $left, array $right): bool
    {
        foreach (['CNPJ', 'CPF', 'NIF'] as $key) {
            $id = $this->val($left, $key);
            if ($id !== '' && $id === $this->val($right, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Navigate to a nested sub-array, returning [] when any step is missing.
     *
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function node(array $source, string ...$keys): array
    {
        $currentNode = $source;

        foreach ($keys as $key) {
            if (!is_array($currentNode[$key] ?? null)) {
                return [];
            }
            /** @var array<string, mixed> $currentNode */
            $currentNode = $currentNode[$key];
        }

        return $currentNode;
    }

    /**
     * Navigate to a nested scalar value, returning '' when missing or non-scalar.
     *
     * @param array<string, mixed> $source
     */
    private function val(array $source, string ...$keys): string
    {
        $last = array_pop($keys);
        if ($last === null) {
            return '';
        }

        $node  = $this->node($source, ...$keys);
        $value = $node[$last] ?? null;

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * Resolve the party document (CNPJ, CPF or NIF).
     *
     * @param array<string, mixed> $party
     */
    private function formattedDocument(array $party): string
    {
        $cnpj = $this->val($party, 'CNPJ');
        if ($cnpj !== '') {
            return $this->formatter->cnpjCpf($cnpj);
        }

        $cpf = $this->val($party, 'CPF');
        if ($cpf !== '') {
            return $this->formatter->cnpjCpf($cpf);
        }

        return $this->val($party, 'NIF') ?: '-';
    }

    /**
     * @param array<string, mixed> $endereco
     */
    private function address(array $endereco): string
    {
        $parts = array_filter([
            $this->val($endereco, 'xLgr'),
            $this->val($endereco, 'nro'),
            $this->val($endereco, 'xCpl'),
            $this->val($endereco, 'xBairro'),
        ], static fn (string $value): bool => $value !== '');

        return $parts === [] ? '-' : implode(', ', $parts);
    }

    /**
     * Display ISO country BR for a recognized Brazilian IBGE municipality.
     * The NT 008/2026 v1.02 DANFSe table explicitly uses BR as an example;
     * this is a presentation inference from the authorized IBGE municipality,
     * NOT a cPaisPrestacao XML value (which E0304 forbids for BR).
     * For foreign operations, preserve the explicit XML country instead.
     */
    private function locationWithUfAndCountry(string $city, string $ibge, string $country): string
    {
        $city = trim($city);
        $uf = '';
        if (preg_match('/^\\d{7}$/D', $ibge) === 1) {
            $resolved = Municipios::lookup($ibge);
            if ($resolved !== $ibge && str_contains($resolved, ' - ')) {
                $uf = substr($resolved, (int) strrpos($resolved, ' - ') + 3);
            }
        }

        $country = strtoupper(trim($country));
        if ($country === '' && $uf !== '') {
            $country = 'BR';
        }

        return ($city !== '' ? $city : '-') . ' / ' . ($uf !== '' ? $uf : '-') . ' / '
            . ($country !== '' ? $country : '-');
    }

    private function formattedNbs(string $code): string
    {
        if (preg_match('/^\\d{9}$/D', $code) !== 1) {
            return $code !== '' ? $code : '-';
        }

        return substr($code, 0, 1) . '.' . substr($code, 1, 4) . '.'
            . substr($code, 5, 2) . '.' . substr($code, 7, 2);
    }

    /**
     * The NT 008 municipal heading is not the service location. Its city and UF
     * come only from the authorized NFS-e, and item 99 suppresses the line.
     */
    private function headerMunicipality(string $city, string $uf, string $nationalTaxCode): string
    {
        if (str_starts_with($nationalTaxCode, '99') || ($city === '' && $uf === '')) {
            return '';
        }

        return ($city !== '' ? $city : '-') . ' / ' . ($uf !== '' ? $uf : '-');
    }

    private function cityWithUf(string $city, string $uf): string
    {
        return ($city !== '' && $uf !== '') ? $city . ' - ' . $uf : '-';
    }

    private function ibgeCep(string $ibge, string $cep): string
    {
        return ($ibge !== '' ? $ibge : '-') . ' / ' . $this->formatter->cep($cep);
    }

    private function city(string $cMun): string
    {
        return $cMun !== '' ? Municipios::lookup($cMun) : '-';
    }

    private function firstPresent(string ...$values): string
    {
        foreach ($values as $value) {
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function hasAnyPresent(string ...$values): bool
    {
        foreach ($values as $value) {
            if ($value !== '') {
                return true;
            }
        }

        return false;
    }

    private function immunityLabel(string $value): string
    {
        $label = match ($value) {
            // XSD 1.01 explicitly assigns 0 to a legacy, unspecified type.
            // Display the actual fiscal meaning instead of confusing it with
            // a missing XML tag, even though NT008's table lists 1–5.
            '0' => 'Imunidade (tipo não informado)',
            '1' => 'Patrimônio, renda ou serviços de entes públicos',
            '2' => 'Entidades religiosas e templos de qualquer culto',
            '3' => 'Partidos, sindicatos, educação e assistência social',
            '4' => 'Livros, jornais, periódicos e o papel destinado à impressão',
            '5' => 'Fonogramas e videofonogramas musicais brasileiros',
            default => '',
        };

        return $label !== '' ? $this->formatter->limit($label, 37) : '-';
    }

    private function suspensionLabel(string $value): string
    {
        $label = match ($value) {
            '1' => 'Exigibilidade Suspensa por Decisão Judicial',
            '2' => 'Exigibilidade Suspensa por Processo Administrativo',
            default => '',
        };

        return $label !== '' ? $this->formatter->limit($label, 37) : '-';
    }

    private function municipalBenefitLabel(string $value): string
    {
        return match ($value) {
            '1' => 'Isenção',
            '2' => 'Redução da BC em percentual',
            '3' => 'Redução da BC em reais',
            '4' => 'Alíquota Diferenciada',
            default => '-',
        };
    }

    private function currencyOrDash(string $value): string
    {
        return $value !== '' ? $this->formatter->currency($value) : '-';
    }

    private function percentOrDash(string $value): string
    {
        return $value !== '' ? $value . '%' : '-';
    }

    private function sumCurrency(string ...$values): string
    {
        $total    = 0.0;
        $hasValue = false;
        foreach ($values as $value) {
            if ($value !== '') {
                $total += (float) $value;
                $hasValue = true;
            }
        }

        return $hasValue ? $this->formatter->currency((string) $total) : '-';
    }

    private function emitterLabel(string $value): string
    {
        return match ($value) {
            '1' => 'Prestador',
            '2' => 'Tomador',
            '3' => 'Intermediário',
            default => '-',
        };
    }

    private function statusLabel(string $value): string
    {
        return $value === '100' ? 'NFS-e Gerada' : ($value !== '' ? $value : '-');
    }

    private function purposeLabel(string $value): string
    {
        return match ($value) {
            '0' => 'NFS-e regular',
            '1' => 'NFS-e de crédito',
            '2' => 'NFS-e de débito',
            default => '-',
        };
    }

    private function showFederalDetailsForCompetence(string $competence): bool
    {
        // Do not use today's date: printing an older authorized invoice
        // must reproduce the display rules for its original competence.
        if (preg_match('/^(\\d{4})-\\d{2}-\\d{2}$/D', $competence, $matches) !== 1) {
            return false;
        }

        return (int) $matches[1] <= 2026;
    }

    private function pisCofinsRetentionLabel(string $value): string
    {
        return match ($value) {
            '0' => '0 - PIS/COFINS/CSLL Não Retidos',
            '1' => '1 - PIS/COFINS Retidos',
            '2' => '2 - PIS/COFINS Não Retidos',
            '3' => '3 - PIS/COFINS/CSLL Retidos',
            '4' => '4 - PIS/COFINS Retidos, CSLL Não Retido',
            '5' => '5 - PIS Retido, COFINS/CSLL Não Retido',
            '6' => '6 - COFINS Retido, PIS/CSLL Não Retido',
            '7' => '7 - PIS Não Retido, COFINS/CSLL Retidos',
            '8' => '8 - PIS/COFINS Não Retidos, CSLL Retido',
            '9' => '9 - COFINS Não Retido, PIS/CSLL Retidos',
            default => '-',
        };
    }

    private function joinedOrDash(string ...$values): string
    {
        if (array_filter($values, static fn (string $value): bool => $value !== '') === []) {
            return '-';
        }

        return implode(' / ', array_map(static fn (string $value): string => $value !== '' ? $value : '-', $values));
    }

    private function complementaryInformation(
        string $existing,
        string $federal,
        string $state,
        string $municipal,
        ?int $freeTextLimit = null,
    ): string {
        if ($freeTextLimit !== null) {
            $existing = $this->formatter->limit(trim($existing), $freeTextLimit);
        }

        $taxes = sprintf(
            'Totais aproximados dos Tributos cfe. Lei n° 12.741/2012: Federais: %s; Estaduais: %s; Municipais: %s;',
            $federal,
            $state,
            $municipal,
        );

        return trim($existing) === '' ? $taxes : trim($existing) . "\n" . $taxes;
    }

    private function generateQrCode(string $chaveAcesso, Ambiente $ambiente): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd(),
        );
        $svg = (new Writer($renderer))->writeString($this->qrCodeUrl($chaveAcesso, $ambiente));

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function qrCodeUrl(string $chaveAcesso, Ambiente $ambiente): string
    {
        $baseUrl = $ambiente->isHomologacao()
            ? self::QR_BASE_URL_SANDBOX
            : self::QR_BASE_URL_PROD;

        return $baseUrl . $chaveAcesso;
    }
}
