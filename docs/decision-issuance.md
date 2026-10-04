<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Administrative or judicial decision issuance

The Sistema Nacional NFS-e provides a dedicated contributor bypass for NFS-e
authorized under an administrative or judicial decision. This is an opt-in
protocol capability and is not part of ordinary DPS issuance.

## Official contract

The current production contributor manual documents:

- endpoint: `POST /decisao-judicial/nfse`;
- request field: `xmlGZipB64`, containing the complete signed NFS-e XML
  compressed with GZip and encoded as Base64;
- the contributor is responsible for every mandatory NFS-e field normally
  generated/calculated by the national platform;
- the municipality must previously register the applicable decision and
  authorize the contributor for this flow;
- after authorization, normal NFS-e query and cancellation APIs apply;
- substitution may be represented through the normal substitution relationship
  embedded in the DPS contained by the complete NFS-e.

Production documentation:
https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/documentacao-atual

## PHP API

Use the optional `DecisionNfseIssuerInterface` capability. Ordinary
`NfseClientInterface` implementations do not need to implement decision-flow
issuance.

```php
use LibreCodeCoop\NfsePHP\Contracts\DecisionNfseIssuerInterface;
use LibreCodeCoop\NfsePHP\Dto\DecisionNfseData;

if ($client instanceof DecisionNfseIssuerInterface) {
    $receipt = $client->emitDecision($decisionNfse);
}
```

`DecisionNfseData` contains the complete-NFS-e fields owned by the contributor
and embeds the ordinary `DpsData`. The library does not determine legal
eligibility, decision classification, numbering, rates, incidence municipality,
or values.

## Validation and known schema contradiction

Generated decision-flow documents should be validated with
`NfseSchemaValidator` against the vendored official schema version before live
submission.

The January 2026 decision manual prescribes `nDFSe=0`, while the official
NFS-e v1.01 XSD requires a positive 1-to-13-digit value. The library keeps this
contradiction explicit rather than silently changing either contract. Callers
must use the value required by the environment they are integrating with and
keep the decision/legal source auditable.

## IBS/CBS

Decision-flow DPS data containing IBS/CBS currently raises a `LogicException`
because the bypass requires complete NFS-e-level calculated IBS/CBS values.
Those values are not silently omitted or inferred.

## Transport and recovery

The bypass POST is a mutable request and is never blindly retried. After a
successful authorization, use the returned access key with the normal
`query()`, `cancel()`, event and DANFSe operations.
