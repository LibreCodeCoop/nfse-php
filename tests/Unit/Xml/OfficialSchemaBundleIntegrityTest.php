<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Xml;

use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * Guards the vendored official schema snapshot against silent byte changes.
 */
final class OfficialSchemaBundleIntegrityTest extends TestCase
{
    /**
     * @var array<string, string>
     */
    private const EXPECTED_GIT_BLOBS = [
        'DPS_v1.01.xsd' => '39f440dd46db4e15a378b60910a3ac0baf19e476',
        'tiposComplexos_v1.01.xsd' => '91f44b804ddcba93c65f7dea923ca4621c1dcd6a',
        'tiposSimples_v1.01.xsd' => '6cf3dcb9c085e284a9b78b9a6a4c2688a95df5e9',
        'xmldsig-core-schema.xsd' => '8a9c9139d0cb2c3497ce67942ba7d1e8528241d0',
        'pedRegEvento_v1.01.xsd' => 'd8d3db9afdb2b79d6c32dd6d2ed727dcc6e22a6a',
        'tiposEventos_v1.01.xsd' => '1a568683f6cb5e1ca65b5827477d50884409bd43',
    ];

    public function testOfficialSchemaFilesMatchTheDocumentedSnapshot(): void
    {
        $schemaDirectory = dirname(__DIR__, 3) . '/references/schemas/nfse/1.01';

        foreach (self::EXPECTED_GIT_BLOBS as $file => $expectedBlob) {
            $path = $schemaDirectory . '/' . $file;
            self::assertFileExists($path);

            $contents = file_get_contents($path);
            self::assertIsString($contents);

            $actualBlob = sha1('blob ' . strlen($contents) . "\0" . $contents);

            self::assertSame(
                $expectedBlob,
                $actualBlob,
                'Official schema changed without an explicit integrity-manifest update: ' . $file,
            );
        }
    }
}
