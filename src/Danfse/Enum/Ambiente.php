<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Danfse\Enum;

/**
 * NFS-e environment (tpAmb): production carries legal value, homologação
 * (sandbox) is for testing and renders a "no legal value" watermark.
 */
enum Ambiente: int
{
    case PRODUCAO = 1;
    case HOMOLOGACAO = 2;

    /**
     * Resolve a raw tpAmb value, defaulting to production for missing or
     * unknown values.
     */
    public static function fromValue(string $value): self
    {
        return self::tryFrom((int) $value) ?? self::PRODUCAO;
    }

    public function isHomologacao(): bool
    {
        return $this === self::HOMOLOGACAO;
    }
}
