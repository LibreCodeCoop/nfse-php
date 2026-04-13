<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Config;

/**
 * Immutable configuration for the NFS-e environment (sandbox vs. production).
 *
 * When no custom base URL is supplied the appropriate official endpoint is
 * selected automatically from the sandboxMode flag:
 *
 *   - Production:  https://sefin.nfse.gov.br/SefinNacional
 *   - Sandbox:     https://sefin.producaorestrita.nfse.gov.br/SefinNacional
 */
final readonly class EnvironmentConfig
{
    private const BASE_URL_PROD    = 'https://sefin.nfse.gov.br/SefinNacional';
    private const BASE_URL_SANDBOX = 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional';
    private const DANFSE_BASE_URL_PROD    = 'https://adn.nfse.gov.br/danfse';
    private const DANFSE_BASE_URL_SANDBOX = 'https://adn.producaorestrita.nfse.gov.br/danfse';

    public string $baseUrl;
    public string $danfseBaseUrl;

    public function __construct(
        public bool $sandboxMode = false,
        ?string $baseUrl = null,
        ?string $danfseBaseUrl = null,
    ) {
        $this->baseUrl = $baseUrl ?? ($sandboxMode
            ? self::BASE_URL_SANDBOX
            : self::BASE_URL_PROD);
        $this->danfseBaseUrl = $danfseBaseUrl ?? ($sandboxMode
            ? self::DANFSE_BASE_URL_SANDBOX
            : self::DANFSE_BASE_URL_PROD);
    }
}
