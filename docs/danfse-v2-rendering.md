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
- retained ISS labels from the DPS and municipal base/rate/assessed tax from the authorized `infNFSe/valores` (not the DPS `tribMun`);
- foreign taker NIF without inventing a Brazilian CPF/CNPJ;
- 60-character taxation-description content with an ellipsis suffix when truncated;
- IBS/CBS block rendered only when the authorized XML contains it;
- IBS/CBS values displayed from authorized totals without recalculation;
- legacy authorized XML without IBS/CBS remains renderable;
- homologation documents have a visible environment marker;
- local generation returns a valid PDF artifact;
- line breaks in the authorized service description are preserved as escaped HTML line breaks;
- optional contact complement and IBGE/CEP follow the authorized XML without inventing missing data;
- the reference footer never fabricates an acknowledgement date or human signature.

The project does not guarantee byte-identical PDF output. PDF metadata, font
metrics and QR SVG serialization can change when rendering dependencies are
updated. Reviews should therefore treat the named structural reference tests
as the baseline and intentionally inspect renderer changes that alter them.

## National portal visual reference

The default local DANFSe renderer follows the visual hierarchy of the national
portal's DANFSe v2: compact Arial typography, pale gray header, gray section
cells, four-column tax tables, and a receipt stub aligned to the A4 footer.
This is a visual convention, not a byte-exact or official portal rendering.

The standard header includes the NFS-e project's horizontal logo. The asset is
bundled as a normal `src/Danfse/Assets/nfse-horizontal.png` file for offline Dompdf generation, with a
separate CC-BY-ND-3.0 attribution file. Official source:
https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/logos-da-nfs-e
The image remains directly viewable on disk and is encoded in the temporary HTML only during PDF rendering, not stored as base64 in the repository. The asset is required for the default renderer: missing assets fail explicitly rather than silently removing the logo. Caller-provided logos override the default without editing the core renderer.

All monetary values and document identity continue to come only from authorized
NFS-e XML; the layout neither recalculates taxes nor invents absent fiscal
data. Homologation warnings remain visible. No receipt date or signature
is invented. The footer reserves bottom page space, including when a lengthy
description flows onto additional pages.
