<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\Config\DanfseConfig;
use LibreCodeCoop\NfsePHP\Danfse\Config\MunicipalityBranding;
use LibreCodeCoop\NfsePHP\Danfse\DanfseGenerator;
use LibreCodeCoop\NfsePHP\Exception\ArtifactException;
use LibreCodeCoop\NfsePHP\Exception\NfseErrorCode;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * @covers \LibreCodeCoop\NfsePHP\Danfse\DanfseGenerator
 */
class DanfseGeneratorTest extends TestCase
{
    private string $xml;

    protected function setUp(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/nfse_exemplo.xml');
        self::assertNotFalse($xml);
        $this->xml = $xml;
    }

    public function testGenerateFromXmlReturnsPdfBinary(): void
    {
        $pdf = (new DanfseGenerator())->generateFromXml($this->xml);

        self::assertStringStartsWith('%PDF-', $pdf);
    }

    public function testGeneratedPdfSizeIsReasonable(): void
    {
        $pdf = (new DanfseGenerator())->generateFromXml($this->xml);

        $size = strlen($pdf);
        self::assertGreaterThan(1000, $size);
        self::assertLessThan(5_000_000, $size);
    }

    public function testGenerateWithMunicipalityConfig(): void
    {
        $config = new DanfseConfig(
            municipality: new MunicipalityBranding(
                name: 'Prefeitura de Niterói',
                department: 'Secretaria Municipal de Fazenda',
                email: 'iss@fazenda.niteroi.rj.gov.br',
            ),
        );

        $pdf = (new DanfseGenerator($config))->generateFromXml($this->xml);

        self::assertStringStartsWith('%PDF-', $pdf);
    }

    public function testGenerateHtmlContainsHomologacaoWatermark(): void
    {
        $xml = str_replace('<tpAmb>1</tpAmb>', '<tpAmb>2</tpAmb>', $this->xml);

        $html = (new DanfseGenerator())->generateHtml($xml);

        self::assertStringContainsString('HOMOLOGAÇÃO', $html);
    }

    public function testInvalidXmlThrowsArtifactException(): void
    {
        try {
            (new DanfseGenerator())->generateFromXml('not-valid-xml');
            self::fail('Expected ArtifactException');
        } catch (ArtifactException $e) {
            self::assertSame(NfseErrorCode::ArtifactRetrievalFailed, $e->errorCode);
        }
    }

    public function testWellFormedNonNfseXmlThrowsArtifactException(): void
    {
        try {
            (new DanfseGenerator())->generateHtml('<foo/>');
            self::fail('Expected ArtifactException');
        } catch (ArtifactException $e) {
            self::assertSame(NfseErrorCode::ArtifactRetrievalFailed, $e->errorCode);
        }
    }

    public function testDpsOnlyXmlThrowsArtifactException(): void
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <DPS versao="1.01" xmlns="http://www.sped.fazenda.gov.br/nfse">
                <infDPS Id="DPS330330211222333000181202601000000000000005">
                    <tpAmb>2</tpAmb>
                </infDPS>
            </DPS>
            XML;

        try {
            (new DanfseGenerator())->generateHtml($xml);
            self::fail('Expected ArtifactException');
        } catch (ArtifactException $e) {
            self::assertSame(NfseErrorCode::ArtifactRetrievalFailed, $e->errorCode);
        }
    }
}
