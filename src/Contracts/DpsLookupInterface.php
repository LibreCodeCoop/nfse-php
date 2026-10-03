<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Contracts;

interface DpsLookupInterface
{
    /**
     * Return the NFS-e access key generated from a DPS identifier.
     *
     * The identifier may be provided either with or without the "DPS" prefix.
     */
    public function queryDps(string $idDps): string;

    /**
     * Check whether the SEFIN gateway already generated an NFS-e for a DPS.
     *
     * This maps to the official HEAD /dps/{id} endpoint and is intended for
     * recovery after ambiguous network failures without re-sending the DPS.
     */
    public function existsDps(string $idDps): bool;
}
