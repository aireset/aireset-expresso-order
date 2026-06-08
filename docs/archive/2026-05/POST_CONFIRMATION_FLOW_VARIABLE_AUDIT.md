# Auditoria: Fluxo de Confirmacao Pos-Proposta

Data da auditoria: 2026-05-05

Arquivo-base do plano: `POST_CONFIRMATION_FLOW_PLAN.md`

Arquivos analisados:

- `includes/class-post-confirmation-flow.php`
- `includes/class-public-proposal.php`
- `includes/class-settings.php`
- `includes/class-orders-page.php`
- `includes/class-document-manager.php`
- `includes/class-wc-pdf-integration.php`
- `assets/js/settings-admin.js`
- `assets/js/orders.js`

## Resumo Executivo

O fluxo complementar existe e possui boa parte da persistencia necessaria: token publico, flag de proposta, aceite contratual, anexo/logo, personalizacao de itens, documentos de assinatura e endpoints REST/admin.

Porem, o estado atual do arquivo `class-post-confirmation-flow.php` nao esta totalmente coerente com o fluxo canonico descrito no plano. O ponto mais critico e que a versao ativa de `render_frontend_stage()` renderiza o formulario da etapa final mesmo quando o pedido esta em `contract` ou `payment`. A versao anterior, que fazia o `switch` correto entre pagamento, contrato, etapa final e conclusao, esta comentada entre as linhas 995 e 1133.

Impacto pratico: no estado `contract`, o cliente tende a ver o formulario final com nonce/action de `products`, mas `handle_request()` so aceita `contract` quando o estado atual e `contract`. Resultado provavel: aceite contratual fica bloqueado por `invalid_request`.

## Fluxo Real Encontrado

### Entrada publica

- `EOP_Public_Proposal::create_public_token()` cria:
  - `_eop_public_token`
  - `_eop_is_proposal = yes`
  - `_eop_proposal_confirmed = no`
- `EOP_Public_Proposal::handle_confirmation()` grava `_eop_proposal_confirmed = yes`.
- `EOP_Public_Proposal::render_proposal_page()` chama `EOP_Post_Confirmation_Flow::render_frontend_stage()` quando a proposta ja foi confirmada e o fluxo esta ativo.

### Motor do fluxo

- `EOP_Post_Confirmation_Flow::get_current_stage()` calcula os estados atuais:
  - `inactive`
  - `awaiting_confirmation`
  - `payment`
  - `contract`
  - `upload`
  - `products`
  - `completed`

O plano recomenda nomes de estado em portugues/negocio:

- `aguardando_confirmacao`
- `aguardando_pagamento`
- `aguardando_aceite_contratual`
- `aguardando_etapa_final`
- `concluido`

O codigo usa nomes tecnicos em ingles. Isso nao quebra por si so, mas deve ser tratado como divergencia de nomenclatura entre plano e implementacao.

### Etapa final

O codigo ja tenta unificar upload e personalizacao:

- `process_upload_submission()` chama `process_final_step_submission( $order, true )`
- `process_products_submission()` chama `process_final_step_submission( $order, false )`
- `render_final_step_form()` existe e une upload + produtos

Mas ainda ha estados internos separados `upload` e `products`, como o plano ja observava. Visualmente, a intencao de etapa final unica esta parcialmente implementada.

## Variaveis/Chaves de Configuracao

### Usadas corretamente

| Chave | Onde e usada | Status |
| --- | --- | --- |
| `enable_post_confirmation_flow` | `EOP_Post_Confirmation_Flow::is_enabled()` | Usada corretamente para ativar/desativar o fluxo. |
| `enable_checkout_confirmation` | proposta publica, retorno de pagamento e `order_requires_payment()` | Usada para controlar pagamento antes da continuidade. |
| `post_confirmation_contract_title` | titulo da etapa/contrato e preview | Usada. |
| `post_confirmation_contract_document_description` | descricao do documento contratual | Usada. |
| `post_confirmation_contract_checkbox_label` | formulario de aceite contratual | Usada quando o formulario de contrato e renderizado. |
| `post_confirmation_contract_button_label` | botao do aceite contratual | Usada quando o formulario de contrato e renderizado. |
| `post_confirmation_contract_body` | fallback legado do contrato | Usada como fallback quando nao ha documentos configurados. |
| `post_confirmation_signature_documents` | documentos contratuais/editor/anexo | Usada para gerar documentos assinaveis/anexos. |
| `post_confirmation_require_attachment` | `requires_attachment()` | Usada para exigir ou dispensar anexo. |
| `post_confirmation_locked_products` | `get_locked_product_tokens()` / `is_product_locked()` | Usada para bloquear renomeacao por SKU/ID/slug/nome. |
| `post_confirmation_completion_title` | titulo da conclusao | Usada. |
| `post_confirmation_completion_description` | descricao da conclusao | Usada. |

### Parcialmente usadas ou inconsistentes

| Chave | Situacao |
| --- | --- |
| `post_confirmation_upload_title` | Existe no admin e em `get_stage_title()`, mas a funcao ativa de renderizacao mostra texto fixo no bloco final. |
| `post_confirmation_upload_description` | Existe no admin e em `render_final_step_form()`, mas a funcao ativa duplica o formulario final com texto fixo. |
| `post_confirmation_upload_button_label` | Existe no admin e em `render_final_step_form()`, mas a funcao ativa usa texto fixo `Salvar personalizacao`. |
| `post_confirmation_products_title` | Existe no admin e em `get_stage_title()`, mas a funcao ativa usa texto fixo `Personalize os produtos do pedido`. |
| `post_confirmation_products_description` | Existe no admin e em `render_final_step_form()`, mas a funcao ativa usa texto fixo. |
| `post_confirmation_products_button_label` | Existe no admin e em `render_final_step_form()`, mas a funcao ativa usa texto fixo `Salvar personalizacao`. |

Conclusao: essas chaves nao estao mortas, mas estao inconsistentes porque a UI ativa nao respeita todos os textos configuraveis.

### Usadas no admin/export, mas nao no fluxo publico atual

| Chave | Situacao |
| --- | --- |
| `post_confirmation_documents_title` | Persistida e exibida no admin/configuracao, mas a etapa documental separada nao aparece no fluxo publico atual. |
| `post_confirmation_documents_description` | Mesmo caso acima. |
| `post_confirmation_documents_button_label` | Mesmo caso acima. |
| `post_confirmation_document_{1..3}_label` | Usada para montar `get_order_data_rows()`/document fields, mas o plano atual substitui a etapa documental por contrato + etapa final unica. |
| `post_confirmation_document_{1..3}_placeholder` | Mesmo caso acima. |

Conclusao: sao legado/parcial. Podem continuar se forem usados em PDF/admin, mas nao fazem parte do fluxo canonico novo.

## Metadados do Pedido

### Usados e persistidos

| Meta | Uso |
| --- | --- |
| `_eop_public_token` | Link publico da proposta/fluxo. |
| `_eop_is_proposal` | Ativa comportamento de proposta no pedido. |
| `_eop_proposal_confirmed` | Marca confirmacao comercial da proposta. |
| `_eop_post_confirmation_flow_data` | JSON principal do fluxo complementar. |
| `_eop_post_confirmation_flow_completed` | Flag rapida de conclusao. |
| `_eop_post_confirmation_flow_stage` | Stage salvo para listagem/admin. |
| `_eop_post_confirmation_contract_accepted_at` | Data/hora do aceite. |
| `_eop_post_confirmation_contract_accepted_name` | Ainda existe, mas hoje e gravado vazio porque a exigencia de nome foi removida. |
| `_eop_post_confirmation_contract_accepted_ip` | IP do aceite. |
| `_eop_post_confirmation_contract_text` | Snapshot HTML/texto do contrato aceito. |
| `_eop_post_confirmation_attachment_id` | Anexo/logo enviado pelo cliente. |
| `_eop_original_product_snapshot` | Nome original do item no momento da personalizacao. |
| `_eop_custom_name_locked` | Flag por item para bloqueio. |
| `_eop_custom_product_name` | Nome personalizado por item. |

### Chaves recomendadas pelo plano, mas ausentes

| Chave recomendada | Status atual |
| --- | --- |
| `brand_attachment_id` | Existe como `state['attachment']['id']` e `_eop_post_confirmation_attachment_id`, mas nao com esse nome semantico. |
| `contract_pdf_attachment_id` | Nao existe como chave clara. Documentos de assinatura ficam em `state['signature_documents'][].attachment_id`. |
| `final_customization_pdf_attachment_id` | Nao encontrado. PDF final especifico da personalizacao ainda nao foi implementado. |
| `generated_at` do PDF final | Nao encontrado para o PDF final de personalizacao. |
| Payload estruturado versionado para integracao | `get_export_data()` existe, mas ainda nao ha schema formal/versionado salvo no pedido. |

## Estado JSON Principal

`_eop_post_confirmation_flow_data` usa este formato base:

- `schema_version`
- `current_stage`
- `completed_at`
- `contract.accepted`
- `contract.accepted_name`
- `contract.accepted_at`
- `contract.accepted_ip`
- `contract.contract_text`
- `documents`
- `attachment.id`
- `attachment.filename`
- `attachment.uploaded_at`
- `signature_documents`
- `products`

### Usados corretamente

- `schema_version`: inicializado, mas hoje sem evolucao pratica.
- `current_stage`: recalculado e persistido.
- `completed_at`: preenchido ao concluir.
- `contract.accepted`: usado para passar da etapa de contrato.
- `contract.accepted_at`: usado em status, export e placeholders.
- `contract.accepted_ip`: salvo e exposto apenas em contexto admin.
- `contract.contract_text`: snapshot/fallback do contrato.
- `attachment.id`: decide se upload foi feito.
- `attachment.filename`: exibido no fluxo/admin.
- `attachment.uploaded_at`: exibido no fluxo/admin.
- `signature_documents`: usado para documentos gerados/anexados.
- `products`: usado para nomes customizados por item.

### Parcialmente usados

- `contract.accepted_name`: mantido por compatibilidade, mas sempre fica vazio no novo aceite.
- `documents`: inicializado e exportado como alias de dados do pedido, mas a etapa documental separada nao existe mais no fluxo canonico.

## Variaveis POST/GET do Fluxo

### GET

| Variavel | Uso |
| --- | --- |
| `eop_proposal` | Token publico principal da proposta. |
| `eop_token` | Alias para downloads publicos de PDF/documento. |
| `eop_confirmed` | Mensagem visual apos confirmar proposta. |
| `eop_flow_notice` | Mensagem apos submissao do fluxo complementar. |
| `eop_post_confirmation_pdf` | Download/visualizacao publica do PDF consolidado. |
| `eop_post_confirmation_signature_document` | Download/visualizacao publica de documento de assinatura. |
| `document_key` | Escolhe documento de assinatura. |
| `download` | Define inline/download. |

### POST

| Variavel | Uso |
| --- | --- |
| `eop_confirm_proposal_nonce` | Nonce da confirmacao da proposta. |
| `eop_proposal_token` | Token publico na confirmacao e no fluxo complementar. |
| `eop_post_confirmation_nonce` | Nonce da etapa complementar. |
| `eop_post_confirmation_action` | `contract`, `upload` ou `products`. |
| `eop_contract_accept` | Checkbox do aceite contratual. |
| `eop_post_confirmation_attachment` | Arquivo enviado pelo cliente. |
| `eop_product_name[item_id]` | Nomes personalizados por item. |

## Problemas Encontrados

### Critico: renderizacao ativa nao respeita o stage

Em `class-post-confirmation-flow.php`, a funcao ativa `render_frontend_stage()` abre na linha 798 e renderiza direto o formulario final. Ela nao chama:

- `render_contract_form()` para `contract`
- botao de pagamento para `payment`
- `render_completion_panel()` para `completed`

A versao comentada entre as linhas 995 e 1133 tinha essa decisao correta.

### Critico: contrato pode ficar impossivel de aceitar

Na funcao ativa, `$action` e calculado assim:

- `upload` se `$stage === 'upload'`
- `products` para qualquer outro stage

Quando `$stage === 'contract'`, o formulario envia `eop_post_confirmation_action=products`. Em `handle_request()`, a linha de validacao compara a action com o stage atual:

- `products` so e aceito quando o stage atual e `products`
- portanto, no stage `contract`, isso cai em `invalid_request`

### Alto: pagamento pode nao aparecer no fluxo complementar

`get_current_stage()` retorna `payment` quando o pedido precisa pagamento. Mas a funcao ativa nao renderiza o CTA de checkout. A versao comentada tinha o bloco `if ( 'payment' === $stage )`.

### Medio: textos configuraveis nao sao totalmente respeitados

As chaves de upload/produtos existem, sao salvas e aparecem no admin, mas a funcao ativa usa textos fixos no formulario duplicado. Isso reduz controle administrativo e torna parte das configuracoes aparentemente inoperante.

### Medio: PDF final de personalizacao nao existe

O codigo tem PDF consolidado do fluxo/contrato e documentos de assinatura, mas nao foi encontrado `final_customization_pdf_attachment_id` nem geracao de PDF especifico da lista personalizada com logo do cliente.

### Medio: payload estruturado existe como retorno, nao como contrato versionado salvo

`get_export_data()` monta um payload bom para REST/admin, mas o plano pede um schema estavel/versionado para integracao futura. Hoje isso ainda parece runtime/export, nao uma estrutura formal persistida com versao propria.

## Recomendacao de Correcao

1. Restaurar a logica de roteamento por stage dentro de `render_frontend_stage()`:
   - `payment` -> CTA de pagamento
   - `contract` -> `render_contract_form()`
   - `upload`/`products` -> `render_final_step_form()`
   - `completed` -> `render_completion_panel()`
2. Remover o bloco antigo comentado para evitar ambiguidade.
3. Fazer a etapa final unica preservar internamente `upload` e `products`, se desejado, mas com UI unica e textos vindos das configuracoes.
4. Gerar e salvar o PDF final da personalizacao:
   - `final_customization_pdf_attachment_id`
   - `generated_at`
   - link admin/download
5. Formalizar `get_export_data()` como schema versionado:
   - `schema_version`
   - `order`
   - `contract`
   - `brand_attachment`
   - `items`
   - `final_customization_pdf`

## Status Geral Contra o Plano

| Item do plano | Status atual |
| --- | --- |
| mesma jornada publica por token | Implementado. |
| fluxo sem jornada complementar | Parcial: depende de nao ativar/enviar fluxo, nao ha controle operacional por pedido alem de proposta/flag. |
| fluxo sem pagamento -> contrato -> etapa final | Quebrado ou instavel pela renderizacao ativa. |
| fluxo com pagamento -> contrato -> etapa final | Parcial/quebrado na UI ativa de pagamento. |
| contrato salvo no pedido | Implementado quando o aceite consegue ser processado. |
| upload PDF/PNG/JPG | Implementado. O plano cita PDF/PNG; o codigo tambem aceita JPG/JPEG. |
| produtos com imagem, titulo e SKU | Implementado na etapa final. |
| bloqueio sem campo de renomeacao | Parcial: o campo aparece `disabled` para bloqueados; o plano pede nao expor campo. |
| persistencia dos nomes | Implementado. |
| PDF final da personalizacao | Pendente. |
| payload estruturado futuro | Parcial via `get_export_data()`, ainda sem contrato formal/persistido. |

