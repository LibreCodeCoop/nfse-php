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

---

## Why nfse-php?

Emitting NFS-e in Brazil involves XML signing with ICP-Brasil certificates, SOAP/REST calls to multiple municipal gateways, and safe credential management — all of which most accounting software gets wrong.

**nfse-php** handles all of it correctly:

- **XML signing** with PFX/PKCS#12 certificates (native PHP first, CLI repack fallback for OpenSSL legacy format)
- **Credential isolation** — PFX passwords are never stored in your database; they live in [OpenBao](https://openbao.org/) / HashiCorp Vault KV v2
- **Pluggable secret store** — swap OpenBao for any `SecretStoreInterface` implementation
- **Tier-1 tests always run** via `donatj/mock-webserver` (no real cert required in CI)
- **Strict PHP 8.2+ types** throughout

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ^8.2 |
| ext-openssl | * |
| ext-dom | * |
| ext-soap | * |

---

## Installation

```bash
composer require librecodeoop/nfse-php
```

---

## Quick Start

```php
use LibreCodeCoop\NfsePHP\Http\NfseClient;
use LibreCodeCoop\NfsePHP\SecretStore\OpenBaoSecretStore;
use LibreCodeCoop\NfsePHP\Dto\DpsData;

$store  = new OpenBaoSecretStore(addr: 'http://localhost:8200', token: getenv('BAO_TOKEN'));
$client = new NfseClient(secretStore: $store, sandboxMode: true);

$dps = new DpsData(
    cnpjPrestador: '11222333000181',
    municipioIbge: '3303302',
    // ... other fields
);

$receipt = $client->emit($dps);
echo $receipt->nfseNumber; // NFS-e number returned by the SEFIN gateway
```

---

## Secret Storage with OpenBao

PFX passwords are **never** persisted in application databases. They are stored in OpenBao (or Vault) KV v2 under a path like `nfse/pfx/{cnpj}`.

```php
use LibreCodeCoop\NfsePHP\SecretStore\OpenBaoSecretStore;

$store = new OpenBaoSecretStore(
    addr:      getenv('BAO_ADDR'),   // e.g. http://openbao:8200
    roleId:    getenv('BAO_ROLE_ID'),
    secretId:  getenv('BAO_SECRET_ID'),
    mount:     'nfse',               // KV v2 mount
);

// Store the PFX password after upload
$store->put('pfx/11222333000181', ['password' => 'secret']);

// Retrieve during signing
$password = $store->get('pfx/11222333000181')['password'];
```

For development/CI without OpenBao, use `NoOpSecretStore` which reads directly from constructor arguments and never touches any server.

---

## Roadmap

- [x] DPS issuance via SEFIN Nacional REST API
- [x] XML signing (PFX, ICP-Brasil)
- [x] OpenBao / Vault KV v2 secret store
- [x] Mock webserver for CI-friendly testing
- [ ] NFS-e query (GET /dps/{id})
- [ ] NFS-e cancellation
- [ ] Webhook / event polling
- [ ] Municipal gateway adapters beyond Niterói

---

## Commercial Support

This library is the foundation of the [akaunting-nfse](https://github.com/LibreCodeCoop/akaunting-nfse) module for [Akaunting](https://akaunting.com/).

Need SLA-backed support, custom municipal adapters, or managed hosting?  
Contact us: **comercial@librecodecoop.org.br**

---

## Contributing

We welcome issues and pull requests. Please read [CONTRIBUTING.md](CONTRIBUTING.md) before opening a PR.

All commits must use [Conventional Commits](https://www.conventionalcommits.org/) and be signed off (`git commit -s`).

---

## Give us a star!

If this library saves you hours of integration pain, please ⭐ the repository.  
It helps other developers discover the project and motivates the team to keep improving it.

---

## License

GNU Affero General Public License v3.0 or later — see [LICENSES/AGPL-3.0-or-later.txt](LICENSES/AGPL-3.0-or-later.txt).  
&copy; 2026 LibreCode Coop and contributors.
