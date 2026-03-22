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

    public function buildDps(DpsData $dps): string
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput       = true;

        $root = $doc->createElementNS(self::XSD_NAMESPACE, 'DPS');
        $root->setAttribute('xsi:schemaLocation', self::XSD_SCHEMA);
        $root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $doc->appendChild($root);

        $infDps = $doc->createElement('infDPS');
        $infDps->setAttribute('Id', 'DPS' . $dps->cnpjPrestador . date('YmdHis'));
        $root->appendChild($infDps);

        // Municipality
        $cMun = $doc->createElement('cMun', $dps->municipioIbge);
        $infDps->appendChild($cMun);

        // Prestador
        $prest = $doc->createElement('prest');
        $cnpj  = $doc->createElement('CNPJ', $dps->cnpjPrestador);
        $prest->appendChild($cnpj);
        $infDps->appendChild($prest);

        // Service block
        $serv = $doc->createElement('serv');

        $itemListaServico = $doc->createElement('cServ');
        $itemListaServico->appendChild($doc->createElement('cTribNac', $dps->itemListaServico));
        $serv->appendChild($itemListaServico);

        $serv->appendChild($doc->createElement('xDescServ', htmlspecialchars($dps->discriminacao, ENT_XML1)));
        $infDps->appendChild($serv);

        // Tomador (optional — absent for foreign buyers with no document)
        if ($dps->documentoTomador !== '') {
            $infDps->appendChild($this->buildToma($doc, $dps));
        }

        // Values
        $valores = $doc->createElement('valores');
        $valores->appendChild($doc->createElement('vServ', $dps->valorServico));
        $valores->appendChild($this->buildTrib($doc, $dps));
        $infDps->appendChild($valores);

        // Regime especial de tributação (optional)
        if ($dps->regimeEspecialTributacao !== null) {
            $infDps->appendChild($doc->createElement('regEspTrib', (string) $dps->regimeEspecialTributacao));
        }

        return $doc->saveXML() ?: '';
    }

    private function buildTrib(\DOMDocument $doc, DpsData $dps): \DOMElement
    {
        $trib = $doc->createElement('tribMun');
        $trib->appendChild($doc->createElement('tribISSQN', $dps->issRetido ? '2' : '1'));
        $trib->appendChild($doc->createElement('pAliq', $dps->aliquota));

        return $trib;
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
}
