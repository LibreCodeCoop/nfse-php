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
                'Unsupported NFS-e schema version: ' . $this->schemaVersion
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
                return $this->collectActionableErrors();
            }

            $isValid = $document->schemaValidate($this->resolvedSchemaPath());

            if ($isValid) {
                return [];
            }

            return $this->collectActionableErrors();
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
                . '/DPS_v'
                . $this->schemaVersion
                . '.xsd';
    }

    /**
     * @return list<string>
     */
    private function collectActionableErrors(): array
    {
        $errors = [];

        foreach (libxml_get_errors() as $error) {
            $message = trim($error->message);

            if ($this->isKnownUpstreamSchemaIssue($message)) {
                continue;
            }

            if ($error->line > 0) {
                $message .= ' (line ' . $error->line . ')';
            }

            $errors[] = $message;
        }

        return $errors;
    }

    /**
     * The official 2026-02-09 v1.01 package uses ^ and $ in TSSerieDPS.
     * XML Schema regular expressions do not define those characters as
     * anchors, so libxml interprets them literally and rejects every normal
     * DPS series. Keep the vendored schema byte-for-byte intact and ignore
     * only this exact upstream defect while retaining all other validation.
     */
    private function isKnownUpstreamSchemaIssue(string $message): bool
    {
        return str_contains($message, "Element '{http://www.sped.fazenda.gov.br/nfse}serie'")
            && str_contains($message, "not accepted by the pattern '^0{0,4}");
    }
}
