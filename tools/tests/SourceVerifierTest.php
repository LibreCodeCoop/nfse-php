<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tools\Tests;

use LibreCodeCoop\NfsePHP\Tools\SourceVerifier;
use PHPUnit\Framework\TestCase;

final class SourceVerifierTest extends TestCase
{
    private string $manifest;

    protected function setUp(): void
    {
        $this->manifest = tempnam(sys_get_temp_dir(), 'nfse-sources-');
        self::assertIsString($this->manifest);
    }

    protected function tearDown(): void
    {
        unlink($this->manifest);
    }

    public function testByteLevelChecksDistinguishBaselineFromChangesWithoutNetwork(): void
    {
        $payload = "PK\x03\x04" . 'deterministic-xlsx-fixture';
        file_put_contents($this->manifest, json_encode([
            'sources' => [
                ['id' => 'annex-a', 'url' => 'https://www.gov.br/nfse/official.xlsx',
                    'type' => 'xlsx', 'version' => '1.00', 'environment' => 'production',
                    'observed_sha256' => hash('sha256', $payload)],
                ['id' => 'annex-b', 'url' => 'https://www.gov.br/nfse/missing-baseline.xlsx',
                    'type' => 'xlsx', 'version' => '1.01', 'environment' => 'production',
                    'observed_sha256' => null],
            ],
        ], JSON_THROW_ON_ERROR));

        $verifier = new SourceVerifier();
        $results = $verifier->inspect($this->manifest, static fn (string $url): string => $payload);
        self::assertSame('unchanged', $results[0]['status']);
        self::assertSame('baseline-needed', $results[1]['status']);
        self::assertSame(hash('sha256', $payload), $results[0]['sha256']);
        $other = $verifier->inspect($this->manifest,
            static fn (string $url): string => "PK\x03\x04changed");
        self::assertSame('changed', $other[0]['status']);
    }

    public function testRefusesNonGovernmentalOrUntrustedManifestUrls(): void
    {
        file_put_contents($this->manifest, json_encode([
            'sources' => [
                ['id' => 'a', 'url' => 'https://example.com/x.xlsx',
                    'type' => 'xlsx', 'observed_sha256' => null],
            ],
        ], JSON_THROW_ON_ERROR));
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Unexpected official source URL');
        (new SourceVerifier())->inspect($this->manifest,
            static fn (string $url): string => 'should not be fetched');
    }

    public function testRejectsHtmlMasqueradingAsAWorkbook(): void
    {
        file_put_contents($this->manifest, json_encode([
            'sources' => [
                ['id' => 'annex-a', 'url' => 'https://www.gov.br/nfse/a.xlsx',
                    'type' => 'xlsx', 'observed_sha256' => null],
            ],
        ], JSON_THROW_ON_ERROR));
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid xlsx download');
        (new SourceVerifier())->inspect($this->manifest,
            static fn (string $url): string => '<html>service unavailable</html>');
    }
}
