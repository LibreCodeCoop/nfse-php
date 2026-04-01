<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Xml;

use LibreCodeCoop\NfsePHP\Dto\DpsData;

/**
 * Builds the DPS (Documento Padrão de Serviço) XML payload.
 * Spec: ABRASF 2.04 / SEFIN Nacional 1.0.
 */
class XmlBuilder
{
    private const XSD_NAMESPACE = 'http://www.sped.fazenda.gov.br/nfse';
    private const XSD_SCHEMA    = 'http://www.sped.fazenda.gov.br/nfse tiDPS_v1.00.xsd';
    private const DPS_VERSION   = '1.01';

    public function buildDps(DpsData $dps): string
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput       = true;

        $emissionDateTime = $this->formattedEmissionDateTime();
        $competenceDate = $dps->dataCompetencia ?: substr($emissionDateTime, 0, 10);

        $root = $doc->createElementNS(self::XSD_NAMESPACE, 'DPS');
        $root->setAttribute('versao', self::DPS_VERSION);
        $root->setAttribute('xsi:schemaLocation', self::XSD_SCHEMA);
        $root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $doc->appendChild($root);

        $infDps = $doc->createElement('infDPS');
        $infDps->setAttribute('Id', $this->buildIdentifier($dps));
        $root->appendChild($infDps);

        $infDps->appendChild($doc->createElement('tpAmb', (string) $dps->tipoAmbiente));
        $infDps->appendChild($doc->createElement('dhEmi', $emissionDateTime));
        $infDps->appendChild($doc->createElement('verAplic', $dps->versaoAplicativo));
        $infDps->appendChild($doc->createElement('serie', str_pad($dps->serie, 5, '0', STR_PAD_LEFT)));
        $infDps->appendChild($doc->createElement('nDPS', $dps->numeroDps));
        $infDps->appendChild($doc->createElement('dCompet', $competenceDate));
        $infDps->appendChild($doc->createElement('tpEmit', (string) $dps->tipoEmissao));
        $infDps->appendChild($doc->createElement('cLocEmi', $dps->municipioIbge));

        $prest = $doc->createElement('prest');
        $cnpj  = $doc->createElement('CNPJ', $dps->cnpjPrestador);
        $prest->appendChild($cnpj);
        $prest->appendChild($this->buildRegTrib($doc, $dps));
        $infDps->appendChild($prest);

        if ($dps->documentoTomador !== '') {
            $infDps->appendChild($this->buildToma($doc, $dps));
        }

        $infDps->appendChild($this->buildServico($doc, $dps));
        $infDps->appendChild($this->buildValores($doc, $dps));

        return $doc->saveXML() ?: '';
    }

    private function buildIdentifier(DpsData $dps): string
    {
        return 'DPS'
            . $dps->municipioIbge
            . $dps->tipoAmbiente
            . $dps->cnpjPrestador
            . str_pad($dps->serie, 5, '0', STR_PAD_LEFT)
            . str_pad($dps->numeroDps, 15, '0', STR_PAD_LEFT);
    }

    private function buildValores(\DOMDocument $doc, DpsData $dps): \DOMElement
    {
        $valores = $doc->createElement('valores');

        $vServPrest = $doc->createElement('vServPrest');
        $vServPrest->appendChild($doc->createElement('vServ', $dps->valorServico));
        $valores->appendChild($vServPrest);

        $trib = $doc->createElement('trib');
        $trib->appendChild($this->buildTribMun($doc, $dps));

        if ($this->hasFederalTaxationData($dps)) {
            $trib->appendChild($this->buildTribFederal($doc, $dps));
        }

        if ($this->hasTotalTributosPercentuais($dps)) {
            $trib->appendChild($this->buildTotTrib($doc, $dps));
        }

        $valores->appendChild($trib);

        return $valores;
    }

    private function buildTribMun(\DOMDocument $doc, DpsData $dps): \DOMElement
    {
        $tribMun = $doc->createElement('tribMun');
        $tribMun->appendChild($doc->createElement('tribISSQN', $dps->issRetido ? '2' : '1'));
        $tribMun->appendChild($doc->createElement('tpRetISSQN', (string) $dps->tipoRetencaoIss));

        if ($dps->opcaoSimplesNacional !== 1) {
            $tribMun->appendChild($doc->createElement('pAliq', $dps->aliquota));
        }

        return $tribMun;
    }

    private function buildTotTrib(\DOMDocument $doc, DpsData $dps): \DOMElement
    {
        $totTrib = $doc->createElement('totTrib');
        $percentuais = $doc->createElement('pTotTrib');

        if ($dps->totalTributosPercentualFederal !== '') {
            $percentuais->appendChild($doc->createElement('pTotTribFed', $dps->totalTributosPercentualFederal));
        }

        if ($dps->totalTributosPercentualEstadual !== '') {
            $percentuais->appendChild($doc->createElement('pTotTribEst', $dps->totalTributosPercentualEstadual));
        }

        if ($dps->totalTributosPercentualMunicipal !== '') {
            $percentuais->appendChild($doc->createElement('pTotTribMun', $dps->totalTributosPercentualMunicipal));
        }

        $totTrib->appendChild($percentuais);

        return $totTrib;
    }

    private function buildServico(\DOMDocument $doc, DpsData $dps): \DOMElement
    {
        $serv = $doc->createElement('serv');

        $locPrest = $doc->createElement('locPrest');
        $locPrest->appendChild($doc->createElement('cLocPrestacao', $dps->municipioIbge));
        $serv->appendChild($locPrest);

        $cServ = $doc->createElement('cServ');
        $cServ->appendChild($doc->createElement('cTribNac', $dps->codigoTributacaoNacional));

        if ($dps->itemListaServico !== '') {
            $cServ->appendChild($doc->createElement('cTribMun', $dps->itemListaServico));
        }

        $cServ->appendChild($doc->createElement('xDescServ', htmlspecialchars($dps->discriminacao, ENT_XML1)));
        $serv->appendChild($cServ);

        return $serv;
    }

    private function buildRegTrib(\DOMDocument $doc, DpsData $dps): \DOMElement
    {
        $regTrib = $doc->createElement('regTrib');
        $regTrib->appendChild($doc->createElement('opSimpNac', (string) $dps->opcaoSimplesNacional));
        $regTrib->appendChild($doc->createElement('regEspTrib', (string) $dps->regimeEspecialTributacao));

        return $regTrib;
    }

    private function buildToma(\DOMDocument $doc, DpsData $dps): \DOMElement
    {
        $toma = $doc->createElement('toma');

        $docLen = strlen($dps->documentoTomador);

        if ($docLen === 14) {
            $toma->appendChild($doc->createElement('CNPJ', $dps->documentoTomador));
        } elseif ($docLen === 11) {
            $toma->appendChild($doc->createElement('CPF', $dps->documentoTomador));
        }

        if ($dps->nomeTomador !== '') {
            $toma->appendChild($doc->createElement('xNome', htmlspecialchars($dps->nomeTomador, ENT_XML1)));
        }

        return $toma;
    }

    private function buildTribFederal(\DOMDocument $doc, DpsData $dps): \DOMElement
    {
        $tribFed = $doc->createElement('tribFed');
        $piscofins = $doc->createElement('piscofins');

        if ($dps->federalPiscofinsSituacaoTributaria !== '') {
            $piscofins->appendChild($doc->createElement('CST', str_pad($dps->federalPiscofinsSituacaoTributaria, 2, '0', STR_PAD_LEFT)));
        }

        foreach ([
            'vBCPisCofins' => $dps->federalPiscofinsBaseCalculo,
            'pAliqPis' => $dps->federalPiscofinsAliquotaPis,
            'pAliqCofins' => $dps->federalPiscofinsAliquotaCofins,
            'vPis' => $dps->federalPiscofinsValorPis,
            'vCofins' => $dps->federalPiscofinsValorCofins,
        ] as $tag => $value) {
            if ($value !== '') {
                $piscofins->appendChild($doc->createElement($tag, $value));
            }
        }

        if ($dps->federalPiscofinsTipoRetencao !== '') {
            $piscofins->appendChild($doc->createElement('tpRetPisCofins', $dps->federalPiscofinsTipoRetencao));
        }

        if ($piscofins->hasChildNodes()) {
            $tribFed->appendChild($piscofins);
        }

        foreach ([
            'vRetIRRF' => $dps->federalValorIrrf,
            'vRetCSLL' => $dps->federalValorCsll,
            'vRetCP' => $dps->federalValorCp,
        ] as $tag => $value) {
            if ($this->hasNonZeroDecimalValue($value)) {
                $tribFed->appendChild($doc->createElement($tag, $value));
            }
        }

        return $tribFed;
    }

    private function formattedEmissionDateTime(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d\\TH:i:sP');
    }

    private function hasTotalTributosPercentuais(DpsData $dps): bool
    {
        return $dps->totalTributosPercentualFederal !== ''
            || $dps->totalTributosPercentualEstadual !== ''
            || $dps->totalTributosPercentualMunicipal !== '';
    }

    private function hasFederalTaxationData(DpsData $dps): bool
    {
        return $dps->federalPiscofinsSituacaoTributaria !== ''
            || $dps->federalPiscofinsTipoRetencao !== ''
            || $dps->federalPiscofinsBaseCalculo !== ''
            || $dps->federalPiscofinsAliquotaPis !== ''
            || $dps->federalPiscofinsValorPis !== ''
            || $dps->federalPiscofinsAliquotaCofins !== ''
            || $dps->federalPiscofinsValorCofins !== ''
            || $this->hasNonZeroDecimalValue($dps->federalValorIrrf)
            || $this->hasNonZeroDecimalValue($dps->federalValorCsll)
            || $this->hasNonZeroDecimalValue($dps->federalValorCp);
    }

    private function hasNonZeroDecimalValue(string $value): bool
    {
        return $value !== '' && (float) $value !== 0.0;
    }
}
