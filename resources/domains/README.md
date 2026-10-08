<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Versioned NFS-e domain tables

These offline TSV snapshots normalize the production domain annexes currently listed by the
Sistema Nacional NFS-e portal. Runtime validation never scrapes the portal.

| Snapshot | Official source | Version | Mirror workbook Git blob |
| --- | --- | --- | --- |
| Municipalities / countries | https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/documentacao-atual/anexo_a-municipio_ibge-paises_iso2-v1-00-snnfse-20251210.xlsx | ANEXO_A v1.00 (20251210) | `8fd9a9cf85e36352966ff7f9052b7136908fc431` |
| NBS / national service list | https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/documentacao-atual/anexo_b-nbs2-lista_servico_nacional-snnfse-v1-01-20260122.xlsx | ANEXO_B v1.01 (20260122) | `a2eab7f0e2fa54a7f22bce0ad29b1b7b751f64d1` |
| IBS/CBS operation indicators | https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/documentacao-atual/anexo-c-indop-ibscbs-snnfse-v1-01.xlsx | ANEXO_C v1.01 (20260122) | `d4eafbdf4d1d58f846999db290b12715e0cd33bb` |

The workbook mirror is the public `stackin-io/data-source` repository. The normalized TSVs
were cross-checked against the independently generated tables in
`rafael-negrao/nfse-nacional-service`.

## RTC correlation table

The Portal NFS-e currently describes **Anexo VIII v1.01.00** (Item de Serviço × NBS ×
`cClassTrib` × `cIndOp`) as an initial work in progress and explicitly states that no
business rules in Production or the RTC pilot are based on it.

For that reason this package does **not** reject fiscal data based on Anexo VIII. A future
advisory correlation helper may expose it for suggestions, but normative validation must wait
for the portal to make the correlation contract authoritative.

## Updating

When an official annex changes:

1. record the exact official workbook URL and published version;
2. retain the source workbook checksum/blob metadata used for extraction;
3. regenerate the corresponding TSV without inventing missing descriptions or codes;
4. update the version constant in `OfficialDomainCatalog`;
5. update cardinality and representative-code tests;
6. review downstream mapping changes explicitly instead of silently coercing old values.


## PHP tooling (isolated)

The PHP SDK loads committed TSV files only; it never needs to read XLSX or access government
websites at runtime. The annex parser, PHPUnit suite and PhpSpreadsheet live in
`vendor-bin/domains/`, with a separate Composer manifest and lockfile. The root
`composer.json` contains *commands*, not a PhpSpreadsheet dependency.

Install the isolated tooling (PHP 8.2+, plus PhpSpreadsheet's extensions including zip and gd):

```sh
composer domains:install
```

Generate from exact, reviewed official workbooks:

```sh
composer domains:generate -- --annex-a=/path/to/ANEXO_A.xlsx --annex-b=/path/to/ANEXO_B.xlsx --annex-c=/path/to/ANEXO_C.xlsx
composer domains:check -- --annex-a=/path/to/ANEXO_A.xlsx --annex-b=/path/to/ANEXO_B.xlsx --annex-c=/path/to/ANEXO_C.xlsx
composer domains:test
```

For Annex VII v1.03.00, also pass `--annex-vii=/path/to/ANEXO_VII.xlsx` and
`--expected-vii=N`, where N is an **independently verified** cardinality from the
official workbook. The new file is written under its own versioned filename; no
existing production catalog or XSD is silently replaced.

The generator preserves the existing headers and row order of the current TSVs.
For the official INDOP sheet the mapping is explicit: F = supply characteristic,
G = cIndOp, H = location. If an annex changes its physical columns, the generator
must fail pending manual examination; never guess which description belongs to a code.

## Government source monitoring

`resources/domains/sources.json` records official portal URLs, version, reported environment
and the available **observed** SHA-256 values. They are not official digital signatures.
Two older source baselines have not yet been verified and are intentionally null.
The scheduled source-watch GitHub workflow downloads these exact public URLs, rejects
HTML masquerading as an XLSX and reports byte-level differences. A changed hash
is **not** proof of changed fiscal semantics or production activation.

```sh
composer domains:watch
composer domains:audit -- --annex-vi=/path/to/ANEXO_VI.xlsx --annex-vii=/path/to/ANEXO_VII.xlsx
```

The audit command checks source bytes against the recorded Annex VI/VII October 2026
observations. A changed source must be manually reviewed and the manifest updated.
The monitor never commits, opens issues, or modifies production data. The advisory
Annex VIII correlation remains non-enforcing.

No runtime or CI test reaches out to government sources; monitoring is a separate,
scheduled workflow. The `tools/` directory is written in PHP only.
