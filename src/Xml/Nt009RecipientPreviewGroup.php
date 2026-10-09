<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Xml;

use LibreCodeCoop\NfsePHP\Dto\Nt009RecipientAddressData;
use LibreCodeCoop\NfsePHP\Dto\Nt009RecipientData;

/**
 * Explicit separate destination identity, Annex VI rows 180-200.
 */
final class Nt009RecipientPreviewGroup
{
    public function build(\DOMDocument $doc, Nt009RecipientData $input): \DOMElement
    {
        if (!$this->text($input->nome, 150)) {
            throw new \InvalidArgumentException('NT009 dest/xNome must have 1-150 characters');
        }
        $identities = (int) ($input->cnpj !== null) + (int) ($input->cpf !== null)
            + (int) ($input->nif !== null) + (int) ($input->codigoNaoNif !== null);
        if ($identities !== 1
            || ($input->cnpj !== null && preg_match('/^[A-Z0-9]{12}[0-9]{2}$/D', $input->cnpj) !== 1)
            || ($input->cpf !== null && preg_match('/^[0-9]{11}$/D', $input->cpf) !== 1)
            || ($input->nif !== null && !$this->text($input->nif, 40))
            || ($input->codigoNaoNif !== null && !in_array($input->codigoNaoNif, [1, 2], true))) {
            throw new \InvalidArgumentException('NT009 dest requires exactly one valid tax identity');
        }

        $dest = $doc->createElement('dest');
        foreach (['CNPJ' => $input->cnpj, 'CPF' => $input->cpf, 'NIF' => $input->nif] as $tag => $value) {
            if ($value !== null) {
                $this->add($doc, $dest, $tag, $value);
            }
        }
        if ($input->codigoNaoNif !== null) {
            $this->add($doc, $dest, 'cNaoNIF', (string) $input->codigoNaoNif);
        }
        $this->add($doc, $dest, 'xNome', $input->nome);
        if ($input->endereco !== null) {
            $dest->appendChild($this->buildAddress($doc, $input->endereco));
        }
        if ($input->telefone !== null) {
            if (preg_match('/^[0-9]{6,20}$/D', $input->telefone) !== 1) {
                throw new \InvalidArgumentException('Invalid NT009 dest/fone');
            }
            $this->add($doc, $dest, 'fone', $input->telefone);
        }
        if ($input->email !== null) {
            if (!$this->text($input->email, 80)) {
                throw new \InvalidArgumentException('Invalid NT009 dest/email');
            }
            $this->add($doc, $dest, 'email', $input->email);
        }

        return $dest;
    }

    private function buildAddress(
        \DOMDocument $doc,
        Nt009RecipientAddressData $input,
    ): \DOMElement {
        if (!$this->text($input->logradouro, 255)
            || !$this->text($input->numero, 60) || !$this->text($input->bairro, 60)
            || ($input->complemento !== null && !$this->text($input->complemento, 156))) {
            throw new \InvalidArgumentException('Invalid NT009 recipient address');
        }
        $national = $input->municipioIbge !== null || $input->cep !== null;
        $foreign = $input->paisIso2 !== null || $input->codigoPostalExterior !== null
            || $input->cidadeExterior !== null || $input->estadoExterior !== null;
        if ($national === $foreign) {
            throw new \InvalidArgumentException('NT009 recipient address must be national or foreign, not both');
        }
        $end = $doc->createElement('end');
        if ($national) {
            if (preg_match('/^[0-9]{7}$/D', (string) $input->municipioIbge) !== 1
                || preg_match('/^[0-9]{8}$/D', (string) $input->cep) !== 1) {
                throw new \InvalidArgumentException('NT009 dest/endNac requires cMun and CEP');
            }
            $group = $doc->createElement('endNac');
            $this->add($doc, $group, 'cMun', (string) $input->municipioIbge);
            $this->add($doc, $group, 'CEP', (string) $input->cep);
        } else {
            if (preg_match('/^[A-Z]{2}$/D', (string) $input->paisIso2) !== 1
                || !$this->text((string) $input->codigoPostalExterior, 11)
                || !$this->text((string) $input->cidadeExterior, 60)
                || !$this->text((string) $input->estadoExterior, 60)) {
                throw new \InvalidArgumentException('Incomplete NT009 foreign recipient address');
            }
            $group = $doc->createElement('endExt');
            $this->add($doc, $group, 'cPais', (string) $input->paisIso2);
            $this->add($doc, $group, 'cEndPost', (string) $input->codigoPostalExterior);
            $this->add($doc, $group, 'xCidade', (string) $input->cidadeExterior);
            $this->add($doc, $group, 'xEstProvReg', (string) $input->estadoExterior);
        }
        $end->appendChild($group);
        $this->add($doc, $end, 'xLgr', $input->logradouro);
        $this->add($doc, $end, 'nro', $input->numero);
        if ($input->complemento !== null) {
            $this->add($doc, $end, 'xCpl', $input->complemento);
        }
        $this->add($doc, $end, 'xBairro', $input->bairro);

        return $end;
    }

    private function add(\DOMDocument $doc, \DOMElement $parent, string $tag, string $text): void
    {
        $node = $doc->createElement($tag);
        $node->appendChild($doc->createTextNode($text));
        $parent->appendChild($node);
    }

    private function text(string $value, int $maxLength): bool
    {
        return mb_strlen($value) >= 1 && mb_strlen($value) <= $maxLength
            && preg_match('/\p{Cc}/u', $value) === 0;
    }
}
