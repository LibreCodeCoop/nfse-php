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
        self::assertSame('Niterói / RJ / BR', $data['tributacao_municipal']['municipio_incidencia']);

        // Totais
        self::assertSame('R$ 1.500,00', $data['totais']['valor_servico']);
        self::assertSame('R$ 1.292,75', $data['totais']['valor_liquido']);
    }

    public function testOfficialLocationsAndProviderContactUseOnlyDpsData(): void
    {
        $nfse = $this->fixtureNfseData();
        $dps = & $nfse['infNFSe']['DPS']['infDPS'];
        $dps['prest']['IM'] = '12345678';
        // Deliberately invalid Brazilian DDD 00: never use a real person's phone in fixtures.
        $dps['prest']['fone'] = '00900000000';
        $dps['prest']['email'] = 'fiscal@example.org';
        unset($nfse['infNFSe']['emit']['fone'], $nfse['infNFSe']['emit']['email']);
        $nfse['infNFSe']['xLocPrestacao'] = 'Curitiba';
        $dps['serv']['locPrest']['cLocPrestacao'] = '4106902';
        $nfse['infNFSe']['xLocIncid'] = 'Curitiba';
        $nfse['infNFSe']['cLocIncid'] = '4106902';
        // Format-only fixture; deliberately not an official service classification.
        $dps['serv']['cServ']['cNBS'] = '000000000';

        $data = (new DanfseTemplate())->buildData($nfse);

        self::assertSame('12345678', $data['emitente']['im']);
        self::assertSame('(00) 90000-0000', $data['emitente']['telefone']);
        self::assertSame('fiscal@example.org', $data['emitente']['email']);
        self::assertSame('Curitiba / PR / BR', $data['servico']['local_prestacao']);
        self::assertSame('Curitiba / PR / BR', $data['tributacao_municipal']['municipio_incidencia']);
        self::assertSame('0.0000.00.00', $data['servico']['codigo_nbs']);
    }

    public function testAuthorizedForeignCountryOverridesBrazilianFallback(): void
    {
        $nfse = $this->fixtureNfseData();
        $nfse['infNFSe']['xLocPrestacao'] = '';
        $nfse['infNFSe']['DPS']['infDPS']['serv']['locPrest'] = ['cPaisPrestacao' => 'PT'];
        $nfse['infNFSe']['xLocIncid'] = '';
        $nfse['infNFSe']['cLocIncid'] = '';
        $nfse['infNFSe']['DPS']['infDPS']['valores']['trib']['tribMun']['cPaisResult'] = 'PT';

        $data = (new DanfseTemplate())->buildData($nfse);

        self::assertSame('- / - / PT', $data['servico']['local_prestacao']);
        self::assertSame('- / - / PT', $data['tributacao_municipal']['municipio_incidencia']);
    }

    public function testUnknownMunicipalityDoesNotInventAStateOrCountry(): void
    {
        $nfse = $this->fixtureNfseData();
        $nfse['infNFSe']['cLocIncid'] = '9999999';
        $nfse['infNFSe']['xLocIncid'] = 'Municipio Desconhecido';

        $data = (new DanfseTemplate())->buildData($nfse);

        self::assertSame('Municipio Desconhecido / - / -', $data['tributacao_municipal']['municipio_incidencia']);
    }

    public function testLayoutHasAlignedDpsMetadataAndNT008GrayShadingOnlyWhereRequired(): void
    {
        $html = (new DanfseTemplate())->render($this->fixtureNfseData(), new DanfseConfig());

        self::assertStringContainsString('rowspan="4"', $html);
        self::assertStringContainsString('class="emitter-cell"', $html);
        self::assertStringContainsString('class="total-emphasis"', $html);
        self::assertStringContainsString('Local da Prestação / Sigla UF / País', $html);
        self::assertStringContainsString('Município / Sigla UF / País de Incidência do ISSQN', $html);
        self::assertStringNotContainsString('colspan="4" class="section-header"', $html);
        self::assertSame(1, substr_count($html, 'class="value value-highlight"'));
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

    public function testMunicipalTaxDescriptionWinsButNationalIsFallback(): void
    {
        $nfse = $this->fixtureNfseData();
        $nfse['infNFSe']['xTribNac'] = 'Descricao nacional sintetica';
        $nfse['infNFSe']['xTribMun'] = 'Descricao municipal sintetica';

        $template = new DanfseTemplate();
        self::assertSame('Descricao municipal sintetica', $template->buildData($nfse)['servico']['desc_trib_nacional']);
        self::assertStringContainsString('Descricao municipal sintetica', $template->render($nfse, new DanfseConfig()));
        self::assertStringNotContainsString('Descricao nacional sintetica', $template->render($nfse, new DanfseConfig()));

        unset($nfse['infNFSe']['xTribMun']);
        self::assertSame('Descricao nacional sintetica', $template->buildData($nfse)['servico']['desc_trib_nacional']);
    }

    public function testFederalDetailRowFollowsOriginalFiscalCompetence(): void
    {
        $nfse = $this->fixtureNfseData();
        $template = new DanfseTemplate();

        $nfse['infNFSe']['DPS']['infDPS']['dCompet'] = '2026-12-31';
        self::assertTrue($template->buildData($nfse)['tributacao_federal']['mostrar_linha_2026']);
        self::assertStringContainsString('class="federal-competence-2026"', $template->render($nfse, new DanfseConfig()));

        $nfse['infNFSe']['DPS']['infDPS']['dCompet'] = '2027-01-01';
        self::assertFalse($template->buildData($nfse)['tributacao_federal']['mostrar_linha_2026']);
        $html = $template->render($nfse, new DanfseConfig());
        self::assertStringNotContainsString('class="federal-competence-2026"', $html);
        self::assertStringContainsString('Contribuições Sociais - Retidas', $html);

        unset($nfse['infNFSe']['DPS']['infDPS']['dCompet']);
        self::assertFalse($template->buildData($nfse)['tributacao_federal']['mostrar_linha_2026']);
    }

    public function testAuthorizedApproximateTaxValuesAreCopiedWithoutZeroFillingOrIssFallback(): void
    {
        $nfse = $this->fixtureNfseData();
        $nfse['infNFSe']['valores']['pAliqAplic'] = '2.00';
        $nfse['infNFSe']['valores']['vISSQN'] = '630.00';
        $totals = & $nfse['infNFSe']['DPS']['infDPS']['valores']['trib']['totTrib'];

        $totals = ['indTotTrib' => '0'];
        $unknown = (new DanfseTemplate())->buildData($nfse);

        self::assertSame('2.00%', $unknown['tributacao_municipal']['aliquota']);
        self::assertSame('R$ 630,00', $unknown['tributacao_municipal']['issqn_apurado']);
        self::assertSame(
            ['federais' => '-', 'estaduais' => '-', 'municipais' => '-'],
            $unknown['totais_tributos'],
        );
        self::assertStringContainsString(
            'Federais: -; Estaduais: -; Municipais: -;',
            $unknown['informacoes_complementares'],
        );

        // These are explicit source declarations, not the assessed ISS rate.
        $totals = [
            'pTotTrib' => [
                'pTotTribFed' => '0.00',
                'pTotTribEst' => '0.00',
                'pTotTribMun' => '0.00',
            ],
        ];
        $documentedZero = (new DanfseTemplate())->buildData($nfse);
        self::assertSame('0.00%', $documentedZero['totais_tributos']['municipais']);
        self::assertStringContainsString(
            'Federais: 0.00%; Estaduais: 0.00%; Municipais: 0.00%;',
            $documentedZero['informacoes_complementares'],
        );

        $totals['pTotTrib']['pTotTribFed'] = '7.45';
        $totals['pTotTrib']['pTotTribMun'] = '4.10';
        $sourced = (new DanfseTemplate())->buildData($nfse);
        self::assertSame('7.45%', $sourced['totais_tributos']['federais']);
        self::assertSame('4.10%', $sourced['totais_tributos']['municipais']);
        self::assertSame('2.00%', $sourced['tributacao_municipal']['aliquota']);
        self::assertSame('R$ 630,00', $sourced['tributacao_municipal']['issqn_apurado']);
    }

    public function testFederalRetainedPisCofinsFollowsNt008PresentationWithoutRecomputingTax(): void
    {
        $nfse = $this->fixtureNfseData();
        $federal = & $nfse['infNFSe']['DPS']['infDPS']['valores']['trib']['tribFed'];
        $federal['vRetCSLL'] = '3.00';
        $federal['piscofins'] = [
            'vPis' => '2.25',
            'vCofins' => '4.50',
            'tpRetPisCofins' => '1',
        ];

        $template = new DanfseTemplate();
        $data = $template->buildData($nfse);
        self::assertSame('R$ 9,75', $data['tributacao_federal']['contribuicoes_sociais']);
        self::assertSame('R$ 0,00', $data['tributacao_federal']['pis']);
        self::assertSame('R$ 0,00', $data['tributacao_federal']['cofins']);
        self::assertSame('R$ 3,00', $data['tributacao_federal']['csll']);

        $federal['piscofins']['tpRetPisCofins'] = '4';
        $data = $template->buildData($nfse);
        self::assertSame('R$ 3,00', $data['tributacao_federal']['contribuicoes_sociais']);
        self::assertSame('R$ 2,25', $data['tributacao_federal']['pis']);
        self::assertSame('R$ 4,50', $data['tributacao_federal']['cofins']);

        unset($federal['vRetCSLL']);
        $data = $template->buildData($nfse);
        self::assertSame('-', $data['tributacao_federal']['contribuicoes_sociais']);
    }

    public function testImmunityExportationAndNonIncidenceUseNoIssBlock(): void
    {
        $nfse = $this->fixtureNfseData();
        $template = new DanfseTemplate();
        $municipal = & $nfse['infNFSe']['DPS']['infDPS']['valores']['trib']['tribMun'];

        foreach (['2', '3', '4'] as $code) {
            $municipal['tribISSQN'] = $code;
            self::assertTrue($template->buildData($nfse)['tributacao_municipal']['nao_sujeito_issqn']);
            $html = $template->render($nfse, new DanfseConfig());
            self::assertStringContainsString('OPERAÇÃO NÃO SUJEITA AO ISSQN', $html);
            self::assertStringNotContainsString('class="municipal-conditional-regime"', $html);
        }

        $municipal['tribISSQN'] = '1';
        self::assertFalse($template->buildData($nfse)['tributacao_municipal']['nao_sujeito_issqn']);
        self::assertStringContainsString('class="municipal-conditional-regime"', $template->render($nfse, new DanfseConfig()));
    }

    public function testIbsCbsFullGridUsesOnlyAuthorizedAmountsAndRates(): void
    {
        $nfse = $this->fixtureNfseData();
        $authorized = & $nfse['infNFSe'];
        $dps = & $authorized['DPS']['infDPS'];
        $dps['valores']['vDescCondIncond'] = ['vDescIncond' => '10.00', 'vDescCond' => '5.00'];
        $authorized['valores']['vISSQN'] = '7.00';
        $authorized['valores']['vTotalRet'] = '123.45';
        $authorized['IBSCBS'] = [
            'cLocalidadeIncid' => '3303302',
            'xLocalidadeIncid' => 'Niterói',
            'valores' => [
                'vBC' => '1450.00',
                'vCalcReeRepRes' => '20.00',
                'uf' => ['pRedAliqUF' => '0.00', 'pIBSUF' => '0.10', 'pAliqEfetUF' => '0.10'],
                'mun' => ['pRedAliqMun' => '0.00', 'pIBSMun' => '0.05', 'pAliqEfetMun' => '0.05'],
                'fed' => ['pRedAliqCBS' => '0.00', 'pCBS' => '0.90', 'pAliqEfetCBS' => '0.90'],
            ],
            'totCIBS' => [
                'vTotNF' => '1515.75',
                'gIBS' => [
                    'vIBSTot' => '2.25',
                    'gIBSMunTot' => ['vIBSMun' => '0.75'],
                    'gIBSUFTot' => ['vIBSUF' => '1.50'],
                ],
                'gCBS' => ['vCBS' => '13.50'],
            ],
        ];
        $dps['IBSCBS']['cIndOp'] = '010101';

        $template = new DanfseTemplate();
        $data = $template->buildData($nfse);
        self::assertSame('010101 / 3303302 / Niterói / RJ', $data['ibs_cbs']['indicador_operacao']);
        self::assertSame('R$ 91,75', $data['ibs_cbs']['exclusoes_reducoes']);
        self::assertSame('0.00% / 0.00% / 0.00%', $data['ibs_cbs']['reducao_aliquotas']);
        self::assertSame('0.10% / 0.05%', $data['ibs_cbs']['aliquota_ibs']);
        self::assertSame('0.05%', $data['ibs_cbs']['aliquota_efetiva_municipal']);
        self::assertSame('R$ 0,75', $data['ibs_cbs']['valor_ibs_municipal']);
        self::assertSame('0.10%', $data['ibs_cbs']['aliquota_efetiva_estadual']);
        self::assertSame('R$ 1,50', $data['ibs_cbs']['valor_ibs_estadual']);
        self::assertSame('0.90%', $data['ibs_cbs']['aliquota_efetiva_cbs']);
        self::assertSame('R$ 15,75', $data['ibs_cbs']['total_ibs_cbs']);
        self::assertSame('R$ 123,45', $data['totais']['total_retencoes']);
        self::assertSame('R$ 10,00', $data['totais']['desconto_incondicionado']);
        self::assertSame('R$ 5,00', $data['totais']['desconto_condicionado']);

        $html = $template->render($nfse, new DanfseConfig());
        self::assertStringContainsString('Valor Apurado Municipal - IBS', $html);
        self::assertStringContainsString('Valor Apurado Estadual - IBS', $html);
        self::assertStringContainsString('Alíquota Efetiva - CBS', $html);
        self::assertStringContainsString('Total das Retenções (ISSQN / Federais)', $html);
    }

    public function testAbsentIbsOrCbsAmountIsNotTreatedAsRealZero(): void
    {
        $nfse = $this->fixtureNfseData();
        $nfse['infNFSe']['IBSCBS']['totCIBS']['gIBS']['vIBSTot'] = '0.00';
        $template = new DanfseTemplate();
        self::assertSame('-', $template->buildData($nfse)['ibs_cbs']['total_ibs_cbs']);

        $nfse['infNFSe']['IBSCBS']['totCIBS']['gCBS']['vCBS'] = '0.00';
        self::assertSame('R$ 0,00', $template->buildData($nfse)['ibs_cbs']['total_ibs_cbs']);
    }

    public function testLegacyXmlWithoutIbsCbsDoesNotInventIbsExclusions(): void
    {
        $nfse = $this->fixtureNfseData();
        unset($nfse['infNFSe']['IBSCBS']);
        // The old DPS can still contain PIS/COFINS amounts. Without any
        // authorized IBS/CBS block they are not a new IBS base reduction.
        $nfse['infNFSe']['DPS']['infDPS']['valores']['trib']['tribFed']['piscofins']['vPis'] = '12.25';

        $model = (new DanfseTemplate())->buildData($nfse);
        self::assertSame('-', $model['ibs_cbs']['exclusoes_reducoes']);
        self::assertSame('-', $model['ibs_cbs']['base_calculo']);
    }

    public function testAuthorizedTotalRetentionNeverComesFromPartialDpsSum(): void
    {
        $nfse = $this->fixtureNfseData();
        $dps = & $nfse['infNFSe']['DPS']['infDPS'];
        $dps['valores']['trib']['tribFed']['vRetIRRF'] = '99.99';

        $template = new DanfseTemplate();
        self::assertSame('-', $template->buildData($nfse)['totais']['total_retencoes']);

        $nfse['infNFSe']['valores']['vTotalRet'] = '0.00';
        self::assertSame('R$ 0,00', $template->buildData($nfse)['totais']['total_retencoes']);

        $nfse['infNFSe']['valores']['vTotalRet'] = '78.90';
        self::assertSame('R$ 78,90', $template->buildData($nfse)['totais']['total_retencoes']);
    }

    public function testComplementaryInformationFollowsOfficialOrderWithoutInventingTaxZeros(): void
    {
        $nfse = $this->fixtureNfseData();
        $dps = & $nfse['infNFSe']['DPS']['infDPS'];
        $dps['subst'] = ['chSubstda' => str_repeat('3', 50)];
        $dps['serv']['obra'] = [
            'cObra' => 'OBRA-FICTICIA-001',
            'inscImobFisc' => 'IMOB-TESTE-0002',
        ];
        $dps['serv']['atvEvento'] = ['idAtvEvt' => 'EVENTO-TESTE-123'];
        $dps['serv']['infoCompl'] = [
            'xInfComp' => 'Observacao sintetica',
            'docRef' => 'DOC-REF-123',
            'idDocTec' => 'DOC-TEC-456',
            'xPed' => 'PED-456',
            'gItemPed' => ['xItemPed' => ['01', '02']],
        ];
        $nfse['infNFSe']['xOutInf'] = 'Mensagem municipal sintetica';
        $dps['valores']['trib']['totTrib'] = [
            'vTotTrib' => [
                'vTotTribFed' => '0.00',
                'vTotTribEst' => '15.50',
                'vTotTribMun' => '3.25',
            ],
        ];

        $data = (new DanfseTemplate())->buildData($nfse);
        $actual = $data['informacoes_complementares'];
        foreach (['Inf. Cont.:', 'NFS-e Subst.:', 'Doc. Ref.:', 'Cod. Obra:',
            'Insc. Imob.:', 'Cod. Evt.:', 'Doc. Tec.:', 'Núm. Ped.:',
            'Item Ped.:', 'Inf. A. T. Mun.:', 'Totais aproximados dos Tributos'] as $part) {
            self::assertStringContainsString($part, $actual);
        }
        self::assertSame('R$ 0,00', $data['totais_tributos']['federais']);
        self::assertSame('R$ 15,50', $data['totais_tributos']['estaduais']);
        self::assertSame('R$ 3,25', $data['totais_tributos']['municipais']);
        self::assertStringContainsString('Item Ped.: 01, 02', $actual);
        self::assertStringContainsString('Federais: R$ 0,00; Estaduais: R$ 15,50; Municipais: R$ 3,25', $actual);
        self::assertLessThan(strpos($actual, 'Doc. Ref.:'), strpos($actual, 'NFS-e Subst.:'));
        self::assertLessThan(strpos($actual, 'Totais aproximados'), strpos($actual, 'Inf. A. T. Mun.:'));
    }

    public function testAllAbsentApproximateTaxesStayAbsentAndRealZeroIsDistinct(): void
    {
        $nfse = $this->fixtureNfseData();
        unset($nfse['infNFSe']['DPS']['infDPS']['valores']['trib']['totTrib']);

        $template = new DanfseTemplate();
        $data = $template->buildData($nfse);
        self::assertSame('-', $data['totais_tributos']['municipais']);
        self::assertStringContainsString('Municipais: -;', $data['informacoes_complementares']);

        $nfse['infNFSe']['DPS']['infDPS']['valores']['trib']['totTrib']['pTotTrib']['pTotTribMun'] = '0.00';
        $data = $template->buildData($nfse);
        self::assertSame('0.00%', $data['totais_tributos']['municipais']);
        self::assertStringContainsString('Municipais: 0.00%', $data['informacoes_complementares']);
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

    public function testRecipientIsIndependentOfIntermediaryAndUsesAuthorizedDps(): void
    {
        $nfse = $this->fixtureNfseData();
        $dps = & $nfse['infNFSe']['DPS']['infDPS'];
        unset($dps['interm']);
        $dps['IBSCBS']['dest'] = [
            'NIF' => 'PT-TEST-998877',
            'xNome' => 'DESTINATARIO SINTETICO',
            'end' => [
                'endExt' => [
                    'cPais' => 'PT',
                    'cEndPost' => '0000-001',
                    'xCidade' => 'Cidade Ficticia',
                    'xEstProvReg' => 'Distrito Ficticio',
                ],
                'xLgr' => 'Rua Exemplo',
                'nro' => '1',
                'xBairro' => 'Bairro Modelo',
            ],
            'email' => 'recipient@example.org',
        ];

        $template = new DanfseTemplate();
        $data = $template->buildData($nfse);
        self::assertNull($data['intermediario']);
        self::assertSame('PT-TEST-998877', $data['destinatario']['cnpj_cpf']);
        self::assertSame('Cidade Ficticia - Distrito Ficticio', $data['destinatario']['municipio']);
        self::assertSame('- / 0000-001', $data['destinatario']['ibge_cep']);
        self::assertFalse($data['destinatario']['mesmo_tomador']);

        $html = $template->render($nfse, new DanfseConfig());
        self::assertStringContainsString('DESTINATÁRIO DA OPERAÇÃO', $html);
        self::assertStringContainsString('recipient@example.org', $html);
        self::assertStringContainsString('INTERMEDIÁRIO DA OPERAÇÃO NÃO IDENTIFICADO', $html);
        self::assertStringNotContainsString('DESTINATÁRIO DA OPERAÇÃO NÃO IDENTIFICADO', $html);
    }

    public function testSameRecipientAndTakerUseOfficialConciseMessage(): void
    {
        $nfse = $this->fixtureNfseData();
        $dps = & $nfse['infNFSe']['DPS']['infDPS'];
        $dps['IBSCBS']['dest'] = [
            'CNPJ' => $dps['toma']['CNPJ'],
            'xNome' => 'DESTINATARIO SINTETICO',
        ];

        $template = new DanfseTemplate();
        self::assertTrue($template->buildData($nfse)['destinatario']['mesmo_tomador']);
        $html = $template->render($nfse, new DanfseConfig());
        self::assertStringContainsString('O DESTINATÁRIO É O PRÓPRIO TOMADOR/ADQUIRENTE DA OPERAÇÃO', $html);
        self::assertStringNotContainsString('class="bordered-section party-destinatario"', $html);
    }

    public function testOfficialIndDestZeroMeansSameTakerEvenWithoutDestGroup(): void
    {
        $nfse = $this->fixtureNfseData();
        $dps = & $nfse['infNFSe']['DPS']['infDPS'];
        $dps['IBSCBS']['indDest'] = '0';
        unset($dps['IBSCBS']['dest']);

        $template = new DanfseTemplate();
        $data = $template->buildData($nfse);
        self::assertNull($data['destinatario']);
        self::assertTrue($data['destinatario_mesmo_tomador']);

        $html = $template->render($nfse, new DanfseConfig());
        self::assertStringContainsString('O DESTINATÁRIO É O PRÓPRIO TOMADOR/ADQUIRENTE DA OPERAÇÃO', $html);
        self::assertStringNotContainsString('DESTINATÁRIO DA OPERAÇÃO NÃO IDENTIFICADO', $html);
    }

    public function testOfficialIndDestOnePreventsIncorrectSameTakerSuppression(): void
    {
        $nfse = $this->fixtureNfseData();
        $dps = & $nfse['infNFSe']['DPS']['infDPS'];
        $dps['IBSCBS']['indDest'] = '1';
        $dps['IBSCBS']['dest'] = [
            'CNPJ' => $dps['toma']['CNPJ'],
            'xNome' => 'DESTINATARIO AUTORIZADO DISTINTO',
        ];

        $template = new DanfseTemplate();
        self::assertFalse($template->buildData($nfse)['destinatario_mesmo_tomador']);
        $html = $template->render($nfse, new DanfseConfig());
        self::assertStringContainsString('class="bordered-section party-destinatario"', $html);
        self::assertStringNotContainsString('O DESTINATÁRIO É O PRÓPRIO TOMADOR', $html);
    }

    public function testMissingTakerAndRecipientHaveSeparateCondensedBlocks(): void
    {
        $nfse = $this->fixtureNfseData();
        unset($nfse['infNFSe']['DPS']['infDPS']['toma']);
        $template = new DanfseTemplate();
        self::assertNull($template->buildData($nfse)['tomador']);
        self::assertNull($template->buildData($nfse)['destinatario']);
        $html = $template->render($nfse, new DanfseConfig());
        self::assertStringContainsString('TOMADOR/ADQUIRENTE DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e', $html);
        self::assertStringContainsString('DESTINATÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e', $html);
        self::assertStringContainsString('class="bordered-section party-intermediario"', $html);
    }

    public function testProviderPrefersAuthorizedDpsAddressAndPreservesLeadingZeroCep(): void
    {
        $nfse = $this->fixtureNfseData();
        $provider = & $nfse['infNFSe']['DPS']['infDPS']['prest'];
        $provider['xNome'] = 'FORNECEDOR SINTETICO DA DPS';
        $provider['end'] = [
            'endNac' => ['cMun' => '3550308', 'CEP' => '01001000'],
            'xLgr' => 'Rua da Amostra',
            'nro' => '10',
            'xBairro' => 'Centro',
        ];

        $data = (new DanfseTemplate())->buildData($nfse);
        self::assertSame('FORNECEDOR SINTETICO DA DPS', $data['emitente']['nome']);
        self::assertSame('São Paulo - SP', $data['emitente']['municipio']);
        self::assertSame('3550308 / 01001-000', $data['emitente']['ibge_cep']);
        self::assertStringContainsString('Rua da Amostra', $data['emitente']['endereco']);
    }

    public function testIntermediaryKeepsIbgeAndCepInSameCell(): void
    {
        $nfse = $this->fixtureNfseData();
        $template = new DanfseTemplate();
        $data = $template->buildData($nfse);
        self::assertSame('3518800 / 07095-130', $data['intermediario']['ibge_cep']);
        $html = $template->render($nfse, new DanfseConfig());
        self::assertStringContainsString('class="bordered-section party-intermediario"', $html);
        self::assertStringContainsString('3518800 / 07095-130', $html);
    }


    public function testMunicipalRegimeZeroIsPresentButMissingCodeIsNot(): void
    {
        $nfse = $this->fixtureNfseData();
        $regTrib = & $nfse['infNFSe']['DPS']['infDPS']['prest']['regTrib'];
        $regTrib['regEspTrib'] = '0';
        $template = new DanfseTemplate();
        $data = $template->buildData($nfse);

        self::assertSame('Nenhum', $data['tributacao_municipal']['regime_especial']);
        self::assertTrue($data['tributacao_municipal']['mostrar_linha_regime']);
        self::assertStringContainsString('class="municipal-conditional-regime"', $template->render($nfse, new DanfseConfig()));

        unset($regTrib['regEspTrib']);
        $data = $template->buildData($nfse);
        self::assertSame('-', $data['tributacao_municipal']['regime_especial']);
        self::assertFalse($data['tributacao_municipal']['mostrar_linha_regime']);
        self::assertStringNotContainsString('class="municipal-conditional-regime"', $template->render($nfse, new DanfseConfig()));
    }

    public function testMunicipalRegimeOtherIsRenderedWithoutChangingFiscalEligibility(): void
    {
        $nfse = $this->fixtureNfseData();
        $nfse['infNFSe']['DPS']['infDPS']['prest']['regTrib']['regEspTrib'] = '9';
        $data = (new DanfseTemplate())->buildData($nfse);

        self::assertSame('Outros', $data['tributacao_municipal']['regime_especial']);
        self::assertTrue($data['tributacao_municipal']['mostrar_linha_regime']);
        self::assertStringContainsString('>Outros</span>', (new DanfseTemplate())->render($nfse, new DanfseConfig()));
    }

    public function testMunicipalConditionalRegimeRowRemainsWhenSiblingHasData(): void
    {
        $nfse = $this->fixtureNfseData();
        $trib = & $nfse['infNFSe']['DPS']['infDPS']['valores']['trib']['tribMun'];
        unset($nfse['infNFSe']['DPS']['infDPS']['prest']['regTrib']['regEspTrib']);
        $trib['tpImunidade'] = '4';
        $trib['exigSusp'] = ['tpSusp' => '1', 'nProcesso' => 'PROCESSO-SINTETICO-123'];

        $template = new DanfseTemplate();
        $data = $template->buildData($nfse);
        self::assertTrue($data['tributacao_municipal']['mostrar_linha_regime']);
        self::assertSame('PROCESSO-SINTETICO-123', $data['tributacao_municipal']['numero_processo']);
        $html = $template->render($nfse, new DanfseConfig());
        self::assertStringContainsString('Tipo de Imunidade do ISSQN', $html);
        self::assertStringContainsString('PROCESSO-SINTETICO-123', $html);
        self::assertSame(1, substr_count($html, 'class="municipal-conditional-regime"'));
    }

    public function testMunicipalImmunityCodeZeroIsNotDisplayedAsAbsent(): void
    {
        $nfse = $this->fixtureNfseData();
        unset($nfse['infNFSe']['DPS']['infDPS']['prest']['regTrib']['regEspTrib']);
        $nfse['infNFSe']['DPS']['infDPS']['valores']['trib']['tribMun']['tpImunidade'] = '0';

        $template = new DanfseTemplate();
        $data = $template->buildData($nfse);
        self::assertSame('Imunidade (tipo não informado)', $data['tributacao_municipal']['tipo_imunidade']);
        self::assertTrue($data['tributacao_municipal']['mostrar_linha_regime']);

        $html = $template->render($nfse, new DanfseConfig());
        self::assertStringContainsString('Imunidade (tipo não informado)', $html);
        self::assertStringContainsString('class="municipal-conditional-regime"', $html);
    }

    public function testMunicipalDeductionsUseOfficialAuthorizedSourcesAndPreserveExplicitZero(): void
    {
        $nfse = $this->fixtureNfseData();
        $values = & $nfse['infNFSe']['valores'];
        $dpsValues = & $nfse['infNFSe']['DPS']['infDPS']['valores'];

        unset($dpsValues['vDedRed'], $values['vCalcDR'], $nfse['infNFSe']['IBSCBS']);
        $template = new DanfseTemplate();
        self::assertSame('-', $template->buildData($nfse)['tributacao_municipal']['total_deducoes']);

        $values['vCalcDR'] = '10.50';
        $nfse['infNFSe']['IBSCBS']['valores']['vCalcReeRepRes'] = '3.25';
        $data = $template->buildData($nfse);
        self::assertSame('R$ 13,75', $data['tributacao_municipal']['total_deducoes']);
        self::assertTrue($data['tributacao_municipal']['mostrar_linha_beneficio']);

        $dpsValues['vDedRed']['vDR'] = '7.00';
        self::assertSame('R$ 7,00', $template->buildData($nfse)['tributacao_municipal']['total_deducoes']);

        $dpsValues['vDedRed']['vDR'] = '0.00';
        self::assertSame('R$ 0,00', $template->buildData($nfse)['tributacao_municipal']['total_deducoes']);
    }

    public function testMunicipalBenefitZeroIsNotSuppressedAndAbsenceIsDifferent(): void
    {
        $nfse = $this->fixtureNfseData();
        $template = new DanfseTemplate();
        self::assertFalse($template->buildData($nfse)['tributacao_municipal']['mostrar_linha_beneficio']);

        $nfse['infNFSe']['valores']['vCalcBM'] = '0.00';
        $data = $template->buildData($nfse);
        self::assertTrue($data['tributacao_municipal']['mostrar_linha_beneficio']);
        self::assertSame('R$ 0,00', $data['tributacao_municipal']['calculo_bm']);
        self::assertStringContainsString('class="municipal-conditional-benefit"', $template->render($nfse, new DanfseConfig()));

        unset($nfse['infNFSe']['valores']['vCalcBM']);
        $nfse['infNFSe']['valores']['tpBM'] = '1';
        self::assertSame('Isenção', $template->buildData($nfse)['tributacao_municipal']['beneficio_municipal']);
    }

    public function testExplicitNonIncidenceUsesConciseMunicipalMessage(): void
    {
        $nfse = $this->fixtureNfseData();
        $nfse['infNFSe']['DPS']['infDPS']['valores']['trib']['tribMun']['tribISSQN'] = '4';

        $html = (new DanfseTemplate())->render($nfse, new DanfseConfig());
        self::assertStringContainsString('TRIBUTAÇÃO MUNICIPAL (ISSQN) - OPERAÇÃO NÃO SUJEITA AO ISSQN', $html);
        self::assertStringNotContainsString('class="municipal-conditional-regime"', $html);
        self::assertStringContainsString('TRIBUTAÇÃO FEDERAL (EXCETO CBS)', $html);
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


    public function testHeaderMunicipalityFollowsAuthorizedXmlAndItem99Rule(): void
    {
        $nfse = $this->fixtureNfseData();
        $nfse['infNFSe']['xLocEmi'] = 'Município de Teste';
        $nfse['infNFSe']['emit']['enderNac']['UF'] = 'MG';

        $template = new DanfseTemplate();
        $data = $template->buildData($nfse);
        self::assertSame('Município de Teste / MG', $data['municipio_emissao']);

        $html = $template->render($nfse, new DanfseConfig());
        self::assertStringContainsString('Município: Município de Teste / MG', $html);
        self::assertStringContainsString('.municipality-name { font-size: 8pt;', $html);
        self::assertStringContainsString('.municipality-environment { font-size: 6pt;', $html);

        $nfse['infNFSe']['DPS']['infDPS']['serv']['cServ']['cTribNac'] = '990000';
        self::assertSame('', $template->buildData($nfse)['municipio_emissao']);
        $html = $template->render($nfse, new DanfseConfig());
        self::assertStringNotContainsString('Município: Município de Teste', $html);
        self::assertStringContainsString('Ambiente Gerador:', $html);
        self::assertStringContainsString('Tipo de Ambiente:', $html);
    }

    public function testHeaderDoesNotInventAbsentMunicipalityOrUf(): void
    {
        $nfse = $this->fixtureNfseData();
        unset($nfse['infNFSe']['xLocEmi'], $nfse['infNFSe']['emit']['enderNac']['UF']);
        $template = new DanfseTemplate();
        self::assertSame('', $template->buildData($nfse)['municipio_emissao']);
        self::assertStringNotContainsString('Município:', $template->render($nfse, new DanfseConfig()));

        $nfse['infNFSe']['xLocEmi'] = 'Cidade Sintética';
        self::assertSame('Cidade Sintética / -', $template->buildData($nfse)['municipio_emissao']);

        unset($nfse['infNFSe']['xLocEmi']);
        $nfse['infNFSe']['emit']['enderNac']['UF'] = 'SP';
        self::assertSame('- / SP', $template->buildData($nfse)['municipio_emissao']);
    }

    public function testHomologationHeaderRetainsWarningAndEnvironmentWhenMunicipalitySuppressed(): void
    {
        $nfse = $this->fixtureNfseData();
        $nfse['infNFSe']['DPS']['infDPS']['tpAmb'] = '2';
        $nfse['infNFSe']['DPS']['infDPS']['serv']['cServ']['cTribNac'] = '990000';

        $html = (new DanfseTemplate())->render($nfse, new DanfseConfig());
        self::assertStringContainsString('NFS-e SEM VALIDADE JURÍDICA', $html);
        self::assertStringContainsString('Tipo de Ambiente:', $html);
        self::assertStringNotContainsString('Município: Niterói / RJ', $html);
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
