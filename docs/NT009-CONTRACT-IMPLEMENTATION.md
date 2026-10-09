<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# NT009 v1.01 — DPS contract matrix and activation boundary

## Original normative sources

The authoritative field matrix is committed at
`resources/contracts/nt009-dps-v1.04.01-matrix.tsv`. The **450 data rows**
come from two **checksum-pinned** official XLSX sources and record, for every
path, type, size, cardinality, original field description, conditional guidance
and source row in both contracts. An element moved to a different parent appears
as one added and one removed XML path; it is not necessarily eliminated.

| XLSX | Source and environment | Observed SHA-256 |
| --- | --- | --- |
| Anexo I v1.01, 2026-02-09 | Production | `de5bc492959eadc8bfa7540e16939995924f2188f743648eaf84d3b31e9eeb7c` |
| Anexo VI v1.04.01 | RTC published; deployment not proven | `103a150dd6f56ec8edcaa3a453b59a1b0e096d7cd5025d7bd06a387f141ddf39` |
| Anexo VII v1.03.00 | RTC published; deployment not proven | `95f30a44ee94adeea57fb92f3a0337b1e62fdc106f393e73894087889be9e5aa` |

Hashes are local observations, **not** government-issued signatures. The
comparison found **168 unchanged, 38 changed in place, 135 added, and 109
removed or moved** XML paths, not 282 distinct new fiscal requirements.

## Representative directly verified changes

| XML path (under `DPS/infDPS/`) | Production Anexo I | NT009 Anexo VI |
| --- | --- | --- |
| `finNFSe` | `IBSCBS/finNFSe`, 1-1 | Direct child, 1-1, 0/1/2 |
| `tpNFSeDebito`, `tpNFSeCredito` | Absent | Direct children, 0-1; conditionally required for `finNFSe` 2 and 1 |
| `indDest` / `dest` | Children of IBSCBS | Direct `infDPS` children |
| `IBSCBS/cIndOp` | 1-1 | 0-1; classification-dependent |
| `IBSCBS/valores/trib/CST` | Under `gIBSCBS` | Direct `trib` child, 1-1 when `trib` exists |
| `IBSCBS/valores/trib/cClassTrib` | Under `gIBSCBS` | Direct `trib` child, 1-1 when `trib` exists |
| `IBSCBS/valores/trib/gIBSCBS` | 1-1 | 0-1, `ind_gIBSCBS`-dependent |
| `IBSCBS/valores/trib/gIBSCBS/gIBSCBSAjuste` | Absent | 0-1, allowed only for specified credit/debit adjustment types |

The table compares published **technical contracts**, not a fiscal rules
engine. For example, `cIndOp` and `gIBSCBS` depend on external official
tax-classification attributes. Anexo VIII correlations remain advisory-only.

## Activation and schema verification gate

The production documentation lists `NFSe-ESQUEMAS_XSD-v1.01-20260209`
(vendored unchanged at `references/schemas/nfse/1.01`).
Restricted production instead lists
`esquemas-nfse-rtc-v1-01-20260727.zip`. They share a numeric schema
version but not necessarily the same content or deployment semantics.

At review time (2026-10-08), the RTC portal published NT009 v1.01 but no
NT009-specific XSD package or confirmed SEFIN deployment/effective date.
The NT009 text points to a separately published rollout schedule. **Do not
enable or claim production/homologation conformity for the draft NT009
serialization until the relevant XSD and environment activation are verified.**

The current `DpsSchemaValidator` continues to accept the vendored production
February package only. The default `XmlBuilder::buildDps` and existing
Akaunting consumer continue using that contract.

## Reproduce the matrix

```sh
composer domains:install
composer domains:download -- --ids=annex-i-production,annex-vi-nt009 --output=/tmp/nfse-nt009
composer domains:contract-matrix -- --legacy-annex=/tmp/nfse-nt009/annex-i-production.xlsx --annex-vi=/tmp/nfse-nt009/annex-vi-nt009.xlsx --output=/tmp/nt009-matrix.tsv
diff -u resources/contracts/nt009-dps-v1.04.01-matrix.tsv /tmp/nt009-matrix.tsv
```

CI compares the complete matrix byte-for-byte with fresh downloads of the
checksum-pinned official workbooks. Network failures are reported by an
advisory job and cannot establish that the matrix passed; offline PHPUnit
covers the extraction algorithm independently.
