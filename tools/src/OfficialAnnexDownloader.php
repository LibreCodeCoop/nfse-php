<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tools;

/**
 * Download only explicitly selected, checksum-pinned official NFS-e annexes.
 *
 * Fetching is injected so the same validation works with offline test fixtures.
 * The source manifest and not workflow YAML owns URLs and expected checksums.
 */
final class OfficialAnnexDownloader
{
    /**
     * @param list<string> $ids
     * @return array<string, array{url:string,observed_sha256:string}>
     */
    public function sources(string $manifestPath, array $ids): array
    {
        if ($ids === [] || count($ids) !== count(array_unique($ids))) {
            throw new \InvalidArgumentException('Provide distinct official annex IDs');
        }
        foreach ($ids as $id) {
            if (!preg_match('/^[a-z0-9-]+$/D', $id)) {
                throw new \InvalidArgumentException('Invalid official source ID: ' . $id);
            }
        }

        $contents = @file_get_contents($manifestPath);
        if ($contents === false) {
            throw new \RuntimeException('Missing official source manifest: ' . $manifestPath);
        }
        $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || !isset($manifest['sources'])
            || !is_array($manifest['sources'])) {
            throw new \UnexpectedValueException('Invalid official source manifest');
        }

        $found = [];
        foreach ($manifest['sources'] as $source) {
            if (!is_array($source) || !in_array($source['id'] ?? null, $ids, true)) {
                continue;
            }
            $id = $source['id'];
            if (isset($found[$id])) {
                throw new \UnexpectedValueException('Duplicate official source ID: ' . $id);
            }
            $url = $source['url'] ?? null;
            $hash = $source['observed_sha256'] ?? null;
            if (!is_string($url) || !is_string($hash)
                || ($source['type'] ?? null) !== 'xlsx'
                || !preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                throw new \UnexpectedValueException('Missing valid XLSX URL or SHA-256 for ' . $id);
            }
            $parts = parse_url($url);
            if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
                || ($parts['host'] ?? '') !== 'www.gov.br'
                || !str_starts_with($parts['path'] ?? '', '/nfse/')
                || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
                throw new \UnexpectedValueException('Non-official source URL for ' . $id);
            }
            $found[$id] = ['url' => $url, 'observed_sha256' => $hash];
        }
        foreach ($ids as $id) {
            if (!isset($found[$id])) {
                throw new \UnexpectedValueException('Official annex missing from manifest: ' . $id);
            }
        }

        return $found;
    }

    /**
     * @param list<string> $ids
     * @param callable(string): string $fetch
     * @return array<string, array{path:string,sha256:string}>
     */
    public function download(string $manifestPath, array $ids, string $outputDir, callable $fetch): array
    {
        $sources = $this->sources($manifestPath, $ids);
        $data = [];
        foreach ($sources as $id => $source) {
            $bytes = $fetch($source['url']);
            if (!is_string($bytes) || $bytes === ''
                || strlen($bytes) >= SourceVerifier::MAX_SOURCE_BYTES
                || !str_starts_with($bytes, "PK\x03\x04")) {
                throw new \UnexpectedValueException('Invalid official XLSX download for ' . $id);
            }
            $hash = hash('sha256', $bytes);
            if (!hash_equals($source['observed_sha256'], $hash)) {
                throw new \UnexpectedValueException('Official source SHA-256 changed for ' . $id . ': ' . $hash);
            }
            $data[$id] = ['bytes' => $bytes, 'sha256' => $hash];
        }

        if (!is_dir($outputDir) && !mkdir($outputDir, 0o700, true) && !is_dir($outputDir)) {
            throw new \RuntimeException('Cannot create official annex download directory');
        }
        if (!is_dir($outputDir) || !is_writable($outputDir)) {
            throw new \RuntimeException('Official annex download directory is not writable');
        }
        $result = [];
        foreach ($data as $id => $value) {
            $path = rtrim($outputDir, '/') . '/' . $id . '.xlsx';
            if (is_link($path)) {
                throw new \UnexpectedValueException('Refusing to overwrite symlink: ' . $path);
            }
            $temporary = tempnam($outputDir, '.nfse-');
            if ($temporary === false) {
                throw new \RuntimeException('Cannot create temporary official annex file');
            }
            try {
                if (file_put_contents($temporary, $value['bytes']) !== strlen($value['bytes'])
                    || !rename($temporary, $path)) {
                    throw new \RuntimeException('Cannot write verified official annex ' . $id);
                }
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
            $result[$id] = ['path' => $path, 'sha256' => $value['sha256']];
        }

        return $result;
    }
}
