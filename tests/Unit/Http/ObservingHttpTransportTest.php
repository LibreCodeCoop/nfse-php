<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Http;

use LibreCodeCoop\NfsePHP\Contracts\HttpTransportInterface;
use LibreCodeCoop\NfsePHP\Dto\HttpRequestData;
use LibreCodeCoop\NfsePHP\Dto\HttpResponseData;
use LibreCodeCoop\NfsePHP\Http\ObservingHttpTransport;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

final class ObservingHttpTransportTest extends TestCase
{
    public function testObserverReceivesOnlyRedactedMetadata(): void
    {
        $events = [];
        $inner = new class () implements HttpTransportInterface {
            public function request(HttpRequestData $request): HttpResponseData
            {
                return new HttpResponseData(200, '{"secret":"response-body"}');
            }
        };

        $transport = new ObservingHttpTransport(
            $inner,
            static function (array $event) use (&$events): void {
                $events[] = $event;
            },
        );

        $request = new HttpRequestData(
            method: 'get',
            url: 'https://sefin.example.test/nfse/ACCESS-KEY-SECRET',
            headers: ['Authorization' => 'Bearer secret-token'],
            body: '{"certificatePassword":"secret"}',
            clientCertificatePath: '/tmp/private-cert.pem',
            clientPrivateKeyPath: '/tmp/private-key.pem',
        );

        $response = $transport->request($request);

        self::assertSame(200, $response->status);
        self::assertCount(1, $events);
        self::assertSame('GET', $events[0]['method']);
        self::assertSame('sefin.example.test', $events[0]['host']);
        self::assertSame(200, $events[0]['status']);
        self::assertSame('response', $events[0]['outcome']);
        self::assertGreaterThanOrEqual(0, $events[0]['duration_ms']);

        $serialized = json_encode($events[0], JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('ACCESS-KEY-SECRET', $serialized);
        self::assertStringNotContainsString('secret-token', $serialized);
        self::assertStringNotContainsString('certificatePassword', $serialized);
        self::assertStringNotContainsString('private-cert.pem', $serialized);
        self::assertStringNotContainsString('private-key.pem', $serialized);
        self::assertStringNotContainsString('response-body', $serialized);
    }

    public function testObserverRecordsExceptionWithoutSwallowingIt(): void
    {
        $events = [];
        $inner = new class () implements HttpTransportInterface {
            public function request(HttpRequestData $request): HttpResponseData
            {
                throw new \RuntimeException('transport failed with sensitive detail');
            }
        };

        $transport = new ObservingHttpTransport(
            $inner,
            static function (array $event) use (&$events): void {
                $events[] = $event;
            },
        );

        try {
            $transport->request(new HttpRequestData(
                method: 'POST',
                url: 'https://sefin.example.test/nfse',
                body: '{"secret":"payload"}',
            ));
            self::fail('Expected runtime exception.');
        } catch (\RuntimeException $e) {
            self::assertSame('transport failed with sensitive detail', $e->getMessage());
        }

        self::assertCount(1, $events);
        self::assertSame('POST', $events[0]['method']);
        self::assertSame(0, $events[0]['status']);
        self::assertSame('exception', $events[0]['outcome']);
    }
}
