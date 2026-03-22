<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Exception;

/**
 * Thrown when the SEFIN gateway rejects an NFS-e cancellation request.
 */
class CancellationException extends GatewayException
{
}
