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
use LibreCodeCoop\NfsePHP\Danfse\Enum\OpSimpNac;
use LibreCodeCoop\NfsePHP\Danfse\Enum\RegApTribSN;
use LibreCodeCoop\NfsePHP\Danfse\Enum\RegEspTrib;
use LibreCodeCoop\NfsePHP\Danfse\Enum\TpRetISSQN;
use LibreCodeCoop\NfsePHP\Danfse\Enum\TribISSQN;

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

    private readonly Formatter $fmt;

    public function __construct()
    {
        $this->fmt = new Formatter();
    }

    /**
     * Render the full HTML document.
     *
     * @param array<string, mixed> $nfse
     */
    public function render(array $nfse, DanfseConfig $config): string
    {
        $data         = $this->buildData($nfse);
        $logo         = $config->logoDataUri;
        $municipality = $config->municipality;
        $qrCode       = $this->generateQrCode((string) $data['chave_acesso'], (int) $data['ambiente']);

        array_walk_recursive($data, static function (mixed &$v): void {
            if (is_string($v)) {
                $v = htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        });

        ob_start();
        include __DIR__ . '/template.php';

        return (string) ob_get_clean();
    }

    /**
     * Flatten the NFS-e array into the template's data structure.
     *
     * @param array<string, mixed> $nfse
     * @return array<string, mixed>
     */
    public function buildData(array $nfse): array
    {
        $inf    = $this->node($nfse, 'infNFSe');
        $infDps = $this->node($inf, 'DPS', 'infDPS');
        $prest  = $this->node($infDps, 'prest');
        $regTrib = $this->node($prest, 'regTrib');
        $emit    = $this->node($inf, 'emit');
        $enderEmit = $this->node($emit, 'enderNac');
        $toma    = $this->node($infDps, 'toma');
        $endToma = $this->node($toma, 'end');
        $interm  = $this->node($infDps, 'interm');
        $endInterm = $this->node($interm, 'end');
        $serv    = $this->node($infDps, 'serv');
        $cServ   = $this->node($serv, 'cServ');
        $valores = $this->node($infDps, 'valores');
        $tribMun = $this->node($valores, 'trib', 'tribMun');
        $tribFed = $this->node($valores, 'trib', 'tribFed');
        $totTrib = $this->node($valores, 'trib', 'totTrib', 'pTotTrib');

        $id          = $this->val($inf, 'Id');
        $chaveAcesso = str_starts_with($id, 'NFS') ? substr($id, 3) : $id;

        return [
            'chave_acesso' => $chaveAcesso,
            'numero_nfse'  => $this->val($inf, 'nNFSe') ?: '-',
            'competencia'  => $this->fmt->date($this->val($infDps, 'dCompet')),
            'emissao_nfse' => $this->fmt->dateTime($this->val($inf, 'dhProc')),
            'numero_dps'   => $this->val($infDps, 'nDPS') ?: '-',
            'serie_dps'    => $this->val($infDps, 'serie') ?: '-',
            'emissao_dps'  => $this->fmt->dateTime($this->val($infDps, 'dhEmi')),
            'ambiente'     => (int) ($this->val($infDps, 'tpAmb') ?: '1'),

            'emitente' => [
                'nome'            => $this->val($emit, 'xNome') ?: '-',
                'cnpj_cpf'        => $this->formattedDocument($emit),
                'im'              => '-',
                'telefone'        => $this->fmt->phone($this->val($emit, 'fone')),
                'email'           => strtolower($this->val($emit, 'email')),
                'endereco'        => $this->address($enderEmit),
                'municipio'       => $this->cityWithUf($this->val($inf, 'xLocEmi'), $this->val($enderEmit, 'UF')),
                'cep'             => $this->fmt->cep($this->val($enderEmit, 'CEP')),
                'simples_nacional' => OpSimpNac::labelFor($this->val($regTrib, 'opSimpNac')),
                'regime_sn'       => RegApTribSN::labelFor($this->val($regTrib, 'regApTribSN')),
            ],

            'tomador' => [
                'nome'      => $this->val($toma, 'xNome') ?: '-',
                'cnpj_cpf'  => $this->formattedDocument($toma),
                'im'        => $this->val($toma, 'IM') ?: '-',
                'telefone'  => $this->fmt->phone($this->val($toma, 'fone')),
                'email'     => strtolower($this->val($toma, 'email')),
                'endereco'  => $this->address($endToma),
                'municipio' => $this->city($this->val($endToma, 'endNac', 'cMun')),
                'cep'       => $this->fmt->cep($this->val($endToma, 'endNac', 'CEP')),
            ],

            'intermediario' => $interm === [] ? null : [
                'nome'      => $this->val($interm, 'xNome') ?: '-',
                'cnpj_cpf'  => $this->formattedDocument($interm),
                'im'        => $this->val($interm, 'IMPrestMun') ?: '-',
                'telefone'  => $this->fmt->phone($this->val($interm, 'fone')),
                'email'     => strtolower($this->val($interm, 'email')),
                'endereco'  => $this->address($endInterm),
                'municipio' => $this->city($this->val($endInterm, 'endNac', 'cMun')),
                'cep'       => $this->fmt->cep($this->val($endInterm, 'endNac', 'CEP')),
            ],

            'servico' => [
                'codigo_trib_nacional'  => $this->fmt->codTribNacional($this->val($cServ, 'cTribNac')),
                'desc_trib_nacional'    => $this->fmt->limit(trim($this->val($inf, 'xTribNac')), 60),
                'codigo_trib_municipal' => $this->val($cServ, 'cTribMun') ?: '-',
                'desc_trib_municipal'   => $this->fmt->limit(trim($this->val($inf, 'xTribMun')), 60),
                'local_prestacao'       => $this->val($inf, 'xLocPrestacao') ?: '-',
                'pais_prestacao'        => $this->val($serv, 'locPrest', 'cPaisPrestacao') ?: '-',
                'descricao'             => $this->val($cServ, 'xDescServ') ?: '-',
            ],

            'tributacao_municipal' => [
                'tributacao_issqn'     => TribISSQN::labelFor($this->val($tribMun, 'tribISSQN')),
                'municipio_incidencia' => $this->val($inf, 'xLocIncid') ?: '-',
                'regime_especial'      => RegEspTrib::labelFor($this->val($regTrib, 'regEspTrib')),
                'valor_servico'        => $this->fmt->currency($this->val($valores, 'vServPrest', 'vServ')),
                'bc_issqn'             => $this->currencyOrDash($this->val($tribMun, 'vBC')),
                'aliquota'             => ($p = $this->val($tribMun, 'pAliq')) !== '' ? $p . '%' : '-',
                'retencao_issqn'       => TpRetISSQN::labelFor($this->val($tribMun, 'tpRetISSQN')),
                'issqn_apurado'        => $this->currencyOrDash($this->val($tribMun, 'vISSQN')),
            ],

            'tributacao_federal' => [
                'irrf'   => $this->currencyOrDash($this->val($tribFed, 'vRetIRRF')),
                'cp'     => $this->currencyOrDash($this->val($tribFed, 'vRetCP')),
                'csll'   => $this->currencyOrDash($this->val($tribFed, 'vRetCSLL')),
                'pis'    => $this->currencyOrDash($this->val($tribFed, 'piscofins', 'vPis')),
                'cofins' => $this->currencyOrDash($this->val($tribFed, 'piscofins', 'vCofins')),
            ],

            'totais' => [
                'valor_servico'           => $this->fmt->currency($this->val($valores, 'vServPrest', 'vServ')),
                'desconto_condicionado'   => $this->currencyOrDash($this->val($tribMun, 'vDescCond')),
                'desconto_incondicionado' => $this->currencyOrDash($this->val($tribMun, 'vDescIncond')),
                'issqn_retido'            => ($this->val($tribMun, 'vISSQN') !== '' && ($this->val($tribMun, 'tpRetISSQN') ?: '1') !== '1')
                    ? $this->fmt->currency($this->val($tribMun, 'vISSQN'))
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
                'valor_liquido' => $this->fmt->currency($this->val($inf, 'valores', 'vLiq')),
            ],

            'totais_tributos' => [
                'federais'   => ($f = $this->val($totTrib, 'pTotTribFed')) !== '' ? $f . '%' : '-',
                'estaduais'  => ($e = $this->val($totTrib, 'pTotTribEst')) !== '' ? $e . '%' : '-',
                'municipais' => ($m = $this->val($totTrib, 'pTotTribMun')) !== '' ? $m . '%' : '-',
            ],

            'informacoes_complementares' => $this->val($serv, 'infoCompl', 'xInfComp'),
        ];
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
            return $this->fmt->cnpjCpf($cnpj);
        }

        $cpf = $this->val($party, 'CPF');
        if ($cpf !== '') {
            return $this->fmt->cnpjCpf($cpf);
        }

        return $this->val($party, 'NIF') ?: '-';
    }

    /**
     * @param array<string, mixed> $end
     */
    private function address(array $end): string
    {
        $parts = array_filter([
            $this->val($end, 'xLgr'),
            $this->val($end, 'nro'),
            $this->val($end, 'xBairro'),
        ], static fn (string $v): bool => $v !== '');

        return $parts === [] ? '-' : implode(', ', $parts);
    }

    private function cityWithUf(string $city, string $uf): string
    {
        return ($city !== '' && $uf !== '') ? $city . ' - ' . $uf : '-';
    }

    private function city(string $cMun): string
    {
        return $cMun !== '' ? Municipios::lookup($cMun) : '-';
    }

    private function currencyOrDash(string $value): string
    {
        return $value !== '' ? $this->fmt->currency($value) : '-';
    }

    private function sumCurrency(string ...$values): string
    {
        $sum      = 0.0;
        $hasValue = false;
        foreach ($values as $v) {
            if ($v !== '') {
                $sum += (float) $v;
                $hasValue = true;
            }
        }

        return $hasValue ? $this->fmt->currency((string) $sum) : '-';
    }

    private function generateQrCode(string $chaveAcesso, int $ambiente): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd(),
        );
        $svg = (new Writer($renderer))->writeString($this->qrCodeUrl($chaveAcesso, $ambiente));

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function qrCodeUrl(string $chaveAcesso, int $ambiente): string
    {
        $baseUrl = $ambiente === 2
            ? self::QR_BASE_URL_SANDBOX
            : self::QR_BASE_URL_PROD;

        return $baseUrl . $chaveAcesso;
    }
}
