=== Aireset — Expresso Order ===
Contributors: aireset
Tags: woocommerce, pedido expresso, vendas, proposta, administrativo
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.1.87

Pedido expresso para vendedores do WooCommerce: busca e selecao rapida de cliente e produto, geracao de pedido, proposta publica e PDF nativo com navegacao SPA no admin.

== Description ==

O Aireset - Expresso Order habilita um fluxo comercial rapido dentro do painel WordPress para criar pedidos, gerar propostas e acompanhar vendas sem sair do admin.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Access `Aireset > Pedido Expresso` to configure and use.

== Frequently Asked Questions ==

= O plugin funciona com qualquer tema? =
Sim, ele foi desenvolvido para rodar sobre o WooCommerce ativo e usa o painel administrativo do WordPress.

= Preciso configurar algo especial no WooCommerce? =
Basta ter o WooCommerce ativo e o plugin instalado; as configuracoes de pedido e frete seguem o fluxo do WooCommerce.

== Screenshots ==

1. Tela principal do Pedido Expresso no admin.
2. Formulario de criacao rapida de pedido.
3. Proposta publica gerada para compartilhamento com o cliente.

== Upgrade Notice ==

= 1.1.87 =
* Adiciona editor em accordions para customizar por parte a pagina de upload e produtos do fluxo complementar

= 1.1.86 =
* Adiciona visual dedicado para a pagina de upload e produtos no fluxo complementar

= 1.1.85 =
* Adiciona seletor de categorias de produtos considerados servicos no menu Geral

= 1.1.72 =
* Ajuste de consolidacao do fluxo de confirmacao e dos cards de documentos antes do deploy em producao.

= 1.1.71 =
* Restaura a interface em cards da pagina Documentos do fluxo de confirmacao.

= 1.1.70 =
* Ajusta os controles da sidebar colapsada para ficarem abaixo da logo.

= 1.1.69 =
* Fontselect empacotado localmente e produtos de servico separados nos totalizadores e no fluxo complementar.

= 1.1.68 =
* Controles do sidebar movidos para a linha da logo Aireset, evitando quebra com titulos longos.

= 1.1.67 =
* Navegação administrativa refinada com fly-in menu lateral, controle de recolhimento da sidebar e ajustes no flyout compartilhado para testes em outro ambiente.

= 1.1.66 =
* PDF final da personalizacao salvo no pedido, governanca melhor de upload e flyout admin compartilhado mais robusto.

= 1.1.65 =
* Etapa final do fluxo complementar unificada com upload, personalizacao e carregamento confiavel do CSS publico.

== Changelog ==

= 1.1.72 =
* consolidacao dos ajustes recentes antes do deploy em producao

= 1.1.71 =
* pagina Documentos do fluxo de confirmacao voltou a renderizar documentos como cards recolhiveis, escondendo estados vazios e paineis internos corretamente

= 1.1.70 =
* controles de recolhimento e modo foco agora ficam abaixo da logo quando a sidebar esta colapsada, evitando quebra visual da marca

= 1.1.69 =
* jquery.fontselect empacotado no proprio plugin para evitar erro `_typeof is not defined` em instalacoes via ZIP
* menu Geral ganhou seletor de produtos considerados servicos
* produtos de servico passam a aparecer em linha propria antes do total e ficam fora da edicao do fluxo complementar

= 1.1.68 =
* controles de recolhimento e modo foco movidos para a linha da logo Aireset, mantendo o titulo do plugin limpo mesmo com textos longos

= 1.1.67 =
* shell admin com fly-in menu lateral dedicado e inclusão desses assets na auditoria de performance
* cabeçalho do painel com ação para recolher a sidebar e deixar a navegação mais compacta
* flyout administrativo compartilhado refinado para conviver melhor com a nova navegação lateral

= 1.1.66 =
* fluxo complementar agora salva o PDF final da personalizacao no pedido, exibe downloads centralizados no admin e persiste um payload estruturado versionado para integracoes futuras
* upload complementar ganhou whitelist explicita, limite configuravel e mensagens de erro mais precisas
* flyout administrativo compartilhado ficou mais estavel em submenus multinivel, icones e posicionamento responsivo

= 1.1.65 =
* etapa final publica unificada com upload e personalizacao dos itens em uma unica tela
* proposta publica por token agora carrega o CSS frontend corretamente e usa `eop_token` nos downloads publicos
* editor de documentos ganhou placeholders agrupados e o aceite contratual deixou de exigir nome manual

= 1.1.64 =
* listagem de documentos do fluxo complementar convertida para cards compactos com editar e excluir
* editor de cada documento passa a abrir sob demanda, com resumo do card atualizado em tempo real

= 1.1.63 =
* shell admin consolidado com lazy load mais leve para views principais e reorganizacao das secoes do fluxo complementar
* nova tela de exportar e importar configuracoes dentro da SPA administrativa
* ajustes em PDF, proposta publica e carregamento de settings para reduzir preload e custo do admin

= 1.1.45 =
* pacote de release reduzido ao zip canonico `aireset-expresso-order.zip`
* exclusao de arquivos de desenvolvimento do artefato distribuido
* correcao da ofuscacao do `class-eop-license-manager.php` no dist e no zip final

= 1.1.44 =
* campo de cor alinhado ao layout visual do checkout com swatch interno correto
* botao Padrao restaurado ao lado do controle sem deformar o input

= 1.1.43 =
* corrigido o shell administrativo para nao sobrescrever o visual nativo de configuracoes e licenca
* separacoes da tela de licenca refinadas para uma leitura mais limpa
* padrao oficial de SPA e branding da Aireset documentado para reutilizacao interna

= 1.1.39 =
* adicionadas acoes em massa de quantidade e desconto no cadastro, na edicao e no shortcode
* valor unitario com desconto passa a aparecer nos cards dos itens
* descontos fixos e percentuais agora sao persistidos e recarregados com o tipo correto
* acoes e caixas do PDF respeitam o tipo real de documento do pedido

= 1.1.38 =
* aba de documentacao substitui a antiga tela de atualizacao no modulo PDF
* tooltips explicam o efeito real de cada configuracao diretamente no formulario
* politica de acesso, reset anual, marcacao de impressao, logs e limpeza de cache passaram a afetar o runtime do PDF
* preview e download do XML experimental agora ficam disponiveis no admin

= 1.1.33 =
* priorizada a geracao do PDF final a partir do mesmo HTML do preview, usando Dompdf carregado do plugin de referencia com fallback seguro
* modulo PDF integrado ao shell SPA do Pedido Expresso com submenu lateral em accordion e preview recolhido em drawer lateral
* proposta publica e downloads passaram a usar metadados e nome de arquivo mais consistentes, incluindo download como id-do-pedido.pdf

= 1.1.28 =
* criacao automatica das paginas `[expresso_order]` e `[expresso_order_proposal]` na ativacao
* gerador nativo de PDF com links privados e publicos sem depender de plugin externo
* novo submenu `PDF` com central de documentos e configuracoes do modulo

= 1.1.27 =
* atualizacao do fluxo de build para gerar zip de release padrao e evitar empacotamentos quebrados
* agora o pacote inclui readme.txt na raiz e exclui arquivos de documentacao interna
* arquivos com transicao PHP/HTML sao pulados da ofuscacao para evitar erros na ativacao

= 1.1.26 =
* workflow de release valida a integridade do pacote antes de anexar o zip ao GitHub Release
* `class-admin-page.php` passou a entrar no pacote final junto com os arquivos essenciais do plugin

= 1.1.25 =
* separacao do sistema em `verification core` e `integrity core`
* integridade da distribuicao agora valida a presenca dos arquivos essenciais e pode exibir aviso no admin
* refinado o visual de quantidade, desconto e subtotal nos cards de item do painel
* pipeline de release atualizado para gerar pacote distribuivel mais consistente

= 1.1.24 =
* corrigido o acesso aos submenus `Pedidos` e `Ativacao` do Pedido Expresso no admin
* submenu continua registrado no WordPress e agora e ocultado apenas no DOM pelo flyout

= 1.1.23 =
* restaurado o comportamento do flyout do admin do Pedido Expresso apos regressao no submenu
* ajuste das permissoes do menu para `vendedor_expresso` manter o pai `Aireset`
* flyout do Pedido Expresso agora exibe apenas itens compativeis com a capability do usuario

= 1.1.22 =
* ajuste do flyout do admin para usar o menu raiz correto do `Aireset`, mantendo a estrutura `Aireset > Pedido Expresso > Configuracoes, Pedidos, Ativacao`
* limpeza do bootstrap principal para evitar texto corrompido por encoding

= 1.1.21 =
* reversao da mudanca que transformava `Pedido Expresso` em menu pai do admin
* retorno do plugin para a estrutura filha de `Aireset`, com submenu flyout proprio no item `Pedido Expresso`
* ocultacao dos itens auxiliares do menu raiz e exposicao de `Configuracoes`, `Pedidos` e `Ativacao` no flyout do plugin

= 1.1.20 =
* reorganizacao do menu admin para `Pedido Expresso` funcionar como menu pai real do plugin
* exibicao correta dos submenus `Configuracoes`, `Pedidos` e `Ativacao` no padrao esperado do WordPress
* ajuste complementar da integracao da tela de ativacao para conviver com a nova estrutura de menu

= 1.1.19 =
* adicao de botoes de menos e mais ao redor do campo de quantidade em cada item do pedido
* campo de desconto por item e desconto geral passam a exibir teclado numerico decimal no mobile via inputmode

= 1.1.18 =
* correcao do encoding de entidades HTML no preco dos produtos na busca (R$ aparecia como &#82;&#36;)
* nova configuracao "Modo do desconto" nas configuracoes do plugin (porcentagem, valor fixo ou ambos)
* campo de desconto por item e desconto geral agora respeitam o modo configurado

= 1.1.17 =
* reforco da integracao do campo de logo com a biblioteca de midia do WordPress
* ajuste das dependencias do uploader e do binding JavaScript para seguir o padrao funcional usado pelo plugin de PDF invoices

= 1.1.16 =
* substituicao do campo manual de logo por um uploader com biblioteca de midia do WordPress
* adicao de preview, troca e remocao da logo direto na tela de configuracoes
* melhoria do visual do bloco de logo para ficar coerente com o restante do admin do plugin

= 1.1.15 =
* conversao da listagem de itens da proposta publica para cards visuais de produto
* exibicao de imagem, quantidade, SKU e subtotal por item para deixar a proposta mais bonita e facil de revisar

= 1.1.14 =
* refatoracao da base do frontend para um SCSS mais correto, com mixins utilitarios, funcao de alpha e nesting real nos componentes principais
* melhoria do escopo dos seletores compilados para evitar combinacoes globais indevidas e manter prioridade sem perder previsibilidade

= 1.1.13 =
* reforco da cadeia SCSS do frontend para deixar explicito que `frontend.css` e gerado a partir de `assets/scss/frontend`
* aumento da especificidade dos estilos base de botao, notice e wrappers para reduzir conflitos com o CSS do tema

= 1.1.12 =
* correcao da navegacao SPA para limpar a sessao de edicao e resetar o formulario ao clicar em `Novo pedido`
* garantia de que o vendedor sempre inicia um pedido novo com estado limpo ao sair de uma edicao

= 1.1.11 =
* correcao do fluxo de salvar pedidos para persistir, recarregar e executar o recalculo real do WooCommerce a cada criacao e edicao
* sincronizacao dos totais do pedido e da proposta publica com os descontos por item e descontos gerais ja aplicados

= 1.1.10 =
* substituicao do switch de pagamento apos confirmacao por um componente visual funcional baseado no padrao do `checkout-aireset`
* ajuste do HTML, CSS e JavaScript da tela de configuracoes para refletir claramente os estados `Ativado` e `Desativado`

= 1.1.9 =
* refinamento da comunicacao no admin para deixar a opcao de pagamento apos confirmacao mais clara e menos tecnica
* ajuste do titulo e da descricao do switch para refletir o comportamento real da proposta confirmada

= 1.1.8 =
* limpeza do admin para manter a operacao de criar e editar pedidos somente no SPA
* menu do plugin no admin simplificado para foco em `Configuracoes` e `Pedidos`
* nova personalizacao visual dedicada para a pagina publica do cliente
* proposta confirmada agora pode exibir botao de pagamento em vez de redirecionamento automatico

= 1.1.7 =
* correcao do link de PDF no fluxo SPA para remover entidades HTML da URL antes do envio ao navegador
* ajuste de compatibilidade com o endpoint `generate_wpo_wcpdf` do plugin `PDF Invoices & Packing Slips for WooCommerce`

= 1.1.6 =
* edicao de pedidos agora pode ser aberta e salva diretamente dentro do SPA do shortcode
* novo estado visual de edicao no painel com banner e cancelamento rapido
* ajuste da integracao com `PDF Invoices & Packing Slips for WooCommerce` para usar o endpoint oficial do plugin ao gerar links de PDF
* reforco de permissao para vendedores acessarem e editarem apenas os pedidos expresso que pertencem a eles

= 1.1.5 =
* transformacao do shortcode em experiencia de painel com navegacao SPA entre `Novo pedido` e `Pedidos`
* nova listagem frontend de pedidos com atualizacao por AJAX, filtros por status e busca textual
* administradores agora visualizam todos os pedidos do plugin e vendedores visualizam apenas os pedidos criados por eles
* persistencia do vendedor criador no pedido para suportar rastreio e filtragem por responsavel

= 1.1.4 =
* correcao da regressao visual no frontend apos a simplificacao do desconto
* restauracao da estrutura visual dos cards de item e dos accordions no shortcode
* ajuste do comportamento dos icones de abrir e fechar nos accordions

= 1.1.3 =
* simplificacao do desconto em todas as telas do pedido para um unico campo textual
* agora `10%` aplica desconto percentual e `10` aplica desconto fixo em reais
* ajuste dos cards de item, tela de criacao e tela de edicao para manter o mesmo comportamento de desconto

= 1.1.2 =
* troca do login customizado do shortcode para o fluxo nativo do `wp-login.php`, com `redirect_to` de volta para a pagina do pedido
* ajuste no `login_redirect` para respeitar redirecionamento solicitado quando o acesso vier do frontend
* melhora de compatibilidade para sessao persistente em ambientes locais e instalacoes com comportamento diferente de cookie

= 1.1.1 =
* reforco no login do shortcode com `wp_set_current_user()`, `wp_set_auth_cookie()` e hook `wp_login` para persistir melhor a sessao
* blindagem de compatibilidade nas telas do plugin para nao quebrar caso o servidor esteja com deploy parcial das funcoes de fonte
* alinhamento de versao no bootstrap do plugin e no `package.json`

= 1.1.0 =
* suporte a desconto por produto em `R$` ou `%`
* suporte a desconto geral em `R$` ou `%`
* nova experiencia do shortcode com cards de item, accordions e fluxo de frete mais guiado
* seletor de fontes, color picker e switch na tela de configuracoes
*** End Patch
