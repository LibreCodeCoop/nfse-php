<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Xml;

use LibreCodeCoop\NfsePHP\Dto\Nt009BaseAdjustmentData;

/**
 * Draft-only NT009 valores/vAjusteBC: monetary, percentage or document-based
 * alternatives. These structures do not prove fiscal approval by SEFIN.
 */
final class Nt009BaseAdjustmentPreviewGroup
{
    public function build(\DOMDocument $doc, Nt009BaseAdjustmentData $input): \DOMElement
    {
        $modes = (int) ($input->percentualIssqn !== null)
            + (int) ($input->valorIssqn !== null)
            + (int) ($input->documentos !== []);
        if ($modes !== 1) {
            throw new \InvalidArgumentException(
                'NT009 vAjusteBC requires exactly one percentage, value or documents mode'
            );
        }
        if (count($input->documentos) > 1000) {
            throw new \InvalidArgumentException('NT009 vAjusteBC supports at most 1000 adjustment documents');
        }

        $group = $doc->createElement('vAjusteBC');
        if ($input->percentualIssqn !== null) {
            $this->assertDecimal($input->percentualIssqn, 3, 'pAjusteBCISSQN');
            $this->append($doc, $group, 'pAjusteBCISSQN', $input->percentualIssqn);
        }
        if ($input->valorIssqn !== null) {
            $this->assertDecimal($input->valorIssqn, 15, 'vAjusteBCISSQN');
            $this->append($doc, $group, 'vAjusteBCISSQN', $input->valorIssqn);
        }
        if ($input->documentos !== []) {
            $documents = $doc->createElement('documentos');
            foreach ($input->documentos as $entry) {
                $documents->appendChild(
                    (new Nt009AdjustmentDocumentPreviewGroup())->build($doc, $entry)
                );
            }
            $group->appendChild($documents);
        }
        if ($input->valorIbsCbsComExterior !== null) {
            $this->assertDecimal($input->valorIbsCbsComExterior, 15, 'vAjusteBCIBSCBSComExt');
            $this->append($doc, $group, 'vAjusteBCIBSCBSComExt', $input->valorIbsCbsComExterior);
        }

        return $group;
    }

    private function assertDecimal(string $value, int $integralDigits, string $field): void
    {
        if (preg_match('/^[0-9]{1,' . $integralDigits . '}\\.[0-9]{2}$/D', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid NT009 decimal field ' . $field);
        }
    }

    private function append(\DOMDocument $doc, \DOMElement $group, string $name, string $value): void
    {
        $element = $doc->createElement($name);
        $element->appendChild($doc->createTextNode($value));
        $group->appendChild($element);
    }
}
