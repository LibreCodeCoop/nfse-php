<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/**
 * Documento Padrão de Serviço — the payload submitted to the SEFIN gateway.
 *
 * All monetary values are in BRL, represented as strings to avoid floating-point issues.
 */
final readonly class DpsData
{
    public function __construct(
        /** CNPJ do prestador de serviço (only digits, 14 chars). */
        public string $cnpjPrestador,

        /** Código IBGE do município do prestador (7 digits). */
        public string $municipioIbge,

        /** Item da lista de serviços — LC 116/2003. */
        public string $itemListaServico,

        /** Valor total do serviço em reais (e.g. "1500.00"). */
        public string $valorServico,

        /** Alíquota do ISS em percentual (e.g. "5.00"). */
        public string $aliquota,

        /** Descrição do serviço prestado. */
        public string $discriminacao,

        /** CNPJ ou CPF do tomador (only digits, 11 or 14 chars). Empty string for foreign. */
        public string $documentoTomador = '',

        /** Nome / Razão Social do tomador. */
        public string $nomeTomador = '',

        /** Regime especial de tributação (optional). */
        public ?int $regimeEspecialTributacao = null,

        /** Whether ISS is retained at source. */
        public bool $issRetido = false,
    ) {
    }
}
