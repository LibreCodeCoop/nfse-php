#!/usr/bin/env python3
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

"""Audit the exact NT009 XLSX inputs before changing offline tax domains.

Run manually; never fetch external data from application runtime.
The hashes are independent download observations recorded on 2026-10-05,
not a published governmental checksum. Reconfirm against the official URLs.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import tempfile
import time
import urllib.request
from pathlib import Path

from generate_domain_tables import Workbook

SOURCES = {
    "annex_vi": (
        "https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc/"
        "anexovi-leiautesrn_rtc_ibscbs-v1-04-01-nt009.xlsx",
        "103a150dd6f56ec8edcaa3a453b59a1b0e096d7cd5025d7bd06a387f141ddf39",
    ),
    "annex_vii": (
        "https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc/"
        "anexovii-indop_ibscbs_v1-03-00-nt009.xlsx",
        "95f30a44ee94adeea57fb92f3a0337b1e62fdc106f393e73894087889be9e5aa",
    ),
}


def retrieve(url: str, destination: Path) -> bytes:
    request = urllib.request.Request(
        url, headers={"User-Agent": "LibreCodeCoop/nfse-php normative source audit"}
    )
    for attempt in range(4):
        try:
            with urllib.request.urlopen(request, timeout=45) as response:
                payload = response.read()
            destination.write_bytes(payload)
            return payload
        except (OSError, TimeoutError):
            if attempt == 3:
                raise
            time.sleep(2 ** attempt)
    raise RuntimeError("unreachable")


def inspect_annex(name: str, path: Path, dump: bool) -> None:
    with Workbook(path) as book:
        print(f"{name}: sheets = {list(book.sheet_targets)}", flush=True)
        if not dump:
            return
        for sheet in book.sheet_targets:
            if name == "annex_vi" and "LEIAUTE DPS" not in sheet:
                continue
            rows = book.rows(sheet)
            for number, cells in enumerate(rows, 1):
                joined = " ".join(cells.values())
                if name == "annex_vii":
                    if not any(re.fullmatch(r"[0-9]{6}", re.sub(r"\D", "", val)) for val in cells.values()):
                        if number > 12:
                            continue
                elif not any(marker in joined for marker in (
                    "IBSCBS", "indDest", "finNFSe", "tpNFSeDebito",
                    "tpNFSeCredito", "gPgtoVinc", "regApIBSCBSSN",
                    "CST", "cClassTrib",
                )):
                    continue
                print(f"{name}:{sheet}:{number}: {json.dumps(cells, ensure_ascii=False)}", flush=True)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--inspect", action="store_true", help="Print source rows for manual normative review")
    parser.add_argument("--output-dir", type=Path, help="Keep exact verified XLSX files here")
    args = parser.parse_args()

    def audit(destination: Path) -> None:
        for name, (url, expected) in SOURCES.items():
            path = destination / f"{name}.xlsx"
            payload = retrieve(url, path)
            actual = hashlib.sha256(payload).hexdigest()
            print(f"{name}: {url} sha256={actual} bytes={len(payload)}", flush=True)
            if actual != expected:
                raise ValueError(f"{name}: source checksum differs from recorded observation {expected}")
            inspect_annex(name, path, args.inspect)

    if args.output_dir:
        args.output_dir.mkdir(parents=True, exist_ok=True)
        audit(args.output_dir)
    else:
        with tempfile.TemporaryDirectory() as temporary:
            audit(Path(temporary))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
