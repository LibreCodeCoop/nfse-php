<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# SEFIN event endpoint contract

The contributor manual currently documents these event endpoints:

| Operation | Endpoint | nfse-php status |
| --- | --- | --- |
| Register signed event | `POST /nfse/{chaveAcesso}/eventos` | Supported by `EventRegistrationInterface::registerEventXml()`; cancellation uses this primitive. |
| List all events | `GET /nfse/{chaveAcesso}/eventos` | Not enabled by default. |
| List events by type | `GET /nfse/{chaveAcesso}/eventos/{tipoEvento}` | Not enabled by default. |
| Query one event | `GET /nfse/{chaveAcesso}/eventos/{tipoEvento}/{numSeqEvento}` | Supported by `EventLookupInterface::queryEvent()`. |

Source: Sistema Nacional NFS-e, current production contributor API manual.

The production/restricted Swagger snapshot reviewed during implementation exposed the POST
operation and the fully-qualified GET query, but did not expose the base or type-only GET
operations. Because mutating or querying a fiscal API from an undocumented executable
contract is an interoperability risk, nfse-php intentionally does not guess those two routes.

When an authoritative executable contract exposes them, they can be added without changing
the existing SEFIN/ADN separation.

## Event XML

Event registration uses the official `pedRegEvento_v1.01.xsd` contract and
`tiposEventos_v1.01.xsd`. The repository validates cancellation/event-registration XML
locally with `EventSchemaValidator`; schema validation never downloads external resources.
