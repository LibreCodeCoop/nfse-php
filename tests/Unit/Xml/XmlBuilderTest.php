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

    public function testBuildDpsReferencesProductionV101Schema(): void
    {
        $xml = $this->builder->buildDps($this->makeDps());

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        self::assertSame(
            'http://www.sped.fazenda.gov.br/nfse DPS_v1.01.xsd',
            $doc->documentElement?->getAttributeNS(
                'http://www.w3.org/2001/XMLSchema-instance',
                'schemaLocation',
            ),
        );
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
        self::assertStringContainsString('<cLocPrestacao>3303302</cLocPrestacao>', $xml);
    }

    public function testBuildDpsStartsInfDpsWithTpAmbBeforeMunicipalityFields(): void
    {
        $xml = $this->builder->buildDps($this->makeDps(municipioIbge: '3303302'));

        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');

        $infDps = $xpath->query('//n:infDPS')->item(0);
        self::assertNotNull($infDps);

        $headerElements = [];

        foreach ($infDps?->childNodes ?? [] as $childNode) {
            if ($childNode instanceof \DOMElement) {
                $headerElements[] = $childNode->localName;
            }
        }

        self::assertSame(
            ['tpAmb', 'dhEmi', 'verAplic', 'serie', 'nDPS', 'dCompet', 'tpEmit', 'cLocEmi'],
            array_slice($headerElements, 0, 8),
        );
        self::assertSame('2', $xpath->query('//n:infDPS/n:tpAmb')->item(0)?->textContent);
        self::assertSame(0, $xpath->query('//n:infDPS/n:cMun')->length);
    }

    public function testBuildDpsUsesNationalTaxCodeInCtribnac(): void
    {
        $dps = $this->makeDps(itemListaServico: '0107', codigoTributacaoNacional: '101011');
        $xml = $this->builder->buildDps($dps);

        self::assertStringContainsString(
            '<cServ><cTribNac>101011</cTribNac><cTribMun>0107</cTribMun><xDescServ>Consultoria em TI</xDescServ></cServ>',
            str_replace(["\n", '  '], '', $xml),
        );
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

    public function testTomadorCnpjBlockAcceptsAlphanumericCnpj(): void
    {
        $dps = $this->makeDps(documentoTomador: '00000000E08G12', nomeTomador: 'Empresa Alfa S.A.');
        $xml = $this->builder->buildDps($dps);

        $doc   = new \DOMDocument();
        $doc->loadXML($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');

        $nodes = $xpath->query('//n:toma/n:CNPJ');
        self::assertSame(1, $nodes->length);
        self::assertSame('00000000E08G12', $nodes->item(0)->textContent);
    }

    public function testBuildDpsIdentifierAcceptsAlphanumericProviderCnpj(): void
    {
        $xml = $this->builder->buildDps($this->makeDps(
            cnpjPrestador: '00000000E08G12',
            municipioIbge: '3303302',
            serie: '12',
            numeroDps: '345',
        ));

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        self::assertSame(
            'DPS3303302200000000E08G1200012000000000000345',
            $doc->getElementsByTagName('infDPS')->item(0)?->attributes?->getNamedItem('Id')?->nodeValue,
        );
        self::assertStringContainsString('<CNPJ>00000000E08G12</CNPJ>', $xml);
    }

    public function testDpsIdentifierUsesCnpjRegistrationTypeInProduction(): void
    {
        $xml = $this->builder->buildDps($this->makeDps(
            tipoAmbiente: 1,
            cnpjPrestador: '11222333000181',
            municipioIbge: '3303302',
            serie: '12',
            numeroDps: '345',
        ));

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        self::assertSame(
            'DPS3303302211222333000181000120000000000000345',
            $doc->getElementsByTagName('infDPS')->item(0)?->attributes?->getNamedItem('Id')?->nodeValue,
        );
        self::assertSame('1', $doc->getElementsByTagName('tpAmb')->item(0)?->textContent);
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

    public function testTomadorAddressPhoneAndEmailAreIncludedWhenProvided(): void
    {
        $dps = $this->makeDps(
            documentoTomador: '12345678000195',
            nomeTomador: 'Empresa Tomadora S.A.',
            tomadorCodigoMunicipio: '3303302',
            tomadorCep: '24020077',
            tomadorLogradouro: 'Avenida Rio Branco',
            tomadorTelefone: '21988887777',
            tomadorEmail: 'financeiro@example.test',
        );

        $xml = $this->builder->buildDps($dps);

        self::assertStringContainsString('<toma>', $xml);
        self::assertStringContainsString('<end><endNac><cMun>3303302</cMun><CEP>24020077</CEP></endNac><xLgr>Avenida Rio Branco</xLgr></end>', str_replace(["\n", '  '], '', $xml));
        self::assertStringContainsString('<fone>21988887777</fone>', $xml);
        self::assertStringContainsString('<email>financeiro@example.test</email>', $xml);
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

        $simplesNodes = $xpath->query('//n:prest/n:regTrib/n:opSimpNac');
        self::assertSame(1, $simplesNodes->length, '<prest><regTrib><opSimpNac> must always be present');
        self::assertSame('1', $simplesNodes->item(0)->textContent);
    }

    public function testBuildDpsIncludesFederalTaxationBlockWhenConfigured(): void
    {
        $dps = $this->makeDps(
            federalPiscofinsSituacaoTributaria: '1',
            federalPiscofinsTipoRetencao: '3',
            federalPiscofinsBaseCalculo: '1000.00',
            federalPiscofinsAliquotaPis: '1.65',
            federalPiscofinsValorPis: '16.50',
            federalPiscofinsAliquotaCofins: '7.60',
            federalPiscofinsValorCofins: '76.00',
            federalValorIrrf: '15.00',
            federalValorCsll: '10.00',
            federalValorCp: '5.00',
        );

        $xml = $this->builder->buildDps($dps);

        self::assertStringContainsString('<tribFed>', $xml);
        self::assertStringContainsString('<piscofins>', $xml);
        self::assertStringContainsString('<CST>01</CST>', $xml);
        self::assertStringContainsString('<tpRetPisCofins>3</tpRetPisCofins>', $xml);
        self::assertStringContainsString('<vBCPisCofins>1000.00</vBCPisCofins>', $xml);
        self::assertStringContainsString('<pAliqPis>1.65</pAliqPis>', $xml);
        self::assertStringContainsString('<vPis>16.50</vPis>', $xml);
        self::assertStringContainsString('<pAliqCofins>7.60</pAliqCofins>', $xml);
        self::assertStringContainsString('<vCofins>76.00</vCofins>', $xml);
        self::assertStringContainsString('<vRetIRRF>15.00</vRetIRRF>', $xml);
        self::assertStringContainsString('<vRetCSLL>10.00</vRetCSLL>', $xml);
        self::assertStringContainsString('<vRetCP>5.00</vRetCP>', $xml);
    }

    public function testBuildDpsOmitsZeroValuedOptionalFederalRetentions(): void
    {
        $xml = $this->builder->buildDps($this->makeDps(
            federalPiscofinsSituacaoTributaria: '1',
            federalPiscofinsTipoRetencao: '4',
            federalPiscofinsBaseCalculo: '14227.50',
            federalPiscofinsAliquotaPis: '0.65',
            federalPiscofinsValorPis: '92.48',
            federalPiscofinsAliquotaCofins: '3.00',
            federalPiscofinsValorCofins: '426.83',
            federalValorIrrf: '472.50',
            federalValorCsll: '0.00',
            federalValorCp: '0.00',
        ));

        self::assertStringContainsString('<vRetIRRF>472.50</vRetIRRF>', $xml);
        self::assertStringNotContainsString('<vRetCSLL>', $xml);
        self::assertStringNotContainsString('<vRetCP>', $xml);
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

        // For "não optante" (opSimpNac = 1), pAliq must NOT be present.
        self::assertStringNotContainsString('<pAliq>', $xml);
        self::assertStringContainsString('<tribFed/>', str_replace(["\n", '  '], '', $xml));
        self::assertStringContainsString('<pTotTrib><pTotTribFed>0.00</pTotTribFed><pTotTribEst>0.00</pTotTribEst><pTotTribMun>0.00</pTotTribMun></pTotTrib>', str_replace(["\n", '  '], '', $xml));

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
        );
        $xml = $this->builder->buildDps($dpsOptante);

        // For "optante" (opSimpNac = 2), pAliq MUST be present.
        self::assertStringContainsString('<pAliq>', $xml);
        self::assertStringContainsString('<tribFed/>', str_replace(["\n", '  '], '', $xml));
        self::assertStringContainsString('<pTotTrib><pTotTribFed>0.00</pTotTribFed><pTotTribEst>0.00</pTotTribEst><pTotTribMun>0.00</pTotTribMun></pTotTrib>', str_replace(["\n", '  '], '', $xml));
    }

    public function testBuildDpsIncludesTotalTributosPercentuaisWhenConfigured(): void
    {
        $xml = $this->builder->buildDps($this->makeDps(
            indicadorTributacao: 2,
            totalTributosPercentualFederal: '3.65',
            totalTributosPercentualEstadual: '0.00',
            totalTributosPercentualMunicipal: '2.00',
        ));

        self::assertStringContainsString('<pTotTrib><pTotTribFed>3.65</pTotTribFed><pTotTribEst>0.00</pTotTribEst><pTotTribMun>2.00</pTotTribMun></pTotTrib>', str_replace(["\n", '  '], '', $xml));
    }

    public function testBuildDpsOmitsIbsCbsWhenNotConfigured(): void
    {
        $xml = $this->builder->buildDps($this->makeDps());

        self::assertStringNotContainsString('<IBSCBS>', $xml);
    }

    public function testBuildDpsIncludesMinimalIbsCbsGroupInSchemaOrder(): void
    {
        $xml = $this->builder->buildDps($this->makeDps(
            ibsCbsFinalidade: 0,
            ibsCbsIndFinal: 0,
            ibsCbsCodigoIndicadorOperacao: '010101',
            ibsCbsIndDest: 0,
            ibsCbsCst: '000',
            ibsCbsClassificacaoTributaria: '000001',
        ));

        $normalized = str_replace(["\n", '  '], '', $xml);

        self::assertStringContainsString(
            '<IBSCBS><finNFSe>0</finNFSe><indFinal>0</indFinal><cIndOp>010101</cIndOp><indDest>0</indDest><valores><trib><gIBSCBS><CST>000</CST><cClassTrib>000001</cClassTrib></gIBSCBS></trib></valores></IBSCBS>',
            $normalized,
        );

        self::assertLessThan(
            strpos($normalized, '<IBSCBS>'),
            strpos($normalized, '</valores>'),
            'IBSCBS must be emitted after the legacy valores group.',
        );
    }

    public function testBuildDpsRejectsPartialIbsCbsConfiguration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('missing finNFSe, indDest, CST, cClassTrib');

        $this->builder->buildDps($this->makeDps(
            ibsCbsCodigoIndicadorOperacao: '010101',
        ));
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
        string $tomadorCodigoMunicipio = '',
        string $tomadorCep = '',
        string $tomadorLogradouro = '',
        string $tomadorNumero = '',
        string $tomadorComplemento = '',
        string $tomadorBairro = '',
        string $tomadorInscricaoMunicipal = '',
        string $tomadorTelefone = '',
        string $tomadorEmail = '',
        int $regimeEspecialTributacao = 0,
        int $tipoRetencaoIss = 1,
        int $opcaoSimplesNacional = 1,
        int $tipoAmbiente = 2,
        int $indicadorTributacao = 0,
        string $totalTributosPercentualFederal = '',
        string $totalTributosPercentualEstadual = '',
        string $totalTributosPercentualMunicipal = '',
        string $federalPiscofinsSituacaoTributaria = '',
        string $federalPiscofinsTipoRetencao = '',
        string $federalPiscofinsBaseCalculo = '',
        string $federalPiscofinsAliquotaPis = '',
        string $federalPiscofinsValorPis = '',
        string $federalPiscofinsAliquotaCofins = '',
        string $federalPiscofinsValorCofins = '',
        string $federalValorIrrf = '',
        string $federalValorCsll = '',
        string $federalValorCp = '',
        ?int $ibsCbsFinalidade = null,
        ?int $ibsCbsIndFinal = null,
        string $ibsCbsCodigoIndicadorOperacao = '',
        ?int $ibsCbsIndDest = null,
        string $ibsCbsCst = '',
        string $ibsCbsClassificacaoTributaria = '',
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
            tomadorCodigoMunicipio:   $tomadorCodigoMunicipio,
            tomadorCep:               $tomadorCep,
            tomadorLogradouro:        $tomadorLogradouro,
            tomadorNumero:            $tomadorNumero,
            tomadorComplemento:       $tomadorComplemento,
            tomadorBairro:            $tomadorBairro,
            tomadorInscricaoMunicipal: $tomadorInscricaoMunicipal,
            tomadorTelefone:          $tomadorTelefone,
            tomadorEmail:             $tomadorEmail,
            regimeEspecialTributacao: $regimeEspecialTributacao,
            tipoRetencaoIss:          $tipoRetencaoIss,
            issRetido:                $issRetido,
            opcaoSimplesNacional:     $opcaoSimplesNacional,
            tipoAmbiente:              $tipoAmbiente,
            indicadorTributacao:      $indicadorTributacao,
            totalTributosPercentualFederal: $totalTributosPercentualFederal,
            totalTributosPercentualEstadual: $totalTributosPercentualEstadual,
            totalTributosPercentualMunicipal: $totalTributosPercentualMunicipal,
            federalPiscofinsSituacaoTributaria: $federalPiscofinsSituacaoTributaria,
            federalPiscofinsTipoRetencao: $federalPiscofinsTipoRetencao,
            federalPiscofinsBaseCalculo: $federalPiscofinsBaseCalculo,
            federalPiscofinsAliquotaPis: $federalPiscofinsAliquotaPis,
            federalPiscofinsValorPis: $federalPiscofinsValorPis,
            federalPiscofinsAliquotaCofins: $federalPiscofinsAliquotaCofins,
            federalPiscofinsValorCofins: $federalPiscofinsValorCofins,
            federalValorIrrf: $federalValorIrrf,
            federalValorCsll: $federalValorCsll,
            federalValorCp: $federalValorCp,
            ibsCbsFinalidade: $ibsCbsFinalidade,
            ibsCbsIndFinal: $ibsCbsIndFinal,
            ibsCbsCodigoIndicadorOperacao: $ibsCbsCodigoIndicadorOperacao,
            ibsCbsIndDest: $ibsCbsIndDest,
            ibsCbsCst: $ibsCbsCst,
            ibsCbsClassificacaoTributaria: $ibsCbsClassificacaoTributaria,
        );
    }
}
