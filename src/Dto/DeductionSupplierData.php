<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/**
 * Optional supplier identity used by a document-backed deduction.
 *
 * Address data is intentionally omitted because TCInfoPessoa makes it optional.
 */
final readonly class DeductionSupplierData
{
    public function __construct(
        public string $identityType,
        public string $identity,
        public string $name,
        public string $caepf = '',
        public string $municipalRegistration = '',
        public string $phone = '',
        public string $email = '',
    ) {
    }
}
