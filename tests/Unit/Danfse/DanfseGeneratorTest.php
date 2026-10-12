<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\Config\BundledDanfseFonts;
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

    public function testBundledLiberationFontsAreAvailableAndRejectTampering(): void
    {
        $fontDir = dirname(__DIR__, 3) . '/src/Danfse/Assets/fonts';
        $regular = $fontDir . '/LiberationSans-Regular.ttf';
        $bold = $fontDir . '/LiberationSans-Bold.ttf';
        self::assertFileExists($regular);
        self::assertFileExists($bold);
        self::assertSame('76d04c18ea243f426b7de1f3ad208e927008f961dc5945e5aad352d0dfde8ee8', hash_file('sha256', $regular));
        self::assertSame('788abee4c806d660e8aee46689dd8540cd4bb98da03dcc9d171ce3efd99a9173', hash_file('sha256', $bold));
        self::assertFileExists(dirname(__DIR__, 3) . '/LICENSES/OFL-1.1.txt');

        $fonts = BundledDanfseFonts::get();
        self::assertSame(realpath($regular), $fonts->arialRegular);
        self::assertSame(realpath($bold), $fonts->arialBold);

        $this->expectException(ArtifactException::class);
        BundledDanfseFonts::assertValidFile($regular, str_repeat('0', 64));
    }

    public function testBundledFontsAreEmbeddedByDefaultWithoutRemoteUrls(): void
    {
        $html = (new DanfseGenerator())->generateHtml($this->xml);
        $fonts = BundledDanfseFonts::get();
        self::assertStringContainsString($fonts->labelFamily(), $html);
        self::assertStringContainsString($fonts->valueFamily(), $html);
        self::assertStringContainsString('LiberationSans-Regular.ttf', $html);
        self::assertStringContainsString('LiberationSans-Bold.ttf', $html);
        self::assertStringContainsString('font-weight: 700', $html);
        self::assertStringNotContainsString('data:font/', $html);
        self::assertStringNotContainsString('url("http', $html);
        self::assertStringStartsWith('%PDF-', (new DanfseGenerator())->generateFromXml($this->xml));
    }

    public function testDefaultCustomDefaultAndDistinctCustomCachesRemainIsolated(): void
    {
        $path = dirname(__DIR__, 3) . '/vendor/dompdf/dompdf/lib/fonts';
        $regular = $path . '/DejaVuSans.ttf';
        $bold = $path . '/DejaVuSans-Bold.ttf';
        $firstCache = sys_get_temp_dir() . '/nfse-font-A-' . bin2hex(random_bytes(6));
        $secondCache = sys_get_temp_dir() . '/nfse-font-B-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($firstCache, 0o700));
        self::assertTrue(mkdir($secondCache, 0o700));
        $a = new DanfseFonts($regular, $bold, $regular, $firstCache);
        $b = new DanfseFonts($regular, $bold, $regular, $secondCache);
        self::assertNotSame($a->labelFamily(), $b->labelFamily());

        foreach ([null, $a, null, $b, $a, null] as $selected) {
            $config = new DanfseConfig(fonts: $selected);
            $html = (new DanfseGenerator($config))->generateHtml($this->xml);
            $family = ($selected ?? BundledDanfseFonts::get())->labelFamily();
            self::assertStringContainsString($family, $html);
            self::assertStringStartsWith('%PDF-', (new DanfseGenerator($config))->generateFromXml($this->xml));
        }
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
        self::assertTrue(mkdir($cache, 0o700));
        $fonts = new DanfseFonts($regular, $bold, $regular, $cache);
        self::assertCount(1, $fonts->directories());

        $generator = new DanfseGenerator(new DanfseConfig(fonts: $fonts));
        $html = $generator->generateHtml($this->xml);
        self::assertStringContainsString('font-family: "' . $fonts->labelFamily() . '"', $html);
        self::assertStringContainsString('font-family: "' . $fonts->valueFamily() . '"', $html);
        self::assertStringContainsString('font-family: "' . $fonts->valueFamily() . '"; font-style: normal; font-weight: 700;', $html);
        self::assertStringNotContainsString('@font-face { font-family: "Arial"', $html);
        self::assertStringContainsString('src: url("file://', $html);
        self::assertStringNotContainsString('data:font/', $html);

        $pdf = $generator->generateFromXml($this->xml);
        self::assertStringStartsWith('%PDF-', $pdf);

        // Distinct font registrations must not change the default font
        // lookup later in the SAME PHP process.
        $default = new DanfseGenerator();
        self::assertStringStartsWith('%PDF-', $default->generateFromXml($this->xml));
        $homolog = str_replace('<tpAmb>1</tpAmb>', '<tpAmb>2</tpAmb>', $this->xml);
        self::assertStringContainsString('HOMOLOGAÇÃO', $default->generateHtml($homolog));
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
