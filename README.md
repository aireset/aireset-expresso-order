# Aireset Expresso Order

Plugin WordPress/WooCommerce privado da Aireset para operacao comercial interna, criacao rapida de pedidos, proposta publica por token e fluxo complementar pos-proposta.

## Versao atual

`1.2.30`

## Ownership e licenca

- Titularidade documental e operacional: `Felipe Almeman + Aireset`
- Licenca: proprietaria
- Termos legais e politica para IA: [`docs/LEGAL_AND_AI_POLICY.md`](./docs/LEGAL_AND_AI_POLICY.md)
- Texto integral da licenca: [`LICENSE`](./LICENSE)

## Documentacao canonica

- [`docs/ARCHITECTURE.md`](./docs/ARCHITECTURE.md): arquitetura atual, arquitetura alvo, contratos do admin e limites do legado
- [`docs/ROADMAP.md`](./docs/ROADMAP.md): backlog consolidado, matriz por superficie e criterio de aceite
- [`docs/OPERATIONS.md`](./docs/OPERATIONS.md): build, release, empacotamento e smoke tests
- [`docs/LEGAL_AND_AI_POLICY.md`](./docs/LEGAL_AND_AI_POLICY.md): titularidade, restricoes de uso, politica para IA e fluxo de autorizacao
- [`AGENT.md`](./AGENT.md): instrucoes operacionais curtas para agentes
- [`CHANGELOG.md`](./CHANGELOG.md): historico de alteracoes

## Estrutura principal

- [`aireset-expresso-order.php`](./aireset-expresso-order.php): bootstrap principal, constantes e gate de licenca
- [`includes/`](./includes): classes de admin, settings, pedidos, PDF, AJAX, licenca e fluxo complementar
- [`templates/`](./templates): renderers PHP do admin e das superficies publicas
- [`assets/`](./assets): CSS, JS legado, imagens e base do novo admin SPA
- [`docs/`](./docs): documentacao canonica do plugin

## Estado tecnico atual

- O admin React/Vite e a superficie administrativa principal quando o bundle esta compilado
- O admin legado fica como fallback tecnico via `eop_admin_legacy=1`
- Os previews publicos continuam sendo a fonte de verdade funcional

## Arquivo historico

Os documentos extensos que deixaram de ser fonte canonica foram movidos para [`docs/archive/2026-05/`](./docs/archive/2026-05/).

## Requisitos

- WordPress
- WooCommerce ativo
- Licenca valida do plugin

## Desenvolvimento

```bash
npm install
npm run build:css
npm run build:admin-spa
npm run smoke:admin-browser
```

O admin legado continua compilando CSS a partir de `assets/scss/`.
O novo admin SPA usa a stack documentada em [`docs/ARCHITECTURE.md`](./docs/ARCHITECTURE.md).
