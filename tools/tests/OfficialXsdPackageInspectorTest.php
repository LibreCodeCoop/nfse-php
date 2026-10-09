<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tools\Tests;

use LibreCodeCoop\NfsePHP\Tools\OfficialXsdPackageInspector;
use PHPUnit\Framework\TestCase;

final class OfficialXsdPackageInspectorTest extends TestCase
{
    public function testSameVersionMayContainDistinctXsdBytes(): void
    {
        $production = $this->zip(['DPS_v1.01.xsd' => '<xs:schema>old</xs:schema>']);
        $restricted = $this->zip([
            'DPS_v1.01.xsd' => '<xs:schema>new</xs:schema>',
            'new_types_v1.01.xsd' => '<xs:schema/>',
        ]);
        $inspector = new OfficialXsdPackageInspector();
        $comparison = $inspector->compareOfficial(
            'https://www.gov.br/nfse/producao/esquemas-v1.01.zip',
            'https://www.gov.br/nfse/homologacao/esquemas-v1.01.zip',
            static fn (string $url): string => str_contains($url, '/producao/') ? $production : $restricted
        );

        self::assertSame('different', $comparison['file_comparison']['DPS_v1.01.xsd']);
        self::assertSame('restricted-only', $comparison['file_comparison']['new_types_v1.01.xsd']);
        self::assertSame('different', $comparison['basename_comparison']['DPS_v1.01.xsd']);
        self::assertSame('restricted-only', $comparison['basename_comparison']['new_types_v1.01.xsd']);
        self::assertSame(1, $comparison['production']['xsd_count']);
        self::assertSame(2, $comparison['restricted']['xsd_count']);
        self::assertSame(hash('sha256', $production), $comparison['production']['sha256']);
    }

    public function testSameBasenameInDifferentFoldersIsPreserved(): void
    {
        $zip = $this->zip([
            'Schemas/DPS_v1.01.xsd' => '<xs:schema>one</xs:schema>',
            'Archive/DPS_v1.01.xsd' => '<xs:schema>two</xs:schema>',
        ]);
        $files = (new OfficialXsdPackageInspector())->inspect($zip);
        self::assertSame(2, $files['xsd_count']);
        self::assertNotSame(
            $files['files']['Schemas/DPS_v1.01.xsd'],
            $files['files']['Archive/DPS_v1.01.xsd']
        );
    }

    public function testSameSchemaBytesAreRecognizedAcrossDifferentArchiveDirectories(): void
    {
        $production = $this->zip(['A/DPS_v1.01.xsd' => '<xs:schema>identical</xs:schema>']);
        $restricted = $this->zip(['B/DPS_v1.01.xsd' => '<xs:schema>identical</xs:schema>']);
        $comparison = (new OfficialXsdPackageInspector())->compareOfficial(
            'https://www.gov.br/nfse/production.zip',
            'https://www.gov.br/nfse/restricted.zip',
            static fn (string $url): string => str_contains($url, 'production') ? $production : $restricted
        );
        self::assertSame('identical', $comparison['basename_comparison']['DPS_v1.01.xsd']);
        self::assertSame('production-only', $comparison['file_comparison']['A/DPS_v1.01.xsd']);
        self::assertSame('restricted-only', $comparison['file_comparison']['B/DPS_v1.01.xsd']);
    }

    public function testPinnedManifestRejectsChangedOfficialArchiveBytes(): void
    {
        $production = $this->zip(['DPS_v1.01.xsd' => '<xs:schema>prod</xs:schema>']);
        $restricted = $this->zip(['DPS_v1.01.xsd' => '<xs:schema>test</xs:schema>']);
        $file = tempnam(sys_get_temp_dir(), 'nfse-schema-source');
        self::assertIsString($file);
        try {
            file_put_contents($file, json_encode([
                'sources' => [
                    ['id' => 'xsd-production-20260209', 'type' => 'zip',
                        'url' => 'https://www.gov.br/nfse/p.zip',
                        'observed_sha256' => hash('sha256', $production)],
                    ['id' => 'xsd-restricted-20260727', 'type' => 'zip',
                        'url' => 'https://www.gov.br/nfse/r.zip',
                        'observed_sha256' => hash('sha256', $restricted)],
                ],
            ], JSON_THROW_ON_ERROR));
            $inspector = new OfficialXsdPackageInspector();
            $fetch = static fn (string $url): string => str_ends_with($url, '/p.zip') ? $production : $restricted;
            self::assertSame('different', $inspector->comparePinned($file, $fetch)[
                'basename_comparison'
            ]['DPS_v1.01.xsd']);
            $this->expectException(\UnexpectedValueException::class);
            $this->expectExceptionMessage('XSD bytes changed');
            $inspector->comparePinned(
                $file,
                static fn (string $url): string => $restricted
            );
        } finally {
            unlink($file);
        }
    }

    public function testForbidsUntrustedSchemaSource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only official NFS-e');
        (new OfficialXsdPackageInspector())->compareOfficial(
            'https://example.com/schema.zip',
            'https://www.gov.br/nfse/schema.zip',
            static fn (string $url): string => 'must-not-fetch'
        );
    }

    public function testRejectsHtmlAndArchivesWithoutXsd(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('No XSD files');
        (new OfficialXsdPackageInspector())->inspect($this->zip(['readme.txt' => 'not a schema']));
    }

    /**
     * @param array<string,string> $entries
     */
    private function zip(array $entries): string
    {
        $file = tempnam(sys_get_temp_dir(), 'nfse-xsd-test');
        self::assertIsString($file);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($file, \ZipArchive::OVERWRITE) === true);
        foreach ($entries as $path => $content) {
            self::assertTrue($zip->addFromString($path, $content));
        }
        $zip->close();
        $result = file_get_contents($file);
        unlink($file);
        self::assertIsString($result);

        return $result;
    }
}
