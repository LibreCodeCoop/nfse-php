<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Contracts;

use LibreCodeCoop\NfsePHP\Dto\DecisionNfseData;
use LibreCodeCoop\NfsePHP\Dto\ReceiptData;

/**
 * Optional capability for administrative/judicial-decision NFS-e issuance.
 *
 * Kept separate from NfseClientInterface so ordinary NFS-e client
 * implementations are not forced to support the legal bypass flow.
 */
interface DecisionNfseIssuerInterface
{
    public function emitDecision(DecisionNfseData $nfse): ReceiptData;
}
