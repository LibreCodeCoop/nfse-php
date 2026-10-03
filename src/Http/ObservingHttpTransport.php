<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Http;

use LibreCodeCoop\NfsePHP\Contracts\HttpTransportInterface;
use LibreCodeCoop\NfsePHP\Dto\HttpRequestData;
use LibreCodeCoop\NfsePHP\Dto\HttpResponseData;

/**
 * Adds optional structured observability without exposing fiscal payloads,
 * client certificate paths or private-key material.
 */
final class ObservingHttpTransport implements HttpTransportInterface
{
    /**
     * @param \Closure(array{method:string,host:string,status:int,duration_ms:int,outcome:string}): void $observer
     */
    public function __construct(
        private readonly HttpTransportInterface $inner,
        private readonly \Closure $observer,
    ) {
    }

    #[\Override]
    public function request(HttpRequestData $request): HttpResponseData
    {
        $startedAt = hrtime(true);

        try {
            $response = $this->inner->request($request);

            $this->observe($request, $response->status, 'response', $startedAt);

            return $response;
        } catch (\Throwable $e) {
            $this->observe($request, 0, 'exception', $startedAt);

            throw $e;
        }
    }

    private function observe(HttpRequestData $request, int $status, string $outcome, int $startedAt): void
    {
        $host = parse_url($request->url, PHP_URL_HOST);

        ($this->observer)([
            'method' => strtoupper($request->method),
            'host' => is_string($host) ? $host : '',
            'status' => $status,
            'duration_ms' => max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000)),
            'outcome' => $outcome,
        ]);
    }
}
