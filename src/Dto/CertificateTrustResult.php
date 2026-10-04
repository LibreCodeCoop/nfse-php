<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

final readonly class CertificateTrustResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public bool $signatureValid,
        public bool $certificatePresent,
        public bool $certificateTimeValid,
        public bool $chainTrusted,
        public string $revocationStatus,
        public array $errors = [],
    ) {
    }

    public function isTrusted(): bool
    {
        return $this->signatureValid
            && $this->certificatePresent
            && $this->certificateTimeValid
            && $this->chainTrusted
            && in_array($this->revocationStatus, ['not_checked', 'good'], true);
    }
}
