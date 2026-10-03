<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Protocol contract fixtures

These fixtures are synthetic protocol-shape examples used by deterministic tests. They contain no real taxpayer data, certificates, credentials, access keys or production payloads.

Each JSON fixture records its intended contract/source context inside `_meta`. The payload itself is under `response`.

When adding a regression fixture:
- prefer a synthetic fixture that reproduces the upstream shape;
- if a captured response is needed, remove all PII/secrets and mark it as sanitized;
- record the relevant API/schema version or documentation family;
- add an expectation in `ProtocolContractFixtureTest`.
