<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Xml;

/**
 * Validates DPS XML against the vendored official Sistema Nacional NFS-e schema.
 *
 * Validation is explicit and is not performed automatically during emission so
 * production callers can choose the desired performance/diagnostic trade-off.
 */
final class DpsSchemaValidator
{
    public const SCHEMA_VERSION = '1.01';

    public function __construct(
        private readonly ?string $schemaPath = null,
    ) {
    }

    /**
     * @return list<string> Validation errors. An empty list means valid XML.
     */
    public function validate(string $xml): array
    {
        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = new \DOMDocument();

            if (!$document->loadXML($xml, LIBXML_NONET)) {
                return $this->collectErrors();
            }

            $isValid = $document->schemaValidate($this->resolvedSchemaPath());

            if ($isValid) {
                return [];
            }

            return $this->collectErrors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);
        }
    }

    public function isValid(string $xml): bool
    {
        return $this->validate($xml) === [];
    }

    private function resolvedSchemaPath(): string
    {
        return $this->schemaPath
            ?? dirname(__DIR__, 2) . '/references/schemas/nfse/' . self::SCHEMA_VERSION . '/DPS_v1.01.xsd';
    }

    /**
     * @return list<string>
     */
    private function collectErrors(): array
    {
        $errors = [];

        foreach (libxml_get_errors() as $error) {
            $message = trim($error->message);

            if ($error->line > 0) {
                $message .= ' (line ' . $error->line . ')';
            }

            $errors[] = $message;
        }

        return $errors;
    }
}
