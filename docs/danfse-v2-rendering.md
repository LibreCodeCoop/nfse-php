<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# DANFSe v2 rendering guarantees

The local DANFSe renderer follows the field/layout semantics implemented from
the National NFS-e DANFSe v2 / NT 008 work. Regression coverage intentionally
uses synthetic/public-schema data rather than copyrighted screenshots.

The stable contract covered by tests is:

- authorized NFS-e identity, access key and QR-code presence;
- provider, taker and intermediary identity fields;
- retained ISS labels and totals as authorized in the XML;
- foreign taker NIF without inventing a Brazilian CPF/CNPJ;
- 60-character summaries for national/municipal taxation descriptions;
- IBS/CBS block rendered only when the authorized XML contains it;
- IBS/CBS values displayed from authorized totals without recalculation;
- legacy authorized XML without IBS/CBS remains renderable;
- homologation documents have a visible environment marker;
- local generation returns a valid PDF artifact.

The project does not guarantee byte-identical PDF output. PDF metadata, font
metrics and QR SVG serialization can change when rendering dependencies are
updated. Reviews should therefore treat the named structural reference tests
as the baseline and intentionally inspect renderer changes that alter them.
