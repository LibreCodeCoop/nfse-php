<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Support;

use LibreCodeCoop\NfsePHP\Dto\DpsData;

final class DpsIdentifier
{
    /**
     * Build the official DPS identifier used in infDPS@Id.
     */
    public static function fromData(DpsData $dps, bool $withPrefix = true): string
    {
        $identifier = $dps->municipioIbge
            . '2'
            . strtoupper($dps->cnpjPrestador)
            . str_pad($dps->serie, 5, '0', STR_PAD_LEFT)
            . str_pad($dps->numeroDps, 15, '0', STR_PAD_LEFT);

        return $withPrefix ? 'DPS' . $identifier : $identifier;
    }

    /**
     * Normalize an infDPS@Id value for the /dps/{id} API path.
     */
    public static function forApi(string $idDps): string
    {
        $normalized = trim($idDps);

        if (strncasecmp($normalized, 'DPS', 3) === 0) {
            $normalized = substr($normalized, 3);
        }

        if ($normalized === '') {
            throw new \InvalidArgumentException('DPS identifier cannot be empty.');
        }

        return $normalized;
    }
}
