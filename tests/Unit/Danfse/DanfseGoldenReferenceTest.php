<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\Config\DanfseConfig;
use LibreCodeCoop\NfsePHP\Danfse\DanfseGenerator;
use LibreCodeCoop\NfsePHP\Danfse\DanfseTemplate;
use LibreCodeCoop\NfsePHP\Danfse\XmlToArray;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * Structural reference cases for the DANFSe v2 renderer.
 *
 * These tests intentionally avoid pixel/PDF-byte snapshots because QR/PDF
 * metadata can vary across renderer versions. The stable contract is the
 * normalized document data plus required/optional HTML sections.
 *
 * @covers \LibreCodeCoop\NfsePHP\Danfse\DanfseGenerator
 * @covers \LibreCodeCoop\NfsePHP\Danfse\DanfseTemplate
 */
final class DanfseGoldenReferenceTest extends TestCase
{
    public function testCommonTaxableReferenceKeepsRequiredIdentityAndSections(): void
    {
        $data = $this->fixtureData();
        $html = (new DanfseTemplate())->render($data, new DanfseConfig());

        self::assertStringContainsString('DANFSe v2.0', $html);
        self::assertStringContainsString('3303302112233450000195000000000000100000000001', $html);
        self::assertStringContainsString('EMPRESA EXEMPLO DESENVOLVIMENTO LTDA', $html);
        self::assertStringContainsString('CLIENTE FICTICIO COMERCIO S.A.', $html);
        self::assertStringContainsString('data:image/svg+xml;base64,', $html);
        self::assertStringNotContainsString('HOMOLOGAÇÃO', $html);
    }

    public function testRetainedIssReferenceKeepsAuthorizedRetentionValues(): void
    {
        $data = (new DanfseTemplate())->buildData($this->fixtureData());

        self::assertSame('Retido pelo Tomador', $data['tributacao_municipal']['retencao_issqn']);
        self::assertNotSame('-', $data['totais']['issqn_retido']);
        self::assertSame('R$ 27,00', $data['totais']['issqn_retido']);
    }

    public function testForeignTakerReferenceUsesNifWithoutInventingBrazilianDocument(): void
    {
        $nfse = $this->fixtureData();
        $taker = & $nfse['infNFSe']['DPS']['infDPS']['toma'];

        unset($taker['CNPJ'], $taker['CPF']);
        $taker['NIF'] = 'EU-PT-998877';

        $data = (new DanfseTemplate())->buildData($nfse);

        self::assertSame('EU-PT-998877', $data['tomador']['cnpj_cpf']);
    }

    public function testLongOfficialDescriptionsUseStableSixtyCharacterSummary(): void
    {
        $nfse = $this->fixtureData();
        $longDescription = str_repeat('Descrição fiscal extensa ', 8);
        $nfse['infNFSe']['xTribNac'] = $longDescription;
        $nfse['infNFSe']['xTribMun'] = $longDescription;

        $data = (new DanfseTemplate())->buildData($nfse);

        self::assertSame(63, mb_strlen($data['servico']['desc_trib_nacional']));
        self::assertSame(63, mb_strlen($data['servico']['desc_trib_municipal']));
        self::assertStringEndsWith('...', $data['servico']['desc_trib_nacional']);
        self::assertStringEndsWith('...', $data['servico']['desc_trib_municipal']);
        self::assertNotSame($longDescription, $data['servico']['desc_trib_nacional']);
    }

    public function testIbsCbsReferenceRendersOnlyAuthorizedTotals(): void
    {
        $nfse = $this->fixtureData();
        $nfse['infNFSe']['IBSCBS'] = [
            'xLocalidadeIncid' => 'Niterói',
            'valores' => [
                'vBC' => '1500.00',
                'uf' => ['pIBSUF' => '0.10'],
                'mun' => ['pIBSMun' => '0.05'],
                'fed' => ['pCBS' => '0.90'],
            ],
            'totCIBS' => [
                'vTotNF' => '1515.75',
                'gIBS' => ['vIBSTot' => '2.25'],
                'gCBS' => ['vCBS' => '13.50'],
            ],
        ];

        $template = new DanfseTemplate();
        $data = $template->buildData($nfse);
        $html = $template->render($nfse, new DanfseConfig());

        self::assertSame('R$ 2,25', $data['ibs_cbs']['total_ibs']);
        self::assertSame('R$ 13,50', $data['ibs_cbs']['total_cbs']);
        self::assertSame('R$ 1.515,75', $data['ibs_cbs']['valor_total_nfse']);
        self::assertStringContainsString('TRIBUTAÇÃO IBS / CBS', $html);
    }

    public function testLegacyAuthorizedXmlWithoutIbsCbsKeepsLegacyLayout(): void
    {
        $nfse = $this->fixtureData();
        unset($nfse['infNFSe']['IBSCBS']);

        $template = new DanfseTemplate();
        $data = $template->buildData($nfse);
        $html = $template->render($nfse, new DanfseConfig());

        self::assertNull($data['ibs_cbs']);
        self::assertStringNotContainsString('TRIBUTAÇÃO IBS / CBS', $html);
        self::assertStringContainsString('DANFSe v2.0', $html);
    }

    public function testHomologationReferenceCarriesVisibleMarker(): void
    {
        $nfse = $this->fixtureData();
        $nfse['infNFSe']['DPS']['infDPS']['tpAmb'] = '2';

        $html = (new DanfseTemplate())->render($nfse, new DanfseConfig());

        self::assertStringContainsString('HOMOLOGAÇÃO', $html);
    }

    public function testReferenceXmlStillProducesA4PdfArtifact(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/nfse_exemplo.xml');
        self::assertNotFalse($xml);

        $pdf = (new DanfseGenerator())->generateFromXml($xml);

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertGreaterThan(1000, strlen($pdf));
    }

    /**
     * @return array<string, mixed>
     */
    private function fixtureData(): array
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/nfse_exemplo.xml');
        self::assertNotFalse($xml);

        return (new XmlToArray())->convert($xml);
    }
}
