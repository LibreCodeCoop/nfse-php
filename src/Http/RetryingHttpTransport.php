<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Http;

use LibreCodeCoop\NfsePHP\Contracts\HttpTransportInterface;
use LibreCodeCoop\NfsePHP\Dto\HttpRequestData;
use LibreCodeCoop\NfsePHP\Dto\HttpResponseData;
use LibreCodeCoop\NfsePHP\Exception\NetworkException;

/**
 * Retries only idempotent fiscal reads.
 *
 * Mutating requests (POST/PUT/PATCH/DELETE) are deliberately delegated once.
 * NFS-e emission recovery must continue to use DPS lookup instead of blind
 * transport retries.
 */
final class RetryingHttpTransport implements HttpTransportInterface
{
    /**
     * @param list<int> $retryableStatuses
     * @param (\Closure(int): void)|null $sleeper Receives the delay in milliseconds.
     */
    public function __construct(
        private readonly HttpTransportInterface $inner,
        private readonly int $maxAttempts = 3,
        private readonly int $baseDelayMilliseconds = 100,
        private readonly array $retryableStatuses = [408, 425, 429, 500, 502, 503, 504],
        private readonly ?\Closure $sleeper = null,
    ) {
        if ($this->maxAttempts < 1) {
            throw new \InvalidArgumentException('maxAttempts must be at least 1.');
        }

        if ($this->baseDelayMilliseconds < 0) {
            throw new \InvalidArgumentException('baseDelayMilliseconds cannot be negative.');
        }
    }

    #[\Override]
    public function request(HttpRequestData $request): HttpResponseData
    {
        $method = strtoupper($request->method);

        if (!in_array($method, ['GET', 'HEAD'], true) || $this->maxAttempts === 1) {
            return $this->inner->request($request);
        }

        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $response = $this->inner->request($request);

                if (
                    $attempt >= $this->maxAttempts
                    || !in_array($response->status, $this->retryableStatuses, true)
                ) {
                    return $response;
                }
            } catch (NetworkException $e) {
                if ($attempt >= $this->maxAttempts) {
                    throw $e;
                }
            }

            $this->sleepBeforeRetry($attempt);
        }
    }

    private function sleepBeforeRetry(int $failedAttempt): void
    {
        $delay = $this->baseDelayMilliseconds * (2 ** max(0, $failedAttempt - 1));

        if ($delay <= 0) {
            return;
        }

        if ($this->sleeper !== null) {
            ($this->sleeper)($delay);

            return;
        }

        usleep($delay * 1000);
    }
}
