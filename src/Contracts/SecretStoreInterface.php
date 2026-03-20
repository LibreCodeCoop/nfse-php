<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Contracts;

interface SecretStoreInterface
{
    /**
     * Retrieve a secret map by path.
     *
     * @return array<string, string>
     */
    public function get(string $path): array;

    /**
     * Persist a secret map at the given path.
     *
     * @param array<string, string> $data
     */
    public function put(string $path, array $data): void;

    /**
     * Delete the secret at the given path.
     */
    public function delete(string $path): void;
}
