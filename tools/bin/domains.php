#!/usr/bin/env php
<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use LibreCodeCoop\NfsePHP\Tools\AnnexReader;
use LibreCodeCoop\NfsePHP\Tools\DomainTableGenerator;
use LibreCodeCoop\NfsePHP\Tools\Nt009Inspector;
use LibreCodeCoop\NfsePHP\Tools\Nt009ContractMatrix;
use LibreCodeCoop\NfsePHP\Tools\OfficialAnnexDownloader;
use LibreCodeCoop\NfsePHP\Tools\PortalIndexDiscovery;
use LibreCodeCoop\NfsePHP\Tools\SourceVerifier;

$root = dirname(__DIR__, 2);
$autoload = $root . '/vendor-bin/domains/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Domain tools not installed: run composer domains:install\n");
    exit(1);
}
require_once $autoload;

$command = $argv[1] ?? '';
$options = [];
foreach (array_slice($argv, 2) as $arg) {
    if (!preg_match('/^--([a-z-]+)=(.+)$/D', $arg, $parts)) {
        fwrite(STDERR, "Expected --name=value; received: {$arg}\n");
        exit(2);
    }
    $options[$parts[1]] = $parts[2];
}

try {
    if (in_array($command, ['generate', 'check'], true)) {
        foreach (['annex-a', 'annex-b', 'annex-c'] as $name) {
            if (!isset($options[$name])) {
                throw new InvalidArgumentException("Missing --{$name}=PATH");
            }
        }
        $expectedVII = isset($options['expected-vii']) ? filter_var(
            $options['expected-vii'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        ) : null;
        if ($expectedVII === false) {
            throw new InvalidArgumentException('Invalid --expected-vii count');
        }
        $generator = new DomainTableGenerator();
        $outputs = $generator->generate(
            $options['annex-a'],
            $options['annex-b'],
            $options['annex-c'],
            $options['annex-vii'] ?? null
        );
        $generator->validateCounts($outputs, $expectedVII);
        $target = $options['output'] ?? $root . '/resources/domains';
        if ($command === 'generate' && !is_dir($target) && !mkdir($target, 0o775, true)) {
            throw new RuntimeException('Cannot create output directory');
        }
        foreach ($outputs as $name => $output) {
            $destination = $target . '/' . $name;
            if ($command === 'check') {
                $current = is_file($destination) ? file_get_contents($destination) : false;
                if ($current !== $output) {
                    throw new UnexpectedValueException("Snapshot differs: {$name} (review official source)");
                }
            } elseif (file_put_contents($destination, $output) === false) {
                throw new RuntimeException("Unable to write {$name}");
            }
        }
        echo "Verified " . count($outputs) . " deterministic snapshots\n";
    } elseif ($command === 'observe-source') {
        if (!isset($options['url'], $options['output'])
            || ($options['acknowledge-unpinned'] ?? '') !== 'yes') {
            throw new InvalidArgumentException(
                'Observation requires --url= --output= --acknowledge-unpinned=yes'
            );
        }
        $sha = (new OfficialAnnexDownloader())->observe(
            $options['url'],
            $options['output'],
            SourceVerifier::fetchOfficial(...)
        );
        echo "UNPINNED source: {$sha}; review provenance before adding to trusted manifest\n";
    } elseif ($command === 'contract-matrix') {
        foreach (['legacy-annex', 'annex-vi', 'output'] as $name) {
            if (!isset($options[$name])) {
                throw new InvalidArgumentException("Missing --{$name}=PATH");
            }
        }
        $matrix = new Nt009ContractMatrix();
        $rows = $matrix->compare($options['legacy-annex'], $options['annex-vi']);
        $data = $matrix->toTsv($rows);
        if (file_put_contents($options['output'], $data) !== strlen($data)) {
            throw new RuntimeException('Cannot write official NT009 contract comparison');
        }
        $counts = array_count_values(array_column($rows, 'status'));
        echo 'Official DPS field matrix: '
            . json_encode($counts, JSON_THROW_ON_ERROR) . "\n";
    } elseif ($command === 'download') {
        if (!isset($options['ids'], $options['output'])) {
            throw new InvalidArgumentException('Download requires --ids=ID,... and --output=DIR');
        }
        $files = (new OfficialAnnexDownloader())->download(
            $options['manifest'] ?? $root . '/resources/domains/sources.json',
            explode(',', $options['ids']),
            $options['output'],
            SourceVerifier::fetchOfficial(...)
        );
        foreach ($files as $id => $entry) {
            echo "{$id}: {$entry['sha256']} -> {$entry['path']}\n";
        }
    } elseif ($command === 'audit') {
        $manifest = $options['manifest'] ?? $root . '/resources/domains/sources.json';
        $downloader = new OfficialAnnexDownloader();
        $ids = ['annex-vi-nt009', 'annex-vii-nt009'];
        $sources = $downloader->sources($manifest, $ids);
        if (isset($options['download-dir'])) {
            $files = $downloader->download(
                $manifest,
                $ids,
                $options['download-dir'],
                SourceVerifier::fetchOfficial(...)
            );
            $options['annex-vi'] = $files['annex-vi-nt009']['path'];
            $options['annex-vii'] = $files['annex-vii-nt009']['path'];
        }
        foreach (['annex-vi', 'annex-vii'] as $name) {
            if (!isset($options[$name])) {
                throw new InvalidArgumentException("Missing --{$name}=PATH");
            }
        }
        $verifier = new SourceVerifier();
        $hashes = [
            'annex-vi' => $sources['annex-vi-nt009']['observed_sha256'],
            'annex-vii' => $sources['annex-vii-nt009']['observed_sha256'],
        ];
        foreach ($hashes as $name => $expected) {
            $hash = $verifier->assertFile($options[$name], $expected);
            $reader = new AnnexReader($options[$name]);
            echo "{$name}: {$hash}; sheets: " . implode(', ', $reader->sheetNames()) . "\n";
            $reader->close();
        }
        $comparison = (new Nt009Inspector())->inspect(
            $options['annex-vi'],
            $options['annex-vii'],
            $root . '/resources/domains/indicadores-operacao-ibscbs-v1.01.tsv'
        );
        $json = json_encode($comparison, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (($options['print-layout'] ?? '') === 'yes') {
            foreach ($comparison['layout_rows'] as $row) {
                echo 'NT009_LAYOUT_ROW '
                    . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
            }
        }
        if (isset($options['report'])) {
            if (file_put_contents($options['report'], $json) === false) {
                throw new RuntimeException('Unable to save NT009 comparison');
            }
        }
        echo 'NT009 Anexo VII: ' . $comparison['annex_vii_count']
            . ' indicators; added=' . count($comparison['added_codes'])
            . ', changed=' . count($comparison['changed_codes'])
            . ', removed=' . count($comparison['removed_codes']) . "\n";
        if (getenv('GITHUB_STEP_SUMMARY') !== false) {
            file_put_contents(
                (string) getenv('GITHUB_STEP_SUMMARY'),
                "## NT009 source comparison\n\n" . 'Annex VII: '
                . $comparison['annex_vii_count'] . ' indicators, added: '
                . count($comparison['added_codes']) . ', changed: '
                . count($comparison['changed_codes']) . ', removed: '
                . count($comparison['removed_codes'])
                . "\n\nA published annex does not establish production applicability.\n",
                FILE_APPEND
            );
        }
    } elseif ($command === 'verify-indicators') {
        if (!isset($options['annex-vii'])) {
            throw new InvalidArgumentException('Missing --annex-vii=PATH');
        }
        $snapshot = $options['snapshot']
            ?? $root . '/resources/domains/indicadores-operacao-ibscbs-v1.03.00.tsv';
        $count = (new Nt009Inspector())->verifyIndicatorSnapshot(
            $options['annex-vii'],
            $snapshot,
            Nt009Inspector::VERIFIED_NT009_INDICATOR_COUNT
        );
        echo "Official Annex VII verified: {$count} exact code/characteristic/location triplets\n";
    } elseif ($command === 'watch') {
        $manifest = $options['manifest'] ?? $root . '/resources/domains/sources.json';
        $verifier = new SourceVerifier();
        $results = $verifier->inspect($manifest, SourceVerifier::fetchOfficial(...));
        $newVersions = (new PortalIndexDiscovery())->findNewer(
            $manifest,
            SourceVerifier::fetchOfficial(...)
        );
        $report = [
            'checked_at_utc' => gmdate('c'),
            'notice' => 'Published bytes are not evidence of production activation.',
            'sources' => $results,
            'new_versions' => $newVersions,
        ];
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (isset($options['report'])) {
            if (file_put_contents($options['report'], $json) === false) {
                throw new RuntimeException('Unable to write report');
            }
        }
        echo $json;
        $summary = getenv('GITHUB_STEP_SUMMARY');
        if ($summary !== false) {
            $body = "## Official NFS-e sources\n\n| Source | Version | Environment | Status | Observed SHA-256 |\n|---|---|---|---|---|\n";
            foreach ($results as $entry) {
                $body .= '| ' . $entry['id'] . ' | ' . $entry['version'] . ' | '
                    . $entry['environment'] . ' | ' . $entry['status'] . ' | `'
                    . $entry['sha256'] . "` |\n";
            }
            $body .= "\nNew official document versions detected: " . count($newVersions) . "\n";
            foreach ($newVersions as $candidate) {
                $body .= '- ' . $candidate['id'] . ': ' . $candidate['version']
                    . ' (' . $candidate['url'] . ")\n";
            }
            $body .= "\nNo published data or XSD was activated automatically.\n";
            file_put_contents($summary, $body, FILE_APPEND);
        }
        if ($newVersions !== [] || count(array_filter(
            $results,
            static fn (array $entry): bool => $entry['status'] === 'changed'
        )) > 0) {
            fwrite(STDERR, "Official files changed: review before updating any fiscal contract\n");
            exit(3);
        }
    } else {
        throw new InvalidArgumentException(
            "Usage: php tools/bin/domains.php {generate|check|observe-source|contract-matrix|download|audit|verify-indicators|watch} --name=PATH\n"
            . "generate/check: --annex-a= --annex-b= --annex-c= --output= [--annex-vii= --expected-vii=N]\n"
            . "contract-matrix: --legacy-annex=PATH --annex-vi=PATH --output=PATH\n"
            . "download: --ids=annex-a,annex-b,... --output=DIR [--manifest=PATH]\n"
            . "audit: --annex-vi= --annex-vii= [--report=] OR --download-dir= [--report=]\n"
            . "verify-indicators: --annex-vii=PATH [--snapshot=PATH]\n"
            . "watch: [--manifest=PATH --report=PATH]\n",
        );
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
