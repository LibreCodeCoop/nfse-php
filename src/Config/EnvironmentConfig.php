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
    public const SCHEMA_RELEASE_PRODUCTION_20260209 = '20260209';
    public const SCHEMA_RELEASE_RESTRICTED_20261020 = '20261020';

    private const BASE_URL_PROD    = 'https://sefin.nfse.gov.br/SefinNacional';
    private const BASE_URL_SANDBOX = 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional';

    public string $baseUrl;
    public string $dpsSchemaRelease;

    public function __construct(
        public bool $sandboxMode = false,
        ?string $baseUrl = null,
        public int $requestTimeoutSeconds = 30,
        ?string $dpsSchemaRelease = null,
    ) {
        if ($requestTimeoutSeconds < 1) {
            throw new \InvalidArgumentException('SEFIN request timeout must be at least 1 second.');
        }

        $release = $dpsSchemaRelease ?? ($sandboxMode
            ? self::SCHEMA_RELEASE_RESTRICTED_20261020
            : self::SCHEMA_RELEASE_PRODUCTION_20260209);
        if (!in_array($release, [
            self::SCHEMA_RELEASE_PRODUCTION_20260209,
            self::SCHEMA_RELEASE_RESTRICTED_20261020,
        ], true)) {
            throw new \InvalidArgumentException('Unsupported SEFIN DPS schema release.');
        }
        $this->dpsSchemaRelease = $release;
        $this->baseUrl = rtrim($baseUrl ?? ($sandboxMode
            ? self::BASE_URL_SANDBOX
            : self::BASE_URL_PROD), '/');
    }
}
