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

        return [
            'notice' => 'Schema packages are compared by their XSD bytes; this does not establish NT009 activation.',
            'production_url' => $productionUrl,
            'restricted_url' => $restrictedUrl,
            'production' => $production,
            'restricted' => $restricted,
            'file_comparison' => $changed,
        ];
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
                $basename = basename($path);
                if (isset($files[$basename])) {
                    throw new \UnexpectedValueException('Duplicate XSD basename in official schema ZIP');
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
                $files[$basename] = hash('sha256', $data);
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
