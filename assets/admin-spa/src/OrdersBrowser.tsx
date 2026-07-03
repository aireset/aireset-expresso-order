import { useCallback, useEffect, useState } from 'react';
import type { FlowSummary, OrderSummary, OrdersPayload } from './app/types';
import { adminApi, getAdminSpaConfig } from './app/api';
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

export function OrderCard({
  order,
  onRefresh,
  onEdit,
  onStagePatched,
}: {
  order: OrderSummary;
  onRefresh: () => void;
  onEdit: (id: number) => void;
  onStagePatched?: (id: number, summary: FlowSummary) => void;
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
      const result = (await adminApi.updateOrderStage(order.id, stage)) as { summary?: FlowSummary } | null;
      // Atualiza so este pedido (sem refetch da lista inteira, que "recarregava a tela toda").
      if (result?.summary && onStagePatched) {
        onStagePatched(order.id, result.summary);
      } else {
        onRefresh();
      }
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
        <span className="eop-order-card__status">
          {flow?.active_for_order && flow.stage_label ? flow.stage_label : order.status_label || order.status}
        </span>
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
            <FlowPill label="Dados" value={docsValue} tone={docsDone ? 'success' : 'info'} />
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

function OrdersBrowser() {
  const [orders, setOrders] = useState<OrdersPayload | null>(null);
  const [search, setSearch] = useState<string>('');
  const [statusFilter, setStatusFilter] = useState<string>('any');
  const [flowFilter, setFlowFilter] = useState<string>('any');
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string>('');
  const [editingOrderId, setEditingOrderId] = useState<number | null>(null);
  const [page, setPage] = useState<number>(1);

  const loadOrders = useCallback(() => {
    if (!getAdminSpaConfig()) {
      return;
    }

    setLoading(true);
    setError('');

    adminApi
      .getOrders({ status: statusFilter, flow: flowFilter, search, page })
      .then((payload) => setOrders(payload))
      .catch((fetchError) => setError(fetchError instanceof Error ? fetchError.message : 'Nao foi possivel carregar os pedidos agora.'))
      .finally(() => setLoading(false));
  }, [statusFilter, flowFilter, search, page]);

  // Volta para a pagina 1 quando muda filtro/busca (senao ficaria numa pagina inexistente).
  useEffect(() => {
    setPage(1);
  }, [statusFilter, flowFilter, search]);

  // Recarrega no servidor quando muda filtro/busca/pagina (com debounce para a busca).
  useEffect(() => {
    if (editingOrderId !== null) {
      return;
    }
    const timer = setTimeout(loadOrders, 250);
    return () => clearTimeout(timer);
  }, [loadOrders, editingOrderId]);

  if (editingOrderId !== null) {
    return (
      <NewOrderForm
        key={editingOrderId}
        orderId={editingOrderId}
        onExit={() => setEditingOrderId(null)}
      />
    );
  }

  const items = orders?.items ?? [];
  const totalItems = orders?.pagination?.total_items ?? items.length;

  const statusChips: Array<{ value: string; label: string }> = [
    { value: 'any', label: 'Todos' },
    { value: 'pending', label: 'Pendente' },
    { value: 'processing', label: 'Processando' },
    { value: 'on-hold', label: 'Aguardando' },
    { value: 'completed', label: 'Concluido' },
    { value: 'cancelled', label: 'Cancelado' },
  ];
  const flowChips: Array<{ value: string; label: string }> = [
    { value: 'any', label: 'Todos' },
    { value: 'pending', label: 'Em andamento' },
    { value: 'completed', label: 'Fluxo concluido' },
  ];

  return (
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

        <div className="eop-field">
          <label>Buscar</label>
          <input
            type="search"
            value={search}
            placeholder="Pedido, cliente ou e-mail"
            onChange={(event) => setSearch(event.target.value)}
          />
        </div>

        <div className="eop-orders-chips">
          <span className="eop-orders-chips__label">Status</span>
          <div className="eop-orders-chips__row">
            {statusChips.map((chip) => (
              <button
                key={chip.value}
                type="button"
                className={`eop-orders-chip ${statusFilter === chip.value ? 'is-active' : ''}`}
                aria-pressed={statusFilter === chip.value}
                onClick={() => setStatusFilter(chip.value)}
              >
                {chip.label}
              </button>
            ))}
          </div>
        </div>

        <div className="eop-orders-chips">
          <span className="eop-orders-chips__label">Etapa do fluxo</span>
          <div className="eop-orders-chips__row">
            {flowChips.map((chip) => (
              <button
                key={chip.value}
                type="button"
                className={`eop-orders-chip ${flowFilter === chip.value ? 'is-active' : ''}`}
                aria-pressed={flowFilter === chip.value}
                onClick={() => setFlowFilter(chip.value)}
              >
                {chip.label}
              </button>
            ))}
          </div>
        </div>
      </div>

      <div className="eop-orders-browser__summary">
        {orders ? (
          <div className="eop-orders-summary__card">
            <strong>{orders.viewer?.is_admin ? 'Todos os pedidos expresso' : 'Seus pedidos expresso'}</strong>
            <span>{totalItems} pedido(s) encontrado(s)</span>
          </div>
        ) : null}
      </div>

      <div className="eop-orders-browser__list">
        {loading ? <div className="eop-card eop-orders-empty-state">Carregando pedidos...</div> : null}
        {error ? <div className="eop-notice eop-notice-error">{error}</div> : null}

        {!loading && !error && items.length === 0 ? (
          <div className="eop-card eop-orders-empty-state">Nenhum pedido encontrado para este filtro.</div>
        ) : null}

        {!loading && !error
          ? items.map((order) => (
              <OrderCard
                key={order.id}
                order={order}
                onRefresh={loadOrders}
                onEdit={(id) => setEditingOrderId(id)}
                onStagePatched={(id, summary) =>
                  setOrders((prev) =>
                    prev
                      ? {
                          ...prev,
                          items: prev.items.map((current) =>
                            current.id === id ? { ...current, post_confirmation_flow_summary: summary } : current
                          ),
                        }
                      : prev
                  )
                }
              />
            ))
          : null}
      </div>

      {orders && (orders.pagination?.total_pages ?? 1) > 1 ? (
        <div className="eop-orders-browser__pagination">
          <button
            type="button"
            className="eop-btn"
            disabled={page <= 1 || loading}
            onClick={() => setPage((current) => Math.max(1, current - 1))}
          >
            Anterior
          </button>
          <span className="eop-orders-page-indicator">
            Pagina {page} de {orders.pagination?.total_pages ?? 1}
          </span>
          <button
            type="button"
            className="eop-btn"
            disabled={page >= (orders.pagination?.total_pages ?? 1) || loading}
            onClick={() => setPage((current) => current + 1)}
          >
            Proxima
          </button>
        </div>
      ) : null}
    </div>
  );
}

export default OrdersBrowser;
