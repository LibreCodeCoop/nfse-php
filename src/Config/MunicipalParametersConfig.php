<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Config;

final readonly class MunicipalParametersConfig
{
    private const BASE_URL_PROD = 'https://adn.nfse.gov.br/parametrizacao';
    private const BASE_URL_SANDBOX = 'https://adn.producaorestrita.nfse.gov.br/parametrizacao';

    public string $baseUrl;

    public function __construct(
        public bool $sandboxMode = false,
        ?string $baseUrl = null,
        public float $timeoutSeconds = 30.0,
    ) {
        if ($timeoutSeconds <= 0) {
            throw new \InvalidArgumentException('Municipal parameters timeout must be greater than zero.');
        }

        $this->baseUrl = rtrim($baseUrl ?? ($sandboxMode ? self::BASE_URL_SANDBOX : self::BASE_URL_PROD), '/');
    }
}
