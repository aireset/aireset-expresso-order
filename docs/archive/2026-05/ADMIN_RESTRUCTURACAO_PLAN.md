# Plan: Reestruturacao do Admin EOP

Separar o admin do Aireset Expresso Order em areas internas mais leves dentro do shell SPA ja existente, reduzindo a quantidade de configuracoes por tela e melhorando leitura, edicao e navegacao sem abrir mao da experiencia visual atual. A recomendacao e preservar o shell administrativo e reorganizar cada dominio em views internas, secoes, cards, accordions e fluxos dedicados: operacao comercial, pedidos, configuracoes gerais, aparencia e painel, fluxo complementar, PDF e licenca.

## Status atual

- Fase 1 concluida parcialmente: mapeamento inicial do admin, definicao da arquitetura alvo em SPA e definicao basica de capabilities/views.
- Fase 2 concluida parcialmente: registry de views internas, retorno para slug unico do admin e remocao dos submenus reais do PDF.
- Correcao emergencial aplicada: ajuste do loop de redirect e alinhamento do menu/flyout com a navegacao por view.
- Nao concluido ainda: validacao real no navegador, modularizacao de JS por dominio e segunda passada de UX nas telas de configuracao.

## Steps

1. Fase 1 — Mapear e congelar a arquitetura atual antes da migracao. Consolidar a estrutura existente em torno de EOP_Admin_Page, EOP_Settings, EOP_PDF_Admin_Page, EOP_Orders_Page e EOP_License_Manager, registrando explicitamente quais views, assets, handlers AJAX e options pertencem a cada dominio. Esta fase bloqueia todas as seguintes porque evita mover telas sem conhecer dependencias reais.
2. Fase 1 — Definir a nova arquitetura de informacao do admin. A recomendacao e preservar a SPA e quebrar a view unica em areas internas claras: Operacao, Pedidos, Configuracoes gerais, Aparencia e painel, Fluxo complementar, PDF e Licenca. O shell visual permanece; o que muda e a densidade por view, a hierarquia de leitura e a responsabilidade de cada tela. Esta etapa depende do passo 1.
3. Fase 1 — Definir contratos de acesso, URL e ownership de dados por pagina. Formalizar capability por area: edit_shop_orders para Operacao, Pedidos e PDF Geral; manage_options para configuracoes de marca, fluxo complementar, PDF administrativo avancado e licenca. Formalizar tambem quais options/metas cada pagina pode editar para eliminar mistura entre eop_settings e eop_pdf_settings. Esta etapa depende do passo 2.
4. Fase 2 — Extrair a navegacao do admin para um registro unico de views internas. Em vez de manter o roteamento espalhado entre normalize_view, get_view_url, get_accessible_tabs e blocos heterogeneos dentro do template, criar um registry administrativo unico no backend que declare view, titulo, capability, assets e renderer por area. Isso permite reorganizar a SPA sem perder a navegacao. Esta etapa depende do passo 3.
5. Fase 2 — Desacoplar a view monolitica atual sem abandonar o shell SPA. Reduzir EOP_Admin_Page para funcionar como orquestrador leve de views internas, em vez de continuar empilhando todas as configuracoes e modulos no mesmo fluxo de leitura. O objetivo e parar de carregar configuracoes, licenca e PDF como uma massa unica dificil de administrar. Esta etapa depende do passo 4.
6. Fase 2 — Definir a estrategia de assets por contexto. Mapear quais CSS e JS devem carregar apenas nas paginas que realmente usam cada recurso: admin.js para operacao/pedidos, settings-admin.js para paginas com media picker e color picker, pdf-admin.css/js apenas no modulo PDF, assets de licenca apenas na pagina de licenca. Esta etapa pode ocorrer em paralelo com o passo 5.
7. Fase 3 — Migrar Operacao e Pedidos primeiro. Separar o que hoje e misturado entre new-order e orders em duas paginas reais: Operacao para criar/editar pedido e Pedidos para busca/listagem. A recomendacao e manter compatibilidade com os handlers AJAX atuais, mas dividir o estado do frontend e reduzir o admin.js em modulos por responsabilidade. Esta etapa depende dos passos 5 e 6.
8. Fase 3 — Migrar Configuracoes gerais e Aparencia. Tirar de EOP_Settings::render_embedded_page o excesso de grupos heterogeneos e redistribuir em paginas coesas: Aparencia e painel para identidade visual, proposta publica e shell administrativo; Fluxo complementar para contrato, documentos, upload, personalizacao e mensagens finais. Esta etapa depende dos passos 4 e 6.
9. Fase 3 — Reestruturar o modulo PDF como view interna dedicada dentro da SPA. Em vez de competir com configuracoes gerais, o PDF deve aparecer como area propria com tabs internas, preview lateral, ajuda contextual e superficie administrativa focada. Esta etapa depende dos passos 4, 5 e 6.
10. Fase 3 — Isolar Licenca em view administrativa simples e independente dentro da SPA. A licenca nao deve disputar espaco com operacao comercial nem com configuracoes de identidade visual. Reaproveitar EOP_License_Manager para manter o gate atual, mas simplificar a superficie em uma area enxuta com status, chave e acoes. Esta etapa depende do passo 4.
11. Fase 4 — Unificar o design system do admin. Consolidar padroes de cabecalho, agrupamento de campos, textos de apoio, save bar, largura de leitura, accordions, side help e estados vazios. O objetivo e substituir a sensacao de tela socada por blocos administrativos com hierarquia previsivel. Esta etapa pode ocorrer em paralelo com as migracoes dos passos 7 a 10, mas precisa de uma base definida ate metade da implementacao.
12. Fase 4 — Quebrar o JavaScript monolitico por dominio. Fatiar o atual admin.js em modulos de Operacao, Pedidos, componentes compartilhados e utilitarios, deixando cada pagina inicializar so o que usa. A mesma regra vale para os comportamentos de settings e PDF. Esta etapa depende dos passos 6 e 7.
13. Fase 4 — Remover legado comentado e templates mortos apos a migracao. Quando as novas paginas estiverem ativas e validadas, eliminar submenus comentados, templates legacy sem rota, duplicidade de normalizacao entre PHP e JS e renderers embutidos que so existiam para a SPA unica. Esta etapa depende dos passos 7 a 12.
14. Fase 5 — Validar regressao funcional e operacional. Garantir que salvamento de settings, preview PDF, documentos eletronicos, criacao/edicao de pedidos, fluxo complementar e gate de licenca continuam funcionando com as novas rotas. Esta etapa depende de todas as anteriores.
15. Fase 5 — Fechar rollout incremental e limpeza final. Entregar a migracao em lotes pequenos e verificaveis nesta ordem recomendada: 1) registry + navegacao, 2) Operacao/Pedidos, 3) Configuracoes gerais/Aparencia, 4) Fluxo complementar, 5) PDF, 6) Licenca + remocao de legado. Esta etapa depende do passo 14.

## Relevant files

- includes/class-admin-page.php — shell atual, roteamento por view, registro de pagina principal, enqueue global de assets e URL helper do admin.
- templates/admin-page.php — template monolitico atual que concentra Operacao, Pedidos, PDF, Configuracoes e Licenca no mesmo DOM.
- assets/js/admin.js — frontend administrativo centralizado demais; precisa ser quebrado por pagina e responsabilidade.
- includes/class-orders-page.php — fonte dos handlers AJAX e do legado de pedidos; referencia para separar Operacao e Pedidos em paginas reais.
- includes/class-settings.php — option eop_settings, renderizacao embutida atual e excesso de configuracoes heterogeneas em uma unica superficie.
- assets/js/settings-admin.js — comportamentos de media picker, color picker e seletores que devem carregar apenas nas paginas certas.
- assets/css/settings-admin.css — base visual das paginas de configuracao; deve virar design system administrativo coeso com densidade menor.
- includes/class-pdf-admin-page.php — modulo PDF com abas, capabilities diferentes e submenus hoje comentados; ponto principal para virar um conjunto real de subpaginas.
- templates/pdf-admin-page.php — template denso do PDF; precisa ser repartido por contexto e deixar de operar como pagina dentro da pagina.
- assets/css/pdf-admin.css — estilos especificos do PDF que hoje carregam dentro da experiencia administrativa geral.
- includes/class-eop-license-manager.php — gate de licenca e roteamento de pagina quando ativa/inativa; precisa continuar preservando o comportamento de bloqueio durante a reorganizacao.
- assets/css/admin.css — layout do shell, sidebar e componentes compartilhados que devem ser reaproveitados sem manter o acoplamento atual.

## Verification

1. Confirmar que cada nova pagina administrativa carrega apenas os assets necessarios e nao inicializa componentes de outras areas.
2. Validar a matriz de acesso por capability para que vendedores nao vejam configuracoes administrativas e administradores continuem acessando tudo.
3. Validar URLs e navegacao: refresh, back/forward do navegador e deep links para cada pagina/subpagina devem abrir a area correta sem depender de estado oculto da SPA antiga.
4. Testar Operacao e Pedidos separadamente: criar pedido, editar pedido existente, cancelar edicao, buscar cliente, calcular frete, aplicar desconto e gerar PDF.
5. Testar Configuracoes gerais, Aparencia e Fluxo complementar em superficies distintas, garantindo salvamento correto em eop_settings sem campos duplicados ou deslocados.
6. Testar o modulo PDF em suas subpaginas reais, incluindo preview read-only, numeracao, documentos eletronicos, cache e logs, sem perda de capability por aba.
7. Testar a pagina de licenca nos cenarios ativo e inativo para garantir que o gate atual continue coerente.
8. Executar php -l nos arquivos PHP alterados em cada lote e validar estaticamente JS/CSS carregados por pagina.
9. Revisar se templates e classes legacy comentadas foram de fato removidos ou desativados sem deixar caminhos mortos no menu.

## Decisions

- A nova navegacao deve permanecer dentro da SPA, com views internas claras, baixa densidade por tela e menos mistura de responsabilidades.
- O rollout deve ser incremental, com migracao por dominio para reduzir risco operacional.
- O escopo do plano cobre o admin inteiro do plugin, nao apenas a tela PDF ou uma tela isolada.
- A recomendacao de arquitetura e manter apenas um shell visual leve compartilhado e mover o conteudo de cada area para renderers/paginas proprios.
- O escopo inclui reorganizacao de IA, rotas admin, renderizacao, assets, design system e remocao de legado administrativo.
- O escopo nao inclui mudar o frontend publico da proposta, o motor de geracao de PDF ou as regras de negocio do WooCommerce alem do necessario para encaixar a nova administracao.

## Further Considerations

1. Recomendacao forte: nao atacar primeiro o PDF. O melhor primeiro lote e a reorganizacao do shell SPA e a separacao entre Operacao e Pedidos, porque isso reduz o acoplamento mais perigoso do admin.js e do template monolitico.
2. Recomendacao de UX: Fluxo complementar deve sair de Configuracoes gerais e virar pagina propria, porque hoje ele compete com identidade visual, checkout e textos basicos e isso piora muito a clareza administrativa.
3. Recomendacao tecnica: evitar manter dois modos permanentes, SPA e subpaginas reais, por muito tempo. O hibrido so deve existir como etapa de transicao curta para nao perpetuar a duplicidade que hoje ja existe entre legado e shell novo.
