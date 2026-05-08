# Architecture Modernization Roadmap

## Objetivo

Este documento registra a melhor direcao tecnica para evoluir o plugin
`Aireset Expresso Order` sem ficar preso a remendos locais.

O foco aqui nao e o menor caminho, e sim o melhor cenario de medio e longo prazo:

- admin realmente reativo
- API como fonte de verdade
- frontend desacoplado das regras de negocio
- estrutura modular
- melhor performance
- manutencao mais simples
- possibilidade de expor endpoints publicos com autenticacao e rate limiting

---

## Resumo Executivo

Hoje o plugin ja nao e um plugin WordPress "classico". Ele possui:

- SPA hibrida no admin
- lazy loading de views
- fluxo complementar com previews isolados
- AJAX
- REST API
- renderizacao dinamica no frontend
- varios editores visuais

O problema e que a arquitetura ainda esta em um meio-termo caro:

- parte das views ainda depende de HTML pesado renderizado no PHP
- parte dos dados vem por `admin-ajax.php`
- parte dos dados ja usa REST
- arquivos centrais ficaram grandes demais
- regras, renderizacao e persistencia ainda se misturam

### Decisao recomendada

O melhor caminho para o plugin e:

1. padronizar a camada de dados em REST
2. modularizar PHP por dominio
3. modularizar o frontend do admin por view
4. migrar gradualmente para uma SPA real em React
5. manter a migracao incremental, sem rewrite total imediato

---

## Diagnostico Atual

### Sinais de acoplamento

Arquivos muito grandes indicam mistura de responsabilidades:

- `includes/class-post-confirmation-flow.php`
- `includes/class-settings.php`
- `includes/class-public-proposal.php`
- `assets/js/admin.js`
- `assets/js/settings-admin.js`

Esses arquivos concentram simultaneamente:

- regras de negocio
- construcao de HTML
- controle de estado
- endpoints
- serializacao
- CSS dinamico
- organizacao de UX

### Sintomas operacionais

Os sintomas mais provaveis no admin sao:

- abertura lenta de algumas views
- muita logica sendo inicializada em JS mesmo fora da tela atual
- custo alto de manutencao
- dificuldade para localizar onde alterar cada recurso
- duplicacao parcial de modelos mentais entre PHP, AJAX e REST

### Conclusao do diagnostico

O gargalo principal hoje nao parece ser "framework ausente".

O gargalo principal e:

- estrutura
- excesso de acoplamento
- fronteira pouco clara entre backend, API, render e frontend

---

## Visao de Futuro

### Estado desejado

O plugin deve evoluir para um modelo em que:

- o WordPress entrega apenas o shell inicial do admin
- cada view carrega seus dados via API
- salvar nao recarrega a pagina
- previews reagem sem refresh completo
- o frontend administra seu proprio estado
- o backend expõe servicos claros e versionados

### Filosofia da aplicacao

O ideal e tratar o plugin como um produto interno com duas camadas:

#### 1. Core de dominio

Responsavel por:

- pedidos
- proposta publica
- fluxo complementar
- documentos
- configuracoes
- geracao de PDF
- politicas de acesso

#### 2. Interfaces

Responsaveis por:

- admin SPA
- frontend publico
- exportacao e integracoes
- endpoints internos e externos

---

## Recomendacao de Stack

## Backend

Continuar em:

- WordPress
- WooCommerce
- PHP

Mas com reorganizacao interna forte.

## API

Padronizar novos recursos em:

- WordPress REST API

Evitar crescimento de `admin-ajax.php` para novos fluxos.

## Frontend do admin

### Recomendacao principal

- React

### Motivos

- melhor encaixe com ecossistema WordPress moderno
- maior compatibilidade mental com Gutenberg e `@wordpress/*`
- facilidade maior para trabalhar com `apiFetch`, nonce e REST
- melhor ecossistema para SPA administrativa

### Alternativas

- Preact: boa se quiser reduzir bundle
- Vue: viavel, mas menos natural para WordPress

### Decisao recomendada

Se a equipe nao tiver uma restricao forte, usar:

- React + Vite

## Estado e dados no frontend

Recomendacao:

- TanStack Query para dados remotos
- Zustand para estado de interface local

Opcionalmente:

- React Hook Form para formularios grandes
- Zod para validacao local de payloads

---

## API-first: por que faz sentido aqui

## Beneficios

Migrar para API-first traz ganhos reais para este plugin:

- desacopla a renderizacao da persistencia
- facilita SPA real
- melhora a reutilizacao entre admin e frontend
- abre caminho para automacoes e integracoes externas
- facilita documentacao
- facilita versionamento
- facilita testes

## Casos de uso futuros viabilizados

Com API bem definida, o plugin pode expor:

- leitura de status de proposta
- consulta de fluxo complementar
- listagem de pedidos
- atualizacao de etapas
- leitura de resumo operacional
- integracoes com CRM
- integracoes com app externo
- portal do cliente desacoplado

## Requisito importante

Esses endpoints publicos so devem existir com:

- autenticacao
- autorizacao
- expiracao de tokens
- rate limiting
- trilha de auditoria

---

## Recomendacoes de Estrutura

## Estrutura atual: problema

Hoje a organizacao ainda e muito horizontal:

- `includes/` com classes grandes
- renderizacao e regra na mesma classe
- JS administrativo monolitico

## Estrutura recomendada

Uma estrutura mais saudavel para o plugin seria:

```text
includes/
  Admin/
    AdminBootstrap.php
    AdminAssets.php
    AdminViews.php
  Api/
    RestNamespace.php
    Controllers/
      OrdersRestController.php
      SettingsRestController.php
      PostConfirmationRestController.php
      ProposalRestController.php
  Domain/
    Orders/
      OrderService.php
      OrderRepository.php
      OrderFormatter.php
    Proposal/
      ProposalService.php
      ProposalRenderer.php
      ProposalTokenService.php
    PostConfirmation/
      PostConfirmationService.php
      PostConfirmationStageManager.php
      PostConfirmationPreviewBuilder.php
      PostConfirmationDocumentService.php
    Settings/
      SettingsService.php
      SettingsRepository.php
      SettingsSanitizer.php
      SettingsDefaults.php
      SettingsSchema.php
  Infrastructure/
    WooCommerce/
    Persistence/
    Security/
    Performance/
  Support/
    Helpers/
    Traits/
    ValueObjects/
templates/
  admin/
  settings/
  frontend/
assets/
  js/
    admin/
      shell/
      views/
      components/
      services/
    frontend/
  scss/
    admin/
    frontend/
```

## Principio organizacional

Separar por dominio e responsabilidade:

- dominio nao renderiza HTML
- renderer nao decide regra de negocio
- controller nao conhece detalhes de markup
- settings nao ficam espalhadas por varias classes grandes

---

## Recomendacoes especificas para o admin

## Meta

Transformar o admin atual de SPA hibrida para SPA real.

## Como deve funcionar no melhor cenario

### Shell inicial

PHP entrega somente:

- wrapper do admin
- menu lateral
- nonce
- URLs base
- configuracoes minimas de bootstrap

### Dados

Cada view busca seus dados em REST:

- pedidos
- configuracoes
- resumos
- fluxo complementar
- documentos
- PDF

### Persistencia

Salvar deve:

- enviar payload por API
- receber resposta estruturada
- atualizar estado local
- atualizar preview sem refresh da pagina

### Preview

Preview deve continuar isolado, mas com estrategia clara:

- `iframe` para isolamento de CSS quando a fidelidade visual for critica
- renderer compartilhado entre preview e pagina real
- schema de dados unico

---

## Recomendacao de migracao do admin.js

Hoje `admin.js` ja concentra:

- lazy views
- navegacao
- cache
- fluxo de pedidos
- modais
- PDF
- performance audit
- post-confirmation

No melhor cenario ele deve ser quebrado em modulos:

```text
assets/js/admin/
  bootstrap.js
  shell/navigation.js
  shell/history.js
  shell/cache.js
  views/orders/index.js
  views/orders/list.js
  views/orders/detail.js
  views/settings/index.js
  views/pdf/index.js
  views/post-confirmation/index.js
  services/http.js
  services/rest.js
  services/session-cache.js
  components/preview-frame.js
  components/accordion.js
  components/media-picker.js
  components/performance-panel.js
```

---

## REST vs admin-ajax

## Direcao recomendada

### Legado

Permitir convivio temporario com `admin-ajax.php`.

### Novo padrao

Todo novo fluxo deve nascer em REST.

### Migracao

Migrar gradualmente:

1. settings
2. pedidos
3. fluxo complementar
4. PDF

## Motivo

Misturar REST e AJAX por muito tempo aumenta:

- complexidade
- duplicacao
- custo mental
- custo de testes
- risco de divergencia de regras

---

## Publicacao de endpoints externos

## Oportunidade

Sim, faz sentido pensar em endpoints externos publicos.

Principalmente para:

- integracoes comerciais
- acompanhamento por sistema externo
- CRM
- automacoes operacionais
- portal externo do cliente

## Modelo recomendado

### Camadas de acesso

1. Endpoints internos do admin
- autenticacao WordPress
- nonce
- sessao autenticada

2. Endpoints privados para integracao
- token por aplicacao
- escopos
- rate limiting
- logs

3. Endpoints publicos limitados
- token curto
- assinatura
- expiracao
- acesso minimo

## Regras importantes

Nunca expor diretamente:

- dados sensiveis do pedido sem token forte
- update de etapa sem autorizacao
- documentos sem politica de acesso

---

## Recomendacoes de performance

## Hipoteses mais provaveis para o admin lento

1. HTML grande renderizado no servidor
2. classes PHP gigantes sendo carregadas em requests simples
3. JS monolitico inicializando logicas de views nao ativas
4. widgets pesados no admin:
   - color pickers
   - media pickers
   - Select2
   - previews
5. mistura de estrategias de carregamento

## Otimizacoes recomendadas

### Curto prazo

- continuar quebrando templates grandes
- quebrar classes grandes por dominio
- mover definicoes de schema/config para arquivos dedicados
- atrasar inicializacao de componentes por view
- reduzir trabalho executado no `document.ready`

### Medio prazo

- dados via REST por view
- cache local por sessao
- hidratar apenas a tela aberta
- previews recalculados apenas quando necessario
- lazy init de Select2, media picker e font picker

### Longo prazo

- SPA real com code splitting
- bundle por view
- cache inteligente de configuracoes
- API com payloads menores e especializados

## Metas objetivas sugeridas

### Admin

- abrir shell principal em menos de `1.2s`
- trocar de view em menos de `400ms` em cache quente
- abrir views pesadas em menos de `900ms`

### Backend

- reduzir queries por view
- reduzir montagem de HTML server-side em telas reativas
- reduzir parse de arquivos gigantes

---

## Recomendacoes para Settings

`class-settings.php` e um dos melhores candidatos para modularizacao.

## Melhor estado para essa area

Separar em:

```text
includes/Settings/
  SettingsBootstrap.php
  SettingsRepository.php
  SettingsDefaults.php
  SettingsSanitizer.php
  SettingsPageRenderer.php
  SettingsSectionRegistry.php
  VisualEditors/
    OrderLinkVisualEditor.php
    ProposalLinkVisualEditor.php
    ContractVisualEditor.php
    UploadProductsVisualEditor.php
```

## Beneficio

Isso deixa claro:

- onde defaults moram
- onde sanitizacao mora
- onde cada editor visual mora
- onde cada tela do admin e renderizada

---

## Recomendacoes para Post Confirmation

`class-post-confirmation-flow.php` deve ser fatiada.

## Melhor estado para essa area

```text
includes/PostConfirmation/
  PostConfirmationBootstrap.php
  PostConfirmationService.php
  PostConfirmationStageManager.php
  PostConfirmationRestController.php
  PostConfirmationAdminPresenter.php
  PostConfirmationFrontendRenderer.php
  PostConfirmationPreviewRenderer.php
  PostConfirmationDocumentService.php
  PostConfirmationAttachmentService.php
```

## Motivo

Hoje esse arquivo concentra:

- etapas
- REST
- AJAX
- frontend
- preview
- admin
- render

Isso encarece qualquer mudanca pequena.

---

## Recomendacoes para Proposal/Public frontend

`class-public-proposal.php` deve caminhar para um modulo de dominio + renderer.

## Melhor estado

```text
includes/Proposal/
  ProposalService.php
  ProposalTokenService.php
  ProposalRenderer.php
  ProposalPreviewRenderer.php
  ProposalRestController.php
```

---

## Estrategia de migracao recomendada

## Regra principal

Nao fazer rewrite total agora.

O melhor caminho e uma migracao por fases.

## Fase 1 - Estrutura

Objetivo:

- reduzir acoplamento
- diminuir tamanho dos arquivos
- separar HTML de classes gigantes

Acoes:

- extrair templates
- extrair schemas
- quebrar classes por dominio
- quebrar JS por view

## Fase 2 - Padronizacao de dados

Objetivo:

- parar crescimento do legado em AJAX

Acoes:

- novos fluxos apenas em REST
- adapters de compatibilidade temporarios
- documentar contratos dos endpoints

## Fase 3 - SPA real do admin

Objetivo:

- telas reativas
- sem refresh
- payloads especializados

Acoes:

- React + Vite
- shell PHP + app JS
- fetch por view
- salvar por API

## Fase 4 - Performance e DX

Objetivo:

- resposta rapida
- manutencao mais previsivel

Acoes:

- code splitting
- testes por dominio
- observabilidade
- cache local
- baseline de performance automatizado

---

## Padroes recomendados para novos recursos

Sempre que um novo recurso entrar no plugin, seguir estas regras:

1. regra de negocio em service
2. persistencia em repository/store
3. endpoint em controller REST
4. renderizacao fora da classe de regra
5. frontend por view/componente
6. nada novo grande em `class-settings.php`
7. nada novo grande em `class-post-confirmation-flow.php`
8. evitar ampliar `admin.js` monolitico

---

## Decisao recomendada final

Se fosse preciso decidir a direcao oficial do plugin hoje:

- **framework do admin:** React
- **camada de dados:** REST API
- **estrategia:** migracao incremental
- **estrutura:** modular por dominio
- **publicacao futura de integracoes:** API-first com autenticacao e limite
- **meta de manutencao:** classes pequenas, renderers separados, frontend por view

---

## Proxima acao recomendada

Se esta estrategia for adotada, a proxima etapa pratica ideal e:

1. separar `class-post-confirmation-flow.php` por dominio
2. separar `class-settings.php` em modulos de settings
3. quebrar `admin.js` por view
4. definir um primeiro pacote oficial de endpoints REST para o admin

---

## Status deste documento

Este documento representa a direcao tecnica recomendada para o melhor cenario
de evolucao do plugin no estado atual do codigo.

Ele deve ser revisado sempre que:

- uma nova camada arquitetural for adotada
- um framework for oficialmente introduzido
- a API publica do plugin for aberta para integracoes externas
