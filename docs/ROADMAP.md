# Roadmap

## Estado base

- Versao atual usada como referencia: `1.2.24`
- Janela refletida neste roadmap: `1.1.96` ate `1.2.24`
- Observacao de versionamento: a serie posterior a `1.1.100` passa a ser tratada como `1.2.x`; a entrega que estava como `1.1.116` foi corrigida para `1.2.16`
- Status permitidos neste arquivo: `feito`, `parcial`, `pendente`
- Admin React/Vite passa a ser a superficie ativa quando o bundle esta compilado
- Admin legado fica como fallback tecnico por `eop_admin_legacy=1`

## Prioridades

1. `feito` - Consolidar ownership, licenca, politica legal e instrucoes para IA
2. `parcial` - Evoluir o admin SPA principal por dominios funcionais
3. `parcial` - Quebrar o admin legado por dominio e reduzir dependencias globais
4. `parcial` - Unificar contratos de preview sem divergir das superficies publicas
5. `parcial` - Fechar lacunas funcionais antes do cutover

## Baseline de performance medido em 2026-05-29

Fonte: `npm run baseline:admin-performance`, 2 amostras por alvo, ambiente local `eclipsiacosmeticos.local.br`, apos limpeza de assets globais nao relacionados.

| Alvo | Duracao media | DOMContentLoaded medio | Recursos | Scripts | CSS | Transferencia |
|---|---:|---:|---:|---:|---:|---:|
| legacy-shell | `4797.5ms` | `4263.5ms` | `74` | `59` | `10` | `4148.87KB` |
| legacy-new-order | `4751.5ms` | `4212ms` | `72` | `58` | `10` | `4047.91KB` |
| legacy-orders | `11686ms` | `4055ms` | `75` | `58` | `10` | `4158.05KB` |
| legacy-pdf | `6518ms` | `5995.5ms` | `103` | `84` | `13` | `7874.07KB` |
| spa-feature-flag | `4661ms` | `4140.5ms` | `66` | `56` | `6` | `3939.96KB` |
| public-new-order | `12604ms` | `5351.5ms` | `153` | `56` | `54` | `4015.03KB` |

Leitura objetiva: a limpeza reduziu significativamente recursos, scripts e transferencia, mas o tempo percebido ainda e alto em `legacy-orders` e `/pedido-expresso/`. A proxima etapa deve atacar render inicial, consultas/listagem de pedidos e CSS/JS publico carregado pelo tema/site.

## Matriz por superficie

| Superficie | Origem dominante | Renderer real | Preview / SPA atual | Status |
|---|---|---|---|---|
| new-order | `eop_settings`, REST admin, fallback AJAX legado, WooCommerce | `EOP_Shortcode` / `templates/shortcode-page.php` | iframe com pagina real; JS legado tenta REST para cliente, produto, frete e criacao/edicao antes de `admin-ajax.php` | `feito` |
| orders | pedidos WooCommerce + REST admin + fallback AJAX legado | `EOP_Orders_Page` / templates admin | SPA e JS legado listam/leem/editam via REST primeiro; fallback AJAX permanece temporario | `parcial` |
| settings | `eop_settings` e `eop_pdf_settings` | `EOP_Settings`, `EOP_PDF_Settings` e renderers por dominio | SPA edita `general` com schema dedicado, `store` via `EOP_PDF_Settings`, secoes seguras via schema generico escalar e seletores ricos tentam REST antes de AJAX | `parcial` |
| proposal public | `eop_settings`, token, pedido | `EOP_Public_Proposal` | preview por renderer compartilhado usando `customer_experience_*` como fonte principal | `parcial` |
| confirmation contract | `eop_settings`, pedido, post flow | `EOP_Post_Confirmation_Flow` | preview dedicado cobre documento principal, documentos secundarios e botoes auxiliares; arrays/documentos ricos seguem no legado | `parcial` |
| confirmation upload/products | `eop_settings`, pedido, post flow | `EOP_Post_Confirmation_Flow` | preview dedicado mostra os estados `upload` e `products`, produto bloqueado, SKU vazio e botoes por etapa | `parcial` |
| pdf | `eop_pdf_settings`, pedido | `EOP_Document_Manager` | preview lateral segue ativo mesmo com `advanced_html_output`; `store` no SPA usa `shop_*` reais | `parcial` |
| documentation | `docs/`, `README.md`, `AGENT.md`, `LICENSE`, `readme.txt` | hub de documentacao admin | documentacao canonica consolidada e planos legados arquivados | `feito` |

## Itens obrigatorios antes do cutover

- [x] `feito` - Consolidar documentacao canonica minima em `docs/`
- [x] `feito` - Formalizar ownership, licenca proprietaria e politica para IA
- [x] `feito` - Criar feature flag e fallback limpo para o admin legado
- [x] `feito` - Expor namespace REST administrativo `aireset-expresso-order/v1/admin`
- [x] `feito` - Entregar contratos REST de pedidos para listagem, leitura, criacao e atualizacao
- [x] `feito` - Normalizar busca de cliente, busca de produto e calculo de frete para o SPA
- [x] `feito` - Fazer o JS legado de novo pedido/pedidos consumir REST primeiro, com fallback temporario para `admin-ajax.php`
- [x] `feito` - Migrar lazy-view, abas PDF e seletores de categoria/produto do admin legado para REST-first com fallback temporario
- [x] `feito` - Migrar troca manual de etapa do fluxo complementar para REST-first com fallback temporario
- [ ] `parcial` - Migrar settings por dominio sem enviar arrays ou estruturas nao editaveis
- [ ] `parcial` - Garantir uma chave, um dono e um renderer por ajuste configuravel; `store` ja foi realinhado para `EOP_PDF_Settings`
- [ ] `parcial` - Garantir que todo preview reflita o renderer final com contrato unico; `new-order`, proposta, contrato, upload/produtos e PDF ja receberam correcoes de paridade
- [ ] `parcial` - Validar capability por role para admin novo e legado em fluxo real
- [ ] `parcial` - Medir baseline de performance antes e depois da migracao; script automatizado criado, ainda falta comparar antes/depois em ambiente alvo
- [ ] `parcial` - Executar smoke tests de criacao e edicao real de pedido no admin SPA; smoke REST e browser smoke administrativo ja validados
- [ ] `pendente` - Remover rotas, telas e assets legados somente apos equivalencia comprovada

## Fases

### Fase 1 - Fundacao - `feito`

- [x] `feito` - Documentacao canonica consolidada em `docs/`
- [x] `feito` - Planos legados movidos para `docs/archive/2026-05/`
- [x] `feito` - `README.md`, `AGENT.md`, `LICENSE`, `readme.txt` e `.github/copilot-instructions.md` alinhados a ownership e licenca proprietaria
- [x] `feito` - Estrutura `React + TypeScript + Vite` criada para o novo admin SPA
- [x] `feito` - Feature flag administrativa criada para convivencia com o admin legado
- [x] `feito` - Namespace REST administrativo criado

### Fase 2 - Shell novo - `parcial`

- [x] `feito` - Navegacao basica do novo admin SPA criada
- [x] `feito` - Consumo de `GET /bootstrap` implementado
- [x] `feito` - Fallback para abrir a view equivalente no admin legado
- [x] `feito` - Hub de documentacao canonica exposto no admin
- [ ] `parcial` - Build Vite e manifest preparados, mas cutover ainda depende de validacao operacional do bundle em producao
- [ ] `pendente` - Tornar o shell novo a experiencia padrao para usuarios finais

### Fase 3 - Settings por dominio - `parcial`

- [x] `feito` - `settings-general-config` editavel no SPA com schema dedicado
- [x] `feito` - `store`, `proposal`, `new-order`, `orders-list`, confirmacao e `pdf` expostos por schema generico para valores escalares seguros
- [x] `feito` - `store` realinhado para editar `EOP_PDF_Settings`/`shop_*`, removendo `pdf_company_*` do caminho primario do SPA
- [x] `feito` - Salvamento do SPA restrito as chaves declaradas no schema de campos
- [x] `feito` - Seletores Select2 de produto/categoria das telas de settings tentam REST administrativo antes de `admin-ajax.php`
- [ ] `parcial` - Seletores ricos, arrays, media library, color picker e estruturas compostas ainda dependem do admin legado
- [ ] `parcial` - Duplicidade de settings entre telas ainda precisa ser reduzida por dominio
- [x] `feito` - Retirar assets globais do shell legado para `frontend.css`, PDF, Select2, media, editor, Coloris, fontselect, `settings-admin.js` e flyout global

### Fase 4 - Orders - `parcial`

- [x] `feito` - `GET /orders` implementado para listagem
- [x] `feito` - `GET /orders/{id}` implementado para leitura
- [x] `feito` - `POST /orders` implementado para criacao de pedido no SPA
- [x] `feito` - `PUT /orders/{id}` implementado para edicao real de pedidos existentes
- [x] `feito` - Busca de cliente, busca de produtos e calculo de frete normalizados no REST admin
- [x] `feito` - SPA edita cliente, endereco, descontos, itens existentes, novos produtos e metodo de frete
- [x] `feito` - Admin legado passa a tentar REST antes de `admin-ajax.php` para listagem, leitura e salvamento de pedidos
- [ ] `parcial` - Admin legado segue ativo como fallback e ainda precisa de equivalencia validada antes do cutover
- [ ] `parcial` - Browser smoke valida carregamento e interacao basica em `orders`; criacao e edicao real de pedidos ainda pendentes no navegador

### Fase 5 - Previews - `parcial`

- [x] `feito` - `new-order` usa iframe com pagina real do shortcode e lazy-load legado tambem usa `templates/shortcode-page.php`
- [x] `feito` - `proposal` usa renderer compartilhado no admin e prioriza `customer_experience_title`, `customer_experience_description`, `customer_experience_text_color` e `customer_experience_muted_color`
- [x] `feito` - `confirmation-contract` usa renderer admin dedicado baseado no fluxo complementar e mostra documentos secundarios configurados
- [x] `feito` - `confirmation-upload-products` usa renderer admin dedicado baseado no fluxo complementar e mostra os estados `upload` e `products`
- [ ] `parcial` - Contrato REST unico de previews existe e browser smoke cobre telas administrativas; ainda depende de dados reais de pedido/anexo/documentos
- [x] `feito` - Smoke REST administrativo automatizado cobre bootstrap, settings, previews e listagem de pedidos via WP-CLI
- [x] `feito` - Browser smoke automatizado cobre shell admin, `new-order`, `orders`, `/pedido-expresso/`, previews de configuracao, PDF e SPA por feature flag
- [x] `feito` - Lazy-load de views e abas PDF do admin legado tentam REST administrativo antes de `admin-ajax.php`

### Fase 6 - Cutover - `pendente`

- [ ] `pendente` - Ativar novo admin por feature flag controlada para uso operacional real
- [ ] `pendente` - Medir performance e regressao antes/depois
- [ ] `pendente` - Validar roles, capabilities e nonce em todos os fluxos migrados
- [ ] `pendente` - Remover rotas e assets legados somente apos equivalencia comprovada

## Aceite

- [x] `feito` - Documentacao canonica reduzida ao pacote minimo
- [x] `feito` - Politica legal e de IA sem contradicao documental
- [x] `feito` - Admin novo acessivel por feature flag
- [ ] `parcial` - Backlog remanescente centralizado neste arquivo
- [ ] `pendente` - Cutover funcional validado com smoke tests e baseline de performance
