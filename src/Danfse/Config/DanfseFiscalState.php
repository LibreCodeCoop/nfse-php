<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Danfse\Config;

/**
 * Explicit fiscal event status provided by a trusted caller.
 *
 * This library does not query the cancellation/substitution event here.
 * Do not infer these states from a DPS substitution reference or cStat.
 */
enum DanfseFiscalState: string
{
    case Cancelled = 'CANCELADA';
    case Substituted = 'SUBSTITUÍDA';
}
