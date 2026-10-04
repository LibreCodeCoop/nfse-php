<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/**
 * Official National NFS-e substitution reference embedded in a replacement DPS.
 *
 * The replacement is submitted through the normal DPS issuance endpoint. The
 * Sistema Nacional links the replacement to the original NFS-e and creates the
 * corresponding cancellation-by-substitution event.
 */
final readonly class SubstitutionData
{
    /**
     * @param string $chaveNfseSubstituida 50-digit access key of the NFS-e being replaced.
     * @param string $codigoMotivo Official substitution reason: 01-05 or 99.
     * @param string $descricaoMotivo Optional 15-255 character explanation.
     */
    public function __construct(
        public string $chaveNfseSubstituida,
        public string $codigoMotivo,
        public string $descricaoMotivo = '',
    ) {
        if (preg_match('/^\d{50}$/', $this->chaveNfseSubstituida) !== 1) {
            throw new \InvalidArgumentException('Substituted NFS-e access key must contain exactly 50 digits.');
        }

        if (!in_array($this->codigoMotivo, ['01', '02', '03', '04', '05', '99'], true)) {
            throw new \InvalidArgumentException('Substitution reason code must be 01, 02, 03, 04, 05 or 99.');
        }

        $length = mb_strlen($this->descricaoMotivo);

        if ($this->descricaoMotivo !== '' && ($length < 15 || $length > 255)) {
            throw new \InvalidArgumentException(
                'Substitution reason description must contain between 15 and 255 characters when informed.',
            );
        }
    }
}
