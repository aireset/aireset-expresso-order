import { useEffect, useState } from 'react';
import type { NewOrderDraft, OrderDetail, OrderItem, ProductResult, ShippingPackage } from './app/types';
import { adminApi } from './app/api';
import ProductSelect2 from './ProductSelect2';

function createInitialDraft(): NewOrderDraft {
  return {
    customer: { user_id: 0, name: '', email: '', phone: '', document: '' },
    items: [],
    shipping: 0,
    shipping_method: '',
    shipping_address: { postcode: '', state: '', city: '', address: '', number: '', neighborhood: '', address_2: '' },
    shipping_rate: null,
    discount: 0,
    discount_type: 'fixed',
    status: 'completed',
  };
}

function parseNumber(value: string): number {
  const parsed = Number.parseFloat(value.replace(',', '.'));
  return Number.isFinite(parsed) ? parsed : 0;
}

function formatCurrency(value: number): string {
  try {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
  } catch {
    return `R$ ${value.toFixed(2)}`;
  }
}

function discountedUnitPrice(item: OrderItem): number {
  const discount = item.discount_type === 'percent' ? (item.price * item.discount_value) / 100 : item.discount_value;
  return Math.max(0, item.price - discount);
}

function discountInputValue(item: OrderItem): string {
  if (!item.discount_value) {
    return '';
  }
  return item.discount_type === 'percent' ? `${item.discount_value}%` : `${item.discount_value}`;
}

function parseDiscountInput(raw: string): { type: 'fixed' | 'percent'; value: number } {
  const isPercent = raw.includes('%');
  return { type: isPercent ? 'percent' : 'fixed', value: parseNumber(raw.replace('%', '')) };
}

function onlyDigits(value: string): string {
  return value.replace(/\D/g, '');
}

function formatCep(value: string): string {
  const digits = onlyDigits(value).slice(0, 8);
  return digits.length > 5 ? `${digits.slice(0, 5)}-${digits.slice(5)}` : digits;
}

function formatDocument(value: string): string {
  const digits = onlyDigits(value).slice(0, 14);

  if (digits.length <= 11) {
    const match = digits.match(/^(\d{0,3})(\d{0,3})(\d{0,3})(\d{0,2})$/);
    if (!match) {
      return digits;
    }
    let out = match[1];
    if (match[2]) out += `.${match[2]}`;
    if (match[3]) out += `.${match[3]}`;
    if (match[4]) out += `-${match[4]}`;
    return out;
  }

  const match = digits.match(/^(\d{0,2})(\d{0,3})(\d{0,3})(\d{0,4})(\d{0,2})$/);
  if (!match) {
    return digits;
  }
  let out = match[1];
  if (match[2]) out += `.${match[2]}`;
  if (match[3]) out += `.${match[3]}`;
  if (match[4]) out += `/${match[4]}`;
  if (match[5]) out += `-${match[5]}`;
  return out;
}

type CreatedInfo = { id: number; pdfUrl: string; publicUrl: string; orderUrl: string };

function draftFromOrder(order: OrderDetail): NewOrderDraft {
  return {
    customer: { ...order.customer },
    items: order.items.map((item) => ({ ...item })),
    shipping: order.shipping,
    shipping_method: order.shipping_method,
    shipping_address: { ...order.shipping_address },
    shipping_rate: null,
    discount: order.discount,
    discount_type: order.discount_type,
    status: order.status,
  };
}

function NewOrderForm({ orderId, onExit }: { orderId?: number; onExit?: () => void } = {}) {
  const isEdit = typeof orderId === 'number' && orderId > 0;
  const [draft, setDraft] = useState<NewOrderDraft>(createInitialDraft());
  const [loadingOrder, setLoadingOrder] = useState<boolean>(isEdit);
  const [savedMessage, setSavedMessage] = useState<string>('');
  const [orderNotes, setOrderNotes] = useState<string>('');
  const [customerState, setCustomerState] = useState<'idle' | 'loading' | 'found' | 'not-found' | 'error'>('idle');
  const [customerMessage, setCustomerMessage] = useState('');
  const [packages, setPackages] = useState<ShippingPackage[]>([]);
  const [shippingState, setShippingState] = useState<'idle' | 'loading' | 'done' | 'error'>('idle');
  const [shippingMessage, setShippingMessage] = useState('');
  const [saveState, setSaveState] = useState<'idle' | 'saving' | 'error'>('idle');
  const [saveMessage, setSaveMessage] = useState('');
  const [created, setCreated] = useState<CreatedInfo | null>(null);
  const [defaultQty, setDefaultQty] = useState<string>('1');
  const [defaultDiscount, setDefaultDiscount] = useState<string>('');
  const [customerOpen, setCustomerOpen] = useState<boolean>(false);
  const [paymentOpen, setPaymentOpen] = useState<boolean>(false);
  const [shippingOpen, setShippingOpen] = useState<boolean>(false);
  const [cepStatus, setCepStatus] = useState<string>('');

  useEffect(() => {
    if (!isEdit || !orderId) {
      return;
    }

    let cancelled = false;
    setLoadingOrder(true);

    adminApi
      .getOrder(orderId)
      .then((payload) => {
        if (!cancelled) {
          setDraft(draftFromOrder(payload.order));
          setOrderNotes(payload.order.notes || '');
        }
      })
      .catch((error) => {
        if (!cancelled) {
          setSaveState('error');
          setSaveMessage(error instanceof Error ? error.message : 'Falha ao carregar pedido.');
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoadingOrder(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [isEdit, orderId]);


  function update(updater: (current: NewOrderDraft) => NewOrderDraft) {
    setDraft((current) => updater(current));
    setSaveState('idle');
    setSaveMessage('');
  }

  async function lookupCep(rawCep: string) {
    const digits = onlyDigits(rawCep);

    if (digits.length !== 8) {
      return;
    }

    setCepStatus('Buscando endereco pelo CEP...');

    try {
      const response = await fetch(`https://viacep.com.br/ws/${digits}/json/`);
      const data = await response.json();

      if (data?.erro) {
        setCepStatus('Nao encontramos esse CEP. Preencha o endereco manualmente.');
        return;
      }

      update((current) => ({
        ...current,
        shipping_address: {
          ...current.shipping_address,
          state: data.uf || current.shipping_address.state,
          city: data.localidade || current.shipping_address.city,
          address: data.logradouro || current.shipping_address.address,
          neighborhood: data.bairro || current.shipping_address.neighborhood,
        },
      }));
      setCepStatus('Endereco encontrado. Confira o numero e o complemento.');
    } catch {
      setCepStatus('Nao foi possivel buscar o CEP agora. Continue manualmente.');
    }
  }

  function applyDefaults() {
    const qty = Math.max(1, Math.round(parseNumber(defaultQty)));
    const raw = defaultDiscount.trim();
    const isPercent = raw.includes('%');
    const discountValue = parseNumber(raw.replace('%', ''));

    update((current) => ({
      ...current,
      items: current.items.map((item) => ({
        ...item,
        quantity: qty,
        discount_type: isPercent ? 'percent' : 'fixed',
        discount_value: discountValue,
      })),
    }));
  }

  const itemsTotal = draft.items.reduce((total, item) => {
    const lineTotal = item.price * item.quantity;
    const discount =
      item.discount_type === 'percent'
        ? Math.min(lineTotal, (lineTotal * item.discount_value) / 100)
        : Math.min(lineTotal, item.discount_value);
    return total + Math.max(0, lineTotal - discount);
  }, 0);
  const generalDiscount = draft.discount_type === 'percent' ? (itemsTotal * draft.discount) / 100 : draft.discount;
  const total = Math.max(0, itemsTotal + draft.shipping - generalDiscount);

  async function lookupCustomer() {
    setCustomerState('loading');
    setCustomerMessage('');
    try {
      const payload = await adminApi.searchCustomer(onlyDigits(draft.customer.document));
      if (payload.found) {
        update((current) => ({
          ...current,
          customer: {
            ...current.customer,
            user_id: payload.user_id,
            name: payload.name,
            email: payload.email,
            phone: payload.phone,
            document: payload.document,
          },
        }));
        setCustomerState('found');
        setCustomerMessage('Cliente encontrado e preenchido.');
      } else {
        update((current) => ({ ...current, customer: { ...current.customer, user_id: 0, document: payload.document } }));
        setCustomerState('not-found');
        setCustomerMessage('Cliente nao encontrado. Preencha os dados para criar ou vincular pelo e-mail.');
      }
    } catch (error) {
      setCustomerState('error');
      setCustomerMessage(error instanceof Error ? error.message : 'Falha ao buscar cliente.');
    }
  }

  function setItemQty(index: number, qty: number) {
    update((current) => ({
      ...current,
      items: current.items.map((item, i) => (i === index ? { ...item, quantity: Math.max(1, qty || 1) } : item)),
    }));
  }

  function setItemDiscount(index: number, raw: string) {
    const parsed = parseDiscountInput(raw);
    update((current) => ({
      ...current,
      items: current.items.map((item, i) =>
        i === index ? { ...item, discount_type: parsed.type, discount_value: parsed.value } : item
      ),
    }));
  }

  function addProduct(product: ProductResult) {
    update((current) => {
      const index = current.items.findIndex((item) => item.product_id === product.id);
      if (index >= 0) {
        return {
          ...current,
          items: current.items.map((item, i) => (i === index ? { ...item, quantity: item.quantity + 1 } : item)),
        };
      }
      return {
        ...current,
        items: [
          ...current.items,
          {
            product_id: product.id,
            name: product.name,
            sku: product.sku,
            price: product.price,
            quantity: 1,
            discount_type: 'fixed',
            discount_value: 0,
            image: product.image,
          },
        ],
      };
    });
  }

  async function calculateShipping() {
    setShippingState('loading');
    setShippingMessage('');
    setPackages([]);
    try {
      const payload = await adminApi.calculateShipping(draft.items, draft.shipping_address);
      const firstRate = payload.rates.flatMap((pkg) => pkg.rates)[0] || null;
      setPackages(payload.rates);
      setShippingState('done');
      setShippingMessage(firstRate ? 'Fretes calculados.' : 'Nenhuma opcao de frete encontrada.');
      if (firstRate) {
        update((current) => ({ ...current, shipping: firstRate.cost, shipping_method: firstRate.label, shipping_rate: firstRate }));
      }
    } catch (error) {
      setShippingState('error');
      setShippingMessage(error instanceof Error ? error.message : 'Falha ao calcular frete.');
    }
  }

  async function save() {
    setSaveState('saving');
    setSaveMessage('');
    setSavedMessage('');

    const payloadDraft = {
      ...draft,
      customer: { ...draft.customer, document: onlyDigits(draft.customer.document) },
    };

    try {
      if (isEdit && orderId) {
        await adminApi.updateOrder(orderId, { ...payloadDraft, order_id: orderId, notes: orderNotes });
        setSaveState('idle');
        setSavedMessage('Pedido atualizado com sucesso.');
      } else {
        const payload = await adminApi.createOrder(payloadDraft);
        setCreated({
          id: payload.result.order_id,
          pdfUrl: payload.result.pdf_url || '',
          publicUrl: payload.result.public_url || '',
          orderUrl: payload.order?.edit_url || payload.result.order_url || '',
        });
      }
    } catch (error) {
      setSaveState('error');
      setSaveMessage(error instanceof Error ? error.message : 'Falha ao salvar pedido.');
    }
  }

  function resetForm() {
    setDraft(createInitialDraft());
    setPackages([]);
    setCreated(null);
    setSaveState('idle');
    setSaveMessage('');
    setCustomerState('idle');
    setCustomerMessage('');
  }

  if (created) {
    return (
      <div className="eop-card">
        <h2>Pedido criado!</h2>
        <p>Pedido #{created.id} criado com sucesso.</p>
        <div className="eop-order-card__actions">
          {created.publicUrl ? (
            <a className="eop-btn eop-btn-primary" href={created.publicUrl} target="_blank" rel="noreferrer">
              Link do cliente
            </a>
          ) : null}
          {created.pdfUrl ? (
            <a className="eop-btn" href={created.pdfUrl} target="_blank" rel="noreferrer">
              PDF
            </a>
          ) : null}
          {created.orderUrl ? (
            <a className="eop-btn" href={created.orderUrl}>
              Ver pedido
            </a>
          ) : null}
          <button type="button" className="eop-btn" onClick={resetForm}>
            Novo pedido
          </button>
        </div>
      </div>
    );
  }

  if (loadingOrder) {
    return <div className="eop-card">Carregando pedido...</div>;
  }

  return (
    <>
      {isEdit ? (
        <div className="eop-editing-banner">
          <div>
            <strong>Editando pedido{orderId ? ` #${orderId}` : ''}</strong>
            <p>Voce esta ajustando um pedido existente dentro do painel.</p>
          </div>
          {onExit ? (
            <button type="button" className="eop-btn" onClick={onExit}>
              Voltar
            </button>
          ) : null}
        </div>
      ) : null}

      <div className="eop-pdv-grid">
      <div className="eop-pdv-main">
        <div className="eop-card">
          <h2>Produtos</h2>

          <div className="eop-item-defaults">
            <div className="eop-item-defaults__title">Acoes em massa</div>
            <div className="eop-field">
              <label>Quantidade</label>
              <input type="number" min="1" value={defaultQty} onChange={(event) => setDefaultQty(event.target.value)} />
            </div>
            <div className="eop-field">
              <label>Desconto</label>
              <input
                type="text"
                value={defaultDiscount}
                placeholder="10 ou 10%"
                onChange={(event) => setDefaultDiscount(event.target.value)}
              />
            </div>
            <div className="eop-field eop-item-defaults__action">
              <button type="button" className="eop-btn" onClick={applyDefaults} disabled={draft.items.length === 0}>
                Aplicar
              </button>
            </div>
          </div>

          <div className="eop-field">
            <ProductSelect2 onSelect={addProduct} />
          </div>

          <div className="eop-items-list">
            {draft.items.length === 0 ? <div className="eop-items-empty">Nenhum produto adicionado.</div> : null}
            {draft.items.map((item, index) => {
              const unit = discountedUnitPrice(item);
              return (
                <div className="eop-item-card" key={`${item.product_id}-${index}`}>
                  <button
                    type="button"
                    className="eop-remove-item"
                    title="Remover"
                    onClick={() => update((current) => ({ ...current, items: current.items.filter((_, i) => i !== index) }))}
                  >
                    ×
                  </button>
                  <div className="eop-item-card__left">
                    {item.image ? <img src={item.image} alt="" className="eop-item-card__img" /> : null}
                  </div>
                  <div className="eop-item-card__right">
                    <div className="eop-item-card__name">
                      {item.name}
                      {item.sku ? ` [${item.sku}]` : ''}
                    </div>
                    <div className="eop-item-card__fields">
                      <div className="eop-item-card__field">
                        <span className="eop-item-card__label">Preco</span>
                        <span className="eop-item-card__value">{formatCurrency(item.price)}</span>
                      </div>
                      <div className="eop-item-card__field">
                        <span className="eop-item-card__label">Qtd</span>
                        <div className="eop-qty-stepper">
                          <button type="button" className="eop-qty-btn eop-qty-dec" aria-label="Diminuir" onClick={() => setItemQty(index, item.quantity - 1)}>
                            −
                          </button>
                          <input
                            type="number"
                            className="eop-qty"
                            min="1"
                            value={item.quantity}
                            onChange={(event) => setItemQty(index, Math.round(parseNumber(event.target.value)))}
                          />
                          <button type="button" className="eop-qty-btn eop-qty-inc" aria-label="Aumentar" onClick={() => setItemQty(index, item.quantity + 1)}>
                            +
                          </button>
                        </div>
                      </div>
                      <div className="eop-item-card__field">
                        <span className="eop-item-card__label">Desconto</span>
                        <div className="eop-item-discount-group">
                          <input
                            type="text"
                            className="eop-item-discount"
                            inputMode="decimal"
                            value={discountInputValue(item)}
                            placeholder="0"
                            onChange={(event) => setItemDiscount(index, event.target.value)}
                          />
                        </div>
                      </div>
                      <div className="eop-item-card__field">
                        <span className="eop-item-card__label">Valor c/ desconto</span>
                        <span className="eop-item-card__value">{formatCurrency(unit)}</span>
                      </div>
                      <div className="eop-item-card__field">
                        <span className="eop-item-card__label">Subtotal</span>
                        <span className="eop-item-card__value">{formatCurrency(unit * item.quantity)}</span>
                      </div>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </div>

      <div className="eop-pdv-sidebar">
        <div className="eop-card eop-accordion">
          <button
            type="button"
            className="eop-accordion__toggle"
            aria-expanded={customerOpen}
            onClick={() => setCustomerOpen((value) => !value)}
          >
            <h2>Cliente (opcional)</h2>
            <span className="eop-accordion__icon" aria-hidden="true">{customerOpen ? '−' : '+'}</span>
          </button>
          <div className="eop-accordion__body" hidden={!customerOpen}>
          <div className="eop-field">
            <label>CPF / CNPJ</label>
            <div className="eop-input-group">
              <input
                type="text"
                value={draft.customer.document}
                placeholder="000.000.000-00"
                inputMode="numeric"
                onChange={(event) =>
                  update((current) => ({ ...current, customer: { ...current.customer, document: formatDocument(event.target.value) } }))
                }
              />
              <button type="button" className="eop-btn" onClick={() => void lookupCustomer()} disabled={customerState === 'loading'}>
                {customerState === 'loading' ? '...' : 'Buscar'}
              </button>
            </div>
            {customerMessage ? <span className="eop-status">{customerMessage}</span> : null}
          </div>
          <div className="eop-field">
            <label>Nome</label>
            <input type="text" value={draft.customer.name} onChange={(event) => update((c) => ({ ...c, customer: { ...c.customer, name: event.target.value } }))} />
          </div>
          <div className="eop-field">
            <label>E-mail</label>
            <input type="email" value={draft.customer.email} onChange={(event) => update((c) => ({ ...c, customer: { ...c.customer, email: event.target.value } }))} />
          </div>
          <div className="eop-field">
            <label>WhatsApp</label>
            <input type="tel" value={draft.customer.phone} onChange={(event) => update((c) => ({ ...c, customer: { ...c.customer, phone: event.target.value } }))} />
          </div>
          </div>
        </div>

        <div className="eop-card">
          <div className="eop-accordion eop-totals-accordion">
            <button
              type="button"
              className="eop-accordion__toggle eop-totals-detail-toggle"
              aria-expanded={paymentOpen}
              onClick={() => setPaymentOpen((value) => !value)}
            >
              <span>Ver detalhes de pagamento</span>
              <span className="eop-accordion__icon" aria-hidden="true">{paymentOpen ? '−' : '+'}</span>
            </button>
            <div className="eop-accordion__body" hidden={!paymentOpen}>
              <div className="eop-shipping-box">
                <button
                  type="button"
                  className="eop-shipping-toggle"
                  aria-expanded={shippingOpen}
                  onClick={() => setShippingOpen((value) => !value)}
                >
                  <span className="eop-shipping-toggle__copy">
                    <strong>Entrega e frete</strong>
                    <small>
                      {draft.shipping_rate
                        ? `${draft.shipping_method} - ${formatCurrency(draft.shipping)}`
                        : 'Preencha o endereco e escolha uma opcao de frete.'}
                    </small>
                  </span>
                  <span className="eop-shipping-toggle__icon" aria-hidden="true">{shippingOpen ? '−' : '+'}</span>
                </button>

                <div className="eop-shipping-panel" hidden={!shippingOpen}>
                  <div className="eop-field-row">
                    <div className="eop-field">
                      <label>CEP</label>
                      <input
                        type="text"
                        value={draft.shipping_address.postcode}
                        placeholder="00000-000"
                        inputMode="numeric"
                        onChange={(event) => {
                          const formatted = formatCep(event.target.value);
                          update((c) => ({ ...c, shipping_address: { ...c.shipping_address, postcode: formatted } }));
                          if (onlyDigits(formatted).length === 8) {
                            void lookupCep(formatted);
                          }
                        }}
                      />
                    </div>
                    <div className="eop-field">
                      <label>Estado</label>
                      <input type="text" value={draft.shipping_address.state} onChange={(event) => update((c) => ({ ...c, shipping_address: { ...c.shipping_address, state: event.target.value } }))} />
                    </div>
                  </div>
                  {cepStatus ? <span className="eop-status">{cepStatus}</span> : null}
                  <div className="eop-field-row">
                    <div className="eop-field">
                      <label>Cidade</label>
                      <input type="text" value={draft.shipping_address.city} onChange={(event) => update((c) => ({ ...c, shipping_address: { ...c.shipping_address, city: event.target.value } }))} />
                    </div>
                    <div className="eop-field">
                      <label>Numero</label>
                      <input type="text" value={draft.shipping_address.number} onChange={(event) => update((c) => ({ ...c, shipping_address: { ...c.shipping_address, number: event.target.value } }))} />
                    </div>
                  </div>
                  <div className="eop-field">
                    <label>Endereco</label>
                    <input type="text" value={draft.shipping_address.address} onChange={(event) => update((c) => ({ ...c, shipping_address: { ...c.shipping_address, address: event.target.value } }))} />
                  </div>
                  <div className="eop-field-row">
                    <div className="eop-field">
                      <label>Bairro</label>
                      <input type="text" value={draft.shipping_address.neighborhood} onChange={(event) => update((c) => ({ ...c, shipping_address: { ...c.shipping_address, neighborhood: event.target.value } }))} />
                    </div>
                    <div className="eop-field">
                      <label>Complemento</label>
                      <input type="text" value={draft.shipping_address.address_2} onChange={(event) => update((c) => ({ ...c, shipping_address: { ...c.shipping_address, address_2: event.target.value } }))} />
                    </div>
                  </div>
                  <div className="eop-field">
                    <button type="button" className="eop-btn eop-btn-primary eop-btn-block" onClick={() => void calculateShipping()} disabled={shippingState === 'loading'}>
                      {shippingState === 'loading' ? 'Calculando...' : 'Buscar opcoes de frete'}
                    </button>
                  </div>
                  {shippingMessage ? <span className="eop-status">{shippingMessage}</span> : null}
                  {packages.length ? (
                    <div className="eop-shipping-rates">
                      {packages.flatMap((pkg) =>
                        pkg.rates.map((rate) => (
                          <button
                            key={`${pkg.package_key}-${rate.id}`}
                            type="button"
                            className={`eop-btn eop-btn-block ${draft.shipping_rate?.id === rate.id ? 'eop-btn-primary' : ''}`}
                            onClick={() => update((c) => ({ ...c, shipping: rate.cost, shipping_method: rate.label, shipping_rate: rate }))}
                          >
                            {rate.label} - {formatCurrency(rate.cost)}
                          </button>
                        ))
                      )}
                    </div>
                  ) : null}
                </div>
              </div>

              <div className="eop-field">
                <label>Desconto geral</label>
                <input
                  type="text"
                  className="eop-discount-text-input"
                  inputMode="decimal"
                  value={draft.discount ? (draft.discount_type === 'percent' ? `${draft.discount}%` : `${draft.discount}`) : ''}
                  placeholder="10% ou 10"
                  onChange={(event) => {
                    const parsed = parseDiscountInput(event.target.value);
                    update((c) => ({ ...c, discount_type: parsed.type, discount: parsed.value }));
                  }}
                />
              </div>

              <div className="eop-totals">
                <div className="eop-total-row">
                  <span>Subtotal:</span>
                  <span>{formatCurrency(itemsTotal)}</span>
                </div>
                <div className="eop-total-row">
                  <span>Frete:</span>
                  <span>{formatCurrency(draft.shipping)}</span>
                </div>
                <div className="eop-total-row">
                  <span>Desconto:</span>
                  <span>- {formatCurrency(generalDiscount)}</span>
                </div>
              </div>
            </div>
          </div>

          <div className="eop-totals-grand-always">
            <div className="eop-total-row eop-total-grand">
              <span>Total:</span>
              <span>{formatCurrency(total)}</span>
            </div>
          </div>
        </div>

        <div className="eop-card">
          <div className="eop-field">
            <label>Status</label>
            <select value={draft.status} onChange={(event) => update((c) => ({ ...c, status: event.target.value }))}>
              <option value="completed">Concluido</option>
              <option value="pending">Pendente</option>
              <option value="processing">Processando</option>
              <option value="on-hold">Aguardando</option>
            </select>
          </div>

          {saveMessage ? <span className="eop-notice eop-notice-error">{saveMessage}</span> : null}
          {savedMessage ? <span className="eop-status">{savedMessage}</span> : null}

          <button type="button" className="eop-btn eop-btn-primary eop-btn-block" onClick={() => void save()} disabled={saveState === 'saving' || draft.items.length === 0}>
            {saveState === 'saving'
              ? isEdit
                ? 'Salvando...'
                : 'Criando...'
              : isEdit
                ? 'Salvar alteracoes'
                : 'Finalizar e gerar PDF'}
          </button>
        </div>
      </div>
      </div>
    </>
  );
}

export default NewOrderForm;
