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

At re-verification (2026-10-09), the RTC portal published NT009 v1.01 but no
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

## Explicit opt-in preview (not an emitter)

The `XmlBuilder::buildDps(DpsData)` API and `DpsData` constructor remain
unchanged. Legacy callers continue to serialize using the production v1.01
contract, including their existing IBSCBS behavior. The new entry point
`XmlBuilder::previewNt009Dps(DpsData, Nt009DpsPreviewData)` returns a
`Nt009DpsPreview` object, **not** a string suitable for
`NfseClient::emit()`.

```php
use LibreCodeCoop\NfsePHP\Dto\Nt009DpsPreviewData;
use LibreCodeCoop\NfsePHP\Xml\XmlBuilder;

// $existingDps has no legacy ibsCbs* values set.
$draft = (new XmlBuilder())->previewNt009Dps(
    $existingDps,
    new Nt009DpsPreviewData(
        finalidade: 0,
        indicadorDestinatario: 0,
        codigoIndicadorOperacao: '010101',
        cst: '000',
        classificacaoTributaria: '000001',
        exigeGrupoIbsCbs: true, // from the official ind_gIBSCBS attribute
    ),
);
// $draft->xml is for comparison and review ONLY; do not transmit for issuance.
```

The preview currently covers the structural placement of `finNFSe`,
`tpNFSeDebito`, `tpNFSeCredito`, `indDest`, `regApIBSCBSSN`,
`IBSCBS/indFinal`, `cIndOp`, `IBSCBS/valores/trib/CST`,
`cClassTrib` and conditional `gIBSCBS`. It also models the
`gIBSCBSAjuste` paths and values for the adjustment types documented by
Annex VI. Both `CST` and `cClassTrib` are required whenever `IBSCBS/valores/trib`
is configured; `cIndOp` and `gIBSCBS` are forbidden if the externally
verified `ind_gIBSCBS` flag is false, and required in the appropriate
conditional form if it is true.

**The preview deliberately cannot model the complete 450-row new contract.**
It now includes explicitly typed, separately validated structural drafts for:

- `infDPS/dest` (Annex VI rows 180-200): one identity, optional national or
  foreign address; identifiers and textual values are validated before
  insertion, with DOM text nodes used for safe XML escaping.
- `infDPS/valores/vAjusteBC` (rows 293-336): exactly one ISSQN
  percentage, monetary or document-backed mode. The document mode accepts
  up to 1,000 typed `docAjusteBC` entries with a choice of
  `dFeNacional`, `docFiscalOutro` or `docOutro`, optional dates and
  explicit `fornec` identity. An optional externally justified
  `vAjusteBCIBSCBSComExt` may be provided; fiscal applicability is not
  inferred by the SDK.
- `IBSCBS/bensMoveis` (rows 404-407): up to 1,000 records, restricted to
  `cTribNac=99.04.01`, preserving the 8-digit NCM and bounded quantities.
- `IBSCBS/gPgtoVinc/pgto` (rows 443-449): up to 99 records, with distinct
  transaction IDs and payment numbers and the published payment method codes.
- `IBSCBS/imovel` (rows 383-403): explicit municipality, the
  classification-restricted optional `gLocacao`, and up to 99 units with
  bounded address and per-unit adjustment data.
- `IBSCBS/condominios` (rows 408-421): source-coded charge categories and
  optional detail and discount records, with exact-cent reconciliation of
  `gDetCobranca` to the charge and total charge values to `vServ`.

Additional optional, source-backed Annex VI groups are represented without
fiscal inference: `indZFMALC` (row 377, explicitly verified geography plus
published cIndOp whitelist), `tpOper`/`gRefNFSe` (rows 378-380, mandatory
reference for type 2/3), `tpEnteGov` (row 381, verified government purchase),
`indDoacao` (row 382, donation without consideration), `gTribRegular`
(rows 431-433) and `gDif` (rows 434-437). Existing callers are unaffected:
the `Nt009DpsPreviewData` constructor adds trailing optional arguments only.
The optional nested tax groups are rejected if the caller-verified
`ind_gIBSCBS` disallows `gIBSCBS`. Cross-domain applicability and
calculations remain caller responsibilities.

The preview also models `IBSCBS/valores/trib/gIBSCBS/gEstornoCred`
(Annex VI rows 438-440), containing both decimal `vIBSEstCred` and
`vCBSEstCred`. The caller must separately verify the official cClassTrib
attribute `ind_gEstornoCred`; it is never inferred from the classification
code or the advisory Annex VIII. A verified true flag requires both values,
while a false flag forbids the group. An omitted flag cannot authorize an
estorno. The optional `gPagAntecipado` (rows 441-442) accepts 1-99
50-character `refNFSe` references to previously issued advance-payment
invoices; no prior invoice is inferred or retrieved by this library. Both
groups remain review-only, with the same unresolved NT009 XSD gate.

The builder now rejects legacy `DpsData::deducaoReducao` in NT009 preview
instead of silently copying the obsolete `vDedRed` group. This prevents
mistaking old deduction rules for the new, not-yet-effective schema.
No taxpayer identity or payment values are inferred.

Still missing are independently confirmed adjustment-type tax-code
repercussions, externally verified tax-code correlations,
specialized conditional groups not yet modeled, and — most importantly —
validation against a published,
environment-specific **effective NT009 XSD**. None of these review-only
structures is enabled in the real emission path. The builder rejects
mixing legacy IBS fields and the new preview options. It removes the obsolete
`xsi:schemaLocation` reference, without claiming that it matches a new
schema. The existing `DpsSchemaValidator` remains bound to the February
production XSD and rejects this review-only structure. The current
`NfseClient` and its emission path are **not wired to the preview**.

The SDK intentionally does not calculate IBS/CBS or infer tax classification
from `cClassTrib`. Callers must supply `ind_gIBSCBS` from the authoritative
classification attributes, not consultative Annex VIII. The preview accepts
an externally verified boolean, but that is **not proof of actual fiscal
acceptance** by the SEFIN API.

## Downstream handoff: LibreCodeCoop/akaunting-nfse

The consumer's `3rdparty/composer.json` currently pins
`librecodeoop/nfse-php` to
`dev-main#f179d7f723bec80f5b19a25dfbc385a612c2447f`. It uses
`Application/RuntimeDpsFactory.php` to reflect the existing `DpsData`
constructor, maps all present legacy IBS/CBS fields in
`Application/InvoiceDpsBuilder.php`, and calls `NfseClient::emit(DpsData)`
from `Application/IssueInvoiceNfse.php`.

No constructor parameter, default legacy serializer or emission interface
changed here. Consequently the consumer **requires no immediate update**
to remain on its existing fiscal contract. Adopting NT009 for fiscal emission
requires a separate downstream RTC integration that first verifies official
schema deployment, updates the scoped Composer pin to a reviewed **merged
commit or tagged release**, adds a compatible version-selector or emitter,
and retests the module's emission and recovery flows with the applicable
staging environment. Do not pin this unmerged PR's SHA in the consumer.


## Post-PR #101 published-layout guards (review only)

The NT009 preview additionally checks the exact published adjustment type
domain (Annex VI row 298), the conditional descriptions for types 99/199
(row 299), and the strict \`vAjusteAplic <= vTotDoc\` constraint (row 301).
The amount comparison uses decimal strings as integer cents; it never rounds
or converts fiscal amounts to floating point or platform integers. DF-e
reference types are limited to 1/2/3/9, with \`xTipoChaveDFe\` permitted only
for 9 (rows 305-306). Property adjustment types are limited to
01/02/03/04/99, with descriptions permitted for 99 (rows 401-402).
The independently optional export IBS/CBS adjustment (row 336) can appear
without an unrelated ISSQN adjustment (rows 294-296).

These are source-supported **structural** constraints only. They do not
implement municipal eligibility, taxpayer classification, the official
adjustment-repercussion matrix or downstream emission. In particular, no
restrictions are inferred from consultative Annex VIII, and the preview
remains non-emitting until an applicable officially effective XSD, environment
activation and SEFIN acceptance tests have been confirmed.

## Outstanding normative gates

1. Obtain authoritative NT009-compatible XSD packages for each intended
   environment, record byte-level hashes and compare against the existing
   February production and July restricted-production packages.
2. Verify public activation dates and SEFIN acceptance behavior. Annex VI
   publication alone is not activation.
3. Resolve implementation of the complete revised DPS contract, including
   its new or moved optional and conditional groups, and verify those cases
   against the actual new XSD.
4. Confirm CST/cClassTrib and `ind_gIBSCBS` attributes from authoritative
   tax classifications, not Annex VIII consultation. Validate code
   applicability to NFS-e before restricting domains.
5. Only after those verifications, implement an approved version-specific
   **emitter**, fixtures and end-to-end acceptance tests for the downstream
   consumer. Keep the current production emitter as its default until
   official applicability is established.

**Issue #95 must remain open.** Offline tests and a structural preview prove
the library can express part of NT009; they do not establish fiscal conformity.

## Official XSD ZIP provenance

Two distinct v1.01 packages have now been independently downloaded and
their archive bytes recorded:

| Environment | Published archive | Observed SHA-256 |
| --- | --- | --- |
| Production | `nfse-esquemas_xsd-v1-01-20260209.zip` | `e7935cbd9470527c6cc32984c1b2263e614183bf0139ce2733eaaed2de9a8072` |
| Restricted production | `esquemas-nfse-rtc-v1-01-20260727.zip` | `6c7e0510d3ecff4454f291f4e10b742d27a4818f23aab181494f96d0ea79f3dc` |

The `schema-packages` PHP command downloads only these pinned official
manifest URLs, validates both ZIP hashes and compares the hash of each XSD
by its full relative ZIP path and by basename (allowing repeated basenames
in separate folders). Raw ZIP metadata differences do not establish
semantic differences between equivalent XSD files; the per-file hashes
are the evidence for actual schema-byte differences. Run:

```sh
composer domains:install
php tools/bin/domains.php schema-packages --report=/tmp/nfse-xsd-comparison.json
```

These packages cannot independently prove an NT009 v1.04.01 rollout, which
is a separate contractual/effective-date gate.

## Downstream adoption boundary

`LibreCodeCoop/akaunting-nfse` currently pins
`librecodeoop/nfse-php: dev-main#f179d7f723bec80f5b19a25dfbc385a612c2447f`
in `3rdparty/composer.json`. The opt-in preview extends the constructor with
optional trailing parameters, leaving the current `DpsData` constructor,
`XmlBuilder::buildDps(DpsData)`, `DpsSchemaValidator` and
`NfseClient::emit()` unchanged. Existing consumers need no migration for
ordinary emission. **No NT009 commit should replace the consumer pin** until
an effective schema, correct environment and successful SEFIN contract tests
are evidenced. This library makes no downstream UI/persistence changes.

## Cross-publication discrepancies requiring official confirmation

The live, checksum-pinned XSD ZIP comparison on 2026-10-08 (Brazil local
date) reports **10 like-named XSDs with different contents** and **7 XSD
basenames found only in the production archive**. This compares extracted XSD
file bytes, not just ZIP metadata or folder prefixes, so a shared `v1.01`
label is insufficient to select a compatible schema. The exact report is
retained as `nt009-xsd-packages.json` in the NT009 Source Evidence job.
**Neither compared archive establishes the October NT009 v1.04.01 contract
as deployed.**

There is also a documentary discrepancy concerning `gIBSCBSAjuste`.
The NT009 v1.01 **PDF**, section 2.2 (page 4), shows the adjustment group
directly below `IBSCBS/valores/trib`, while the subsequently published
**Anexo VI v1.04.01** workbook identifies its parent as
`IBSCBS/valores/trib/gIBSCBS` (original layout row 428). The review-only
preview currently follows the later spreadsheet; **it must not be used for
fiscal submission** until an applicable XSD and official validation contract
resolve that disagreement. Do not silently infer a definitive parent path.

Source PDF: https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc/nota-tecnica-009-se-cgnfs-e-v-1-01.pdf

Review status: code and document extraction are verifiable, but the live
production schema/effective-date evidence is **not yet available**. This
blocks a conformant final implementation and closure of issue #95.
