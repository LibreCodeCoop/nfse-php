<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Danfse\Enum;

/**
 * Shared resolver for int-backed DANFSe enums: maps a raw string value coming
 * from the NFS-e XML to its human-readable label, falling back to '-' when the
 * value is non-numeric or not a known case.
 *
 * The using enum must be int-backed and declare a label(): string method.
 */
trait LabelFromIntValue
{
    public static function labelFor(string $value): string
    {
        if (!is_numeric($value)) {
            return '-';
        }

        return self::tryFrom((int) $value)?->label() ?? '-';
    }
}
