<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\SecretStore;

use LibreCodeCoop\NfsePHP\SecretStore\NoOpSecretStore;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * @covers \LibreCodeCoop\NfsePHP\SecretStore\NoOpSecretStore
 */
class NoOpSecretStoreTest extends TestCase
{
    private NoOpSecretStore $store;

    protected function setUp(): void
    {
        $this->store = new NoOpSecretStore();
    }

    public function testGetReturnsEmptyArrayWhenPathNotSet(): void
    {
        self::assertSame([], $this->store->get('pfx/unknown'));
    }

    public function testPutAndGetRoundtrip(): void
    {
        $this->store->put('pfx/12345678000100', ['password' => 'secret123']);

        self::assertSame(['password' => 'secret123'], $this->store->get('pfx/12345678000100'));
    }

    public function testDeleteRemovesSecret(): void
    {
        $this->store->put('pfx/12345678000100', ['password' => 'secret123']);
        $this->store->delete('pfx/12345678000100');

        self::assertSame([], $this->store->get('pfx/12345678000100'));
    }

    public function testMultiplePathsAreIndependent(): void
    {
        $this->store->put('pfx/11111111000100', ['password' => 'aaa']);
        $this->store->put('pfx/22222222000100', ['password' => 'bbb']);

        self::assertSame('aaa', $this->store->get('pfx/11111111000100')['password']);
        self::assertSame('bbb', $this->store->get('pfx/22222222000100')['password']);
    }
}
