<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Exception;

/**
 * Thrown when the SEFIN gateway responds with an HTTP error status (4xx/5xx).
 *
 * The upstream error payload is preserved to allow callers to surface
 * gateway-specific diagnostic information (e.g. fiscal rejection codes).
 */
class GatewayException extends NfseException
{
    /**
     * @param array<string, mixed> $upstreamPayload Raw decoded response body from the gateway.
     */
    public function __construct(
        string $message,
        public readonly NfseErrorCode $errorCode,
        public readonly int $httpStatus = 0,
        public readonly array $upstreamPayload = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
