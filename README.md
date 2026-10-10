<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# NFS-e Nacional em PHP — nfse-php

**Biblioteca PHP livre para integrar a Nota Fiscal de Serviço Eletrônica (NFS-e) no padrão nacional**, sem exigir Laravel, Akaunting ou outro framework.

[![Packagist](https://img.shields.io/packagist/v/librecodeoop/nfse-php)](https://packagist.org/packages/librecodeoop/nfse-php)
[![PHP](https://img.shields.io/packagist/php-v/librecodeoop/nfse-php)](https://packagist.org/packages/librecodeoop/nfse-php)
[![CI](https://github.com/LibreCodeCoop/nfse-php/actions/workflows/phpunit.yml/badge.svg)](https://github.com/LibreCodeCoop/nfse-php/actions/workflows/phpunit.yml)

> **English:** Framework-agnostic PHP library for Brazil's National Electronic Service Invoice (NFS-e): DPS submission, consultation, cancellation, digital signing and DANFSe PDF generation.

## Integre a NFS-e sem reimplementar o protocolo fiscal

Integrar aplicações ao Sistema Nacional NFS-e envolve XML, assinatura digital, certificados, comunicação com a SEFIN e interpretação dos documentos autorizados. O **nfse-php** reúne essas responsabilidades em componentes PHP reutilizáveis, para que equipes concentrem esforços nas funcionalidades do seu sistema.

O projeto é útil para desenvolvedores de ERP, sistemas de gestão, plataformas SaaS e integrações que precisam incorporar fluxos de NFS-e Nacional.

## O que a biblioteca oferece

- **Emissão, consulta e cancelamento de NFS-e** por meio dos contratos de integração SEFIN implementados.
- **Assinatura digital da DPS**, com certificado e recursos de proteção de credenciais.
- **Geração local da DANFSe em PDF**, a partir do XML autorizado, sem depender da aparência de um sistema de gestão específico.
- **Integração com OpenBao/HashiCorp Vault**, além de interfaces que permitem adaptar o armazenamento de segredos.
- **Consulta de documentos e eventos via ADN**, separada do fluxo de emissão SEFIN.
- **Validação de XML e assinaturas** com distinção explícita entre integridade criptográfica e confiança na cadeia de certificados.

A biblioteca não decide automaticamente o enquadramento tributário de cada operação, não substitui orientação fiscal e não presume que novos leiautes publicados já estejam ativos em produção.

## Comece pelo Composer

```bash
composer require librecodeoop/nfse-php
```

Veja a [integração PHP](docs/integracao.md), os [contratos de emissão](docs/nt009.md) e as [garantias da DANFSe](docs/danfse-v2-rendering.md) antes de conectar um ambiente de produção.

## Para quem é

Esta é uma **biblioteca independente**: não precisa do Akaunting e não exige que uma aplicação utilize os mesmos componentes de infraestrutura da LibreCode. Quem desenvolve módulos, conectores ou sistemas próprios pode usar os contratos públicos e adaptar as dependências ao seu contexto.

Para integrar NFS-e diretamente no **Akaunting**, conheça o [módulo akaunting-nfse](https://github.com/LibreCodeCoop/akaunting-nfse), mantido separadamente.

## Suporte profissional e evolução do projeto

A [LibreCode](https://librecodecoop.org.br) desenvolve soluções livres e oferece consultoria, integração, adaptações, suporte técnico e manutenção para equipes que precisam operar NFS-e em seus sistemas.

**Contato comercial:** [comercial@librecodecoop.org.br](mailto:comercial@librecodecoop.org.br)

O código é aberto à comunidade. Contribuições, relatos de problemas e melhorias são bem-vindos; consulte [CONTRIBUTING.md](CONTRIBUTING.md) e as [issues](https://github.com/LibreCodeCoop/nfse-php/issues).
