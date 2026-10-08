<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tools;

/**
 * Discover newer official annex filenames from the government index pages.
 * This complements file SHA-256 checks, which cannot detect a NEW URL.
 */
final class PortalIndexDiscovery
{
    /**
     * @param callable(string): string $fetchPage
     * @return list<array{id:string,version:string,url:string}>
     */
    public function findNewer(string $manifestPath, callable $fetchPage): array
    {
        $raw = @file_get_contents($manifestPath);
        if ($raw === false) {
            throw new \RuntimeException('Cannot read source manifest');
        }
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['sources']) || !is_array($data['sources'])) {
            throw new \UnexpectedValueException('Invalid source manifest');
        }

        $cache = [];
        $newer = [];
        foreach ($data['sources'] as $source) {
            $page = $source['index_url'] ?? null;
            $pattern = $source['file_pattern'] ?? null;
            $original = $source['url'] ?? null;
            $id = $source['id'] ?? null;
            if ($page === null || $pattern === null) {
                continue;
            }
            if (!is_string($page) || !is_string($pattern) || !is_string($original)
                || !is_string($id) || !self::isOfficialUrl($page)) {
                throw new \UnexpectedValueException('Invalid source index metadata');
            }
            $originalVersion = self::versionFromUrl($original);
            if ($originalVersion === null) {
                throw new \UnexpectedValueException("Missing version for tracked source {$id}");
            }
            if (!isset($cache[$page])) {
                $contents = $fetchPage($page);
                if ($contents === '' || strlen($contents) > 5 * 1024 * 1024) {
                    throw new \UnexpectedValueException('Empty or oversized official index: ' . $page);
                }
                $cache[$page] = self::links($contents);
            }

            foreach ($cache[$page] as $url) {
                if (!self::isOfficialUrl($url) || preg_match('~' . $pattern . '~i', $url) !== 1) {
                    continue;
                }
                $candidateVersion = self::versionFromUrl($url);
                if ($candidateVersion === null
                    || version_compare($candidateVersion, $originalVersion) <= 0) {
                    continue;
                }
                $newer[$id . ':' . $url] = [
                    'id' => $id,
                    'version' => $candidateVersion,
                    'url' => $url,
                ];
            }
        }

        return array_values($newer);
    }

    /**
     * @return list<string>
     */
    private static function links(string $html): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $doc = new \DOMDocument();
            if (!$doc->loadHTML($html, LIBXML_NONET)) {
                throw new \UnexpectedValueException('Invalid official HTML index');
            }
            $urls = [];
            foreach ($doc->getElementsByTagName('a') as $anchor) {
                $href = $anchor->getAttribute('href');
                if (str_starts_with($href, '/nfse/')) {
                    $href = 'https://www.gov.br' . $href;
                }
                // Plone may expose a download link and a /view preview of the same resource.
                $href = preg_replace('~/view$~i', '', $href) ?? $href;
                $urls[$href] = true;
            }

            return array_keys($urls);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function isOfficialUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts) && ($parts['scheme'] ?? '') === 'https'
            && ($parts['host'] ?? '') === 'www.gov.br'
            && str_starts_with($parts['path'] ?? '', '/nfse/');
    }

    private static function versionFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path)
            || preg_match('/[-_]v([0-9]+)[-_]([0-9]+)(?:[-_]([0-9]+))?/i', basename($path), $matches) !== 1) {
            return null;
        }

        return implode('.', [(int) $matches[1], (int) $matches[2], (int) ($matches[3] ?? 0)]);
    }
}
