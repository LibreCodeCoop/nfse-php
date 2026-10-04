<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Xml;

use LibreCodeCoop\NfsePHP\Dto\DeductionDocumentData;
use LibreCodeCoop\NfsePHP\Dto\DeductionDocumentReferenceData;
use LibreCodeCoop\NfsePHP\Dto\DeductionReductionData;
use LibreCodeCoop\NfsePHP\Dto\DeductionSupplierData;
use LibreCodeCoop\NfsePHP\Dto\DpsData;
use LibreCodeCoop\NfsePHP\Dto\MunicipalBenefitData;
use LibreCodeCoop\NfsePHP\Dto\SubstitutionData;
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

    public function testStandardDeductionAndMunicipalBenefitMatchOfficialSchema(): void
    {
        $xml = $this->builder->buildDps($this->makeDps(
            deducaoReducao: new DeductionReductionData(percentual: '10.00'),
            beneficioMunicipal: new MunicipalBenefitData(
                identificador: '33033020400001',
                valorReducaoBaseCalculo: '100.00',
            ),
        ));

        self::assertSame([], $this->validator->validate($xml));
    }

    public function testDocumentBackedDeductionMatchesOfficialSchema(): void
    {
        $xml = $this->builder->buildDps($this->makeDps(
            deducaoReducao: new DeductionReductionData(documentos: [
                new DeductionDocumentData(
                    reference: DeductionDocumentReferenceData::document('DOC-2026-001'),
                    type: 99,
                    issuedAt: '2026-09-30',
                    deductibleValue: '500.00',
                    deductionValue: '125.00',
                    otherDescription: 'Despesa comprovada',
                    supplier: new DeductionSupplierData(
                        identityType: 'cnpj',
                        identity: '11222333000181',
                        name: 'Fornecedor Exemplo',
                    ),
                ),
            ]),
        ));

        self::assertSame([], $this->validator->validate($xml));
    }

    public function testValidatorRejectsUnsupportedSchemaVersionExplicitly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported NFS-e schema version: 9.99');

        new DpsSchemaValidator(schemaVersion: '9.99');
    }

    public function testSupportedSchemaVersionResolvesVersionedOfficialBundle(): void
    {
        self::assertContains(
            DpsSchemaValidator::SCHEMA_VERSION,
            DpsSchemaValidator::SUPPORTED_SCHEMA_VERSIONS,
        );

        self::assertSame([], (new DpsSchemaValidator(
            schemaVersion: DpsSchemaValidator::SCHEMA_VERSION,
        ))->validate($this->builder->buildDps($this->makeDps())));
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

    public function testRetainedIssDpsMatchesOfficialSchema(): void
    {
        $xml = $this->builder->buildDps($this->makeDps(
            tipoRetencaoIss: 2,
        ));

        self::assertSame([], $this->validator->validate($xml));
    }

    public function testIssqnImmunityDpsMatchesOfficialSchema(): void
    {
        $xml = $this->builder->buildDps($this->makeDps(
            tributacaoIssqn: 2,
            issqnTipoImunidade: 3,
        ));

        self::assertSame([], $this->validator->validate($xml));
    }

    public function testIssqnExportDpsMatchesOfficialSchema(): void
    {
        $xml = $this->builder->buildDps($this->makeDps(
            tributacaoIssqn: 3,
            issqnPaisResultado: 'US',
        ));

        self::assertSame([], $this->validator->validate($xml));
    }

    public function testIssqnSuspendedEnforceabilityDpsMatchesOfficialSchema(): void
    {
        $xml = $this->builder->buildDps($this->makeDps(
            tributacaoIssqn: 1,
            issqnTipoSuspensao: 1,
            issqnNumeroProcessoSuspensao: str_repeat('1', 30),
        ));

        self::assertSame([], $this->validator->validate($xml));
    }

    public function testForeignServiceTakerDpsMatchesOfficialSchema(): void
    {
        $xml = $this->builder->buildDps(new DpsData(
            cnpjPrestador: '11222333000181',
            municipioIbge: '3303302',
            itemListaServico: '001',
            valorServico: '1000.00',
            aliquota: '5.00',
            discriminacao: 'Consultoria internacional',
            tipoAmbiente: 2,
            serie: '1',
            numeroDps: '43',
            dataCompetencia: '2026-10-03',
            codigoTributacaoNacional: '010701',
            tomadorNif: 'US-TAX-12345',
            nomeTomador: 'Foreign Customer LLC',
            tomadorPaisCodigo: 'US',
            tomadorCodigoPostalExterior: '10001',
            tomadorCidadeExterior: 'New York',
            tomadorEstadoExterior: 'NY',
            tomadorLogradouro: '5th Avenue',
            tomadorNumero: '100',
            tomadorBairro: 'Manhattan',
            opcaoSimplesNacional: 1,
            regimeEspecialTributacao: 0,
            tributacaoIssqn: 1,
            tipoRetencaoIss: 1,
            indicadorTributacao: 0,
        ));

        self::assertSame([], $this->validator->validate($xml));
    }

    public function testSubstitutionDpsMatchesOfficialSchema(): void
    {
        $xml = $this->builder->buildDps($this->makeDps(
            substituicao: new SubstitutionData(
                chaveNfseSubstituida: str_repeat('1', 50),
                codigoMotivo: '01',
            ),
        ));

        self::assertSame([], $this->validator->validate($xml));
    }

    public function testValidatorRejectsInvalidOfficialEnumEvenWhenXmlIsWellFormed(): void
    {
        $xml = $this->builder->buildDps($this->makeDps());
        $invalidXml = str_replace('<tribISSQN>1</tribISSQN>', '<tribISSQN>9</tribISSQN>', $xml);

        self::assertNotSame([], $this->validator->validate($invalidXml));
    }

    public function testValidatorRejectsWrongTribMunElementOrder(): void
    {
        $xml = $this->builder->buildDps($this->makeDps());
        $invalidXml = str_replace(
            '<tribISSQN>1</tribISSQN><tpRetISSQN>1</tpRetISSQN>',
            '<tpRetISSQN>1</tpRetISSQN><tribISSQN>1</tribISSQN>',
            str_replace(["\n", '  '], '', $xml),
        );

        self::assertNotSame([], $this->validator->validate($invalidXml));
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
        int $tributacaoIssqn = 1,
        string $issqnPaisResultado = '',
        ?int $issqnTipoImunidade = null,
        ?int $issqnTipoSuspensao = null,
        string $issqnNumeroProcessoSuspensao = '',
        int $tipoRetencaoIss = 1,
        ?SubstitutionData $substituicao = null,
        ?DeductionReductionData $deducaoReducao = null,
        ?MunicipalBenefitData $beneficioMunicipal = null,
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
            substituicao: $substituicao,
            deducaoReducao: $deducaoReducao,
            beneficioMunicipal: $beneficioMunicipal,
        );
    }
}
