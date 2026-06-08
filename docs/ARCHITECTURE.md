# Architecture

## Objetivo

Definir a arquitetura real do plugin hoje e a arquitetura alvo do novo admin SPA sem perder compatibilidade funcional com WooCommerce, licenciamento e renderers publicos.

## Arquitetura atual

- Bootstrap principal em `aireset-expresso-order.php`
- Admin legado concentrado em `includes/class-admin-page.php`
- Configuracoes concentradas em `includes/class-settings.php`
- Fluxo complementar concentrado em `includes/class-post-confirmation-flow.php`
- Proposta publica em `includes/class-public-proposal.php`
- Formulario publico em `includes/class-shortcode.php`
- PDF em `includes/class-pdf-settings.php`, `includes/class-pdf-admin-page.php` e `includes/class-document-manager.php`

## Limites do legado

- O shell admin atual ainda carrega assets globais pesados em quase todas as views
- O lazy-load atual troca HTML renderizado em PHP, mas nao isola estado por dominio
- `class-settings.php`, `class-post-confirmation-flow.php` e `class-admin-page.php` concentram responsabilidades demais
- Os previews nao seguem um contrato unico de dados e ownership

## Arquitetura alvo

- Frontend admin novo em `React + TypeScript + Vite`
- Bootstrap minimo em PHP para capability, nonce, licenca, feature flags e URLs base
- Namespace REST administrativo dedicado: `aireset-expresso-order/v1/admin`
- Estado separado por dominio: `app`, `orders`, `settings`, `previews`, `pdf`
- Convivencia entre admin novo e admin legado por feature flag

## Contratos administrativos introduzidos

### Bootstrap

- `GET /bootstrap`
- Resposta alvo: usuario autenticado, licenca, flags, rotas, views disponiveis e URLs base

### Settings

- `GET /settings/{section}`
- `POST /settings/{section}`
- Secoes-alvo:
  - `store`
  - `general`
  - `proposal`
  - `new-order`
  - `orders-list`
  - `confirmation-general`
  - `confirmation-contract`
  - `confirmation-upload-products`
  - `pdf`

### Previews

- `GET /previews/{surface}`
- Superficies:
  - `new-order`
  - `proposal`
  - `confirmation-contract`
  - `confirmation-upload-products`

### Orders

- `GET /orders`
- `GET /orders/{id}`
- `POST /orders`
- `PUT /orders/{id}`

## Fonte de verdade dos previews

- `new-order`: renderer publico do shortcode/pagina real
- `proposal`: renderer publico da proposta por token
- `confirmation-contract`: renderer publico contratual
- `confirmation-upload-products`: renderer publico da etapa final

Os previews do admin nao devem divergir da superficie publica. O admin consome o renderer publico ou um renderer equivalente no servidor, nunca um mock desconectado.

## Fundacao entregue nesta rodada

- Documentacao consolidada para a arquitetura alvo
- Fundacao de feature flag para o novo admin SPA
- Namespace REST administrativo para bootstrap e contratos iniciais
- Estrutura de build preparada para `Vite`

## Arquitetura historica

Os documentos de analise e transicao anteriores foram arquivados em `docs/archive/2026-05/`.
