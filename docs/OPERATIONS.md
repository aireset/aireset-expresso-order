# Operations

## Build local

### CSS legado

```bash
npm install
npm run build:css
```

### Admin SPA novo

```bash
npm run build:admin-spa
```

## Release

1. Validar sintaxe PHP dos arquivos alterados
2. Validar consistencia de versao e licenca
3. Compilar CSS legado
4. Compilar o admin SPA quando houver mudancas em `assets/admin-spa/`
5. Gerar pacote com `php scripts/build-release-package.php`
6. Validar o zip final antes de publicar

## Smoke tests minimos

### REST administrativo via WP-CLI

Execute no diretorio raiz do WordPress:

```bash
wp eval-file wp-content/plugins/aireset-expresso-order/scripts/smoke-admin-rest.php --skip-themes
```

Em ambiente Windows sem `wp` global, use o PHAR local:

```bash
php ../tools/wp-cli.phar --path=. --skip-themes eval-file wp-content/plugins/aireset-expresso-order/scripts/smoke-admin-rest.php
```

Esse teste valida `GET /bootstrap`, `GET /settings/{section}`, previews administrativos, lazy-view, abas PDF, busca de categorias/produtos e `GET /orders` usando um usuario administrador real, sem salvar dados.

### Browser smoke administrativo via Playwright

Execute no diretorio do plugin:

```bash
npm run smoke:admin-browser
```

O script usa WP-CLI para gerar cookies temporarios de um administrador real, abre o WordPress com Chromium e grava relatorio, HTML e screenshots em `output/eop-browser-smoke/`, fora da pasta do plugin.

Variaveis uteis:

- `WP_PATH`: caminho da raiz WordPress quando o script nao estiver dentro de `wp-content/plugins/aireset-expresso-order`
- `WP_CLI_BIN`: binario `wp` alternativo; sem isso o script tenta `../tools/wp-cli.phar`
- `EOP_SMOKE_USER_ID`: ID de usuario administrador especifico
- `EOP_SMOKE_ONLY`: lista separada por virgula para rodar checks especificos, por exemplo `admin-shell,admin-spa-feature-flag`
- `EOP_SMOKE_HEADED=1`: abre o navegador visivel para depuracao

Esse teste valida o shell admin, `new-order`, `orders`, `/pedido-expresso/`, previews de configuracao, PDF e SPA por feature flag. Ele falha em redirect para login, erro PHP fatal, seletor ausente ou `pageerror` JavaScript.

### Baseline de performance do admin

Execute no diretorio do plugin:

```bash
npm run baseline:admin-performance
```

O script abre as views principais do admin legado, a pagina publica `/pedido-expresso/` e o SPA por feature flag, sem criar pedidos e sem salvar configuracoes. Ele grava `baseline.json` e `baseline.md` em `output/eop-performance-baseline/`.

Variaveis uteis:

- `EOP_PERF_RUNS`: quantidade de amostras por alvo; padrao `3`
- `EOP_PERF_ONLY`: lista separada por virgula, por exemplo `legacy-orders,spa-feature-flag`
- `EOP_PERF_USER_ID`: ID de administrador especifico
- `EOP_PERF_HEADED=1`: abre o navegador visivel

## Migracao de AJAX para REST

- O JS legado de `new-order`, `orders`, settings, lazy-view, PDF e troca manual de etapa complementar tenta primeiro `aireset-expresso-order/v1/admin`
- `admin-ajax.php` permanece como fallback temporario para manter compatibilidade operacional
- Antes de remover qualquer handler `wp_ajax_*`, validar no navegador busca de produto, busca de cliente, frete, criacao e edicao real de pedido
- A remocao definitiva de AJAX deve ser feita por dominio, nunca em lote unico

- Licenca valida: menu e fluxo principal carregam
- Licenca invalida: gate continua funcionando
- `new-order`: busca produto, busca cliente, frete e criacao de pedido
- `orders`: listagem e abertura para edicao
- `proposal public`: acesso por token
- `confirmation contract` e `confirmation upload/products`: preview e renderer publico coerentes
- `pdf`: preview, download e configuracoes

## Feature flag do admin novo

- Com bundle compilado, o admin novo e a superficie padrao (`eop_admin_experimental.enabled` assume `yes`)
- Habilitacao continua controlavel por filtro `eop_enable_admin_spa` e pela opcao `eop_admin_experimental`
- Sem bundle compilado, o sistema cai de forma limpa no admin legado
- O admin legado permanece acessivel sob demanda via `eop_admin_legacy=1` para quem tem `manage_options`

## Empacotamento

- `docs/archive/` nao deve ser tratado como fonte canonica
- O pacote distribuido nao deve depender de material de planejamento legado para operar
- A licenca proprietaria e os documentos canonicamente atuais devem permanecer consistentes

## Suporte

Se houver divergencia entre preview e renderer publico, considere isso regressao funcional.
Se houver necessidade de alterar licenca, protecoes ou desbloqueio, trate como fluxo juridico e do titular, nao como manutencao rotineira.
