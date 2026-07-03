import { useCallback, useEffect, useState } from 'react';
import type { BootstrapPayload, OrdersPayload } from './app/types';
import { adminApi, getAdminSpaConfig, getInlineBootstrap } from './app/api';
import NewOrderForm from './NewOrderForm';
import { OrderCard } from './OrdersBrowser';


type View = 'new-order' | 'orders';

function FrontendApp() {
  const [bootstrap, setBootstrap] = useState<BootstrapPayload | null>(() => getInlineBootstrap());
  const [view, setView] = useState<View>(() => {
    try {
      return new URLSearchParams(window.location.search).get('view') === 'orders' ? 'orders' : 'new-order';
    } catch {
      return 'new-order';
    }
  });
  const [orders, setOrders] = useState<OrdersPayload | null>(null);
  const [search, setSearch] = useState<string>('');
  const [statusFilter, setStatusFilter] = useState<string>('any');
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string>('');
  const [editingOrderId, setEditingOrderId] = useState<number | null>(null);
  const [page, setPage] = useState<number>(1);

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

  // Busca/filtro/paginacao no SERVIDOR (antes filtrava client-side so os 12 da pagina 1,
  // por isso "12 pedido(s) encontrado(s)" mesmo havendo varias paginas).
  const loadOrders = useCallback(() => {
    if (!getAdminSpaConfig()) {
      return;
    }

    setLoading(true);
    setError('');

    adminApi
      .getOrders({ status: statusFilter, search, page })
      .then((payload) => setOrders(payload))
      .catch((fetchError) => setError(fetchError instanceof Error ? fetchError.message : 'Nao foi possivel carregar os pedidos agora.'))
      .finally(() => setLoading(false));
  }, [statusFilter, search, page]);

  // Volta para a pagina 1 quando muda filtro/busca.
  useEffect(() => {
    setPage(1);
  }, [statusFilter, search]);

  useEffect(() => {
    if (view !== 'orders') {
      return;
    }
    const timer = setTimeout(loadOrders, 250);
    return () => clearTimeout(timer);
  }, [view, loadOrders]);

  const title = bootstrap?.branding.panelTitle || 'Pedido Expresso';
  const subtitle = bootstrap?.branding.panelSubtitle || '';

  const items = orders?.items ?? [];
  const totalItems = orders?.pagination?.total_items ?? items.length;
  const totalPages = orders?.pagination?.total_pages ?? 1;

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
                      onEdit={(id) => {
                        setEditingOrderId(id);
                        setView('new-order');
                      }}
                      onStagePatched={(id, summary) =>
                        setOrders((prev) =>
                          prev
                            ? {
                                ...prev,
                                items: prev.items.map((current) =>
                                  current.id === id
                                    ? { ...current, post_confirmation_flow_summary: summary }
                                    : current
                                ),
                              }
                            : prev
                        )
                      }
                    />
                  ))
                : null}
            </div>

            {orders && totalPages > 1 ? (
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
                  Pagina {page} de {totalPages}
                </span>
                <button
                  type="button"
                  className="eop-btn"
                  disabled={page >= totalPages || loading}
                  onClick={() => setPage((current) => current + 1)}
                >
                  Proxima
                </button>
              </div>
            ) : null}
          </div>
        </section>
      )}
    </div>
  );
}

export default FrontendApp;
