# Changelog

## 1.5.16 - 2026-07-03

- Telemetria anti-pirataria de instalacao (2 pontos ofuscados: license base + arquivo independente carregado antes do gate).

Todas as alteracoes relevantes do plugin `Aireset Expresso Order` devem ser registradas aqui.

## 1.5.10 - 2026-06-28

- fluxo (etapa do pedido): adicionada a etapa "Dados do cliente" (data) no seletor de etapa do admin. Existia no fluxo/breadcrumb mas nao podia ser selecionada manualmente (faltava em get_stage_control_options + nos stages validos do normalize/rewind). Agora da pra forcar a etapa de dados

## 1.5.9 - 2026-06-28

- pedidos (atualizar etapa): mudar a etapa no card agora atualiza SO aquele pedido, sem recarregar a lista inteira (usa o resumo que o proprio update ja retorna). Antes "recarregava a tela toda"
- fluxo: a etapa "Pagamento pendente" so existe quando "Liberar pagamento apos confirmacao" esta ligado. Sumiu do dropdown de etapa, do breadcrumb e do hero quando a cobranca esta desligada (inclusive ignora override antigo de 'payment' que prendia o pedido em "Pagamento pendente")
- frontend (card de pedido): unificado com o do admin (FrontendApp passou a usar o mesmo OrderCard). Antes era um componente duplicado, e correcoes (badge mostrando a etapa, atualizar etapa) so pegavam no admin

## 1.5.8 - 2026-06-28

- PDF (tabela de itens): aumentado o espacamento entre colunas de valor (passo 58->66pt) para garantir folga real entre "Valor Un. Desc" e "Total" com valores grandes (a 58pt ainda encostavam ~2pt)

## 1.5.7 - 2026-06-28

- documentos (reordenar): trocado os botoes ↑↓ por DRAG-AND-DROP, com handle ☰ (tres barras) a esquerda do titulo de cada documento. Arrastar para a posicao + Salvar persiste a ordem
- PDF (tabela de itens): corrigida a sobreposicao das colunas de valor (ex.: "R$13,1" colado em "R$5.125,77"). As colunas de valor passaram a ser alinhadas a direita com posicoes de borda direita — um valor largo cresce para a esquerda no proprio espaco, sem colidir com a coluna vizinha nem ultrapassar a margem da pagina (page_right=549). Antes eram center com passo ~47pt e valores de ~55pt se sobrepunham

## 1.5.6 - 2026-06-28

- documentos (editor): corrigido o carregamento do conteudo no editor. Em TipTap v3 o `setContent` mudou a assinatura (2o arg virou options `{ emitUpdate }`, nao mais boolean); com o `false` antigo o HTML salvo nao era carregado e o documento abria "fora do formato". Agora carrega corretamente
- documentos (lista): adicionados botoes de REORDENAR (mover para cima/baixo) em cada documento. A ordem do array e a ordem salva, entao reordenar + salvar persiste. Antes nao havia como mudar a ordem
- nota: os placeholders ({order_number}, {billing_full_name}, {billing_cnpj}, etc.) sao inseridos no editor pelo menu de placeholders — o conteudo cadastrado a partir dos PDFs nao continha tokens, entao precisa inseri-los onde os dados dinamicos devem aparecer

## 1.5.5 - 2026-06-28

- config Geral: campos "Produtos considerados servicos" e "Categorias de produtos considerados servicos" agora estilo Select2 — pre-carregam todas as opcoes ao abrir e filtram localmente conforme digita (sem botao "Buscar", sem exigir 1+ caractere). Backend: search_products_payload/search_product_categories_payload retornam a lista completa (ate 100 produtos / 200 categorias) quando o termo vem vazio

## 1.5.4 - 2026-06-28

- pedidos (card): o badge do topo do card passa a mostrar a ETAPA DO FLUXO (ex.: "Dados do cliente", "Conclusao") quando o fluxo complementar esta ativo, em vez do status de pagamento do WooCommerce ("Pagamento pendente"). Como pagamento nao faz parte do fluxo, o status WC ficava sempre "pendente" e nao agregava. Cai de volta para o status WC quando o pedido nao tem fluxo ativo

## 1.5.3 - 2026-06-28

- frontend (PDV publico, lista de pedidos): a lista do frontend (FrontendApp — separada do OrdersBrowser do admin) agora busca/filtra/pagina no SERVIDOR. Mostra o total real (ex.: 196 pedido(s)) + controles Anterior/Proxima. Antes filtrava client-side apenas os 12 da pagina 1 e exibia "12 pedido(s) encontrado(s)" sem navegacao. (1.5.2 havia paginado so o OrdersBrowser do admin, que o frontend nao usa)

## 1.5.2 - 2026-06-28

- pedidos (lista React): agora PAGINADA — botoes Anterior/Proxima + "Pagina X de Y". Antes mostrava so a 1a pagina (12 pedidos) sem navegacao, mesmo havendo varias paginas (ex.: 196 pedidos / 17 paginas). O REST ja retornava a paginacao; o front passou a consumir (page) e renderizar os controles
- frontend (PDV publico): a pagina do PDV envia nocache no HTML. Sem isso, apos um deploy o navegador servia o HTML antigo apontando para o bundle React antigo — por isso correcoes (desconto, etc.) "nao apareciam" sem limpar cache manualmente. O bundle continua versionado por hash e cacheavel

## 1.5.1 - 2026-06-28

- frontend (PDV publico): o campo de desconto agora respeita o "Modo de desconto" tambem na pagina publica. O bug: o config inline do frontend (window.eopAdminSpaConfig em class-shortcode.php) nao incluia discount_mode, entao o React caia no fallback "both" ("10 ou 10%") mesmo com a config em "percent". Agora os 2 blocos de config inline enviam discount_mode
- admin (preview do formulario/listagem): a barra do wp-admin nao aparece mais dentro do iframe de preview. O iframe aponta para a pagina publica real (com admin logado a barra aparecia); agora a URL leva ?eop_preview=1 e o EOP_Shortcode esconde a admin bar nesse contexto. Tambem adicionado cache-bust (?eop_v=versao) para o iframe nao ficar preso em cache

## 1.5.0 - 2026-06-28

- admin (cores): o seletor de cor (Coloris) agora APLICA a cor escolhida no campo. Antes, escolher a cor no popup nao atualizava o valor do input (era um input controlado do React que nao captava a mudanca programatica do Coloris), impossibilitando salvar cores
- admin (novo pedido): o campo de desconto respeita a config "Modo de desconto" (somente %, somente valor, ou ambos) nos 3 pontos — acoes em massa, por item e desconto geral
- admin (novo pedido) e proposta/documento do cliente: a linha "Frete" so aparece quando ha frete informado (> 0). Some o "Frete R$0,00" (envio por transportadora e acordado depois)
- admin (previews): o preview do "Visual do formulario de pedido" e da "Visual da listagem de pedidos" nao mostram mais a barra do wp-admin dentro do iframe (mesmo comportamento limpo da previa da proposta)

## 1.4.9 - 2026-06-28

- admin (resumo do fluxo): card "Pagamento" deixa de aparecer quando a feature "Liberar pagamento apos confirmacao" (enable_checkout_confirmation) esta desligada. Antes todo pedido mostrava "Pagamento: Pendente" mesmo sem cobranca no fluxo, o que nao fazia sentido

## 1.4.8 - 2026-06-28

- login PDV: bloco "Fluxo rapido" volta a ficar AO LADO do login (2 colunas). O colapso para 1 coluna agora so ocorre em tela estreita (<=760px); antes colapsava em <=1280px e quebrava no desktop ~1277px

## 1.4.7 - 2026-06-27

- fluxo (dados): inputs do formulario com border/border-radius/altura forcados (!important) para vencer o override de inputs do tema hello-elementor/Elementor/WooCommerce, que deixava os campos quadrados e sem o estilo do plugin

## 1.4.6 - 2026-06-27

- fluxo: bump de versao para forcar refetch do frontend.css (Cloudflare + cache de navegador estavam servindo CSS antigo, deixando o form da etapa "Dados do cliente" sem estilo). Sem mudanca de codigo alem do EOP_VERSION

## 1.4.5 - 2026-06-27

- fluxo (contrato): contrato agora exibido em HTML inline (nao depende mais da geracao de PDF, que falhava no servidor mostrando "Nao foi possivel gerar o PDF"); PDF vira link secundario "Abrir contrato em PDF"
- fluxo (contrato): captura obrigatoria do NOME de quem aceita (pre-preenchido com o nome do cliente) — antes so registrava data+IP
- fluxo (stepper): a etapa "Dados do cliente" permanece sempre visivel; o total de etapas nao encolhe mais (era "1 de 4" -> "1 de 3")
- fluxo (upload): input de arquivo estilizado (dropzone na cor da marca + nome do arquivo em PT) no lugar do controle cru "Choose File"
- fluxo (dados): mascaras de CPF/CNPJ e telefone na digitacao
- fluxo: correcao de acentos (Endereco->Endereço, Numero->Número, Inscricao->Inscrição, "sao obrigatorios"->"são obrigatórios")

## 1.4.4 - 2026-06-27

- fluxo (cliente): etapa "Dados do cliente" agora tem autofill de endereco por CEP (ViaCEP) + mascara de CEP — preenche Endereco/Bairro/Cidade/UF e foca o Numero, igual ja existia no PDV do vendedor. Antes o cliente digitava tudo a mao

## 1.4.3 - 2026-06-27

- proposta: removido o card lateral "Visão do pedido" (contexto rápido) da proposta pública e do preview admin

## 1.4.2 - 2026-06-27

- pdv: libera o PDV da largura global do block theme (`--wp--style--global--content-size`, 800px) — o mount `#eop-frontend-app` passa a usar 100% da largura do container, dando espaco para as 2 colunas no desktop

## 1.4.1 - 2026-06-27

- seguranca: comando remoto de licenca (Elite Licenser) agora exige requisicao ASSINADA (Ed25519) do servidor; removido o gatilho crc32/md5 nao-autenticado do init_action_handler (qualquer copia do plugin computava o token e deletava/resetava a instalacao sem login). Chave publica embarcada em EOP_License_Core::CMD_PUBKEY; privada so no servidor (option el_cmd_signing_sk)
- pdv: layout do PDV (frontend React) responde a largura do PROPRIO container (container query) em vez da viewport, pois vive embutido num container Elementor mais estreito que a tela; antes ficava 1 coluna no desktop
- pdv: corrige sobreposicao das colunas — itens do grid recebem min-width:0 e a linha "acoes em massa" passa a ser fluida (fr) com inputs width:100%, evitando transbordo por cima do sidebar

## 1.4.0 - 2026-06-25

- PDF: gestao completa migrada da pagina legada para 11 secoes React nativas (Visualizacao; Pedido e Proposta: documento/colunas/textos/estilo; Documentos eletronicos; Avancado), no mesmo sistema de configuracoes do resto do plugin, com preview ao vivo do documento; a pagina legada permanece so como degradacao quando os assets React faltam
- fluxo: formulario "Dados do cliente" redesenhado (intro + grupos Dados pessoais/Contato/Endereco, grade de 12 colunas com larguras corretas, autocomplete/inputmode para mobile)
- fluxo: etapa final renomeada para "Conclusao"; etapa "Dados do cliente" agora ocupa a tela como as demais; botoes de baixar PDF da Conclusao so aparecem para vendedor/admin
- fluxo: corrige a etapa de upload que exigia anexo mesmo com a opcao desativada; validacao de nome e UF alinhada com o formulario
- distribuicao: uninstall remove options/transients de licenca; default da cor do cabecalho do PDF passa para o navy da identidade (#00034b)
- correcoes: re-sincronizacao do editor de documentos (TipTap), fallback de campo desconhecido nas configuracoes, remocao de codigo morto e comentarios desatualizados

## 1.3.0 - 2026-06-10

- performance: cacheia a verificacao de licenca (phone-home bloqueante a cada request do admin), derrubando o tempo das chamadas REST do painel de ~2-3s para ~0,8s; quando o servidor nao envia `request_duration`, o resultado valido passa a ser memoizado por 12h em vez de revalidado a cada carregamento, e o timeout do request cai de 120s para 12s (sem alterar a logica de validacao)
- admin SPA: a tela "Novo pedido" passa a renderizar o componente React canonico (NewOrderForm) com o layout do admin legado (grid PDV de 2 colunas, cards, accordions, totais) em vez do formulario chapado divergente
- admin SPA: a tela "Pedidos" passa a usar o novo OrdersBrowser (cards com Data/Total/Vendedor, resumo do fluxo complementar com pills, filtros Buscar/Status) e a edicao abre o pedido no proprio formulario
- admin SPA: desativa o modo fullscreen forcado no "Novo pedido" que escondia o menu do WordPress; o painel volta a conviver com o menu do WP, e o modo foco (esconder a interface do WP) e o recolher da sidebar (faixa de icones com submenus em fly-in) viram botoes opcionais controlados pelo usuario
- frontend: o shortcode `[expresso_order]` (tela de vendas) passa a usar o React por padrao para usuarios com permissao de vendedor; escape para o frontend legado com `?eop_legacy=1`
- frontend: corrige o overlap do PDV em larguras medias/zoom (a coluna de produtos vazava por baixo da sidebar); o layout passa a empilhar em 1 coluna abaixo de 1280px
- admin SPA: o conteudo do "Novo pedido"/"Pedidos" passa a ter o mesmo acabamento da tela de vendas do frontend (cards arredondados com sombra, Total em destaque navy, botao primario em pill com gradiente, icone de accordion circular)
- compatibilidade: declara suporte a HPOS (Custom Order Tables) do WooCommerce
- corrige o numero de pedido exibido com `#` duplicado (`##6161` -> `#6161`) no admin SPA

## 1.2.30 - 2026-06-10

- corrige a interpolacao desnecessaria de numeros de pedido no admin SPA, limpando a renderizacao e evitando formatacao redundante

## 1.2.29 - 2026-06-10

- release patch: correcoes menores, aprimoramentos de estabilidade e ajustes de build

## 1.2.28 - 2026-06-08

- redesenha a pagina publica da proposta para mobile (hero mais leve, card de item compacto, logo responsiva que nao estoura mais a caixa) mantendo o layout do desktop
- reorganiza o card de item em lista de detalhes (rotulo -> valor) com todos os dados que o cliente precisa: quantidade, preco unitario, desconto em percentual e em valor, preco unitario com desconto e SKU, com o total destacado
- oculta o bloco "Cliente" no hero e no resumo quando o pedido nao tem nome de cliente informado
- trava o text-size-adjust do iOS para o Safari mobile nao inflar as fontes da proposta
- corrige acentuacao e ortografia dos textos exibidos ao cliente: proposta, paginas de confirmacao/contrato/upload/personalizacao, mensagens de erro/download e PDF gerado
- adiciona migracao automatica unica que corrige textos ja salvos no banco que ainda batem com o padrao antigo sem acento, preservando textos personalizados pela loja

## 1.2.27 - 2026-06-08

- reconstroi a tela Fluxo de Confirmacao > Documentos em React nativo: repetidor de documentos de assinatura com titulo, tipo (conteudo HTML ou arquivo), corpo, anexo via wp.media (PDF/Word) e textos dos botoes, com insercao de variaveis de placeholder
- adiciona o endpoint REST `aireset-expresso-order/v1/admin/confirmation-documents` (leitura e gravacao) reaproveitando o sanitizador oficial das configuracoes
- remove o handoff para o admin legado nessa tela

## 1.2.26 - 2026-06-08

- adiciona o seletor de produtos bloqueados (multiselect com busca) na tela Confirmacao - Upload e Produtos, reaproveitando o estado de selecao do admin legado
- remove o patch de recuperacao do versionamento e passa a ignorar arquivos `.patch`

## 1.2.25 - 2026-06-08

- migra as telas de configuracao para React nativo no admin SPA: Loja, Gerais, Visual do Pedido, Visual da Listagem, Visual da Proposta e Confirmacao, reaproveitando as definicoes de campos do admin legado como fonte unica
- adiciona tipos de campo no SPA: seletor de logo via wp.media, color picker Coloris (igual ao legado), seletor de produto/categoria com busca (multiselect), switch para campos Sim/Nao e campos numericos
- organiza as secoes em accordions fechados por padrao com sub-cabecalhos por bloco e tooltips de ajuda nos campos complexos; um unico bloco aparece aberto sem accordion
- corrige campos compartilhados que sumiam no SPA (ex.: borda compartilhada) liberando as chaves do schema na leitura e no salvamento de todas as secoes da ponte
- ajusta o campo de loja para refletir o legado (logo, todos os campos em PT, botao Salvar flutuante) e remove textos tecnicos de desenvolvimento do cabecalho
- bootstrap do admin injetado inline no HTML, eliminando o round-trip REST do primeiro carregamento
- torna o flyout do menu Aireset disponivel em todas as telas do admin, nao apenas dentro do plugin
- permite navegar no admin legado por aba via `eop_admin_legacy` sem cookie global, com botao dedicado de volta ao admin novo
- aponta a tela de Documentos do fluxo complementar para o gerenciador do admin legado enquanto a versao nativa nao chega
- extrai o cluster de anexos/extracao de texto do fluxo complementar para um trait dedicado
- reduz assets de terceiros carregados na pagina publica `/pedido-expresso/`

## 1.2.24 - 2026-05-30

- adiciona cache em memoria no admin React para settings, previews e pedidos ja carregados, reduzindo flicker ao voltar para views visitadas
- evita que respostas REST atrasadas sobrescrevam a view atual quando o usuario navega rapidamente entre menus
- mantem refresh em segundo plano para dados ja cacheados sem reexibir a tela de carregamento completa

## 1.2.23 - 2026-05-30

- remove a renderizacao crua de todas as configuracoes do PDF na view React, evitando uma tela gigante e lenta no SPA
- transforma a view `PDF` em um painel operacional curto com acesso ao modulo completo pelo fallback tecnico
- reduz a carga da rota `admin-pdf` ao nao buscar `settings/pdf` ate a quebra do dominio PDF em telas React dedicadas

## 1.2.22 - 2026-05-29

- corrige o deslocamento visual do admin React quando plugins terceiros injetam notices dentro da `.wrap` do WordPress
- oculta notices externos no shell React sem reativar o CSS legado global do admin
- endurece o smoke browser para validar que o shell React inicia dentro do viewport e sem notices visiveis quebrando o layout

## 1.2.21 - 2026-05-29

- restaura no admin React/Vite a identidade visual do shell anterior, incluindo sidebar Aireset, logo, grupos de menu e estados ativos equivalentes ao legado
- adiciona branding no bootstrap REST para o SPA consumir titulo, subtitulo, logo, cores, raio e fonte das configuracoes reais do plugin
- extrai a camada REST do `App.tsx` para `assets/admin-spa/src/app/api.ts`, mantendo o componente focado em estado e renderizacao

## 1.2.20 - 2026-05-29

- promove o admin React/Vite para superficie principal quando o bundle esta compilado, deixando o admin PHP legado apenas como fallback tecnico por `eop_admin_legacy=1`
- inicia a reestruturacao real da pasta SPA extraindo tipos e mapas de views para `assets/admin-spa/src/app/`
- recompila o bundle Vite e atualiza os smokes para validar o SPA como padrao e o legado como fallback explicito

## 1.2.19 - 2026-05-29

- corrige erro `$.fn.select2 is not a function` ao abrir `Novo pedido` via lazy-load a partir de telas que nao carregaram Select2 no bootstrap inicial
- adiciona carregamento sob demanda dos assets Select2 do WooCommerce para manter a navegacao SPA sem voltar a enfileirar Select2 globalmente em todas as views
- amplia o smoke browser com navegacao real de uma tela de configuracao para `Novo pedido`, validando Select2 carregado sob demanda

## 1.2.18 - 2026-05-29

- corrige regressao do shell admin apos a remocao do `frontend.css` global, garantindo que views SPA com atributo `hidden` continuem realmente ocultas
- atualiza a versao do pacote para quebrar cache de `admin.css` nos navegadores e no enqueue do WordPress
- amplia o smoke browser para cobrir `settings-confirmation-documents` e falhar quando mais de uma view do admin fica visivel
- torna o lazy-load REST-first mais tolerante: qualquer falha REST tenta o fallback AJAX antes de redirecionar a tela

## 1.2.17 - 2026-05-29

- remove assets globais nao relacionados de Elementor, cupons, Mercado Pago, YaySMTP, Woo admin blocks e Jetpack nas telas do Pedido Expresso
- bloqueia Elementor Notes na pagina publica `/pedido-expresso/`, evitando erro JavaScript para usuarios logados fora do editor
- atualiza o baseline pos-limpeza no roadmap, reduzindo `legacy-orders` de `99` para `58` scripts e o SPA de `97` para `56` scripts

## 1.2.16 - 2026-05-29

- corrige o versionamento da serie posterior a `1.1.100`, tratando esta entrega como `1.2.16`
- adiciona baseline automatizado de performance do admin para comparar legado, SPA por feature flag e views principais
- documenta o novo fluxo de medicao em `docs/OPERATIONS.md`

- migra lazy-load de views e abas PDF do admin legado para REST-first, mantendo `admin-ajax.php` como fallback temporario
- adiciona REST-first para seletores de categoria/produto em settings, troca manual de etapa do fluxo complementar e para o fluxo legado isolado de pedidos em `orders.js`
- amplia o smoke REST para validar lazy-view, PDF tab e busca de categorias junto com bootstrap, settings, previews e pedidos
- atualiza roadmap e operacoes para refletir a nova cobertura REST-first ainda antes do cutover definitivo do SPA

## 1.1.115 - 2026-05-29

- migra o fluxo legado de novo pedido para tentar REST antes de `admin-ajax.php` em busca de produto, busca de cliente, calculo de frete, listagem de pedidos, carregamento para edicao e salvamento/criacao de pedido
- mantem fallback temporario para `admin-ajax.php` quando o REST administrativo nao estiver disponivel
- amplia o payload REST de pedidos para expor `orders`, `viewer`, URLs, status, PDF e resumo do fluxo complementar no formato consumido pelo admin legado

## 1.1.114 - 2026-05-29

- adiciona `scripts/smoke-admin-browser.mjs` com Playwright, autenticacao por cookies gerados via WP-CLI e artefatos em `output/eop-browser-smoke/`
- valida no navegador o shell admin, `new-order`, `orders`, pagina publica `/pedido-expresso/`, telas de preview, PDF e SPA por feature flag
- remove na pagina do Pedido Expresso os handlers globais do `disable-dashboard-for-woocommerce-pro` que chamavam `core/edit-post` fora do editor de blocos e geravam erro `isFeatureActive`

## 1.1.113 - 2026-05-29

- endurece o smoke REST via WP-CLI com assertions de fonte da secao `store`, origem publica do preview `new-order` e conteudo das etapas `upload/products`
- valida que o preview de upload/produtos contem os botoes configurados de upload e personalizacao, alem do card de stage `products`

## 1.1.112 - 2026-05-29

- adiciona `scripts/smoke-admin-rest.php` para validar via WP-CLI os endpoints administrativos de bootstrap, settings, previews e listagem de pedidos
- documenta em `docs/OPERATIONS.md` o smoke REST administrativo, incluindo execucao com `wp` global ou `tools/wp-cli.phar` local
- atualiza o roadmap para marcar o smoke REST como validado e separar essa cobertura dos smoke tests interativos ainda pendentes

## 1.1.111 - 2026-05-29

- amplia o preview administrativo de upload/produtos para renderizar separadamente os estados `upload` e `products`
- exibe no preview os textos e botoes proprios de cada etapa, incluindo `post_confirmation_upload_button_label` e `post_confirmation_products_button_label`
- preserva o mesmo renderer interno `render_final_step_renderer_markup` para evitar divergencia entre preview e fluxo publico

## 1.1.110 - 2026-05-29

- alinha a secao REST `settings/store` do admin SPA com `EOP_PDF_Settings`, usando os campos reais `shop_*` consumidos pelo renderer de PDF
- evita que o SPA edite os campos legados `pdf_company_*`/`pdf_footer_note` como fonte primaria da loja
- passa a aplicar o filtro `exact`/`prefix` tambem em secoes de origem `pdf`, mantendo a secao `pdf` completa apenas quando nenhum filtro e declarado

## 1.1.109 - 2026-05-29

- mantem o preview lateral do PDF ativo mesmo quando `advanced_html_output` esta habilitado, desde que exista pedido valido para preview
- troca o bloqueio do preview por um aviso operacional informando que o modo avancado continua usando o renderer HTML interno para validacao visual

## 1.1.108 - 2026-05-29

- amplia o payload do preview de contrato para incluir documentos secundarios configurados
- renderiza cards de documentos adicionais e botoes secundarios no preview administrativo da confirmacao, aproximando-o do renderer publico `render_contract_form`

## 1.1.107 - 2026-05-29

- troca o lazy-load legado da view `new-order` para renderizar `templates/shortcode-page.php`, a mesma superficie usada pelo shortcode publico `[expresso_order]`
- reduz a divergencia entre admin, preview e pagina `/pedido-expresso/` ao deixar o mock `admin-view-new-order.php` fora do caminho ativo carregado por AJAX

## 1.1.106 - 2026-05-29

- corrige o editor visual da proposta para salvar as cores base em `customer_experience_text_color` e `customer_experience_muted_color`, que sao as chaves priorizadas pelo renderer publico
- reduz campos sombreados no preview da proposta ao substituir controles legados `proposal_text_color` e `proposal_muted_color` na tela visual principal

## 1.1.105 - 2026-05-29

- alinha o preview administrativo da proposta publica ao renderer compartilhado, usando `customer_experience_title` e `customer_experience_description` como fonte de verdade
- remove a sobrescrita por `proposal_title`/`proposal_description` no preview visual da proposta para reduzir divergencia entre admin e pagina publica

## 1.1.104 - 2026-05-29

- corrige o preview administrativo da etapa de upload/produtos para usar `post_confirmation_upload_button_label` quando o stage exibido e `upload`
- amplia o sample do preview final para exibir produto bloqueado e SKU vazio, cobrindo estados configuraveis que antes nao apareciam na tela

## 1.1.103 - 2026-05-29

- separa assets ricos do admin legado por view, carregando media library, TinyMCE, Coloris, fontselect e Select2 apenas nas telas que usam esses recursos
- limita o `settings-admin.js` a dependencias dinamicas por recurso, reduzindo o peso de views simples de configuracao
- restringe o flyout administrativo as telas Aireset/Pedido Expresso e remove o carregamento remoto de Font Awesome quando os icones usam Dashicons

## 1.1.102 - 2026-05-29

- reduz o carregamento global de assets no admin legado, limitando `frontend.css`, `pdf-admin.css`, `select2`, media library, editor, Coloris, fontselect e `settings-admin.js` as views que realmente precisam deles
- atualiza o baseline de performance para refletir os handles carregados por view em vez de uma lista global fixa
- preserva `admin.js` e o menu do shell legado como base comum enquanto o cutover do SPA segue por feature flag

## 1.1.101 - 2026-05-29

- amplia a migracao de settings no admin SPA com schema automatico para valores escalares das secoes REST existentes
- libera edicao/salvamento generico para `store`, `proposal`, `new-order`, `orders-list`, confirmacao e PDF quando os campos sao seguros para formulario simples
- restringe o draft React as chaves declaradas no schema de campos, evitando envio acidental de arrays ou estruturas nao editaveis

## 1.1.100 - 2026-05-29

- completa a edicao de pedidos existentes no admin SPA com busca/adicao de produtos via REST
- adiciona calculo de frete na edicao de pedidos usando `POST /shipping/rates` e preserva dados do metodo selecionado ao salvar
- atualiza a UI de `orders` para remover a dependencia do legado na adicao de produtos durante a edicao

## 1.1.99 - 2026-05-29

- normaliza os contratos REST do novo admin para busca de cliente, busca de produtos, calculo de frete e criacao de pedido
- reaproveita os handlers legados de AJAX como helpers compartilhados para reduzir divergencia entre shortcode, admin legado e shell React
- adiciona fluxo funcional de `Novo pedido` no admin SPA experimental, com cliente, produtos, endereco, frete, desconto e `POST /orders`

## 1.1.98 - 2026-05-29

- extrai a carga e a atualizacao de pedidos para metodos compartilhados em `EOP_Orders_Page`, reaproveitados pelo AJAX legado e pelo namespace REST do novo admin
- substitui o `501` de `PUT /orders/{id}` por edicao REST real de pedidos existentes, com refresh do payload completo apos salvar
- amplia o shell React para listar pedidos, selecionar um pedido, editar cliente/endereco/descontos/itens existentes e salvar pelo novo fluxo SPA

## 1.1.97 - 2026-05-29

- expande o contrato REST da secao `general` com schema e opcoes reais para o novo admin SPA
- adiciona formulario funcional no shell React para editar e salvar `settings-general-config` via `POST /settings/general`
- mantem o admin legado como fallback enquanto a migracao funcional segue por dominio

## 1.1.96 - 2026-05-29

- consolida a documentacao canonica em `docs/`, move os planos legados para arquivo historico e reescreve a politica legal e de IA em torno de licenca proprietaria com titularidade `Felipe Almeman + Aireset`
- substitui `README.md`, `AGENT.md`, `LICENSE`, `readme.txt` e `.github/copilot-instructions.md` para refletir ownership, restricoes de uso e governanca para agentes
- introduz a fundacao do novo admin SPA experimental com `React + TypeScript + Vite`, namespace REST `aireset-expresso-order/v1/admin`, feature flag controlada e fallback limpo para o admin legado
- converte a aba `Documentacao` em hub da documentacao canonica do plugin inteiro e adiciona um controle administrativo para ligar o admin SPA experimental nas configuracoes gerais

## 1.1.95 - 2026-05-14

- reorganiza o editor visual da proposta publica em accordions por elemento real da pagina, incluindo hero principal, hero lateral, lista de itens, cards laterais, botoes e alertas
- separa estilos dedicados no renderer compartilhado para hero lateral, cards internos, secao de itens, cards de item e cards laterais, mantendo o preview admin alinhado com a pagina publica
- remove a observacao legada "Este bloco simula a jornada publica com a mesma hierarquia visual usada pelo cliente." do card de acoes no preview e na proposta publica

## 1.1.94 - 2026-05-13

- remove os previews duplicados da tela `Visual da proposta do cliente`, mantendo apenas o preview inicial da proposta publica
- restaura a consistencia da navegacao do SPA do admin ao remover referencias legadas da view `settings-customer-experience` no JavaScript
- preserva os previews proprios das telas `settings-confirmation-preview` e `settings-confirmation-upload-products-preview`, mantendo a separacao correta das etapas do fluxo complementar

## 1.1.93 - 2026-05-13

- unifica a configuracao visual da proposta do cliente, adiciona telas dedicadas para criar pedido e listagem de pedidos e reorganiza a navegacao geral do admin
- os previews visuais de criar pedido e listagem passam a carregar a tela real do shortcode `[expresso_order]` em iframe com skin configuravel, mantendo o fluxo DRY e fiel ao frontend
- amplia a customizacao textual e visual da proposta publica, incluindo rotulos, estados, botoes, alertas e resumos do fluxo complementar

## 1.1.92 - 2026-05-07

- ajustes na renderização pública da proposta e refinamento do resumo administrativo com novo renderer compartilhado

## 1.1.91 - 2026-05-07

- ajustes na proposta pública e inclusão de novos templates de configuração para confirmação de pedidos e documentos

## 1.1.90 - 2026-05-07

- o fluxo contratual e a etapa final receberam refinamentos visuais no frontend, enquanto o resumo administrativo passou a refletir melhor confirmacao do pedido, pagamento, aceite contratual e conclusao da etapa de upload/personalizacao

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
