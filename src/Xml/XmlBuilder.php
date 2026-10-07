<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Xml;

use LibreCodeCoop\NfsePHP\Dto\DpsData;
use LibreCodeCoop\NfsePHP\Support\DpsIdentifier;

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

        if ($dps->substituicao !== null) {
            $infDps->appendChild($this->buildSubstitution($doc, $dps));
        }

        $prest = $doc->createElement('prest');
        $cnpj  = $doc->createElement('CNPJ', $dps->cnpjPrestador);
        $prest->appendChild($cnpj);

        if ($dps->prestadorTelefone !== '') {
            $prest->appendChild($doc->createElement('fone', $dps->prestadorTelefone));
        }

        if ($dps->prestadorEmail !== '') {
            $prest->appendChild($doc->createElement('email', htmlspecialchars($dps->prestadorEmail, ENT_XML1)));
        }

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

    private function buildSubstitution(\DOMDocument $doc, DpsData $dps): \DOMElement
    {
        $substitution = $dps->substituicao;

        if ($substitution === null) {
            throw new \LogicException('Substitution data is required.');
        }

        $subst = $doc->createElement('subst');
        $subst->appendChild($doc->createElement('chSubstda', $substitution->chaveNfseSubstituida));
        $subst->appendChild($doc->createElement('cMotivo', $substitution->codigoMotivo));

        if ($substitution->descricaoMotivo !== '') {
            $subst->appendChild(
                $doc->createElement(
                    'xMotivo',
                    htmlspecialchars($substitution->descricaoMotivo, ENT_XML1),
                ),
            );
        }

        return $subst;
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
        return DpsIdentifier::fromData($dps);
    }

    private function buildValores(\DOMDocument $doc, DpsData $dps): \DOMElement
    {
        $valores = $doc->createElement('valores');

        $vServPrest = $doc->createElement('vServPrest');
        $vServPrest->appendChild($doc->createElement('vServ', $dps->valorServico));
        $valores->appendChild($vServPrest);

        if ($dps->deducaoReducao !== null) {
            $valores->appendChild($this->buildDeductionReduction($doc, $dps));
        }

        $trib = $doc->createElement('trib');
        $trib->appendChild($this->buildTribMun($doc, $dps));

        $trib->appendChild($this->buildTribFederal($doc, $dps));
        $trib->appendChild($this->buildTotTrib($doc, $dps));

        $valores->appendChild($trib);

        return $valores;
    }

    private function buildTribMun(\DOMDocument $doc, DpsData $dps): \DOMElement
    {
        if (!in_array($dps->tributacaoIssqn, [1, 2, 3, 4], true)) {
            throw new \InvalidArgumentException('ISSQN taxation code must be 1, 2, 3 or 4.');
        }

        if (!in_array($dps->tipoRetencaoIss, [1, 2, 3], true)) {
            throw new \InvalidArgumentException('ISSQN withholding type must be 1, 2 or 3.');
        }

        $tribMun = $doc->createElement('tribMun');
        $tribMun->appendChild($doc->createElement('tribISSQN', (string) $dps->tributacaoIssqn));

        if ($dps->issqnPaisResultado !== '') {
            if ($dps->tributacaoIssqn !== 3) {
                throw new \InvalidArgumentException('ISSQN result country may only be informed for service export.');
            }

            $country = strtoupper($dps->issqnPaisResultado);

            if (preg_match('/^[A-Z]{2}$/', $country) !== 1 || $country === 'BR') {
                throw new \InvalidArgumentException('ISSQN export result country must be a foreign ISO alpha-2 code.');
            }

            $tribMun->appendChild($doc->createElement('cPaisResult', $country));
        }

        if ($dps->issqnTipoImunidade !== null) {
            if ($dps->tributacaoIssqn !== 2) {
                throw new \InvalidArgumentException('ISSQN immunity type may only be informed for immunity taxation.');
            }

            if (!in_array($dps->issqnTipoImunidade, [1, 2, 3, 4, 5], true)) {
                throw new \InvalidArgumentException('ISSQN immunity type must be between 1 and 5 for national issuance.');
            }

            $tribMun->appendChild($doc->createElement('tpImunidade', (string) $dps->issqnTipoImunidade));
        } elseif ($dps->tributacaoIssqn === 2) {
            throw new \InvalidArgumentException('ISSQN immunity type is required when tribISSQN is immunity.');
        }

        $hasSuspension = $dps->issqnTipoSuspensao !== null || $dps->issqnNumeroProcessoSuspensao !== '';

        if ($hasSuspension) {
            if ($dps->tributacaoIssqn !== 1) {
                throw new \InvalidArgumentException('ISSQN suspended enforceability is only allowed for taxable operations.');
            }

            if (!in_array($dps->issqnTipoSuspensao, [1, 2], true)) {
                throw new \InvalidArgumentException('ISSQN suspension type must be 1 or 2.');
            }

            if (preg_match('/^\d{30}$/', $dps->issqnNumeroProcessoSuspensao) !== 1) {
                throw new \InvalidArgumentException('ISSQN suspension proceeding number must contain exactly 30 digits.');
            }

            $exigSusp = $doc->createElement('exigSusp');
            $exigSusp->appendChild($doc->createElement('tpSusp', (string) $dps->issqnTipoSuspensao));
            $exigSusp->appendChild($doc->createElement('nProcesso', $dps->issqnNumeroProcessoSuspensao));
            $tribMun->appendChild($exigSusp);
        }

        if ($dps->beneficioMunicipal !== null) {
            $tribMun->appendChild($this->buildMunicipalBenefit($doc, $dps));
        }

        $tribMun->appendChild($doc->createElement('tpRetISSQN', (string) $dps->tipoRetencaoIss));

        if ($dps->opcaoSimplesNacional !== 1) {
            $tribMun->appendChild($doc->createElement('pAliq', $dps->aliquota));
        }

        return $tribMun;
    }

    private function buildDeductionReduction(\DOMDocument $doc, DpsData $dps): \DOMElement
    {
        $deduction = $dps->deducaoReducao;

        if ($deduction === null) {
            throw new \LogicException('Deduction/reduction data is required.');
        }

        $hasPercentage = $deduction->percentual !== '';
        $hasValue = $deduction->valor !== '';
        $hasDocuments = $deduction->documentos !== [];
        $modeCount = (int) $hasPercentage + (int) $hasValue + (int) $hasDocuments;

        if ($modeCount !== 1) {
            throw new \InvalidArgumentException(
                'Deduction/reduction must provide exactly one of percentual (pDR), valor (vDR) or documentos.',
            );
        }

        $vDedRed = $doc->createElement('vDedRed');

        if ($hasPercentage) {
            $this->assertDecimal($deduction->percentual, 3, 'Deduction/reduction percentage');
            $vDedRed->appendChild($doc->createElement('pDR', $deduction->percentual));

            return $vDedRed;
        }

        if ($hasValue) {
            $this->assertDecimal($deduction->valor, 15, 'Deduction/reduction value');
            $vDedRed->appendChild($doc->createElement('vDR', $deduction->valor));

            return $vDedRed;
        }

        if (count($deduction->documentos) > 1000) {
            throw new \InvalidArgumentException('Deduction/reduction supports at most 1000 documents.');
        }

        $documents = $doc->createElement('documentos');

        foreach ($deduction->documentos as $document) {
            $documents->appendChild($this->buildDeductionDocument($doc, $document));
        }

        $vDedRed->appendChild($documents);

        return $vDedRed;
    }

    private function buildDeductionDocument(
        \DOMDocument $doc,
        \LibreCodeCoop\NfsePHP\Dto\DeductionDocumentData $document,
    ): \DOMElement {
        if (!in_array($document->type, [1, 2, 3, 4, 5, 6, 7, 8, 9, 99], true)) {
            throw new \InvalidArgumentException('Deduction document type must be 1-9 or 99.');
        }

        if ($document->type === 99 && trim($document->otherDescription) === '') {
            throw new \InvalidArgumentException('Other deduction type (99) requires a description.');
        }

        if ($document->type !== 99 && $document->otherDescription !== '') {
            throw new \InvalidArgumentException('Other deduction description is only allowed for type 99.');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $document->issuedAt);
        if ($date === false || $date->format('Y-m-d') !== $document->issuedAt) {
            throw new \InvalidArgumentException('Deduction document issue date must use YYYY-MM-DD.');
        }

        $this->assertDecimal($document->deductibleValue, 15, 'Deductible/reducible document value');
        $this->assertDecimal($document->deductionValue, 15, 'Applied deduction/reduction value');

        if ((float) $document->deductionValue > (float) $document->deductibleValue) {
            throw new \InvalidArgumentException(
                'Applied deduction/reduction value cannot exceed the deductible/reducible document value.',
            );
        }

        $element = $doc->createElement('docDedRed');
        $element->appendChild($this->buildDeductionDocumentReference($doc, $document->reference));
        $element->appendChild($doc->createElement('tpDedRed', (string) $document->type));

        if ($document->otherDescription !== '') {
            $element->appendChild(
                $doc->createElement('xDescOutDed', htmlspecialchars($document->otherDescription, ENT_XML1)),
            );
        }

        $element->appendChild($doc->createElement('dtEmiDoc', $document->issuedAt));
        $element->appendChild($doc->createElement('vDedutivelRedutivel', $document->deductibleValue));
        $element->appendChild($doc->createElement('vDeducaoReducao', $document->deductionValue));

        if ($document->supplier !== null) {
            $element->appendChild($this->buildDeductionSupplier($doc, $document->supplier));
        }

        return $element;
    }

    private function buildDeductionDocumentReference(
        \DOMDocument $doc,
        \LibreCodeCoop\NfsePHP\Dto\DeductionDocumentReferenceData $reference,
    ): \DOMElement {
        return match ($reference->type) {
            'nfse' => $doc->createElement('chNFSe', (string) ($reference->values['access_key'] ?? '')),
            'nfe' => $doc->createElement('chNFe', (string) ($reference->values['access_key'] ?? '')),
            'legacy_nfse' => $this->buildLegacyNfseReference($doc, $reference->values),
            'legacy_nf_nfs' => $this->buildLegacyNfNfsReference($doc, $reference->values),
            'fiscal_document' => $doc->createElement('nDocFisc', (string) ($reference->values['numero'] ?? '')),
            'document' => $doc->createElement('nDoc', (string) ($reference->values['numero'] ?? '')),
            default => throw new \InvalidArgumentException('Unsupported deduction document reference type.'),
        };
    }

    /**
     * @param array<string, mixed> $values
     */
    private function buildLegacyNfseReference(\DOMDocument $doc, array $values): \DOMElement
    {
        $group = $doc->createElement('NFSeMun');
        $group->appendChild($doc->createElement('cMunNFSeMun', (string) ($values['municipio_ibge'] ?? '')));
        $group->appendChild($doc->createElement('nNFSeMun', (string) ($values['numero'] ?? '')));
        $group->appendChild($doc->createElement('cVerifNFSeMun', (string) ($values['codigo_verificacao'] ?? '')));

        return $group;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function buildLegacyNfNfsReference(\DOMDocument $doc, array $values): \DOMElement
    {
        $group = $doc->createElement('NFNFS');
        $group->appendChild($doc->createElement('nNFS', (string) ($values['numero'] ?? '')));
        $group->appendChild($doc->createElement('modNFS', (string) ($values['modelo'] ?? '')));
        $group->appendChild($doc->createElement('serieNFS', (string) ($values['serie'] ?? '')));

        return $group;
    }

    private function buildDeductionSupplier(
        \DOMDocument $doc,
        \LibreCodeCoop\NfsePHP\Dto\DeductionSupplierData $supplier,
    ): \DOMElement {
        if (trim($supplier->name) === '') {
            throw new \InvalidArgumentException('Deduction supplier name is required.');
        }

        $element = $doc->createElement('fornec');

        switch ($supplier->identityType) {
            case 'cnpj':
                if (preg_match('/^[A-Z0-9]{12}\d{2}$/', strtoupper($supplier->identity)) !== 1) {
                    throw new \InvalidArgumentException('Deduction supplier CNPJ must contain 14 valid-format characters.');
                }
                $element->appendChild($doc->createElement('CNPJ', strtoupper($supplier->identity)));
                break;
            case 'cpf':
                if (preg_match('/^\d{11}$/', $supplier->identity) !== 1) {
                    throw new \InvalidArgumentException('Deduction supplier CPF must contain 11 digits.');
                }
                $element->appendChild($doc->createElement('CPF', $supplier->identity));
                break;
            case 'nif':
                if ($supplier->identity === '') {
                    throw new \InvalidArgumentException('Deduction supplier NIF cannot be empty.');
                }
                $element->appendChild($doc->createElement('NIF', $supplier->identity));
                break;
            case 'no_nif':
                if (!in_array($supplier->identity, ['0', '1', '2'], true)) {
                    throw new \InvalidArgumentException('Deduction supplier cNaoNIF must be 0, 1 or 2.');
                }
                $element->appendChild($doc->createElement('cNaoNIF', $supplier->identity));
                break;
            default:
                throw new \InvalidArgumentException('Unsupported deduction supplier identity type.');
        }

        if ($supplier->caepf !== '') {
            $element->appendChild($doc->createElement('CAEPF', $supplier->caepf));
        }

        if ($supplier->municipalRegistration !== '') {
            $element->appendChild($doc->createElement('IM', $supplier->municipalRegistration));
        }

        $element->appendChild($doc->createElement('xNome', htmlspecialchars($supplier->name, ENT_XML1)));

        if ($supplier->phone !== '') {
            $element->appendChild($doc->createElement('fone', $supplier->phone));
        }

        if ($supplier->email !== '') {
            $element->appendChild($doc->createElement('email', htmlspecialchars($supplier->email, ENT_XML1)));
        }

        return $element;
    }

    private function buildMunicipalBenefit(\DOMDocument $doc, DpsData $dps): \DOMElement
    {
        $benefit = $dps->beneficioMunicipal;

        if ($benefit === null) {
            throw new \LogicException('Municipal benefit data is required.');
        }

        if (preg_match('/^\d{14}$/', $benefit->identificador) !== 1) {
            throw new \InvalidArgumentException('Municipal benefit identifier must contain exactly 14 digits.');
        }

        $hasValue = $benefit->valorReducaoBaseCalculo !== '';
        $hasPercentage = $benefit->percentualReducaoBaseCalculo !== '';

        if ($hasValue && $hasPercentage) {
            throw new \InvalidArgumentException(
                'Municipal benefit may provide either value or percentage base reduction, not both.',
            );
        }

        $bm = $doc->createElement('BM');
        $bm->appendChild($doc->createElement('nBM', $benefit->identificador));

        if ($hasValue) {
            $this->assertDecimal($benefit->valorReducaoBaseCalculo, 15, 'Municipal benefit value reduction');
            $bm->appendChild($doc->createElement('vRedBCBM', $benefit->valorReducaoBaseCalculo));
        }

        if ($hasPercentage) {
            $this->assertDecimal($benefit->percentualReducaoBaseCalculo, 3, 'Municipal benefit percentage reduction');
            $bm->appendChild($doc->createElement('pRedBCBM', $benefit->percentualReducaoBaseCalculo));
        }

        return $bm;
    }

    private function assertDecimal(string $value, int $integerDigits, string $label): void
    {
        $pattern = '/^(?:0|0\.\d{2}|[1-9]\d{0,' . ($integerDigits - 1) . '}(?:\.\d{2})?)$/';

        if (preg_match($pattern, $value) !== 1) {
            throw new \InvalidArgumentException(
                $label . ' must follow the official decimal format with up to '
                . $integerDigits . ' integer digits and optional two decimal places.',
            );
        }
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

        $municipalTaxCode = $dps->codigoTributacaoMunicipal ?? $dps->itemListaServico;

        if ($municipalTaxCode !== '') {
            $cServ->appendChild($doc->createElement('cTribMun', $municipalTaxCode));
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
