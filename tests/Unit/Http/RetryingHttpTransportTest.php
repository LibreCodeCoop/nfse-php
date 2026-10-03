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
    public function testRetriesTransientGetUntilSuccess(): void
    {
        $inner = new QueueTransport(
            new HttpResponseData(503, 'busy'),
            new HttpResponseData(429, 'slow down'),
            new HttpResponseData(200, 'ok'),
        );
        $delays = [];

        $transport = new RetryingHttpTransport(
            inner: $inner,
            maxAttempts: 3,
            baseDelayMilliseconds: 10,
            sleeper: static function (int $milliseconds) use (&$delays): void {
                $delays[] = $milliseconds;
            },
        );

        $response = $transport->request(new HttpRequestData('GET', 'https://example.test/resource'));

        self::assertSame(200, $response->status);
        self::assertSame(3, $inner->calls);
        self::assertSame([10, 20], $delays);
    }

    public function testRetriesHeadAfterNetworkFailure(): void
    {
        $inner = new QueueTransport(
            new NetworkException('temporary failure'),
            new HttpResponseData(200, ''),
        );

        $transport = new RetryingHttpTransport(
            inner: $inner,
            maxAttempts: 2,
            baseDelayMilliseconds: 0,
        );

        $response = $transport->request(new HttpRequestData('HEAD', 'https://example.test/resource'));

        self::assertSame(200, $response->status);
        self::assertSame(2, $inner->calls);
    }

    public function testDoesNotRetryPostAfterTransientGatewayResponse(): void
    {
        $inner = new QueueTransport(
            new HttpResponseData(503, 'busy'),
            new HttpResponseData(200, 'would be unsafe second call'),
        );

        $transport = new RetryingHttpTransport(
            inner: $inner,
            maxAttempts: 3,
            baseDelayMilliseconds: 0,
        );

        $response = $transport->request(new HttpRequestData('POST', 'https://example.test/nfse', body: '{}'));

        self::assertSame(503, $response->status);
        self::assertSame(1, $inner->calls);
    }

    public function testDoesNotRetryPostAfterNetworkFailure(): void
    {
        $inner = new QueueTransport(
            new NetworkException('ambiguous POST outcome'),
            new HttpResponseData(200, 'must not be reached'),
        );

        $transport = new RetryingHttpTransport(
            inner: $inner,
            maxAttempts: 3,
            baseDelayMilliseconds: 0,
        );

        $this->expectException(NetworkException::class);

        try {
            $transport->request(new HttpRequestData('POST', 'https://example.test/nfse', body: '{}'));
        } finally {
            self::assertSame(1, $inner->calls);
        }
    }

    public function testReturnsLastRetryableResponseAfterAttemptBudgetIsExhausted(): void
    {
        $inner = new QueueTransport(
            new HttpResponseData(503, 'one'),
            new HttpResponseData(503, 'two'),
        );

        $transport = new RetryingHttpTransport(
            inner: $inner,
            maxAttempts: 2,
            baseDelayMilliseconds: 0,
        );

        $response = $transport->request(new HttpRequestData('GET', 'https://example.test/resource'));

        self::assertSame(503, $response->status);
        self::assertSame(2, $inner->calls);
    }

    public function testDoesNotRetryBusinessClientErrors(): void
    {
        $inner = new QueueTransport(
            new HttpResponseData(400, 'business rejection'),
            new HttpResponseData(200, 'must not be reached'),
        );

        $transport = new RetryingHttpTransport(
            inner: $inner,
            maxAttempts: 3,
            baseDelayMilliseconds: 0,
        );

        $response = $transport->request(new HttpRequestData('GET', 'https://example.test/resource'));

        self::assertSame(400, $response->status);
        self::assertSame(1, $inner->calls);
    }

    public function testRejectsInvalidRetryConfiguration(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RetryingHttpTransport(new QueueTransport(), maxAttempts: 0);
    }
}

final class QueueTransport implements HttpTransportInterface
{
    public int $calls = 0;

    /** @var list<HttpResponseData|NetworkException> */
    private array $queue;

    public function __construct(HttpResponseData|NetworkException ...$queue)
    {
        $this->queue = $queue;
    }

    #[\Override]
    public function request(HttpRequestData $request): HttpResponseData
    {
        $this->calls++;

        if ($this->queue === []) {
            throw new \RuntimeException('No queued transport response.');
        }

        $next = array_shift($this->queue);

        if ($next instanceof NetworkException) {
            throw $next;
        }

        return $next;
    }
}
