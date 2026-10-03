<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Contracts;

use LibreCodeCoop\NfsePHP\Dto\EventReceiptData;

interface EventLookupInterface
{
    /**
     * Query one event by access key, event type and event sequence number.
     */
    public function queryEvent(string $chaveAcesso, int $tipoEvento, int $numSeqEvento = 1): EventReceiptData;
}
