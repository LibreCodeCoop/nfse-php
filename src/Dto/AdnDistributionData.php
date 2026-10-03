<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

final readonly class AdnDistributionData
{
    /**
     * @param list<AdnDocumentData> $documents
     * @param list<AdnMessageData> $alerts
     * @param list<AdnMessageData> $errors
     */
    public function __construct(
        public string $statusProcessamento,
        public array $documents,
        public array $alerts,
        public array $errors,
        public string $ambiente,
        public ?string $versaoAplicativo,
        public string $dataHoraProcessamento,
        public ?int $ultimoNsu = null,
    ) {
    }
}
