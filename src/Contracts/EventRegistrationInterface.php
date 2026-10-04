<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Contracts;

use LibreCodeCoop\NfsePHP\Dto\EventRegistrationData;

interface EventRegistrationInterface
{
    /**
     * Sign and register a caller-built event XML through the documented
     * POST /nfse/{chaveAcesso}/eventos endpoint.
     */
    public function registerEventXml(string $chaveAcesso, string $eventXml): EventRegistrationData;
}
