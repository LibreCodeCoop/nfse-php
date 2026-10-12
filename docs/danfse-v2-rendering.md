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
- header municipality and UF only from `infNFSe/xLocEmi` and `emit/enderNac/UF`, suppressed for national taxation item 99; environment identification remains visible;
- provider, taker and intermediary identity fields;
- retained ISS labels from the DPS and municipal base/rate/assessed tax from the authorized `infNFSe/valores` (not the DPS `tribMun`);
- municipal special regime codes `0` (Nenhum), `9` (Outros) and missing values retain distinct presentation; the two optional municipal rows are omitted only if every field of that row is absent, and a present numeric zero is displayed;
- foreign taker NIF without inventing a Brazilian CPF/CNPJ;
- 60-character taxation-description content with an ellipsis suffix when truncated;
- IBS/CBS block rendered only when the authorized XML contains it;
- IBS/CBS values displayed from authorized totals without recalculation;
- legacy authorized XML without IBS/CBS remains renderable;
- homologation documents have a visible environment marker;
- cancelled and substituted documents may show the required 50pt diagonal status watermark when the consuming application supplies an **explicit, externally verified event state** through `DanfseConfig::fiscalState`; the original authorized XML alone does not establish that a subsequent cancel/substitution occurred and is not mutated;
- local generation returns a valid single-page A4 portrait PDF artifact; overflowing optional free text uses visible ellipses as permitted by NT 008 (item 2.1), without modifying the XML, the fiscal fields or mandatory tax disclosure. Layouts that still cannot fit fail explicitly instead of returning a nonconforming multi-page PDF;
- line breaks in the authorized service description are preserved as escaped HTML line breaks;
- the supplementary text follows NT 008's field order and pipe separators (including substitution, service references, construction, events and municipality information) when they exist in the authorized XML; estimated tax disclosure accepts explicitly supplied monetary **or** percentage values, preserving missing versus numeric zero;
- optional contact complement and IBGE/CEP follow the authorized XML without inventing missing data;
- the reference footer never fabricates an acknowledgement date or human signature.

## Locally licensed typography

NT 008 requires **Arial** for labels and **Microsoft Sans Serif** for values.
CSS family names alone do not satisfy this: Dompdf otherwise falls back to
Helvetica. The library cannot redistribute those proprietary font binaries.

A caller holding suitable licenses can provide local TrueType files using
`DanfseConfig(fonts: new DanfseFonts($arialRegular, $arialBold, $microsoftSansSerif))`.
Files are validated when the options are built, used only from local paths
within Dompdf's explicitly restricted chroot, and never loaded remotely or
serialized as font bytes in the HTML. Files must permit PDF embedding under
their own license; consumers must provide a writable Dompdf font cache.

An A4 PDF should be inspected with `pdffonts receipt.pdf`; exact typography
is demonstrated only when the correct typefaces are present and embedded.
The default compatibility path may still fall back to Helvetica and must not
be claimed to prove complete NT 008 font compliance. CI exercises local-font
embedding with Dompdf's freely licensed DejaVuSans **only as a test surrogate**.

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
is invented. The footer reserves bottom page space. Long free-text fields are progressively abbreviated with visible ellipses when needed to keep the DANFSe on one page; if mandatory content cannot fit, generation fails rather than producing additional pages.
