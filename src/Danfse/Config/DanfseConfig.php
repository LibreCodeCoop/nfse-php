<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Danfse\Config;

/**
 * Immutable presentation options for the DANFSe.
 *
 * Uses the bundled official horizontal NFS-e logo by default, without network
 * access during PDF generation. A caller may override it with a data URI or
 * local image file (data URI takes precedence). The mark is separately licensed
 * from this PHP source; see the attribution next to the bundled asset.
 */
final readonly class DanfseConfig
{
    public ?string $logoDataUri;

    public function __construct(
        ?string $logoDataUri = null,
        ?string $logoPath = null,
        public ?MunicipalityBranding $municipality = null,
    ) {
        $this->logoDataUri = ($logoDataUri !== null || $logoPath !== null)
            ? LogoLoader::resolve($logoDataUri, $logoPath)
            : self::defaultOfficialLogo();
    }

    private static function defaultOfficialLogo(): ?string
    {
        // Text-encoded PNG is shipped as a non-executable local resource. The
        // NFS-e identity artwork belongs to the official Brazilian project.
        $asset = __DIR__ . '/../Assets/nfse-horizontal.png.base64';
        if (!is_readable($asset)) {
            return null;
        }

        $encoded = trim((string) file_get_contents($asset));
        if ($encoded === '' || base64_decode($encoded, true) === false) {
            return null;
        }

        return 'data:image/png;base64,' . $encoded;
    }
}
