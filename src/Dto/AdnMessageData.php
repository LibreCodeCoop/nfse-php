<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

final readonly class AdnMessageData
{
    /**
     * @param list<string> $parameters
     */
    public function __construct(
        public ?string $code = null,
        public ?string $description = null,
        public ?string $complement = null,
        public array $parameters = [],
    ) {
    }
}
