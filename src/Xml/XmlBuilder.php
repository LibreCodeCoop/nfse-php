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

        $root = $doc->createElementNS(self::XSD_NAMESPACE, 'DPS');
        $root->setAttribute('versao', self::DPS_VERSION);
        $root->setAttribute('xsi:schemaLocation', self::XSD_SCHEMA);
        $root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $doc->appendChild($root);

        $infDps = $doc->createElement('infDPS');
        $infDps->setAttribute('Id', $this->buildIdentifier($dps));
        $root->appendChild($infDps);

        // Municipality
        $cMun = $doc->createElement('cMun', $dps->municipioIbge);
        $infDps->appendChild($cMun);
        $infDps->appendChild($doc->createElement('cLocEmi', $dps->municipioIbge));

        // Prestador
        $prest = $doc->createElement('prest');
        $cnpj  = $doc->createElement('CNPJ', $dps->cnpjPrestador);
        $prest->appendChild($cnpj);
        $prest->appendChild($this->buildRegTrib($doc, $dps));
        $infDps->appendChild($prest);

        // Service block
        $serv = $doc->createElement('serv');

        $itemListaServico = $doc->createElement('cServ');
        $itemListaServico->appendChild($doc->createElement('cTribNac', $dps->codigoTributacaoNacional));
        $serv->appendChild($itemListaServico);

        $serv->appendChild($doc->createElement('xDescServ', htmlspecialchars($dps->discriminacao, ENT_XML1)));
        $infDps->appendChild($serv);

        // Tomador (optional — absent for foreign buyers with no document)
        if ($dps->documentoTomador !== '') {
            $infDps->appendChild($this->buildToma($doc, $dps));
        }

        // Values
        $valores = $doc->createElement('valores');

        $vServPrest = $doc->createElement('vServPrest');
        $vServPrest->appendChild($doc->createElement('vServ', $dps->valorServico));
        $valores->appendChild($vServPrest);

        $valores->appendChild($this->buildTotTrib($doc, $dps));
        $infDps->appendChild($valores);

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

    private function buildTotTrib(\DOMDocument $doc, DpsData $dps): \DOMElement
    {
        $totTrib = $doc->createElement('totTrib');

        // tribMun contains ISS and conditional pAliq
        $tribMun = $doc->createElement('tribMun');
        $tribMun->appendChild($doc->createElement('tribISSQN', $dps->issRetido ? '2' : '1'));
        $tribMun->appendChild($doc->createElement('tpRetISSQN', (string) $dps->tipoRetencaoIss));

        // E0617: For não optante (opSimpNac=1), pAliq must NOT be present
        if ($dps->opcaoSimplesNacional !== 1) {
            $tribMun->appendChild($doc->createElement('pAliq', $dps->aliquota));
        }

        $totTrib->appendChild($tribMun);

        if ($this->hasFederalTaxationData($dps)) {
            $totTrib->appendChild($this->buildTribFederal($doc, $dps));
        }

        // E0715: indTotTrib is ALWAYS included to avoid schema validation errors
        $totTrib->appendChild($doc->createElement('indTotTrib', (string) $dps->indicadorTributacao));

        return $totTrib;
    }

    private function buildRegTrib(\DOMDocument $doc, DpsData $dps): \DOMElement
    {
        $regTrib = $doc->createElement('regTrib');
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

        if ($dps->federalPiscofinsSituacaoTributaria !== '') {
            $tribFed->appendChild($doc->createElement('sitTribPISCOFINS', $dps->federalPiscofinsSituacaoTributaria));
        }

        if ($dps->federalPiscofinsTipoRetencao !== '') {
            $tribFed->appendChild($doc->createElement('tpRetPISCOFINSCSLL', $dps->federalPiscofinsTipoRetencao));
        }

        foreach ([
            'vBCPISCOFINS' => $dps->federalPiscofinsBaseCalculo,
            'pAliqPIS' => $dps->federalPiscofinsAliquotaPis,
            'vPIS' => $dps->federalPiscofinsValorPis,
            'pAliqCOFINS' => $dps->federalPiscofinsAliquotaCofins,
            'vCOFINS' => $dps->federalPiscofinsValorCofins,
            'vIRRF' => $dps->federalValorIrrf,
            'vCSLL' => $dps->federalValorCsll,
            'vCP' => $dps->federalValorCp,
        ] as $tag => $value) {
            if ($value !== '') {
                $tribFed->appendChild($doc->createElement($tag, $value));
            }
        }

        return $tribFed;
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
            || $dps->federalValorIrrf !== ''
            || $dps->federalValorCsll !== ''
            || $dps->federalValorCp !== '';
    }
}
