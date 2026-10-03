<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Http;

use LibreCodeCoop\NfsePHP\Contracts\HttpTransportInterface;
use LibreCodeCoop\NfsePHP\Dto\HttpRequestData;
use LibreCodeCoop\NfsePHP\Dto\HttpResponseData;
use LibreCodeCoop\NfsePHP\Exception\NetworkException;

final class RetryingHttpTransport implements HttpTransportInterface
{
    /** @var \Closure(int): void */
    private readonly \Closure $sleeper;

    /** @var (\Closure(array{method:string,url:string,attempt:int,max_attempts:int,reason:string,status:?int}): void)|null */
    private readonly ?\Closure $onRetry;

    /**
     * @param (callable(int): void)|null $sleeper Receives milliseconds.
     * @param (callable(array{method:string,url:string,attempt:int,max_attempts:int,reason:string,status:?int}): void)|null $onRetry
     */
    public function __construct(
        private readonly HttpTransportInterface $inner,
        private readonly int $maxAttempts = 3,
        private readonly int $baseDelayMs = 100,
        ?callable $sleeper = null,
        ?callable $onRetry = null,
    ) {
        if ($maxAttempts < 1) {
            throw new \InvalidArgumentException('Retry maxAttempts must be at least 1.');
        }

        if ($baseDelayMs < 0) {
            throw new \InvalidArgumentException('Retry baseDelayMs cannot be negative.');
        }

        $this->sleeper = $sleeper !== null
            ? \Closure::fromCallable($sleeper)
            : static function (int $milliseconds): void {
                if ($milliseconds > 0) {
                    usleep($milliseconds * 1000);
                }
            };

        $this->onRetry = $onRetry !== null
            ? \Closure::fromCallable($onRetry)
            : null;
    }

    #[\Override]
    public function request(HttpRequestData $request): HttpResponseData
    {
        $method = strtoupper($request->method);
        $retryableMethod = in_array($method, ['GET', 'HEAD'], true);
        $attempt = 1;

        while (true) {
            try {
                $response = $this->inner->request($request);
            } catch (NetworkException $exception) {
                if (!$retryableMethod || $attempt >= $this->maxAttempts) {
                    throw $exception;
                }

                $this->beforeRetry(
                    request: $request,
                    attempt: $attempt,
                    reason: 'network_exception',
                    status: null,
                );

                $attempt++;

                continue;
            }

            if (
                !$retryableMethod
                || !$this->isTransientStatus($response->status)
                || $attempt >= $this->maxAttempts
            ) {
                return $response;
            }

            $this->beforeRetry(
                request: $request,
                attempt: $attempt,
                reason: 'transient_http_status',
                status: $response->status,
            );

            $attempt++;
        }
    }

    private function isTransientStatus(int $status): bool
    {
        return in_array($status, [429, 502, 503, 504], true);
    }

    private function beforeRetry(
        HttpRequestData $request,
        int $attempt,
        string $reason,
        ?int $status,
    ): void {
        if ($this->onRetry !== null) {
            ($this->onRetry)([
                'method' => strtoupper($request->method),
                'url' => $request->url,
                'attempt' => $attempt,
                'max_attempts' => $this->maxAttempts,
                'reason' => $reason,
                'status' => $status,
            ]);
        }

        ($this->sleeper)($this->backoffDelayMs($attempt));
    }

    private function backoffDelayMs(int $attempt): int
    {
        if ($this->baseDelayMs === 0) {
            return 0;
        }

        return $this->baseDelayMs * (2 ** ($attempt - 1));
    }
}
