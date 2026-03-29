<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Xml;

use LibreCodeCoop\NfsePHP\Dto\DpsData;
use LibreCodeCoop\NfsePHP\Tests\TestCase;
use LibreCodeCoop\NfsePHP\Xml\XmlBuilder;

/**
 * @covers \LibreCodeCoop\NfsePHP\Xml\XmlBuilder
 */
class XmlBuilderTest extends TestCase
{
    private XmlBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new XmlBuilder();
    }

    public function testBuildDpsReturnsWellFormedXml(): void
    {
        $dps = $this->makeDps();
        $xml = $this->builder->buildDps($dps);

        self::assertNotEmpty($xml);

        $doc = new \DOMDocument();
        self::assertTrue($doc->loadXML($xml), 'Generated XML must be valid XML');
    }

    public function testBuildDpsSetsSchemaVersionAttribute(): void
    {
        $xml = $this->builder->buildDps($this->makeDps());

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        self::assertSame('1.01', $doc->documentElement?->getAttribute('versao'));
    }

    public function testBuildDpsUsesOfficialIdentifierShape(): void
    {
        $xml = $this->builder->buildDps($this->makeDps(cnpjPrestador: '11222333000181', municipioIbge: '3303302', serie: '12', numeroDps: '345'));

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        self::assertSame(
            'DPS330330221122233300018100012000000000000345',
            $doc->getElementsByTagName('infDPS')->item(0)?->attributes?->getNamedItem('Id')?->nodeValue,
        );
    }

    public function testBuildDpsContainsCnpjPrestador(): void
    {
        $dps = $this->makeDps(cnpjPrestador: '11222333000181');
        $xml = $this->builder->buildDps($dps);

        self::assertStringContainsString('11222333000181', $xml);
    }

    public function testBuildDpsContainsMunicipioIbge(): void
    {
        $dps = $this->makeDps(municipioIbge: '3303302');
        $xml = $this->builder->buildDps($dps);

        self::assertStringContainsString('3303302', $xml);
        self::assertStringContainsString('<cLocEmi>3303302</cLocEmi>', $xml);
    }

    public function testBuildDpsUsesNationalTaxCodeInCtribnac(): void
    {
        $dps = $this->makeDps(itemListaServico: '0107', codigoTributacaoNacional: '101011');
        $xml = $this->builder->buildDps($dps);

        self::assertStringContainsString('<cServ><cTribNac>101011</cTribNac></cServ>', str_replace(["\n", '  '], '', $xml));
    }

    public function testBuildDpsContainsValorServico(): void
    {
        $dps = $this->makeDps(valorServico: '1500.00');
        $xml = $this->builder->buildDps($dps);

        self::assertStringContainsString('1500.00', $xml);
        self::assertStringContainsString('<vServPrest><vServ>1500.00</vServ></vServPrest>', str_replace(["\n", '  '], '', $xml));
    }

    public function testDiscriminacaoIsXmlEscaped(): void
    {
        $dps = $this->makeDps(discriminacao: 'Serviço de <consultoria> & TI');
        $xml = $this->builder->buildDps($dps);

        // The raw characters must NOT appear unescaped
        self::assertStringNotContainsString('<consultoria>', $xml);
        self::assertStringContainsString('&lt;consultoria&gt;', $xml);
    }

    public function testIssRetidoSetsTribCode(): void
    {
        $dpsRetido  = $this->makeDps(issRetido: true, tipoRetencaoIss: 2);
        $dpsProprio = $this->makeDps(issRetido: false, tipoRetencaoIss: 1);

        $xmlRetido  = $this->builder->buildDps($dpsRetido);
        $xmlProprio = $this->builder->buildDps($dpsProprio);

        self::assertStringContainsString('<tribISSQN>2</tribISSQN>', $xmlRetido);
        self::assertStringContainsString('<tribISSQN>1</tribISSQN>', $xmlProprio);
        self::assertStringContainsString('<tpRetISSQN>2</tpRetISSQN>', $xmlRetido);
        self::assertStringContainsString('<tpRetISSQN>1</tpRetISSQN>', $xmlProprio);
    }

    // -------------------------------------------------------------------------

    public function testTomadorCnpjBlockIsIncludedWhenDocumentHas14Digits(): void
    {
        $dps = $this->makeDps(documentoTomador: '12345678000195', nomeTomador: 'Empresa Tomadora S.A.');
        $xml = $this->builder->buildDps($dps);

        $doc   = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');

        $nodes = $xpath->query('//n:toma/n:CNPJ');
        self::assertSame(1, $nodes->length, '<toma><CNPJ> block expected');
        self::assertSame('12345678000195', $nodes->item(0)->textContent);

        $nameNodes = $xpath->query('//n:toma/n:xNome');
        self::assertSame(1, $nameNodes->length, '<toma><xNome> block expected');
        self::assertSame('Empresa Tomadora S.A.', $nameNodes->item(0)->textContent);
    }

    public function testTomadorCpfBlockIsIncludedWhenDocumentHas11Digits(): void
    {
        $dps = $this->makeDps(documentoTomador: '12345678901', nomeTomador: 'Pessoa Física Tomadora');
        $xml = $this->builder->buildDps($dps);

        $doc   = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');

        $nodes = $xpath->query('//n:toma/n:CPF');
        self::assertSame(1, $nodes->length, '<toma><CPF> block expected for 11-digit doc');
        self::assertSame('12345678901', $nodes->item(0)->textContent);
    }

    public function testTomadorBlockIsAbsentWhenDocumentoTomadorIsEmpty(): void
    {
        $dps = $this->makeDps(documentoTomador: '');
        $xml = $this->builder->buildDps($dps);

        $doc   = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');

        $nodes = $xpath->query('//n:toma');
        self::assertSame(0, $nodes->length, '<toma> block must be absent when no buyer document');
    }

    public function testRegimeEspecialTributacaoIsIncludedWhenSet(): void
    {
        $dps = $this->makeDps(regimeEspecialTributacao: 1);
        $xml = $this->builder->buildDps($dps);

        $doc   = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');

        $nodes = $xpath->query('//n:prest/n:regTrib/n:regEspTrib');
        self::assertSame(1, $nodes->length, '<prest><regTrib><regEspTrib> expected when regimeEspecialTributacao is set');
        self::assertSame('1', $nodes->item(0)->textContent);
    }

    public function testBuildDpsAlwaysIncludesProviderTaxRegime(): void
    {
        $dps = $this->makeDps(regimeEspecialTributacao: 0);
        $xml = $this->builder->buildDps($dps);

        $doc   = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');

        $nodes = $xpath->query('//n:prest/n:regTrib/n:regEspTrib');
        self::assertSame(1, $nodes->length, '<prest><regTrib><regEspTrib> must always be present');
        self::assertSame('0', $nodes->item(0)->textContent);
    }

    // -------------------------------------------------------------------------

    public function testNonSimplesnacionalMustNotIncludeIndtottribAndPaliq(): void
    {
        // opSimpNac=1 = "não optante" (per SEFIN error messages)
        $dpsNaoOptante = $this->makeDps(
            cnpjPrestador: '11222333000181',
            municipioIbge: '3303302',
            itemListaServico: '0107',
            valorServico: '1000.00',
            aliquota: '5.00',
            opcaoSimplesNacional: 1, // não optante
        );
        $xml = $this->builder->buildDps($dpsNaoOptante);

        // For "não optante" (opSimpNac = 1), pAliq must NOT be present
        // but indTotTrib is now ALWAYS included (even if 0) to avoid schema validation errors
        self::assertStringNotContainsString('<pAliq>', $xml);
        self::assertStringContainsString('<indTotTrib>', $xml);

        // totTrib container must exist with content
        self::assertStringContainsString('<totTrib', $xml);

        // tribMun and tribISSQN must still exist
        self::assertStringContainsString('<tribMun>', $xml);
        self::assertStringContainsString('<tribISSQN>', $xml);
    }

    public function testOptiontSimplesnacionalIncludesIndtottribAndPaliq(): void
    {
        // opSimpNac=2 = optante de Simples Nacional (inverse naming)
        $dpsOptante = $this->makeDps(
            cnpjPrestador: '11222333000181',
            municipioIbge: '3303302',
            itemListaServico: '0107',
            valorServico: '1000.00',
            aliquota: '5.00',
            opcaoSimplesNacional: 2, // optante
            indicadorTributacao: 1,
        );
        $xml = $this->builder->buildDps($dpsOptante);

        // For "optante" (opSimpNac = 2), indTotTrib and pAliq MUST be present
        self::assertStringContainsString('<indTotTrib>', $xml);
        self::assertStringContainsString('<pAliq>', $xml);
    }

    // -------------------------------------------------------------------------

    private function makeDps(
        string $cnpjPrestador = '11222333000181',
        string $municipioIbge = '3303302',
        string $itemListaServico = '0107',
        string $codigoTributacaoNacional = '000000',
        string $valorServico = '1000.00',
        string $aliquota = '5.00',
        string $discriminacao = 'Consultoria em TI',
        string $serie = '00001',
        string $numeroDps = '1',
        bool $issRetido = false,
        string $documentoTomador = '',
        string $nomeTomador = '',
        int $regimeEspecialTributacao = 0,
        int $tipoRetencaoIss = 1,
        int $opcaoSimplesNacional = 1,
        int $indicadorTributacao = 0,
    ): DpsData {
        return new DpsData(
            cnpjPrestador:            $cnpjPrestador,
            municipioIbge:            $municipioIbge,
            itemListaServico:         $itemListaServico,
            valorServico:             $valorServico,
            aliquota:                 $aliquota,
            discriminacao:            $discriminacao,
            serie:                    $serie,
            numeroDps:                $numeroDps,
            codigoTributacaoNacional: $codigoTributacaoNacional,
            documentoTomador:         $documentoTomador,
            nomeTomador:              $nomeTomador,
            regimeEspecialTributacao: $regimeEspecialTributacao,
            tipoRetencaoIss:          $tipoRetencaoIss,
            issRetido:                $issRetido,
            opcaoSimplesNacional:     $opcaoSimplesNacional,
            indicadorTributacao:      $indicadorTributacao,
        );
    }
}
