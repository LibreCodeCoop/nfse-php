<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Xml;

final class NfseSchemaValidator
{
    public const SCHEMA_VERSION = '1.01';

    public function __construct(
        private readonly ?string $schemaPath = null,
        private readonly string $schemaVersion = self::SCHEMA_VERSION,
    ) {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new \InvalidArgumentException('Unsupported complete NFS-e schema version: ' . $schemaVersion);
        }
    }

    /** @return list<string> */
    public function validate(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $document = new \DOMDocument();
            if (!$document->loadXML($xml, LIBXML_NONET)) {
                return $this->errors();
            }
            if ($document->schemaValidate($this->schemaPath ?? dirname(__DIR__, 2) . '/references/schemas/nfse/' . $this->schemaVersion . '/NFSe_v' . $this->schemaVersion . '.xsd')) {
                return [];
            }
            return $this->errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function isValid(string $xml): bool
    {
        return $this->validate($xml) === [];
    }

    /** @return list<string> */
    private function errors(): array
    {
        $errors = [];
        foreach (libxml_get_errors() as $error) {
            $message = trim($error->message);
            if (
                str_contains($message, "Element '{http://www.sped.fazenda.gov.br/nfse}serie'")
                && str_contains($message, "not accepted by the pattern '^0{0,4}")
            ) {
                continue;
            }
            $errors[] = $message . ($error->line > 0 ? ' (line ' . $error->line . ')' : '');
        }
        return $errors;
    }
}
