<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Danfse\Config;

/**
 * Optional municipal issuer branding shown on the DANFSe header.
 *
 * The logo accepts either a file path or a ready data URI; a data URI takes
 * precedence when both are supplied.
 */
final readonly class MunicipalityBranding
{
    public ?string $logoDataUri;

    public function __construct(
        public string $name,
        public string $department = '',
        public string $email = '',
        ?string $logoDataUri = null,
        ?string $logoPath = null,
    ) {
        $this->logoDataUri = LogoLoader::resolve($logoDataUri, $logoPath);
    }
}
