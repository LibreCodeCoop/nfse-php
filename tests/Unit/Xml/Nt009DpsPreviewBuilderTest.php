<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Xml;

use LibreCodeCoop\NfsePHP\Dto\DpsData;
use LibreCodeCoop\NfsePHP\Dto\Nt009DpsPreview;
use LibreCodeCoop\NfsePHP\Dto\Nt009DpsPreviewData;
use LibreCodeCoop\NfsePHP\Tests\TestCase;
use LibreCodeCoop\NfsePHP\Xml\DpsSchemaValidator;
use LibreCodeCoop\NfsePHP\Xml\XmlBuilder;

/**
 * The NT009 output is structural evidence, NOT fiscal emission validation.
 * The February production v1.01 XSD is intentionally incompatible with it.
 */
final class Nt009DpsPreviewBuilderTest extends TestCase
{
    private XmlBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new XmlBuilder();
    }

    public function testLegacyEmissionOutputIsNotModifiedByPreview(): void
    {
        $dps = $this->makeDps();
        $legacy = $this->builder->buildDps($dps);

        $draft = $this->builder->previewNt009Dps(
            $dps,
            new Nt009DpsPreviewData(finalidade: 0, indicadorDestinatario: 0)
        );

        self::assertInstanceOf(Nt009DpsPreview::class, $draft);
        self::assertSame(
            $this->withoutEmissionTimestamp($legacy),
            $this->withoutEmissionTimestamp($this->builder->buildDps($dps))
        );
        self::assertStringNotContainsString('<finNFSe>', $legacy);
        self::assertStringNotContainsString('<IBSCBS>', $draft->xml);
        self::assertSame('0', $this->first($draft->xml, '/n:DPS/n:infDPS/n:finNFSe')->textContent);
        self::assertSame('0', $this->first($draft->xml, '/n:DPS/n:infDPS/n:indDest')->textContent);
    }

    public function testPreviewRelocatesMandatoryFieldsAndSkipsConditionalGIbsCbs(): void
    {
        $draft = $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 1,
                indicadorUsoPessoal: 0,
                codigoIndicadorOperacao: '010101',
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: false,
                regimeApuracaoSimples: 1,
            )
        );
        self::assertSame('1', $this->first(
            $draft->xml,
            '/n:DPS/n:infDPS/n:prest/n:regTrib/n:regApIBSCBSSN'
        )->textContent);
        self::assertSame('000', $this->first(
            $draft->xml,
            '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib/n:CST'
        )->textContent);
        self::assertSame('000001', $this->first(
            $draft->xml,
            '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib/n:cClassTrib'
        )->textContent);
        self::assertSame('010101', $this->first(
            $draft->xml,
            '/n:DPS/n:infDPS/n:IBSCBS/n:cIndOp'
        )->textContent);
        $xpath = $this->xpath($draft->xml);
        self::assertSame(0, $xpath->query(
            '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib/n:gIBSCBS'
        )?->length);
        self::assertSame(0, $xpath->query('/n:DPS/n:infDPS/n:IBSCBS/n:indDest')?->length);
        self::assertSame(0, $xpath->query('/n:DPS/n:infDPS/n:IBSCBS/n:finNFSe')?->length);
    }

    public function testValidDebitoAdjustmentEmitsConditionalNestedValues(): void
    {
        $draft = $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 2,
                indicadorDestinatario: 0,
                tipoNotaDebito: '01',
                codigoIndicadorOperacao: '010101',
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: true,
                valorAjusteIbs: '15.25',
                valorAjusteCbs: '6.80',
            )
        );
        self::assertSame('01', $this->first(
            $draft->xml,
            '/n:DPS/n:infDPS/n:tpNFSeDebito'
        )->textContent);
        self::assertSame('15.25', $this->first(
            $draft->xml,
            '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib/n:gIBSCBS/n:gIBSCBSAjuste/n:vIBS'
        )->textContent);
        self::assertSame('6.80', $this->first(
            $draft->xml,
            '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib/n:gIBSCBS/n:gIBSCBSAjuste/n:vCBS'
        )->textContent);
        self::assertStringNotContainsString('schemaLocation', $draft->xml);
        // Production XSD still implements the old field hierarchy.
        self::assertNotSame([], (new DpsSchemaValidator())->validate($draft->xml));
    }

    public function testCreditNoteRequiresOfficialSpecificSubtype(): void
    {
        $valid = $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(finalidade: 1, indicadorDestinatario: 0, tipoNotaCredito: '05')
        );
        self::assertSame('05', $this->first(
            $valid->xml,
            '/n:DPS/n:infDPS/n:tpNFSeCredito'
        )->textContent);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('credit note requires tpNFSeCredito');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(finalidade: 1, indicadorDestinatario: 0, tipoNotaCredito: '02')
        );
    }

    public function testRequiresExternallyVerifiedClassificationFlag(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('caller-verified ind_gIBSCBS');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                cst: '000',
                classificacaoTributaria: '000001',
            )
        );
    }

    public function testRejectsAbsentAndInventedCIndOpCodes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('absent from official Annex VII');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                codigoIndicadorOperacao: '030103',
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: false,
            )
        );
    }

    public function testForbiddenAdjustmentSubtypeCannotGenerateTaxAdjustmentValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('forbidden for this adjustment-note type');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 2,
                indicadorDestinatario: 0,
                tipoNotaDebito: '04',
                codigoIndicadorOperacao: '010101',
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: true,
                valorAjusteIbs: '1.00',
                valorAjusteCbs: '2.00',
            )
        );
    }

    public function testRejectsIncompleteConditionalAdjustmentPair(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires both vIBS and vCBS');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 2,
                indicadorDestinatario: 0,
                tipoNotaDebito: '01',
                codigoIndicadorOperacao: '010101',
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: true,
                valorAjusteIbs: '1.00',
            )
        );
    }

    public function testRejectsMixingLegacyAndNewIbsCbsSemantics(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot mix legacy DpsData');
        $this->builder->previewNt009Dps(
            $this->makeDps(ibsCbsFinalidade: 0),
            new Nt009DpsPreviewData(finalidade: 0, indicadorDestinatario: 0)
        );
    }

    public function testAllPublishedVersionSensitiveFieldsAreOrdered(): void
    {
        $draft = $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(finalidade: 2, indicadorDestinatario: 0, tipoNotaDebito: '06')
        );
        $xml = $draft->xml;
        self::assertLessThan(strpos($xml, '<cLocEmi>'), strpos($xml, '<finNFSe>'));
        self::assertLessThan(strpos($xml, '<cLocEmi>'), strpos($xml, '<tpNFSeDebito>'));
        self::assertLessThan(strpos($xml, '<serv>'), strpos($xml, '<indDest>'));
        self::assertLessThan(strpos($xml, '<valores>'), strpos($xml, '<serv>'));
    }

    private function makeDps(?int $ibsCbsFinalidade = null): DpsData
    {
        return new DpsData(
            cnpjPrestador: '11222333000181',
            municipioIbge: '3303302',
            itemListaServico: '0107',
            valorServico: '150.00',
            aliquota: '5.00',
            discriminacao: 'Servico de tecnologia',
            serie: '00001',
            numeroDps: '17',
            codigoTributacaoNacional: '010701',
            ibsCbsFinalidade: $ibsCbsFinalidade,
        );
    }

    private function withoutEmissionTimestamp(string $xml): string
    {
        return (string) preg_replace('/<dhEmi>[^<]+<\/dhEmi>/', '<dhEmi>normalized</dhEmi>', $xml);
    }

    private function xpath(string $xml): \DOMXPath
    {
        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML($xml, LIBXML_NONET));
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');

        return $xpath;
    }

    private function first(string $xml, string $path): \DOMNode
    {
        $result = $this->xpath($xml)->query($path);
        self::assertNotFalse($result);
        self::assertSame(1, $result->length, 'Missing or duplicated NT009 path: ' . $path);

        return $result->item(0);
    }
}
