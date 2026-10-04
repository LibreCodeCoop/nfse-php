#!/usr/bin/env python3
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

"""Generate normalized NFS-e domain TSV snapshots directly from official XLSX annexes."""

from __future__ import annotations

import argparse
import re
import sys
import tempfile
import unicodedata
import xml.etree.ElementTree as ET
import zipfile
from pathlib import Path

MAIN_NS = "http://schemas.openxmlformats.org/spreadsheetml/2006/main"
REL_NS = "http://schemas.openxmlformats.org/officeDocument/2006/relationships"
PKG_REL_NS = "http://schemas.openxmlformats.org/package/2006/relationships"

UF_BY_IBGE_PREFIX = {
    "11": "RO", "12": "AC", "13": "AM", "14": "RR", "15": "PA", "16": "AP", "17": "TO",
    "21": "MA", "22": "PI", "23": "CE", "24": "RN", "25": "PB", "26": "PE", "27": "AL",
    "28": "SE", "29": "BA", "31": "MG", "32": "ES", "33": "RJ", "35": "SP", "41": "PR",
    "42": "SC", "43": "RS", "50": "MS", "51": "MT", "52": "GO", "53": "DF",
}

EXPECTED_COUNTS = {
    "municipios-ibge-v1.00.tsv": 5571,
    "paises-iso2-v1.00.tsv": 250,
    "servicos-nacionais-v1.01.tsv": 338,
    "nbs-v2.0.tsv": 918,
    "indicadores-operacao-ibscbs-v1.01.tsv": 26,
}

HEADERS = {
    "municipios-ibge-v1.00.tsv": [
        "# Municípios IBGE — ANEXO_A-MUNICIPIO_IBGE-PAISES_ISO2-v1.00-SNNFSe-20251210",
        "# Formato: código IBGE<TAB>sigla UF<TAB>nome do município. UF vazia = localidade geral.",
        "# A sigla é derivada dos 2 primeiros dígitos do código (padrão IBGE): o anexo só a",
        "# preenche em 450 das 5570 linhas. A derivação foi conferida contra essas 450, contra",
        "# o enum TSUF do XSD e contra o conjunto de prefixos presentes no anexo.",
    ],
    "paises-iso2-v1.00.tsv": [
        "# Países ISO 3166-1 alpha-2 — ANEXO_A-MUNICIPIO_IBGE-PAISES_ISO2-v1.00-SNNFSe-20251210",
        "# Formato: código ISO2<TAB>nome",
    ],
    "servicos-nacionais-v1.01.tsv": [
        "# Lista de Serviços Nacional — ANEXO_B-NBS2-LISTA_SERVICO_NACIONAL-SNNFSe-v1.01-20260122",
        "# Gerado de doc/anexos/, aba LISTA.SERV.NAC. Formato: cTribNac<TAB>descrição",
    ],
    "nbs-v2.0.tsv": [
        "# Nomenclatura Brasileira de Serviços 2.0 — ANEXO_B-NBS2-LISTA_SERVICO_NACIONAL-SNNFSe-v1.01-20260122",
        "# Aba LISTA.NBS_v2.0. Formato: cNBS (9 dígitos, sem pontos)<TAB>descrição.",
        "# Das 1210 linhas do anexo, 292 são níveis de hierarquia (5, 6, 7 e 8 dígitos) e não",
        "# são valores válidos de cNBS, que o XSD tipa como [0-9]{9}.",
        "# 999999999 vem sem descrição no anexo; é o código genérico, e é o que a NFS-e real",
        "# consultada em produção usa. Mantido com descrição vazia — não se inventa texto.",
    ],
    "indicadores-operacao-ibscbs-v1.01.tsv": [
        "# Indicadores de operação IBS/CBS — ANEXO_C-INDOP_IBSCBS-SNNFSe-v1.01-20260122",
        "# Formato: cIndOp<TAB>característica do fornecimento<TAB>local a identificar no DF-e",
    ],
}


def normalized_sheet_name(value: str) -> str:
    decomposed = unicodedata.normalize("NFKD", value)
    return "".join(ch for ch in decomposed if not unicodedata.combining(ch)).upper().strip()


def column(ref: str) -> str:
    match = re.match(r"([A-Z]+)", ref)
    return match.group(1) if match else ""


def clean(value: str) -> str:
    return re.sub(r"\s+", " ", value.replace("\t", " ")).strip()


def digits(value: str) -> str:
    return re.sub(r"\D+", "", value)


class Workbook:
    def __init__(self, path: Path):
        self.path = path
        self.zip = zipfile.ZipFile(path)
        self.shared_strings = self._read_shared_strings()
        self.sheet_targets = self._read_sheet_targets()

    def close(self) -> None:
        self.zip.close()

    def __enter__(self) -> "Workbook":
        return self

    def __exit__(self, *_args: object) -> None:
        self.close()

    def _read_shared_strings(self) -> list[str]:
        try:
            raw = self.zip.read("xl/sharedStrings.xml")
        except KeyError:
            return []
        root = ET.fromstring(raw)
        values = []
        for item in root.findall(f"{{{MAIN_NS}}}si"):
            values.append("".join(node.text or "" for node in item.iter(f"{{{MAIN_NS}}}t")))
        return values

    def _read_sheet_targets(self) -> dict[str, str]:
        workbook = ET.fromstring(self.zip.read("xl/workbook.xml"))
        rels = ET.fromstring(self.zip.read("xl/_rels/workbook.xml.rels"))
        targets = {
            rel.attrib["Id"]: rel.attrib["Target"]
            for rel in rels.findall(f"{{{PKG_REL_NS}}}Relationship")
        }
        sheets: dict[str, str] = {}
        for sheet in workbook.findall(f".//{{{MAIN_NS}}}sheet"):
            rel_id = sheet.attrib[f"{{{REL_NS}}}id"]
            target = targets[rel_id]
            if not target.startswith("/"):
                target = "xl/" + target.lstrip("/")
            else:
                target = target.lstrip("/")
            sheets[normalized_sheet_name(sheet.attrib["name"])] = target
        return sheets

    def rows(self, sheet_name: str) -> list[dict[str, str]]:
        key = normalized_sheet_name(sheet_name)
        if key not in self.sheet_targets:
            raise ValueError(f"worksheet not found: {sheet_name}")
        root = ET.fromstring(self.zip.read(self.sheet_targets[key]))
        rows: list[dict[str, str]] = []
        by_number: dict[int, dict[str, str]] = {}
        for row in root.findall(f".//{{{MAIN_NS}}}row"):
            number = int(row.attrib.get("r", len(rows) + 1))
            values: dict[str, str] = {}
            for cell in row.findall(f"{{{MAIN_NS}}}c"):
                ref = cell.attrib.get("r", "")
                col = column(ref)
                cell_type = cell.attrib.get("t", "")
                value = ""
                if cell_type == "inlineStr":
                    value = "".join(node.text or "" for node in cell.iter(f"{{{MAIN_NS}}}t"))
                else:
                    node = cell.find(f"{{{MAIN_NS}}}v")
                    raw = node.text if node is not None and node.text is not None else ""
                    if cell_type == "s" and raw != "":
                        value = self.shared_strings[int(raw)]
                    else:
                        value = raw
                values[col] = clean(value)
            rows.append(values)
            by_number[number] = values

        # Preserve merged-cell semantics used by the official annexes.
        for merged in root.findall(f".//{{{MAIN_NS}}}mergeCell"):
            ref = merged.attrib.get("ref", "")
            match = re.fullmatch(r"([A-Z]+)(\d+):([A-Z]+)(\d+)", ref)
            if not match:
                continue
            start_col, start_row, end_col, end_row = match.groups()
            if start_col != end_col:
                continue
            start = int(start_row)
            end = int(end_row)
            source = by_number.get(start, {}).get(start_col, "")
            if source == "":
                continue
            for number in range(start, end + 1):
                by_number.setdefault(number, {}).setdefault(start_col, source)

        return rows


def longest_text(values: list[str], excluded: set[str]) -> str:
    candidates = [
        clean(value) for value in values
        if clean(value) not in excluded
        and clean(value) != ""
        and not re.fullmatch(r"[\d.\-/]+", clean(value))
    ]
    return max(candidates, key=len, default="")


def extract_municipalities(book: Workbook) -> list[list[str]]:
    rows: list[list[str]] = []
    seen: set[str] = set()
    for row in book.rows("TAB.MUN_IBGE"):
        code = digits(row.get("D", ""))
        name = clean(row.get("C", ""))
        if len(code) != 7 or name == "" or code in seen:
            continue
        prefix = code[:2]
        if prefix not in UF_BY_IBGE_PREFIX:
            raise ValueError(f"unknown IBGE state prefix: {prefix}")
        rows.append([code, UF_BY_IBGE_PREFIX[prefix], name])
        seen.add(code)

    # The annex publishes a separate general-locality sheet (e.g. maritime waters).
    for row in book.rows("TAB.LOC.GERAL"):
        values = list(row.values())
        for value in values:
            code = digits(value)
            if len(code) != 7 or code in seen:
                continue
            name = longest_text(values, {value})
            if name:
                rows.append([code, "", name])
                seen.add(code)
    return rows


def extract_countries(book: Workbook) -> list[list[str]]:
    rows: list[list[str]] = []
    seen: set[str] = set()
    for row in book.rows("TAB.PAÍS_ISO2"):
        values = [clean(value) for value in row.values()]
        code = next((value for value in values if re.fullmatch(r"[A-Z]{2}", value)), "")
        if code == "" or code in seen:
            continue
        name = longest_text(values, {code})
        if name:
            rows.append([code, name])
            seen.add(code)
    return rows


def extract_code_description(book: Workbook, sheet: str, length: int) -> list[list[str]]:
    rows: list[list[str]] = []
    seen: set[str] = set()
    for row in book.rows(sheet):
        values = [clean(value) for value in row.values()]
        code_value = next((value for value in values if len(digits(value)) == length), "")
        if code_value == "":
            continue
        code = digits(code_value)
        if code in seen:
            continue
        description = longest_text(values, {code_value, code})
        if description == "" and code != "999999999":
            continue
        rows.append([code, description])
        seen.add(code)
    return rows


def extract_indicators(book: Workbook) -> list[list[str]]:
    rows: list[list[str]] = []
    seen: set[str] = set()
    for sheet in book.sheet_targets:
        for row in book.rows(sheet):
            values = [clean(value) for value in row.values()]
            code_value = next(
                (value for value in values if re.fullmatch(r"\d{6}", digits(value))),
                "",
            )
            if code_value == "":
                continue
            code = digits(code_value)
            if code in seen:
                continue
            texts = [
                value for value in values
                if value not in {code_value, code}
                and value != ""
                and not re.fullmatch(r"[\d.\-/]+", value)
            ]
            if len(texts) < 2:
                continue
            # Characteristic is normally the longer prose field; location is the other.
            ranked = sorted(texts, key=len, reverse=True)
            characteristic = ranked[0]
            location = ranked[1]
            rows.append([code, characteristic, location])
            seen.add(code)
    return rows


def render(name: str, rows: list[list[str]]) -> str:
    body = ["\t".join(row) for row in rows]
    return "\n".join(HEADERS[name] + body) + "\n"


def generate(annex_a: Path, annex_b: Path, annex_c: Path) -> dict[str, str]:
    with Workbook(annex_a) as book_a, Workbook(annex_b) as book_b, Workbook(annex_c) as book_c:
        outputs = {
            "municipios-ibge-v1.00.tsv": render("municipios-ibge-v1.00.tsv", extract_municipalities(book_a)),
            "paises-iso2-v1.00.tsv": render("paises-iso2-v1.00.tsv", extract_countries(book_a)),
            "servicos-nacionais-v1.01.tsv": render(
                "servicos-nacionais-v1.01.tsv",
                extract_code_description(book_b, "LISTA.SERV.NAC.", 6),
            ),
            "nbs-v2.0.tsv": render(
                "nbs-v2.0.tsv",
                extract_code_description(book_b, "LISTA.NBS_v2.0", 9),
            ),
            "indicadores-operacao-ibscbs-v1.01.tsv": render(
                "indicadores-operacao-ibscbs-v1.01.tsv",
                extract_indicators(book_c),
            ),
        }
    return outputs


def validate_counts(outputs: dict[str, str]) -> None:
    for name, expected in EXPECTED_COUNTS.items():
        actual = sum(1 for line in outputs[name].splitlines() if line and not line.startswith("#"))
        if actual != expected:
            raise ValueError(f"{name}: expected {expected} data rows, generated {actual}")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--annex-a", required=True, type=Path)
    parser.add_argument("--annex-b", required=True, type=Path)
    parser.add_argument("--annex-c", required=True, type=Path)
    parser.add_argument("--output", required=True, type=Path)
    parser.add_argument("--check", action="store_true", help="fail if generated output differs from existing files")
    parser.add_argument("--skip-cardinality-check", action="store_true", help=argparse.SUPPRESS)
    args = parser.parse_args()

    outputs = generate(args.annex_a, args.annex_b, args.annex_c)
    if not args.skip_cardinality_check:
        validate_counts(outputs)

    args.output.mkdir(parents=True, exist_ok=True)
    failures = []
    for name, content in outputs.items():
        path = args.output / name
        if args.check:
            current = path.read_text(encoding="utf-8") if path.exists() else ""
            if current != content:
                failures.append(name)
        else:
            path.write_text(content, encoding="utf-8")

    if failures:
        print("Domain snapshots differ: " + ", ".join(failures), file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
