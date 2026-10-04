#!/usr/bin/env python3
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

from __future__ import annotations

import importlib.util
import tempfile
import unittest
import zipfile
from pathlib import Path

SCRIPT = Path(__file__).resolve().parents[1] / "generate_domain_tables.py"
SPEC = importlib.util.spec_from_file_location("domain_generator", SCRIPT)
assert SPEC and SPEC.loader
GEN = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(GEN)


def col(index: int) -> str:
    out = ""
    while index:
        index, rem = divmod(index - 1, 26)
        out = chr(65 + rem) + out
    return out


def workbook(path: Path, sheets: dict[str, list[list[str]]], merges: dict[str, list[str]] | None = None) -> None:
    merges = merges or {}
    shared: list[str] = []
    index: dict[str, int] = {}

    def shared_id(value: str) -> int:
        if value not in index:
            index[value] = len(shared)
            shared.append(value)
        return index[value]

    workbook_xml = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>',
    ]
    rels = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">',
    ]
    sheet_xml: list[tuple[str, str]] = []

    for idx, (name, rows) in enumerate(sheets.items(), start=1):
        workbook_xml.append(f'<sheet name="{name}" sheetId="{idx}" r:id="rId{idx}"/>')
        rels.append(
            f'<Relationship Id="rId{idx}" '
            'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
            f'Target="worksheets/sheet{idx}.xml"/>'
        )
        parts = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>',
        ]
        for row_number, row in enumerate(rows, start=1):
            parts.append(f'<row r="{row_number}">')
            for column_number, value in enumerate(row, start=1):
                if value == "":
                    continue
                ref = f"{col(column_number)}{row_number}"
                sid = shared_id(value)
                parts.append(f'<c r="{ref}" t="s"><v>{sid}</v></c>')
            parts.append('</row>')
        parts.append('</sheetData>')
        if merges.get(name):
            parts.append(f'<mergeCells count="{len(merges[name])}">')
            for ref in merges[name]:
                parts.append(f'<mergeCell ref="{ref}"/>')
            parts.append('</mergeCells>')
        parts.append('</worksheet>')
        sheet_xml.append((f"xl/worksheets/sheet{idx}.xml", "".join(parts)))

    workbook_xml.append("</sheets></workbook>")
    rels.append("</Relationships>")
    shared_xml = (
        '<?xml version="1.0" encoding="UTF-8"?>'
        '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        + "".join(f"<si><t>{value}</t></si>" for value in shared)
        + "</sst>"
    )

    with zipfile.ZipFile(path, "w") as archive:
        archive.writestr("xl/workbook.xml", "".join(workbook_xml))
        archive.writestr("xl/_rels/workbook.xml.rels", "".join(rels))
        archive.writestr("xl/sharedStrings.xml", shared_xml)
        for target, xml in sheet_xml:
            archive.writestr(target, xml)


class DomainGeneratorTest(unittest.TestCase):
    def test_generates_deterministic_normalized_snapshots(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            a = root / "a.xlsx"
            b = root / "b.xlsx"
            c = root / "c.xlsx"

            workbook(a, {
                "TAB.MUN_IBGE": [
                    ["UF", "", "Município", "Código"],
                    ["Rio de Janeiro", "", "Rio de Janeiro", "3304557"],
                ],
                "TAB.LOC.GERAL": [["Código", "Descrição"], ["0000000", "ÁGUAS MARÍTIMAS"]],
                "TAB.PAÍS_ISO2": [["Código", "País"], ["BR", "Brasil"], ["US", "Estados Unidos"]],
            })
            workbook(b, {
                "LISTA.SERV.NAC.": [
                    ["Código", "Descrição"],
                    ["01.01.01", "Análise e desenvolvimento de sistemas."],
                    ["01.01", "Cabeçalho que não é código utilizável"],
                ],
                "LISTA.NBS_v2.0": [
                    ["Código", "Descrição"],
                    ["1.0101.11.00", "Serviço NBS válido"],
                    ["1.0101.11", "Nível hierárquico"],
                ],
            })
            workbook(c, {
                "INDOP": [
                    ["Código", "Característica", "Local"],
                    ["020101", "Execução sobre bem imóvel", "Localidade do imóvel (1)"],
                ],
            })

            first = GEN.generate(a, b, c)
            second = GEN.generate(a, b, c)
            self.assertEqual(first, second)
            self.assertIn("3304557\tRJ\tRio de Janeiro", first["municipios-ibge-v1.00.tsv"])
            self.assertIn("0000000\t\tÁGUAS MARÍTIMAS", first["municipios-ibge-v1.00.tsv"])
            self.assertIn("BR\tBrasil", first["paises-iso2-v1.00.tsv"])
            self.assertIn("010101\tAnálise e desenvolvimento de sistemas.", first["servicos-nacionais-v1.01.tsv"])
            service_codes = {
                line.split("\\t", 1)[0]
                for line in first["servicos-nacionais-v1.01.tsv"].splitlines()
                if line and not line.startswith("#")
            }
            nbs_codes = {
                line.split("\\t", 1)[0]
                for line in first["nbs-v2.0.tsv"].splitlines()
                if line and not line.startswith("#")
            }
            self.assertNotIn("0101", service_codes)
            self.assertNotIn("10101100", nbs_codes)
            self.assertIn("020101\tExecução sobre bem imóvel\tLocalidade do imóvel (1)", first["indicadores-operacao-ibscbs-v1.01.tsv"])

    def test_cardinality_guard_rejects_partial_annexes(self) -> None:
        partial = {
            name: "\n".join(GEN.HEADERS[name] + ["000000\tfixture"]) + "\n"
            for name in GEN.EXPECTED_COUNTS
        }
        with self.assertRaises(ValueError):
            GEN.validate_counts(partial)


if __name__ == "__main__":
    unittest.main()
