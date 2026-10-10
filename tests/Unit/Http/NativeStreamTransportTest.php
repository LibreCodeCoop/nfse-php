<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Http;

use LibreCodeCoop\NfsePHP\Dto\HttpRequestData;
use LibreCodeCoop\NfsePHP\Exception\NetworkException;
use LibreCodeCoop\NfsePHP\Http\NativeStreamTransport;
use PHPUnit\Framework\TestCase;

final class NativeStreamTransportTest extends TestCase
{
    public function testFailedConnectionDoesNotIncludeUrlOrCredentialsInException(): void
    {
        $request = new HttpRequestData(
            method: 'GET',
            url: 'invalid-stream://user:synthetic-password@example.invalid/ACCESS-KEY?token=synthetic-token',
        );

        try {
            (new NativeStreamTransport())->request($request);
            self::fail('The invalid stream scheme must fail.');
        } catch (NetworkException $error) {
            self::assertSame('Failed to connect to fiscal gateway', $error->getMessage());
            self::assertStringNotContainsString('synthetic-password', $error->getMessage());
            self::assertStringNotContainsString('ACCESS-KEY', $error->getMessage());
            self::assertStringNotContainsString('synthetic-token', $error->getMessage());
        }
    }
}
