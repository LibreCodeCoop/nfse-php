<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tools;

/**
 * Offline, bounded structural comparison of published government XSD ZIPs.
 *
 * Identical numeric scheme versions do not imply identical byte content or
 * deployment. This tool does not validate emitted fiscal XML.
 */
final class OfficialXsdPackageInspector
{
    private const MAX_PACKAGE_BYTES = 20 * 1024 * 1024;
    private const MAX_XSD_BYTES = 2 * 1024 * 1024;
    private const MAX_XSD_FILES = 200;

    /**
     * @param callable(string): string $fetch
     * @return array<string, mixed>
     */
    public function compareOfficial(string $productionUrl, string $restrictedUrl, callable $fetch): array
    {
        foreach ([$productionUrl, $restrictedUrl] as $url) {
            $urlParts = parse_url($url);
            if (!is_array($urlParts) || ($urlParts['scheme'] ?? '') !== 'https'
                || ($urlParts['host'] ?? '') !== 'www.gov.br'
                || !str_starts_with($urlParts['path'] ?? '', '/nfse/')
                || !str_ends_with($urlParts['path'] ?? '', '.zip')
                || isset($urlParts['user']) || isset($urlParts['pass']) || isset($urlParts['port'])) {
                throw new \InvalidArgumentException('Only official NFS-e XSD ZIP URLs are permitted');
            }
        }

        $production = $this->inspect($fetch($productionUrl));
        $restricted = $this->inspect($fetch($restrictedUrl));

        $changed = [];
        $allNames = array_values(array_unique(array_merge(
            array_keys($production['files']),
            array_keys($restricted['files'])
        )));
        sort($allNames);
        foreach ($allNames as $filename) {
            $a = $production['files'][$filename] ?? null;
            $b = $restricted['files'][$filename] ?? null;
            $changed[$filename] = $a === null ? 'restricted-only'
                : ($b === null ? 'production-only' : ($a === $b ? 'identical' : 'different'));
        }

        // Compare the multiset of SHA-256 values for each XSD basename as
        // well as full archive paths. Different ZIP folder names must not
        // falsely report that every logical schema is unique to one bundle.
        $aByName = $this->byBasename($production['files']);
        $bByName = $this->byBasename($restricted['files']);
        $basenameComparison = [];
        $basenameNames = array_values(array_unique(array_merge(
            array_keys($aByName),
            array_keys($bByName)
        )));
        sort($basenameNames);
        foreach ($basenameNames as $name) {
            $a = $aByName[$name] ?? null;
            $b = $bByName[$name] ?? null;
            $basenameComparison[$name] = $a === null ? 'restricted-only'
                : ($b === null ? 'production-only' : ($a === $b ? 'identical' : 'different'));
        }

        return [
            'notice' => 'Schema packages are compared by XSD bytes, not solely ZIP metadata; this does not establish NT009 activation.',
            'production_url' => $productionUrl,
            'restricted_url' => $restrictedUrl,
            'production' => $production,
            'restricted' => $restricted,
            'file_comparison' => $changed,
            'basename_comparison' => $basenameComparison,
        ];
    }

    /**
     * Compare only the explicitly pinned official production and restricted
     * packages. The manifest is the single source of URLs and observed hashes.
     *
     * @param callable(string): string $fetch
     * @return array<string,mixed>
     */
    public function comparePinned(string $manifestPath, callable $fetch): array
    {
        $json = @file_get_contents($manifestPath);
        if ($json === false) {
            throw new \RuntimeException('Missing official schema provenance manifest');
        }
        $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || !is_array($manifest['sources'] ?? null)) {
            throw new \UnexpectedValueException('Malformed official source manifest');
        }

        $ids = ['xsd-production-20260209', 'xsd-restricted-20260727'];
        $found = [];
        foreach ($manifest['sources'] as $entry) {
            if (!is_array($entry) || !in_array($entry['id'] ?? null, $ids, true)) {
                continue;
            }
            $id = $entry['id'];
            if (isset($found[$id]) || ($entry['type'] ?? null) !== 'zip'
                || !is_string($entry['url'] ?? null)
                || !is_string($entry['observed_sha256'] ?? null)
                || preg_match('/^[a-f0-9]{64}$/D', $entry['observed_sha256']) !== 1) {
                throw new \UnexpectedValueException('Invalid or duplicate pinned XSD source: ' . $id);
            }
            $found[$id] = $entry;
        }
        foreach ($ids as $id) {
            if (!isset($found[$id])) {
                throw new \UnexpectedValueException('Missing pinned official XSD source: ' . $id);
            }
        }
        $result = $this->compareOfficial(
            $found[$ids[0]]['url'],
            $found[$ids[1]]['url'],
            $fetch
        );
        foreach (['production', 'restricted'] as $index => $environment) {
            $expected = $found[$ids[$index]]['observed_sha256'];
            if (!hash_equals($expected, $result[$environment]['sha256'])) {
                throw new \UnexpectedValueException(
                    'Published ' . $environment . ' XSD bytes changed from the reviewed manifest'
                );
            }
        }

        return $result;
    }

    /**
     * @param array<string, string> $files
     * @return array<string, list<string>>
     */
    private function byBasename(array $files): array
    {
        $groups = [];
        foreach ($files as $path => $sha) {
            $groups[basename($path)][] = $sha;
        }
        foreach ($groups as &$shas) {
            sort($shas);
        }
        unset($shas);
        ksort($groups);

        return $groups;
    }

    /**
     * @return array{sha256:string,files:array<string,string>,xsd_count:int}
     */
    public function inspect(string $archive): array
    {
        if ($archive === '' || strlen($archive) >= self::MAX_PACKAGE_BYTES
            || !str_starts_with($archive, "PK\x03\x04")) {
            throw new \UnexpectedValueException('Invalid or oversized official schema ZIP');
        }
        $temp = tempnam(sys_get_temp_dir(), 'nfse-xsd-');
        if ($temp === false || file_put_contents($temp, $archive) !== strlen($archive)) {
            throw new \RuntimeException('Cannot stage XSD for bounded inspection');
        }
        $zip = new \ZipArchive();
        try {
            if ($zip->open($temp) !== true) {
                throw new \UnexpectedValueException('Official schema archive is not valid ZIP');
            }
            $files = [];
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $entry = $zip->statIndex($i);
                $path = $entry['name'] ?? '';
                if (!is_string($path) || $path === '' || !str_ends_with(strtolower($path), '.xsd')) {
                    continue;
                }
                if (str_contains($path, '..') || str_starts_with($path, '/')
                    || str_contains($path, '\\') || ($entry['size'] ?? 0) > self::MAX_XSD_BYTES) {
                    throw new \UnexpectedValueException('Unsafe XSD archive entry');
                }
                // Official ZIPs may contain distinct directory trees with
                // identically named XSDs. Compare full archive paths rather
                // than silently overwriting or rejecting valid entries.
                if (isset($files[$path])) {
                    throw new \UnexpectedValueException('Duplicate XSD archive path');
                }
                if (count($files) >= self::MAX_XSD_FILES) {
                    throw new \UnexpectedValueException('Too many XSD files in archive');
                }
                $size = (int) ($entry['size'] ?? 0);
                $total += $size;
                if ($total > self::MAX_PACKAGE_BYTES) {
                    throw new \UnexpectedValueException('Excessive cumulative XSD size');
                }
                $data = $zip->getFromIndex($i);
                if ($data === false || strlen($data) !== $size) {
                    throw new \UnexpectedValueException('Cannot read an official XSD entry');
                }
                $files[$path] = hash('sha256', $data);
            }
            if ($files === []) {
                throw new \UnexpectedValueException('No XSD files present in the schema ZIP');
            }
            ksort($files);

            return ['sha256' => hash('sha256', $archive), 'files' => $files, 'xsd_count' => count($files)];
        } finally {
            $zip->close();
            unlink($temp);
        }
    }
}
