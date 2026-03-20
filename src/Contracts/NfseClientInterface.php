<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Contracts;

use LibreCodeCoop\NfsePHP\Dto\DpsData;
use LibreCodeCoop\NfsePHP\Dto\ReceiptData;

interface NfseClientInterface
{
    /**
     * Emit an NFS-e for the given DPS data.
     */
    public function emit(DpsData $dps): ReceiptData;

    /**
     * Query an existing NFS-e by its access key.
     */
    public function query(string $chaveAcesso): ReceiptData;

    /**
     * Cancel an existing NFS-e.
     */
    public function cancel(string $chaveAcesso, string $motivo): bool;
}
