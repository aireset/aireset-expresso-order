# Changelog

Todas as alteracoes relevantes do plugin `Aireset Expresso Order` devem ser registradas aqui.

## 1.1.89 - 2026-05-07

- a pagina contratual do fluxo complementar foi consolidada com editor visual mais granular no admin, preview publico mais fiel ao renderer real e ajustes de estilo/estrutura no frontend, no shell admin e na preparacao dos dados do contrato

## 1.1.88 - 2026-05-07

- a tela `Visual da pagina contratual` ganhou editor em accordions com textos e estilos separados por parte da pagina: header, breadcrumbs, leitor do documento, cards de apoio/aceite, resumo lateral e botoes

## 1.1.87 - 2026-05-07

- a tela `Visual da pagina de upload e produtos` ganhou editor em accordions com textos e estilos separados por parte da pagina: header, breadcrumbs, bloco de titulo, upload, produtos e botao principal

## 1.1.86 - 2026-05-07

- adicionada a view `Visual da pagina de upload e produtos` no fluxo complementar, com controles para textos da etapa final e preview do upload/personalizacao

## 1.1.85 - 2026-05-07

- o menu Geral ganhou seletor de categorias de produtos considerados servicos, com a mesma logica de busca, persistencia e classificacao usada no seletor de produtos

## 1.1.84 - 2026-05-06

- os textos e botoes configurados no admin para upload e personalizacao passaram a ser usados no fluxo complementar

## 1.1.83 - 2026-05-06

- o fluxo complementar passou a publicar fonte e tamanhos configurados no admin como variaveis CSS consumidas pelo SCSS

## 1.1.80 - 2026-05-06

- o baseline de performance voltou a abrir e fechar corretamente, a listagem de pedidos ganhou filtros mais confiaveis e o fluxo complementar ficou mais legivel

## 1.1.81 - 2026-05-06

- o accordion do baseline passou a iniciar colapsado de forma consistente

## 1.1.82 - 2026-05-06

- o passo final do fluxo passou a usar um bloco visual proprio, com estilos conectados as configuracoes do cliente

## 1.1.79 - 2026-05-06

- o PDF ganhou controle de line-height da tabela, modo de exibição da coluna de desconto e sufixo separado para valor unitario com desconto

## 1.1.78 - 2026-05-06

- o resumo do fluxo complementar na listagem de pedidos ficou mais legivel e organizado em chips

## 1.1.77 - 2026-05-06

- o accordion do baseline de performance ganhou seta menor e transicao mais suave

## 1.1.76 - 2026-05-06

- a listagem de pedidos passou a filtrar corretamente por busca e o baseline de performance agora inicia fechado como accordion

## 1.1.75 - 2026-05-06

- a tela de pedidos no admin ganhou busca em formulario com Enter funcionando e o campo de busca passou a ocupar toda a largura disponivel

## 1.1.74 - 2026-05-06

- a pagina publica da proposta passou a respeitar os ajustes visuais configurados no admin, incluindo modos solid/gradient, fontes e cores base

## 1.1.73 - 2026-05-06

- corrige a cor do cabecalho do PDF no caminho de geracao via navegador, aplicando o fundo configurado no `thead`

## 1.1.72 - 2026-05-06

- consolidacao dos ajustes recentes antes do deploy em producao

## 1.1.71 - 2026-05-06

- a pagina Documentos do fluxo de confirmacao voltou a renderizar a lista como cards recolhiveis, escondendo estado vazio, paineis internos e campos de edicao ate o usuario abrir cada documento

## 1.1.70 - 2026-05-06

- os controles de recolhimento e modo foco agora ficam abaixo da logo quando a sidebar esta colapsada, mantendo a marca legivel no menu estreito

## 1.1.69 - 2026-05-06

- o `jquery.fontselect` passou a ser empacotado dentro do proprio Pedido Expresso, evitando carregamento por caminho antigo e o erro `_typeof is not defined` apos instalacao pelo ZIP
- o menu Geral ganhou seletor de produtos considerados servicos, reutilizando a busca por produto/SKU ja existente no admin
- produtos marcados como servico agora ficam separados em uma linha `Servicos` antes do total e nao aparecem na personalizacao do fluxo complementar

## 1.1.68 - 2026-05-06

- os controles de recolhimento da sidebar e modo foco foram movidos para a linha da logo Aireset, evitando quebra visual quando o titulo do painel fica longo

## 1.1.67 - 2026-05-06

- o shell administrativo ganhou suporte dedicado ao fly-in menu lateral, com assets próprios para o menu recolhível e melhor integração desse comportamento ao baseline de performance
- o cabeçalho do painel passou a expor um controle de recolhimento da sidebar, deixando a navegação do admin mais compacta para testes em telas menores e fluxos mais densos
- o flyout administrativo compartilhado foi refinado para conviver melhor com a nova navegação lateral e manter comportamento consistente entre menus e submenus

## 1.1.66 - 2026-05-05

- o fluxo complementar agora gera e salva um PDF final proprio da personalizacao, exposto no frontend e no admin junto com um resumo operacional mais completo, downloads centralizados e payload estruturado versionado para integracoes futuras
- o upload do anexo complementar passou a usar whitelist explicita, limite maximo configuravel e mensagens de falha mais precisas, enquanto o plano operacional do fluxo foi alinhado ao estado real da implementacao
- o flyout administrativo compartilhado foi refinado com bootstrap unico, suporte melhor a icones e submenus multinivel, fechamento mais consistente e posicionamento responsivo no desktop e no mobile

## 1.1.65 - 2026-05-05

- o fluxo complementar publico passou a tratar contrato, upload e personalizacao com uma etapa final unificada, visual refinado e melhor encaixe do layout no estado final
- a proposta publica por token agora carrega o CSS do frontend de forma confiavel e os downloads publicos passaram a usar `eop_token`, evitando conflito com outros plugins
- o modulo de documentos ganhou placeholders organizados no editor, contrato consolidado com melhor reaproveitamento dos documentos configurados e aceite sem exigir nome manual do cliente

## 1.1.64 - 2026-05-04

- a listagem de documentos de assinatura no admin foi convertida para cards resumidos com titulo, badge do tipo, editar e excluir, removendo a exibicao aberta e pesada do formulario inteiro
- o editor de cada documento agora abre sob demanda e o resumo do card acompanha alteracoes de titulo e tipo em tempo real, mantendo a UX mais proxima de uma listagem editavel

## 1.1.63 - 2026-05-03

- o shell administrativo consolidou o bootstrap leve com lazy load para views principais e seccionou melhor as configuracoes do fluxo complementar, experiencia do cliente e modulos de suporte
- adicionada a tela de exportar e importar configuracoes dentro da SPA, incluindo a nova base de portabilidade para backup e migracao do plugin
- refinados o modulo PDF, a proposta publica e o carregamento das configuracoes para reduzir preload desnecessario e manter a experiencia comercial mais fluida

## 1.1.62 - 2026-05-03

- o shell inicial do admin deixou de embutir inteiro as views de Novo pedido e Pedidos; ambas agora nascem como placeholder e carregam o HTML real sob demanda pelo endpoint leve da SPA
- a view comercial principal passou a se auto-inicializar mesmo quando chega via AJAX, preservando Select2, rascunho local e abertura direta de pedido em modo de edicao
- os filtros e atalhos da listagem foram movidos para binds delegados, permitindo lazy load completo da tela de Pedidos sem depender do DOM presente no bootstrap

## 1.1.61 - 2026-05-03

- reduzidas as queries e o custo PHP da listagem de pedidos ao empurrar filtros de vendedor e fluxo complementar para o wc_get_orders com meta_query indexada, evitando varredura completa e filtro em memoria
- o carregamento da edicao de pedido deixou de gerar PDFs de assinatura no payload inicial do admin; os links continuam disponiveis e a geracao fica sob demanda quando o documento for aberto
- o resumo do fluxo complementar nos cards de pedido passou a usar metadados ja persistidos e contagem leve de itens, cortando trabalho repetitivo com produtos e renderizacao pesada

## 1.1.60 - 2026-05-03

- reduzido o custo de renderizacao das lazy views de configuracoes ao carregar listas de paginas, produtos bloqueados e documentos de contrato apenas nas secoes que realmente usam esses dados
- mantida a mesma estrutura da SPA administrativa, mas sem o preload desnecessario que estava inflando o tempo PHP nas views de settings

## 1.1.59 - 2026-05-02

- iniciada a Fase 2 do plano de performance com um endpoint AJAX leve para lazy views administrativas, evitando baixar e renderizar a pagina inteira ao abrir PDF, settings, documentacao e licenca
- a SPA agora carrega essas views sob demanda com payload focado na secao solicitada e continua registrando baseline por request para comparar antes e depois das proximas otimizações

## 1.1.58 - 2026-05-02

- iniciada a Fase 1 do plano de performance com baseline visual no shell admin para medir abertura da SPA, views lazy, PDF e requests principais de pedidos na sessao atual
- adicionada instrumentacao de metricas no PHP e no JavaScript, incluindo tempo total, tempo PHP, tamanho estimado de resposta, pico de memoria e resumo dos assets carregados
- os endpoints AJAX de listagem de pedidos, edicao de pedido e abas do PDF agora retornam dados de auditoria para orientar as proximas fases de otimizacao
- redesenhada a pagina publica confirmada com hero mais forte, cards laterais mais claros e uma hierarquia visual nova para itens, resumo financeiro e acoes
- criada a view dedicada Experiencia do Cliente dentro da SPA para separar fontes, textos e cores da jornada publica confirmada do restante das configuracoes
- o fluxo complementar passou a usar a nova paleta da experiencia publica e agora le o titulo do mapa de jornada diretamente das novas configuracoes

## 1.1.56 - 2026-05-02

- restaurado o bootstrap do fluxo complementar pos-confirmacao, incluindo carga da classe central, endpoints REST e reexibicao do resumo do fluxo dentro do shell SPA
- reativada a integracao da proposta publica com a etapa complementar apos a confirmacao, evitando cair direto apenas nos botoes finais quando o fluxo estiver habilitado
- devolvidos os campos de configuracao do fluxo complementar no admin e o resumo visual do progresso voltou para a edicao de pedidos e para a listagem interna

## 1.1.57 - 2026-05-02

- reorganizado o shell SPA do admin com headers mais compactos, menus internos mais granulares e rodape fixo de salvamento para reduzir o excesso visual nas configuracoes
- o modulo PDF embutido passou a respeitar a aba correta em cada view, remover o chrome redundante no topo e manter os formularios mais curtos ao navegar entre configuracoes
- convertidos os selects binarios do settings para switches visuais e refinada a proposta publica confirmada com resumo lateral, hierarquia melhor e etapa contratual mais clara
- adicionados modo foco no shell administrativo, placeholders com lazy load para views pesadas e cache local da SPA para acelerar reabertura de telas, pedidos e rascunhos
- corrigido o estado visual da navegacao lateral para evitar grupos e itens presos ao trocar de view, enquanto o botao de foco foi reduzido para um icone compacto no sidebar
- a navegacao interna do PDF passou a usar um endpoint AJAX dedicado, com cache por aba e documentacao interna do roadmap futuro em `PERFORMANCE_OPTIMIZATION_PLAN.md`

## 1.1.55 - 2026-05-01

- adicionados filtros da listagem da SPA para isolar pedidos com fluxo complementar ativo, pendente ou concluido
- criada rota REST de colecao paginada para o fluxo complementar, com busca, status do pedido e filtro por status do proprio fluxo

## 1.1.54 - 2026-04-30

- a listagem de pedidos da SPA passou a exibir um resumo compacto do fluxo complementar, incluindo etapa atual, contrato, campos, anexo e progresso dos produtos
- adicionado payload leve especifico para cards de listagem, evitando reutilizar o export completo do fluxo complementar onde ele nao era necessario

## 1.1.53 - 2026-04-29

- a SPA administrativa do Pedido Expresso passou a consumir a rota REST do fluxo complementar ao abrir um pedido em modo de edicao
- o shell administrativo ganhou um card proprio para resumir contrato, campos, anexo, produtos e links do fluxo complementar sem depender apenas do retorno AJAX legado

## 1.1.52 - 2026-04-29

- adicionada rota REST autenticada para consultar o payload estruturado do fluxo complementar por pedido, com validacao de permissao por usuario e por acesso ao pedido
- enriquecido o payload exportado com contexto, timestamp de geracao e metadados basicos do pedido para consumo mais direto em integracoes

## 1.1.51 - 2026-04-29

- adicionado um export estruturado e filtravel do fluxo complementar por pedido, pronto para reaproveito em integracoes futuras sem depender do HTML do frontend ou do PDF
- enriquecido o retorno AJAX de carregamento do pedido com o payload do fluxo complementar para uso no painel interno
- criada uma leitura visual do fluxo complementar na tela administrativa de edicao do pedido, com etapa atual, contrato, campos preenchidos, anexo, produtos e links rapidos

## 1.1.50 - 2026-04-29

- adicionado PDF complementar do fluxo pos-confirmacao, com contrato, campos documentais, status do anexo e personalizacao dos produtos, disponivel no frontend concluido e no admin do pedido
- trocado o campo textual de produtos bloqueados por um seletor visual com busca por nome ou SKU no settings do plugin, mantendo compatibilidade com a option existente
- refinada a experiencia publica do fluxo complementar com barra de progresso, cards de status laterais, resumo de upload e mensagens orientando cada etapa

## 1.1.49 - 2026-04-29

- adicionada a base do fluxo complementar opcional apos a confirmacao da proposta, com contrato inline, 11 campos documentais configuraveis, upload de anexo, personalizacao de nomes por item e resumo no admin do pedido
- a pagina publica da proposta agora consegue retomar a jornada depois do pagamento usando o mesmo link do cliente

## 1.1.48 - 2026-04-23

- ocultado o container de notices do shell quando estiver vazio, inclusive apos dispensar mensagens no admin e na tela de pedidos
- reduzida a altura visual do header `.eop-admin-panel-head` para evitar hero excessivamente alto em views internas

## 1.1.47 - 2026-04-23

- corrigido o preview do PDF para renderizar o desconto em linha unica sem depender do bloco auxiliar em duas linhas
- adicionada exibicao automatica de impostos nos totais do documento quando houver valor tributario, evitando diferenca entre soma das linhas e total final

## 1.1.46 - 2026-04-23

- ajustado o preview e o PDF nativo para manter porcentagem e valor do desconto na mesma linha
- blindada a quebra de linha do simbolo da moeda com o valor no preview do PDF e na proposta publica

## 1.1.45 - 2026-04-20

- corrigido o empacotamento de release para gerar apenas o arquivo `aireset-expresso-order.zip` no formato esperado pelo WordPress
- ajustada a exclusao de arquivos de desenvolvimento do pacote final, mantendo apenas os artefatos necessarios em runtime
- corrigida a ofuscacao do `class-eop-license-manager.php`, incluindo suporte a arquivos com transicao interna entre PHP e HTML

## 1.1.44 - 2026-04-20

- alinhado o campo de cor do Expresso Order ao mesmo padrao visual do checkout, preservando o swatch circular interno e a geometria correta do input
- restaurado o botao `Padrao` ao lado dos controles de cor sem voltar ao layout quebrado que empurrava ou deformava o campo

## 1.1.43 - 2026-04-21

- corrigido o shell administrativo para preservar o visual nativo dos modulos de configuracoes e licenca, evitando que wrappers globais sobrescrevam cards, fundos e sombras ja validados
- refinada a leitura visual da tela de licenca com separacoes mais limpas entre linhas e sem o efeito de caixas pesadas em cascata
- documentado em `ADMIN_UI_BRAND.md` o padrao oficial de SPA e branding da Aireset, incluindo a regra de manter o shell compartilhado sem invadir o design interno de modulos especializados

## 1.1.42 - 2026-04-20

- removido o texto interno do box do brand no sidebar e os labels do menu passaram a ficar alinhados a esquerda
- views principais do SPA ganharam header e acabamento de conteudo no mesmo idioma visual do checkout, com cards, grid e espacamentos mais consistentes

## 1.1.41 - 2026-04-20

- shell SPA do Pedido Expresso aproximado do checkout de forma mais fiel, com mesma estrutura visual de sidebar, bloco interno do brand, paddings, raios e breakpoints do menu
- navegacao lateral recebeu icones, labels e submenu no mesmo padrao do checkout, mantendo apenas o hero proprio do Pedido Expresso

## 1.1.40 - 2026-04-21

- shell administrativo do Pedido Expresso alinhado a identidade visual mais sobria da Aireset, com menus laterais, bordas e submenus no mesmo idioma visual do checkout
- documentado o padrao compartilhado do admin para preservar o hero proprio de cada plugin e reutilizar a mesma linguagem de navegacao

## 1.1.39 - 2026-04-20

- adicionadas acoes em massa de quantidade e desconto no cadastro rapido, na edicao de pedidos e na interface por shortcode para acelerar o preenchimento dos itens
- cards de item passaram a mostrar o valor unitario com desconto e o modo de desconto fixo ou percentual agora e preservado ao salvar, recarregar e reabrir pedidos
- fluxo SPA e listagens foram alinhados para exibir apenas o tipo de PDF compativel com cada pedido, enquanto o shell visual recebeu ajustes de navegacao e responsividade

## 1.1.38 - 2026-04-21

- a aba morta de atualizacao do modulo PDF foi substituida por documentacao interna completa, com textos de efeito real por configuracao e tooltips no formulario
- configuracoes antes expostas sem efeito passaram a funcionar no runtime: politica de acesso do link, reset anual de numeracao, marcacao de impressao, logs, limpeza de cache, modo de teste e dados institucionais extras da loja
- documentos eletronicos ganharam preview XML experimental no admin e exportacao manual, enquanto a danger zone passou a oferecer limpeza de cache e reset de contadores via nonce

## 1.1.37 - 2026-04-20

- adicionados campos globais de quantidade e desconto antes da busca de produtos no cadastro e na edicao de pedidos
- cards de itens agora mostram o valor unitario com desconto e o fluxo SPA foi alinhado ao backend para desconto fixo por item

## 1.1.36 - 2026-04-20

- corrigido o fluxo de PDF para respeitar o tipo real do pedido e evitar abrir proposta para pedidos comuns e vice-versa
- adicionada indicacao clara do documento em edicao na tela de configuracoes e aviso quando o preview usa um tipo diferente do que esta sendo editado

## 1.1.35 - 2026-04-20

- corrigida a invalidacao do cache dos PDFs quando configuracoes visuais e textos de colunas sao alterados
- centralizados os defaults dos textos das colunas e adicionada indicacao visual de expandir/recolher no accordion do admin

## 1.1.34 - 2026-04-18

- adicionada a coluna configuravel de valor unitario com desconto e a personalizacao dos nomes das colunas no PDF e na proposta publica

## 1.1.33 - 2026-04-17

- priorizada a geracao do PDF final a partir do mesmo HTML do preview, usando Dompdf carregado do plugin de referencia com fallback seguro
- modulo PDF integrado ao shell SPA do Pedido Expresso com submenu lateral em accordion, tabs sem reload completo e preview recolhido em drawer lateral
- proposta publica e downloads passaram a usar metadados visuais consistentes e nome de arquivo no formato `id-do-pedido.pdf`

## 1.1.32 - 2026-04-17

- alinhado o item do flyout para manter icone e texto no inicio, deixando apenas a seta do submenu no final

## 1.1.31 - 2026-04-17

- corrigido o flyout multinivel do menu Aireset para que o terceiro nivel do PDF nao seja recortado pelo submenu pai

## 1.1.30 - 2026-04-16

- colunas e totais do PDF/proposta agora podem ser ativados ou desativados por documento nas configuracoes
- pagina publica da proposta ganhou controles de largura e tipografia, melhor alinhamento sem logo e layout visual refinado
- abas do modulo PDF passaram a ter rotas dedicadas no admin para sustentar o flyout multinivel com Geral, Documentos, Documentos eletronicos, Avancado e Atualizar

## 1.1.29 - 2026-04-16

- integracao nativa do modulo de PDF com WooCommerce para emails, acoes do pedido, Minha Conta e metabox no admin
- adicionada coluna de documento PDF e busca por numero do documento na listagem de pedidos do WooCommerce

## 1.1.28 - 2026-04-16

- criacao automatica das paginas de pedido e proposta na ativacao do plugin, com reparo dos shortcodes gerenciados
- substituicao da dependencia externa de PDF por um gerador nativo de documentos e links publicos/privados dentro do plugin
- novo submenu `PDF` com central de documentos e configuracoes do modulo no padrao Aireset

## 1.1.27 - 2026-04-16

- atualizacao do fluxo de build para gerar zip de release padrao e evitar empacotamentos quebrados
- agora o pacote inclui readme.txt na raiz e exclui arquivos de documentacao interna
- arquivos com transicao PHP/HTML sao pulados da ofuscacao para evitar erros na ativacao

## 1.1.26 - 2026-04-15

- workflow de release valida a integridade do pacote antes de anexar o zip ao GitHub Release
- `class-admin-page.php` passou a entrar no pacote final junto com os arquivos essenciais do plugin

## 1.1.25 - 2026-04-15

- separacao do sistema em `verification core` e `integrity core`
- integridade da distribuicao agora valida a presenca dos arquivos essenciais e pode exibir aviso no admin
- refinado o visual de quantidade, desconto e subtotal nos cards de item do painel
- pipeline de release atualizado para gerar pacote distribuivel mais consistente

## 1.1.24 - 2026-04-15

- corrigido o acesso aos submenus `Pedidos` e `Ativacao` do Pedido Expresso no admin
- submenu continua registrado no WordPress e agora e ocultado apenas no DOM pelo flyout

## 1.1.23 - 2026-04-15

- restaurado o comportamento do flyout do admin do Pedido Expresso apos regressao no submenu
- ajuste das permissoes do menu para `vendedor_expresso` manter o pai `Aireset`
- flyout do Pedido Expresso agora exibe apenas itens compativeis com a capability do usuario

## 1.1.22 - 2026-04-14

- ajuste do flyout do admin para usar o menu raiz correto do `Aireset`, mantendo a estrutura `Aireset > Pedido Expresso > Configuracoes, Pedidos, Ativacao`
- limpeza do bootstrap principal para evitar texto corrompido por encoding

## 1.1.21 - 2026-04-14

- reversao da mudanca que transformava `Pedido Expresso` em menu pai do admin
- retorno do plugin para a estrutura filha de `Aireset`, com submenu flyout proprio no item `Pedido Expresso`
- ocultacao dos itens auxiliares do menu raiz e exposicao de `Configuracoes`, `Pedidos` e `Ativacao` no flyout do plugin

## 1.1.20 - 2026-04-14

- reorganizacao do menu admin para `Pedido Expresso` funcionar como menu pai real do plugin
- exibicao correta dos submenus `Configuracoes`, `Pedidos` e `Ativacao` no padrao esperado do WordPress
- ajuste complementar da integracao da tela de ativacao para conviver com a nova estrutura de menu

## 1.1.19 - 2026-04-14

- adicao de botoes de menos e mais ao redor do campo de quantidade em cada item do pedido
- campo de desconto por item e desconto geral passam a exibir teclado numerico decimal no mobile via inputmode

## 1.1.18 - 2026-04-14

- correcao do encoding de entidades HTML no preco dos produtos na busca (R$ aparecia como &#82;&#36;)
- nova configuracao "Modo do desconto" nas configuracoes do plugin (porcentagem, valor fixo ou ambos)
- campo de desconto por item e desconto geral agora respeitam o modo configurado

## 1.1.17 - 2026-04-13

- reforco da integracao do campo de logo com a biblioteca de midia do WordPress
- ajuste das dependencias do uploader e do binding JavaScript para seguir o padrao funcional usado pelo plugin de PDF invoices

## 1.1.16 - 2026-04-13

- substituicao do campo manual de logo por um uploader com biblioteca de midia do WordPress
- adicao de preview, troca e remocao da logo direto na tela de configuracoes
- melhoria do visual do bloco de logo para ficar coerente com o restante do admin do plugin

## 1.1.15 - 2026-04-13

- conversao da listagem de itens da proposta publica para cards visuais de produto
- exibicao de imagem, quantidade, SKU e subtotal por item para deixar a proposta mais bonita e facil de revisar

## 1.1.14 - 2026-04-13

- refatoracao da base do frontend para um SCSS mais correto, com mixins utilitarios, funcao de alpha e nesting real nos componentes principais
- melhoria do escopo dos seletores compilados para evitar combinacoes globais indevidas e manter prioridade sem perder previsibilidade

## 1.1.13 - 2026-04-13

- reforco da cadeia SCSS do frontend para deixar explicito que `frontend.css` e gerado a partir de `assets/scss/frontend`
- aumento da especificidade dos estilos base de botao, notice e wrappers para reduzir conflitos com o CSS do tema

## 1.1.12 - 2026-04-13

- correcao da navegacao SPA para limpar a sessao de edicao e resetar o formulario ao clicar em `Novo pedido`
- garantia de que o vendedor sempre inicia um pedido novo com estado limpo ao sair de uma edicao

## 1.1.11 - 2026-04-13

- correcao do fluxo de salvar pedidos para persistir, recarregar e executar o recalculo real do WooCommerce a cada criacao e edicao
- sincronizacao dos totais do pedido e da proposta publica com os descontos por item e descontos gerais ja aplicados

## 1.1.10 - 2026-04-13

- substituicao do switch de pagamento apos confirmacao por um componente visual funcional baseado no padrao do `checkout-aireset`
- ajuste do HTML, CSS e JavaScript da tela de configuracoes para refletir claramente os estados `Ativado` e `Desativado`

## 1.1.9 - 2026-04-13

- refinamento da comunicacao no admin para deixar a opcao de pagamento apos confirmacao mais clara e menos tecnica
- ajuste do titulo e da descricao do switch para refletir o comportamento real da proposta confirmada

## 1.1.8 - 2026-04-13

- limpeza do admin para manter a operacao de criar e editar pedidos somente no SPA
- menu do plugin no admin simplificado para foco em `Configuracoes` e `Pedidos`
- nova personalizacao visual dedicada para a pagina publica do cliente
- proposta confirmada agora pode exibir botao de pagamento em vez de redirecionamento automatico

## 1.1.7 - 2026-04-13

- correcao do link de PDF no fluxo SPA para remover entidades HTML da URL antes do envio ao navegador
- ajuste de compatibilidade com o endpoint `generate_wpo_wcpdf` do plugin `PDF Invoices & Packing Slips for WooCommerce`

## 1.1.6 - 2026-04-13

- edicao de pedidos agora pode ser aberta e salva diretamente dentro do SPA do shortcode
- novo estado visual de edicao no painel com banner e cancelamento rapido
- ajuste da integracao com `PDF Invoices & Packing Slips for WooCommerce` para usar o endpoint oficial do plugin ao gerar links de PDF
- reforco de permissao para vendedores acessarem e editarem apenas os pedidos expresso que pertencem a eles

## 1.1.5 - 2026-04-13

- transformacao do shortcode em experiencia de painel com navegacao SPA entre `Novo pedido` e `Pedidos`
- nova listagem frontend de pedidos com atualizacao por AJAX, filtros por status e busca textual
- administradores agora visualizam todos os pedidos do plugin e vendedores visualizam apenas os pedidos criados por eles
- persistencia do vendedor criador no pedido para suportar rastreio e filtragem por responsavel

## 1.1.4 - 2026-04-13

- correcao da regressao visual no frontend apos a simplificacao do desconto
- restauracao da estrutura visual dos cards de item e dos accordions no shortcode
- ajuste do comportamento dos icones de abrir e fechar nos accordions

## 1.1.3 - 2026-04-13

- simplificacao do desconto em todas as telas do pedido para um unico campo textual
- agora `10%` aplica desconto percentual e `10` aplica desconto fixo em reais
- ajuste dos cards de item, tela de criacao e tela de edicao para manter o mesmo comportamento de desconto

## 1.1.2 - 2026-04-13

- troca do login customizado do shortcode para o fluxo nativo do `wp-login.php`, com `redirect_to` de volta para a pagina do pedido
- ajuste no `login_redirect` para respeitar redirecionamento solicitado quando o acesso vier do frontend
- melhora de compatibilidade para sessao persistente em ambientes locais e instalacoes com comportamento diferente de cookie

## 1.1.1 - 2026-04-13

- reforco no login do shortcode com `wp_set_current_user()`, `wp_set_auth_cookie()` e hook `wp_login` para persistir melhor a sessao
- blindagem de compatibilidade nas telas do plugin para nao quebrar caso o servidor esteja com deploy parcial das funcoes de fonte
- alinhamento de versao no bootstrap do plugin e no `package.json`

## 1.1.0 - 2026-04-13

- suporte a desconto por produto em `R$` ou `%`
- suporte a desconto geral em `R$` ou `%`
- nova experiencia do shortcode com cards de item, accordions e fluxo de frete mais guiado
- seletor de fontes, color picker e switch na tela de configuracoes
