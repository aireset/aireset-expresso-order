import { useCallback, useEffect, useState } from 'react';
import type { BootstrapPayload, OrderSummary, OrdersPayload } from './app/types';
import { adminApi, getAdminSpaConfig, getInlineBootstrap } from './app/api';
import NewOrderForm from './NewOrderForm';
import Select2 from './Select2';

function formatCurrency(value: number, currency: string): string {
  try {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: currency || 'BRL' }).format(value);
  } catch {
    return `${currency || 'BRL'} ${value.toFixed(2)}`;
  }
}

function FlowPill({ label, value, tone }: { label: string; value: string; tone: string }) {
  return (
    <span className={`eop-order-card__flow-pill${tone ? ` eop-order-card__flow-pill--${tone}` : ''}`}>
      <span className="eop-order-card__flow-pill-label">{label}</span>
      <strong className="eop-order-card__flow-pill-value">{value}</strong>
    </span>
  );
}

function OrderCard({
  order,
  onRefresh,
  onEdit,
}: {
  order: OrderSummary;
  onRefresh: () => void;
  onEdit: (id: number) => void;
}) {
  const flow = order.post_confirmation_flow_summary;
  const controls = flow?.stage_controls;
  const [stage, setStage] = useState<string>(controls?.current || '');
  const [updating, setUpdating] = useState<boolean>(false);
  const [stageError, setStageError] = useState<string>('');

  async function updateStage() {
    setUpdating(true);
    setStageError('');
    try {
      await adminApi.updateOrderStage(order.id, stage);
      onRefresh();
    } catch (error) {
      setStageError(error instanceof Error ? error.message : 'Nao foi possivel atualizar a etapa.');
    } finally {
      setUpdating(false);
    }
  }

  const docs = flow?.documents;
  const docsValue = docs ? `${docs.completed ?? 0}/${docs.total ?? 0}` : '0/0';
  const docsDone = docs ? (docs.completed ?? 0) >= (docs.total ?? 0) : false;
  const products = flow?.products;
  const productsValue = products ? `${products.completed ?? 0}/${products.editable ?? 0}` : '0/0';
  const productsDone = products ? (products.completed ?? 0) >= (products.editable ?? 0) : false;
  const contractAccepted = !!flow?.contract?.accepted;
  const attachmentUploaded = !!flow?.attachment?.uploaded;
  const attachmentOptional = flow?.attachment ? !flow.attachment.required : false;
  const finalPdfReady = !!flow?.final_pdf?.ready;

  return (
    <div className="eop-card eop-order-card">
      <div className="eop-order-card__header">
        <div>
          <div className="eop-order-card__number">{order.number}</div>
          <h3>{order.customer_name}</h3>
        </div>
        <span className="eop-order-card__status">{order.status_label || order.status}</span>
      </div>

      <div className="eop-order-card__meta">
        <div>
          <span>Data</span>
          <strong>{order.date_label || order.created_at}</strong>
        </div>
        <div>
          <span>Total</span>
          <strong>{formatCurrency(order.total, order.currency)}</strong>
        </div>
        <div>
          <span>Vendedor</span>
          <strong>{order.created_by_name || '—'}</strong>
        </div>
      </div>

      {flow?.active_for_order ? (
        <div className="eop-order-card__flow">
          <div className="eop-order-card__flow-head">
            <span>Fluxo complementar</span>
            <strong className="eop-order-card__flow-stage">{flow.stage_label || ''}</strong>
          </div>
          <div className="eop-order-card__flow-list">
            <FlowPill label="Contrato" value={contractAccepted ? 'Aceito' : 'Pendente'} tone={contractAccepted ? 'success' : 'warning'} />
            <FlowPill label="Campos" value={docsValue} tone={docsDone ? 'success' : 'info'} />
            <FlowPill
              label="Anexo"
              value={attachmentUploaded ? 'Enviado' : attachmentOptional ? 'Opcional' : 'Pendente'}
              tone={attachmentUploaded ? 'success' : attachmentOptional ? 'neutral' : 'warning'}
            />
            <FlowPill label="PDF final" value={finalPdfReady ? 'Pronto' : 'Pendente'} tone={finalPdfReady ? 'success' : 'warning'} />
            <FlowPill label="Produtos" value={productsValue} tone={productsDone ? 'success' : 'info'} />
          </div>
          {controls?.can_update && controls.options && controls.options.length ? (
            <div className="eop-order-card__flow-stage-controls">
              <label>Etapa do fluxo</label>
              <div className="eop-order-card__flow-stage-row">
                <Select2
                  value={stage}
                  options={controls.options.map((option) => ({ value: option.value, label: option.label }))}
                  onChange={setStage}
                  ariaLabel="Etapa do fluxo"
                />
                <button
                  type="button"
                  className="eop-btn eop-btn-primary"
                  onClick={() => void updateStage()}
                  disabled={updating}
                >
                  {updating ? '...' : 'Atualizar etapa'}
                </button>
              </div>
              {stageError ? <p className="eop-notice eop-notice-error">{stageError}</p> : null}
            </div>
          ) : null}
        </div>
      ) : null}

      <div className="eop-order-card__actions">
        {order.public_url ? (
          <a className="eop-btn eop-btn-primary" href={order.public_url} target="_blank" rel="noreferrer">
            Link do cliente
          </a>
        ) : null}
        {order.pdf_url ? (
          <a className="eop-btn eop-btn-primary" href={order.pdf_url} target="_blank" rel="noreferrer">
            PDF
          </a>
        ) : null}
        <button type="button" className="eop-btn eop-btn-primary" onClick={() => onEdit(order.id)}>
          Editar aqui
        </button>
      </div>
    </div>
  );
}

type View = 'new-order' | 'orders';

function FrontendApp() {
  const [bootstrap, setBootstrap] = useState<BootstrapPayload | null>(() => getInlineBootstrap());
  const [view, setView] = useState<View>('new-order');
  const [orders, setOrders] = useState<OrdersPayload | null>(null);
  const [search, setSearch] = useState<string>('');
  const [statusFilter, setStatusFilter] = useState<string>('any');
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string>('');
  const [editingOrderId, setEditingOrderId] = useState<number | null>(null);

  useEffect(() => {
    if (bootstrap || !getAdminSpaConfig()) {
      return;
    }

    adminApi
      .getBootstrap()
      .then((payload) => setBootstrap(payload))
      .catch(() => {
        /* branding e opcional aqui */
      });
  }, []);

  const loadOrders = useCallback(() => {
    if (!getAdminSpaConfig()) {
      return;
    }

    setLoading(true);
    setError('');

    adminApi
      .getOrders()
      .then((payload) => setOrders(payload))
      .catch((fetchError) => setError(fetchError instanceof Error ? fetchError.message : 'Nao foi possivel carregar os pedidos agora.'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    if (view === 'orders') {
      loadOrders();
    }
  }, [view, loadOrders]);

  const title = bootstrap?.branding.panelTitle || 'Pedido Expresso';
  const subtitle = bootstrap?.branding.panelSubtitle || '';

  const filteredItems = (orders?.items ?? []).filter((order) => {
    const matchesStatus = statusFilter === 'any' || order.status === statusFilter;
    const term = search.trim().toLowerCase();
    const matchesSearch =
      term === '' ||
      order.number.toLowerCase().includes(term) ||
      order.customer_name.toLowerCase().includes(term) ||
      (order.customer_email || '').toLowerCase().includes(term);
    return matchesStatus && matchesSearch;
  });

  return (
    <div className="eop-pdv">
      <div className="eop-pdv-header">
        <div className="eop-pdv-header__content">
          <h1>{title}</h1>
          {subtitle ? <p className="eop-pdv-subtitle">{subtitle}</p> : null}

          <div className="eop-pdv-nav" role="tablist">
            <button
              type="button"
              className={`eop-pdv-nav__item ${view === 'new-order' ? 'is-active' : ''}`}
              aria-selected={view === 'new-order'}
              onClick={() => {
                setEditingOrderId(null);
                setView('new-order');
              }}
            >
              Novo pedido
            </button>
            <button
              type="button"
              className={`eop-pdv-nav__item ${view === 'orders' ? 'is-active' : ''}`}
              aria-selected={view === 'orders'}
              onClick={() => setView('orders')}
            >
              Pedidos
            </button>
          </div>
        </div>
      </div>

      <div id="eop-notices" />

      {view === 'new-order' ? (
        <section className="eop-pdv-view is-active">
          <NewOrderForm
            key={editingOrderId ?? 'new'}
            orderId={editingOrderId ?? undefined}
            onExit={() => {
              setEditingOrderId(null);
              setView('orders');
              loadOrders();
            }}
          />
        </section>
      ) : (
        <section className="eop-pdv-view is-active">
          <div className="eop-orders-browser">
            <div className="eop-card eop-orders-browser__controls">
              <div className="eop-orders-browser__top">
                <div>
                  <h2>Pedidos criados</h2>
                  <p>Acompanhe propostas e pedidos sem sair da tela de vendas.</p>
                </div>
                <button type="button" className="eop-btn" onClick={loadOrders} disabled={loading}>
                  {loading ? 'Carregando...' : 'Atualizar'}
                </button>
              </div>

              <div className="eop-orders-browser__filters">
                <div className="eop-field">
                  <label>Buscar</label>
                  <input
                    type="search"
                    value={search}
                    placeholder="Pedido, cliente ou e-mail"
                    onChange={(event) => setSearch(event.target.value)}
                  />
                </div>
                <div className="eop-field">
                  <label>Status</label>
                  <select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)}>
                    <option value="any">Todos</option>
                    <option value="pending">Pendente</option>
                    <option value="processing">Processando</option>
                    <option value="on-hold">Aguardando</option>
                    <option value="completed">Concluido</option>
                    <option value="cancelled">Cancelado</option>
                  </select>
                </div>
              </div>
            </div>

            <div className="eop-orders-browser__summary">
              {orders ? (
                <div className="eop-orders-summary__card">
                  <strong>{orders.viewer?.is_admin ? 'Todos os pedidos expresso' : 'Seus pedidos expresso'}</strong>
                  <span>{filteredItems.length} pedido(s) encontrado(s)</span>
                </div>
              ) : null}
            </div>

            <div className="eop-orders-browser__list">
              {loading ? <div className="eop-card eop-orders-empty-state">Carregando pedidos...</div> : null}
              {error ? <div className="eop-notice eop-notice-error">{error}</div> : null}

              {!loading && !error && filteredItems.length === 0 ? (
                <div className="eop-card eop-orders-empty-state">Nenhum pedido encontrado para este filtro.</div>
              ) : null}

              {!loading && !error
                ? filteredItems.map((order) => (
                    <OrderCard
                      key={order.id}
                      order={order}
                      onRefresh={loadOrders}
                      onEdit={(id) => {
                        setEditingOrderId(id);
                        setView('new-order');
                      }}
                    />
                  ))
                : null}
            </div>
          </div>
        </section>
      )}
    </div>
  );
}

export default FrontendApp;
