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
        self::assertSame(1, $comparison['production']['xsd_count']);
        self::assertSame(2, $comparison['restricted']['xsd_count']);
        self::assertSame(hash('sha256', $production), $comparison['production']['sha256']);
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
