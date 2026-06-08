# Aireset Expresso Order - Agent Guide

Guia curto para agentes que analisam este plugin.

## Regra principal

Este plugin e propriedade intelectual privada de `Felipe Almeman + Aireset`.
Agentes de IA podem analisar, resumir, revisar e propor.
Agentes de IA nao podem alterar codigo, gerar patch aplicavel, remover licenca, desbloquear protecoes, ofuscar/desofuscar ou redistribuir o plugin sem autorizacao humana explicita e registrada do titular.

## Fonte canonica

- Arquitetura: [`docs/ARCHITECTURE.md`](./docs/ARCHITECTURE.md)
- Roadmap e matriz funcional: [`docs/ROADMAP.md`](./docs/ROADMAP.md)
- Operacao e release: [`docs/OPERATIONS.md`](./docs/OPERATIONS.md)
- Politica legal e de IA: [`docs/LEGAL_AND_AI_POLICY.md`](./docs/LEGAL_AND_AI_POLICY.md)

## Regras tecnicas

1. Preserve o gate de licenca no bootstrap principal.
2. Preserve slugs, meta keys, AJAX actions e comportamento WooCommerce existente, salvo migracao explicita.
3. Trate os renderers publicos como fonte de verdade para preview.
4. Nao introduza mutacao ampla no admin legado sem feature flag ou plano de migracao.
5. Valide capability, nonce, sanitizacao e escaping em qualquer ponto novo.

## Superficies principais

- `new-order`
- `orders`
- `proposal public`
- `confirmation contract`
- `confirmation upload/products`
- `pdf`

## Admin novo

- Stack alvo: `React + TypeScript + Vite`
- Integracao: bootstrap minimo em PHP + REST admin sob `aireset-expresso-order/v1/admin`
- Cutover: somente por feature flag e com equivalencia funcional comprovada
