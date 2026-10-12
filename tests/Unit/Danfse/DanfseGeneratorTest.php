<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\Config\DanfseConfig;
use LibreCodeCoop\NfsePHP\Danfse\Config\DanfseFiscalState;
use LibreCodeCoop\NfsePHP\Danfse\Config\DanfseFonts;
use LibreCodeCoop\NfsePHP\Danfse\Config\MunicipalityBranding;
use LibreCodeCoop\NfsePHP\Danfse\DanfseGenerator;
use LibreCodeCoop\NfsePHP\Exception\ArtifactException;
use LibreCodeCoop\NfsePHP\Exception\NfseErrorCode;
use LibreCodeCoop\NfsePHP\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

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

    public function testFontConfigurationRejectsMissingLocalFiles(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DanfseFonts(
            '/not-a-real-folder/arial.ttf',
            '/not-a-real-folder/arialbd.ttf',
            '/not-a-real-folder/micross.ttf',
            sys_get_temp_dir(),
        );
    }

    public function testLocalFontFilesCanBeRegisteredWithoutRemoteLoading(): void
    {
        // Dompdf's redistributable bundled fonts are safe stand-ins for
        // proving the loading mechanism, NOT Arial/MSG or NT008 compliance.
        $fontsPath = dirname(__DIR__, 3) . '/vendor/dompdf/dompdf/lib/fonts';
        $regular = $fontsPath . '/DejaVuSans.ttf';
        $bold = $fontsPath . '/DejaVuSans-Bold.ttf';
        self::assertFileExists($regular);
        self::assertFileExists($bold);

        $cache = sys_get_temp_dir() . '/nfse-danfse-fonts-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($cache, 0700));
        $fonts = new DanfseFonts($regular, $bold, $regular, $cache);
        self::assertCount(1, $fonts->directories());

        $generator = new DanfseGenerator(new DanfseConfig(fonts: $fonts));
        $html = $generator->generateHtml($this->xml);
        self::assertStringContainsString('font-family: "Arial"', $html);
        self::assertStringContainsString('font-family: "Microsoft Sans Serif"', $html);
        self::assertStringContainsString('src: url("file://', $html);
        self::assertStringNotContainsString('data:font/', $html);

        $pdf = $generator->generateFromXml($this->xml);
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
                email: 'issuer@example.org',
            ),
        );

        $pdf = (new DanfseGenerator($config))->generateFromXml($this->xml);

        self::assertStringStartsWith('%PDF-', $pdf);
    }

    public function testFiscalWatermarkRequiresExplicitAuthorizedExternalState(): void
    {
        $generator = new DanfseGenerator();
        $html = $generator->generateHtml($this->xml);
        self::assertStringNotContainsString('class="fiscal-state-watermark"', $html);

        self::assertStringNotContainsString('CANCELADA', $html);
        self::assertStringNotContainsString('SUBSTITUÍDA', $html);
    }

    public function testCancelledFiscalStateRendersAtLeastFiftyPointWatermark(): void
    {
        $config = new DanfseConfig(fiscalState: DanfseFiscalState::Cancelled);
        $html = (new DanfseGenerator($config))->generateHtml($this->xml);
        self::assertStringContainsString('class="fiscal-state-watermark">CANCELADA</div>', $html);
        self::assertStringContainsString('font-size: 50pt;', $html);
        self::assertStringStartsWith('%PDF-', (new DanfseGenerator($config))->generateFromXml($this->xml));
    }

    public function testSubstitutedFiscalStateRendersExplicitWatermark(): void
    {
        $config = new DanfseConfig(fiscalState: DanfseFiscalState::Substituted);
        $html = (new DanfseGenerator($config))->generateHtml($this->xml);
        self::assertStringContainsString('class="fiscal-state-watermark">SUBSTITUÍDA</div>', $html);
    }

    public function testLongSyntheticServiceKeepsExplicitEllipsisAndOnePageHtml(): void
    {
        $longDescription = str_repeat('DESCRICAO SINTETICA DE SERVICO E ETAPAS. ', 140);
        $xml = preg_replace(
            '~<xDescServ>[^<]*</xDescServ>~',
            '<xDescServ>' . $longDescription . '</xDescServ>',
            $this->xml,
        );
        self::assertIsString($xml);

        $generator = new DanfseGenerator();
        $html = $generator->generateHtml($xml);
        self::assertStringContainsString('DESCRICAO SINTETICA DE SERVICO', $html);
        self::assertStringContainsString('...', $html);
        self::assertStringContainsString('Totais aproximados dos Tributos', $html);
        self::assertStringStartsWith('%PDF-', $generator->generateFromXml($xml));
    }

    public function testLongSyntheticComplementaryInformationPreservesTaxDisclosure(): void
    {
        $longNotes = str_repeat('OBSERVACAO SINTETICA DO SERVICO. ', 140);
        $xml = preg_replace(
            '~<xInfComp>[^<]*</xInfComp>~',
            '<xInfComp>' . $longNotes . '</xInfComp>',
            $this->xml,
        );
        self::assertIsString($xml);

        $html = (new DanfseGenerator())->generateHtml($xml);
        self::assertStringContainsString('OBSERVACAO SINTETICA DO SERVICO', $html);
        self::assertStringContainsString('...', $html);
        self::assertStringContainsString('Totais aproximados dos Tributos', $html);
        self::assertStringContainsString('Federais: 4.50%', $html);
    }

    public function testGenerateHtmlContainsHomologacaoWatermark(): void
    {
        $xml = str_replace('<tpAmb>1</tpAmb>', '<tpAmb>2</tpAmb>', $this->xml);

        $html = (new DanfseGenerator())->generateHtml($xml);

        self::assertStringContainsString('HOMOLOGAÇÃO', $html);
    }

    /**
     * @param callable(): mixed $generate
     */
    #[DataProvider('invalidXmlProvider')]
    public function testInvalidXmlThrowsArtifactException(callable $generate): void
    {
        try {
            $generate();
            self::fail('Expected ArtifactException');
        } catch (ArtifactException $e) {
            self::assertSame(NfseErrorCode::ArtifactRetrievalFailed, $e->errorCode);
        }
    }

    /**
     * @return array<string, array{callable(): mixed}>
     */
    public static function invalidXmlProvider(): array
    {
        $dpsOnlyXml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <DPS versao="1.01" xmlns="http://www.sped.fazenda.gov.br/nfse">
                <infDPS Id="DPS330330211222333000181202601000000000000005">
                    <tpAmb>2</tpAmb>
                </infDPS>
            </DPS>
            XML;

        return [
            'malformed xml' => [static fn () => (new DanfseGenerator())->generateFromXml('not-valid-xml')],
            'well-formed non-nfse xml' => [static fn () => (new DanfseGenerator())->generateHtml('<foo/>')],
            'dps-only xml' => [static fn () => (new DanfseGenerator())->generateHtml($dpsOnlyXml)],
        ];
    }
}
