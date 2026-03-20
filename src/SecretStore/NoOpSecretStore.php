<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\SecretStore;

use LibreCodeCoop\NfsePHP\Contracts\SecretStoreInterface;

/**
 * No-op secret store for tests and local dev without a running OpenBao instance.
 *
 * Secrets live only in memory for the lifetime of this object.
 * Never use in production.
 */
class NoOpSecretStore implements SecretStoreInterface
{
    /** @var array<string, array<string, string>> */
    private array $store = [];

    /**
     * @return array<string, string>
     */
    public function get(string $path): array
    {
        return $this->store[$path] ?? [];
    }

    /**
     * @param array<string, string> $data
     */
    public function put(string $path, array $data): void
    {
        $this->store[$path] = $data;
    }

    public function delete(string $path): void
    {
        unset($this->store[$path]);
    }
}
