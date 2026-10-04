<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

final readonly class EventRegistrationData
{
    /**
     * @param array<string, mixed> $response
     */
    public function __construct(
        public bool $accepted,
        public int $httpStatus,
        public array $response,
    ) {
    }
}
