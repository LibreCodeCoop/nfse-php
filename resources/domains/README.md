<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Official NFS-e domain snapshots

Runtime domain lookups use committed, versioned TSV data. They never
download, parse or interpret official XLSX workbooks at runtime.

`sources.json` is the canonical manifest of official publication URLs,
published versions, environments and observed SHA-256 checksums.
Checksums attest only to observed file bytes, **not** to an official digital
signature or deployment/activation of a tax rule.

The library retains separate sources for municipalities, countries, national
services, NBS and IBS/CBS operation indicators. The production Annex C
indicators and published NT009 Annex VII indicators are different versioned
tables. Calling `OfficialDomainCatalog::operationIndicator()` without an
explicit version continues to use the production catalog. The Annex VIII
crosswalk is consultative and is not enforced as a fiscal validation rule.

## Reproduce and update

Workbook tooling (including PhpSpreadsheet) is isolated under
`vendor-bin/domains/` and is not installed for the production SDK.

```bash
composer domains:install
composer domains:download -- --ids=annex-a,annex-b,annex-c --output=/tmp/nfse-official
composer domains:check -- --annex-a=/tmp/nfse-official/annex-a.xlsx --annex-b=/tmp/nfse-official/annex-b.xlsx --annex-c=/tmp/nfse-official/annex-c.xlsx
composer domains:verify-indicators -- --annex-vii=/path/to/ANEXO_VII.xlsx
composer domains:audit -- --annex-vi=/path/to/ANEXO_VI.xlsx --annex-vii=/path/to/ANEXO_VII.xlsx
composer domains:test
```

On an upstream change, first compare the source bytes and the actual
schema/rules. Regenerate into a new versioned artifact, update
`sources.json`, and review domain cardinalities and representative
values against the official workbook. Never silently rewrite an older
snapshot or infer field conditions from spreadsheet formulas.

Offline tests remain deterministic if government endpoints are unavailable.
The source-watch workflow is advisory: a changed file hash or a newly
published annex needs human normative review before any runtime activation.

For the NT009 preview/production separation, see
[the contract guide](../../docs/nt009.md).
