<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/**
 * Review-only structural XML, intentionally not accepted as an emission input.
 *
 * This is not validated against an effective NT009 XSD. No production or
 * restricted-production activation claim is made by creating this object.
 */
final readonly class Nt009DpsPreview
{
    public function __construct(
        public string $xml,
    ) {
    }
}
