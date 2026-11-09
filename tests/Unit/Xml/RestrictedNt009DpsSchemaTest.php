<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Xml;

use LibreCodeCoop\NfsePHP\Config\EnvironmentConfig;
use LibreCodeCoop\NfsePHP\Dto\DeductionReductionData;
use LibreCodeCoop\NfsePHP\Dto\DpsData;
use LibreCodeCoop\NfsePHP\Dto\SubstitutionData;
use LibreCodeCoop\NfsePHP\Tests\TestCase;
use LibreCodeCoop\NfsePHP\Xml\DpsSchemaValidator;
use LibreCodeCoop\NfsePHP\Xml\XmlBuilder;

final class RestrictedNt009DpsSchemaTest extends TestCase
{
    private const RESTRICTED = EnvironmentConfig::SCHEMA_RELEASE_RESTRICTED_20261020;

    public function testRestrictedSubstitutionMatchesOfficialOctoberXsd(): void
    {
        $dps = $this->syntheticDps(new SubstitutionData(
            chaveNfseSubstituida: str_repeat('1', 50),
            codigoMotivo: '05',
            descricaoMotivo: 'Retificacao de descricao ficticia',
        ));

        $xml = (new XmlBuilder())->buildDps($dps, self::RESTRICTED);
        self::assertSame([], (new DpsSchemaValidator(schemaRelease: self::RESTRICTED))->validate($xml));

        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($xml, LIBXML_NONET));
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('nfse', 'http://www.sped.fazenda.gov.br/nfse');

        self::assertSame('0', $xpath->evaluate('string(/nfse:DPS/nfse:infDPS/nfse:finNFSe)'));
        self::assertSame('05', $xpath->evaluate('string(/nfse:DPS/nfse:infDPS/nfse:subst/nfse:cMotivo)'));
        self::assertSame('000', $xpath->evaluate('string(/nfse:DPS/nfse:infDPS/nfse:IBSCBS/nfse:valores/nfse:trib/nfse:CST)'));
        self::assertSame('115011000', $xpath->evaluate('string(/nfse:DPS/nfse:infDPS/nfse:serv/nfse:cServ/nfse:cNBS)'));
        self::assertSame(0, $xpath->query('/nfse:DPS/nfse:infDPS/nfse:IBSCBS/nfse:finNFSe')?->length);
        self::assertSame(0, $xpath->query('/nfse:DPS/nfse:infDPS/nfse:IBSCBS/nfse:valores/nfse:trib/nfse:gIBSCBS')?->length);
    }

    public function testRestrictedRegularIssuanceMatchesOfficialOctoberXsd(): void
    {
        $xml = (new XmlBuilder())->buildDps($this->syntheticDps(), self::RESTRICTED);
        self::assertSame([], (new DpsSchemaValidator(schemaRelease: self::RESTRICTED))->validate($xml));
        self::assertStringNotContainsString('<subst>', $xml);
    }

    public function testProductionRetainsFebruaryContractAndOctoberRejectsIt(): void
    {
        $xml = (new XmlBuilder())->buildDps($this->syntheticDps());
        self::assertSame([], (new DpsSchemaValidator())->validate($xml));
        $oldDocument = new \DOMDocument();
        self::assertTrue($oldDocument->loadXML($xml, LIBXML_NONET));
        $oldXpath = new \DOMXPath($oldDocument);
        $oldXpath->registerNamespace('nfse', 'http://www.sped.fazenda.gov.br/nfse');
        self::assertSame(0, $oldXpath->query('/nfse:DPS/nfse:infDPS/nfse:finNFSe')?->length);

        $errors = (new DpsSchemaValidator(schemaRelease: self::RESTRICTED))->validate($xml);
        self::assertNotSame([], $errors);
        self::assertStringContainsString('cLocEmi', implode(' ', $errors));
    }

    public function testRestrictedLayoutRefusesUnsupportedCreditPurpose(): void
    {
        $dps = new DpsData(
            // Synthetic approximate-tax disclosure for isolated XML contract tests.
            totalTributosPercentualFederal: '0.00',
            totalTributosPercentualEstadual: '0.00',
            totalTributosPercentualMunicipal: '0.00',
            cnpjPrestador: '11222333000181',
            municipioIbge: '3303302',
            itemListaServico: '0101',
            valorServico: '1500.00',
            aliquota: '2.00',
            discriminacao: 'Servico ficticio',
            ibsCbsFinalidade: 1,
        );
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('finNFSe=0');
        (new XmlBuilder())->buildDps($dps, self::RESTRICTED);
    }

    public function testRestrictedPlainDpsWithoutOptionalIbsCbsGroupRemainsValid(): void
    {
        $dps = new DpsData(
            // Synthetic approximate-tax disclosure for isolated XML contract tests.
            totalTributosPercentualFederal: '0.00',
            totalTributosPercentualEstadual: '0.00',
            totalTributosPercentualMunicipal: '0.00',
            cnpjPrestador: '11222333000181',
            municipioIbge: '3303302',
            itemListaServico: '0101',
            valorServico: '1500.00',
            aliquota: '2.00',
            discriminacao: 'Servico ficticio',
            codigoTributacaoMunicipal: '001',
        );

        $xml = (new XmlBuilder())->buildDps($dps, self::RESTRICTED);

        self::assertSame([], (new DpsSchemaValidator(schemaRelease: self::RESTRICTED))->validate($xml));
        self::assertStringContainsString('<finNFSe>0</finNFSe>', $xml);
        self::assertStringNotContainsString('<IBSCBS>', $xml);
    }

    public function testRestrictedLayoutRefusesLegacyDeduction(): void
    {
        $dps = $this->syntheticDps();
        $dpsWithDeduction = new DpsData(...array_replace(
            get_object_vars($dps),
            ['deducaoReducao' => new DeductionReductionData(percentual: '10.00')],
        ));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Legacy vDedRed');
        (new XmlBuilder())->buildDps($dpsWithDeduction, self::RESTRICTED);
    }

    public function testUnsupportedSchemaReleaseFailsClosed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported DPS schema release');
        (new XmlBuilder())->buildDps($this->syntheticDps(), 'unknown');
    }

    private function syntheticDps(?SubstitutionData $substitution = null): DpsData
    {
        return new DpsData(
            // Synthetic approximate-tax disclosure for isolated XML contract tests.
            totalTributosPercentualFederal: '0.00',
            totalTributosPercentualEstadual: '0.00',
            totalTributosPercentualMunicipal: '0.00',
            cnpjPrestador: '11222333000181',
            municipioIbge: '3303302',
            itemListaServico: '0101',
            valorServico: '1500.00',
            aliquota: '2.00',
            discriminacao: 'Consultoria ficticia',
            dataCompetencia: '2026-10-06',
            codigoTributacaoNacional: '010101',
            codigoTributacaoMunicipal: '001',
            codigoNbs: '115011000',
            ibsCbsFinalidade: 0,
            ibsCbsIndFinal: 0,
            ibsCbsCodigoIndicadorOperacao: '100301',
            ibsCbsIndDest: 0,
            ibsCbsCst: '000',
            ibsCbsClassificacaoTributaria: '000001',
            federalPiscofinsSituacaoTributaria: '1',
            federalPiscofinsTipoRetencao: '4',
            federalValorIrrf: '22.50',
            federalValorCsll: '54.75',
            substituicao: $substitution,
        );
    }
}
