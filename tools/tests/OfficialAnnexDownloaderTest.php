<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tools\Tests;

use LibreCodeCoop\NfsePHP\Tools\OfficialAnnexDownloader;
use PHPUnit\Framework\TestCase;

final class OfficialAnnexDownloaderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/nfse-download-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/annexes/*') ?: [] as $path) {
            unlink($path);
        }
        if (is_dir($this->directory . '/annexes')) {
            rmdir($this->directory . '/annexes');
        }
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($this->directory);
    }

    public function testDownloadsOnlySelectedChecksumPinnedAnnexesWithoutNetwork(): void
    {
        $a = "PK\x03\x04" . 'official-source-a';
        $b = "PK\x03\x04" . 'official-source-b';
        $manifest = $this->manifest([
            $this->entry('annex-a', $a),
            $this->entry('annex-b', $b),
        ]);
        $seen = [];
        $results = (new OfficialAnnexDownloader())->download(
            $manifest,
            ['annex-b', 'annex-a'],
            $this->directory . '/annexes',
            static function (string $url) use (&$seen, $a, $b): string {
                $seen[] = $url;

                return str_ends_with($url, '/annex-a.xlsx') ? $a : $b;
            }
        );
        self::assertCount(2, $seen);
        self::assertSame($b, file_get_contents($results['annex-b']['path']));
        self::assertSame($a, file_get_contents($results['annex-a']['path']));
        self::assertSame(hash('sha256', $b), $results['annex-b']['sha256']);
    }

    public function testAChangedOfficialChecksumIsRejectedBeforeWritingAnyFile(): void
    {
        $manifest = $this->manifest([$this->entry('annex-a', "PK\x03\x04original")]);
        try {
            (new OfficialAnnexDownloader())->download(
                $manifest,
                ['annex-a'],
                $this->directory . '/annexes',
                static fn (string $url): string => "PK\x03\x04changed"
            );
            self::fail('Changed official source must be rejected');
        } catch (\UnexpectedValueException $error) {
            self::assertStringContainsString('SHA-256 changed', $error->getMessage());
            self::assertDirectoryDoesNotExist($this->directory . '/annexes');
        }
    }

    public function testRejectsHtmlDisguisedAsOfficialXlsx(): void
    {
        $manifest = $this->manifest([$this->entry('annex-a', "PK\x03\x04original")]);
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid official XLSX');
        (new OfficialAnnexDownloader())->download(
            $manifest,
            ['annex-a'],
            $this->directory . '/annexes',
            static fn (string $url): string => '<html>redirected portal</html>'
        );
    }

    public function testRejectsForeignAndUnknownManifestSourcesBeforeDownload(): void
    {
        $source = $this->entry('annex-a', "PK\x03\x04original");
        $source['url'] = 'https://example.com/nfse/annex-a.xlsx';
        $manifest = $this->manifest([$source]);
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Non-official source');
        (new OfficialAnnexDownloader())->download(
            $manifest,
            ['annex-a'],
            $this->directory . '/annexes',
            static fn (string $url): string => 'must-not-be-called'
        );
    }

    public function testRefusesUnlistedOrDuplicatedIdentifiers(): void
    {
        $manifest = $this->manifest([$this->entry('annex-a', "PK\x03\x04original")]);
        $downloader = new OfficialAnnexDownloader();
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('missing from manifest');
        $downloader->sources($manifest, ['annex-x']);
    }

    public function testRejectsDuplicateRequestedIdsBeforeFetching(): void
    {
        $manifest = $this->manifest([$this->entry('annex-a', "PK\x03\x04official")]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('distinct official annex IDs');
        (new OfficialAnnexDownloader())->download(
            $manifest,
            ['annex-a', 'annex-a'],
            $this->directory . '/annexes',
            static fn (string $url): string => 'should not download'
        );
    }

    public function testRequiresObservedSourceChecksumInTheManifest(): void
    {
        $source = $this->entry('annex-a', "PK\x03\x04official");
        $source['observed_sha256'] = '';
        $manifest = $this->manifest([$source]);
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Missing valid XLSX URL or SHA-256');
        (new OfficialAnnexDownloader())->sources($manifest, ['annex-a']);
    }

    /**
     * @return array{id:string,url:string,type:string,observed_sha256:string}
     */
    private function entry(string $id, string $content): array
    {
        return [
            'id' => $id,
            'url' => 'https://www.gov.br/nfse/annexes/' . $id . '.xlsx',
            'type' => 'xlsx',
            'observed_sha256' => hash('sha256', $content),
        ];
    }

    /**
     * @param list<array<string, string>> $entries
     */
    private function manifest(array $entries): string
    {
        $filename = $this->directory . '/sources.json';
        file_put_contents($filename, json_encode(['sources' => $entries], JSON_THROW_ON_ERROR));

        return $filename;
    }
}
