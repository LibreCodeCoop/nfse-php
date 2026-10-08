<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# NT009 v1.01: inspect published contracts without activating them

The official [RTC portal](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc)
publishes **Anexo VI v1.04.01** (DPS/NFS-e layout and fiscal business rules)
and **Anexo VII v1.03.00** (IBS/CBS operation indicators). The publication
does not by itself establish a deployed production schema or effective date.

A strictly read-only source review uses PHP/PhpSpreadsheet from the isolated
`vendor-bin/domains` Composer project:

```sh
composer domains:install
composer domains:audit -- --download-dir=/tmp/nfse-nt009 --report=/tmp/nt009-report.json
# Or use previously downloaded, independently verified official files:
composer domains:audit -- --annex-vi=/path/to/VI.xlsx --annex-vii=/path/to/VII.xlsx --report=/tmp/nt009-report.json
```

The audit first validates both workbook bytes against SHA-256 values independently
observed on **2026-10-05**, not government-issued signatures. It then reads
the layout worksheet rows with literal field names (ignoring, not evaluating,
spreadsheet helper formulas) and compares the complete
indicator-code/description/location triplets with the existing Anexo C v1.01
snapshot. Both known official indicator worksheet names (`INDOP` and `cIndOp Public`) are
supported; unknown sheet names fail explicitly. The JSON report lists additions, removals and text changes, as well
as original cell coordinates for relevant NT009 layout entries.

**Interpretation boundary:** exported raw layout rows do *not* automatically
encode complex field conditions, business-rule activation or authorization by
the SEFIN API. The output is audit evidence, not a replacement XSD or a
compliance declaration. Neither Anexo VIII consultative correlations nor new
NT009 fields are activated in production from this tool. The existing
`DpsData`, `XmlBuilder`, `OfficialDomainCatalog` and XSD validators remain
unchanged until a separately reviewed implementation validates the full
normative conditions and deployment dates.

If official sources cannot be downloaded, the auxiliary evidence GitHub
workflow may be inconclusive; the offline PHPUnit suite remains deterministic.
