<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Danfse\Config;

/**
 * Immutable presentation options for the DANFSe.
 *
 * The provider logo is optional: pass a ready data URI via $logoDataUri, or a
 * file path via $logoPath (a data URI takes precedence). When neither is given
 * the header logo area stays empty.
 */
final readonly class DanfseConfig
{
    public ?string $logoDataUri;

    public function __construct(
        ?string $logoDataUri = null,
        ?string $logoPath = null,
        public ?MunicipalityBranding $municipality = null,
    ) {
        $this->logoDataUri = LogoLoader::resolve($logoDataUri, $logoPath);
    }
}
