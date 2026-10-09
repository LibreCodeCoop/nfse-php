<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Xml;

use LibreCodeCoop\NfsePHP\Dto\Nt009CondominiumData;

/** Draft-only condominium fields from NT009 Annex VI rows 408-421. */
final class Nt009CondominiumPreviewGroup
{
    public function build(
        \DOMDocument $doc,
        Nt009CondominiumData $input,
        string $taxCode,
        string $serviceValue,
    ): \DOMElement {
        if (!str_starts_with($taxCode, '9905')) {
            throw new \InvalidArgumentException('NT009 condominios requires cTribNac subitem 99.05');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $input->vencimentoOriginal);
        if ($date === false || $date->format('Y-m-d') !== $input->vencimentoOriginal) {
            throw new \InvalidArgumentException('Invalid NT009 condominios/dVencOrig date');
        }
        if ($input->cobrancas === [] || count($input->cobrancas) > 20 || count($input->descontos) > 10) {
            throw new \InvalidArgumentException('NT009 condominios requires 1-20 charges and at most 10 discounts');
        }
        $group = $doc->createElement('condominios');
        $this->add($doc, $group, 'dVencOrig', $input->vencimentoOriginal);
        $total = '0';
        foreach ($input->cobrancas as $charge) {
            if (!in_array($charge->tipo, ['01', '02', '03', '04', '05', '99'], true)
                || ($charge->tipo === '99') !== ($charge->descricaoTipo !== null)
                || ($charge->descricaoTipo !== null && !$this->text($charge->descricaoTipo, 150))
                || count($charge->detalhes) > 20) {
                throw new \InvalidArgumentException('Invalid NT009 gCobranca type or detail cardinality');
            }
            $total = $this->addCents($total, $this->cents($charge->valor, 'vCobranca'));
            $record = $doc->createElement('gCobranca');
            $this->add($doc, $record, 'tpCobranca', $charge->tipo);
            if ($charge->descricaoTipo !== null) {
                $this->add($doc, $record, 'xTpCobranca', $charge->descricaoTipo);
            }
            $this->add($doc, $record, 'vCobranca', $charge->valor);
            $detailedTotal = '0';
            foreach ($charge->detalhes as $detail) {
                if (preg_match('/^[0-9]{3}$/D', $detail->tipo) !== 1
                    || ($detail->tipo === '999') !== ($detail->descricaoTipo !== null)
                    || ($detail->descricaoTipo !== null && !$this->text($detail->descricaoTipo, 150))) {
                    throw new \InvalidArgumentException('Invalid NT009 gDetCobranca type');
                }
                $detailedTotal = $this->addCents($detailedTotal, $this->cents($detail->valor, 'vDetCobranca'));
                $one = $doc->createElement('gDetCobranca');
                $this->add($doc, $one, 'tpDetCobranca', $detail->tipo);
                if ($detail->descricaoTipo !== null) {
                    $this->add($doc, $one, 'xTpDetCobranca', $detail->descricaoTipo);
                }
                $this->add($doc, $one, 'vDetCobranca', $detail->valor);
                $record->appendChild($one);
            }
            if ($charge->detalhes !== [] && $detailedTotal !== $this->cents($charge->valor, 'vCobranca')) {
                throw new \InvalidArgumentException('NT009 detailed condominium charges must sum to vCobranca');
            }
            $group->appendChild($record);
        }
        if ($total !== $this->cents($serviceValue, 'vServ')) {
            throw new \InvalidArgumentException('NT009 condominium charges must sum to service value');
        }
        foreach ($input->descontos as $discount) {
            if (preg_match('/^[0-9]$/D', $discount->tipo) !== 1
                || !$this->text($discount->descricaoTipo, 150)) {
                throw new \InvalidArgumentException('Invalid NT009 condominium discount');
            }
            $this->cents($discount->valor, 'vDesconto');
            $element = $doc->createElement('gDesconto');
            $this->add($doc, $element, 'tpDesconto', $discount->tipo);
            $this->add($doc, $element, 'vDesconto', $discount->valor);
            $this->add($doc, $element, 'xTpDesconto', $discount->descricaoTipo);
            $group->appendChild($element);
        }

        return $group;
    }

    private function cents(string $number, string $field): string
    {
        if (preg_match('/^([0-9]{1,15})\\.([0-9]{2})$/D', $number, $matches) !== 1) {
            throw new \InvalidArgumentException('NT009 ' . $field . ' must be a decimal with two places');
        }

        return ltrim($matches[1] . $matches[2], '0') ?: '0';
    }

    /**
     * Decimal-as-string cent addition avoids integer overflow for 15-digit
     * amounts, and never converts fiscal values through floating point.
     */
    private function addCents(string $left, string $right): string
    {
        $carry = 0;
        $digits = '';
        $i = strlen($left) - 1;
        $j = strlen($right) - 1;
        while ($i >= 0 || $j >= 0 || $carry > 0) {
            $sum = $carry;
            if ($i >= 0) {
                $sum += (int) $left[$i--];
            }
            if ($j >= 0) {
                $sum += (int) $right[$j--];
            }
            $digits = (string) ($sum % 10) . $digits;
            $carry = intdiv($sum, 10);
        }

        return ltrim($digits, '0') ?: '0';
    }

    private function add(\DOMDocument $doc, \DOMElement $parent, string $tag, string $value): void
    {
        $child = $doc->createElement($tag);
        $child->appendChild($doc->createTextNode($value));
        $parent->appendChild($child);
    }

    private function text(string $value, int $max): bool
    {
        return mb_strlen($value) >= 1 && mb_strlen($value) <= $max
            && preg_match('/\p{Cc}/u', $value) === 0;
    }
}
