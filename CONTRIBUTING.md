<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Contribuir com nfse-php

A biblioteca é independente de frameworks. Abra uma issue com comportamento esperado, documentação oficial pertinente, versão do PHP e reprodução com **dados sintéticos**. Não inclua documentos fiscais reais, certificados, chaves, tokens ou logs de clientes.

Para enviar um PR:

1. Mantenha alterações pequenas e respeite os contratos públicos e os limites entre SEFIN, ADN e DANFSe.
2. Adicione testes unitários/de integração para qualquer comportamento novo; não faça emissões fiscais reais no CI.
3. Execute `composer test`, `composer cs:check` e `composer psalm`. Verifique os workflows obrigatórios, inclusive REUSE.
4. Descreva motivação, impacto nos consumidores, riscos e validação **na descrição do PR**. Documente no repositório somente interfaces e procedimentos duráveis.
5. Use Conventional Commits, DCO (`git commit -s`) e a assinatura criptográfica configurada quando disponível.

Os códigos e textos seguem a AGPL indicada no arquivo raiz `LICENSE`; recursos de terceiros podem possuir condições próprias detalhadas junto aos arquivos e no diretório `LICENSES/`.
