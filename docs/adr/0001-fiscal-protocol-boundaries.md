# ADR 0001: Fiscal protocol boundaries and deterministic testing

Status: Accepted

## Context

The library integrates with the National NFS-e ecosystem through three protocol surfaces:

- SEFIN issuance/query/event operations
- ADN contributor distribution
- ADN municipal parameter queries

These operations require mTLS in production, while deterministic tests must not depend on a real ICP-Brasil certificate or government availability.

The codebase already contains dedicated DTOs, XML builders/signers, secret-store contracts, schema validation and an injectable HTTP transport. This ADR records the intended boundaries so future features do not collapse XML, certificate, transport and application concerns back into large coupled clients.

## Decision

### Protocol DTOs

Classes under `src/Dto` represent protocol input/output and transport data only. They must not depend on Akaunting, Laravel or other application frameworks.

### XML construction and signing

- `XmlBuilder` owns deterministic DPS XML construction.
- event-specific XML builders must remain explicit and testable.
- signer implementations own XMLDSig generation.
- HTTP clients orchestrate builders/signers but must not embed certificate parsing or filesystem lifecycle logic.

### Certificate material

`CertConfig` carries certificate identity and prepared transport paths.

PKCS#12 parsing, secret retrieval and temporary PEM lifecycle remain outside the HTTP transport contract. Consumers may prepare those paths before creating the client. Tests may omit them entirely when using an injected fake transport.

### HTTP transport

`HttpTransportInterface` is the only network boundary used by protocol clients.

`NativeStreamTransport` is the default production implementation and owns PHP stream/mTLS mechanics.

SEFIN, ADN and municipal-parameter clients must not call `file_get_contents`, cURL or stream functions directly.

### Client responsibilities

Clients own:

- endpoint/path construction
- request encoding
- protocol-specific response decoding
- mapping HTTP/protocol failures to typed exceptions

Clients do not own:

- application persistence
- UI/workflow state
- certificate storage
- framework service containers
- municipality-specific legacy integrations

### Retry policy

Read retries, if introduced, belong at the transport/policy boundary and must be observable and bounded.

Mutating fiscal POST requests must never be blindly retried. Emission ambiguity is resolved by DPS lookup/recovery before any second submission.

### Test tiers

1. Pure/unit tests: DTOs, XML, validation, parsers and helpers.
2. Deterministic protocol tests: injected fake transport; no network or real certificate.
3. Certificate/signing tests: generated synthetic/self-signed PKCS#12 material.
4. Live fiscal smoke: opt-in only, with real credential and external endpoint.

A passing deterministic suite does not claim ICP-Brasil chain trust or government availability.

## Consequences

- consumers can test orchestration without a real A1 certificate;
- transport behavior can evolve independently from protocol clients;
- certificate/mTLS tests remain possible without coupling ordinary tests to external services;
- new protocol features must preserve this separation.

## Rejected alternatives

### Framework-specific HTTP client

Rejected because nfse-php is framework-agnostic and consumers should not inherit Laravel/Symfony/Guzzle requirements.

### Generic service/repository layers

Rejected because they would duplicate the concrete protocol clients without adding a second useful implementation.

### Live SEFIN as the main integration test

Rejected because certificate lifecycle, government availability and fiscal side effects make it unsuitable as a deterministic quality gate.
