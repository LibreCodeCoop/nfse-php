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
 * local image file (data URI takes precedence). The PNG is separately licensed from this PHP source; see its .license file.
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
        // Keep the official artwork as a real, inspectable PNG in the package.
        // Dompdf receives its bytes as a data URI at runtime because remote
        // requests are intentionally disabled for document generation.
        $asset = __DIR__ . '/../Assets/nfse-horizontal.png';
        if (!is_file($asset) || !is_readable($asset)) {
            throw new \RuntimeException('Bundled official NFS-e PNG is missing: ' . $asset);
        }

        return LogoLoader::pathToDataUri($asset);
    }
}
