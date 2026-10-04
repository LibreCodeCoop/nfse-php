<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Xml;

/**
 * Validates SEFIN event-registration XML against the vendored NFS-e schema.
 */
final class EventSchemaValidator
{
    public const SCHEMA_VERSION = '1.01';

    /** @var list<string> */
    public const SUPPORTED_SCHEMA_VERSIONS = [
        self::SCHEMA_VERSION,
    ];

    public function __construct(
        private readonly ?string $schemaPath = null,
        private readonly string $schemaVersion = self::SCHEMA_VERSION,
    ) {
        if (!in_array($this->schemaVersion, self::SUPPORTED_SCHEMA_VERSIONS, true)) {
            throw new \InvalidArgumentException(
                'Unsupported NFS-e event schema version: ' . $this->schemaVersion
                . '. Supported versions: ' . implode(', ', self::SUPPORTED_SCHEMA_VERSIONS),
            );
        }
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

            if ($document->schemaValidate($this->resolvedSchemaPath())) {
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
            ?? dirname(__DIR__, 2)
                . '/references/schemas/nfse/'
                . $this->schemaVersion
                . '/pedRegEvento_v'
                . $this->schemaVersion
                . '.xsd';
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
