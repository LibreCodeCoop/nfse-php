<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

/** @var array<string, mixed> $data */
/** @var ?string $logo */
/** @var string $qrCode */
/** @var bool $isHomologacao */
/** @var ?\LibreCodeCoop\NfsePHP\Danfse\Config\MunicipalityBranding $municipality */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>DANFSe - <?= $data['numero_nfse'] ?></title>
    <style>
        @page { margin: 7pt 7pt 33pt; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 6.5pt;
            line-height: 1.14;
            color: #111;
            margin: 0;
            padding: 3pt 4pt;
            border: 0.7pt solid #111;
        }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 0; }
        tr { page-break-inside: avoid; }
        td { padding: 1.4pt 3pt 1.6pt; vertical-align: top; word-wrap: break-word; }
        .bordered-section { border-bottom: 0.7pt solid #222; margin: 0; }
        .first-section td { padding-bottom: 1pt; }
        .label { display: block; font-size: 6.4pt; font-weight: bold; margin-bottom: 1pt; }
        .value { font-size: 6.85pt; line-height: 1.18; font-weight: normal; }
        .section-header {
            width: 25%;
            font-size: 7.1pt;
            font-weight: bold;
            text-align: left;
            vertical-align: middle;
            background: #ededed;
            padding: 2pt 3pt;
        }
        .section-title { font-size: 7.1pt; font-weight: bold; }
        .header-table { background: #f3f3f3; border-bottom: 0.7pt solid #111; margin-bottom: 2pt; }
        .header-table td { padding: 2pt 3pt 3pt; vertical-align: middle; }
        .logo-cell { width: 32%; text-align: left; }
        .title-cell { width: 42%; text-align: center; }
        .municipality-cell { width: 26%; font-size: 5.7pt; text-align: left; }
        .qr-container { text-align: center; }
        .qr-notice { font-size: 5.6pt; line-height: 1.15; margin-top: 2pt; text-align: left; }
        .access-key { white-space: nowrap; font-size: 6.6pt; }
        .no-party { text-align: center; font-size: 6.4pt; padding: 2pt; border-bottom: 0.7pt solid #222; }
        .value-highlight { font-weight: bold; background: #ededed; }
        .footer-table { position: fixed; bottom: 0; left: 0; width: 100%; background: #fff; }
        .footer-table td { border: 0.8pt solid #000; padding: 2pt 3pt; }
        .footer-table .label { font-size: 5.8pt; }
        .footer-table .value { font-size: 6pt; }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 45pt;
            font-weight: bold;
            color: rgba(180, 180, 180, 0.2);
            z-index: -1;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <?php if ($isHomologacao): ?>
    <div class="watermark">HOMOLOGAÇÃO</div>
    <?php endif; ?>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                <?php if ($logo): ?>
                <img src="<?= htmlspecialchars($logo) ?>" alt="Logo NFS-e" style="max-width: 155pt; max-height: 37pt;">
                <?php endif; ?>
            </td>
            <td class="title-cell">
                <div style="font-size: 8.7pt; font-weight: bold;">DANFSe v2.0</div>
                <div style="font-size: 8pt; font-weight: bold;">Documento Auxiliar da NFS-e</div>
                <?php if ($isHomologacao): ?>
                    <div style="color: red; font-weight: bold;">NFS-e SEM VALIDADE JURÍDICA</div>
                <?php endif; ?>
            </td>
            <td class="municipality-cell">
                <?php if ($municipality && $municipality->logoDataUri): ?>
                    <img style="height: 22pt; width: auto" src="<?= htmlspecialchars($municipality->logoDataUri) ?>" alt="Prefeitura" /><br>
                <?php endif; ?>
                Município: <?= $data['municipio_emissao'] ?><br>
                Ambiente Gerador: <?= $data['ambiente_gerador'] ?><br>
                Tipo de Ambiente: <?= $data['ambiente'] ?>
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
                <td style="width: 25%;" rowspan="3">
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
        </table>
        <table>
            <tr>
                <td style="width: 33.33%;">
                    <span class="label">EMITENTE DA NFS-e</span>
                    <span class="value"><?= $data['emitente_nfse'] ?></span>
                </td>
                <td style="width: 33.33%;">
                    <span class="label">SITUAÇÃO DA NFS-e</span>
                    <span class="value"><?= $data['situacao_nfse'] ?></span>
                </td>
                <td style="width: 33.33%;">
                    <span class="label">FINALIDADE</span>
                    <span class="value"><?= $data['finalidade_nfse'] ?></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Emitente -->
    <div class="bordered-section">
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
                <td colspan="2">
                    <span class="label">Nome / Nome Empresarial</span>
                    <span class="value"><?= $data['emitente']['nome'] ?></span>
                </td>
                <td colspan="2">
                    <span class="label">E-mail</span>
                    <span class="value"><?= $data['emitente']['email'] ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">Endereço</span>
                    <span class="value"><?= $data['emitente']['endereco'] ?></span>
                </td>
                <td>
                    <span class="label">Município</span>
                    <span class="value"><?= $data['emitente']['municipio'] ?></span>
                </td>
                <td>
                    <span class="label">Código IBGE / CEP</span>
                    <span class="value"><?= $data['emitente']['ibge_cep'] ?></span>
                </td>
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
    <div class="bordered-section">
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
                <td colspan="2" style="width: 50%;">
                    <span class="label">E-mail</span>
                    <span class="value"><?= $data['tomador']['email'] ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="width: 50%;">
                    <span class="label">Endereço</span>
                    <span class="value"><?= $data['tomador']['endereco'] ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Município</span>
                    <span class="value"><?= $data['tomador']['municipio'] ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Código IBGE / CEP</span>
                    <span class="value"><?= $data['tomador']['ibge_cep'] ?></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Intermediário -->
    <?php if ($data['intermediario'] !== null): ?>
    <div class="bordered-section">
        <table>
            <tr>
                <td class="section-header">
                  <span class="section-title">INTERMEDIÁRIO DO SERVIÇO</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">CNPJ / CPF</span>
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
                <td colspan="2" style="width: 50%;">
                    <span class="label">E-mail</span>
                    <span class="value"><?= $data['intermediario']['email'] ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="width: 50%;">
                    <span class="label">Endereço</span>
                    <span class="value"><?= $data['intermediario']['endereco'] ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Município</span>
                    <span class="value"><?= $data['intermediario']['municipio'] ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">CEP</span>
                    <span class="value"><?= $data['intermediario']['cep'] ?></span>
                </td>
            </tr>
        </table>
    </div>
    <?php else: ?>
    <div class="no-party">DESTINATÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e
        <div>INTERMEDIÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e</div>
    </div>
    <?php endif; ?>

    <!-- Serviço Prestado -->
    <div class="bordered-section">
        <table>
            <tr>
                <td class="section-header"><span class="section-title">SERVIÇO PRESTADO</span></td>
                <td><span class="label">Código de Tributação Nacional/Municipal</span><span class="value"><?= $data['servico']['codigo_trib_nacional'] ?> / <?= $data['servico']['codigo_trib_municipal'] ?></span></td>
                <td><span class="label">Código NBS</span><span class="value"><?= $data['servico']['codigo_nbs'] ?></span></td>
                <td><span class="label">Local da Prestação / País</span><span class="value"><?= $data['servico']['local_prestacao'] ?> / <?= $data['servico']['pais_prestacao'] ?></span></td>
            </tr>
            <tr><td colspan="4"><span class="value"><?= $data['servico']['desc_trib_nacional'] ?></span></td></tr>
            <tr>
                <td colspan="4"><span class="label">Descrição do Serviço</span><span class="value"><?= nl2br($data['servico']['descricao'], false) ?></span></td>
            </tr>
        </table>
    </div>

    <!-- Tributação Municipal -->
    <div class="bordered-section">
        <table>
            <tr>
                <td class="section-header"><span class="section-title">TRIBUTAÇÃO MUNICIPAL (ISSQN)</span></td>
                <td><span class="label">Tipo de Tributação do ISSQN</span><span class="value"><?= $data['tributacao_municipal']['tributacao_issqn'] ?></span></td>
                <td><span class="label">Município de Incidência do ISSQN</span><span class="value"><?= $data['tributacao_municipal']['municipio_incidencia'] ?></span></td>
                <td><span class="label">Regime Especial de Tributação</span><span class="value"><?= $data['tributacao_municipal']['regime_especial'] ?></span></td>
            </tr>
            <tr>
                <td><span class="label">BC ISSQN</span><span class="value"><?= $data['tributacao_municipal']['bc_issqn'] ?></span></td>
                <td><span class="label">Alíquota Aplicada</span><span class="value"><?= $data['tributacao_municipal']['aliquota'] ?></span></td>
                <td><span class="label">Retenção do ISSQN</span><span class="value"><?= $data['tributacao_municipal']['retencao_issqn'] ?></span></td>
                <td><span class="label">ISSQN Apurado</span><span class="value"><?= $data['tributacao_municipal']['issqn_apurado'] ?></span></td>
            </tr>
        </table>
    </div>

    <!-- Tributação Federal -->
    <div class="bordered-section">
        <table>
            <tr>
                <td class="section-header"><span class="section-title">TRIBUTAÇÃO FEDERAL (EXCETO CBS)</span></td>
                <td><span class="label">IRRF</span><span class="value"><?= $data['tributacao_federal']['irrf'] ?></span></td>
                <td><span class="label">Contribuição Previdenciária - Retida</span><span class="value"><?= $data['tributacao_federal']['cp'] ?></span></td>
                <td><span class="label">Contribuições Sociais - Retidas</span><span class="value"><?= $data['tributacao_federal']['csll'] ?></span></td>
            </tr>
            <tr>
                <td><span class="label">PIS - Débito Apuração Própria</span><span class="value"><?= $data['tributacao_federal']['pis'] ?></span></td>
                <td><span class="label">COFINS - Débito Apuração Própria</span><span class="value"><?= $data['tributacao_federal']['cofins'] ?></span></td>
                <td colspan="2"><span class="label">Tipo de retenção PIS/COFINS/CSLL</span><span class="value"><?= $data['tributacao_federal']['descricao_retencao'] ?></span></td>
            </tr>
        </table>
    </div>

    <!-- Tributação IBS / CBS (NT 008 v1.02) -->
    <div class="bordered-section">
        <table>
            <tr>
                <td colspan="4" class="section-header">
                    <span class="section-title">TRIBUTAÇÃO IBS / CBS</span>
                </td>
            </tr>
            <tr>
                <td style="width: 25%;">
                    <span class="label">CST / cClassTrib</span>
                    <span class="value"><?= $data['ibs_cbs']['cst_classificacao'] ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Indicador de Operação</span>
                    <span class="value"><?= $data['ibs_cbs']['indicador_operacao'] ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Localidade de Incidência</span>
                    <span class="value"><?= $data['ibs_cbs']['localidade_incidencia'] ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Exclusões e Reduções da Base de Cálculo</span>
                    <span class="value"><?= $data['ibs_cbs']['exclusoes_reducoes'] ?></span>
                </td>
            </tr>
            <tr>
                <td style="width: 25%;">
                    <span class="label">Base de Cálculo</span>
                    <span class="value"><?= $data['ibs_cbs']['base_calculo'] ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Alíquota IBS UF</span>
                    <span class="value"><?= $data['ibs_cbs']['aliquota_ibs_uf'] ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Alíquota IBS Município</span>
                    <span class="value"><?= $data['ibs_cbs']['aliquota_ibs_municipal'] ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Reduções de Alíquota IBS/CBS</span>
                    <span class="value">-</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Alíquota CBS</span>
                    <span class="value"><?= $data['ibs_cbs']['aliquota_cbs'] ?></span>
                </td>
                <td>
                    <span class="label">Total IBS</span>
                    <span class="value"><?= $data['ibs_cbs']['total_ibs'] ?></span>
                </td>
                <td>
                    <span class="label">Total CBS</span>
                    <span class="value"><?= $data['ibs_cbs']['total_cbs'] ?></span>
                </td>
                <td>
                    <span class="label">Valor Total da NFS-e c/ IBS/CBS</span>
                    <span class="value" style="font-weight: bold;"><?= $data['ibs_cbs']['valor_total_nfse'] ?></span>
                </td>
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
                <td><span class="label">Retenções Federais</span><span class="value"><?= $data['totais']['retencoes_federais'] ?></span></td>
                <td><span class="label">VALOR LÍQUIDO DA NFS-e</span><span class="value value-highlight"><?= $data['totais']['valor_liquido'] ?></span></td>
                <td><span class="label">ISSQN Retido / Total IBS/CBS</span><span class="value"><?= $data['totais']['issqn_retido'] ?> / <?= $data['ibs_cbs']['total_ibs_cbs'] ?></span></td>
                <td><span class="label">VALOR LÍQUIDO DA NFS-e + IBS/CBS</span><span class="value value-highlight"><?= $data['ibs_cbs']['valor_total_nfse'] ?></span></td>
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
    <table class="footer-table">
        <tr>
            <td style="width: 25%;"><span class="label">DATA CIENTIFICAÇÃO</span><span class="value">-</span></td>
            <td style="width: 35%;"><span class="label">IDENTIFICAÇÃO E ASSINATURA</span><span class="value">-</span></td>
            <td style="width: 40%;"><span class="label">Nº NFS-e / CHAVE NFS-e</span><span class="value"><?= $data['numero_nfse'] ?> / <?= $data['chave_acesso'] ?></span></td>
        </tr>
    </table>
</body>
</html>
