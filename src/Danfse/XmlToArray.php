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
        $previous = libxml_use_internal_errors(true);

        try {
            $root = new \SimpleXMLElement($xml);
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
                $result[$name] = $this->nodeToArray($child);
            }
        } else {
            // Fallback for XMLs that omit the namespace on children.
            foreach ($node->children() ?? [] as $name => $child) {
                if (!isset($result[$name])) {
                    $result[$name] = $this->nodeToArray($child);
                }
            }
        }

        // Leaf element without attributes or children: return its text directly.
        if ($result === []) {
            return trim((string) $node);
        }

        return $result;
    }
}
