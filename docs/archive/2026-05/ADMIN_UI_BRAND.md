# Identidade Visual Admin Aireset

Este documento define o shell visual base para plugins administrativos da Aireset.

## Objetivo

Padronizar menus, bordas, superficies e estados de navegacao entre plugins, mantendo liberdade apenas no bloco hero de cada produto.

## Regras do shell

- sidebar com base navy escura e gradiente sutil, sem blocos brancos chapados no estado ativo
- bordas discretas em baixa opacidade e sombras curtas, evitando brilho excessivo
- item ativo principal com gradiente navy + teal e anel interno suave
- submenu com fundo translcido, recuo leve e destaque lateral teal no hover e no ativo
- o topo da lateral esquerda precisa sempre nascer com um bloco de marca composto por logo Aireset, dados essenciais do plugin e acao de expandir o plugin para tela total
- tipografia do hero continua especifica por plugin; nao usar copy generica obrigatoria
- o hero pode variar por produto, mas a navegacao lateral deve manter a mesma linguagem visual
- headers das views SPA podem compartilhar o mesmo gradiente do shell para unificar a navegacao com o conteudo
- wrappers globais do SPA nao devem sobrescrever formularios ou containers de modulos que ja possuam design proprio validado
- configuracoes, PDF e licenca devem manter seus cards internos e apenas receber o shell, espacamento e headers compartilhados

## Bloco superior da sidebar

- a logo Aireset deve aparecer no topo da sidebar antes da navegacao, preferencialmente como wordmark oficial em arquivo proprio do plugin
- o card principal desse bloco deve exibir nome do plugin, resumo curto do produto e pelo menos dois ou tres dados operacionais do modulo, como versao, slug ou dominios cobertos
- a acao de tela total precisa ficar no canto superior desse card, com botao proprio e estado visivel para entrar e sair do modo foco
- esse bloco superior e parte obrigatoria do shell Aireset; nao deve ser substituido por um simples titulo textual solto
- plugins que reutilizarem este shell devem copiar a estrutura do topo lateral junto com a navegacao, e nao apenas as cores da sidebar

## Regras de controles

- campos de cor devem seguir o mesmo padrao visual do checkout, usando o `Coloris` com swatch circular pequeno dentro do proprio input
- o wrapper `.clr-field` continua sendo o container visual principal do color picker; nao inserir botoes extras dentro dele alem do swatch nativo
- quando houver acao de reset para cor padrao, ela deve ficar em um wrapper externo ao lado do campo, como `.eop-color-control`, sem empurrar ou deformar o input
- o botao `Padrao` e complementar: deve restaurar `data-default-color`, mas nao pode alterar o layout base do picker nem competir com o botao nativo do `Coloris`
- campos de cor do admin nao devem herdar automaticamente a mesma paleta aplicada a proposta publica ou ao frontend do cliente; sao controles administrativos

## Tokens de referencia

- fundo da sidebar: `rgba(9, 21, 51, 0.98)` ate `rgba(15, 37, 87, 0.98)`
- gradiente ativo: `rgba(21, 50, 103, 0.95)` ate `rgba(22, 91, 121, 0.9)`
- acento: `#32d1c7`
- borda da sidebar: `rgba(255, 255, 255, 0.06)`
- borda interna ativa: `rgba(80, 201, 194, 0.14)`

## Aplicacao no Expresso Order

- manter o hero com `panel_title` e `panel_subtitle`
- aplicar o padrao apenas no shell lateral, grupos, submenus e estados ativos
- manter no topo lateral a wordmark Aireset, o card com os dados do plugin e o toggle de tela total usado no shell SPA
- nao acoplar a paleta administrativa aos controles de cor da proposta do cliente
- preservar o visual nativo de `.eop-settings-form`, `.eop-settings-card` e `.el-license-container`, evitando wrappers que redefinam borda, fundo ou sombra nesses modulos
- manter os campos `.eop-color-field` com o mesmo comportamento do checkout: swatch interno em `.clr-field` e `Padrao` externo em `.eop-color-control`

## Referencias internas

- Pedido Expresso: `assets/scss/admin/_spa-shell.scss`
- Pedido Expresso controles: `assets/scss/admin/settings.scss` e `assets/js/settings-admin.js`
- Checkout: `../aireset-checkout/backend/assets/scss/admin/partials/spa/_navigation.scss`

Use este guia como referencia base ao replicar o shell administrativo em outros plugins Aireset.
