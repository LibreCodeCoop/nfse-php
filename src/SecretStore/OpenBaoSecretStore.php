<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\SecretStore;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Uri;
use LibreCodeCoop\NfsePHP\Contracts\SecretStoreInterface;
use LibreCodeCoop\NfsePHP\Exception\SecretStoreException;
use Vault\AuthenticationStrategies\AppRoleAuthenticationStrategy;
use Vault\AuthenticationStrategies\TokenAuthenticationStrategy;
use Vault\Client;

/**
 * OpenBao / HashiCorp Vault KV v2 secret store.
 *
 * Supports two authentication methods:
 *  - Token (for development/CI): pass $token
 *  - AppRole (for production):   pass $roleId + $secretId
 *
 * Secrets are stored under: {$mount}/data/{$path}
 */
class OpenBaoSecretStore implements SecretStoreInterface
{
    private readonly Client $vault;

    public function __construct(
        private readonly string $addr,
        private readonly string $mount = 'nfse',
        private readonly ?string $token = null,
        private readonly ?string $roleId = null,
        private readonly ?string $secretId = null,
        private readonly ?string $namespace = null,
    ) {
        if ($this->token === null && ($this->roleId === null || $this->secretId === null)) {
            throw new SecretStoreException('Either $token or ($roleId + $secretId) must be provided.');
        }

        $this->vault = $this->buildClient();
    }

    /**
     * @return array<string, string>
     */
    public function get(string $path): array
    {
        try {
            $response = $this->vault->read($this->kvPath($path));
            /** @var array<string, string> $data */
            $data = $response->getData()['data'] ?? [];

            return $data;
        } catch (\Throwable $e) {
            throw new SecretStoreException('Failed to read secret at path "' . $path . '": ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * @param array<string, string> $data
     */
    public function put(string $path, array $data): void
    {
        try {
            $this->vault->write($this->kvPath($path), ['data' => $data]);
        } catch (\Throwable $e) {
            throw new SecretStoreException('Failed to write secret at path "' . $path . '": ' . $e->getMessage(), previous: $e);
        }
    }

    public function delete(string $path): void
    {
        try {
            $this->vault->revoke($this->kvPath($path));
        } catch (\Throwable $e) {
            throw new SecretStoreException('Failed to delete secret at path "' . $path . '": ' . $e->getMessage(), previous: $e);
        }
    }

    // -------------------------------------------------------------------------

    private function kvPath(string $path): string
    {
        // KV v2 API uses the "data" sub-path for reads/writes
        return $this->mount . '/data/' . ltrim($path, '/');
    }

    private function buildClient(): Client
    {
        $client = new Client(
            new Uri($this->addr),
            new HttpClient(),
            new HttpFactory(),
            new HttpFactory(),
        );

        if ($this->namespace !== null) {
            $client->setNamespace($this->namespace);
        }

        if ($this->token !== null) {
            $client->setAuthenticationStrategy(new TokenAuthenticationStrategy($this->token));
        } else {
            $roleId = $this->roleId;
            $secretId = $this->secretId;

            if ($roleId === null || $secretId === null) {
                throw new SecretStoreException('AppRole credentials are incomplete.');
            }

            $client->setAuthenticationStrategy(
                new AppRoleAuthenticationStrategy($roleId, $secretId)
            );
        }

        $client->authenticate();

        return $client;
    }
}
