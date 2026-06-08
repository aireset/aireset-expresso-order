# Plan: Performance Optimization Roadmap

Plano de referencia para otimizar o Aireset Expresso Order no futuro, sem alterar a arquitetura agora. O objetivo e reduzir tempo de abertura das telas administrativas, tornar a navegacao percebida quase instantanea e baixar o custo de renderizacao do modulo PDF, preservando o shell SPA atual em jQuery e PHP.

## Objetivos

- reduzir o tempo de abertura da tela principal do admin
- reduzir o tempo de troca entre views da SPA
- reduzir fortemente o tempo de troca entre abas do PDF
- minimizar recargas completas de HTML administrativo
- carregar apenas assets e componentes realmente necessarios por tela
- manter compatibilidade com WordPress, WooCommerce, licenciamento e rotas atuais
- preparar base para evolucao futura sem obrigar migracao imediata para framework JS

## Stack atual confirmada

- admin em SPA propria baseada em PHP + jQuery
- estilos compilados via SCSS, sem build moderno de JavaScript
- renderizacao principal feita no servidor, com templates PHP embutidos na SPA
- modulo PDF tambem renderizado no servidor
- AJAX e REST usados em pontos especificos de busca, pedidos, frete e fluxo complementar
- sem Vue, sem React e sem Vite no estado atual

## Sintomas atuais

- algumas views administrativas ainda carregam HTML demais antes de o usuario precisar
- o modulo PDF concentra alto custo de renderizacao e de assets
- existem requests repetidos para dados que poderiam ser cacheados
- o admin carrega mais CSS e JS do que o necessario em varias rotas
- parte da lentidao percebida vem de renderizacao PHP pesada, nao apenas de JavaScript

## Gargalos mapeados

### 1. Bootstrap administrativo pesado

O shell principal ainda enfileira CSS e JS de multiplos dominios. Isso aumenta o custo da primeira abertura, mesmo quando o usuario nao vai usar PDF, configuracoes ou componentes de media picker.

### 2. Views pesadas renderizadas no servidor

O plugin ainda depende de templates PHP com markup extenso para areas de configuracao e PDF. Isso aumenta tempo de resposta e tamanho do HTML.

### 3. Modulo PDF caro por natureza

O PDF mistura configuracoes, preview, listas auxiliares, toolbar e dados derivados. Isso faz cada navegacao ou troca de contexto custar mais do que o restante da SPA.

### 4. Assets globais demais

Color picker, media frame, estilos de configuracao e estilos do PDF nem sempre precisariam estar disponiveis em todas as views.

### 5. Cache insuficiente por camada

Parte dos dados ja pode ser reaproveitada no cliente, mas a maior oportunidade ainda esta em cachear no servidor trechos caros e dados repetidos.

### 6. Falta de modularizacao de inicializacao

O JavaScript administrativo ainda concentra comportamento de multiplas areas. Isso dificulta carga sob demanda e faz a pagina inicializar mais logica do que deveria.

## Principios de otimizacao

1. Preferir a menor mudanca viavel que reduza custo real.
2. Otimizar primeiro o que pesa mais: PDF, assets globais e renderizacao server-side.
3. Medir antes e depois de cada lote.
4. Evitar reescrita em framework sem esgotar ganhos estruturais do desenho atual.
5. Separar custo de rede, custo de PHP, custo de banco e custo de DOM.

## Tecnicas recomendadas

### A. Carga condicional de assets

Carregar scripts e estilos por contexto, nao globalmente.

Aplicacoes no plugin:
- carregar assets de configuracao apenas em views que usam Coloris, media picker e formularios administrativos
- carregar assets do PDF apenas quando a view PDF estiver ativa
- evitar CSS de frontend em rotas do admin que nao o utilizam
- separar inicializadores de Operacao, Pedidos, Configuracoes e PDF

Beneficio esperado:
- melhora da abertura inicial do admin
- menos parse e menos execucao no navegador

### B. Lazy load real por view e por bloco

Em vez de renderizar tudo no bootstrap, carregar somente a view ativa e, dentro dela, os blocos caros sob demanda.

Aplicacoes no plugin:
- manter placeholders leves na SPA
- carregar configuracoes apenas ao abrir cada view
- no PDF, separar shell, formulario, preview e blocos auxiliares
- carregar preview do PDF apenas quando o painel de preview for aberto

Beneficio esperado:
- menor tempo para primeiro paint util
- reducao forte do custo percebido em telas pesadas

### C. Endpoints dedicados e respostas menores

Quando possivel, retornar JSON ou fragmentos especificos em vez de paginas completas.

Aplicacoes no plugin:
- endpoints dedicados para abas do PDF
- endpoints separados para preview, listas auxiliares e metadados do PDF
- respostas menores para listagem e edicao de pedidos
- separar dados de apoio do HTML principal

Beneficio esperado:
- menos bytes trafegados
- menos trabalho de parse HTML
- menos custo de renderizacao do PHP por request

### D. Cache no cliente

Usar sessionStorage, localStorage e cache em memoria para evitar roundtrips desnecessarios.

Aplicacoes no plugin:
- cache de views da SPA dentro da sessao
- cache das abas do PDF
- cache da listagem de pedidos por pagina/filtro
- cache do payload de edicao de pedido
- persistencia local de rascunho do novo pedido
- cache do fluxo complementar por pedido

Beneficio esperado:
- navegacao muito mais rapida em ida e volta
- melhor experiencia em telas revisitadas na mesma sessao

### E. Cache no servidor

Mover parte do ganho para transients, object cache e memoizacao de trechos caros.

Aplicacoes no plugin:
- cache de listas recentes usadas pelo PDF
- cache do HTML ou dos dados de preview quando parametros nao mudarem
- cache de configuracoes derivadas que hoje sao recalculadas toda hora
- cache de partes de documentacao interna do PDF
- cache de consultas repetidas a pedidos e metadados auxiliares

Beneficio esperado:
- menor tempo de resposta do PHP
- menos consultas repetidas ao banco

### F. Reducao de queries e trabalho no banco

Revisar consultas repetidas, carregamento de pedidos e acesso a meta dados.

Aplicacoes no plugin:
- reduzir buscas repetidas do mesmo pedido na mesma request
- centralizar e memoizar metadados do pedido durante edicao
- rever queries usadas em listagem, preview e documentos auxiliares
- limitar mais agressivamente listas do PDF quando nao forem essenciais

Beneficio esperado:
- menor latencia do backend
- menos variacao de desempenho com volume de dados

### G. Modularizacao do JavaScript

Fatiar o admin por dominio funcional.

Aplicacoes no plugin:
- modulo Operacao
- modulo Pedidos
- modulo Configuracoes
- modulo PDF
- modulo componentes compartilhados

Beneficio esperado:
- inicializacao menor por tela
- manutencao mais simples
- base melhor para lazy load real no futuro

### H. Prefetch estrategico

Antecipar a carga do proximo recurso mais provavel, sem exagerar.

Aplicacoes no plugin:
- prefetch da proxima aba do PDF mais usada
- prefetch da view de Pedidos ao abrir Novo pedido, se ocioso
- prefetch da edicao do pedido quando o usuario pairar ou focar um card de pedido

Beneficio esperado:
- experiencia percebida quase instantanea em navegacoes frequentes

### I. Virtualizacao e reducao de DOM

Evitar DOM grande demais quando houver listas ou grids extensas.

Aplicacoes no plugin:
- limitar quantidade de cards simultaneos na listagem de pedidos
- paginacao mais enxuta
- render parcial de listas auxiliares do PDF

Beneficio esperado:
- menos custo de layout e repaint

### J. Observabilidade de performance

Sem medicao, o plugin pode parecer rapido em um fluxo e continuar lento em outro.

Aplicacoes no plugin:
- instrumentar tempo de abertura por view
- logar tempo medio do PDF por aba
- medir tamanho das respostas AJAX principais
- registrar numero de queries e memoria em rotas administrativas pesadas durante auditoria

Beneficio esperado:
- decisao tecnica guiada por dado real

## Roadmap sugerido

### Fase 1. Auditoria e baseline

Objetivo:
estabelecer medicao real antes de otimizar.

Escopo:
- medir tempo da abertura inicial da SPA
- medir tempo de entrada na view PDF
- medir tempo de troca entre abas do PDF
- medir tempo de listagem e edicao de pedidos
- medir peso dos assets por rota
- medir principais requests PHP/AJAX/REST

Entregavel:
uma tabela baseline com tempo, tamanho de resposta e custo estimado por view.

### Fase 2. Quick wins estruturais

Objetivo:
reduzir o custo da abertura sem reescrever arquitetura.

Escopo:
- carga condicional de assets por view
- lazy load de views administrativas nao iniciais
- limpeza de inicializadores globais
- debounce de filtros e campos que disparam requests
- reaproveitamento de cache do cliente em navegacao basica

Entregavel:
admin sensivelmente mais rapido na primeira abertura e na troca entre views comuns.

### Fase 3. Otimizacao dedicada do PDF

Objetivo:
atacar o pior gargalo do plugin.

Escopo:
- separar shell, formulario e preview do PDF
- endpoint dedicado por aba e por preview
- evitar carregar preview pesado no bootstrap
- cachear dados auxiliares do PDF no servidor
- reduzir listas e consultas acessorias do modulo

Entregavel:
abas do PDF com abertura muito mais rapida e previsivel.

### Fase 4. Cache de backend e consultas

Objetivo:
baixar custo de CPU, banco e renderizacao.

Escopo:
- transients e object cache onde fizer sentido
- memoizacao intra-request
- consolidacao de consultas repetidas
- normalizacao de acessos a options e metadados

Entregavel:
tempo de resposta do servidor menor e mais estavel.

### Fase 5. Modularizacao profunda do admin

Objetivo:
preparar a base para crescimento sem degradacao.

Escopo:
- dividir admin.js por dominio
- dividir inicializacao por view
- isolar componentes compartilhados
- preparar ponto de entrada futuro para build JS moderno, se necessario

Entregavel:
base mais limpa, com menor custo por tela e manutencao mais simples.

### Fase 6. Decisao arquitetural futura

Objetivo:
decidir com dados se ainda vale migrar para framework moderno.

Possibilidades:
- manter SPA atual em jQuery + PHP, se o desempenho ficar suficiente
- adotar build JS moderno sem framework, apenas com modulos
- migrar gradualmente para Vue ou React se houver necessidade real de complexidade cliente-side

Regra:
nao migrar para Vue apenas por intuicao; decidir depois de medir o ganho obtido nas fases anteriores.

## Ordem de prioridade recomendada

1. medir e estabelecer baseline
2. cortar assets globais e inicializacao desnecessaria
3. otimizar o modulo PDF
4. cachear backend e reduzir queries caras
5. modularizar JS e preparar evolucao futura
6. reavaliar necessidade de framework

## O que nao fazer primeiro

- nao comecar por reescrita total para Vue
- nao tentar otimizar CSS antes de atacar renderizacao e requests
- nao mexer em tudo ao mesmo tempo sem baseline
- nao criar duas arquiteturas permanentes concorrentes para o mesmo fluxo
- nao introduzir dependencias novas sem necessidade medida

## Medidas de sucesso

As metas exatas devem ser definidas apos a baseline, mas a referencia alvo pode ser:

- abertura inicial do admin significativamente menor que o estado atual
- troca de views comuns em menos de 1 segundo em ambiente normal
- troca de abas do PDF em faixa proxima de instantanea do ponto de vista do usuario
- reabertura de telas visitadas na mesma sessao praticamente imediata
- menor variacao de tempo conforme cresce o volume de pedidos

## Riscos e cuidados

- invalidacao de cache mal feita pode mostrar dado velho
- otimizar HTML sem rever consultas pode esconder o gargalo real
- excesso de prefetch pode piorar consumo e saturar requests
- modularizacao sem compatibilidade pode quebrar hooks e fluxo de licenca
- preview do PDF exige cuidado para nao gerar inconsistencias entre configuracao atual e cache exibido

## Arquivos e areas candidatas a foco futuro

- includes/class-admin-page.php
- templates/admin-page.php
- assets/js/admin.js
- includes/class-pdf-admin-page.php
- templates/pdf-admin-page.php
- includes/class-settings.php
- assets/js/settings-admin.js
- includes/class-orders-page.php
- assets/js/orders.js
- assets/css/pdf-admin.css
- assets/scss/admin/

## Checklist de execucao futura

1. levantar baseline de performance por view e por request
2. revisar enqueues por contexto
3. listar trechos caros do PDF e separar por bloco funcional
4. definir pontos de cache de cliente e servidor
5. implementar quick wins em lotes pequenos
6. validar regressao funcional a cada lote
7. comparar metricas antes e depois
8. decidir se ainda existe necessidade real de migracao para framework

## Decisao recomendada neste momento

O melhor caminho futuro para este plugin e otimizar a arquitetura atual antes de considerar Vue. O problema principal hoje nao e ausencia de framework, e sim excesso de renderizacao server-side, assets globais demais e custo alto do modulo PDF. Se essas camadas forem enxugadas primeiro, existe boa chance de atingir uma experiencia muito mais rapida sem reescrita estrutural total.
