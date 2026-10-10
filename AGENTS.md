<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# AGENTS.md — nfse-php

Estas regras valem para todo este repositório, inclusive contribuições de agentes de IA.

## Escopo e independência

- Esta é uma biblioteca genérica de integração NFS-e Nacional em PHP. **Não** acople o código de produção ao Akaunting, Laravel, Docker, banco de dados ou servidor da LibreCode. Respeite contratos públicos e interfaces injetáveis.
- Diferencie emissão SEFIN, distribuição ADN, validação de assinatura, confiança ICP-Brasil e geração de DANFSe. A fonte de valores fiscais para o PDF é o XML autorizado: não recalcule nem invente campos ausentes.
- Não introduza novos leiautes fiscais em produção com base apenas em publicação de minuta. Exija evidência oficial de ativação e testes de emissão/recuperação. Nunca repita um POST ambíguo sem reconciliação.

## Privacidade, segurança e licenças

- Use exclusivamente identificadores, endereços, nomes, valores e certificados **sintéticos** ou exemplos oficialmente públicos nas fixtures. Não copie de faturas, XML, PDFs, logs ou cadastros reais de clientes, mesmo em testes de regressão. Revise também snapshots, nomes de arquivos, mensagens de erro, screenshots e documentação.
- Não registre tokens, cabeçalhos Authorization, segredos PFX, chaves privadas, XMLs contendo dados de clientes nem dados pessoais em logs de CI/erro. Masque segredos na origem e teste a redação.
- Preserve o arquivo `LICENSE` na raiz para identificação pelo GitHub, além de `LICENSES/`, `REUSE.toml` e avisos SPDX. Recursos oficiais de terceiros, como a logo da NFS-e, mantêm sua atribuição e licença próprias; não os relicense sob AGPL.
- Recursos necessários ao renderer, incluindo PNG, devem existir como arquivos normais no pacote e ser validados por testes de empacotamento. Não silencie a ausência de um recurso obrigatório.

## Arquitetura e testes

- Extraia regras de domínio repetidas ou de precedência complexa para componentes testáveis sem infraestrutura. Não confunda o uso de `fn`/coleções com problema arquitetural: avalie acoplamento, responsabilidades e cobertura.
- Para mudanças no renderizador, teste XMLs sintéticos/autorizados de fixture, sem transmitir NFS-e. Preserve semântica de ISSQN, impostos e identidade; cubra diferenças de leiaute sem exigir PDF byte a byte idêntico.
- Rode `composer test`, `composer cs:check` e `composer psalm` (e verificações REUSE/CI correspondentes). Não anuncie PR pronto com checks obrigatórios pendentes ou falhando. Não reduza cobertura nem afrouxe assertions para obter verde.

## Publicação, consumidores e documentação

- `README.md` apresenta o propósito, os benefícios, a instalação mínima, onde encontrar documentação e suporte; conteúdos de protocolo, versões, configuração, testes e diagnósticos pertencem a `docs/` ou `CONTRIBUTING.md`.
- Documente **contratos estáveis**, não a narrativa de PRs, falhas transitórias de CI, caminhos absolutos ou detalhes do ambiente interno. Explique pré-condições sem presumir Docker.
- Ao alterar contrato/API ou comportamento de renderização, identifique consumidores afetados e comunique a necessidade de atualizar versões/pins e reconstruir artefatos. **Não modifique silenciosamente consumidores** nem diga que merge aqui já os atualizou. No módulo Akaunting separado, a rotina conhecida é atualizar o pin de `3rdparty/composer.json`, executar `composer thirdparty:build:prod`, conferir `source.reference` e reiniciar processos com OPcache após validação; isso é uma responsabilidade do consumidor, não uma dependência desta biblioteca.
- Commits seguem Conventional Commits, DCO (`Signed-off-by`) e, quando possível, assinatura criptográfica pelo serviço configurado. Mantenha PRs com escopo claro e documentação de alterações na descrição do PR, não no README.
