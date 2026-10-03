<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Official NFS-e schemas

This directory contains verbatim reference schemas from the Brazilian Sistema Nacional NFS-e production package:

- package: `NFSe-ESQUEMAS_XSD-v1.01-20260209`
- production documentation: https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/documentacao-atual
- schema version: 1.01
- publication date encoded by the official package: 2026-02-09

These files are intentionally kept unmodified and are used as executable contracts in the test suite. They are licensed separately from the AGPL application code; see `REUSE.toml` and `LICENSES/CC-BY-ND-3.0.txt`.

When the Sistema Nacional NFS-e publishes a new production schema package, add it in a new version directory instead of editing this snapshot in place.


## Known validation caveats

The 2026-02-09 v1.01 snapshot predates the later 2026 production rollout of alphanumeric CNPJ handling, so its `TSCNPJ` type still restricts values to 14 numeric digits.

The snapshot also declares `TSSerieDPS` with `^0{0,4}\d{1,5}$`. XML Schema regular expressions do not use `^` and `$` as anchors, and libxml therefore rejects normal series values against that exact pattern. The test validator keeps this file unchanged and suppresses only that exact upstream validation error. All other schema violations remain actionable.

## Repository integrity manifest

GitHub stores file contents as content-addressed Git blobs. The blob IDs below pin the exact schema snapshot used by this repository and make accidental edits visible in review/CI history.

| File | Git blob SHA-1 |
| --- | --- |
| `DPS_v1.01.xsd` | `39f440dd46db4e15a378b60910a3ac0baf19e476` |
| `tiposComplexos_v1.01.xsd` | `91f44b804ddcba93c65f7dea923ca4621c1dcd6a` |
| `tiposSimples_v1.01.xsd` | `6cf3dcb9c085e284a9b78b9a6a4c2688a95df5e9` |
| `xmldsig-core-schema.xsd` | `8a9c9139d0cb2c3497ce67942ba7d1e8528241d0` |

When refreshing the official bundle, add a new version directory and update its own manifest instead of replacing this v1.01 snapshot in place.

