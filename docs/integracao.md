<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Integração com nfse-php

O pacote `librecodeoop/nfse-php` é uma biblioteca PHP independente de frameworks. Instale-o por Composer:

```bash
composer require librecodeoop/nfse-php
```

A aplicação consumidora é responsável por configurar certificados, armazenamento de segredos, ambiente fiscal e os dados da operação. Nenhuma integração deve inferir dados obrigatórios não presentes no cadastro ou em fontes verificadas.

## Interfaces principais

- `NfseClient::emit(DpsData)`: submissão de DPS pelo contrato de produção implementado.
- `NfseClient::query()` e `cancel()`: consulta e cancelamento conforme disponibilidade do serviço oficial.
- `DanfseGenerator`: renderização local de PDF a partir do XML **autorizado**.
- `Http\AdnClient`: distribuição de documentos e eventos por ADN, separada da emissão SEFIN.
- `XmlSignatureVerifier` e `CertificateTrustValidator`: verificações diferentes. Uma assinatura íntegra não demonstra, sozinha, confiança ICP-Brasil.

## Segurança e recuperação

Nunca adicione credenciais, certificados privados, senhas PFX ou XMLs de clientes aos exemplos, testes e repositórios. Prefira dados sintéticos ou esquemas oficiais públicos; mantenha credenciais fora do código-fonte.

Uma resposta HTTP 2xx sem evidência suficiente da NFS-e autorizada é **ambígua**. Antes de qualquer novo POST, reconcilie a DPS e consulte o serviço oficial. Não use tentativas de emissão para validar mudanças puramente visuais no PDF.

Para os campos aceitos pelo leiaute nacional, veja os DTOs e testes do pacote. As regras e a eventual ativação de novos leiautes são documentadas em [NT009](nt009.md), [eventos SEFIN](sefin-events.md) e [DANFSe](danfse-v2-rendering.md).

## Atualização de consumidores

Após atualizar a dependência via Composer, execute os testes da aplicação consumidora e confirme a versão de fato instalada. Quem incorpora a biblioteca por empacotamento, isolamento de namespace ou cópia de artefatos deve reconstruir esse runtime e reiniciar os processos que mantêm código em memória, de acordo com sua própria arquitetura. Um merge neste repositório não atualiza automaticamente outros sistemas.
