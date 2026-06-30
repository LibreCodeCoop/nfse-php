<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\Config\DanfseConfig;
use LibreCodeCoop\NfsePHP\Danfse\DanfseTemplate;
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
    private function fixtureArray(): array
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/nfse_exemplo.xml');
        self::assertNotFalse($xml);

        return (new XmlToArray())->convert($xml);
    }

    public function testBuildDataMapsKeyFields(): void
    {
        $data = (new DanfseTemplate())->buildData($this->fixtureArray());

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

    public function testIntermediarioIsNullWhenAbsent(): void
    {
        $arr = $this->fixtureArray();
        unset($arr['infNFSe']['DPS']['infDPS']['interm']);

        $data = (new DanfseTemplate())->buildData($arr);

        self::assertNull($data['intermediario']);
    }

    public function testRenderProducesHtmlWithQrCodeAndAccessKey(): void
    {
        $html = (new DanfseTemplate())->render($this->fixtureArray(), new DanfseConfig());

        self::assertStringContainsString('<!DOCTYPE html>', $html);
        self::assertStringContainsString('3303302112233450000195000000000000100000000001', $html);
        self::assertStringContainsString('data:image/svg+xml;base64,', $html);
        // Production environment: no homologação watermark
        self::assertStringNotContainsString('HOMOLOGAÇÃO', $html);
    }

    public function testHomologacaoEnvironmentShowsWatermark(): void
    {
        $arr = $this->fixtureArray();
        $arr['infNFSe']['DPS']['infDPS']['tpAmb'] = '2';

        $data = (new DanfseTemplate())->buildData($arr);
        self::assertSame(2, $data['ambiente']);

        $html = (new DanfseTemplate())->render($arr, new DanfseConfig());
        self::assertStringContainsString('HOMOLOGAÇÃO', $html);
    }
}
