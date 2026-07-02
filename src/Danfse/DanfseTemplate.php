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
    public function render(array $nfse, DanfseConfig $config): string
    {
        $data          = $this->buildData($nfse);
        $logo          = $config->logoDataUri;
        $municipality  = $config->municipality;
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
    public function buildData(array $nfse): array
    {
        $infNfse = $this->node($nfse, 'infNFSe');
        $infDps  = $this->node($infNfse, 'DPS', 'infDPS');
        $prest  = $this->node($infDps, 'prest');
        $regTrib = $this->node($prest, 'regTrib');
        $emit    = $this->node($infNfse, 'emit');
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
            'ambiente'     => Ambiente::fromValue($this->val($infDps, 'tpAmb'))->value,

            'emitente' => [
                'nome'            => $this->val($emit, 'xNome') ?: '-',
                'cnpj_cpf'        => $this->formattedDocument($emit),
                'im'              => '-',
                'telefone'        => $this->formatter->phone($this->val($emit, 'fone')),
                'email'           => strtolower($this->val($emit, 'email')),
                'endereco'        => $this->address($enderEmit),
                'municipio'       => $this->cityWithUf($this->val($infNfse, 'xLocEmi'), $this->val($enderEmit, 'UF')),
                'cep'             => $this->formatter->cep($this->val($enderEmit, 'CEP')),
                'simples_nacional' => OptanteSimplesNacional::labelFor($this->val($regTrib, 'opSimpNac')),
                'regime_sn'       => RegimeApuracaoTributariaSN::labelFor($this->val($regTrib, 'regApTribSN')),
            ],

            'tomador' => [
                'nome'      => $this->val($toma, 'xNome') ?: '-',
                'cnpj_cpf'  => $this->formattedDocument($toma),
                'im'        => $this->val($toma, 'IM') ?: '-',
                'telefone'  => $this->formatter->phone($this->val($toma, 'fone')),
                'email'     => strtolower($this->val($toma, 'email')),
                'endereco'  => $this->address($endToma),
                'municipio' => $this->city($this->val($endToma, 'endNac', 'cMun')),
                'cep'       => $this->formatter->cep($this->val($endToma, 'endNac', 'CEP')),
            ],

            'intermediario' => $interm === [] ? null : [
                'nome'      => $this->val($interm, 'xNome') ?: '-',
                'cnpj_cpf'  => $this->formattedDocument($interm),
                'im'        => $this->val($interm, 'IMPrestMun') ?: '-',
                'telefone'  => $this->formatter->phone($this->val($interm, 'fone')),
                'email'     => strtolower($this->val($interm, 'email')),
                'endereco'  => $this->address($endInterm),
                'municipio' => $this->city($this->val($endInterm, 'endNac', 'cMun')),
                'cep'       => $this->formatter->cep($this->val($endInterm, 'endNac', 'CEP')),
            ],

            'servico' => [
                'codigo_trib_nacional'  => $this->formatter->codTribNacional($this->val($cServ, 'cTribNac')),
                'desc_trib_nacional'    => $this->formatter->limit(trim($this->val($infNfse, 'xTribNac')), 60),
                'codigo_trib_municipal' => $this->val($cServ, 'cTribMun') ?: '-',
                'desc_trib_municipal'   => $this->formatter->limit(trim($this->val($infNfse, 'xTribMun')), 60),
                'local_prestacao'       => $this->val($infNfse, 'xLocPrestacao') ?: '-',
                'pais_prestacao'        => $this->val($serv, 'locPrest', 'cPaisPrestacao') ?: '-',
                'descricao'             => $this->val($cServ, 'xDescServ') ?: '-',
            ],

            'tributacao_municipal' => [
                'tributacao_issqn'     => TributacaoISSQN::labelFor($this->val($tribMun, 'tribISSQN')),
                'municipio_incidencia' => $this->val($infNfse, 'xLocIncid') ?: '-',
                'regime_especial'      => RegimeEspecialTributacao::labelFor($this->val($regTrib, 'regEspTrib')),
                'valor_servico'        => $this->formatter->currency($this->val($valores, 'vServPrest', 'vServ')),
                'bc_issqn'             => $this->currencyOrDash($this->val($tribMun, 'vBC')),
                'aliquota'             => $this->percentOrDash($this->val($tribMun, 'pAliq')),
                'retencao_issqn'       => TipoRetencaoISSQN::labelFor($this->val($tribMun, 'tpRetISSQN')),
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
                'valor_servico'           => $this->formatter->currency($this->val($valores, 'vServPrest', 'vServ')),
                'desconto_condicionado'   => $this->currencyOrDash($this->val($tribMun, 'vDescCond')),
                'desconto_incondicionado' => $this->currencyOrDash($this->val($tribMun, 'vDescIncond')),
                'issqn_retido'            => ($this->val($tribMun, 'vISSQN') !== '' && ($this->val($tribMun, 'tpRetISSQN') ?: '1') !== '1')
                    ? $this->formatter->currency($this->val($tribMun, 'vISSQN'))
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
            $this->val($endereco, 'xBairro'),
        ], static fn (string $value): bool => $value !== '');

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
