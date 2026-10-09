<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Xml;

use LibreCodeCoop\NfsePHP\Domain\OfficialDomainCatalog;
use LibreCodeCoop\NfsePHP\Dto\DpsData;
use LibreCodeCoop\NfsePHP\Dto\Nt009DpsPreview;
use LibreCodeCoop\NfsePHP\Dto\Nt009DpsPreviewData;

/**
 * Structural preview from official NT009 Annex VI, NOT a validated emitter.
 *
 * This is kept separate from buildDps() until the correct environment XSD and
 * deployment/effective date are established by the Sistema Nacional NFS-e.
 */
final class Nt009DpsPreviewBuilder
{
    public function __construct(
        private readonly XmlBuilder $legacyBuilder,
    ) {
    }

    public function build(DpsData $dps, Nt009DpsPreviewData $preview): Nt009DpsPreview
    {
        $this->rejectLegacyIbsCbs($dps);
        $this->validatePreview($preview);

        // Reuse the exact existing DPS implementation, including identifiers
        // and other older taxpayer fields, without replacing its public API.
        $xml = $this->legacyBuilder->buildDps($dps);
        $document = new \DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;
        if (!$document->loadXML($xml, LIBXML_NONET)) {
            throw new \LogicException('Legacy builder produced invalid XML');
        }
        $infDps = $document->getElementsByTagName('infDPS')->item(0);
        if (!$infDps instanceof \DOMElement) {
            throw new \LogicException('Missing infDPS in legacy DPS');
        }

        $beforeMunicipality = $this->child($infDps, 'cLocEmi');
        if ($beforeMunicipality === null) {
            throw new \LogicException('Missing legacy cLocEmi anchor');
        }
        $infDps->insertBefore($document->createElement('finNFSe', (string) $preview->finalidade), $beforeMunicipality);
        if ($preview->tipoNotaDebito !== null) {
            $infDps->insertBefore($document->createElement('tpNFSeDebito', $preview->tipoNotaDebito), $beforeMunicipality);
        }
        if ($preview->tipoNotaCredito !== null) {
            $infDps->insertBefore($document->createElement('tpNFSeCredito', $preview->tipoNotaCredito), $beforeMunicipality);
        }

        // Official row order: prest/regTrib/opSimpNac, regApTribSN,
        // regApIBSCBSSN, regEspTrib.
        if ($preview->regimeApuracaoSimples !== null) {
            $prest = $this->child($infDps, 'prest');
            $regTrib = $prest instanceof \DOMElement ? $this->child($prest, 'regTrib') : null;
            if (!$regTrib instanceof \DOMElement) {
                throw new \LogicException('Missing legacy provider tax regime group');
            }
            $reference = $this->child($regTrib, 'regEspTrib');
            $element = $document->createElement('regApIBSCBSSN', (string) $preview->regimeApuracaoSimples);
            if ($reference === null) {
                $regTrib->appendChild($element);
            } else {
                $regTrib->insertBefore($element, $reference);
            }
        }

        // In Annex VI, indDest and dest are outside IBSCBS, after toma and
        // before interm/serv. No speculative recipient identity is inferred.
        $beforeService = $this->child($infDps, 'serv');
        if ($beforeService === null) {
            throw new \LogicException('Missing legacy service group');
        }
        $infDps->insertBefore($document->createElement('indDest', (string) $preview->indicadorDestinatario), $beforeService);

        if ($preview->cst !== null) {
            $ibs = $document->createElement('IBSCBS');
            if ($preview->indicadorUsoPessoal !== null) {
                $ibs->appendChild($document->createElement('indFinal', (string) $preview->indicadorUsoPessoal));
            }
            if ($preview->codigoIndicadorOperacao !== null) {
                $ibs->appendChild($document->createElement('cIndOp', $preview->codigoIndicadorOperacao));
            }
            $valores = $document->createElement('valores');
            $trib = $document->createElement('trib');
            // NT009: CST and cClassTrib moved directly beneath trib.
            $trib->appendChild($document->createElement('CST', (string) $preview->cst));
            $trib->appendChild($document->createElement('cClassTrib', (string) $preview->classificacaoTributaria));
            if ($preview->exigeGrupoIbsCbs) {
                $group = $document->createElement('gIBSCBS');
                if ($preview->codigoCreditoPresumido !== null) {
                    $group->appendChild($document->createElement('cCredPres', $preview->codigoCreditoPresumido));
                }
                if ($preview->valorAjusteIbs !== null) {
                    $adjustment = $document->createElement('gIBSCBSAjuste');
                    $adjustment->appendChild($document->createElement('vIBS', $preview->valorAjusteIbs));
                    $adjustment->appendChild($document->createElement('vCBS', $preview->valorAjusteCbs));
                    $group->appendChild($adjustment);
                }
                $trib->appendChild($group);
            }
            $valores->appendChild($trib);
            $ibs->appendChild($valores);
            $infDps->appendChild($ibs);
        }

        // A preview must not pretend to reference the older official XSD.
        $document->documentElement?->removeAttributeNS(
            'http://www.w3.org/2001/XMLSchema-instance',
            'schemaLocation'
        );

        return new Nt009DpsPreview($document->saveXML() ?: '');
    }

    private function validatePreview(Nt009DpsPreviewData $preview): void
    {
        if (!in_array($preview->finalidade, [0, 1, 2], true)
            || !in_array($preview->indicadorDestinatario, [0, 1], true)) {
            throw new \InvalidArgumentException('NT009 finNFSe must be 0-2 and indDest must be 0-1');
        }
        if ($preview->finalidade === 0
            && ($preview->tipoNotaDebito !== null || $preview->tipoNotaCredito !== null)) {
            throw new \InvalidArgumentException('Regular NT009 NFS-e cannot carry credit/debit adjustment type');
        }
        if ($preview->finalidade === 1
            && (!in_array($preview->tipoNotaCredito, ['01', '05'], true)
                || $preview->tipoNotaDebito !== null)) {
            throw new \InvalidArgumentException('NT009 credit note requires tpNFSeCredito 01 or 05 only');
        }
        if ($preview->finalidade === 2
            && (!in_array($preview->tipoNotaDebito, ['01', '02', '03', '04', '05', '06'], true)
                || $preview->tipoNotaCredito !== null)) {
            throw new \InvalidArgumentException('NT009 debit note requires tpNFSeDebito 01-06 only');
        }
        if ($preview->regimeApuracaoSimples !== null
            && !in_array($preview->regimeApuracaoSimples, [1, 2, 3], true)) {
            throw new \InvalidArgumentException('NT009 regApIBSCBSSN must be 1-3');
        }
        if ($preview->indicadorUsoPessoal !== null && !in_array($preview->indicadorUsoPessoal, [0, 1], true)) {
            throw new \InvalidArgumentException('NT009 indFinal must be 0 or 1');
        }

        $hasIbs = $preview->cst !== null
            || $preview->classificacaoTributaria !== null
            || $preview->codigoIndicadorOperacao !== null
            || $preview->indicadorUsoPessoal !== null
            || $preview->codigoCreditoPresumido !== null
            || $preview->valorAjusteIbs !== null
            || $preview->valorAjusteCbs !== null
            || $preview->exigeGrupoIbsCbs !== null;

        if (!$hasIbs) {
            return;
        }
        if ($preview->cst === null || preg_match('/^[0-9]{3}$/D', $preview->cst) !== 1
            || $preview->classificacaoTributaria === null
            || preg_match('/^[0-9]{6}$/D', $preview->classificacaoTributaria) !== 1) {
            throw new \InvalidArgumentException('NT009 IBSCBS/valores/trib requires a 3-digit CST and 6-digit cClassTrib');
        }
        if ($preview->exigeGrupoIbsCbs === null) {
            throw new \InvalidArgumentException('NT009 requires caller-verified ind_gIBSCBS for the chosen classification');
        }
        if ($preview->codigoIndicadorOperacao !== null
            && !(new OfficialDomainCatalog())->hasOperationIndicator(
                $preview->codigoIndicadorOperacao,
                OfficialDomainCatalog::OPERATION_INDICATOR_NT009_VERSION
            )) {
            throw new \InvalidArgumentException('NT009 cIndOp is absent from official Annex VII v1.03.00');
        }
        if ($preview->exigeGrupoIbsCbs && $preview->codigoIndicadorOperacao === null) {
            throw new \InvalidArgumentException('NT009 cIndOp is required when caller-verified ind_gIBSCBS is true');
        }
        if (!$preview->exigeGrupoIbsCbs && $preview->codigoIndicadorOperacao !== null) {
            throw new \InvalidArgumentException('NT009 cIndOp is forbidden when caller-verified ind_gIBSCBS is false');
        }
        if (!$preview->exigeGrupoIbsCbs
            && ($preview->codigoCreditoPresumido !== null
                || $preview->valorAjusteIbs !== null || $preview->valorAjusteCbs !== null)) {
            throw new \InvalidArgumentException('NT009 forbids gIBSCBS children when ind_gIBSCBS is false');
        }
        if ($preview->codigoCreditoPresumido !== null
            && preg_match('/^[0-9]{2}$/D', $preview->codigoCreditoPresumido) !== 1) {
            throw new \InvalidArgumentException('NT009 cCredPres must contain two digits');
        }
        if (($preview->valorAjusteIbs === null) !== ($preview->valorAjusteCbs === null)) {
            throw new \InvalidArgumentException('NT009 gIBSCBSAjuste requires both vIBS and vCBS');
        }
        if ($preview->valorAjusteIbs !== null) {
            $allowed = ($preview->finalidade === 2 && in_array($preview->tipoNotaDebito, ['01', '02', '03', '05'], true))
                || ($preview->finalidade === 1 && $preview->tipoNotaCredito === '05');
            if (!$allowed) {
                throw new \InvalidArgumentException('NT009 gIBSCBSAjuste is forbidden for this adjustment-note type');
            }
            if (preg_match('/^[0-9]{1,15}\.[0-9]{2}$/D', $preview->valorAjusteIbs) !== 1
                || preg_match('/^[0-9]{1,15}\.[0-9]{2}$/D', (string) $preview->valorAjusteCbs) !== 1) {
                throw new \InvalidArgumentException('NT009 gIBSCBSAjuste requires decimal vIBS and vCBS strings');
            }
        }
    }

    private function rejectLegacyIbsCbs(DpsData $dps): void
    {
        if ($dps->ibsCbsFinalidade !== null
            || $dps->ibsCbsIndFinal !== null
            || $dps->ibsCbsCodigoIndicadorOperacao !== ''
            || $dps->ibsCbsIndDest !== null
            || $dps->ibsCbsCst !== ''
            || $dps->ibsCbsClassificacaoTributaria !== '') {
            throw new \InvalidArgumentException(
                'NT009 preview cannot mix legacy DpsData IBS/CBS fields; provide Nt009DpsPreviewData instead'
            );
        }
    }

    private function child(\DOMElement $element, string $name): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $name) {
                return $child;
            }
        }

        return null;
    }
}
