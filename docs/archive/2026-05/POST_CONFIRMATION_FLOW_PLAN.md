# Plan: Fluxos Canonicos de Confirmacao Pos-Proposta

Documentar a arquitetura, os caminhos, o backlog e o status atual do fluxo complementar do Aireset Expresso Order a partir de tres fluxos canonicos de operacao comercial. O objetivo deste documento e alinhar o plano com o processo real informado: proposta, pagamento opcional, contrato, etapa final unica com upload de logo e personalizacao dos produtos, PDF salvo no pedido e preparo para integracao futura.

## Objetivo

Manter o cliente final na mesma jornada publica por token, sem quebrar o fluxo atual da proposta, e suportar estes cenarios:

- encerrar o pedido sem jornada complementar quando esse for o fluxo escolhido pelo vendedor
- permitir jornada complementar sem pagamento
- permitir jornada complementar com pagamento antes do contrato
- exibir contrato em PDF ou visual equivalente e salvar esse contrato no pedido
- exibir uma etapa final unica com upload de arquivo do cliente e lista dos produtos do pedido
- permitir renomear somente os produtos nao bloqueados
- gerar um PDF final da personalizacao e manter esse material salvo para download
- preparar payload estruturado para integracoes futuras

## Fluxos Canonicos

### Fluxo 1: encerramento sem etapa complementar

1. cria-se o pedido
2. o vendedor envia o orcamento
3. o pedido e finalizado sem preenchimento adicional e sem avancar para contrato, pagamento ou personalizacao

Uso esperado:

- operacoes em que o vendedor conclui tudo manualmente
- pedidos que nao exigem contrato nem coleta posterior de branding/nome dos produtos

### Fluxo 2: confirmacao sem pagamento, com contrato e etapa final

1. cria-se o pedido
2. o vendedor envia o orcamento em PDF baixado manualmente
3. o cliente aprova comercialmente a proposta
4. o vendedor preenche os dados do cliente no pedido
5. o vendedor envia o link de confirmacao da proposta
6. o cliente confirma a proposta
7. o cliente e redirecionado para confirmar o contrato
8. o cliente confirma o contrato, que deve ficar salvo no pedido
9. o cliente e redirecionado para a etapa final unica
10. no topo da etapa final, o cliente anexa um arquivo em PDF ou PNG, que representa a logo dele
11. logo abaixo, o sistema lista os produtos do pedido no formato: imagem, titulo do produto e SKU na linha inferior
12. para cada produto nao bloqueado, aparece o campo para preenchimento do novo nome do produto
13. o cliente salva essa etapa
14. a lista personalizada gera um PDF e esse PDF fica salvo no sistema para download

### Fluxo 3: confirmacao com pagamento, contrato e etapa final

1. cria-se o pedido
2. o vendedor envia o orcamento em PDF baixado manualmente
3. o cliente aprova comercialmente a proposta
4. o vendedor envia o link de confirmacao da proposta
5. o cliente confirma a proposta
6. o cliente e redirecionado para realizar o pagamento
7. o cliente preenche os dados e conclui o pagamento
8. depois do pagamento, o cliente e redirecionado para confirmar o contrato
9. o cliente confirma o contrato, que deve ficar salvo no pedido
10. o cliente e redirecionado para a etapa final unica
11. no topo da etapa final, o cliente anexa um arquivo em PDF ou PNG, que representa a logo dele
12. logo abaixo, o sistema lista os produtos do pedido no formato: imagem, titulo do produto e SKU na linha inferior
13. para cada produto nao bloqueado, aparece o campo para preenchimento do novo nome do produto
14. o cliente salva essa etapa
15. a lista personalizada gera um PDF e esse PDF fica salvo no sistema para download

## Status Atual

### Auditoria do plano contra o codigo em 2026-05-04

- Concluido: modulo opcional ativavel, mesma jornada publica por token, etapa contratual, upload configuravel, personalizacao de produtos com bloqueio, placeholders, PDF consolidado e preview administrativo.
- Parcial: retomada apos pagamento existe e reaproveita o mesmo link, mas ainda precisa de validacao ponta a ponta por gateway e cenario real.
- Parcial: acompanhamento administrativo existe em partes, com resumo visivel em areas do admin, mas ainda nao esta consolidado como painel operacional unico por pedido.
- Parcial: a implementacao atual separa upload e personalizacao em etapas distintas; o fluxo canonico desejado agora e uma etapa final unica contendo upload no topo e lista dos produtos logo abaixo.
- Pendente: PDF final especifico da lista personalizada de produtos com a logo do cliente, salvo no pedido como artefato proprio do fluxo final.
- Concluido: payload estruturado formal e versionado para integracao futura, com schema estavel, snapshots, dados derivados, referencias a anexos e persistencia do snapshot no pedido.
- Parcial: governanca de dados sensiveis e rollout. Ja existe whitelist explicita de upload, limite de tamanho filterable, mensagens de falha mais granulares e reducao de dados sensiveis no payload canonicamente exportado; ainda faltam testes manuais finais e decisao operacional de retencao por negocio.
- Desvio importante: o plano antigo falava em etapa documental separada; a definicao atual substitui isso por contrato + etapa final unica de upload/logo e personalizacao de produtos.

### Ja entregue ou ja funcional no codigo atual

- modulo opcional de fluxo pos-confirmacao controlado por configuracao no admin
- jornada publica reaproveitando o mesmo link por token
- suporte ao desvio de fluxo quando pagamento esta desligado
- etapa contratual com titulo, descricao, checkbox e registro de data/hora
- placeholders de pedido, cobranca, entrega e aceite contratual
- PDF contratual/consolidado salvo a partir dos documentos configurados no plugin
- preview administrativo da etapa contratual
- upload configuravel no fluxo complementar
- etapa de personalizacao dos produtos com nomes desejados e suporte a itens bloqueados
- ajustes de token publico para evitar conflito com outros plugins

### Ajustes concluidos nesta rodada de trabalho

- editor administrativo de documentos ampliado sobre a base do wp.editor/TinyMCE classico do WordPress
- inclusao dos placeholders `{contract_date}`, `{contract_time}` e `{contract_datetime}`
- inclusao dos aliases explicitos `{contract_acceptance_date}`, `{contract_acceptance_time}` e `{contract_acceptance_datetime}`
- correcao do cabecalho da etapa contratual para sempre usar `post_confirmation_contract_title` e `post_confirmation_contract_document_description`
- remocao da exigencia de nome completo no aceite contratual

## Escopo Funcional Alvo

### Regra geral

O vendedor decide operacionalmente se o pedido seguira o fluxo simples de encerramento ou um dos fluxos complementares com link de confirmacao.

### Fluxo sem jornada complementar

O plugin nao deve forcar etapas adicionais quando o pedido for tratado no modelo simples do vendedor.

### Fluxo complementar sem pagamento

Depois da confirmacao da proposta, o cliente segue para o contrato e depois para a etapa final unica.

### Fluxo complementar com pagamento

Depois da confirmacao da proposta, o cliente segue para pagamento. Somente depois do pagamento ele volta para o contrato e, em seguida, para a etapa final unica.

## Etapas e Estados da Jornada

### Estados alvo recomendados

- aguardando_confirmacao
- aguardando_pagamento
- aguardando_aceite_contratual
- aguardando_etapa_final
- concluido

### Observacao sobre o codigo atual

O codigo hoje ainda trabalha com estados separados para upload e personalizacao. O alvo de produto agora passa a ser uma unica etapa final, visualmente e funcionalmente unificada.

### Campos minimos de persistencia recomendados

- schema_version
- stage atual
- accepted_at
- contract_text_snapshot
- contract_pdf_attachment_id ou referencia equivalente
- brand_attachment_id e metadados auxiliares
- custom_names por item
- original_names_snapshot por item
- locked_items_snapshot
- final_customization_pdf_attachment_id
- generated_at / completed_at

## Decisoes de Arquitetura

- manter toda a jornada do cliente na mesma URL publica por token
- permitir contrato em PDF ou visual equivalente dentro da propria jornada publica
- tratar a ultima fase como uma unica etapa de experiencia, mesmo que internamente exista mais de um bloco de persistencia
- usar attachment do WordPress para o upload inicial da logo do cliente
- gerar um PDF final especifico da personalizacao dos produtos com a logo anexada quando aplicavel
- separar o payload estruturado da camada de renderizacao HTML/PDF
- preservar comportamento atual quando o recurso estiver desligado
- manter compatibilidade com o admin SPA existente

## Caminhos Mapeados

### Caminho publico

- proposta publica por token
- confirmacao da proposta
- pagamento opcional
- contrato
- etapa final unica com upload da logo e listagem dos produtos do pedido
- geracao e salvamento do PDF final da personalizacao
- tela final de conclusao

### Caminho administrativo

- ativar ou desativar o recurso
- decidir operacionalmente quando o pedido segue no fluxo simples e quando segue no fluxo complementar
- configurar textos e labels da jornada
- configurar contrato
- configurar produtos bloqueados para renomeacao
- acompanhar o status do fluxo por pedido
- revisar contrato salvo, anexo da logo e nomes personalizados
- baixar PDF final da personalizacao

### Caminho de integracao futura

- ler um payload estavel salvo no pedido
- reutilizar snapshots estruturados, sem depender do HTML da pagina publica
- expor contrato salvo, logo enviada, itens originais, itens personalizados e PDF final
- permitir exportacao futura para webhook, REST ou sincronizacao externa

## Arquivos-Chave

- `includes/class-public-proposal.php` ou fluxo publico equivalente: ponto de entrada da jornada por token
- `includes/class-post-confirmation-flow.php`: motor do fluxo complementar, contrato, upload, personalizacao e PDFs do fluxo
- `includes/class-settings.php`: defaults, sanitizacao e configuracao administrativa do recurso
- `includes/class-document-manager.php`: geracao e acesso publico/privado de documentos e PDFs
- `includes/class-orders-page.php`: apoio para acompanhamento administrativo
- `includes/class-wc-pdf-integration.php`: integracao administrativa com pedido, colunas e metaboxes quando aplicavel
- `templates/admin-page.php`: shell SPA e navegacao administrativa
- `assets/js/settings-admin.js`: UX do editor e gestao administrativa do fluxo
- `assets/scss/frontend/style.scss`: base visual do fluxo publico
- `assets/scss/admin/settings.scss`: base visual das configuracoes
- `aireset-expresso-order.php`: bootstrap, carregamento e versionamento final
- `CHANGELOG.md`: registro de entrega

## Backlog Recomendado

### Fase 1 - Alinhar o codigo aos tres fluxos canonicos

1. consolidar o conceito de fluxo simples sem jornada complementar
2. validar a matriz completa de estados para pedidos com e sem pagamento
3. validar retorno ao mesmo link publico depois do pagamento em todos os gateways suportados
4. revisar transicoes incompletas, mensagens de erro e retomada segura da jornada

### Fase 2 - Unificar a etapa final

1. transformar upload e personalizacao em uma unica etapa publica
2. renderizar o upload da logo no topo da etapa final
3. renderizar a lista dos produtos imediatamente abaixo do upload, no formato imagem, titulo e SKU
4. respeitar o bloqueio de produtos sem expor campo de renomeacao para itens bloqueados

### Fase 3 - PDF final da personalizacao

1. definir o layout do PDF final com logo, itens originais e nomes personalizados
2. gerar esse PDF ao salvar a etapa final
3. salvar esse PDF no pedido para download posterior
4. expor link de download no admin e no fluxo concluido quando fizer sentido

### Fase 4 - Consolidacao administrativa

1. exibir resumo do fluxo complementar no admin WooCommerce por pedido
2. exibir o mesmo resumo no shell SPA do plugin
3. permitir consulta rapida de contrato salvo, logo enviada e personalizacao por pedido
4. centralizar links de download do contrato salvo e do PDF final da personalizacao

### Fase 5 - Payload estruturado para integracao futura

1. definir um schema de saida estavel em array/JSON
2. separar claramente snapshots de entrada, dados derivados e referencias a anexos
3. padronizar nomes de chaves para pedido, contrato, logo, itens e PDF final
4. prever versao de payload para evolucao sem quebra

### Fase 6 - Rollout e governanca

1. revisar retencao, privacidade e politica de dados sensiveis dos contratos e anexos
2. formalizar limites de tamanho, whitelist e mensagens de falha de upload
3. executar validacao manual ponta a ponta nos tres fluxos canonicos
4. rodar php -l nos arquivos PHP alterados e revisar erros do editor
5. atualizar changelog e versao somente ao final do lote validado

## Governanca adotada nesta implementacao

- o payload canonicamente estruturado do fluxo complementar passou a usar schema versionado proprio e e salvo no pedido para integracoes futuras, sem depender de parsing do HTML publico
- o payload estruturado separa `snapshots`, `derived` e `references`, mantendo o IP de aceite fora do contrato canonico de integracao e deixando esse dado restrito ao contexto administrativo legado quando necessario
- o upload do anexo complementar aceita apenas JPG, JPEG, PNG e PDF
- o limite padrao de upload do anexo complementar e o menor valor entre 8 MB e o teto de upload do WordPress, com filtro dedicado para override tecnico quando necessario
- os notices de upload agora distinguem formato invalido, arquivo acima do limite e falha geral de transferencia
- os binarios continuam armazenados como attachments do WordPress; o payload salvo no pedido persiste referencias, metadados e snapshots textuais, nao uma copia extra dos arquivos
- a politica final de retencao e descarte continua dependente de definicao operacional do projeto e deve ser fechada antes do rollout produtivo

## Checklist de Verificacao

1. confirmar o fluxo 1 sem jornada complementar e sem regressao
2. confirmar o fluxo 2 com contrato e etapa final unica, sem pagamento
3. confirmar o fluxo 3 com pagamento antes do contrato e da etapa final
4. confirmar que o contrato fica salvo no pedido
5. confirmar upload de PDF/PNG para a logo do cliente
6. confirmar listagem dos produtos com imagem, titulo e SKU
7. confirmar bloqueio de itens sem campo de renomeacao
8. confirmar persistencia dos novos nomes por item
9. confirmar geracao e download do PDF final da personalizacao
10. confirmar que a futura integracao podera consumir payload estruturado, sem parsing de HTML

## Decisoes de Produto e Premissas

- o vendedor pode operar tanto no fluxo simples quanto nos fluxos complementares
- o orcamento manual em PDF continua existindo como realidade operacional dos fluxos 2 e 3
- no fluxo 2, o vendedor preenche os dados do cliente antes de enviar o link de confirmacao
- o contrato da primeira versao pode ser apresentado em PDF ou visual equivalente dentro da etapa contratual
- o aceite minimo e checkbox + data/hora
- a etapa final deve comecar com o upload da logo e seguir imediatamente para a lista dos produtos
- o bloqueio de renomeacao continua configurado manualmente no admin do plugin
- esta etapa nao inclui integracao externa efetiva, assinatura desenhada, OCR ou validacao automatica de documentos

## Proximo Lote Recomendado

Com a parte implementavel deste lote concluida, a ordem mais segura passa a ser:

1. executar validacao manual ponta a ponta nos tres fluxos canonicos
2. corrigir regressao visual, funcional ou de permissao que aparecer nos testes
3. revisar checklist final de governanca e retencao antes do rollout
4. atualizar changelog e versao somente depois da rodada final de validacao
