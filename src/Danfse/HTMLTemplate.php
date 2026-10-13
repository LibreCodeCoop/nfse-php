<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

/** @var array<string, mixed> $data */
/** @var ?string $logo */
/** @var string $qrCode */
/** @var bool $isHomologacao */
/** @var ?\LibreCodeCoop\NfsePHP\Danfse\Config\DanfseFiscalState $fiscalState */
/** @var string $fontFaces */
/** @var string $fontOverrides */
/** @var ?\LibreCodeCoop\NfsePHP\Danfse\Config\MunicipalityBranding $municipality */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>DANFSe - <?= $data['numero_nfse'] ?></title>
    <style>
        <?= $fontFaces ?>
        /* NT008 2.2: 0.15–0.20 cm margins, 1pt perimeter, 0.5pt sections. */
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, "Liberation Sans", Helvetica, sans-serif;
            font-size: 7pt;
            line-height: 1.14;
            color: #000;
            margin: 5pt;
            padding: 1pt 1pt 65pt;
        }
        /* Dompdf fixes positioned elements to the physical page. Inset both
           the perimeter and content to keep the required printable margins. */
        .page-border {
            position: fixed;
            top: 5pt;
            left: 5pt;
            right: 5pt;
            bottom: 5pt;
            border: 1pt solid #000;
            z-index: -2;
        }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 0; }
        tr { page-break-inside: avoid; }
        td { padding: 1.4pt 3pt 1.6pt; vertical-align: top; word-wrap: break-word; }
        .bordered-section { border-bottom: 0.5pt solid #000; margin: 0; }
        .first-section td { padding-bottom: 1pt; }
        /* NT008 2.4.2: identification labels require 7pt. */
        .first-section .label { font-size: 7pt; text-transform: uppercase; }
        .label { display: block; font-family: Arial, "Liberation Sans", Helvetica, sans-serif; font-size: 6pt; font-weight: bold; margin-bottom: 1pt; }
        .value { font-family: "Microsoft Sans Serif", "Liberation Sans", Arial, sans-serif; font-size: 7pt; line-height: 1.18; font-weight: normal; }
        .section-header {
            width: 25%;
            font-size: 7.1pt;
            font-weight: bold;
            text-align: left;
            vertical-align: middle;
            background: #f2f2f2;
            padding: 2pt 3pt;
        }
        .section-title { font-size: 7.1pt; font-weight: bold; }
        .header-table { background: #f2f2f2; border-bottom: 0.5pt solid #000; margin-bottom: 2pt; }
        .header-table td { padding: 2pt 3pt 3pt; vertical-align: middle; }
        .logo-cell { width: 32%; text-align: left; }
        .title-cell { width: 42%; text-align: center; }
        .municipality-cell { width: 26%; font-family: "Microsoft Sans Serif", Arial, sans-serif; text-align: left; }
        .municipality-name { font-size: 8pt; font-weight: normal; }
        .municipality-environment { font-size: 6pt; font-weight: normal; }
        .qr-container { text-align: center; padding-top: 3.7pt; }
        .qr-container img { position: relative; left: 2.4pt; }
        .qr-notice { font-size: 6pt; line-height: 1.15; margin-top: 2pt; text-align: left; }
        .access-key { white-space: nowrap; font-size: 7pt; }
        .no-party { text-align: center; font-size: 6pt; padding: 2pt; border-bottom: 0.5pt solid #000; }
        .no-party div { border-top: 0.5pt solid #222; margin-top: 2pt; padding-top: 2pt; }
        .value-highlight { font-weight: bold; }
        .total-emphasis { background: #f2f2f2; }
        .emitter-cell { background: #f2f2f2; }
        .footer-container { position: fixed; bottom: 7pt; left: 6pt; right: 6pt; }
        .footer-table { width: 100%; background: #fff; }
        .footer-table td { border: 0.5pt solid #000; padding: 2pt 3pt; }
        .footer-table .label { font-size: 6pt; }
        .footer-table .value { font-size: 7pt; }
        .fiscal-state-watermark {
            position: fixed;
            top: 46%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 50pt;
            font-weight: normal;
            color: #a6a6a6;
            z-index: -1;
            white-space: nowrap;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 45pt;
            font-weight: bold;
            color: #a6a6a6;
            z-index: -1;
            white-space: nowrap;
        }
        <?= $fontOverrides ?>
    </style>
</head>
<body>
    <?php if ($fiscalState !== null): ?>
        <div class="fiscal-state-watermark"><?= htmlspecialchars($fiscalState->value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <?php endif; ?>
    <div class="page-border"></div>
    <?php if ($isHomologacao): ?>
    <div class="watermark">HOMOLOGAÇÃO</div>
    <?php endif; ?>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                <?php if ($logo): ?>
                <img src="<?= htmlspecialchars($logo) ?>" alt="Logo NFS-e" style="max-width: 115pt; max-height: 30pt;">
                <?php endif; ?>
            </td>
            <td class="title-cell">
                <div style="font-size: 9pt; font-weight: bold;">DANFSe v2.0</div>
                <div style="font-size: 9pt; font-weight: bold;">Documento Auxiliar da NFS-e</div>
                <?php if ($isHomologacao): ?>
                    <div style="color: red; font-size: 9pt; font-weight: bold;">NFS-e SEM VALIDADE JURÍDICA</div>
                <?php endif; ?>
            </td>
            <td class="municipality-cell">
                <?php if ($municipality && $municipality->logoDataUri): ?>
                    <img style="height: 22pt; width: auto" src="<?= htmlspecialchars($municipality->logoDataUri) ?>" alt="Prefeitura" /><br>
                <?php endif; ?>
                <?php if ($data['municipio_emissao'] !== ''): ?>
                <div class="municipality-name">Município: <?= $data['municipio_emissao'] ?></div>
                <?php endif; ?>
                <div class="municipality-environment">Ambiente Gerador: <?= $data['ambiente_gerador'] ?></div>
                <div class="municipality-environment">Tipo de Ambiente: <?= $data['ambiente'] ?></div>
            </td>
        </tr>
    </table>

    <!-- Grade de Identificação -->
    <div class="bordered-section first-section">
        <table>
            <tr>
                <td colspan="3">
                    <span class="label">CHAVE DE ACESSO DA NFS-e</span>
                    <span class="value access-key"><?= $data['chave_acesso'] ?></span>
                </td>
                <td style="width: 25%;" rowspan="4">
                    <div class="qr-container">
                        <img src="<?= htmlspecialchars($qrCode) ?>" alt="QR Code" style="width: 62px; height: 62px; display: block; margin: 0 auto;" />
                        <div class="qr-notice">
                            A autenticidade desta NFS-e pode ser verificada pela leitura deste código QR ou pela consulta da chave de acesso no portal nacional da NFS-e
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td style="width: 25%;">
                    <span class="label">NÚMERO DA NFS-e</span>
                    <span class="value"><?= $data['numero_nfse'] ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">COMPETÊNCIA DA NFS-e</span>
                    <span class="value"><?= $data['competencia'] ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">DATA E HORA DA EMISSÃO DA NFS-e</span>
                    <span class="value"><?= $data['emissao_nfse'] ?></span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">NÚMERO DA DPS</span>
                    <span class="value"><?= $data['numero_dps'] ?></span>
                </td>
                <td>
                    <span class="label">SÉRIE DA DPS</span>
                    <span class="value"><?= $data['serie_dps'] ?></span>
                </td>
                <td>
                    <span class="label">DATA E HORA DA EMISSÃO DA DPS</span>
                    <span class="value"><?= $data['emissao_dps'] ?></span>
                </td>
            </tr>
            <tr>
                <td class="emitter-cell">
                    <span class="label">EMITENTE DA NFS-e</span>
                    <span class="value"><?= $data['emitente_nfse'] ?></span>
                </td>
                <td>
                    <span class="label">SITUAÇÃO DA NFS-e</span>
                    <span class="value"><?= $data['situacao_nfse'] ?></span>
                </td>
                <td>
                    <span class="label">FINALIDADE</span>
                    <span class="value"><?= $data['finalidade_nfse'] ?></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Emitente -->
    <div class="bordered-section party-prestador">
        <table>
            <tr>
                <td class="section-header">
                    <span class="label section-title">PRESTADOR / FORNECEDOR</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">CNPJ / CPF / NIF</span>
                    <span class="value"><?= $data['emitente']['cnpj_cpf'] ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Inscrição Municipal</span>
                    <span class="value"><?= $data['emitente']['im'] ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Telefone</span>
                    <span class="value"><?= $data['emitente']['telefone'] ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">Nome / Nome Empresarial</span><span class="value"><?= $data['emitente']['nome'] ?></span></td>
                <td><span class="label">Município / Sigla UF</span><span class="value"><?= $data['emitente']['municipio'] ?></span></td>
                <td><span class="label">Código IBGE / CEP</span><span class="value"><?= $data['emitente']['ibge_cep'] ?></span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">Endereço</span><span class="value"><?= $data['emitente']['endereco'] ?></span></td>
                <td colspan="2"><span class="label">E-mail</span><span class="value"><?= $data['emitente']['email'] ?></span></td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">Simples Nacional na Data de Competência</span>
                    <span class="value"><?= $data['emitente']['simples_nacional'] ?></span>
                </td>
                <td colspan="2">
                    <span class="label">Regime de Apuração Tributária pelo SN</span>
                    <span class="value"><?= $data['emitente']['regime_sn'] ?></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Tomador -->
    <?php if ($data['tomador'] === null): ?>
    <div class="no-party">TOMADOR/ADQUIRENTE DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e</div>
    <?php else: ?>
    <div class="bordered-section party-tomador">
        <table>
            <tr>
                <td class="section-header">
                    <span class="section-title">TOMADOR / ADQUIRENTE</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">CNPJ / CPF / NIF</span>
                    <span class="value"><?= $data['tomador']['cnpj_cpf'] ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Inscrição Municipal</span>
                    <span class="value"><?= $data['tomador']['im'] ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Telefone</span>
                    <span class="value"><?= $data['tomador']['telefone'] ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="width: 50%;">
                    <span class="label">Nome / Nome Empresarial</span>
                    <span class="value"><?= $data['tomador']['nome'] ?></span>
                </td>
                <td><span class="label">Município / Sigla UF</span><span class="value"><?= $data['tomador']['municipio'] ?></span></td>
                <td><span class="label">Código IBGE / CEP</span><span class="value"><?= $data['tomador']['ibge_cep'] ?></span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">Endereço</span><span class="value"><?= $data['tomador']['endereco'] ?></span></td>
                <td colspan="2"><span class="label">E-mail</span><span class="value"><?= $data['tomador']['email'] ?></span></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <!-- Destinatário: independent of the intermediary, as required by NT008 -->
    <?php if ($data['destinatario_mesmo_tomador']): ?>
    <div class="no-party">O DESTINATÁRIO É O PRÓPRIO TOMADOR/ADQUIRENTE DA OPERAÇÃO</div>
    <?php elseif ($data['destinatario'] === null): ?>
    <div class="no-party">DESTINATÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e</div>
    <?php else: ?>
    <div class="bordered-section party-destinatario">
        <table>
            <tr>
                <td class="section-header"><span class="section-title">DESTINATÁRIO DA OPERAÇÃO</span></td>
                <td><span class="label">CNPJ / CPF / NIF</span><span class="value"><?= $data['destinatario']['cnpj_cpf'] ?></span></td>
                <td></td>
                <td><span class="label">Telefone</span><span class="value"><?= $data['destinatario']['telefone'] ?></span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">Nome / Nome Empresarial</span><span class="value"><?= $data['destinatario']['nome'] ?></span></td>
                <td><span class="label">Município / Sigla UF</span><span class="value"><?= $data['destinatario']['municipio'] ?></span></td>
                <td><span class="label">Código IBGE / CEP</span><span class="value"><?= $data['destinatario']['ibge_cep'] ?></span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">Endereço</span><span class="value"><?= $data['destinatario']['endereco'] ?></span></td>
                <td colspan="2"><span class="label">E-mail</span><span class="value"><?= $data['destinatario']['email'] ?></span></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <!-- Intermediário -->
    <?php if ($data['intermediario'] !== null): ?>
    <div class="bordered-section party-intermediario">
        <table>
            <tr>
                <td class="section-header">
                  <span class="section-title">INTERMEDIÁRIO DO SERVIÇO</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">CNPJ / CPF / NIF</span>
                    <span class="value"><?= $data['intermediario']['cnpj_cpf'] ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Inscrição Municipal</span>
                    <span class="value"><?= $data['intermediario']['im'] ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Telefone</span>
                    <span class="value"><?= $data['intermediario']['telefone'] ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="width: 50%;">
                    <span class="label">Nome / Nome Empresarial</span>
                    <span class="value"><?= $data['intermediario']['nome'] ?></span>
                </td>
                <td><span class="label">Município / Sigla UF</span><span class="value"><?= $data['intermediario']['municipio'] ?></span></td>
                <td><span class="label">Código IBGE / CEP</span><span class="value"><?= $data['intermediario']['ibge_cep'] ?></span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">Endereço</span><span class="value"><?= $data['intermediario']['endereco'] ?></span></td>
                <td colspan="2"><span class="label">E-mail</span><span class="value"><?= $data['intermediario']['email'] ?></span></td>
            </tr>
        </table>
    </div>
    <?php else: ?>
    <div class="no-party">INTERMEDIÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e</div>
    <?php endif; ?>

    <!-- Serviço Prestado -->
    <div class="bordered-section">
        <table>
            <tr>
                <td class="section-header"><span class="section-title">SERVIÇO PRESTADO</span></td>
                <td><span class="label">Código de Tributação Nacional/Municipal</span><span class="value"><?= $data['servico']['codigo_trib_nacional'] ?> / <?= $data['servico']['codigo_trib_municipal'] ?></span></td>
                <td><span class="label">Código NBS</span><span class="value"><?= $data['servico']['codigo_nbs'] ?></span></td>
                <td><span class="label">Local da Prestação / Sigla UF / País</span><span class="value"><?= $data['servico']['local_prestacao'] ?></span></td>
            </tr>
            <tr><td colspan="4"><span class="value"><?= $data['servico']['desc_trib_nacional'] ?></span></td></tr>
            <tr>
                <td colspan="4"><span class="label">Descrição do Serviço</span><span class="value"><?= nl2br($data['servico']['descricao'], false) ?></span></td>
            </tr>
        </table>
    </div>

    <!-- Tributação Municipal: NT 008 note 4 and optional rows from note 5 -->
    <div class="bordered-section">
        <?php if ($data['tributacao_municipal']['nao_sujeito_issqn']): ?>
        <div class="no-party">TRIBUTAÇÃO MUNICIPAL (ISSQN) - OPERAÇÃO NÃO SUJEITA AO ISSQN</div>
        <?php else: ?>
        <table>
            <tr>
                <td class="section-header"><span class="section-title">TRIBUTAÇÃO MUNICIPAL (ISSQN)</span></td>
                <td><span class="label">Tipo de Tributação do ISSQN</span><span class="value"><?= $data['tributacao_municipal']['tributacao_issqn'] ?></span></td>
                <td colspan="2"><span class="label">Município / Sigla UF / País de Incidência do ISSQN</span><span class="value"><?= $data['tributacao_municipal']['municipio_incidencia'] ?></span></td>
            </tr>
            <?php if ($data['tributacao_municipal']['mostrar_linha_regime']): ?>
            <tr class="municipal-conditional-regime">
                <td><span class="label">Regime Especial de Tributação do ISSQN</span><span class="value"><?= $data['tributacao_municipal']['regime_especial'] ?></span></td>
                <td><span class="label">Tipo de Imunidade do ISSQN</span><span class="value"><?= $data['tributacao_municipal']['tipo_imunidade'] ?></span></td>
                <td><span class="label">Suspensão da Exigibilidade do ISSQN</span><span class="value"><?= $data['tributacao_municipal']['suspensao_issqn'] ?></span></td>
                <td><span class="label">Número Processo Suspensão</span><span class="value"><?= $data['tributacao_municipal']['numero_processo'] ?></span></td>
            </tr>
            <?php endif; ?>
            <?php if ($data['tributacao_municipal']['mostrar_linha_beneficio']): ?>
            <tr class="municipal-conditional-benefit">
                <td><span class="label">Benefício Municipal</span><span class="value"><?= $data['tributacao_municipal']['beneficio_municipal'] ?></span></td>
                <td><span class="label">Cálculo do BM</span><span class="value"><?= $data['tributacao_municipal']['calculo_bm'] ?></span></td>
                <td><span class="label">Total Deduções/Reduções</span><span class="value"><?= $data['tributacao_municipal']['total_deducoes'] ?></span></td>
                <td><span class="label">Desconto Incondicionado</span><span class="value"><?= $data['tributacao_municipal']['desconto_incondicionado'] ?></span></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td><span class="label">BC ISSQN</span><span class="value"><?= $data['tributacao_municipal']['bc_issqn'] ?></span></td>
                <td><span class="label">Alíquota Aplicada</span><span class="value"><?= $data['tributacao_municipal']['aliquota'] ?></span></td>
                <td><span class="label">Retenção do ISSQN</span><span class="value"><?= $data['tributacao_municipal']['retencao_issqn'] ?></span></td>
                <td><span class="label">ISSQN Apurado</span><span class="value"><?= $data['tributacao_municipal']['issqn_apurado'] ?></span></td>
            </tr>
        </table>
        <?php endif; ?>
    </div>

    <!-- Tributação Federal -->
    <div class="bordered-section">
        <table>
            <tr>
                <td class="section-header"><span class="section-title">TRIBUTAÇÃO FEDERAL (EXCETO CBS)</span></td>
                <td><span class="label">IRRF</span><span class="value"><?= $data['tributacao_federal']['irrf'] ?></span></td>
                <td><span class="label">Contribuição Previdenciária - Retida</span><span class="value"><?= $data['tributacao_federal']['cp'] ?></span></td>
                <td><span class="label">Contribuições Sociais - Retidas</span><span class="value"><?= $data['tributacao_federal']['contribuicoes_sociais'] ?></span></td>
            </tr>
            <?php if ($data['tributacao_federal']['mostrar_linha_2026']): ?>
            <tr class="federal-competence-2026">
                <td><span class="label">PIS - Débito Apuração Própria</span><span class="value"><?= $data['tributacao_federal']['pis'] ?></span></td>
                <td><span class="label">COFINS - Débito Apuração Própria</span><span class="value"><?= $data['tributacao_federal']['cofins'] ?></span></td>
                <td colspan="2"><span class="label">Tipo de retenção PIS/COFINS/CSLL</span><span class="value"><?= $data['tributacao_federal']['descricao_retencao'] ?></span></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Tributação IBS/CBS: Anexo I grid and official authorized XML fields -->
    <div class="bordered-section">
        <table>
            <tr>
                <td class="section-header"><span class="section-title">TRIBUTAÇÃO IBS / CBS</span></td>
                <td><span class="label">CST / cClassTrib</span><span class="value"><?= $data['ibs_cbs']['cst_classificacao'] ?></span></td>
                <td colspan="2"><span class="label">Indicador de Operação / Código IBGE / Município Incidência / Sigla UF</span><span class="value"><?= $data['ibs_cbs']['indicador_operacao'] ?></span></td>
            </tr>
            <tr>
                <td><span class="label">Exclusões e Reduções da Base de Cálculo</span><span class="value"><?= $data['ibs_cbs']['exclusoes_reducoes'] ?></span></td>
                <td><span class="label">Base de Cálculo Após Exclusões e Reduções</span><span class="value"><?= $data['ibs_cbs']['base_calculo'] ?></span></td>
                <td><span class="label">Red. Alíquota IBS / Red. Alíquota CBS</span><span class="value"><?= $data['ibs_cbs']['reducao_aliquotas'] ?></span></td>
                <td><span class="label">Alíquota - IBS UF / IBS Mun</span><span class="value"><?= $data['ibs_cbs']['aliquota_ibs'] ?></span></td>
            </tr>
            <tr>
                <td><span class="label">Alíq. Efetiva Municipal - IBS</span><span class="value"><?= $data['ibs_cbs']['aliquota_efetiva_municipal'] ?></span></td>
                <td><span class="label">Valor Apurado Municipal - IBS</span><span class="value"><?= $data['ibs_cbs']['valor_ibs_municipal'] ?></span></td>
                <td><span class="label">Alíq. Efetiva Estadual - IBS</span><span class="value"><?= $data['ibs_cbs']['aliquota_efetiva_estadual'] ?></span></td>
                <td><span class="label">Valor Apurado Estadual - IBS</span><span class="value"><?= $data['ibs_cbs']['valor_ibs_estadual'] ?></span></td>
            </tr>
            <tr>
                <td><span class="label">Valor Total Apurado - IBS</span><span class="value"><?= $data['ibs_cbs']['total_ibs'] ?></span></td>
                <td><span class="label">Alíquota - CBS</span><span class="value"><?= $data['ibs_cbs']['aliquota_cbs'] ?></span></td>
                <td><span class="label">Alíquota Efetiva - CBS</span><span class="value"><?= $data['ibs_cbs']['aliquota_efetiva_cbs'] ?></span></td>
                <td><span class="label">Valor Total Apurado - CBS</span><span class="value"><?= $data['ibs_cbs']['total_cbs'] ?></span></td>
            </tr>
        </table>
    </div>

    <!-- Valor Total -->
    <div class="bordered-section">
        <table>
            <tr>
                <td class="section-header"><span class="section-title">VALOR TOTAL DA NFS-e</span></td>
                <td><span class="label">VALOR DA OPERAÇÃO / SERVIÇO</span><span class="value"><?= $data['totais']['valor_servico'] ?></span></td>
                <td><span class="label">Desconto Incondicionado</span><span class="value"><?= $data['totais']['desconto_incondicionado'] ?></span></td>
                <td><span class="label">Desconto Condicionado</span><span class="value"><?= $data['totais']['desconto_condicionado'] ?></span></td>
            </tr>
            <tr>
                <td><span class="label">Total das Retenções (ISSQN / Federais)</span><span class="value"><?= $data['totais']['total_retencoes'] ?></span></td>
                <td><span class="label">VALOR LÍQUIDO DA NFS-e</span><span class="value"><?= $data['totais']['valor_liquido'] ?></span></td>
                <td><span class="label">Total do IBS/CBS</span><span class="value"><?= $data['ibs_cbs']['total_ibs_cbs'] ?></span></td>
                <td class="total-emphasis"><span class="label">VALOR LÍQUIDO DA NFS-e + IBS/CBS</span><span class="value value-highlight"><?= $data['ibs_cbs']['valor_total_nfse'] ?></span></td>
            </tr>
        </table>
    </div>

    <!-- Informações Complementares -->
    <div class="bordered-section">
        <table>
            <tr>
                <td class="section-header">
                  <span class="section-title">INFORMAÇÕES COMPLEMENTARES</span>
                </td>
            </tr>
            <tr>
                <td style="min-height: 30pt; padding: 4pt;">
                    <span class="value"><?= nl2br($data['informacoes_complementares'], false) ?></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- The XML has no acknowledgement timestamp or handwritten signature data. -->
    <div class="footer-container">
        <table class="footer-table">
            <tr>
                <td style="width: 25%;"><span class="label">DATA CIENTIFICAÇÃO</span><span class="value">-</span></td>
                <td style="width: 35%;"><span class="label">IDENTIFICAÇÃO E ASSINATURA</span><span class="value">-</span></td>
                <td style="width: 40%;"><span class="label">Nº NFS-e / CHAVE NFS-e</span><span class="value"><?= $data['numero_nfse'] ?> / <?= $data['chave_acesso'] ?></span></td>
            </tr>
        </table>
    </div>
</body>
</html>
