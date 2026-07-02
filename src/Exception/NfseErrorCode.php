<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Exception;

/**
 * Machine-readable error codes for NFS-e operations.
 *
 * These codes provide a deterministic, framework-agnostic way to identify
 * the type of failure without relying on human-readable messages.
 */
enum NfseErrorCode: string
{
    /** Connection to the SEFIN gateway could not be established. */
    case NetworkFailure = 'NETWORK_FAILURE';

    /** Gateway returned a response that could not be parsed. */
    case InvalidResponse = 'INVALID_RESPONSE';

    /** Gateway rejected the NFS-e issuance request (HTTP 4xx/5xx). */
    case IssuanceRejected = 'ISSUANCE_REJECTED';

    /** Gateway rejected the NFS-e cancellation request (HTTP 4xx/5xx). */
    case CancellationRejected = 'CANCELLATION_REJECTED';

    /** Gateway returned an error when querying an NFS-e (HTTP 4xx/5xx). */
    case QueryFailed = 'QUERY_FAILED';

    /** DANFSe PDF could not be generated locally (invalid NFS-e XML or render error). */
    case ArtifactRetrievalFailed = 'ARTIFACT_RETRIEVAL_FAILED';
}
