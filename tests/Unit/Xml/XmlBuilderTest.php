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
        $dps = $this->makeDps(cnpjPrestador: '29842527000145');
        $xml = $this->builder->buildDps($dps);

        self::assertStringContainsString('29842527000145', $xml);
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

    private function makeDps(
        string $cnpjPrestador = '29842527000145',
        string $municipioIbge = '3303302',
        string $itemListaServico = '0107',
        string $valorServico = '1000.00',
        string $aliquota = '5.00',
        string $discriminacao = 'Consultoria em TI',
        bool $issRetido = false,
    ): DpsData {
        return new DpsData(
            cnpjPrestador:   $cnpjPrestador,
            municipioIbge:   $municipioIbge,
            itemListaServico: $itemListaServico,
            valorServico:    $valorServico,
            aliquota:        $aliquota,
            discriminacao:   $discriminacao,
            issRetido:       $issRetido,
        );
    }
}
