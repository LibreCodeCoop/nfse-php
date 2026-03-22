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
    }

    public function testBuildDpsContainsValorServico(): void
    {
        $dps = $this->makeDps(valorServico: '1500.00');
        $xml = $this->builder->buildDps($dps);

        self::assertStringContainsString('1500.00', $xml);
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
        $dpsRetido  = $this->makeDps(issRetido: true);
        $dpsProprio = $this->makeDps(issRetido: false);

        $xmlRetido  = $this->builder->buildDps($dpsRetido);
        $xmlProprio = $this->builder->buildDps($dpsProprio);

        self::assertStringContainsString('<tribISSQN>2</tribISSQN>', $xmlRetido);
        self::assertStringContainsString('<tribISSQN>1</tribISSQN>', $xmlProprio);
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

        $nodes = $xpath->query('//n:regEspTrib');
        self::assertSame(1, $nodes->length, '<regEspTrib> expected when regimeEspecialTributacao is set');
        self::assertSame('1', $nodes->item(0)->textContent);
    }

    public function testRegimeEspecialTributacaoIsAbsentWhenNull(): void
    {
        $dps = $this->makeDps(regimeEspecialTributacao: null);
        $xml = $this->builder->buildDps($dps);

        $doc   = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');

        $nodes = $xpath->query('//n:regEspTrib');
        self::assertSame(0, $nodes->length, '<regEspTrib> must be absent when null');
    }

    // -------------------------------------------------------------------------

    private function makeDps(
        string $cnpjPrestador = '11222333000181',
        string $municipioIbge = '3303302',
        string $itemListaServico = '0107',
        string $valorServico = '1000.00',
        string $aliquota = '5.00',
        string $discriminacao = 'Consultoria em TI',
        bool $issRetido = false,
        string $documentoTomador = '',
        string $nomeTomador = '',
        ?int $regimeEspecialTributacao = null,
    ): DpsData {
        return new DpsData(
            cnpjPrestador:            $cnpjPrestador,
            municipioIbge:            $municipioIbge,
            itemListaServico:         $itemListaServico,
            valorServico:             $valorServico,
            aliquota:                 $aliquota,
            discriminacao:            $discriminacao,
            documentoTomador:         $documentoTomador,
            nomeTomador:              $nomeTomador,
            regimeEspecialTributacao: $regimeEspecialTributacao,
            issRetido:                $issRetido,
        );
    }
}
