<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Xml;

use LibreCodeCoop\NfsePHP\Dto\Nt009MovableAssetData;

/**
 * Draft-only rental inventory, Annex VI rows 404-407.
 */
final class Nt009MovableAssetsPreviewGroup
{
    /**
     * @param list<Nt009MovableAssetData> $assets
     * @return list<\DOMElement>
     */
    public function build(\DOMDocument $doc, array $assets, string $nationalTaxCode): array
    {
        if ($assets === []) {
            return [];
        }
        if (count($assets) > 1000) {
            throw new \InvalidArgumentException('NT009 bensMoveis supports at most 1000 records');
        }
        if ($nationalTaxCode !== '990401') {
            throw new \InvalidArgumentException('NT009 bensMoveis is exclusive to cTribNac 99.04.01');
        }

        $result = [];
        foreach ($assets as $asset) {
            if (!$asset instanceof Nt009MovableAssetData
                || preg_match('/^[0-9]{8}$/D', $asset->ncm) !== 1
                || mb_strlen($asset->descricao) < 1 || mb_strlen($asset->descricao) > 150
                || preg_match('/\p{Cc}/u', $asset->descricao) !== 0
                || $asset->quantidade < 1 || $asset->quantidade > 999) {
                throw new \InvalidArgumentException('Invalid NT009 bensMoveis NCM, description or quantity');
            }
            $record = $doc->createElement('bensMoveis');
            foreach ([
                'cNCMBemMovel' => $asset->ncm,
                'xNCMBemMovel' => $asset->descricao,
                'qtdNCMBemMovel' => (string) $asset->quantidade,
            ] as $tag => $value) {
                $element = $doc->createElement($tag);
                $element->appendChild($doc->createTextNode($value));
                $record->appendChild($element);
            }
            $result[] = $record;
        }

        return $result;
    }
}
