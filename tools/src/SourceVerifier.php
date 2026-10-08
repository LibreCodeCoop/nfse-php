<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tools;

/**
 * Tracks changes in government-published source bytes.
 * Observed hashes are OUR snapshots, not government signatures.
 * A content change is evidence to review, never fiscal activation.
 */
final class SourceVerifier
{
    public const MAX_SOURCE_BYTES = 20 * 1024 * 1024;

    public function assertFile(string $path, ?string $expectedSha256): string
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new \RuntimeException('Cannot read source: ' . $path);
        }

        $hash = hash('sha256', $bytes);
        if ($expectedSha256 !== null && !hash_equals(strtolower($expectedSha256), $hash)) {
            throw new \UnexpectedValueException("Source checksum differs for {$path}: {$hash}");
        }

        return $hash;
    }

    /**
     * @param callable(string): string $fetch
     * @return list<array{id:string,version:string,environment:string,status:string,sha256:string,expected_sha256:?string}>
     */
    public function inspect(string $manifestPath, callable $fetch): array
    {
        $raw = @file_get_contents($manifestPath);
        if ($raw === false) {
            throw new \RuntimeException('Cannot read source manifest: ' . $manifestPath);
        }
        $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || !isset($manifest['sources']) || !is_array($manifest['sources'])) {
            throw new \UnexpectedValueException('Invalid source manifest');
        }

        $results = [];
        $ids = [];
        foreach ($manifest['sources'] as $entry) {
            if (!is_array($entry)) {
                throw new \UnexpectedValueException('Invalid source entry');
            }
            $id = $entry['id'] ?? null;
            $url = $entry['url'] ?? null;
            $type = $entry['type'] ?? null;
            $expected = $entry['observed_sha256'] ?? null;
            if (!is_string($id) || !preg_match('/^[a-z0-9-]+$/D', $id)
                || isset($ids[$id]) || !is_string($url)
                || !in_array($type, ['xlsx', 'zip', 'pdf'], true)
                || ($expected !== null && (!is_string($expected)
                    || !preg_match('/^[a-f0-9]{64}$/D', $expected)))) {
                throw new \UnexpectedValueException('Invalid source metadata for ' . (string) $id);
            }
            // Restrict scheduled downloads to the official Sistema Nacional NFS-e portal.
            $parts = parse_url($url);
            if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
                || ($parts['host'] ?? '') !== 'www.gov.br'
                || !str_starts_with($parts['path'] ?? '', '/nfse/')) {
                throw new \UnexpectedValueException("Unexpected official source URL for {$id}");
            }
            $ids[$id] = true;
            $contents = $fetch($url);
            if ($contents === '' || strlen($contents) >= self::MAX_SOURCE_BYTES
                || !self::hasMagic($contents, $type)) {
                throw new \UnexpectedValueException("Invalid {$type} download for {$id}");
            }
            $observed = hash('sha256', $contents);
            $status = $expected === null ? 'baseline-needed'
                : (hash_equals($expected, $observed) ? 'unchanged' : 'changed');
            $results[] = [
                'id' => $id,
                'version' => (string) ($entry['version'] ?? ''),
                'environment' => (string) ($entry['environment'] ?? ''),
                'status' => $status,
                'sha256' => $observed,
                'expected_sha256' => $expected,
            ];
        }

        return $results;
    }

    public static function fetchOfficial(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 45,
                'follow_location' => 1,
                'max_redirects' => 4,
                'header' => "User-Agent: nfse-php-source-monitor/1.0\r\nAccept: application/octet-stream\r\n",
            ],
        ]);
        $content = @file_get_contents($url, false, $context, 0, self::MAX_SOURCE_BYTES);
        if ($content === false) {
            throw new \RuntimeException('Official source unavailable: ' . $url);
        }

        return $content;
    }

    private static function hasMagic(string $content, string $type): bool
    {
        return match ($type) {
            'xlsx', 'zip' => str_starts_with($content, "PK\x03\x04"),
            'pdf' => str_starts_with($content, '%PDF-'),
            default => false,
        };
    }
}
