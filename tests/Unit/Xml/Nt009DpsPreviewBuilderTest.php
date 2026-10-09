<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Xml;

use LibreCodeCoop\NfsePHP\Dto\DeductionReductionData;
use LibreCodeCoop\NfsePHP\Dto\DpsData;
use LibreCodeCoop\NfsePHP\Dto\Nt009AdjustmentDocumentData;
use LibreCodeCoop\NfsePHP\Dto\Nt009BaseAdjustmentData;
use LibreCodeCoop\NfsePHP\Dto\Nt009CondominiumChargeData;
use LibreCodeCoop\NfsePHP\Dto\Nt009CondominiumData;
use LibreCodeCoop\NfsePHP\Dto\Nt009CondominiumDetailData;
use LibreCodeCoop\NfsePHP\Dto\Nt009DpsPreview;
use LibreCodeCoop\NfsePHP\Dto\Nt009DpsPreviewData;
use LibreCodeCoop\NfsePHP\Dto\Nt009LeaseData;
use LibreCodeCoop\NfsePHP\Dto\Nt009LinkedPaymentData;
use LibreCodeCoop\NfsePHP\Dto\Nt009MovableAssetData;
use LibreCodeCoop\NfsePHP\Dto\Nt009NationalInvoiceReference;
use LibreCodeCoop\NfsePHP\Dto\Nt009OtherDocumentReference;
use LibreCodeCoop\NfsePHP\Dto\Nt009OtherFiscalReference;
use LibreCodeCoop\NfsePHP\Dto\Nt009PropertyAdjustmentData;
use LibreCodeCoop\NfsePHP\Dto\Nt009RealEstateData;
use LibreCodeCoop\NfsePHP\Dto\Nt009RealEstateUnitData;
use LibreCodeCoop\NfsePHP\Dto\Nt009RecipientAddressData;
use LibreCodeCoop\NfsePHP\Dto\Nt009RecipientData;
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
        self::assertSame(0, $this->xpath($draft->xml)->query(
            '/n:DPS/n:infDPS/n:IBSCBS/n:cIndOp'
        )?->length);
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
                exigeGrupoIbsCbs: true,
            )
        );
    }

    public function testCIndOpIsAbsentWhenOfficialClassificationForbidsDetails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cIndOp is forbidden when caller-verified ind_gIBSCBS is false');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                codigoIndicadorOperacao: '010101',
                cst: '400',
                classificacaoTributaria: '400001',
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

    public function testNewRecipientIsExplicitlyRepresentedAndXmlEscaped(): void
    {
        $xml = $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 1,
                destinatario: new Nt009RecipientData(
                    nome: 'Ana & Associados',
                    cnpj: '11222333000181',
                    endereco: new Nt009RecipientAddressData(
                        logradouro: 'Rua A & B',
                        numero: '42',
                        bairro: 'Centro',
                        municipioIbge: '3304557',
                        cep: '20000000',
                    ),
                ),
            )
        )->xml;

        self::assertSame('Ana & Associados', $this->first(
            $xml,
            '/n:DPS/n:infDPS/n:dest/n:xNome'
        )->textContent);
        self::assertSame('Rua A & B', $this->first(
            $xml,
            '/n:DPS/n:infDPS/n:dest/n:end/n:xLgr'
        )->textContent);
        self::assertStringContainsString('Ana &amp; Associados', $xml);
        self::assertTrue(strpos($xml, '<indDest>') < strpos($xml, '<dest>'));
        self::assertTrue(strpos($xml, '<dest>') < strpos($xml, '<serv>'));
    }

    public function testRecipientCannotBeSeparateWhenIndDestIsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not allowed when indDest=0');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                destinatario: new Nt009RecipientData(nome: 'Teste', cpf: '12345678901'),
            )
        );
    }

    public function testRecipientIdentityAndAddressChoiceAreValidated(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exactly one valid tax identity');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 1,
                destinatario: new Nt009RecipientData(
                    nome: 'Duplicated',
                    cpf: '12345678901',
                    cnpj: '11222333000181',
                ),
            )
        );
    }

    public function testNewBaseAdjustmentDoesNotEmitLegacyDeductionGroup(): void
    {
        $xml = $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                ajusteBase: new Nt009BaseAdjustmentData(valorIssqn: '10.25'),
            )
        )->xml;

        self::assertSame('10.25', $this->first(
            $xml,
            '/n:DPS/n:infDPS/n:valores/n:vAjusteBC/n:vAjusteBCISSQN'
        )->textContent);
        self::assertStringNotContainsString('<vDedRed>', $xml);
        self::assertTrue(strpos($xml, '<vAjusteBC>') < strpos($xml, '<trib>'));
    }

    public function testLegacyDeductionIsRejectedInsteadOfPassingThrough(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot reuse legacy vDedRed');
        $this->builder->previewNt009Dps(
            $this->makeDps(
                deducaoReducao: new DeductionReductionData(valor: '15.00')
            ),
            new Nt009DpsPreviewData(finalidade: 0, indicadorDestinatario: 0)
        );
    }

    public function testTwoBaseAdjustmentAlternativesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires exactly one');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                ajusteBase: new Nt009BaseAdjustmentData(
                    percentualIssqn: '5.00',
                    valorIssqn: '5.00',
                ),
            )
        );
    }

    public function testLinkedPaymentsUseSourceOrderAndEscapeTransactionText(): void
    {
        $xml = $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                codigoIndicadorOperacao: '010101',
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: true,
                pagamentosVinculados: [
                    new Nt009LinkedPaymentData(
                        numeroPagamento: '001',
                        identificadorTransacao: 'PIX&TX-01',
                        tipoMeioPagamento: '17',
                        cnpjRecebedor: '11222333000181',
                        cnpjBasePsp: '11222333',
                    ),
                ],
            )
        )->xml;

        self::assertSame('PIX&TX-01', $this->first(
            $xml,
            '/n:DPS/n:infDPS/n:IBSCBS/n:gPgtoVinc/n:pgto/n:idTransacao'
        )->textContent);
        self::assertStringContainsString('PIX&amp;TX-01', $xml);
        self::assertTrue(strpos($xml, '<valores>') < strpos($xml, '<gPgtoVinc>'));
    }

    public function testDuplicatedPaymentNumbersAreRejectedEvenWithLeadingZeroes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be unique');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                codigoIndicadorOperacao: '010101',
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: true,
                pagamentosVinculados: [
                    new Nt009LinkedPaymentData('1', 'TX-A', '17', '11222333000181', '11222333'),
                    new Nt009LinkedPaymentData('001', 'TX-B', '17', '11222333000181', '11222333'),
                ],
            )
        );
    }

    public function testMovableItemsRequireRelevantTaxCodeAndHaveBoundedQuantity(): void
    {
        $item = new Nt009MovableAssetData('12345678', 'Bem móvel', 2);
        $draft = $this->builder->previewNt009Dps(
            $this->makeDps(codigoTributacaoNacional: '990401'),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: false,
                bensMoveis: [$item],
            )
        );
        self::assertSame('12345678', $this->first(
            $draft->xml,
            '/n:DPS/n:infDPS/n:IBSCBS/n:bensMoveis/n:cNCMBemMovel'
        )->textContent);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exclusive to cTribNac');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: false,
                bensMoveis: [$item],
            )
        );
    }

    public function testPropertyLeaseAndUnitsFollowOfficialGroupOrder(): void
    {
        $xml = $this->builder->previewNt009Dps(
            $this->makeDps(codigoTributacaoNacional: '990301'),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: false,
                imovel: new Nt009RealEstateData(
                    municipioIbge: '3304557',
                    locacao: new Nt009LeaseData('40.00', '150.00'),
                    unidades: [
                        new Nt009RealEstateUnitData(
                            cib: '12345678',
                            cep: '20000000',
                            logradouro: 'Rua Um',
                            numero: '42',
                            ajustes: [new Nt009PropertyAdjustmentData('01', '10.00')],
                        ),
                    ],
                ),
            )
        )->xml;
        self::assertSame('150.00', $this->first(
            $xml,
            '/n:DPS/n:infDPS/n:IBSCBS/n:imovel/n:gLocacao/n:vTotOper'
        )->textContent);
        self::assertSame('12345678', $this->first(
            $xml,
            '/n:DPS/n:infDPS/n:IBSCBS/n:imovel/n:gUnidImob/n:cCIB'
        )->textContent);
        self::assertSame('10.00', $this->first(
            $xml,
            '/n:DPS/n:infDPS/n:IBSCBS/n:imovel/n:gUnidImob/n:gAjusteBCLocImoveis/n:vAjusteBCLocImoveis'
        )->textContent);
        self::assertSame(1, $this->xpath($xml)->query(
            '/n:DPS/n:infDPS/n:IBSCBS/n:imovel/following-sibling::n:valores'
        )?->length);
    }

    public function testPropertyLeaseRejectsUnrelatedNationalServiceCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('gLocacao requires cTribNac');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: false,
                imovel: new Nt009RealEstateData(
                    municipioIbge: '3304557',
                    locacao: new Nt009LeaseData('30.00', '150.00'),
                ),
            )
        );
    }

    public function testCondominiumChargesAndDetailsUseExactCentArithmetic(): void
    {
        $xml = $this->builder->previewNt009Dps(
            $this->makeDps(codigoTributacaoNacional: '990501'),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: false,
                condominios: new Nt009CondominiumData(
                    vencimentoOriginal: '2026-11-10',
                    cobrancas: [
                        new Nt009CondominiumChargeData(
                            tipo: '01',
                            valor: '150.00',
                            detalhes: [new Nt009CondominiumDetailData('001', '150.00')],
                        ),
                    ],
                ),
            )
        )->xml;
        self::assertSame('150.00', $this->first(
            $xml,
            '/n:DPS/n:infDPS/n:IBSCBS/n:condominios/n:gCobranca/n:gDetCobranca/n:vDetCobranca'
        )->textContent);
        self::assertSame('2026-11-10', $this->first(
            $xml,
            '/n:DPS/n:infDPS/n:IBSCBS/n:condominios/n:dVencOrig'
        )->textContent);
    }

    public function testLargeCondominiumAmountsReconcileWithoutIntegerOverflow(): void
    {
        $amount = '999999999999999.99';
        $xml = $this->builder->previewNt009Dps(
            $this->makeDps(codigoTributacaoNacional: '990501', valorServico: $amount),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: false,
                condominios: new Nt009CondominiumData(
                    '2026-11-10',
                    [new Nt009CondominiumChargeData('01', $amount)],
                ),
            )
        )->xml;
        self::assertSame($amount, $this->first(
            $xml,
            '/n:DPS/n:infDPS/n:IBSCBS/n:condominios/n:gCobranca/n:vCobranca'
        )->textContent);
    }

    public function testCondominiumAmountsMustReconcileWithServiceValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('charges must sum to service value');
        $this->builder->previewNt009Dps(
            $this->makeDps(codigoTributacaoNacional: '990501'),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: false,
                condominios: new Nt009CondominiumData(
                    '2026-11-10',
                    [new Nt009CondominiumChargeData('01', '149.99')],
                ),
            )
        );
    }

    public function testDocumentBasedAdjustmentRendersEachExclusiveReferenceType(): void
    {
        $xml = $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                ajusteBase: new Nt009BaseAdjustmentData(
                    documentos: [
                        new Nt009AdjustmentDocumentData(
                            tipo: '1',
                            valorTotalDocumento: '120.00',
                            valorAjustado: '25.00',
                            referencia: new Nt009NationalInvoiceReference('1', 'NATIONAL-KEY-123'),
                            dataEmissao: '2026-10-07',
                        ),
                        new Nt009AdjustmentDocumentData(
                            tipo: '2',
                            valorTotalDocumento: '200.00',
                            valorAjustado: '15.00',
                            referencia: new Nt009OtherFiscalReference(
                                '3304557',
                                'DOC-55',
                                'Serviço público & fiscal'
                            ),
                        ),
                        new Nt009AdjustmentDocumentData(
                            tipo: '3',
                            valorTotalDocumento: '80.00',
                            valorAjustado: '10.00',
                            referencia: new Nt009OtherDocumentReference('OTHER-01', 'Contrato & aviso'),
                        ),
                    ],
                ),
            )
        )->xml;

        self::assertSame(3, $this->xpath($xml)->query(
            '/n:DPS/n:infDPS/n:valores/n:vAjusteBC/n:documentos/n:docAjusteBC'
        )?->length);
        self::assertSame('NATIONAL-KEY-123', $this->first(
            $xml,
            '/n:DPS/n:infDPS/n:valores/n:vAjusteBC/n:documentos/n:docAjusteBC/n:dFeNacional/n:chaveDFe'
        )->textContent);
        self::assertSame('Serviço público & fiscal', $this->first(
            $xml,
            '/n:DPS/n:infDPS/n:valores/n:vAjusteBC/n:documentos/n:docAjusteBC/n:docFiscalOutro/n:xDocFiscal'
        )->textContent);
        self::assertSame('Contrato & aviso', $this->first(
            $xml,
            '/n:DPS/n:infDPS/n:valores/n:vAjusteBC/n:documentos/n:docAjusteBC/n:docOutro/n:xDoc'
        )->textContent);
        self::assertStringContainsString('Contrato &amp; aviso', $xml);
        self::assertStringNotContainsString('<vDedRed>', $xml);
    }

    public function testDocumentAdjustmentCanPreserveVettedSupplierDetails(): void
    {
        $xml = $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                ajusteBase: new Nt009BaseAdjustmentData(
                    documentos: [
                        new Nt009AdjustmentDocumentData(
                            tipo: '1',
                            valorTotalDocumento: '150.00',
                            valorAjustado: '50.00',
                            referencia: new Nt009OtherDocumentReference('C-7', 'Contract'),
                            fornecedor: new Nt009RecipientData(
                                nome: 'Fornecedor & Filhos',
                                cpf: '12345678901',
                                endereco: new Nt009RecipientAddressData(
                                    logradouro: 'Rua & Filiais',
                                    numero: '10',
                                    bairro: 'Centro',
                                    municipioIbge: '3304557',
                                    cep: '20000000',
                                ),
                            ),
                        ),
                    ],
                ),
            )
        )->xml;
        self::assertSame('Fornecedor & Filhos', $this->first(
            $xml,
            '/n:DPS/n:infDPS/n:valores/n:vAjusteBC/n:documentos/n:docAjusteBC/n:fornec/n:xNome'
        )->textContent);
        self::assertSame('Rua & Filiais', $this->first(
            $xml,
            '/n:DPS/n:infDPS/n:valores/n:vAjusteBC/n:documentos/n:docAjusteBC/n:fornec/n:xLgr'
        )->textContent);
        self::assertSame(0, $this->xpath($xml)->query(
            '/n:DPS/n:infDPS/n:valores/n:vAjusteBC/n:documentos/n:docAjusteBC/n:fornec/n:end/n:xLgr'
        )?->length);
        self::assertStringContainsString('Rua &amp; Filiais', $xml);
    }

    public function testDocumentAdjustmentCannotBeCombinedWithValueMode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires exactly one');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                ajusteBase: new Nt009BaseAdjustmentData(
                    valorIssqn: '5.00',
                    documentos: [
                        new Nt009AdjustmentDocumentData(
                            tipo: '1',
                            valorTotalDocumento: '150.00',
                            valorAjustado: '50.00',
                            referencia: new Nt009OtherDocumentReference('C-7', 'Contract'),
                        ),
                    ],
                ),
            )
        );
    }

    public function testDocumentAdjustmentRejectsInvalidDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid NT009 date field');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                ajusteBase: new Nt009BaseAdjustmentData(
                    documentos: [
                        new Nt009AdjustmentDocumentData(
                            tipo: '1',
                            valorTotalDocumento: '150.00',
                            valorAjustado: '50.00',
                            referencia: new Nt009OtherDocumentReference('C-7', 'Contract'),
                            dataEmissao: '2026-02-30',
                        ),
                    ],
                ),
            )
        );
    }

    public function testDocumentAdjustmentRejectsInvalidFinancialPrecision(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid NT009 decimal field');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                ajusteBase: new Nt009BaseAdjustmentData(
                    documentos: [
                        new Nt009AdjustmentDocumentData(
                            tipo: '1',
                            valorTotalDocumento: '150.00',
                            valorAjustado: '50.001',
                            referencia: new Nt009OtherDocumentReference('C-7', 'Contract'),
                        ),
                    ],
                ),
            )
        );
    }


    public function testDocumentAdjustmentCannotExceedTotalAtLargePrecision(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('vAjusteAplic cannot exceed vTotDoc');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                ajusteBase: new Nt009BaseAdjustmentData(
                    documentos: [
                        new Nt009AdjustmentDocumentData(
                            '1',
                            '999999999999998.99',
                            '999999999999999.00',
                            new Nt009OtherDocumentReference('CON-1', 'Contrato'),
                        ),
                    ],
                ),
            )
        );
    }

    public function testDocumentAdjustmentCanEqualLargeTotal(): void
    {
        $amount = '999999999999999.99';
        $draft = $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                ajusteBase: new Nt009BaseAdjustmentData(
                    documentos: [
                        new Nt009AdjustmentDocumentData(
                            '199',
                            $amount,
                            $amount,
                            new Nt009OtherDocumentReference('CON-1', 'Contrato'),
                            descricaoTipo: 'Reembolso',
                        ),
                    ],
                ),
            )
        );
        self::assertSame($amount, $this->first(
            $draft->xml,
            '/n:DPS/n:infDPS/n:valores/n:vAjusteBC/n:documentos/n:docAjusteBC/n:vAjusteAplic'
        )->textContent);
    }

    public function testDocumentAdjustmentRejectsUnlistedPublishedType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid NT009 tpAjusteBC');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                ajusteBase: new Nt009BaseAdjustmentData(
                    documentos: [
                        new Nt009AdjustmentDocumentData(
                            '100',
                            '50.00',
                            '20.00',
                            new Nt009OtherDocumentReference('CON-1', 'Contrato'),
                        ),
                    ],
                ),
            )
        );
    }

    public function testDocumentAdjustmentDescriptionCannotAccompanyOrdinaryType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid NT009 tpAjusteBC');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                ajusteBase: new Nt009BaseAdjustmentData(
                    documentos: [
                        new Nt009AdjustmentDocumentData(
                            '1',
                            '50.00',
                            '20.00',
                            new Nt009OtherDocumentReference('CON-1', 'Contrato'),
                            descricaoTipo: 'Descrição não prevista',
                        ),
                    ],
                ),
            )
        );
    }

    public function testNationalDfeReferenceRejectsUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid NT009 dFeNacional reference');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                ajusteBase: new Nt009BaseAdjustmentData(
                    documentos: [
                        new Nt009AdjustmentDocumentData(
                            '1',
                            '50.00',
                            '20.00',
                            new Nt009NationalInvoiceReference('4', 'KEY-123'),
                        ),
                    ],
                ),
            )
        );
    }

    public function testNationalDfeDescriptionRequiresOtherType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid NT009 dFeNacional reference');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                ajusteBase: new Nt009BaseAdjustmentData(
                    documentos: [
                        new Nt009AdjustmentDocumentData(
                            '1',
                            '50.00',
                            '20.00',
                            new Nt009NationalInvoiceReference('1', 'KEY-123', 'Outra espécie'),
                        ),
                    ],
                ),
            )
        );
    }

    public function testNationalDfeOtherTypeAcceptsDescription(): void
    {
        $draft = $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                ajusteBase: new Nt009BaseAdjustmentData(
                    documentos: [
                        new Nt009AdjustmentDocumentData(
                            '1',
                            '50.00',
                            '20.00',
                            new Nt009NationalInvoiceReference('9', 'KEY-123', 'Outro documento'),
                        ),
                    ],
                ),
            )
        );
        self::assertSame('Outro documento', $this->first(
            $draft->xml,
            '/n:DPS/n:infDPS/n:valores/n:vAjusteBC/n:documentos/n:docAjusteBC/n:dFeNacional/n:xTipoChaveDFe'
        )->textContent);
    }

    public function testPropertyAdjustmentRejectsUnknownCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid NT009 property adjustment type');
        $this->builder->previewNt009Dps(
            $this->makeDps(codigoTributacaoNacional: '990301'),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: false,
                imovel: new Nt009RealEstateData(
                    municipioIbge: '3304557',
                    unidades: [
                        new Nt009RealEstateUnitData(
                            cib: '12345678',
                            cep: '20000000',
                            logradouro: 'Rua Um',
                            numero: '42',
                            ajustes: [new Nt009PropertyAdjustmentData('98', '10.00')],
                        ),
                    ],
                ),
            )
        );
    }

    public function testPropertyAdjustmentDescriptionRequiresOtherCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid NT009 property adjustment type');
        $this->builder->previewNt009Dps(
            $this->makeDps(codigoTributacaoNacional: '990301'),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: false,
                imovel: new Nt009RealEstateData(
                    municipioIbge: '3304557',
                    unidades: [
                        new Nt009RealEstateUnitData(
                            cib: '12345678',
                            cep: '20000000',
                            logradouro: 'Rua Um',
                            numero: '42',
                            ajustes: [new Nt009PropertyAdjustmentData('01', '10.00', 'Indevido')],
                        ),
                    ],
                ),
            )
        );
    }

    public function testExteriorIbsCbsAdjustmentDoesNotRequireIssqnMode(): void
    {
        $draft = $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                ajusteBase: new Nt009BaseAdjustmentData(valorIbsCbsComExterior: '12.50'),
            )
        );
        self::assertSame('12.50', $this->first(
            $draft->xml,
            '/n:DPS/n:infDPS/n:valores/n:vAjusteBC/n:vAjusteBCIBSCBSComExt'
        )->textContent);
    }

    public function testNt009CreditReversalAndAdvanceReferencesFollowAnnexOrder(): void
    {
        $first = str_repeat('1', 50);
        $second = str_repeat('2', 50);
        $draft = $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                codigoIndicadorOperacao: '010101',
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: true,
                exigeEstornoCredito: true,
                valorEstornoIbs: '123456789012345.67',
                valorEstornoCbs: '0.05',
                notasPagamentoAntecipado: [$first, $second],
            )
        );
        self::assertSame('123456789012345.67', $this->first(
            $draft->xml,
            '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib/n:gIBSCBS/n:gEstornoCred/n:vIBSEstCred'
        )->textContent);
        self::assertSame('0.05', $this->first(
            $draft->xml,
            '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib/n:gIBSCBS/n:gEstornoCred/n:vCBSEstCred'
        )->textContent);
        self::assertSame(2, $this->xpath($draft->xml)->query(
            '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib/n:gIBSCBS/n:gPagAntecipado/n:refNFSe'
        )?->length);
        self::assertSame($first, $this->first(
            $draft->xml,
            '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib/n:gIBSCBS/n:gPagAntecipado/n:refNFSe[1]'
        )->textContent);
        self::assertLessThan(strpos($draft->xml, '<gPagAntecipado>'), strpos($draft->xml, '<gEstornoCred>'));
    }

    public function testCreditReversalRejectsMissingOfficialClassificationEvidence(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('caller-verified ind_gEstornoCred');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                codigoIndicadorOperacao: '010101',
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: true,
                valorEstornoIbs: '10.00',
                valorEstornoCbs: '20.00',
            )
        );
    }

    public function testCreditReversalRequiresAmountsWhenOfficialIndicatorIsTrue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ind_gEstornoCred requires both');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                codigoIndicadorOperacao: '010101',
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: true,
                exigeEstornoCredito: true,
            )
        );
    }

    public function testCreditReversalRejectsIncompletePair(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires both vIBSEstCred and vCBSEstCred');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                codigoIndicadorOperacao: '010101',
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: true,
                exigeEstornoCredito: true,
                valorEstornoIbs: '10.00',
            )
        );
    }

    public function testCreditReversalRejectsFalseOfficialIndicator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires caller-verified ind_gEstornoCred');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                codigoIndicadorOperacao: '010101',
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: true,
                exigeEstornoCredito: false,
                valorEstornoIbs: '10.00',
                valorEstornoCbs: '20.00',
            )
        );
    }

    public function testCreditReversalRejectsMalformedAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires decimal');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                codigoIndicadorOperacao: '010101',
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: true,
                exigeEstornoCredito: true,
                valorEstornoIbs: '10.001',
                valorEstornoCbs: '20.00',
            )
        );
    }

    public function testCreditReversalIsAbsentWithVerifiedFalseIndicator(): void
    {
        $draft = $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                codigoIndicadorOperacao: '010101',
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: true,
                exigeEstornoCredito: false,
            )
        );
        self::assertSame(0, $this->xpath($draft->xml)->query(
            '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib/n:gIBSCBS/n:gEstornoCred'
        )?->length);
    }

    public function testAdvanceReferencesRejectIncompleteKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('refNFSe must contain 50');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                codigoIndicadorOperacao: '010101',
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: true,
                notasPagamentoAntecipado: [str_repeat('9', 49)],
            )
        );
    }

    public function testAdvanceReferencesRejectMoreThan99Keys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at most 99');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                codigoIndicadorOperacao: '010101',
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: true,
                notasPagamentoAntecipado: array_fill(0, 100, str_repeat('9', 50)),
            )
        );
    }

    public function testAdvanceReferencesCannotAppearWhenGIBSCBSIsForbidden(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('forbids gIBSCBS children');
        $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: false,
                notasPagamentoAntecipado: [str_repeat('9', 50)],
            )
        );
    }

    public function testAdvanceReferencesAreOmittedWhenEmpty(): void
    {
        $draft = $this->builder->previewNt009Dps(
            $this->makeDps(),
            new Nt009DpsPreviewData(
                finalidade: 0,
                indicadorDestinatario: 0,
                codigoIndicadorOperacao: '010101',
                cst: '000',
                classificacaoTributaria: '000001',
                exigeGrupoIbsCbs: true,
            )
        );
        self::assertSame(0, $this->xpath($draft->xml)->query(
            '/n:DPS/n:infDPS/n:IBSCBS/n:valores/n:trib/n:gIBSCBS/n:gPagAntecipado'
        )?->length);
    }

    private function makeDps(
        ?int $ibsCbsFinalidade = null,
        string $codigoTributacaoNacional = '010701',
        ?DeductionReductionData $deducaoReducao = null,
        string $valorServico = '150.00',
    ): DpsData {
        return new DpsData(
            cnpjPrestador: '11222333000181',
            municipioIbge: '3303302',
            itemListaServico: '0107',
            valorServico: $valorServico,
            aliquota: '5.00',
            discriminacao: 'Servico de tecnologia',
            serie: '00001',
            numeroDps: '17',
            codigoTributacaoNacional: $codigoTributacaoNacional,
            ibsCbsFinalidade: $ibsCbsFinalidade,
            deducaoReducao: $deducaoReducao,
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
