# Conclusão do plugin — status vivo

Objetivo: terminar o plugin inteiro (navegação, performance, migração React, padrão visual, personalização, falhas, segurança) e deixar pronto.

Branch: `feat/conclusao-plugin`. Modo: autônomo com checkpoints (Felipe valida cada frente).

## Frentes

| Frente | O que resolve | Status |
|---|---|---|
| **A. Design system** | Padrão visual único (tokens) — fim dos "layouts divergentes" | ✅ accent unificado (token-math deferido p/ frentes B/C, baixo ROI) |
| **B. Terminar migração React** | Paridade SPA+frontend, matar legado/jQuery | ⬜ a fazer |
| **C. Navegação + performance** | Reorganizar IA/menu, lazy-load, telas rápidas | 🟡 phone-home licença cacheado (admin ~3s→~0) |
| **D. Personalização** | Expandir opções de customização | ⬜ |
| **E. Falhas funcionais** | Bugs, etapa products morta, mobile (EMAIL FORA: vendedor usa ao vivo no WhatsApp) | 🟡 confirm idempotente + fallback contrato + ViaCEP timeout |
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

## Decisões do Felipe
- **Accent canônico** (A): ✅ dourado `#d78a2f`.
- **SDK de licença** (F): ✅ Felipe **controla o servidor** (aireset.com.br) → corrigir canal autenticado de verdade (cliente+servidor).
- **PDF** (G): ✅ **embarcar Dompdf** (Composer+Strauss) dentro do plugin.

## Log
- 2026-06-10: branch criada. Frente A iniciada (mapeamento de tokens). Commit anterior `1a5f4e4` (hero do fluxo) em `chore/fase0-prontidao-venda`.
- 2026-06-10: A — accent unico dourado aplicado (`9767e0c`). Decisao: token-math (raio/sombra) NAO re-plumbado agora (cores quase identicas, ROI baixo); alinho componente a componente conforme toco telas em B/C.
- 2026-06-10: E — confirm de proposta idempotente (nao duplica nota/redispara). Contrato com botao "Abrir em nova aba" sobreposto (resolve iframe PDF branco no iOS Safari).
- DEFERIDO p/ Frente F: Dompdf `isRemoteEnabled=false` (SSRF CRIT) — flipar direto quebra logo no PDF; precisa resolver imagens confiaveis p/ caminho local/base64 antes.
- 2026-06-10 (Frente C perf): causa raiz da lentidão do admin = **phone-home de licença bloqueante** a `aireset.com.br` em TODO request (TTFB medido 1.7-5.7s via curl). Root cause no `_check_wp_plugin` (`class-eop-license-base.php:236`): quando o servidor não envia `request_duration`, `next_request = time()` → cache expira na hora → revalida toda vez. Fix: floor `+12h` (memoiza resultado válido; expiração e revogação remota seguem honradas) + timeout `_request` 120s→12s. Self-healing: 1 phone-home pós-deploy, depois cacheado 12h. **Precisa deploy na live** (sem CI deploy; só release-zip). Sem mudança na lógica de validação.
