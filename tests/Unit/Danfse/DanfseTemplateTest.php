<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\Config\DanfseConfig;
use LibreCodeCoop\NfsePHP\Danfse\DanfseTemplate;
use LibreCodeCoop\NfsePHP\Danfse\Enum\Ambiente;
use LibreCodeCoop\NfsePHP\Danfse\XmlToArray;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * @covers \LibreCodeCoop\NfsePHP\Danfse\DanfseTemplate
 */
class DanfseTemplateTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function fixtureNfseData(): array
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/nfse_exemplo.xml');
        self::assertNotFalse($xml);

        return (new XmlToArray())->convert($xml);
    }

    public function testBuildDataMapsKeyFields(): void
    {
        $data = (new DanfseTemplate())->buildData($this->fixtureNfseData());

        // Access key (NFS prefix stripped)
        self::assertSame('3303302112233450000195000000000000100000000001', $data['chave_acesso']);
        self::assertSame('10', $data['numero_nfse']);
        self::assertSame(1, $data['ambiente']);

        // Emitente
        self::assertSame('11.222.333/0001-81', $data['emitente']['cnpj_cpf']);
        self::assertSame('EMPRESA EXEMPLO DESENVOLVIMENTO LTDA', $data['emitente']['nome']);
        self::assertSame('Niterói - RJ', $data['emitente']['municipio']);
        self::assertSame('24020-005', $data['emitente']['cep']);
        self::assertSame('Não Optante', $data['emitente']['simples_nacional']);

        // Tomador (municipality resolved via IBGE map)
        self::assertSame('91.712.343/0001-34', $data['tomador']['cnpj_cpf']);
        self::assertSame('CLIENTE FICTICIO COMERCIO S.A.', $data['tomador']['nome']);
        self::assertSame('São Paulo - SP', $data['tomador']['municipio']);

        // Intermediário present
        self::assertNotNull($data['intermediario']);
        self::assertSame('INTERMEDIARIO FICTICIO LTDA', $data['intermediario']['nome']);

        // Serviço
        self::assertSame('01.07.00', $data['servico']['codigo_trib_nacional']);

        // Tributação municipal labels
        self::assertSame('Operação Tributável', $data['tributacao_municipal']['tributacao_issqn']);
        self::assertSame('Retido pelo Tomador', $data['tributacao_municipal']['retencao_issqn']);
        self::assertSame('Sociedade de Profissionais', $data['tributacao_municipal']['regime_especial']);
        self::assertSame('Niterói', $data['tributacao_municipal']['municipio_incidencia']);

        // Totais
        self::assertSame('R$ 1.500,00', $data['totais']['valor_servico']);
        self::assertSame('R$ 1.292,75', $data['totais']['valor_liquido']);
    }

    public function testAuthorizedMunicipalTotalsUseNfseValuesNotDpsTaxFlags(): void
    {
        $nfse = $this->fixtureNfseData();
        $nfse['infNFSe']['valores'] = [
            'vBC' => '31500.00',
            'pAliqAplic' => '2.00',
            'vISSQN' => '630.00',
        ];
        $nfse['infNFSe']['DPS']['infDPS']['valores']['trib']['tribMun']['vBC'] = '100.00';

        $data = (new DanfseTemplate())->buildData($nfse);

        self::assertSame('R$ 31.500,00', $data['tributacao_municipal']['bc_issqn']);
        self::assertSame('2.00%', $data['tributacao_municipal']['aliquota']);
        self::assertSame('R$ 630,00', $data['tributacao_municipal']['issqn_apurado']);
    }

    public function testDescriptionPreservesNewlinesWithoutRenderingMarkup(): void
    {
        $nfse = $this->fixtureNfseData();
        $nfse['infNFSe']['DPS']['infDPS']['serv']['cServ']['xDescServ'] = "Primeira linha\nSegunda <script>";

        $html = (new DanfseTemplate())->render($nfse, new DanfseConfig());

        self::assertStringContainsString('Primeira linha<br>', $html);
        self::assertStringContainsString('Segunda &lt;script&gt;', $html);
        self::assertStringContainsString('Nº NFS-e / CHAVE NFS-e', $html);
        self::assertStringContainsString('Contribuições Sociais - Retidas', $html);
    }

    public function testTomadorAddressKeepsComplementAndOfficialMunicipalityCode(): void
    {
        $nfse = $this->fixtureNfseData();
        $end = & $nfse['infNFSe']['DPS']['infDPS']['toma']['end'];
        $end['xCpl'] = 'Conjunto 10';
        $end['endNac']['cMun'] = '3550308';
        $end['endNac']['CEP'] = '04578000';

        $data = (new DanfseTemplate())->buildData($nfse);

        self::assertStringContainsString('Conjunto 10', $data['tomador']['endereco']);
        self::assertSame('3550308 / 04578-000', $data['tomador']['ibge_cep']);
    }

    public function testIntermediarioIsNullWhenAbsent(): void
    {
        $nfseData = $this->fixtureNfseData();
        unset($nfseData['infNFSe']['DPS']['infDPS']['interm']);

        $data = (new DanfseTemplate())->buildData($nfseData);

        self::assertNull($data['intermediario']);
    }

    public function testTomadorNifIsPreserved(): void
    {
        $nfseData = $this->fixtureNfseData();
        unset($nfseData['infNFSe']['DPS']['infDPS']['toma']['CNPJ']);
        $nfseData['infNFSe']['DPS']['infDPS']['toma']['NIF'] = 'AB-123';

        $data = (new DanfseTemplate())->buildData($nfseData);

        self::assertSame('AB-123', $data['tomador']['cnpj_cpf']);
    }

    public function testRenderProducesHtmlWithQrCodeAndAccessKey(): void
    {
        $html = (new DanfseTemplate())->render($this->fixtureNfseData(), new DanfseConfig());

        self::assertStringContainsString('<!DOCTYPE html>', $html);
        self::assertStringContainsString('DANFSe v2.0', $html);
        self::assertStringContainsString('Município:', $html);
        self::assertStringContainsString('Ambiente Gerador:', $html);
        self::assertStringContainsString('Tipo de Ambiente:', $html);
        self::assertStringContainsString('SITUAÇÃO DA NFS-e', $html);
        self::assertStringContainsString('FINALIDADE', $html);
        self::assertStringContainsString('3303302112233450000195000000000000100000000001', $html);
        self::assertStringContainsString('data:image/svg+xml;base64,', $html);
        // Production environment: no homologação watermark
        self::assertStringNotContainsString('HOMOLOGAÇÃO', $html);
    }

    public function testBuildDataMapsAuthorizedIbsCbsTotalsWithoutRecalculating(): void
    {
        $nfseData = $this->fixtureNfseData();
        $nfseData['infNFSe']['IBSCBS'] = [
            'cLocalidadeIncid' => '3303302',
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

        $data = (new DanfseTemplate())->buildData($nfseData);

        self::assertNotNull($data['ibs_cbs']);
        self::assertSame('Niterói', $data['ibs_cbs']['localidade_incidencia']);
        self::assertSame('R$ 1.500,00', $data['ibs_cbs']['base_calculo']);
        self::assertSame('0.10%', $data['ibs_cbs']['aliquota_ibs_uf']);
        self::assertSame('0.05%', $data['ibs_cbs']['aliquota_ibs_municipal']);
        self::assertSame('0.90%', $data['ibs_cbs']['aliquota_cbs']);
        self::assertSame('R$ 2,25', $data['ibs_cbs']['total_ibs']);
        self::assertSame('R$ 13,50', $data['ibs_cbs']['total_cbs']);
        self::assertSame('R$ 1.515,75', $data['ibs_cbs']['valor_total_nfse']);
    }

    public function testRenderShowsRequiredIbsCbsSectionEvenWithoutAuthorizedIbsCbsGroup(): void
    {
        $withoutIbsCbs = (new DanfseTemplate())->render($this->fixtureNfseData(), new DanfseConfig());

        self::assertStringContainsString('TRIBUTAÇÃO IBS / CBS', $withoutIbsCbs);
        self::assertStringContainsString('Exclusões e Reduções da Base de Cálculo', $withoutIbsCbs);

        $nfseData = $this->fixtureNfseData();
        $nfseData['infNFSe']['IBSCBS'] = [
            'xLocalidadeIncid' => 'Niterói',
            'valores' => [
                'vBC' => '1000.00',
                'uf' => ['pIBSUF' => '0.10'],
                'mun' => ['pIBSMun' => '0.05'],
                'fed' => ['pCBS' => '0.90'],
            ],
            'totCIBS' => [
                'vTotNF' => '1010.50',
                'gIBS' => ['vIBSTot' => '1.50'],
                'gCBS' => ['vCBS' => '9.00'],
            ],
        ];

        $withIbsCbs = (new DanfseTemplate())->render($nfseData, new DanfseConfig());

        self::assertStringContainsString('TRIBUTAÇÃO IBS / CBS', $withIbsCbs);
        self::assertStringContainsString('R$ 1.010,50', $withIbsCbs);
        self::assertStringContainsString('R$ 1,50', $withIbsCbs);
        self::assertStringContainsString('R$ 9,00', $withIbsCbs);
    }

    public function testHomologacaoEnvironmentShowsWatermark(): void
    {
        $nfseData = $this->fixtureNfseData();
        $nfseData['infNFSe']['DPS']['infDPS']['tpAmb'] = '2';

        $data = (new DanfseTemplate())->buildData($nfseData);
        self::assertSame(2, $data['ambiente']);

        $html = (new DanfseTemplate())->render($nfseData, new DanfseConfig());
        self::assertStringContainsString('HOMOLOGAÇÃO', $html);
    }

    public function testQrCodeUrlUsesEnvironmentHost(): void
    {
        $method = new \ReflectionMethod(DanfseTemplate::class, 'qrCodeUrl');
        $template = new DanfseTemplate();

        self::assertSame(
            'https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=abc',
            $method->invoke($template, 'abc', Ambiente::PRODUCAO),
        );
        self::assertSame(
            'https://www.producaorestrita.nfse.gov.br/ConsultaPublica/?tpc=1&chave=abc',
            $method->invoke($template, 'abc', Ambiente::HOMOLOGACAO),
        );
    }
}
