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


## Reproducible generation

The snapshots are generated directly from the three official XLSX annexes with only the Python
standard library:

```bash
python3 tools/generate_domain_tables.py \
  --annex-a /path/to/ANEXO_A.xlsx \
  --annex-b /path/to/ANEXO_B.xlsx \
  --annex-c /path/to/ANEXO_C.xlsx \
  --output resources/domains
```

Use `--check` to compare regenerated output with the committed snapshots. The command validates
the expected official cardinalities (5,571 municipalities/general localities, 250 countries,
338 national service codes, 918 nine-digit NBS codes and 26 operation indicators) before writing
or accepting output.

The generator reads XLSX as ZIP/XML, honors cell references and vertically merged cells, derives
UF only from the stable IBGE prefix map, and rejects an unexpected cardinality. A network-free CI
fixture exercises the same parser and proves deterministic output.
