<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Xml;

use LibreCodeCoop\NfsePHP\Dto\DpsData;
use LibreCodeCoop\NfsePHP\Tests\TestCase;
use LibreCodeCoop\NfsePHP\Xml\DpsSchemaValidator;
use LibreCodeCoop\NfsePHP\Xml\XmlBuilder;

/**
 * Contract tests against the official Sistema Nacional NFS-e DPS v1.01 XSD.
 *
 * @covers \LibreCodeCoop\NfsePHP\Xml\DpsSchemaValidator
 */
final class OfficialDpsSchemaValidationTest extends TestCase
{
    private DpsSchemaValidator $validator;
    private XmlBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new DpsSchemaValidator();
        $this->builder = new XmlBuilder();
    }

    public function testRepresentativeDpsMatchesOfficialSchema(): void
    {
        $xml = $this->builder->buildDps($this->makeDps());

        self::assertSame([], $this->validator->validate($xml));
    }

    public function testFebruarySchemaSnapshotDocumentsPreAlphanumericCnpjContract(): void
    {
        $xml = $this->builder->buildDps($this->makeDps(
            cnpjPrestador: '12ABC34501DE35',
            documentoTomador: '98XYZ76501AB12',
        ));

        $errors = $this->validator->validate($xml);

        self::assertNotSame([], $errors);
        $cnpjPatternError = array_filter(
            $errors,
            static fn (string $error): bool => str_contains($error, 'CNPJ')
                && str_contains($error, '[0-9]{14}'),
        );

        self::assertNotSame(
            [],
            $cnpjPatternError,
            'The 2026-02-09 official schema snapshot should make its pre-alphanumeric CNPJ limitation explicit.',
        );
    }

    public function testIbsCbsDpsMatchesOfficialSchema(): void
    {
        $xml = $this->builder->buildDps($this->makeDps(
            ibsCbsFinalidade: 0,
            ibsCbsIndFinal: 0,
            ibsCbsCodigoIndicadorOperacao: '100101',
            ibsCbsIndDest: 0,
            ibsCbsCst: '000',
            ibsCbsClassificacaoTributaria: '000001',
        ));

        self::assertSame([], $this->validator->validate($xml));
    }

    public function testValidatorReportsMalformedXml(): void
    {
        $errors = $this->validator->validate('<DPS>');

        self::assertNotSame([], $errors);
    }

    private function makeDps(
        string $cnpjPrestador = '11222333000181',
        string $documentoTomador = '12345678000195',
        ?int $ibsCbsFinalidade = null,
        ?int $ibsCbsIndFinal = null,
        string $ibsCbsCodigoIndicadorOperacao = '',
        ?int $ibsCbsIndDest = null,
        string $ibsCbsCst = '',
        string $ibsCbsClassificacaoTributaria = '',
    ): DpsData {
        return new DpsData(
            cnpjPrestador: $cnpjPrestador,
            municipioIbge: '3303302',
            itemListaServico: '001',
            valorServico: '1000.00',
            aliquota: '5.00',
            discriminacao: 'Consultoria em tecnologia da informacao',
            tipoAmbiente: 2,
            serie: '1',
            numeroDps: '42',
            dataCompetencia: '2026-10-03',
            codigoTributacaoNacional: '010701',
            documentoTomador: $documentoTomador,
            nomeTomador: 'Cliente de Teste',
            tomadorCodigoMunicipio: '3304557',
            tomadorCep: '20040020',
            tomadorLogradouro: 'Rua de Teste',
            tomadorNumero: '100',
            tomadorBairro: 'Centro',
            tomadorEmail: 'cliente@example.test',
            opcaoSimplesNacional: 1,
            regimeEspecialTributacao: 0,
            tipoRetencaoIss: 1,
            indicadorTributacao: 0,
            ibsCbsFinalidade: $ibsCbsFinalidade,
            ibsCbsIndFinal: $ibsCbsIndFinal,
            ibsCbsCodigoIndicadorOperacao: $ibsCbsCodigoIndicadorOperacao,
            ibsCbsIndDest: $ibsCbsIndDest,
            ibsCbsCst: $ibsCbsCst,
            ibsCbsClassificacaoTributaria: $ibsCbsClassificacaoTributaria,
        );
    }
}
