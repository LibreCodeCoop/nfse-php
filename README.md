<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# nfse-php

> Framework-agnostic PHP library for issuing, querying, and cancelling **Nota Fiscal de Serviço Eletrônica (NFS-e)** via SEFIN Nacional (ABRASF 2.04 / SEFIN 1.0).

[![Latest Version](https://img.shields.io/packagist/v/librecodeoop/nfse-php?style=flat-square)](https://packagist.org/packages/librecodeoop/nfse-php)
[![PHP Version](https://img.shields.io/packagist/php-v/librecodeoop/nfse-php?style=flat-square)](https://packagist.org/packages/librecodeoop/nfse-php)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg?style=flat-square)](https://www.gnu.org/licenses/agpl-3.0)
[![CI](https://github.com/LibreCodeCoop/nfse-php/actions/workflows/phpunit.yml/badge.svg)](https://github.com/LibreCodeCoop/nfse-php/actions/workflows/phpunit.yml)
[![codecov](https://codecov.io/gh/LibreCodeCoop/nfse-php/branch/main/graph/badge.svg)](https://codecov.io/gh/LibreCodeCoop/nfse-php)

## Scope

- Emit NFS-e (`emit`)
- Query NFS-e (`query`)
- Cancel NFS-e (`cancel`)
- Retrieve DANFSE bytes (`getDanfse`)
- Sign DPS XML with PFX credentials
- Read secrets from OpenBao/Vault or an in-memory store

## Installation

```bash
composer require librecodeoop/nfse-php
```

## Quick Start

```php
use LibreCodeCoop\NfsePHP\Config\CertConfig;
use LibreCodeCoop\NfsePHP\Config\EnvironmentConfig;
use LibreCodeCoop\NfsePHP\Dto\DpsData;
use LibreCodeCoop\NfsePHP\Http\NfseClient;
use LibreCodeCoop\NfsePHP\SecretStore\OpenBaoSecretStore;

$store  = new OpenBaoSecretStore(addr: 'http://localhost:8200', token: getenv('VAULT_TOKEN'));
$env    = new EnvironmentConfig(sandboxMode: true);
$cert   = new CertConfig(
    cnpj: '11222333000181',
    pfxPath: '/secure/path/certificate.pfx',
    vaultPath: 'pfx/11222333000181',
);

$client = new NfseClient(environment: $env, cert: $cert, secretStore: $store);

$dps = new DpsData(
    cnpjPrestador: '11222333000181', // Example only: configure with your provider CNPJ
    municipioIbge: '3303302',
    // ... other fields
);

$receipt = $client->emit($dps);
echo $receipt->nfseNumber; // NFS-e number returned by the SEFIN gateway
```

## Secret Storage with OpenBao

PFX passwords are stored in OpenBao (or Vault) KV v2, for example in `nfse/pfx/{cnpj}`.

```php
use LibreCodeCoop\NfsePHP\SecretStore\OpenBaoSecretStore;

$store = new OpenBaoSecretStore(
    addr:      getenv('VAULT_ADDR'),   // e.g. http://openbao:8200
    roleId:    getenv('VAULT_ROLE_ID'),
    secretId:  getenv('VAULT_SECRET_ID'),
    mount:     'nfse',               // KV v2 mount
);

// Store the PFX password after upload
$store->put('pfx/11222333000181', ['password' => 'secret']);

// Retrieve during signing
$password = $store->get('pfx/11222333000181')['password'];
```

For development/CI without OpenBao, use `NoOpSecretStore` (in-memory only, no server calls).

## Contributing

All commits must use [Conventional Commits](https://www.conventionalcommits.org/) and be signed off (`git commit -s`).

## Give us a star!

If this library saves you hours of integration pain, please ⭐ the repository.  
It helps other developers discover the project and motivates the team to keep improving it.
