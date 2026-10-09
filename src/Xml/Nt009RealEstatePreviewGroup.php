<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Xml;

use LibreCodeCoop\NfsePHP\Dto\Nt009RealEstateData;

/** Draft-only rental real estate groups from Annex VI rows 383-403. */
final class Nt009RealEstatePreviewGroup
{
    public function build(
        \DOMDocument $doc,
        Nt009RealEstateData $input,
        string $nationalTaxCode,
    ): \DOMElement {
        if (preg_match('/^[0-9]{7}$/D', $input->municipioIbge) !== 1
            || count($input->unidades) > 99) {
            throw new \InvalidArgumentException('Invalid NT009 imovel municipality or unit cardinality');
        }

        $group = $doc->createElement('imovel');
        $this->add($doc, $group, 'cMun', $input->municipioIbge);
        if ($input->locacao !== null) {
            if (!in_array($nationalTaxCode, ['990301', '990302', '990303', '990304', '990305'], true)) {
                throw new \InvalidArgumentException('NT009 gLocacao requires cTribNac 99.03.01-99.03.05');
            }
            $lease = $input->locacao;
            $this->decimal($lease->percentualCopropriedade, 2, 'pCopropriedade');
            $this->decimal($lease->valorTotalOperacao, 15, 'vTotOper');
            $element = $doc->createElement('gLocacao');
            $this->add($doc, $element, 'pCopropriedade', $lease->percentualCopropriedade);
            $this->add($doc, $element, 'vTotOper', $lease->valorTotalOperacao);
            foreach ([
                'vDescIncondTot' => $lease->descontoIncondicional,
                'vDescCondTot' => $lease->descontoCondicional,
            ] as $name => $value) {
                if ($value !== null) {
                    $this->decimal($value, 15, $name);
                    $this->add($doc, $element, $name, $value);
                }
            }
            if ($lease->dataVencimentoOriginal !== null) {
                $this->date($lease->dataVencimentoOriginal, 'dVencOrig');
                $this->add($doc, $element, 'dVencOrig', $lease->dataVencimentoOriginal);
            }
            $group->appendChild($element);
        }

        foreach ($input->unidades as $unit) {
            if (preg_match('/^[A-Z0-9]{8}$/D', $unit->cib) !== 1
                || preg_match('/^[0-9]{8}$/D', $unit->cep) !== 1
                || !$this->text($unit->logradouro, 255)
                || !$this->text($unit->numero, 60)
                || ($unit->bairro !== null && !$this->text($unit->bairro, 60))
                || ($unit->complemento !== null && !$this->text($unit->complemento, 156))
                || ($unit->inscricaoFiscal !== null && !$this->text($unit->inscricaoFiscal, 30))
                || count($unit->ajustes) > 10) {
                throw new \InvalidArgumentException('Invalid NT009 gUnidImob identification, address or adjustments');
            }

            $element = $doc->createElement('gUnidImob');
            if ($unit->inscricaoFiscal !== null) {
                $this->add($doc, $element, 'inscImobFisc', $unit->inscricaoFiscal);
            }
            $this->add($doc, $element, 'cCIB', $unit->cib);
            $address = $doc->createElement('end');
            $this->add($doc, $address, 'CEP', $unit->cep);
            $this->add($doc, $address, 'xLgr', $unit->logradouro);
            $this->add($doc, $address, 'nro', $unit->numero);
            if ($unit->complemento !== null) {
                $this->add($doc, $address, 'xCpl', $unit->complemento);
            }
            if ($unit->bairro !== null) {
                $this->add($doc, $address, 'xBairro', $unit->bairro);
            }
            $element->appendChild($address);
            foreach ($unit->ajustes as $adjustment) {
                if (preg_match('/^[0-9]{2}$/D', $adjustment->tipo) !== 1
                    || ($adjustment->descricaoTipo !== null && !$this->text($adjustment->descricaoTipo, 150))) {
                    throw new \InvalidArgumentException('Invalid NT009 property adjustment type');
                }
                $this->decimal($adjustment->valor, 15, 'vAjusteBCLocImoveis');
                $one = $doc->createElement('gAjusteBCLocImoveis');
                $this->add($doc, $one, 'tpAjusteBCLocImoveis', $adjustment->tipo);
                if ($adjustment->descricaoTipo !== null) {
                    $this->add($doc, $one, 'xTpAjusteBCLocImoveis', $adjustment->descricaoTipo);
                }
                $this->add($doc, $one, 'vAjusteBCLocImoveis', $adjustment->valor);
                $element->appendChild($one);
            }
            $group->appendChild($element);
        }

        return $group;
    }

    private function decimal(string $value, int $digits, string $name): void
    {
        if (preg_match('/^[0-9]{1,' . $digits . '}\\.[0-9]{2}$/D', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid NT009 decimal ' . $name);
        }
    }

    private function date(string $value, string $name): void
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Invalid NT009 date ' . $name);
        }
    }

    private function text(string $value, int $max): bool
    {
        return mb_strlen($value) >= 1 && mb_strlen($value) <= $max
            && preg_match('/\p{Cc}/u', $value) === 0;
    }

    private function add(\DOMDocument $doc, \DOMElement $parent, string $tag, string $value): void
    {
        $element = $doc->createElement($tag);
        $element->appendChild($doc->createTextNode($value));
        $parent->appendChild($element);
    }
}
