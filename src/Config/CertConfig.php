<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Config;

/**
 * Immutable certificate configuration for NFS-e mTLS authentication.
 *
 * Holds the contributor CNPJ, the filesystem path to the PFX bundle, and
 * the OpenBao KV path from which the PFX password is retrieved just-in-time.
 * The password is never cached across job boundaries.
 */
final readonly class CertConfig
{
    public function __construct(
        /** CNPJ do prestador de serviço (only digits, 14 chars). */
        public string $cnpj,

        /** Absolute filesystem path to the PFX certificate bundle. */
        public string $pfxPath,

        /** OpenBao KV path for the PFX password (e.g. "secret/nfse/29842527000145"). */
        public string $vaultPath,
    ) {
    }
}
