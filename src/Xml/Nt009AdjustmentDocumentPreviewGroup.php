<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Xml;

use LibreCodeCoop\NfsePHP\Dto\Nt009AdjustmentDocumentData;
use LibreCodeCoop\NfsePHP\Dto\Nt009NationalInvoiceReference;
use LibreCodeCoop\NfsePHP\Dto\Nt009OtherDocumentReference;
use LibreCodeCoop\NfsePHP\Dto\Nt009OtherFiscalReference;

/**
 * One source-backed docAjusteBC draft from NT009 Annex VI, rows 297-335.
 *
 * Tax-specific adjustment codes and repercussions are not inferred.
 */
final class Nt009AdjustmentDocumentPreviewGroup
{
    public function build(\DOMDocument $doc, Nt009AdjustmentDocumentData $data): \DOMElement
    {
        if (preg_match('/^[0-9]{1,3}$/D', $data->tipo) !== 1
            || ($data->descricaoTipo !== null && !$this->text($data->descricaoTipo, 150, 0))) {
            throw new \InvalidArgumentException('Invalid NT009 tpAjusteBC or xTpAjusteBC');
        }
        $this->decimal($data->valorTotalDocumento, 'vTotDoc');
        $this->decimal($data->valorAjustado, 'vAjusteAplic');
        foreach ([
            'dtEmiDoc' => $data->dataEmissao,
            'dtCompDoc' => $data->dataCompetencia,
        ] as $name => $date) {
            if ($date !== null) {
                $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
                    throw new \InvalidArgumentException('Invalid NT009 date field ' . $name);
                }
            }
        }

        $element = $doc->createElement('docAjusteBC');
        $this->add($doc, $element, 'tpAjusteBC', $data->tipo);
        if ($data->descricaoTipo !== null) {
            $this->add($doc, $element, 'xTpAjusteBC', $data->descricaoTipo);
        }
        $this->add($doc, $element, 'vTotDoc', $data->valorTotalDocumento);
        $this->add($doc, $element, 'vAjusteAplic', $data->valorAjustado);
        if ($data->dataEmissao !== null) {
            $this->add($doc, $element, 'dtEmiDoc', $data->dataEmissao);
        }
        if ($data->dataCompetencia !== null) {
            $this->add($doc, $element, 'dtCompDoc', $data->dataCompetencia);
        }

        $reference = $data->referencia;
        if ($reference instanceof Nt009NationalInvoiceReference) {
            if (preg_match('/^[0-9]$/D', $reference->tipoChaveDfe) !== 1
                || !$this->text($reference->chaveDfe, 50)
                || ($reference->descricaoTipoChaveDfe !== null
                    && !$this->text($reference->descricaoTipoChaveDfe, 255))) {
                throw new \InvalidArgumentException('Invalid NT009 dFeNacional reference');
            }
            $group = $doc->createElement('dFeNacional');
            $this->add($doc, $group, 'tipoChaveDFe', $reference->tipoChaveDfe);
            if ($reference->descricaoTipoChaveDfe !== null) {
                $this->add($doc, $group, 'xTipoChaveDFe', $reference->descricaoTipoChaveDfe);
            }
            $this->add($doc, $group, 'chaveDFe', $reference->chaveDfe);
        } elseif ($reference instanceof Nt009OtherFiscalReference) {
            if (preg_match('/^[0-9]{7}$/D', $reference->municipioIbge) !== 1
                || !$this->text($reference->numeroDocumento, 255)
                || !$this->text($reference->descricaoDocumento, 255)) {
                throw new \InvalidArgumentException('Invalid NT009 docFiscalOutro reference');
            }
            $group = $doc->createElement('docFiscalOutro');
            $this->add($doc, $group, 'cMunDocFiscal', $reference->municipioIbge);
            $this->add($doc, $group, 'nDocFiscal', $reference->numeroDocumento);
            $this->add($doc, $group, 'xDocFiscal', $reference->descricaoDocumento);
        } else {
            // The DTO's discriminated union guarantees the final reference
            // is Nt009OtherDocumentReference, not an arbitrary XML payload.
            if (!$this->text($reference->numero, 255)
                || !$this->text($reference->descricao, 255)) {
                throw new \InvalidArgumentException('Invalid NT009 docOutro reference');
            }
            $group = $doc->createElement('docOutro');
            $this->add($doc, $group, 'nDoc', $reference->numero);
            $this->add($doc, $group, 'xDoc', $reference->descricao);
        }
        $element->appendChild($group);
        if ($data->fornecedor !== null) {
            $element->appendChild(
                (new Nt009RecipientPreviewGroup())->build($doc, $data->fornecedor, 'fornec')
            );
        }

        return $element;
    }

    private function decimal(string $value, string $field): void
    {
        if (preg_match('/^[0-9]{1,15}\\.[0-9]{2}$/D', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid NT009 decimal field ' . $field);
        }
    }

    private function text(string $value, int $max, int $min = 1): bool
    {
        return mb_strlen($value) >= $min && mb_strlen($value) <= $max
            && preg_match('/\p{Cc}/u', $value) === 0;
    }

    private function add(\DOMDocument $doc, \DOMElement $parent, string $name, string $value): void
    {
        $element = $doc->createElement($name);
        $element->appendChild($doc->createTextNode($value));
        $parent->appendChild($element);
    }
}
