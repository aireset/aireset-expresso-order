# Aireset Plugin Excellence Standard

Documento mestre para definir como plugins Aireset devem ser planejados, estruturados, implementados, documentados e mantidos.

Este arquivo existe para virar referencia operacional de agentes, desenvolvedores e revisores tecnicos em plugins novos e em evolucao.

Use este documento como padrao-base para:

- novos plugins Aireset
- refatoracoes relevantes em plugins existentes
- definicao de arquitetura admin e frontend
- padronizacao de nomenclatura, pastas, endpoints e assets
- criacao de instrucoes para agentes e copilots

## 1. Objetivo do padrao

O objetivo deste padrao e fazer com que plugins Aireset tenham:

- identidade visual coerente entre produtos
- arquitetura previsivel
- backend WordPress/WooCommerce seguro e performatico
- frontend administrativo rapido e desacoplado
- estrutura de pastas clara
- naming consistente
- documentacao suficiente para manutencao e para agentes trabalharem com autonomia
- um nivel de qualidade que permita replicar o mesmo modelo em varios plugins

Este nao e um documento de teoria. Ele deve orientar implementacao real.

## 2. Regra principal de decisao arquitetural

### 2.1 Plugins simples

Se o plugin tiver apenas:

- 1 ou 2 telas simples de configuracao
- poucas interacoes dinamicas
- nenhum fluxo rico de listagem, filtros, dashboards, cards, edicao em massa ou preview pesado

entao o padrao recomendado e:

- PHP server-rendered
- JS progressivo e modular
- sem SPA completa

### 2.2 Plugins administrativos ricos

Se o plugin tiver qualquer combinacao de:

- varias views administrativas
- navegacao lateral ou shell interno
- listagens com filtros, cache, cards, paines e dashboards
- fluxos de edicao ricos
- previews, PDFs, configuradores visuais, assistentes ou modulos complexos

o padrao recomendado para plugins novos e:

- admin com SPA real
- React como stack padrao recomendada
- API-first no backend
- assets compilados e enfileirados apenas na rota necessaria

### 2.3 Recomendacao oficial Aireset

Para plugins novos e estrategicos, o padrao preferencial passa a ser:

- React
- TypeScript
- Vite
- React Router
- TanStack Query
- camada de servicos para REST API do WordPress

Vue pode ser usado por excecao, mas React e a escolha padrao recomendada para criar base compartilhada entre plugins.

## 3. Principios nao negociaveis

1. O plugin precisa parecer um produto profissional, nao um acumulado de telas WordPress.
2. O backend precisa ser seguro, validado e previsivel.
3. O frontend nao pode depender de recarga completa de pagina para fluxos ricos.
4. O shell administrativo Aireset precisa ser consistente entre plugins.
5. O plugin deve carregar apenas o que a tela atual precisa.
6. Nao se filtra grande volume de dados em PHP depois de carregar tudo do banco.
7. Nao se gera arquivo pesado ou preview caro em request interativo sem necessidade.
8. Toda feature nova precisa nascer com convencao de nome, documentacao minima e estrategia de manutencao.
9. O plugin deve respeitar WordPress, WooCommerce, HPOS, nonces, capabilities e i18n.
10. O padrao deve ser bom para humanos e bom para agentes.

## 4. Branding de referencia Aireset

O branding Aireset tem duas camadas:

- camada compartilhada de shell administrativo
- camada de identidade do produto

### 4.1 Shell administrativo compartilhado

Esta e a linguagem comum entre plugins administrativos Aireset.

#### Paleta-base do admin shell

- navy profundo principal: #0f2557
- navy profundo forte: #091533
- teal de destaque principal: #32d1c7
- teal de compatibilidade ja usado em partes do checkout: #1da7a1
- fundo claro administrativo: #eef3f8
- card claro administrativo: #fbfcfe
- borda clara administrativa: #d6deea
- texto administrativo principal: #111827
- texto administrativo secundario: #425466
- texto administrativo suave: #6b7a90

#### Gradientes oficiais do shell

- sidebar: linear-gradient(180deg, rgba(9, 21, 51, 0.98) 0%, rgba(15, 37, 87, 0.98) 100%)
- item ativo principal: linear-gradient(135deg, rgba(21, 50, 103, 0.95) 0%, rgba(22, 91, 121, 0.9) 100%)

#### Regras de shell

- sidebar sempre escura, com gradiente navy
- item ativo principal com gradiente navy + teal
- submenu com destaque lateral teal no hover e no ativo
- cards internos podem variar por produto, mas o shell e compartilhado
- o hero pode variar por produto
- o shell nao deve destruir o design interno de modulos ja validados

### 4.2 Identidade do produto

Cada plugin pode ter sua propria camada visual para frontend ou areas especiais.

Exemplo atual de tokens do Expresso Order:

- product primary: #00034b
- product primary strong: #050a76
- product accent: #3f66ff
- product surface soft: #f4f6ff
- product border: #dbe2ff
- product text: #11162f
- product text soft: #55607f
- success: #0f9f6e
- danger: #d6336c
- warning: #f59e0b

### 4.3 Tipografia

- admin shell pode usar stack segura de interface e herdar do ambiente quando necessario
- hero e frontend de produto podem usar tipografia propria
- a tipografia escolhida para um plugin deve ser documentada em arquivo de brand do produto
- nao usar fonte aleatoria sem documentar o motivo

### 4.4 Motion

- transicoes curtas e funcionais
- hover com elevacao leve
- sem excesso de animacao decorativa no admin
- loading, skeleton e estados de fetch devem ser discretos e claros

## 5. Stack recomendada por tipo de plugin

## 5.1 Backend

Padrao obrigatorio:

- PHP 8+
- WordPress API nativa
- WooCommerce API nativa quando aplicavel
- HPOS compatibility quando lidar com pedidos
- REST API para fluxos modernos
- AJAX apenas para casos legados ou interacoes pontuais de baixo escopo

Padrao preferencial:

- arquitetura em servicos e modulos
- classes pequenas e responsaveis por um dominio claro
- repositories ou gateways quando a leitura/gravação ficar complexa

## 5.2 Frontend admin de plugin novo

Padrao recomendado:

- React
- TypeScript
- Vite
- CSS modular ou SCSS modular
- build para dist
- consumo de REST API autenticada com nonce

## 5.3 Frontend publico

Escolha por complexidade:

- paginas simples: PHP + CSS + JS modular
- experiencias ricas: React ou frontend isolado compilado

## 5.4 Quando nao usar SPA

Nao usar SPA completa quando:

- a tela e apenas um form simples
- o ganho de UX nao justifica build pipeline e API dedicada
- a manutencao ficaria pior do que o beneficio

## 6. Manifesto de identidade obrigatorio por plugin

Todo plugin novo deve comecar com um bloco de identidade definido logo no inicio do projeto.

Cada plugin precisa declarar:

- nome do produto
- slug do plugin
- text domain
- namespace PHP
- prefixo curto do codigo
- prefixo CSS
- prefixo JS global
- capability principal
- tipo do plugin
- modulo de licenca
- estrategia admin
- estrategia frontend

### 6.1 Exemplo de manifesto

```md
Produto: Aireset Expresso Order
Slug: aireset-expresso-order
Text Domain: aireset-expresso-order
Namespace: Aireset\ExpressoOrder
Code Prefix: aeo
CSS Prefix: aeo-
JS Global: aeoVars
Capability principal: edit_shop_orders
Tipo: WooCommerce admin-rich plugin
Admin strategy: React SPA
Frontend strategy: PHP + JS modular
Licensing: Elite Licenser adapter
```

Este manifesto deve existir em um arquivo proprio dentro de docs.

## 7. Nomenclatura padrao

## 7.1 Slug do plugin

Preferencial:

- aireset-nome-do-produto

Evitar:

- slugs genericos demais
- slugs sem a marca Aireset em plugins proprietarios da marca

## 7.2 Namespace PHP

Padrao recomendado para plugins novos:

- Aireset\Produto

Exemplos:

- Aireset\ExpressoOrder
- Aireset\Checkout
- Aireset\FreteBeneficio

## 7.3 Prefixo curto do codigo

Todo plugin precisa de um prefixo curto e estavel para:

- handles de asset
- globals JS
- meta keys
- option keys
- transients
- action names

Padrao recomendado:

- 3 a 5 letras, derivadas do produto

Exemplos:

- aeo para Aireset Expresso Order
- ach para Aireset Checkout
- afb para Aireset Frete Beneficio

### Regra importante

Plugins legados podem manter prefixos historicos como EOP e CWMP.
Plugins novos nao devem criar novos prefixos opacos sem relacao com o produto.

## 7.4 Classes PHP

Padrao preferido em plugin novo:

- namespace + classe com nome claro

Exemplo:

```php
namespace Aireset\ExpressoOrder\Admin;

final class OrdersPageController {}
```

Para plugins legados sem namespace:

- class-aeo-orders-page.php
- class-aeo-settings-page.php
- class-aeo-rest-orders-controller.php

## 7.5 Funcoes PHP globais

Se uma funcao global for inevitavel, use:

- aireset_<produto>_<acao>

Exemplo:

- aireset_expresso_order_activate
- aireset_checkout_boot

## 7.6 Meta keys

Padrao:

- _<prefix>_<nome>

Exemplos:

- _aeo_public_token
- _aeo_created_by
- _afb_shipping_snapshot

## 7.7 Option keys

Padrao:

- <slug>_settings
- <slug>_<modulo>

Exemplos:

- aireset_expresso_order_settings
- aireset_checkout_messages

## 7.8 REST namespace

Padrao:

- <plugin-slug>/v1

Exemplos:

- aireset-expresso-order/v1
- aireset-checkout/v1

## 7.9 AJAX action names

Padrao:

- <prefix>_<acao>

Exemplos:

- aeo_load_orders
- aeo_load_order
- ach_preview_template

## 7.10 JS globals

Padrao:

- <prefix>Vars

Exemplos:

- aeoVars
- achVars

## 7.11 CSS class prefix

Todo modulo publico precisa de prefixo proprio.

Padrao:

- <prefix>-

Exemplos:

- aeo-
- ach-

Para componentes compartilhados de design system Aireset, usar:

- as-

Exemplos:

- as-shell
- as-card
- as-toolbar
- as-empty-state

## 8. Estrutura de pastas ideal

## 8.1 Estrutura ideal para plugin novo e rico

```text
plugin-root/
  .github/
    copilot-instructions.md
  docs/
    PLUGIN_IDENTITY.md
    ARCHITECTURE.md
    ADMIN_UI_BRAND.md
    API_CONTRACTS.md
    PERFORMANCE_PLAN.md
    RELEASE_PROCESS.md
  assets/
    src/
      admin/
        app/
        components/
        modules/
        router/
        services/
        stores/
        styles/
      frontend/
        app/
        components/
        modules/
        services/
        styles/
      shared/
        ui/
        hooks/
        utils/
    dist/
      admin/
      frontend/
  src/
    Admin/
    Api/
    Application/
    Domain/
    Infrastructure/
    Integrations/
    Modules/
    Support/
  templates/
    admin/
    frontend/
    emails/
  languages/
  scripts/
  tests/
    php/
    js/
    e2e/
  vendor/
  CHANGELOG.md
  README.md
  readme.txt
  package.json
  composer.json
  plugin-main.php
```

## 8.2 Estrutura de compatibilidade para plugin legado Aireset

Se o plugin ja nasceu em estrutura legacy, pode seguir com esta organizacao, mas com disciplina:

```text
plugin-root/
  .github/
    copilot-instructions.md
  docs/
  assets/
    css/
    js/
    scss/
    images/
  backend/
  frontend/
  includes/
  templates/
  languages/
  scripts/
  CHANGELOG.md
  README.md
  package.json
  plugin-main.php
```

### Regra

Plugins novos devem preferir src/.
Plugins existentes podem manter includes/ ou backend/, mas nao devem continuar misturando responsabilidade sem criterio.

## 9. Organizacao de codigo backend

## 9.1 Bootstrap

O bootstrap principal deve:

- declarar cabecalho do plugin
- definir constantes minimas
- carregar autoload ou requires centrais
- registrar activation/deactivation
- bootar modulos
- sair cedo se dependencias obrigatorias nao existirem

O bootstrap nao deve:

- conter regra de negocio extensa
- renderizar tela diretamente
- concentrar dezenas de hooks sem organizacao

## 9.2 Camadas recomendadas

### Admin

Responsavel por:

- menus
- enqueue
- shell admin
- controllers de views administrativas

### Api

Responsavel por:

- REST controllers
- schema de request e response
- permission callbacks

### Application

Responsavel por:

- orquestracao de casos de uso
- servicos de aplicacao

### Domain

Responsavel por:

- regras de negocio puras
- entidades, estados, validadores e politicas do dominio

### Infrastructure

Responsavel por:

- acesso a WP options
- acesso a order meta
- arquivos
- cache
- wrappers de integracao com WP/WC

### Integrations

Responsavel por:

- licenciamento
- gateways externos
- webhooks
- Elementor
- APIs externas

### Support

Responsavel por:

- helpers pequenos
- serializers
- value objects compartilhados
- logger

## 9.3 Regras de backend

- nao espalhar get_option por todo o plugin
- nao espalhar get_post_meta ou order->get_meta sem centralizacao minima
- toda leitura complexa deve poder ser memoizada por request
- toda escrita relevante deve passar por uma camada clara de validacao
- toda resposta REST deve ter contrato previsivel

## 10. Padrao do frontend administrativo

## 10.1 Novo padrao recomendado

Para plugins novos com admin rico, usar SPA real.

### Stack padrao

- React
- TypeScript
- Vite
- React Router
- TanStack Query
- componente de UI interno Aireset

## 10.2 Principios da SPA administrativa

- o shell deve carregar rapido
- cada modulo entra sob demanda
- a rota deve refletir a view ativa
- o estado remoto deve ter cache controlado
- o plugin nao pode depender de HTML gigante do servidor para cada troca de view
- filtros e tabelas nao devem refazer request desnecessariamente

## 10.3 Design system administrativo

Criar um conjunto compartilhado Aireset com componentes como:

- Shell
- Sidebar
- Header de modulo
- Card
- Stat card
- Empty state
- Toolbar
- Filtros
- Table surface
- Save bar sticky
- Drawer
- Modal
- Tabs
- Form grid
- Notice inline

## 10.4 Regras de SPA admin

- nunca carregar a SPA inteira em todas as paginas do wp-admin
- enfileirar build apenas na tela do plugin
- sempre injetar nonce, ajax_url, rest_url e capacidade do usuario no bootstrap inicial
- separar shell, modulos e previews pesados
- toda tela com fetch pesado precisa de skeleton ou loading real

## 10.5 Quando o admin for legacy

Se o plugin ainda estiver em jQuery/PHP:

- modularizar JS por dominio
- usar lazy load por view
- reduzir assets globais
- usar endpoints pequenos
- nao renderizar toda pagina por AJAX se so um fragmento basta

## 11. Padrao do frontend publico

## 11.1 Direcao geral

Frontend publico precisa parecer produto, nao tela improvisada.

### Regras

- tokens visuais definidos em um unico lugar
- fontes e paleta documentadas
- estados de loading, empty e erro previstos
- responsividade obrigatoria
- acessibilidade minima obrigatoria
- evitar markup gerado com echo massivo quando um template HTML legivel resolve

## 11.2 Escolha de stack

- simples: PHP + SCSS + JS modular
- rico: React app compilado para o frontend do plugin

## 12. Padrao de assets

## 12.1 Regras gerais

- todo asset deve ter dono claro
- nao enfileirar biblioteca global por comodidade
- cada view carrega o minimo necessario

## 12.2 Estrategia recomendada

- shared admin shell separado dos assets de modulo
- modulo de PDF separado do resto
- settings separado do resto
- frontend separado do admin
- build com hash ou versionamento pelo plugin version

## 12.3 Regra critica de performance

Nao fazer isto:

- carregar media picker, color picker, editor e PDF assets em todas as rotas
- gerar PDF em request de abertura de tela
- renderizar HTML completo do admin quando a tela so precisa de JSON
- filtrar 500 pedidos em memoria depois de trazer tudo

## 13. Dados, banco e indexacao

## 13.1 Regra de modelagem

Antes de criar tabela customizada, responder:

- esse dado precisa de leitura frequente e estruturada?
- esse dado sera filtrado, ordenado ou agregado?
- option/meta ja resolve sem virar gargalo?

## 13.2 Quando usar option/meta

Use option/meta quando:

- o volume e baixo
- a leitura e pontual
- nao ha necessidade analitica forte

## 13.3 Quando usar tabela customizada

Use tabela propria quando:

- o volume sera alto
- ha filtros frequentes
- ha listagens com paginação real
- ha status, datas, relacionamentos ou agregacoes pesadas

## 13.4 Regras de indexacao

Toda tabela customizada deve ter:

- primary key clara
- timestamps
- indexes de lookup real
- indexes compostos para filtros principais
- nomenclatura legivel

Exemplo de perguntas obrigatorias:

- como a listagem sera filtrada?
- por qual coluna sera ordenada?
- qual lookup ocorre em 80 por cento dos requests?

## 13.5 Regras de leitura performatica

- nao filtrar grandes colecoes em PHP depois do fetch
- espelhar flags criticas em meta ou coluna indexavel
- nao depender de JSON serializado para filtros principais
- usar cache intra-request para dados repetidos
- usar transient ou object cache para dados derivados caros

## 13.6 Regras aprendidas no Expresso Order

- filtros de listagem devem ser empurrados para wc_get_orders com meta_query indexada
- payload de edicao nao deve gerar PDFs de assinatura de forma sincronica
- views pesadas devem carregar so o necessario

## 14. Contratos de API

## 14.1 REST primeiro em plugins novos

Plugins novos com admin rico devem expor REST routes versionadas.

Toda rota deve definir:

- schema de entrada
- schema de saida
- permission callback
- erros esperados
- status HTTP correto

## 14.2 Estrutura de resposta

Padrao recomendado:

```json
{
  "success": true,
  "data": {},
  "meta": {},
  "errors": []
}
```

Ou, para REST puro do WP:

- objeto claro de dominio
- sem mistura arbitraria de HTML e dados

## 14.3 AJAX

Usar AJAX apenas quando:

- a integracao for legada
- a acao for extremamente pequena e local
- nao justificar rota REST completa

## 15. Seguranca

Todo plugin Aireset deve cumprir:

- current_user_can em toda tela admin e acao sensivel
- nonce em toda acao admin, AJAX e forms
- sanitize na entrada
- escape na saida
- validacao de permissao em REST permission_callback
- erros estruturados e explicitos
- nenhuma confianca cega em option, meta, request ou API externa

## 15.1 Regras de sanitizacao

- texto simples: sanitize_text_field
- textarea: sanitize_textarea_field
- email: sanitize_email
- URL de entrada: esc_url_raw
- HTML permitido: wp_kses_post
- IDs: absint
- valores numericos: cast explicito

## 15.2 Dados sensiveis

- tokens nao devem aparecer em HTML sem necessidade
- logs nao devem expor segredos
- licenciamento deve ficar isolado em modulo proprio

## 16. WooCommerce e WordPress

## 16.1 Compatibilidade obrigatoria

- HPOS quando o plugin mexer com pedidos
- text domain consistente
- readme.txt minimamente correto
- uninstall ou cleanup quando aplicavel

## 16.2 Regras de pedidos

- nao quebrar contractos do WooCommerce
- meta de pedido com prefixo do plugin
- filtros de pedido por created_via quando o plugin criar pedidos proprios
- acesso a order meta centralizado quando houver logica rica

## 17. Licenciamento, updates e telemetria

Todo plugin Aireset deve tratar isso como modulo separado.

Padrao recomendado:

- Integrations/Licensing
- Integrations/Updates
- Integrations/Telemetry

Regras:

- UI de licenca separada do core de negocio
- update checker separado do dominio principal
- telemetria opcional e documentada
- logs de licenca nao podem quebrar o plugin inteiro

## 18. Performance como requisito de arquitetura

## 18.1 Regra geral

Toda feature deve responder:

- o que carrega no bootstrap?
- o que pode ser lazy?
- o que pode ser cacheado?
- qual query pode crescer mal?

## 18.2 Regras obrigatorias

- assets por contexto
- lazy load real por view
- endpoints pequenos
- cache de cliente em SPA
- memoizacao intra-request
- background jobs para tarefa pesada

## 18.3 O que vai para background

Sempre considerar Action Scheduler ou fila quando houver:

- geracao de PDF
- exportacao
- sync externo
- recalculo pesado
- processamento de anexos

## 18.4 Observabilidade minima

Plugins administrativos ricos devem ter, no minimo em modo auditoria:

- tempo PHP por request pesado
- numero de queries
- memoria pico
- tamanho da resposta
- tempo de abertura por view

## 19. UX e acessibilidade

## 19.1 Admin

- navegacao previsivel
- foco visivel
- labels claras
- atalhos so quando fizer sentido
- empty states explicativos
- notices nao poluidos

## 19.2 Publico

- hierarquia visual clara
- CTA principal univoco
- responsivo de verdade
- sem formularios confusos

## 19.3 Acessibilidade minima

- contraste suficiente
- aria-label quando necessario
- navegacao por teclado em componentes customizados
- semantic HTML correto

## 20. Testing e validacao

## 20.1 Minimo obrigatorio

- php -l em todo PHP alterado
- smoke test manual do fluxo principal
- validacao de capability e nonce nas acoes novas

## 20.2 Recomendado para plugin novo com SPA

- testes unitarios da camada de dominio
- testes de servicos
- testes e2e dos fluxos principais
- testes de contrato de API

## 20.3 O que validar antes de release

- install/update
- permissao de acesso
- telas admin principais
- save/load de settings
- fluxo de licenca
- rotas REST/AJAX principais
- performance de telas criticas

## 21. Release e versionamento

Todo plugin Aireset deve ter:

- CHANGELOG.md
- versao no bootstrap principal
- versao no package.json quando houver build frontend

Regras:

- bump de versao sempre no fim da tarefa
- changelog com entrada objetiva
- release precisa citar impacto funcional e tecnico

## 22. Documentacao minima obrigatoria em cada plugin

Cada plugin deveria conter estes arquivos:

- docs/PLUGIN_IDENTITY.md
- docs/ARCHITECTURE.md
- docs/ADMIN_UI_BRAND.md
- docs/API_CONTRACTS.md
- docs/PERFORMANCE_PLAN.md
- docs/RELEASE_PROCESS.md
- .github/copilot-instructions.md

## 22.1 O que cada arquivo deve responder

### PLUGIN_IDENTITY.md

- quem e o produto
- slug
- namespace
- prefixos
- capabilities
- licenca

### ARCHITECTURE.md

- pastas
- modulos
- fluxos principais
- dependencias externas

### ADMIN_UI_BRAND.md

- shell
- cores
- tipografia
- componentes base
- limites de variacao

### API_CONTRACTS.md

- rotas
- payloads
- auth
- erros

### PERFORMANCE_PLAN.md

- gargalos esperados
- hotspots
- estrategia de cache

### RELEASE_PROCESS.md

- como buildar
- como validar
- como versionar

## 23. Como os agentes devem trabalhar neste padrao

Todo agente que entrar em um plugin Aireset deve, antes de alterar algo relevante:

1. ler o manifesto de identidade do plugin
2. ler as instrucoes de copilot do plugin
3. ler docs/ARCHITECTURE.md
4. ler docs/ADMIN_UI_BRAND.md quando tocar UI
5. ler docs/API_CONTRACTS.md quando tocar backend de dados
6. preservar naming, prefixos e contratos existentes
7. evitar reescrita total quando a mudanca viavel for menor

## 23.1 Regra de ouro para agentes

Se o plugin e legado:

- respeitar a arquitetura atual
- melhorar modularidade sem quebrar o que existe

Se o plugin e novo:

- iniciar ja no padrao recomendado

## 24. Padrao ideal de plugin Aireset novo

Se hoje um plugin Aireset novo fosse criado do jeito ideal, a receita seria:

- backend WordPress/WooCommerce em PHP 8+
- src/ com modulos claros
- admin em React SPA real
- TypeScript
- Vite
- REST API versionada
- assets por contexto
- shell admin compartilhado Aireset
- tokens visuais documentados
- docs minimos obrigatorios
- licenciamento isolado
- telemetria isolada
- Action Scheduler para trabalho pesado
- testes basicos e e2e dos fluxos centrais

## 25. Padrao ideal de plugin Aireset legado em evolucao

Se o plugin ja existe e nao sera reescrito agora, a meta e:

- reduzir acoplamento
- modularizar JS
- modularizar regras de negocio
- empurrar filtros para o banco
- reduzir HTML pesado
- separar assets por tela
- criar docs que preparem migracao futura

## 26. Anti-padroes proibidos

- um unico arquivo PHP gigante controlando tudo
- get_option espalhado sem controle
- listagens filtradas em memoria apos carregar tudo
- HTML admin inteiro retornado por AJAX quando basta JSON
- assets globais em todo wp-admin
- endpoint sem nonce ou permission check
- prefixos sem padrao
- classes CSS sem namespacing
- plugin sem changelog
- plugin sem documentacao minima
- plugin novo complexo sem estrategia clara de frontend

## 27. Checklist de excelencia para iniciar plugin novo

1. Definir manifesto de identidade.
2. Definir stack e justificar se sera SPA ou nao.
3. Criar estrutura de pastas padrao.
4. Criar docs minimos obrigatorios.
5. Definir tokens visuais do produto em cima do shell Aireset.
6. Definir namespace, prefixo curto e meta keys.
7. Definir contratos de API antes de crescer UI.
8. Definir estrategia de performance antes de codar modulos pesados.
9. Definir estrategia de licenca, update e observabilidade.
10. So depois iniciar implementacao funcional.

## 28. Decisao oficial recomendada

### Para plugins novos Aireset

O padrao recomendado e:

- React SPA no admin para plugins ricos
- backend PHP bem modularizado
- REST API como contrato principal

### Para plugins atuais Aireset

O padrao recomendado e:

- otimizar o que existe
- modularizar
- preparar migracao por dominio
- evitar big bang rewrite sem necessidade

## 29. Conclusao

Excelencia em plugin Aireset nao significa apenas ter uma interface bonita.

Significa juntar:

- identidade visual coerente
- arquitetura previsivel
- backend seguro
- frontend rapido
- pasta organizada
- naming consistente
- contratos claros
- documentacao suficiente
- performance pensada desde o inicio

Se este documento for seguido, os proximos plugins Aireset deixam de nascer como projetos isolados e passam a nascer como parte de uma plataforma com padrao.

Esse e o objetivo real.
