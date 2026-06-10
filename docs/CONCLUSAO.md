# Conclusão do plugin — status vivo

Objetivo: terminar o plugin inteiro (navegação, performance, migração React, padrão visual, personalização, falhas, segurança) e deixar pronto.

Branch: `feat/conclusao-plugin`. Modo: autônomo com checkpoints (Felipe valida cada frente).

## Frentes

| Frente | O que resolve | Status |
|---|---|---|
| **A. Design system** | Padrão visual único (tokens) — fim dos "layouts divergentes" | 🟡 em andamento |
| **B. Terminar migração React** | Paridade SPA+frontend, matar legado/jQuery | ⬜ a fazer |
| **C. Navegação + performance** | Reorganizar IA/menu, lazy-load, telas rápidas | ⬜ |
| **D. Personalização** | Expandir opções de customização | ⬜ |
| **E. Falhas funcionais** | Bugs, etapa products morta, emails, mobile | ⬜ |
| **F. Segurança** | Canal licença, Dompdf SSRF, uploads | ⬜ (SDK licença depende de OK do Felipe) |
| **G. Conclusão** | PDF embarcado, i18n, uninstall, release | ⬜ |

## Frente A — Design system

**Problema:** 4 sistemas de token separados, 3 accents diferentes.
- `--eop-*` (frontend PDV/login): navy `#00034b` + accent azul `#3f66ff`
- `--eop-preview-*` (proposta, customizável): navy + dourado `#d78a2f`
- `--eop-post-flow-*` (fluxo): navy + dourado
- admin SPA `styles.css`: navy + teal `#32d1c7`

**Plano:**
1. Definir 1 paleta canônica (primário navy + 1 accent único — DECISÃO pendente: azul/dourado/teal/outro).
2. Token file único (cores, espaçamento, raio, sombra, tipografia) consumido por frontend + admin + defaults proposta/fluxo.
3. Alinhar componentes (botões, cards, inputs, chips) ao mesmo estilo nas telas.
4. Manter camada customizável (proposta/fluxo) lendo settings, mas default = paleta canônica.

**Checkpoints A:** (1) escolher accent → (2) tokens unificados → (3) aplicar PDV+admin → (4) validar visual.

## Decisões pendentes do Felipe
- **Accent canônico** (frente A): azul / dourado / teal / outro?
- **SDK de licença** (frente F): tem controle do servidor de licença? Pode quebrar ativação ao corrigir.

## Log
- 2026-06-10: branch criada. Frente A iniciada (mapeamento de tokens). Commit anterior `1a5f4e4` (hero do fluxo) em `chore/fase0-prontidao-venda`.
