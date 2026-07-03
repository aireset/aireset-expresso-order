# Plano UX — Proposta / Fluxo (Expresso Order)

Backlog consolidado da revisão visual com o Felipe. Ordenado por valor + dependência + risco.
Status: ⬜ a fazer · 🟡 em andamento · ✅ feito. Esforço: P/M/G.

> Princípio: cada item publica no demo (orçamento.vnz) com versão + changelog, valida com print
> (claro/escuro × desktop/mobile quando aplicável), e só commita após OK do Felipe.

---

## Fase 1 — Heros das etapas 🟡 (rumo aprovado)
Esforço: M · Risco: médio (página do cliente)

- Unificar as DUAS heros hoje existentes (header-de-marca vs título-grande+badge) num **componente único**.
- Hero lidera pela **etapa** (título = a etapa, não a marca); logo discreto; +1 linha do porquê.
- **Contexto em chips:** Pedido #, Total (tabular-nums).
- Título/porquê **por etapa** (editável): pagamento, dados, contrato, upload, conclusão.
- Breadcrumb compacto, passo ativo **on-brand** (não cyan).
- Entrega: print de cada etapa (desktop + mobile).

---

## Fase 2 — Tema claro/escuro (motor) ⬜
Esforço: G · Risco: alto (refactor da página do cliente)

- Mover vars `--eop-preview-*` de `style=""` inline → bloco `<style>` escopado (inline não aceita @media/[data-theme]).
- Paleta **dark derivada** + overrides das cores hardcoded do stylesheet (fundo do resumo do item, bordas dos specs, verde do desconto).
- Aplicar dark via `[data-eop-theme="dark"]` **e** `@media (prefers-color-scheme:dark)` quando sem escolha manual.
- **Toggle no hero:** claro / escuro / sistema (ícones) + JS (localStorage, padrão "sistema").

## Fase 3 — Admin: dois temas + responsividade editáveis ⬜
Esforço: G · Risco: médio · depende da Fase 2

- Arquitetura de **camadas de override**: base (claro/desktop) + `escuro` + `mobile` + `escuro+mobile`; campo vazio herda.
- Admin: switcher **[Claro|Escuro] × [Desktop|Mobile]** em cada seção visual (cores, fontes, padding, margin — igual hoje, por camada).
- **Preview mobile** no admin (Desktop/Mobile × Claro/Escuro).
- Emissão CSS com os 4 `@media` correspondentes.

## Fase 4 — Botão fixo + Breadcrumb ⬜
Esforço: M · Risco: baixo

- **Botão "Confirmar" fixo** no rodapé (mobile) — `position:sticky` + toggle no admin.
- **Breadcrumb num lugar só:** consolidar as 2 configs duplicadas (`post_confirmation_contract_visual_breadcrumb_*` + `post_confirmation_visual_breadcrumb_*`) num grupo único (geral + ativo só-cores). Migrar valores salvos.

## Fase 5 — Seletor de fonte ⬜
Esforço: M · Risco: baixo

- Tipo de campo `font` no React (FontField): dropdown de fontes com **preview** + seletor de **espessura**.
- Mantém formato salvo `Familia:400,700` (compatível). Backend: liberar tipo `font` no schema (class-settings.php).

## Fase 6 — Resumo PDV + Flash F5 ⬜
Esforço: M · Risco: baixo

- **Serviços separados** no resumo do PDV (React/NewOrderForm) — no cliente/PDF já separa; falta na tela do vendedor.
- **Flash no F5:** proposta/PDV mostra defaults e troca pro configurado. Mandar bootstrap inline com os textos/branding já resolvidos (sem round-trip).

## Fase 7 — Documentos: placeholders no conteúdo ⬜
Esforço: M · Risco: médio (conteúdo jurídico) · **aguarda decisão do Felipe**

- Inserir os `{placeholders}` no conteúdo dos 3 docs. Decisão: **(A)** inserir no HTML atual, ou **(B)** recadastrar do PDF (HTML limpo + tokens).
- Mapa sugerido: `{billing_full_name}`/`{billing_company}`, `{billing_document}`, `{billing_full_address}`, `{order_number}`, `{contract_date}`, assinatura `{contract_datetime}`.

---

## Já entregue nesta revisão (referência)
kill-switch Ed25519 · card "Pagamento pendente" (gate) · desconto respeita `discount_mode` · frete R$0 oculto · paginação 196 pedidos · color picker (Coloris) aplica · barra admin fora do preview · selects Select2 (preload+filtro) · documentos: drag-drop + editor `setContent` v3 · PDF colunas (right-align) · gating do 'payment' no fluxo · etapa "Dados do cliente" no dropdown · "Atualizar etapa" sem recarregar lista · OrderCard unificado admin↔frontend · nocache no HTML.
