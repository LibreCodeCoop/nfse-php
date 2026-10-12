<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Bundled Liberation Sans font assets

The bundled TTF name tables identify Liberation Sans version 2.1.5
and SIL OFL 1.1. Their exact SHA-256 fingerprints are recorded below.
The official upstream binary release is available at:
https://github.com/liberationfonts/liberation-fonts/releases/tag/2.1.5

MacPorts independently records SHA-256
`7191c669bf38899f73a2094ed00f7b800553364f90e2637010a69c0e268f25d0`
for the complete upstream `liberation-fonts-ttf-2.1.5.tar.gz` archive:
https://github.com/macports/macports-ports/blob/master/x11/liberation-fonts/Portfile

The above *archive* checksum has not been directly compared with the
bundled font binaries in this repository; do not mistake the recorded
individual-file hashes and internal metadata for proof of byte-for-byte
correspondence with the upstream archive.

| File | SHA-256 |
| --- | --- |
| `LiberationSans-Regular.ttf` | `76d04c18ea243f426b7de1f3ad208e927008f961dc5945e5aad352d0dfde8ee8` |
| `LiberationSans-Bold.ttf` | `788abee4c806d660e8aee46689dd8540cd4bb98da03dcc9d171ce3efd99a9173` |

Font copyright: Google Corporation (2010) and Red Hat, Inc. (2012).
Licensed under **SIL Open Font License 1.1 (SPDX: OFL-1.1)**, with
reserved font names **Arimo, Tinos, Cousine** and **Liberation**.
See `LICENSES/OFL-1.1.txt`, distributed with the Composer package.

The PDF documents are locally generated without runtime font downloads.
The fonts are not Microsoft font files. NT008 v1.02 explicitly names
Arial and Microsoft Sans Serif; substitution is not nominal compliance.
