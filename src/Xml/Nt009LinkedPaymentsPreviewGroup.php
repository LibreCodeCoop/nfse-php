<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Xml;

use LibreCodeCoop\NfsePHP\Dto\Nt009LinkedPaymentData;

/**
 * Draft-only IBSCBS/gPgtoVinc, Annex VI rows 443-449.
 */
final class Nt009LinkedPaymentsPreviewGroup
{
    /**
     * @param list<Nt009LinkedPaymentData> $payments
     */
    public function build(\DOMDocument $doc, array $payments): \DOMElement
    {
        if ($payments === [] || count($payments) > 99) {
            throw new \InvalidArgumentException('NT009 gPgtoVinc requires 1-99 payments');
        }

        $usedNumbers = [];
        $usedTransactions = [];
        $group = $doc->createElement('gPgtoVinc');
        foreach ($payments as $payment) {
            if (!$payment instanceof Nt009LinkedPaymentData
                || preg_match('/^[0-9]{1,3}$/D', $payment->numeroPagamento) !== 1
                || (int) $payment->numeroPagamento < 1
                || mb_strlen($payment->identificadorTransacao) < 2
                || mb_strlen($payment->identificadorTransacao) > 35
                || preg_match('/\p{Cc}/u', $payment->identificadorTransacao) !== 0
                || !in_array($payment->tipoMeioPagamento, ['15', '17', '18', '20', '23', '24'], true)
                || preg_match('/^[A-Z0-9]{12}[0-9]{2}$/D', $payment->cnpjRecebedor) !== 1
                || preg_match('/^[A-Z0-9]{8}$/D', $payment->cnpjBasePsp) !== 1) {
                throw new \InvalidArgumentException('Invalid NT009 gPgtoVinc payment fields');
            }
            $number = (int) $payment->numeroPagamento;
            if (isset($usedNumbers[$number])
                || isset($usedTransactions[$payment->identificadorTransacao])) {
                throw new \InvalidArgumentException('NT009 payment numbers and transaction IDs must be unique');
            }
            $usedNumbers[$number] = true;
            $usedTransactions[$payment->identificadorTransacao] = true;
            $record = $doc->createElement('pgto');
            $this->append($doc, $record, 'nPag', $payment->numeroPagamento);
            $this->append($doc, $record, 'idTransacao', $payment->identificadorTransacao);
            $this->append($doc, $record, 'tpMeioPgto', $payment->tipoMeioPagamento);
            $this->append($doc, $record, 'CNPJReceb', $payment->cnpjRecebedor);
            $this->append($doc, $record, 'CNPJBasePSP', $payment->cnpjBasePsp);
            $group->appendChild($record);
        }

        return $group;
    }

    private function append(\DOMDocument $doc, \DOMElement $parent, string $name, string $value): void
    {
        $node = $doc->createElement($name);
        $node->appendChild($doc->createTextNode($value));
        $parent->appendChild($node);
    }
}
