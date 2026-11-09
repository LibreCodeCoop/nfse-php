<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# DPS schema selection by SEFIN environment

The NFS-e Nacional DPS XML layout can differ between production and production-restricted environments even when both advertise version 1.01. Use the official XSD **release** rather than just the schema version to select the encoder.

The October 20, 2026 official production-restricted package introduced a mandatory finNFSe=0 immediately after tpEmit for regular NFS-e, moved the NBS code before the service description, and placed IBS/CBS CST and cClassTrib directly under the trib element. The February 9, 2026 production package retains the earlier hierarchy.

## Runtime routing

EnvironmentConfig(sandboxMode: true) selects the 20261020 release by default. EnvironmentConfig(sandboxMode: false) selects the 20260209 production release. NfseClient uses the selected release for DPS emission and decision-based emission. Explicit dpsSchemaRelease selection is available for controlled, verified rollouts and rejects unknown values.

XmlBuilder::buildDps(DpsData, schemaRelease) and DpsSchemaValidator(schemaRelease: ...) accept these same release identifiers. A direct builder without a release retains the old production default for backward compatibility.

The October release encodes regular NFS-e with finNFSe=0. It rejects explicitly nonregular purposes (not supported by this package) and legacy vDedRed deductions that require an NT009-specific adjustment mapping. It does not silently infer an adjustment or fabricate tax data.

Existing production consumers remain on the February layout until their environment is explicitly updated to the new official production XSD; do not globally switch production to the restricted snapshot.

## Upgrade checklist for consumers

1. Update the nfse-php dependency to a revision including this schema routing.
2. Rebuild any packaged or namespace-scoped runtime artifacts. A Git checkout of the application alone does not change installed Composer dependencies.
3. Verify the environment flag and run a synthetic XML serialization check against the corresponding official XSD.
4. Restart PHP/opcache processes that load the old package.
5. Do not blindly repeat an ambiguous emission attempt: first reconcile the DPS identifier and any authorized receipt. An unequivocally rejected attempt may be retried only through the usual application reservation and validation process.

The official October snapshot is stored separately under references/schemas/nfse/1.01-20261020 and is guarded by Git-blob integrity tests. Keep both bundles immutable.
