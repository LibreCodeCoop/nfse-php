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

        // indDest and dest precede the intermediary and service groups.
        $beforeRecipient = $this->child($infDps, 'interm') ?? $this->child($infDps, 'serv');
        if (!$beforeRecipient instanceof \DOMElement) {
            throw new \LogicException('Missing NT009 recipient insertion anchor');
        }
        $infDps->insertBefore(
            $document->createElement('indDest', (string) $preview->indicadorDestinatario),
            $beforeRecipient
        );
        if ($preview->destinatario !== null) {
            if ($preview->indicadorDestinatario !== 1) {
                throw new \InvalidArgumentException('NT009 dest is not allowed when indDest=0');
            }
            $recipient = (new Nt009RecipientPreviewGroup())->build($document, $preview->destinatario);
            $infDps->insertBefore($recipient, $beforeRecipient);
        }

        // The new vAjusteBC replaces the old vDedRed, which is rejected above.
        if ($preview->ajusteBase !== null) {
            $rootValues = $this->child($infDps, 'valores');
            $municipalTax = $rootValues instanceof \DOMElement ? $this->child($rootValues, 'trib') : null;
            if (!$rootValues instanceof \DOMElement || !$municipalTax instanceof \DOMElement) {
                throw new \LogicException('Missing NT009 valores/trib insertion anchor');
            }
            $rootValues->insertBefore(
                (new Nt009BaseAdjustmentPreviewGroup())->build($document, $preview->ajusteBase),
                $municipalTax
            );
        }

        if ($preview->cst !== null) {
            $ibs = $document->createElement('IBSCBS');
            if ($preview->indicadorUsoPessoal !== null) {
                $ibs->appendChild($document->createElement('indFinal', (string) $preview->indicadorUsoPessoal));
            }
            if ($preview->codigoIndicadorOperacao !== null) {
                $ibs->appendChild($document->createElement('cIndOp', $preview->codigoIndicadorOperacao));
            }
            if ($preview->indicadorZfmAlc !== null) {
                $ibs->appendChild($document->createElement('indZFMALC', (string) $preview->indicadorZfmAlc));
            }
            if ($preview->tipoOperacao !== null) {
                $ibs->appendChild($document->createElement('tpOper', (string) $preview->tipoOperacao));
            }
            if ($preview->notasFiscaisReferenciadas !== []) {
                $references = $document->createElement('gRefNFSe');
                foreach ($preview->notasFiscaisReferenciadas as $key) {
                    $ref = $document->createElement('refNFSe');
                    $ref->appendChild($document->createTextNode($key));
                    $references->appendChild($ref);
                }
                $ibs->appendChild($references);
            }
            if ($preview->tipoEnteGovernamental !== null) {
                $ibs->appendChild($document->createElement('tpEnteGov', (string) $preview->tipoEnteGovernamental));
            }
            if ($preview->doacaoSemContraprestacao === true) {
                $ibs->appendChild($document->createElement('indDoacao', '1'));
            }
            if ($preview->imovel !== null) {
                $ibs->appendChild(
                    (new Nt009RealEstatePreviewGroup())->build(
                        $document,
                        $preview->imovel,
                        $dps->codigoTributacaoNacional
                    )
                );
            }
            foreach ((new Nt009MovableAssetsPreviewGroup())->build(
                $document,
                $preview->bensMoveis,
                $dps->codigoTributacaoNacional
            ) as $asset) {
                $ibs->appendChild($asset);
            }
            if ($preview->condominios !== null) {
                $ibs->appendChild(
                    (new Nt009CondominiumPreviewGroup())->build(
                        $document,
                        $preview->condominios,
                        $dps->codigoTributacaoNacional,
                        $dps->valorServico
                    )
                );
            }
            $valores = $document->createElement('valores');
            $trib = $document->createElement('trib');
            // NT009: CST and cClassTrib moved directly beneath trib.
            $trib->appendChild($document->createElement('CST', $preview->cst));
            $trib->appendChild($document->createElement('cClassTrib', (string) $preview->classificacaoTributaria));
            if ($preview->exigeGrupoIbsCbs) {
                $group = $document->createElement('gIBSCBS');
                if ($preview->codigoCreditoPresumido !== null) {
                    $group->appendChild($document->createElement('cCredPres', $preview->codigoCreditoPresumido));
                }
                if ($preview->valorAjusteIbs !== null) {
                    $adjustment = $document->createElement('gIBSCBSAjuste');
                    $adjustment->appendChild($document->createElement('vIBS', $preview->valorAjusteIbs));
                    $adjustment->appendChild($document->createElement('vCBS', (string) $preview->valorAjusteCbs));
                    $group->appendChild($adjustment);
                }
                if ($preview->tributacaoRegular !== null) {
                    $regular = $document->createElement('gTribRegular');
                    $regular->appendChild($document->createElement('CSTReg', $preview->tributacaoRegular->cst));
                    $regular->appendChild($document->createElement(
                        'cClassTribReg',
                        $preview->tributacaoRegular->classificacao
                    ));
                    $group->appendChild($regular);
                }
                if ($preview->diferimento !== null) {
                    $deferral = $document->createElement('gDif');
                    $deferral->appendChild($document->createElement('pDifUF', $preview->diferimento->percentualUf));
                    $deferral->appendChild($document->createElement('pDifMun', $preview->diferimento->percentualMunicipio));
                    $deferral->appendChild($document->createElement('pDifCBS', $preview->diferimento->percentualCbs));
                    $group->appendChild($deferral);
                }
                if ($preview->valorEstornoIbs !== null) {
                    // Annex VI rows 438-440: allowed/required exclusively
                    // by the caller-verified cClassTrib.ind_gEstornoCred.
                    $reversal = $document->createElement('gEstornoCred');
                    $reversal->appendChild($document->createElement('vIBSEstCred', $preview->valorEstornoIbs));
                    $reversal->appendChild($document->createElement('vCBSEstCred', (string) $preview->valorEstornoCbs));
                    $group->appendChild($reversal);
                }
                if ($preview->notasPagamentoAntecipado !== []) {
                    // Annex VI rows 441-442: explicit references only;
                    // the library never invents a prior paid invoice.
                    $advance = $document->createElement('gPagAntecipado');
                    foreach ($preview->notasPagamentoAntecipado as $key) {
                        $ref = $document->createElement('refNFSe');
                        $ref->appendChild($document->createTextNode($key));
                        $advance->appendChild($ref);
                    }
                    $group->appendChild($advance);
                }
                $trib->appendChild($group);
            }
            $valores->appendChild($trib);
            $ibs->appendChild($valores);
            if ($preview->pagamentosVinculados !== []) {
                $ibs->appendChild(
                    (new Nt009LinkedPaymentsPreviewGroup())->build($document, $preview->pagamentosVinculados)
                );
            }
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
            || $preview->exigeEstornoCredito !== null
            || $preview->valorEstornoIbs !== null
            || $preview->valorEstornoCbs !== null
            || $preview->notasPagamentoAntecipado !== []
            || $preview->indicadorZfmAlc !== null
            || $preview->tipoOperacao !== null
            || $preview->notasFiscaisReferenciadas !== []
            || $preview->tipoEnteGovernamental !== null
            || $preview->doacaoSemContraprestacao === true
            || $preview->tributacaoRegular !== null
            || $preview->diferimento !== null
            || $preview->exigeGrupoIbsCbs !== null
            || $preview->bensMoveis !== []
            || $preview->pagamentosVinculados !== []
            || $preview->imovel !== null
            || $preview->condominios !== null;

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
                || $preview->valorAjusteIbs !== null || $preview->valorAjusteCbs !== null
                || $preview->exigeEstornoCredito === true
                || $preview->valorEstornoIbs !== null || $preview->valorEstornoCbs !== null
                || $preview->notasPagamentoAntecipado !== []
                || $preview->tributacaoRegular !== null
                || $preview->diferimento !== null)) {
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
        if (($preview->valorEstornoIbs === null) !== ($preview->valorEstornoCbs === null)) {
            throw new \InvalidArgumentException('NT009 gEstornoCred requires both vIBSEstCred and vCBSEstCred');
        }
        if ($preview->exigeEstornoCredito === true && $preview->valorEstornoIbs === null) {
            throw new \InvalidArgumentException('NT009 caller-verified ind_gEstornoCred requires both reversal amounts');
        }
        if ($preview->valorEstornoIbs !== null) {
            if ($preview->exigeEstornoCredito !== true) {
                throw new \InvalidArgumentException('NT009 gEstornoCred requires caller-verified ind_gEstornoCred');
            }
            if (preg_match('/^[0-9]{1,15}\.[0-9]{2}$/D', $preview->valorEstornoIbs) !== 1
                || preg_match('/^[0-9]{1,15}\.[0-9]{2}$/D', (string) $preview->valorEstornoCbs) !== 1) {
                throw new \InvalidArgumentException('NT009 gEstornoCred requires decimal vIBSEstCred and vCBSEstCred strings');
            }
        }
        $this->validateInvoiceKeys($preview->notasPagamentoAntecipado, 'gPagAntecipado');

        if ($preview->indicadorZfmAlc !== null) {
            // Annex VI row 377: both operation-code whitelist and geographic
            // requirements must hold; location is verified by the caller.
            $eligibleOperations = [
                '010101', '010102', '010103', '010106', '020101', '020201',
                '020301', '030101', '030102', '050101', '050102', '050201',
                '060101', '070101', '070102', '100301', '100302', '100401',
                '100501', '100502', '100601',
            ];
            if (!in_array($preview->indicadorZfmAlc, [0, 1], true)
                || !in_array($preview->codigoIndicadorOperacao, $eligibleOperations, true)
                || $preview->elegibilidadeZfmAlcConfirmada !== true) {
                throw new \InvalidArgumentException(
                    'NT009 indZFMALC requires a published eligible cIndOp and caller-verified ZFM/ALC location'
                );
            }
        }
        if ($preview->tipoOperacao !== null
            && !in_array($preview->tipoOperacao, [1, 2, 3, 4, 5], true)) {
            throw new \InvalidArgumentException('NT009 tpOper must be 1-5');
        }
        if (in_array($preview->tipoOperacao, [2, 3], true)
            && $preview->notasFiscaisReferenciadas === []) {
            throw new \InvalidArgumentException('NT009 tpOper 2 or 3 requires gRefNFSe');
        }
        $this->validateInvoiceKeys($preview->notasFiscaisReferenciadas, 'gRefNFSe');
        if ($preview->tipoEnteGovernamental !== null
            && (!in_array($preview->tipoEnteGovernamental, [1, 2, 3, 4], true)
                || $preview->compraGovernamentalConfirmada !== true)) {
            throw new \InvalidArgumentException('NT009 tpEnteGov requires a government purchase and code 1-4');
        }
        if ($preview->doacaoSemContraprestacao === false) {
            throw new \InvalidArgumentException(
                'NT009 indDoacao must be omitted for transactions with consideration'
            );
        }
        if ($preview->tributacaoRegular !== null) {
            if (preg_match('/^[0-9]{3}$/D', $preview->tributacaoRegular->cst) !== 1
                || preg_match('/^[0-9]{6}$/D', $preview->tributacaoRegular->classificacao) !== 1) {
                throw new \InvalidArgumentException('NT009 gTribRegular requires CSTReg (3) and cClassTribReg (6)');
            }
        }
        if ($preview->diferimento !== null) {
            foreach ([
                $preview->diferimento->percentualUf,
                $preview->diferimento->percentualMunicipio,
                $preview->diferimento->percentualCbs,
            ] as $percentual) {
                if (preg_match('/^[0-9]{1,3}\.[0-9]{2}$/D', $percentual) !== 1) {
                    throw new \InvalidArgumentException('NT009 gDif requires three percentage strings (1-3V2)');
                }
            }
        }
    }

    /**
     * @param array<array-key, mixed> $keys
     */
    private function validateInvoiceKeys(array $keys, string $group): void
    {
        if (count($keys) > 99) {
            throw new \InvalidArgumentException('NT009 ' . $group . ' allows at most 99 refNFSe keys');
        }
        foreach ($keys as $key) {
            if (!is_string($key) || mb_strlen($key) !== 50 || preg_match('/\p{Cc}/u', $key) !== 0) {
                throw new \InvalidArgumentException(
                    'NT009 ' . $group . '/refNFSe must contain 50 valid characters'
                );
            }
        }
    }

    private function rejectLegacyIbsCbs(DpsData $dps): void
    {
        // Legacy vDedRed and NT009 vAjusteBC use different field names,
        // alternatives and rules. Never pass the old group through silently.
        if ($dps->deducaoReducao !== null) {
            throw new \InvalidArgumentException(
                'NT009 preview cannot reuse legacy vDedRed; supply ajusteBase explicitly'
            );
        }
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
