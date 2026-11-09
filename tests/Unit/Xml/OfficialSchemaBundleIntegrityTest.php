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
        'NFSe_v1.01.xsd' => 'd7bf8578b8fd17883a9b9feffe80b2b4c370466b',
    ];

    /** @var array<string, string> */
    private const EXPECTED_RESTRICTED_GIT_BLOBS = [
        'CNC_v1.00.xsd' => '47cddb048bde8bdd1aff368762f7ce38b4aae14c',
        'DPS_v1.01.xsd' => '39f440dd46db4e15a378b60910a3ac0baf19e476',
        'NFSe_v1.01.xsd' => '171ac2bbd9dd3b60a43c455518c8d124af70a09a',
        'evento_v1.01.xsd' => 'a67393795c6c9f6295fda86d0cb6905c464bffc9',
        'pedRegEvento_v1.01.xsd' => '26fe4bdd8f21b76fab9381d18e819f11efb41e38',
        'tiposCnc_v1.00.xsd' => '27d839ba775263fe61d3775f8881bf8589661d64',
        'tiposComplexos_v1.01.xsd' => '11413584da9591609d5898b8e1e1dded23fb3376',
        'tiposEventos_v1.01.xsd' => '52c1ef932fa7c798d84eccdeb0822c347c6665f4',
        'tiposSimples_v1.01.xsd' => '98b10f126d2c16e4d282fc46f8527e7c6692d347',
        'xmldsig-core-schema.xsd' => 'c4e034bf7de2a1a325b1b901bbf8bbfb2adc63df',
    ];

    public function testOctoberRestrictedSchemaFilesMatchOfficialSnapshot(): void
    {
        $directory = dirname(__DIR__, 3) . '/references/schemas/nfse/1.01-20261020';
        foreach (self::EXPECTED_RESTRICTED_GIT_BLOBS as $file => $expectedBlob) {
            $path = $directory . '/' . $file;
            self::assertFileExists($path);
            $contents = file_get_contents($path);
            self::assertIsString($contents);
            self::assertSame(
                $expectedBlob,
                sha1('blob ' . strlen($contents) . "\0" . $contents),
                'Official restricted schema changed without integrity review: ' . $file,
            );
        }
    }

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
