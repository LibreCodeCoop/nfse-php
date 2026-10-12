<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Danfse;

/**
 * Converts an NFS-e Nacional XML into a clean associative array.
 *
 * Children in the NFS-e namespace are traversed; digital-signature nodes
 * (in the xmldsig namespace) are skipped. XML attributes (such as Id and
 * versao) become regular array keys.
 */
final class XmlToArray
{
    private const NFSE_NS = 'http://www.sped.fazenda.gov.br/nfse';

    /**
     * @return array<string, mixed>
     */
    public function convert(string $xml): array
    {
        if (preg_match('/<!DOCTYPE/i', $xml) === 1) {
            throw new \InvalidArgumentException('DOCTYPE is not allowed in NFS-e XML.');
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $root = new \SimpleXMLElement($xml, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $result = $this->nodeToArray($root);

        return is_array($result) ? $result : [];
    }

    /**
     * @return array<string, mixed>|string
     */
    private function nodeToArray(\SimpleXMLElement $node): array|string
    {
        $result = [];

        foreach ($node->attributes() ?? [] as $key => $value) {
            $result[$key] = (string) $value;
        }

        $nsChildren = $node->children(self::NFSE_NS);
        if ($nsChildren !== null && $nsChildren->count() > 0) {
            foreach ($nsChildren as $name => $child) {
                $this->appendNamedChild($result, (string) $name, $this->nodeToArray($child));
            }
        } else {
            // Fallback for XMLs that omit the namespace on children.
            foreach ($node->children() ?? [] as $name => $child) {
                $this->appendNamedChild($result, (string) $name, $this->nodeToArray($child));
            }
        }

        // Leaf element without attributes or children: return its text directly.
        if ($result === []) {
            return trim((string) $node);
        }

        return $result;
    }

    /**
     * Repeating an XML element is not a replacement: preserve each distinct
     * child, but leave single-occurrence fields backward compatible.
     *
     * @param array<string,mixed> $out
     * @param array<string,mixed>|string $value
     */
    private function appendNamedChild(array &$out, string $name, array|string $value): void
    {
        if (!array_key_exists($name, $out)) {
            $out[$name] = $value;

            return;
        }

        if (!is_array($out[$name]) || !array_is_list($out[$name])) {
            $out[$name] = [$out[$name]];
        }

        $out[$name][] = $value;
    }
}
