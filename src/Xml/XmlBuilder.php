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
    private const XSD_SCHEMA    = 'http://www.sped.fazenda.gov.br/nfse DPS_v1.01.xsd';
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

        if ($this->hasTomador($dps)) {
            $infDps->appendChild($this->buildToma($doc, $dps));
        }

        $infDps->appendChild($this->buildServico($doc, $dps));
        $infDps->appendChild($this->buildValores($doc, $dps));

        if ($this->hasIbsCbsConfiguration($dps)) {
            $infDps->appendChild($this->buildIbsCbs($doc, $dps));
        }

        return $doc->saveXML() ?: '';
    }

    private function buildIbsCbs(\DOMDocument $doc, DpsData $dps): \DOMElement
    {
        $missing = [];

        if ($dps->ibsCbsFinalidade === null) {
            $missing[] = 'finNFSe';
        }

        if ($dps->ibsCbsCodigoIndicadorOperacao === '') {
            $missing[] = 'cIndOp';
        }

        if ($dps->ibsCbsIndDest === null) {
            $missing[] = 'indDest';
        }

        if ($dps->ibsCbsCst === '') {
            $missing[] = 'CST';
        }

        if ($dps->ibsCbsClassificacaoTributaria === '') {
            $missing[] = 'cClassTrib';
        }

        if ($missing !== []) {
            throw new \InvalidArgumentException(
                'Incomplete IBSCBS configuration: missing ' . implode(', ', $missing),
            );
        }

        $ibsCbs = $doc->createElement('IBSCBS');
        $ibsCbs->appendChild($doc->createElement('finNFSe', (string) $dps->ibsCbsFinalidade));

        if ($dps->ibsCbsIndFinal !== null) {
            $ibsCbs->appendChild($doc->createElement('indFinal', (string) $dps->ibsCbsIndFinal));
        }

        $ibsCbs->appendChild($doc->createElement('cIndOp', $dps->ibsCbsCodigoIndicadorOperacao));
        $ibsCbs->appendChild($doc->createElement('indDest', (string) $dps->ibsCbsIndDest));

        $valores = $doc->createElement('valores');
        $trib = $doc->createElement('trib');
        $gIbsCbs = $doc->createElement('gIBSCBS');
        $gIbsCbs->appendChild($doc->createElement('CST', $dps->ibsCbsCst));
        $gIbsCbs->appendChild($doc->createElement('cClassTrib', $dps->ibsCbsClassificacaoTributaria));
        $trib->appendChild($gIbsCbs);
        $valores->appendChild($trib);
        $ibsCbs->appendChild($valores);

        return $ibsCbs;
    }

    private function hasIbsCbsConfiguration(DpsData $dps): bool
    {
        return $dps->ibsCbsFinalidade !== null
            || $dps->ibsCbsIndFinal !== null
            || $dps->ibsCbsCodigoIndicadorOperacao !== ''
            || $dps->ibsCbsIndDest !== null
            || $dps->ibsCbsCst !== ''
            || $dps->ibsCbsClassificacaoTributaria !== '';
    }

    private function buildIdentifier(DpsData $dps): string
    {
        // TSIdDPS uses the federal registration type, not tpAmb.
        // This client issues on behalf of legal entities identified by CNPJ,
        // whose registration type is 2 (CPF is 1).
        return 'DPS'
            . $dps->municipioIbge
            . '2'
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

        $trib->appendChild($this->buildTribFederal($doc, $dps));
        $trib->appendChild($this->buildTotTrib($doc, $dps));

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

        $percentuais->appendChild($doc->createElement('pTotTribFed', $dps->totalTributosPercentualFederal !== '' ? $dps->totalTributosPercentualFederal : '0.00'));
        $percentuais->appendChild($doc->createElement('pTotTribEst', $dps->totalTributosPercentualEstadual !== '' ? $dps->totalTributosPercentualEstadual : '0.00'));
        $percentuais->appendChild($doc->createElement('pTotTribMun', $dps->totalTributosPercentualMunicipal !== '' ? $dps->totalTributosPercentualMunicipal : '0.00'));

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

        if ($dps->tomadorNif !== '') {
            $toma->appendChild($doc->createElement('NIF', $dps->tomadorNif));
        } elseif ($dps->tomadorCodigoNaoNif !== null) {
            if (!in_array($dps->tomadorCodigoNaoNif, [0, 1, 2], true)) {
                throw new \InvalidArgumentException('Foreign service taker cNaoNIF must be 0, 1 or 2.');
            }

            $toma->appendChild($doc->createElement('cNaoNIF', (string) $dps->tomadorCodigoNaoNif));
        } elseif ($docLen === 14) {
            $toma->appendChild($doc->createElement('CNPJ', $dps->documentoTomador));
        } elseif ($docLen === 11) {
            $toma->appendChild($doc->createElement('CPF', $dps->documentoTomador));
        } else {
            throw new \InvalidArgumentException('Service taker identification must be CNPJ, CPF, NIF or cNaoNIF.');
        }

        if ($dps->nomeTomador === '') {
            throw new \InvalidArgumentException('Service taker name is required when a taker is informed.');
        }

        $toma->appendChild($doc->createElement('xNome', htmlspecialchars($dps->nomeTomador, ENT_XML1)));

        if ($dps->tomadorInscricaoMunicipal !== '') {
            $toma->appendChild($doc->createElement('IM', $dps->tomadorInscricaoMunicipal));
        }

        if ($this->hasTomadorAddressConfiguration($dps)) {
            $toma->appendChild($this->buildTomadorAddress($doc, $dps));
        }

        if ($dps->tomadorTelefone !== '') {
            $toma->appendChild($doc->createElement('fone', $dps->tomadorTelefone));
        }

        if ($dps->tomadorEmail !== '') {
            $toma->appendChild($doc->createElement('email', htmlspecialchars($dps->tomadorEmail, ENT_XML1)));
        }

        return $toma;
    }

    private function hasTomador(DpsData $dps): bool
    {
        return $dps->documentoTomador !== ''
            || $dps->tomadorNif !== ''
            || $dps->tomadorCodigoNaoNif !== null;
    }

    private function hasTomadorAddressConfiguration(DpsData $dps): bool
    {
        return $dps->tomadorCodigoMunicipio !== ''
            || $dps->tomadorCep !== ''
            || $dps->tomadorPaisCodigo !== ''
            || $dps->tomadorCodigoPostalExterior !== ''
            || $dps->tomadorCidadeExterior !== ''
            || $dps->tomadorEstadoExterior !== '';
    }

    private function buildTomadorAddress(\DOMDocument $doc, DpsData $dps): \DOMElement
    {
        $end = $doc->createElement('end');
        $hasForeignAddress = $dps->tomadorPaisCodigo !== ''
            || $dps->tomadorCodigoPostalExterior !== ''
            || $dps->tomadorCidadeExterior !== ''
            || $dps->tomadorEstadoExterior !== '';

        if ($hasForeignAddress) {
            foreach ([
                'country' => $dps->tomadorPaisCodigo,
                'postal code' => $dps->tomadorCodigoPostalExterior,
                'city' => $dps->tomadorCidadeExterior,
                'state/province/region' => $dps->tomadorEstadoExterior,
            ] as $field => $value) {
                if ($value === '') {
                    throw new \InvalidArgumentException('Incomplete foreign service taker address: missing ' . $field . '.');
                }
            }

            if (!preg_match('/^[A-Z]{2}$/', strtoupper($dps->tomadorPaisCodigo))) {
                throw new \InvalidArgumentException('Foreign service taker country must be an ISO alpha-2 code.');
            }

            $endExt = $doc->createElement('endExt');
            $endExt->appendChild($doc->createElement('cPais', strtoupper($dps->tomadorPaisCodigo)));
            $endExt->appendChild($doc->createElement('cEndPost', $dps->tomadorCodigoPostalExterior));
            $endExt->appendChild($doc->createElement('xCidade', $dps->tomadorCidadeExterior));
            $endExt->appendChild($doc->createElement('xEstProvReg', $dps->tomadorEstadoExterior));
            $end->appendChild($endExt);
        } else {
            if ($dps->tomadorCodigoMunicipio === '' || $dps->tomadorCep === '') {
                throw new \InvalidArgumentException('Incomplete national service taker address: municipality and CEP are required.');
            }

            $endNac = $doc->createElement('endNac');
            $endNac->appendChild($doc->createElement('cMun', $dps->tomadorCodigoMunicipio));
            $endNac->appendChild($doc->createElement('CEP', $dps->tomadorCep));
            $end->appendChild($endNac);
        }

        foreach ([
            'xLgr' => $dps->tomadorLogradouro,
            'nro' => $dps->tomadorNumero,
            'xBairro' => $dps->tomadorBairro,
        ] as $tag => $value) {
            if ($value === '') {
                throw new \InvalidArgumentException('Service taker address requires ' . $tag . '.');
            }
        }

        $end->appendChild($doc->createElement('xLgr', $dps->tomadorLogradouro));
        $end->appendChild($doc->createElement('nro', $dps->tomadorNumero));

        if ($dps->tomadorComplemento !== '') {
            $end->appendChild($doc->createElement('xCpl', $dps->tomadorComplemento));
        }

        $end->appendChild($doc->createElement('xBairro', $dps->tomadorBairro));

        return $end;
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
