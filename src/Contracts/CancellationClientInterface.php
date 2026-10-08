<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Contracts;

/**
 * Explicit cancellation contract for the official e101101 reason code and description.
 */
interface CancellationClientInterface
{
    /**
     * @param string $codigoMotivo Official TSCodJustCanc value: 1, 2 or 9.
     */
    public function cancelWithReason(string $chaveAcesso, string $codigoMotivo, string $motivo): bool;
}
