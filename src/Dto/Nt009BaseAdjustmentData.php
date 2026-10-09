<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/**
 * Review-only NT009 valores/vAjusteBC. Monetary, percentage and explicit
 * document-backed alternatives are mutually exclusive and never inferred.
 */
final readonly class Nt009BaseAdjustmentData
{
    /**
     * @param list<Nt009AdjustmentDocumentData> $documentos
     */
    public function __construct(
        /** pAjusteBCISSQN, mutually exclusive with valorIssqn. */
        public ?string $percentualIssqn = null,
        /** vAjusteBCISSQN, mutually exclusive with percentualIssqn. */
        public ?string $valorIssqn = null,
        /** vAjusteBCIBSCBSComExt, only when separately applicable. */
        public ?string $valorIbsCbsComExterior = null,
        /** Document-based alternative to percentage or monetary adjustment. */
        public array $documentos = [],
    ) {
    }
}
