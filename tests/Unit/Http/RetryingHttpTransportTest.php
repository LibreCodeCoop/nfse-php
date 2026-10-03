<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Http;

use LibreCodeCoop\NfsePHP\Contracts\HttpTransportInterface;
use LibreCodeCoop\NfsePHP\Dto\HttpRequestData;
use LibreCodeCoop\NfsePHP\Dto\HttpResponseData;
use LibreCodeCoop\NfsePHP\Exception\NetworkException;
use LibreCodeCoop\NfsePHP\Http\RetryingHttpTransport;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

final class RetryingHttpTransportTest extends TestCase
{
    public function testRetriesGetAfterNetworkFailure(): void
    {
        $inner = new SequenceTransport(
            new NetworkException('temporary network failure'),
            new HttpResponseData(200, '{"ok":true}'),
        );
        $delays = [];

        $transport = new RetryingHttpTransport(
            inner: $inner,
            maxAttempts: 3,
            baseDelayMs: 100,
            sleeper: static function (int $milliseconds) use (&$delays): void {
                $delays[] = $milliseconds;
            },
        );

        $response = $transport->request($this->request('GET'));

        self::assertSame(200, $response->status);
        self::assertSame(2, $inner->calls);
        self::assertSame([100], $delays);
    }

    public function testRetriesGetForTransientHttpStatusWithExponentialBackoff(): void
    {
        $inner = new SequenceTransport(
            new HttpResponseData(503, 'busy'),
            new HttpResponseData(502, 'still busy'),
            new HttpResponseData(200, 'ok'),
        );
        $delays = [];

        $transport = new RetryingHttpTransport(
            inner: $inner,
            maxAttempts: 3,
            baseDelayMs: 50,
            sleeper: static function (int $milliseconds) use (&$delays): void {
                $delays[] = $milliseconds;
            },
        );

        $response = $transport->request($this->request('GET'));

        self::assertSame(200, $response->status);
        self::assertSame(3, $inner->calls);
        self::assertSame([50, 100], $delays);
    }

    /**
     * @dataProvider mutatingMethodProvider
     */
    public function testNeverRetriesMutatingRequests(string $method): void
    {
        $inner = new SequenceTransport(
            new HttpResponseData(503, 'busy'),
            new HttpResponseData(200, 'would be unsafe to reach'),
        );

        $transport = new RetryingHttpTransport(
            inner: $inner,
            maxAttempts: 3,
            baseDelayMs: 0,
        );

        $response = $transport->request($this->request($method));

        self::assertSame(503, $response->status);
        self::assertSame(1, $inner->calls);
    }

    public function testNeverRetriesPostAfterNetworkFailure(): void
    {
        $inner = new SequenceTransport(
            new NetworkException('ambiguous POST outcome'),
            new HttpResponseData(200, 'unsafe second request'),
        );

        $transport = new RetryingHttpTransport(
            inner: $inner,
            maxAttempts: 3,
            baseDelayMs: 0,
        );

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('ambiguous POST outcome');

        try {
            $transport->request($this->request('POST'));
        } finally {
            self::assertSame(1, $inner->calls);
        }
    }

    public function testStopsAfterMaximumAttempts(): void
    {
        $inner = new SequenceTransport(
            new HttpResponseData(503, 'one'),
            new HttpResponseData(503, 'two'),
            new HttpResponseData(503, 'three'),
            new HttpResponseData(200, 'must not be reached'),
        );

        $transport = new RetryingHttpTransport(
            inner: $inner,
            maxAttempts: 3,
            baseDelayMs: 0,
        );

        $response = $transport->request($this->request('HEAD'));

        self::assertSame(503, $response->status);
        self::assertSame(3, $inner->calls);
    }

    public function testRetryObserverDoesNotReceiveCertificateOrPrivateKeyPaths(): void
    {
        $inner = new SequenceTransport(
            new HttpResponseData(429, 'rate limited'),
            new HttpResponseData(200, 'ok'),
        );
        $events = [];

        $transport = new RetryingHttpTransport(
            inner: $inner,
            maxAttempts: 2,
            baseDelayMs: 0,
            onRetry: static function (array $event) use (&$events): void {
                $events[] = $event;
            },
        );

        $transport->request(new HttpRequestData(
            method: 'GET',
            url: 'https://example.invalid/nfse/ACCESS-42',
            clientCertificatePath: '/secret/client-cert.pem',
            clientPrivateKeyPath: '/secret/client-key.pem',
        ));

        self::assertCount(1, $events);
        self::assertSame([
            'method' => 'GET',
            'url' => 'https://example.invalid/nfse/ACCESS-42',
            'attempt' => 1,
            'max_attempts' => 2,
            'reason' => 'transient_http_status',
            'status' => 429,
        ], $events[0]);

        self::assertStringNotContainsString('client-cert', json_encode($events, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('client-key', json_encode($events, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function mutatingMethodProvider(): array
    {
        return [
            'POST' => ['POST'],
            'PUT' => ['PUT'],
            'PATCH' => ['PATCH'],
            'DELETE' => ['DELETE'],
        ];
    }

    private function request(string $method): HttpRequestData
    {
        return new HttpRequestData(
            method: $method,
            url: 'https://example.invalid/resource',
        );
    }
}

final class SequenceTransport implements HttpTransportInterface
{
    public int $calls = 0;

    /** @var list<HttpResponseData|NetworkException> */
    private array $sequence;

    public function __construct(HttpResponseData|NetworkException ...$sequence)
    {
        $this->sequence = array_values($sequence);
    }

    public function request(HttpRequestData $request): HttpResponseData
    {
        $this->calls++;

        if ($this->sequence === []) {
            throw new \RuntimeException('Sequence transport exhausted.');
        }

        $next = array_shift($this->sequence);

        if ($next instanceof NetworkException) {
            throw $next;
        }

        return $next;
    }
}
