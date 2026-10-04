<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Dto;

/**
 * One of the mutually exclusive document identities accepted by TCDocDedRed.
 */
final readonly class DeductionDocumentReferenceData
{
    private function __construct(
        public string $type,
        public array $values,
    ) {
    }

    public static function nfse(string $accessKey): self
    {
        return new self('nfse', ['access_key' => $accessKey]);
    }

    public static function nfe(string $accessKey): self
    {
        return new self('nfe', ['access_key' => $accessKey]);
    }

    public static function legacyNfse(string $municipioIbge, string $numero, string $codigoVerificacao): self
    {
        return new self('legacy_nfse', [
            'municipio_ibge' => $municipioIbge,
            'numero' => $numero,
            'codigo_verificacao' => $codigoVerificacao,
        ]);
    }

    public static function legacyNfNfs(string $numero, string $modelo, string $serie): self
    {
        return new self('legacy_nf_nfs', [
            'numero' => $numero,
            'modelo' => $modelo,
            'serie' => $serie,
        ]);
    }

    public static function fiscalDocument(string $numero): self
    {
        return new self('fiscal_document', ['numero' => $numero]);
    }

    public static function document(string $numero): self
    {
        return new self('document', ['numero' => $numero]);
    }
}
