# Aireset - Expresso Order

Plugin WordPress para operacao comercial com WooCommerce, focado em criacao rapida de pedidos, propostas publicas e administracao em uma interface SPA no painel.

## Versao atual

`1.1.91`

## Principais recursos

- criacao rapida de pedidos expresso com busca de produtos e cliente
- painel administrativo SPA em `Aireset > Pedido Expresso`
- navegacao interna para `Novo pedido`, `Pedidos`, `Configuracoes` e `Licenca`
- tela publica de proposta para compartilhamento com o cliente
- calculo de frete e descontos integrados ao WooCommerce
- controle de identidade visual da proposta e do painel
- sistema de licenca integrado ao Elite Licenser
- suporte ao perfil `vendedor_expresso`

## Modulo PDF nativo

O modulo PDF agora inclui uma central propria em `Aireset > Pedido Expresso > PDF` com:

- configuracoes gerais da identidade da loja, layout e comportamento de download
- configuracoes independentes para pedido e proposta
- documentacao embutida dentro do admin e tooltips por campo
- politica de acesso ao link por nonce, token ou dono do pedido
- numeracao com reset anual opcional e marcacao de impressao
- preview HTML do documento e exportacao XML experimental para UBL, CII e Peppol
- danger zone com limpeza de cache e reset de contadores

## Requisitos

- WordPress
- WooCommerce ativo
- licenca valida do plugin para liberar o carregamento completo

## Estrutura do plugin

- [`aireset-expresso-order.php`](./aireset-expresso-order.php): bootstrap principal e gate de licenca
- [`includes/`](./includes): regras de negocio, admin, licenca, AJAX, pedidos e configuracoes
- [`templates/`](./templates): templates do admin SPA, listagens e shortcode
- [`assets/js/`](./assets/js): comportamento do admin, configuracoes e flyout
- [`assets/scss/`](./assets/scss): fonte SCSS do frontend e do admin
- [`assets/css/`](./assets/css): CSS compilado distribuido com o plugin

## Planos e arquitetura

- [`POST_CONFIRMATION_FLOW_PLAN.md`](./POST_CONFIRMATION_FLOW_PLAN.md): planejamento completo do fluxo documental pos-proposta, com status atual, backlog e caminhos mapeados
- [`ARCHITECTURE_MODERNIZATION_ROADMAP.md`](./ARCHITECTURE_MODERNIZATION_ROADMAP.md): diagnostico tecnico do plugin, arquitetura futura recomendada, stack ideal, migracao para API-first/SPA e metas de performance

## Identidade do admin

O shell visual administrativo segue o padrao documentado em [`ADMIN_UI_BRAND.md`](./ADMIN_UI_BRAND.md), com hero proprio do plugin e navegacao lateral compartilhada com a linha Aireset.

## Desenvolvimento

Instale as dependencias de front-end e use os scripts abaixo para compilar os estilos:

```bash
npm install
npm run build:css
npm run watch:css
```

Os estilos sao compilados a partir de:

- `assets/scss/frontend/style.scss` -> `assets/css/frontend.css`
- `assets/scss/admin/style.scss` -> `assets/css/admin.css`
- `assets/scss/admin/settings.scss` -> `assets/css/settings-admin.css`

## Fluxo administrativo

Com a licenca ativa, o plugin opera a partir da ancora:

- `wp-admin/admin.php?page=eop-pedido-expresso`

Views SPA disponiveis:

- `?view=new-order`
- `?view=orders`
- `?view=settings`
- `?view=license`

Sem licenca ativa, o plugin mantem apenas a tela de ativacao e nao exibe submenu proprio de licenca.

## Licenca e Integridade

O plugin usa integracao proprietaria com Elite Licenser.

- classe base: [`includes/class-eop-license-base.php`](./includes/class-eop-license-base.php)
- manager: [`includes/class-eop-license-manager.php`](./includes/class-eop-license-manager.php)
- integridade: [`includes/class-eop-integrity.php`](./includes/class-eop-integrity.php)
- guard de carregamento: [`includes/trait-eop-license-guard.php`](./includes/trait-eop-license-guard.php)

## Changelog

O historico completo de alteracoes esta em [`CHANGELOG.md`](./CHANGELOG.md).

Destaques recentes da serie `1.1.40`:

- acoes em massa para aplicar quantidade e desconto padrao aos itens no admin e no shortcode
- valor unitario com desconto visivel nos cards e persistencia correta do modo de desconto ao recarregar pedidos
- ajuste das acoes e caixas de PDF para respeitar o tipo real do documento em cada pedido

## Autor

Aireset Agencia Web  
[aireset.com.br](https://aireset.com.br)
