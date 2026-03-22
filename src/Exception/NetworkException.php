<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Exception;

/**
 * Thrown when a network-level failure prevents communication with the gateway,
 * or when the gateway returns an unparseable response.
 */
class NetworkException extends NfseException
{
    public function __construct(
        string $message,
        public readonly NfseErrorCode $errorCode = NfseErrorCode::NetworkFailure,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
