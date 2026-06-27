# Padrão Aireset — Comando remoto de licença assinado (kill-switch seguro)

> Documento canônico. Vale para **TODOS** os plugins `aireset-*` que usam o Elite
> Licenser. Este plugin (`aireset-expresso-order`) é a implementação de referência.

## 1. O problema (presente em todo cliente Elite Licenser)

O SDK do Elite Licenser registra, no hook `init`, um handler de comando remoto que
permite ao servidor de licenças **resetar a licença (`rl`/`rc`) ou DELETAR o plugin
(`dl`)** de uma instalação. A "autenticação" era:

```php
$handler = hash( 'crc32b', $product_id . $key . $domain ) . '_handle';
if ( $_GET['action'] === $handler ) { $this->handle_server_request(); }
```

Falhas:

- `product_id` e `domain` são **públicos**.
- `$key` (chave do produto) é **embarcada em todo cliente** — qualquer licenciado
  (ou cópia vazada) extrai do código, mesmo ofuscado (base64+gzip+eval é reversível).
- `crc32b` é checksum, **não é assinatura**.

Resultado: qualquer pessoa com uma cópia do plugin computa o token de **qualquer
site** e dispara `/?action=<token>&type=dl` → plugin deletado, **sem login**.
(DoS/remoção remota não-autenticada.)

## 2. A solução — comando assinado (Ed25519)

Troca o segredo compartilhado por um **par de chaves assimétrico**:

- **Chave privada** → existe **apenas no servidor** `aireset.com.br`. Nunca é distribuída.
- **Chave pública** → embarcada em cada cliente. Não é segredo.

O servidor **assina** cada comando; o cliente **verifica** com a pública. Quem tem o
plugin só tem a pública → não consegue forjar. O comando é preso ao domínio, tem
validade curta (10 min) e nonce de uso único (anti-replay).

Um par de chaves **global** atende todos os plugins (a pública é a mesma em todos;
a privada vive uma vez no servidor).

## 3. Arquivos alterados (referência)

### 3.1 Cliente — `includes/class-eop-license-base.php` (`EOP_License_Core`)

- Constante com a chave pública:
  ```php
  const CMD_PUBKEY = '<hex da chave publica Ed25519>'; // vazia = comando remoto desativado
  ```
- `init_action_handler()` — **removido** o gatilho `crc32`/`md5`; agora só aceita
  `?eop_cmd=<base64(payload)>&eop_sig=<hex>` validado por `verify_signed_command()`.
- `verify_signed_command()` confere, em ordem: sodium disponível → pública configurada →
  assinatura válida → `domain === site_url()` → `product === product_id` →
  `type ∈ {rl,rc,dl}` → `expires` no futuro (≤ 24h) → `nonce` inédito (transient).
  Em sucesso define `$_GET['type']` e deixa `handle_server_request()` (inalterado) agir.

### 3.2 Servidor — `elite-licenser/models/database/Mapbd_license.php`

- `sign_remote_command( $user_domain, $product_id, $type )` — assina com a privada lida
  da option `el_cmd_signing_sk` (hex). Retorna `&eop_cmd=...&eop_sig=...` ou `''`.
- `RequestRemoteAction( ..., $product_id = '' )` — anexa o sufixo assinado quando há
  `product_id`. **Mantém** o `?action=<crc32>` legado para clientes ainda não migrados.
- Callers `RemoveLicenseFromDomain` (rl), `RemovePluginFromDomain` (dl),
  `ResetLicenseFromDomain` (rc) e `GetRemovePluginUrlFromDomain` passam `product_id`.
- Removido o `file_put_contents(.../remove_log.txt, $product_enc_key)` que vazava a
  chave do produto em arquivo público.

## 4. Geração e instalação das chaves (uma vez)

```bash
php -r "$k=sodium_crypto_sign_keypair();echo 'PUBLICA  -> '.sodium_bin2hex(sodium_crypto_sign_publickey($k)).PHP_EOL.'PRIVADA  -> '.sodium_bin2hex(sodium_crypto_sign_secretkey($k)).PHP_EOL;"
```

- **PÚBLICA** → `const CMD_PUBKEY` de cada plugin cliente (antes de buildar/ofuscar).
- **PRIVADA** → no servidor: `wp option update el_cmd_signing_sk '<hex>'`
  (preferível: constante em `wp-config.php` fora do banco).

Guarde a privada com cuidado. Se vazar, gere outro par e re-embarque a pública.

## 5. Rollout

1. Servidor passa a mandar os **dois** formatos (assinado + crc32 legado).
2. Cada plugin cliente migrado **ignora** o crc32 e só age no assinado.
3. Plugins ainda não migrados continuam no crc32 até receberem este patch.
4. Quando **todos** estiverem migrados, remover o ramo `crc32`/`md5` do servidor.

⚠️ O `domain` assinado deve bater com o `site_url()` do cliente (mesmo esquema
`https://`, sem barra final) — mesma exigência do sistema legado.

## 6. Checklist por plugin `aireset-*`

- [ ] `const CMD_PUBKEY` adicionada com a pública oficial.
- [ ] `init_action_handler()` sem o gatilho `crc32`/`md5`.
- [ ] `verify_signed_command()` presente (copiar da referência).
- [ ] `product_id` único e correto; `enc_key` própria por produto.
- [ ] `php -l` sem erros; teste de assinatura (ver `scripts/`/QA) passando.
- [ ] Build re-ofusca os arquivos de licença antes do release.
- [ ] Deploy + validar 1 comando real `rl` num site de teste.

## 7. Plugins Aireset a migrar (preencher)

| Plugin | product_id | Migrado | Licença no padrão |
|---|---|---|---|
| aireset-expresso-order | 2 | ✅ (chave a colar) | ✅ |
| aireset-checkout | ? | ⬜ | ⬜ |
| aireset-default | ? | ⬜ | ⬜ |
| aireset-frete-beneficio | ? | ⬜ | ⬜ |
| aireset-side-cart-premium | ? | ⬜ | ⬜ |
| aireset-admin-shipping-calc | ? | ⬜ | ⬜ |
| aireset-elementor-hermes | ? | ⬜ | ⬜ |
| (demais) | | ⬜ | ⬜ |

> Itens em aberto de segurança do `aireset-expresso-order` (não relacionados ao
> kill-switch) estão no relatório de revisão / `CHANGELOG`.
