import type { CSSProperties, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import type {
  BootstrapPayload,
  NewOrderDraft,
  OrderDetail,
  OrderDraft,
  OrdersPayload,
  PreviewPayload,
  ProductResult,
  SettingsField,
  SettingsPayload,
  ShippingPackage,
} from './app/types';
import { adminApi, getAdminSpaConfig, getInlineBootstrap } from './app/api';
import {
  labels,
  navGroups,
  orderStatusOptions,
  previewSurfaceByView,
  primaryNavItems,
  settingsSectionByView,
  utilityNavItems,
} from './app/view-config';

function createInitialNewOrderDraft(): NewOrderDraft {
  return {
    customer: {
      user_id: 0,
      name: '',
      email: '',
      phone: '',
      document: '',
    },
    items: [],
    shipping: 0,
    shipping_method: '',
    shipping_address: {
      postcode: '',
      state: '',
      city: '',
      address: '',
      number: '',
      neighborhood: '',
      address_2: '',
    },
    shipping_rate: null,
    discount: 0,
    discount_type: 'fixed',
    status: 'completed',
  };
}

function toOrderDraft(order: OrderDetail): OrderDraft {
  return {
    order_id: order.order_id,
    status: order.status,
    customer: {
      ...order.customer,
    },
    items: order.items.map((item) => ({
      ...item,
    })),
    shipping: order.shipping,
    shipping_method: order.shipping_method,
    shipping_address: {
      ...order.shipping_address,
    },
    shipping_rate: null,
    discount: order.discount,
    discount_type: order.discount_type,
    notes: order.notes,
  };
}

function parseNumber(value: string): number {
  const normalized = value.replace(',', '.');
  const parsed = Number.parseFloat(normalized);
  return Number.isFinite(parsed) ? parsed : 0;
}

function formatCurrency(value: number, currency: string): string {
  try {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: currency || 'BRL',
    }).format(value);
  } catch {
    return `${currency || 'BRL'} ${value.toFixed(2)}`;
  }
}

function createSettingsDraft(payload: SettingsPayload): Record<string, string> {
  const keys = Array.isArray(payload.fields) && payload.fields.length > 0
    ? payload.fields.map((field) => field.key)
    : Object.keys(payload.values);

  return Object.fromEntries(
    keys
      .filter((key) => Object.prototype.hasOwnProperty.call(payload.values, key))
      .map((key) => [key, String(payload.values[key] ?? '')])
  );
}

function SettingsGroup({ title, children }: { title: string; children: ReactNode }) {
  const [open, setOpen] = useState(false);

  if (!title) {
    return <div className="eop-react-settings-group">{children}</div>;
  }

  return (
    <div className={`eop-react-accordion ${open ? 'is-open' : ''}`}>
      <button
        type="button"
        className="eop-react-accordion__head"
        aria-expanded={open}
        onClick={() => setOpen((value) => !value)}
      >
        <span className="eop-react-accordion__title">{title}</span>
        <span className="eop-react-accordion__arrow dashicons dashicons-arrow-down-alt2" aria-hidden="true" />
      </button>
      <div className="eop-react-accordion__body" hidden={!open}>
        {children}
      </div>
    </div>
  );
}

function ColorField({
  value,
  defaultValue,
  onChange,
}: {
  value: string;
  defaultValue?: string;
  onChange: (value: string) => void;
}) {
  const inputRef = useRef<HTMLInputElement>(null);

  return (
    <div className="eop-react-color">
      <div className="eop-react-color__control">
        <input
          ref={inputRef}
          type="text"
          className="eop-color-field"
          value={value}
          placeholder="#000000"
          onChange={(event) => onChange(event.target.value)}
        />
        <button
          type="button"
          className="eop-react-color__dot"
          style={{ backgroundColor: value || '#ffffff' }}
          aria-label="Abrir seletor de cor"
          onClick={() => inputRef.current?.click()}
        />
      </div>
      {defaultValue ? (
        <button
          type="button"
          className="eop-react-color__default"
          onClick={() => onChange(defaultValue)}
        >
          Padrao
        </button>
      ) : null}
    </div>
  );
}

function HelpTip({ text }: { text: string }) {
  const [open, setOpen] = useState(false);
  const [position, setPosition] = useState<{ top: number; left: number }>({ top: 0, left: 0 });
  const buttonRef = useRef<HTMLButtonElement>(null);

  function show() {
    const rect = buttonRef.current?.getBoundingClientRect();

    if (rect) {
      setPosition({
        top: rect.bottom + window.scrollY + 8,
        left: rect.left + window.scrollX + rect.width / 2,
      });
    }

    setOpen(true);
  }

  return (
    <span className="eop-react-help-tip">
      <button
        ref={buttonRef}
        type="button"
        className="eop-react-help-tip__button"
        aria-label="Ajuda"
        onMouseEnter={show}
        onMouseLeave={() => setOpen(false)}
        onFocus={show}
        onBlur={() => setOpen(false)}
      >
        ?
      </button>
      {open
        ? createPortal(
            <span
              className="eop-react-help-tip__bubble"
              role="tooltip"
              style={{ top: `${position.top}px`, left: `${position.left}px` }}
            >
              {text}
            </span>,
            document.body
          )
        : null}
    </span>
  );
}

function MultiSelectField({
  field,
  onChange,
}: {
  field: SettingsField;
  onChange: (value: string) => void;
}) {
  const [items, setItems] = useState<Array<{ value: string; label: string }>>(field.selected ?? []);
  const [term, setTerm] = useState('');
  const [results, setResults] = useState<Array<{ value: string; label: string }>>([]);
  const [searchState, setSearchState] = useState<'idle' | 'loading' | 'done' | 'error'>('idle');
  const minChars = field.minChars ?? 1;

  function commit(next: Array<{ value: string; label: string }>) {
    setItems(next);
    onChange(next.map((item) => item.value).join(','));
  }

  function addItem(item: { value: string; label: string }) {
    if (!items.some((current) => current.value === item.value)) {
      commit([...items, item]);
    }
    setTerm('');
    setResults([]);
    setSearchState('idle');
  }

  function removeItem(value: string) {
    commit(items.filter((item) => item.value !== value));
  }

  async function runSearch() {
    if (term.trim().length < minChars) {
      return;
    }

    setSearchState('loading');

    try {
      const payload =
        field.searchSource === 'categories'
          ? await adminApi.searchProductCategories(term)
          : await adminApi.searchProducts(term);
      const mapped = payload.results.map((result) => ({
        value: String(result.id),
        label: String((result as { text?: string }).text ?? result.id),
      }));
      setResults(mapped.filter((result) => !items.some((current) => current.value === result.value)));
      setSearchState('done');
    } catch {
      setSearchState('error');
    }
  }

  return (
    <div className="eop-react-multiselect">
      {items.length ? (
        <div className="eop-react-multiselect__chips">
          {items.map((item) => (
            <span key={item.value} className="eop-react-multiselect__chip">
              {item.label}
              <button type="button" onClick={() => removeItem(item.value)} aria-label="Remover">
                ×
              </button>
            </span>
          ))}
        </div>
      ) : null}

      <div className="eop-react-inline-action">
        <input
          type="search"
          value={term}
          placeholder={
            field.searchSource === 'categories'
              ? 'Buscar categorias...'
              : 'Buscar produtos por nome ou SKU...'
          }
          onChange={(event) => setTerm(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === 'Enter') {
              event.preventDefault();
              void runSearch();
            }
          }}
        />
        <button
          type="button"
          className="eop-react-button"
          onClick={() => void runSearch()}
          disabled={searchState === 'loading'}
        >
          {searchState === 'loading' ? '...' : 'Buscar'}
        </button>
      </div>

      {searchState === 'error' ? <small className="eop-react-error">Falha na busca.</small> : null}

      {results.length ? (
        <div className="eop-react-multiselect__results">
          {results.map((result) => (
            <button
              key={result.value}
              type="button"
              className="eop-react-multiselect__result"
              onClick={() => addItem(result)}
            >
              {result.label}
            </button>
          ))}
        </div>
      ) : null}

      {searchState === 'done' && !results.length ? <small>Nenhum resultado novo.</small> : null}
    </div>
  );
}

function App() {
  const [bootstrap, setBootstrap] = useState<BootstrapPayload | null>(() => getInlineBootstrap());
  const [error, setError] = useState<string>('');
  const [selectedView, setSelectedView] = useState<string>(getAdminSpaConfig()?.initial_view || 'new-order');
  const [viewLoading, setViewLoading] = useState<boolean>(false);
  const [viewError, setViewError] = useState<string>('');
  const [settingsPayload, setSettingsPayload] = useState<SettingsPayload | null>(null);
  const [settingsDraft, setSettingsDraft] = useState<Record<string, string>>({});
  const [settingsSaveState, setSettingsSaveState] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');
  const [settingsSaveMessage, setSettingsSaveMessage] = useState<string>('');
  const [ordersPayload, setOrdersPayload] = useState<OrdersPayload | null>(null);
  const [selectedOrderId, setSelectedOrderId] = useState<number | null>(null);
  const [orderDetail, setOrderDetail] = useState<OrderDetail | null>(null);
  const [orderDraft, setOrderDraft] = useState<OrderDraft | null>(null);
  const [orderLoading, setOrderLoading] = useState<boolean>(false);
  const [orderError, setOrderError] = useState<string>('');
  const [orderSaveState, setOrderSaveState] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');
  const [orderSaveMessage, setOrderSaveMessage] = useState<string>('');
  const [newOrderDraft, setNewOrderDraft] = useState<NewOrderDraft>(createInitialNewOrderDraft());
  const [customerLookupState, setCustomerLookupState] = useState<'idle' | 'loading' | 'found' | 'not-found' | 'error'>('idle');
  const [customerLookupMessage, setCustomerLookupMessage] = useState<string>('');
  const [productTerm, setProductTerm] = useState<string>('');
  const [productResults, setProductResults] = useState<ProductResult[]>([]);
  const [productSearchState, setProductSearchState] = useState<'idle' | 'loading' | 'done' | 'error'>('idle');
  const [productSearchMessage, setProductSearchMessage] = useState<string>('');
  const [orderProductTerm, setOrderProductTerm] = useState<string>('');
  const [orderProductResults, setOrderProductResults] = useState<ProductResult[]>([]);
  const [orderProductSearchState, setOrderProductSearchState] = useState<'idle' | 'loading' | 'done' | 'error'>('idle');
  const [orderProductSearchMessage, setOrderProductSearchMessage] = useState<string>('');
  const [shippingPackages, setShippingPackages] = useState<ShippingPackage[]>([]);
  const [shippingState, setShippingState] = useState<'idle' | 'loading' | 'done' | 'error'>('idle');
  const [shippingMessage, setShippingMessage] = useState<string>('');
  const [orderShippingPackages, setOrderShippingPackages] = useState<ShippingPackage[]>([]);
  const [orderShippingState, setOrderShippingState] = useState<'idle' | 'loading' | 'done' | 'error'>('idle');
  const [orderShippingMessage, setOrderShippingMessage] = useState<string>('');
  const [newOrderState, setNewOrderState] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');
  const [newOrderMessage, setNewOrderMessage] = useState<string>('');
  const [createdOrderUrl, setCreatedOrderUrl] = useState<string>('');
  const [previewPayload, setPreviewPayload] = useState<PreviewPayload | null>(null);
  const settingsCacheRef = useRef<Record<string, SettingsPayload>>({});
  const previewCacheRef = useRef<Record<string, PreviewPayload>>({});
  const ordersCacheRef = useRef<OrdersPayload | null>(null);
  const [openGroups, setOpenGroups] = useState<Record<string, boolean>>({
    general: true,
    confirmation: true,
    pdf: false,
  });

  useEffect(() => {
    const config = getAdminSpaConfig();

    if (!config) {
      setError('Configuracao do admin SPA nao encontrada.');
      return;
    }

    // Bootstrap ja veio inline no HTML: shell renderiza sem round-trip REST.
    if (bootstrap) {
      return;
    }

    adminApi
      .getBootstrap()
      .then((payload) => {
        setBootstrap(payload);
        setSelectedView(config.initial_view || payload.initialView || selectedView);
      })
      .catch((fetchError) => {
        setError(fetchError instanceof Error ? fetchError.message : 'Falha ao carregar bootstrap.');
      });
  }, []);

  useEffect(() => {
    const coloris = (
      window as unknown as {
        Coloris?: ((options: Record<string, unknown>) => void) & {
          setInstance?: (selector: string, options: Record<string, unknown>) => void;
        };
      }
    ).Coloris;

    if (typeof coloris !== 'function') {
      return;
    }

    // Mesma config do admin legado (settings-admin.js): tema escuro, toggle de
    // formato, botoes limpar/fechar e swatches. Usa delegacao pelo seletor, entao
    // vale para campos color montados depois.
    coloris({ el: '.eop-color-field' });
    coloris.setInstance?.('.eop-color-field', {
      theme: 'pill',
      themeMode: 'dark',
      formatToggle: true,
      alpha: false,
      closeButton: true,
      closeLabel: 'Fechar',
      clearButton: true,
      clearLabel: 'Limpar',
      swatches: ['#00034b', '#1e88e5', '#90caf9', '#43a047', '#fbc02d', '#fb8c00', '#e53935', '#ffffff', '#000000'],
    });
  }, []);

  useEffect(() => {
    if (!bootstrap || !getAdminSpaConfig()) {
      return;
    }

    let cancelled = false;
    let hasBlockingRequest = false;
    const settingsSection = settingsSectionByView[selectedView];
    const previewSurface = previewSurfaceByView[selectedView];

    setViewError('');
    setSettingsSaveState('idle');
    setSettingsSaveMessage('');

    if (settingsSection) {
      const cachedSettings = settingsCacheRef.current[settingsSection];

      if (cachedSettings) {
        setSettingsPayload(cachedSettings);
        setSettingsDraft(createSettingsDraft(cachedSettings));
      } else {
        hasBlockingRequest = true;
        setSettingsPayload(null);
        setSettingsDraft({});
      }
    } else {
      setSettingsPayload(null);
      setSettingsDraft({});
    }

    if (previewSurface) {
      const cachedPreview = previewCacheRef.current[previewSurface];

      if (cachedPreview) {
        setPreviewPayload(cachedPreview);
      } else {
        hasBlockingRequest = true;
        setPreviewPayload(null);
      }
    } else {
      setPreviewPayload(null);
    }

    if (selectedView !== 'orders') {
      setOrderError('');
      setOrderSaveState('idle');
      setOrderSaveMessage('');
    }

    const requests: Promise<unknown>[] = [];

    if (selectedView === 'orders') {
      if (ordersCacheRef.current) {
        setOrdersPayload(ordersCacheRef.current);
      } else {
        hasBlockingRequest = true;
      }

      requests.push(
        adminApi.getOrders().then((payload) => {
          ordersCacheRef.current = payload;
          if (cancelled) {
            return;
          }

          setOrdersPayload(payload);
        })
      );
    } else {
      setOrdersPayload(null);
      setSelectedOrderId(null);
      setOrderDetail(null);
      setOrderDraft(null);
    }

    if (settingsSection) {
      requests.push(
        adminApi
          .getSettings(settingsSection)
          .then((payload) => {
            settingsCacheRef.current[settingsSection] = payload;
            if (cancelled) {
              return;
            }

            setSettingsPayload(payload);
            setSettingsDraft(createSettingsDraft(payload));
          }
        )
      );
    }

    if (previewSurface) {
      requests.push(
        adminApi
          .getPreview(previewSurface)
          .then((payload) => {
            previewCacheRef.current[previewSurface] = payload;
            if (cancelled) {
              return;
            }

            setPreviewPayload(payload);
          }
        )
      );
    }

    if (!requests.length) {
      setViewLoading(false);
      return;
    }

    setViewLoading(hasBlockingRequest);

    Promise.all(requests)
      .catch((fetchError) => {
        if (cancelled) {
          return;
        }

        setViewError(fetchError instanceof Error ? fetchError.message : 'Falha ao carregar a view.');
      })
      .finally(() => {
        if (cancelled) {
          return;
        }

        setViewLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [bootstrap, selectedView]);

  useEffect(() => {
    if (!bootstrap) {
      return;
    }

    const nextUrl = new URL(bootstrap.routes.legacyRoot, window.location.origin);
    nextUrl.searchParams.set('view', selectedView);
    window.history.replaceState({}, '', nextUrl.toString());
  }, [bootstrap, selectedView]);

  useEffect(() => {
    if (selectedView !== 'orders' || !ordersPayload) {
      return;
    }

    const availableIds = ordersPayload.items.map((order) => order.id);
    if (!availableIds.length) {
      setSelectedOrderId(null);
      setOrderDetail(null);
      setOrderDraft(null);
      return;
    }

    if (!selectedOrderId || !availableIds.includes(selectedOrderId)) {
      setSelectedOrderId(availableIds[0]);
    }
  }, [ordersPayload, selectedOrderId, selectedView]);

  useEffect(() => {
    if (
      selectedView !== 'orders' ||
      !selectedOrderId ||
      !getAdminSpaConfig()
    ) {
      return;
    }

    setOrderLoading(true);
    setOrderError('');
    setOrderSaveState('idle');
    setOrderSaveMessage('');
    setOrderProductTerm('');
    setOrderProductResults([]);
    setOrderProductSearchState('idle');
    setOrderProductSearchMessage('');
    setOrderShippingPackages([]);
    setOrderShippingState('idle');
    setOrderShippingMessage('');

    adminApi
      .getOrder(selectedOrderId)
      .then((payload) => {
        setOrderDetail(payload.order);
        setOrderDraft(toOrderDraft(payload.order));
      })
      .catch((fetchError) => {
        setOrderError(fetchError instanceof Error ? fetchError.message : 'Falha ao carregar pedido.');
        setOrderDetail(null);
        setOrderDraft(null);
      })
      .finally(() => {
        setOrderLoading(false);
      });
  }, [selectedOrderId, selectedView]);

  if (error) {
    return (
      <div className="eop-react-boot">
        <div className="eop-react-card eop-react-card--danger">
          <h1>Novo admin indisponivel</h1>
          <p>{error}</p>
        </div>
      </div>
    );
  }

  if (!bootstrap) {
    return (
      <div className="eop-react-boot">
        <div className="eop-react-card">
          <div className="eop-react-loading" role="status" aria-live="polite">
            <span className="eop-react-loading__spinner" aria-hidden="true" />
            <span>Carregando painel...</span>
          </div>
        </div>
      </div>
    );
  }

  const currentUrl = bootstrap.routes.legacyViewUrls?.[selectedView] || bootstrap.routes.legacyRoot;
  const availableViews = new Set(bootstrap.views);
  const visiblePrimaryNavItems = primaryNavItems.filter((item) => availableViews.has(item.view));
  const visibleUtilityNavItems = utilityNavItems.filter((item) => availableViews.has(item.view));
  const visibleNavGroups = navGroups
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => availableViews.has(item.view)),
    }))
    .filter((group) => group.items.length > 0);
  const activeGroupId = visibleNavGroups.find((group) =>
    group.items.some((item) => item.view === selectedView)
  )?.id;
  const shellStyle = {
    '--eop-primary': bootstrap.branding.primaryColor,
    '--eop-surface': bootstrap.branding.surfaceColor,
    '--eop-border': bootstrap.branding.borderColor,
    '--eop-radius': `${bootstrap.branding.borderRadius}px`,
    '--eop-font-family': bootstrap.branding.fontFamily,
    fontFamily: bootstrap.branding.fontFamily,
  } as CSSProperties;
  const settingsEntries = settingsPayload ? Object.entries(settingsPayload.values).slice(0, 12) : [];
  const editableSettings =
    Boolean(settingsPayload) && Array.isArray(settingsPayload?.fields) && settingsPayload.fields.length > 0;
  const newOrderItemsTotal = newOrderDraft.items.reduce((total, item) => {
    const lineTotal = item.price * item.quantity;
    const discount =
      item.discount_type === 'percent'
        ? Math.min(lineTotal, (lineTotal * item.discount_value) / 100)
        : Math.min(lineTotal, item.discount_value);
    return total + Math.max(0, lineTotal - discount);
  }, 0);
  const newOrderDiscount =
    newOrderDraft.discount_type === 'percent'
      ? (newOrderItemsTotal * newOrderDraft.discount) / 100
      : newOrderDraft.discount;
  const newOrderTotal = Math.max(0, newOrderItemsTotal + newOrderDraft.shipping - newOrderDiscount);

  function updateDraft(key: string, value: string) {
    setSettingsDraft((current) => ({
      ...current,
      [key]: value,
    }));
    setSettingsSaveState('idle');
    setSettingsSaveMessage('');
  }

  function openMediaPicker(fieldKey: string) {
    const wp = (window as unknown as { wp?: { media?: (config: unknown) => unknown } }).wp;

    if (!wp?.media) {
      window.alert('Biblioteca de midia indisponivel.');
      return;
    }

    const frame = wp.media({
      title: 'Selecionar logo',
      button: { text: 'Usar este logo' },
      multiple: false,
      library: { type: 'image' },
    }) as {
      on: (event: string, handler: () => void) => void;
      open: () => void;
      state: () => { get: (key: string) => { first: () => { toJSON: () => { url?: string } } } };
    };

    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON();

      if (attachment?.url) {
        updateDraft(fieldKey, String(attachment.url));
      }
    });

    frame.open();
  }

  function renderSettingsField(field: SettingsField) {
    const isFull = field.type === 'media' || field.type === 'textarea' || field.type === 'multiselect';

    return (
      <label
        key={field.key}
        className={`eop-react-form__field ${isFull ? 'eop-react-form__field--full' : ''}`}
      >
        <span className="eop-react-field-label">
          {field.label}
          {field.help ? <HelpTip text={field.help} /> : null}
        </span>

        {field.type === 'media' ? (
          <div className="eop-react-media">
            <div className={`eop-react-media__preview ${settingsDraft[field.key] ? 'has-image' : ''}`}>
              {settingsDraft[field.key] ? (
                <img src={settingsDraft[field.key]} alt="" />
              ) : (
                <span>Nenhum logo selecionado ainda.</span>
              )}
            </div>
            <input
              type="url"
              value={settingsDraft[field.key] ?? ''}
              placeholder="https://..."
              onChange={(event) => updateDraft(field.key, event.target.value)}
            />
            <div className="eop-react-media__actions">
              <button type="button" className="eop-react-button" onClick={() => openMediaPicker(field.key)}>
                {settingsDraft[field.key] ? 'Trocar logo' : 'Selecionar logo'}
              </button>
              {settingsDraft[field.key] ? (
                <button
                  type="button"
                  className="eop-react-link-button"
                  onClick={() => updateDraft(field.key, '')}
                >
                  Remover logo
                </button>
              ) : null}
            </div>
          </div>
        ) : null}

        {field.type === 'select' ? (
          <select
            value={settingsDraft[field.key] ?? ''}
            onChange={(event) => updateDraft(field.key, event.target.value)}
          >
            {field.options?.map((option) => (
              <option key={`${field.key}-${option.value}`} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        ) : null}

        {field.type === 'text' ? (
          <input
            type="text"
            placeholder=" "
            value={settingsDraft[field.key] ?? ''}
            onChange={(event) => updateDraft(field.key, event.target.value)}
          />
        ) : null}

        {field.type === 'textarea' ? (
          <textarea
            rows={4}
            placeholder=" "
            value={settingsDraft[field.key] ?? ''}
            onChange={(event) => updateDraft(field.key, event.target.value)}
          />
        ) : null}

        {field.type === 'number' ? (
          <input
            type="number"
            placeholder=" "
            value={settingsDraft[field.key] ?? ''}
            onChange={(event) => updateDraft(field.key, event.target.value)}
          />
        ) : null}

        {field.type === 'color' ? (
          <ColorField
            value={settingsDraft[field.key] ?? ''}
            defaultValue={field.default}
            onChange={(next) => updateDraft(field.key, next)}
          />
        ) : null}

        {field.type === 'toggle' ? (
          <div className="eop-react-switch-shell">
            <button
              type="button"
              role="switch"
              aria-checked={settingsDraft[field.key] === 'yes'}
              className={`eop-react-switcher ${settingsDraft[field.key] === 'yes' ? 'is-enabled' : ''}`}
              onClick={() => updateDraft(field.key, settingsDraft[field.key] === 'yes' ? 'no' : 'yes')}
            >
              <span className="eop-react-switcher__label eop-react-switcher__label--off">Off</span>
              <span className="eop-react-switcher__thumb" aria-hidden="true" />
              <span className="eop-react-switcher__label eop-react-switcher__label--on">On</span>
            </button>
            <span className="eop-react-switcher__status" aria-live="polite">
              {settingsDraft[field.key] === 'yes' ? 'Ativado' : 'Desativado'}
            </span>
          </div>
        ) : null}

        {field.type === 'multiselect' ? (
          <MultiSelectField field={field} onChange={(value) => updateDraft(field.key, value)} />
        ) : null}
      </label>
    );
  }

  function renderFields(fields: SettingsField[]) {
    const subgroups: { name: string; fields: SettingsField[] }[] = [];

    fields.forEach((field) => {
      const name = field.subgroup || '';
      let bucket = subgroups.find((entry) => entry.name === name);

      if (!bucket) {
        bucket = { name, fields: [] };
        subgroups.push(bucket);
      }

      bucket.fields.push(field);
    });

    // Sem sub-grupos nomeados: um unico form.
    if (subgroups.length === 1 && !subgroups[0].name) {
      return (
        <div className="eop-react-form eop-react-form--settings">
          {subgroups[0].fields.map(renderSettingsField)}
        </div>
      );
    }

    // Com sub-grupos: um h5 separa cada bloco de conteudo (igual ao legado).
    return subgroups.map((sub, index) => (
      <div className="eop-react-subgroup" key={sub.name || `sub-${index}`}>
        {sub.name ? <h5 className="eop-react-subgroup__title">{sub.name}</h5> : null}
        <div className="eop-react-form eop-react-form--settings">
          {sub.fields.map(renderSettingsField)}
        </div>
      </div>
    ));
  }

  function renderSettingsGroups(fields: SettingsField[]) {
    const groups: { group: string; fields: SettingsField[] }[] = [];

    fields.forEach((field) => {
      const groupName = field.group || '';
      let bucket = groups.find((entry) => entry.group === groupName);

      if (!bucket) {
        bucket = { group: groupName, fields: [] };
        groups.push(bucket);
      }

      bucket.fields.push(field);
    });

    // Um unico bloco nao vira accordion: renderiza aberto direto.
    if (groups.length <= 1) {
      return renderFields(groups[0]?.fields ?? fields);
    }

    return groups.map((bucket, index) => (
      <SettingsGroup key={bucket.group || `group-${index}`} title={bucket.group}>
        {renderFields(bucket.fields)}
      </SettingsGroup>
    ));
  }

  function updateOrderDraft(updater: (current: OrderDraft) => OrderDraft) {
    setOrderDraft((current) => {
      if (!current) {
        return current;
      }

      return updater(current);
    });
    setOrderSaveState('idle');
    setOrderSaveMessage('');
  }

  function updateNewOrderDraft(updater: (current: NewOrderDraft) => NewOrderDraft) {
    setNewOrderDraft((current) => updater(current));
    setNewOrderState('idle');
    setNewOrderMessage('');
  }

  function selectView(view: string) {
    setSelectedView(view);
  }

  function toggleGroup(groupId: string) {
    setOpenGroups((current) => ({
      ...current,
      [groupId]: !(current[groupId] ?? activeGroupId === groupId),
    }));
  }

  async function lookupNewOrderCustomer() {
    if (!getAdminSpaConfig()) {
      return;
    }

    setCustomerLookupState('loading');
    setCustomerLookupMessage('');

    try {
      const payload = await adminApi.searchCustomer(newOrderDraft.customer.document);

      if (payload.found) {
        updateNewOrderDraft((current) => ({
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
        setCustomerLookupState('found');
        setCustomerLookupMessage('Cliente encontrado e preenchido.');
      } else {
        updateNewOrderDraft((current) => ({
          ...current,
          customer: {
            ...current.customer,
            user_id: 0,
            document: payload.document,
          },
        }));
        setCustomerLookupState('not-found');
        setCustomerLookupMessage('Cliente nao encontrado. Preencha os dados para criar ou vincular pelo email.');
      }
    } catch (lookupError) {
      setCustomerLookupState('error');
      setCustomerLookupMessage(lookupError instanceof Error ? lookupError.message : 'Falha ao buscar cliente.');
    }
  }

  async function searchNewOrderProducts() {
    if (!getAdminSpaConfig()) {
      return;
    }

    setProductSearchState('loading');
    setProductSearchMessage('');

    try {
      const payload = await adminApi.searchProducts(productTerm);
      setProductResults(payload.results);
      setProductSearchState('done');
      setProductSearchMessage(payload.results.length ? '' : 'Nenhum produto encontrado.');
    } catch (searchError) {
      setProductSearchState('error');
      setProductSearchMessage(searchError instanceof Error ? searchError.message : 'Falha ao buscar produtos.');
    }
  }

  function addProductToNewOrder(product: ProductResult) {
    updateNewOrderDraft((current) => {
      const existingIndex = current.items.findIndex((item) => item.product_id === product.id);

      if (existingIndex >= 0) {
        return {
          ...current,
          items: current.items.map((item, index) =>
            index === existingIndex
              ? {
                  ...item,
                  quantity: item.quantity + 1,
                }
              : item
          ),
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

  async function calculateNewOrderShipping() {
    if (!getAdminSpaConfig()) {
      return;
    }

    setShippingState('loading');
    setShippingMessage('');
    setShippingPackages([]);

    try {
      const payload = await adminApi.calculateShipping(newOrderDraft.items, newOrderDraft.shipping_address);
      const firstRate = payload.rates.flatMap((shippingPackage) => shippingPackage.rates)[0] || null;
      setShippingPackages(payload.rates);
      setShippingState('done');
      setShippingMessage(firstRate ? 'Fretes calculados.' : 'Nenhuma opcao de frete encontrada.');

      if (firstRate) {
        updateNewOrderDraft((current) => ({
          ...current,
          shipping: firstRate.cost,
          shipping_method: firstRate.label,
          shipping_rate: firstRate,
        }));
      }
    } catch (shippingError) {
      setShippingState('error');
      setShippingMessage(shippingError instanceof Error ? shippingError.message : 'Falha ao calcular frete.');
    }
  }

  async function searchOrderProducts() {
    if (!getAdminSpaConfig()) {
      return;
    }

    setOrderProductSearchState('loading');
    setOrderProductSearchMessage('');

    try {
      const payload = await adminApi.searchProducts(orderProductTerm);
      setOrderProductResults(payload.results);
      setOrderProductSearchState('done');
      setOrderProductSearchMessage(payload.results.length ? '' : 'Nenhum produto encontrado.');
    } catch (searchError) {
      setOrderProductSearchState('error');
      setOrderProductSearchMessage(searchError instanceof Error ? searchError.message : 'Falha ao buscar produtos.');
    }
  }

  function addProductToOrderDraft(product: ProductResult) {
    updateOrderDraft((current) => {
      const existingIndex = current.items.findIndex((item) => item.product_id === product.id);

      if (existingIndex >= 0) {
        return {
          ...current,
          items: current.items.map((item, index) =>
            index === existingIndex
              ? {
                  ...item,
                  quantity: item.quantity + 1,
                }
              : item
          ),
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

  async function calculateOrderShipping() {
    if (!orderDraft || !getAdminSpaConfig()) {
      return;
    }

    setOrderShippingState('loading');
    setOrderShippingMessage('');
    setOrderShippingPackages([]);

    try {
      const payload = await adminApi.calculateShipping(orderDraft.items, orderDraft.shipping_address);
      const firstRate = payload.rates.flatMap((shippingPackage) => shippingPackage.rates)[0] || null;
      setOrderShippingPackages(payload.rates);
      setOrderShippingState('done');
      setOrderShippingMessage(firstRate ? 'Fretes calculados.' : 'Nenhuma opcao de frete encontrada.');

      if (firstRate) {
        updateOrderDraft((current) => ({
          ...current,
          shipping: firstRate.cost,
          shipping_method: firstRate.label,
          shipping_rate: firstRate,
        }));
      }
    } catch (shippingError) {
      setOrderShippingState('error');
      setOrderShippingMessage(shippingError instanceof Error ? shippingError.message : 'Falha ao calcular frete.');
    }
  }

  async function createNewOrder() {
    if (!getAdminSpaConfig()) {
      return;
    }

    setNewOrderState('saving');
    setNewOrderMessage('');
    setCreatedOrderUrl('');

    try {
      const payload = await adminApi.createOrder(newOrderDraft);
      setNewOrderState('saved');
      setNewOrderMessage(`Pedido #${payload.result.order_id} criado.`);
      setCreatedOrderUrl(payload.order?.edit_url || payload.result.order_url || '');
      setNewOrderDraft(createInitialNewOrderDraft());
      setShippingPackages([]);
      setProductResults([]);
      setProductTerm('');
    } catch (createError) {
      setNewOrderState('error');
      setNewOrderMessage(createError instanceof Error ? createError.message : 'Falha ao criar pedido.');
    }
  }

  async function saveCurrentSettings() {
    if (!settingsPayload || !getAdminSpaConfig()) {
      return;
    }

    setSettingsSaveState('saving');
    setSettingsSaveMessage('');

    try {
      const payload = await adminApi.updateSettings(settingsPayload.section, settingsDraft);
      setSettingsPayload(payload);
      setSettingsDraft(createSettingsDraft(payload));
      setSettingsSaveState('saved');
      setSettingsSaveMessage('Configuracoes salvas com sucesso.');
    } catch (saveError) {
      setSettingsSaveState('error');
      setSettingsSaveMessage(saveError instanceof Error ? saveError.message : 'Falha ao salvar configuracoes.');
    }
  }

  async function saveCurrentOrder() {
    if (!orderDraft || !getAdminSpaConfig()) {
      return;
    }

    setOrderSaveState('saving');
    setOrderSaveMessage('');

    try {
      const payload = await adminApi.updateOrder(orderDraft.order_id, orderDraft);
      setOrderDetail(payload.order);
      setOrderDraft(toOrderDraft(payload.order));
      setOrderSaveState('saved');
      setOrderSaveMessage(payload.message || 'Pedido salvo com sucesso.');
      setOrdersPayload((current) => {
        if (!current) {
          return current;
        }

        return {
          ...current,
          items: current.items.map((item) => (item.id === payload.order.id ? payload.order : item)),
        };
      });
    } catch (saveError) {
      setOrderSaveState('error');
      setOrderSaveMessage(saveError instanceof Error ? saveError.message : 'Falha ao salvar pedido.');
    }
  }

  return (
    <div className="wrap eop-admin-spa eop-pdv eop-admin-spa--react" style={shellStyle}>
      <div className="eop-admin-spa__layout">
        <aside className="eop-admin-spa__sidebar">
          <div className="eop-admin-spa__brand">
            <div className="eop-admin-spa__brand-mark">
              <img src={bootstrap.branding.logoUrl} alt="Pedido Expresso - Aireset" />
            </div>
            <div className="eop-admin-spa__brand-copy">
              <div className="eop-admin-spa__brand-head">
                <h1>{bootstrap.branding.panelTitle}</h1>
              </div>
              {bootstrap.branding.panelSubtitle ? <p>{bootstrap.branding.panelSubtitle}</p> : null}
            </div>
          </div>

          <nav className="eop-admin-spa__nav" aria-label="Navegacao do admin do Pedido Expresso">
            {visiblePrimaryNavItems.map((item) => (
              <button
                key={item.view}
                type="button"
                className={`eop-pdv-nav__item eop-admin-spa-nav__item ${item.view === selectedView ? 'is-active' : ''}`}
                aria-selected={item.view === selectedView}
                onClick={() => selectView(item.view)}
              >
                <span className={`eop-admin-spa-nav__icon dashicons ${item.icon}`} aria-hidden="true" />
                <span className="eop-admin-spa-nav__name">{item.label}</span>
              </button>
            ))}

            {visibleNavGroups.map((group) => {
              const isActiveGroup = group.id === activeGroupId;
              const isOpen = openGroups[group.id] ?? isActiveGroup;

              return (
                <div key={group.id} className={`eop-admin-spa-nav__group ${isActiveGroup ? 'is-open' : ''}`}>
                  <button
                    type="button"
                    className={`eop-pdv-nav__item eop-admin-spa-nav__item eop-admin-spa-nav__group-toggle ${isActiveGroup ? 'is-active' : ''}`}
                    aria-selected={isActiveGroup}
                    aria-expanded={isOpen}
                    onClick={() => {
                      if (group.items.length === 1) {
                        selectView(group.items[0].view);
                        return;
                      }

                      toggleGroup(group.id);
                    }}
                  >
                    <span className={`eop-admin-spa-nav__icon dashicons ${group.icon}`} aria-hidden="true" />
                    <span className="eop-admin-spa-nav__name">{group.label}</span>
                    <span className="eop-admin-spa-nav__group-arrow dashicons dashicons-arrow-down-alt2" aria-hidden="true" />
                  </button>

                  <div className="eop-admin-spa-nav__submenu" hidden={!isOpen}>
                    {group.items.map((item) => (
                      <button
                        key={item.view}
                        type="button"
                        className={`eop-admin-spa-nav__submenu-item ${item.view === selectedView ? 'is-active' : ''}`}
                        onClick={() => selectView(item.view)}
                      >
                        <span className="eop-admin-spa-nav__submenu-label">{item.label}</span>
                      </button>
                    ))}
                  </div>
                </div>
              );
            })}

            {visibleUtilityNavItems.map((item) => (
              <button
                key={item.view}
                type="button"
                className={`eop-pdv-nav__item eop-admin-spa-nav__item ${item.view === selectedView ? 'is-active' : ''}`}
                aria-selected={item.view === selectedView}
                onClick={() => selectView(item.view)}
              >
                <span className={`eop-admin-spa-nav__icon dashicons ${item.icon}`} aria-hidden="true" />
                <span className="eop-admin-spa-nav__name">{item.label}</span>
              </button>
            ))}

            <a
              className="eop-pdv-nav__item eop-admin-spa-nav__item eop-admin-spa-nav__fallback"
              href={currentUrl}
            >
              <span className="eop-admin-spa-nav__icon dashicons dashicons-arrow-left-alt2" aria-hidden="true" />
              <span className="eop-admin-spa-nav__name">Abrir fallback legado</span>
            </a>
          </nav>
        </aside>

        <main className="eop-admin-spa__content eop-react-main">
          <div id="eop-notices" />

          <section className="eop-admin-panel-head eop-react-hero">
          <div>
            {editableSettings ? (
              <span className="eop-react-eyebrow">{labels[selectedView] || 'Configuracoes'}</span>
            ) : null}
            {editableSettings ? null : <h2>{labels[selectedView] || selectedView}</h2>}
            {settingsPayload?.meta?.description ? <p>{settingsPayload.meta.description}</p> : null}
          </div>
        </section>

        <section className="eop-react-card">
          {viewLoading ? (
            <div className="eop-react-loading" role="status" aria-live="polite">
              <span className="eop-react-loading__spinner" aria-hidden="true" />
              <span>Carregando...</span>
            </div>
          ) : null}
          {viewError ? <p className="eop-react-error">{viewError}</p> : null}

          {!viewLoading && !viewError && selectedView === 'settings-confirmation-documents' ? (
            <div className="eop-react-block">
              <div className="eop-react-block__head">
                <div>
                  <h4>Documentos do contrato</h4>
                  <p>
                    O gerenciador de documentos — cadastro, edicao e upload de arquivos para conversao
                    automatica em documentos de assinatura — ainda e operado pelo admin legado. Esta tela
                    sera migrada para o novo admin em uma etapa dedicada.
                  </p>
                </div>
                <a className="eop-react-link" href={currentUrl}>
                  Abrir gerenciador no legado
                </a>
              </div>
            </div>
          ) : null}

          {!viewLoading && !viewError && selectedView === 'new-order' ? (
            <div className="eop-react-block">
              <div className="eop-react-block__head">
                <div>
                  <h4>Novo pedido</h4>
                  <p>Fluxo REST funcional para criar pedidos sem sair do shell SPA.</p>
                </div>
                <div className="eop-react-totalbox">
                  <small>Total</small>
                  <strong>{formatCurrency(newOrderTotal, 'BRL')}</strong>
                </div>
              </div>

              {newOrderMessage ? (
                <p className={newOrderState === 'error' ? 'eop-react-error' : 'eop-react-success'}>
                  {newOrderMessage}
                  {createdOrderUrl ? (
                    <>
                      {' '}
                      <a href={createdOrderUrl}>Abrir pedido</a>
                    </>
                  ) : null}
                </p>
              ) : null}

              <div className="eop-react-form">
                <label className="eop-react-form__field">
                  <span>Documento</span>
                  <div className="eop-react-inline-action">
                    <input
                      type="text"
                      value={newOrderDraft.customer.document}
                      onChange={(event) =>
                        updateNewOrderDraft((current) => ({
                          ...current,
                          customer: {
                            ...current.customer,
                            document: event.target.value,
                          },
                        }))
                      }
                    />
                    <button
                      type="button"
                      className="eop-react-button"
                      onClick={() => {
                        void lookupNewOrderCustomer();
                      }}
                      disabled={customerLookupState === 'loading'}
                    >
                      {customerLookupState === 'loading' ? '...' : 'Buscar'}
                    </button>
                  </div>
                  {customerLookupMessage ? (
                    <small className={customerLookupState === 'error' ? 'eop-react-error' : ''}>
                      {customerLookupMessage}
                    </small>
                  ) : null}
                </label>

                <label className="eop-react-form__field">
                  <span>Status</span>
                  <select
                    value={newOrderDraft.status}
                    onChange={(event) =>
                      updateNewOrderDraft((current) => ({
                        ...current,
                        status: event.target.value,
                      }))
                    }
                  >
                    {orderStatusOptions
                      .filter((option) => option.value !== 'cancelled')
                      .map((option) => (
                        <option key={option.value} value={option.value}>
                          {option.label}
                        </option>
                      ))}
                  </select>
                </label>

                <label className="eop-react-form__field">
                  <span>Cliente</span>
                  <input
                    type="text"
                    value={newOrderDraft.customer.name}
                    onChange={(event) =>
                      updateNewOrderDraft((current) => ({
                        ...current,
                        customer: {
                          ...current.customer,
                          name: event.target.value,
                        },
                      }))
                    }
                  />
                </label>

                <label className="eop-react-form__field">
                  <span>Email</span>
                  <input
                    type="email"
                    value={newOrderDraft.customer.email}
                    onChange={(event) =>
                      updateNewOrderDraft((current) => ({
                        ...current,
                        customer: {
                          ...current.customer,
                          email: event.target.value,
                        },
                      }))
                    }
                  />
                </label>

                <label className="eop-react-form__field">
                  <span>Telefone</span>
                  <input
                    type="text"
                    value={newOrderDraft.customer.phone}
                    onChange={(event) =>
                      updateNewOrderDraft((current) => ({
                        ...current,
                        customer: {
                          ...current.customer,
                          phone: event.target.value,
                        },
                      }))
                    }
                  />
                </label>

                <label className="eop-react-form__field">
                  <span>Busca de produto</span>
                  <div className="eop-react-inline-action">
                    <input
                      type="search"
                      value={productTerm}
                      onChange={(event) => setProductTerm(event.target.value)}
                      onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                          event.preventDefault();
                          void searchNewOrderProducts();
                        }
                      }}
                    />
                    <button
                      type="button"
                      className="eop-react-button"
                      onClick={() => {
                        void searchNewOrderProducts();
                      }}
                      disabled={productSearchState === 'loading'}
                    >
                      {productSearchState === 'loading' ? '...' : 'Buscar'}
                    </button>
                  </div>
                  {productSearchMessage ? (
                    <small className={productSearchState === 'error' ? 'eop-react-error' : ''}>
                      {productSearchMessage}
                    </small>
                  ) : null}
                </label>
              </div>

              {productResults.length ? (
                <div className="eop-react-product-results">
                  {productResults.map((product) => (
                    <button
                      key={product.id}
                      type="button"
                      className="eop-react-product-result"
                      onClick={() => addProductToNewOrder(product)}
                    >
                      {product.image ? <img src={product.image} alt="" /> : null}
                      <span>
                        <strong>{product.name}</strong>
                        <small>{product.sku || 'sem SKU'} | {formatCurrency(product.price, 'BRL')}</small>
                      </span>
                    </button>
                  ))}
                </div>
              ) : null}

              <div className="eop-react-order-items">
                <div className="eop-react-block__head">
                  <div>
                    <h4>Itens</h4>
                    <p>{newOrderDraft.items.length} item(ns) no pedido.</p>
                  </div>
                </div>

                {newOrderDraft.items.map((item, index) => (
                  <div key={`${item.product_id}-${index}`} className="eop-react-item-card">
                    <div className="eop-react-item-card__head">
                      <div>
                        <strong>{item.name}</strong>
                        <small>{item.sku || 'sem SKU'} | {formatCurrency(item.price, 'BRL')}</small>
                      </div>
                      <button
                        type="button"
                        className="eop-react-link-button"
                        onClick={() =>
                          updateNewOrderDraft((current) => ({
                            ...current,
                            items: current.items.filter((_, currentIndex) => currentIndex !== index),
                          }))
                        }
                      >
                        Remover
                      </button>
                    </div>

                    <div className="eop-react-form eop-react-form--compact">
                      <label className="eop-react-form__field">
                        <span>Qtd</span>
                        <input
                          type="number"
                          min="1"
                          step="1"
                          value={item.quantity}
                          onChange={(event) =>
                            updateNewOrderDraft((current) => ({
                              ...current,
                              items: current.items.map((currentItem, currentIndex) =>
                                currentIndex === index
                                  ? {
                                      ...currentItem,
                                      quantity: Math.max(1, Math.round(parseNumber(event.target.value))),
                                    }
                                  : currentItem
                              ),
                            }))
                          }
                        />
                      </label>

                      <label className="eop-react-form__field">
                        <span>Tipo de desconto</span>
                        <select
                          value={item.discount_type}
                          onChange={(event) =>
                            updateNewOrderDraft((current) => ({
                              ...current,
                              items: current.items.map((currentItem, currentIndex) =>
                                currentIndex === index
                                  ? {
                                      ...currentItem,
                                      discount_type: event.target.value as 'fixed' | 'percent',
                                    }
                                  : currentItem
                              ),
                            }))
                          }
                        >
                          <option value="fixed">Valor fixo</option>
                          <option value="percent">Percentual</option>
                        </select>
                      </label>

                      <label className="eop-react-form__field">
                        <span>Desconto</span>
                        <input
                          type="number"
                          min="0"
                          step="0.01"
                          value={item.discount_value}
                          onChange={(event) =>
                            updateNewOrderDraft((current) => ({
                              ...current,
                              items: current.items.map((currentItem, currentIndex) =>
                                currentIndex === index
                                  ? {
                                      ...currentItem,
                                      discount_value: parseNumber(event.target.value),
                                    }
                                  : currentItem
                              ),
                            }))
                          }
                        />
                      </label>
                    </div>
                  </div>
                ))}
              </div>

              <div className="eop-react-form">
                <label className="eop-react-form__field">
                  <span>CEP</span>
                  <input
                    type="text"
                    value={newOrderDraft.shipping_address.postcode}
                    onChange={(event) =>
                      updateNewOrderDraft((current) => ({
                        ...current,
                        shipping_address: {
                          ...current.shipping_address,
                          postcode: event.target.value,
                        },
                      }))
                    }
                  />
                </label>

                <label className="eop-react-form__field">
                  <span>Estado</span>
                  <input
                    type="text"
                    value={newOrderDraft.shipping_address.state}
                    onChange={(event) =>
                      updateNewOrderDraft((current) => ({
                        ...current,
                        shipping_address: {
                          ...current.shipping_address,
                          state: event.target.value,
                        },
                      }))
                    }
                  />
                </label>

                <label className="eop-react-form__field">
                  <span>Cidade</span>
                  <input
                    type="text"
                    value={newOrderDraft.shipping_address.city}
                    onChange={(event) =>
                      updateNewOrderDraft((current) => ({
                        ...current,
                        shipping_address: {
                          ...current.shipping_address,
                          city: event.target.value,
                        },
                      }))
                    }
                  />
                </label>

                <label className="eop-react-form__field">
                  <span>Endereco</span>
                  <input
                    type="text"
                    value={newOrderDraft.shipping_address.address}
                    onChange={(event) =>
                      updateNewOrderDraft((current) => ({
                        ...current,
                        shipping_address: {
                          ...current.shipping_address,
                          address: event.target.value,
                        },
                      }))
                    }
                  />
                </label>

                <label className="eop-react-form__field">
                  <span>Numero</span>
                  <input
                    type="text"
                    value={newOrderDraft.shipping_address.number}
                    onChange={(event) =>
                      updateNewOrderDraft((current) => ({
                        ...current,
                        shipping_address: {
                          ...current.shipping_address,
                          number: event.target.value,
                        },
                      }))
                    }
                  />
                </label>

                <label className="eop-react-form__field">
                  <span>Bairro</span>
                  <input
                    type="text"
                    value={newOrderDraft.shipping_address.neighborhood}
                    onChange={(event) =>
                      updateNewOrderDraft((current) => ({
                        ...current,
                        shipping_address: {
                          ...current.shipping_address,
                          neighborhood: event.target.value,
                        },
                      }))
                    }
                  />
                </label>

                <label className="eop-react-form__field">
                  <span>Frete manual</span>
                  <input
                    type="number"
                    min="0"
                    step="0.01"
                    value={newOrderDraft.shipping}
                    onChange={(event) =>
                      updateNewOrderDraft((current) => ({
                        ...current,
                        shipping: parseNumber(event.target.value),
                      }))
                    }
                  />
                </label>

                <label className="eop-react-form__field">
                  <span>Metodo de frete</span>
                  <div className="eop-react-inline-action">
                    <input
                      type="text"
                      value={newOrderDraft.shipping_method}
                      onChange={(event) =>
                        updateNewOrderDraft((current) => ({
                          ...current,
                          shipping_method: event.target.value,
                        }))
                      }
                    />
                    <button
                      type="button"
                      className="eop-react-button"
                      onClick={() => {
                        void calculateNewOrderShipping();
                      }}
                      disabled={shippingState === 'loading'}
                    >
                      {shippingState === 'loading' ? '...' : 'Calcular'}
                    </button>
                  </div>
                  {shippingMessage ? (
                    <small className={shippingState === 'error' ? 'eop-react-error' : ''}>
                      {shippingMessage}
                    </small>
                  ) : null}
                </label>

                <label className="eop-react-form__field">
                  <span>Tipo de desconto geral</span>
                  <select
                    value={newOrderDraft.discount_type}
                    onChange={(event) =>
                      updateNewOrderDraft((current) => ({
                        ...current,
                        discount_type: event.target.value as 'fixed' | 'percent',
                      }))
                    }
                  >
                    <option value="fixed">Valor fixo</option>
                    <option value="percent">Percentual</option>
                  </select>
                </label>

                <label className="eop-react-form__field">
                  <span>Desconto geral</span>
                  <input
                    type="number"
                    min="0"
                    step="0.01"
                    value={newOrderDraft.discount}
                    onChange={(event) =>
                      updateNewOrderDraft((current) => ({
                        ...current,
                        discount: parseNumber(event.target.value),
                      }))
                    }
                  />
                </label>
              </div>

              {shippingPackages.length ? (
                <div className="eop-react-shipping-rates">
                  {shippingPackages.flatMap((shippingPackage) =>
                    shippingPackage.rates.map((rate) => (
                      <button
                        key={`${shippingPackage.package_key}-${rate.id}`}
                        type="button"
                        className={`eop-react-shipping-rate ${
                          newOrderDraft.shipping_rate?.id === rate.id ? 'is-active' : ''
                        }`}
                        onClick={() =>
                          updateNewOrderDraft((current) => ({
                            ...current,
                            shipping: rate.cost,
                            shipping_method: rate.label,
                            shipping_rate: rate,
                          }))
                        }
                      >
                        <strong>{rate.label}</strong>
                        <span>{formatCurrency(rate.cost, 'BRL')}</span>
                      </button>
                    ))
                  )}
                </div>
              ) : null}

              <div className="eop-react-block__foot">
                <div>
                  <strong>Subtotal: {formatCurrency(newOrderItemsTotal, 'BRL')}</strong>
                  <small>Frete: {formatCurrency(newOrderDraft.shipping, 'BRL')}</small>
                </div>
                <button
                  type="button"
                  className="eop-react-button"
                  onClick={() => {
                    void createNewOrder();
                  }}
                  disabled={newOrderState === 'saving' || newOrderDraft.items.length === 0}
                >
                  {newOrderState === 'saving' ? 'Criando...' : 'Criar pedido'}
                </button>
              </div>
            </div>
          ) : null}

          {!viewLoading && !viewError && selectedView === 'pdf' ? (
            <div className="eop-react-block">
              <div className="eop-react-block__head">
                <div>
                  <h4>Modulo PDF</h4>
                  <p>
                    O SPA principal esta ativo, mas a edicao completa do PDF ainda usa o modulo especializado ate a
                    quebra por dominio ficar pronta.
                  </p>
                </div>
                <a className="eop-react-link" href={currentUrl}>
                  Abrir PDF completo
                </a>
              </div>

              <div className="eop-react-module-grid">
                <article className="eop-react-module-card">
                  <strong>Configuracoes de loja</strong>
                  <span>Logo, dados institucionais, endereco, telefone e rodape continuam centralizados.</span>
                </article>
                <article className="eop-react-module-card">
                  <strong>Pedido e proposta</strong>
                  <span>Templates, numeracao, exibicao de colunas e textos serao separados em chunks proprios.</span>
                </article>
                <article className="eop-react-module-card">
                  <strong>Preview real</strong>
                  <span>O renderer PHP continua sendo a fonte de verdade para evitar divergencia visual.</span>
                </article>
                <article className="eop-react-module-card">
                  <strong>Proxima etapa</strong>
                  <span>Substituir o fallback por telas React especificas: loja, pedido, proposta e preview.</span>
                </article>
              </div>
            </div>
          ) : null}

          {!viewLoading && !viewError && editableSettings && settingsPayload ? (
            <div className="eop-react-block">
              {settingsPayload.meta?.intro ? (
                <div className="eop-react-block__head">
                  <p>
                    {settingsPayload.meta.intro}
                    {settingsPayload.meta.introLinkUrl ? (
                      <>
                        {' '}
                        <a href={settingsPayload.meta.introLinkUrl}>{settingsPayload.meta.introLinkLabel}</a>.
                      </>
                    ) : null}
                  </p>
                </div>
              ) : null}

              {settingsSaveMessage ? (
                <p className={settingsSaveState === 'error' ? 'eop-react-error' : 'eop-react-success'}>
                  {settingsSaveMessage}
                </p>
              ) : null}

              {renderSettingsGroups(settingsPayload.fields ?? [])}

              <div className="eop-react-save-bar">
                <button
                  type="button"
                  className="eop-react-button"
                  onClick={() => {
                    void saveCurrentSettings();
                  }}
                  disabled={settingsSaveState === 'saving'}
                >
                  {settingsSaveState === 'saving'
                    ? 'Salvando...'
                    : settingsPayload.meta?.saveLabel || 'Salvar configuracoes'}
                </button>
              </div>
            </div>
          ) : null}

          {!viewLoading && !viewError && settingsPayload && !editableSettings ? (
            <div className="eop-react-block">
              <h4>{settingsPayload.meta?.title || labels[selectedView] || settingsPayload.section}</h4>
              <div className="eop-react-keyvalues">
                {settingsEntries.map(([key, value]) => (
                  <div key={key} className="eop-react-kv">
                    <strong>{key}</strong>
                    <span>{typeof value === 'string' ? value : JSON.stringify(value)}</span>
                  </div>
                ))}
              </div>
            </div>
          ) : null}

          {!viewLoading && !viewError && ordersPayload ? (
            <div className="eop-react-block">
              <div className="eop-react-block__head">
                <div>
                  <h4>Pedidos</h4>
                  <p>Edicao REST real de pedidos existentes, com produtos, descontos e frete calculado.</p>
                </div>
                <small>
                  {ordersPayload.pagination.total_items} pedidos encontrados
                </small>
              </div>

              <div className="eop-react-orders-layout">
                <div className="eop-react-orders-list">
                  {ordersPayload.items.map((order) => (
                    <button
                      key={order.id}
                      type="button"
                      className={`eop-react-order ${selectedOrderId === order.id ? 'is-active' : ''}`}
                      onClick={() => setSelectedOrderId(order.id)}
                    >
                      <strong>#{order.number}</strong>
                      <span>{order.customer_name || 'Sem cliente'}</span>
                      <span>{order.status}</span>
                      <small>{formatCurrency(order.total, order.currency)}</small>
                    </button>
                  ))}
                </div>

                <div className="eop-react-order-editor">
                  {orderLoading ? <p>Carregando pedido selecionado.</p> : null}
                  {orderError ? <p className="eop-react-error">{orderError}</p> : null}

                  {!orderLoading && !orderError && orderDraft && orderDetail ? (
                    <div className="eop-react-block">
                      <div className="eop-react-block__head">
                        <div>
                          <h4>Pedido #{orderDetail.number}</h4>
                          <p>
                            Atualize dados do cliente, endereco, descontos e itens existentes.
                          </p>
                        </div>
                        <button
                          type="button"
                          className="eop-react-button"
                          onClick={() => {
                            void saveCurrentOrder();
                          }}
                          disabled={orderSaveState === 'saving'}
                        >
                          {orderSaveState === 'saving' ? 'Salvando...' : 'Salvar pedido'}
                        </button>
                      </div>

                      {orderSaveMessage ? (
                        <p className={orderSaveState === 'error' ? 'eop-react-error' : 'eop-react-success'}>
                          {orderSaveMessage}
                        </p>
                      ) : null}

                      <div className="eop-react-form">
                        <label className="eop-react-form__field">
                          <span>Status</span>
                          <select
                            value={orderDraft.status}
                            onChange={(event) =>
                              updateOrderDraft((current) => ({
                                ...current,
                                status: event.target.value,
                              }))
                            }
                          >
                            {orderStatusOptions.map((option) => (
                              <option key={option.value} value={option.value}>
                                {option.label}
                              </option>
                            ))}
                          </select>
                        </label>

                        <label className="eop-react-form__field">
                          <span>Metodo de frete</span>
                          <div className="eop-react-inline-action">
                            <input
                              type="text"
                              value={orderDraft.shipping_method}
                              onChange={(event) =>
                                updateOrderDraft((current) => ({
                                  ...current,
                                  shipping_method: event.target.value,
                                }))
                              }
                            />
                            <button
                              type="button"
                              className="eop-react-button"
                              onClick={() => {
                                void calculateOrderShipping();
                              }}
                              disabled={orderShippingState === 'loading'}
                            >
                              {orderShippingState === 'loading' ? '...' : 'Calcular'}
                            </button>
                          </div>
                          {orderShippingMessage ? (
                            <small className={orderShippingState === 'error' ? 'eop-react-error' : ''}>
                              {orderShippingMessage}
                            </small>
                          ) : null}
                        </label>

                        <label className="eop-react-form__field">
                          <span>Cliente</span>
                          <input
                            type="text"
                            value={orderDraft.customer.name}
                            onChange={(event) =>
                              updateOrderDraft((current) => ({
                                ...current,
                                customer: {
                                  ...current.customer,
                                  name: event.target.value,
                                },
                              }))
                            }
                          />
                        </label>

                        <label className="eop-react-form__field">
                          <span>Email</span>
                          <input
                            type="email"
                            value={orderDraft.customer.email}
                            onChange={(event) =>
                              updateOrderDraft((current) => ({
                                ...current,
                                customer: {
                                  ...current.customer,
                                  email: event.target.value,
                                },
                              }))
                            }
                          />
                        </label>

                        <label className="eop-react-form__field">
                          <span>Telefone</span>
                          <input
                            type="text"
                            value={orderDraft.customer.phone}
                            onChange={(event) =>
                              updateOrderDraft((current) => ({
                                ...current,
                                customer: {
                                  ...current.customer,
                                  phone: event.target.value,
                                },
                              }))
                            }
                          />
                        </label>

                        <label className="eop-react-form__field">
                          <span>Documento</span>
                          <input
                            type="text"
                            value={orderDraft.customer.document}
                            onChange={(event) =>
                              updateOrderDraft((current) => ({
                                ...current,
                                customer: {
                                  ...current.customer,
                                  document: event.target.value,
                                },
                              }))
                            }
                          />
                        </label>

                        <label className="eop-react-form__field">
                          <span>Frete</span>
                          <input
                            type="number"
                            min="0"
                            step="0.01"
                            value={orderDraft.shipping}
                            onChange={(event) =>
                              updateOrderDraft((current) => ({
                                ...current,
                                shipping: parseNumber(event.target.value),
                              }))
                            }
                          />
                        </label>

                        <label className="eop-react-form__field">
                          <span>Tipo de desconto geral</span>
                          <select
                            value={orderDraft.discount_type}
                            onChange={(event) =>
                              updateOrderDraft((current) => ({
                                ...current,
                                discount_type: event.target.value as 'fixed' | 'percent',
                              }))
                            }
                          >
                            <option value="fixed">Valor fixo</option>
                            <option value="percent">Percentual</option>
                          </select>
                        </label>

                        <label className="eop-react-form__field">
                          <span>Desconto geral</span>
                          <input
                            type="number"
                            min="0"
                            step="0.01"
                            value={orderDraft.discount}
                            onChange={(event) =>
                              updateOrderDraft((current) => ({
                                ...current,
                                discount: parseNumber(event.target.value),
                              }))
                            }
                          />
                        </label>

                        <label className="eop-react-form__field">
                          <span>CEP</span>
                          <input
                            type="text"
                            value={orderDraft.shipping_address.postcode}
                            onChange={(event) =>
                              updateOrderDraft((current) => ({
                                ...current,
                                shipping_address: {
                                  ...current.shipping_address,
                                  postcode: event.target.value,
                                },
                              }))
                            }
                          />
                        </label>

                        <label className="eop-react-form__field">
                          <span>Estado</span>
                          <input
                            type="text"
                            value={orderDraft.shipping_address.state}
                            onChange={(event) =>
                              updateOrderDraft((current) => ({
                                ...current,
                                shipping_address: {
                                  ...current.shipping_address,
                                  state: event.target.value,
                                },
                              }))
                            }
                          />
                        </label>

                        <label className="eop-react-form__field">
                          <span>Cidade</span>
                          <input
                            type="text"
                            value={orderDraft.shipping_address.city}
                            onChange={(event) =>
                              updateOrderDraft((current) => ({
                                ...current,
                                shipping_address: {
                                  ...current.shipping_address,
                                  city: event.target.value,
                                },
                              }))
                            }
                          />
                        </label>

                        <label className="eop-react-form__field">
                          <span>Endereco</span>
                          <input
                            type="text"
                            value={orderDraft.shipping_address.address}
                            onChange={(event) =>
                              updateOrderDraft((current) => ({
                                ...current,
                                shipping_address: {
                                  ...current.shipping_address,
                                  address: event.target.value,
                                },
                              }))
                            }
                          />
                        </label>

                        <label className="eop-react-form__field">
                          <span>Numero</span>
                          <input
                            type="text"
                            value={orderDraft.shipping_address.number}
                            onChange={(event) =>
                              updateOrderDraft((current) => ({
                                ...current,
                                shipping_address: {
                                  ...current.shipping_address,
                                  number: event.target.value,
                                },
                              }))
                            }
                          />
                        </label>

                        <label className="eop-react-form__field">
                          <span>Bairro</span>
                          <input
                            type="text"
                            value={orderDraft.shipping_address.neighborhood}
                            onChange={(event) =>
                              updateOrderDraft((current) => ({
                                ...current,
                                shipping_address: {
                                  ...current.shipping_address,
                                  neighborhood: event.target.value,
                                },
                              }))
                            }
                          />
                        </label>

                        <label className="eop-react-form__field eop-react-form__field--full">
                          <span>Complemento</span>
                          <input
                            type="text"
                            value={orderDraft.shipping_address.address_2}
                            onChange={(event) =>
                              updateOrderDraft((current) => ({
                                ...current,
                                shipping_address: {
                                  ...current.shipping_address,
                                  address_2: event.target.value,
                                },
                              }))
                            }
                          />
                        </label>

                        <label className="eop-react-form__field eop-react-form__field--full">
                          <span>Observacao interna</span>
                          <textarea
                            rows={4}
                            value={orderDraft.notes}
                            onChange={(event) =>
                              updateOrderDraft((current) => ({
                                ...current,
                                notes: event.target.value,
                              }))
                            }
                          />
                          <small>Esta etapa grava uma nota no pedido ao salvar.</small>
                        </label>
                      </div>

                      {orderShippingPackages.length ? (
                        <div className="eop-react-shipping-rates">
                          {orderShippingPackages.flatMap((shippingPackage) =>
                            shippingPackage.rates.map((rate) => (
                              <button
                                key={`${shippingPackage.package_key}-${rate.id}`}
                                type="button"
                                className={`eop-react-shipping-rate ${
                                  orderDraft.shipping_rate?.id === rate.id ? 'is-active' : ''
                                }`}
                                onClick={() =>
                                  updateOrderDraft((current) => ({
                                    ...current,
                                    shipping: rate.cost,
                                    shipping_method: rate.label,
                                    shipping_rate: rate,
                                  }))
                                }
                              >
                                <strong>{rate.label}</strong>
                                <span>{formatCurrency(rate.cost, orderDetail.currency)}</span>
                              </button>
                            ))
                          )}
                        </div>
                      ) : null}

                      <div className="eop-react-order-items">
                        <div className="eop-react-block__head">
                          <div>
                            <h4>Itens atuais</h4>
                            <p>Voce pode adicionar produto, ajustar quantidade e desconto.</p>
                          </div>
                        </div>

                        <div className="eop-react-form">
                          <label className="eop-react-form__field eop-react-form__field--full">
                            <span>Adicionar produto</span>
                            <div className="eop-react-inline-action">
                              <input
                                type="search"
                                value={orderProductTerm}
                                onChange={(event) => setOrderProductTerm(event.target.value)}
                                onKeyDown={(event) => {
                                  if (event.key === 'Enter') {
                                    event.preventDefault();
                                    void searchOrderProducts();
                                  }
                                }}
                              />
                              <button
                                type="button"
                                className="eop-react-button"
                                onClick={() => {
                                  void searchOrderProducts();
                                }}
                                disabled={orderProductSearchState === 'loading'}
                              >
                                {orderProductSearchState === 'loading' ? '...' : 'Buscar'}
                              </button>
                            </div>
                            {orderProductSearchMessage ? (
                              <small className={orderProductSearchState === 'error' ? 'eop-react-error' : ''}>
                                {orderProductSearchMessage}
                              </small>
                            ) : null}
                          </label>
                        </div>

                        {orderProductResults.length ? (
                          <div className="eop-react-product-results">
                            {orderProductResults.map((product) => (
                              <button
                                key={product.id}
                                type="button"
                                className="eop-react-product-result"
                                onClick={() => addProductToOrderDraft(product)}
                              >
                                {product.image ? <img src={product.image} alt="" /> : null}
                                <span>
                                  <strong>{product.name}</strong>
                                  <small>{product.sku || 'sem SKU'} | {formatCurrency(product.price, orderDetail.currency)}</small>
                                </span>
                              </button>
                            ))}
                          </div>
                        ) : null}

                        {orderDraft.items.map((item, index) => (
                          <div key={`${item.product_id}-${index}`} className="eop-react-item-card">
                            <div className="eop-react-item-card__head">
                              <div>
                                <strong>{item.name}</strong>
                                <small>SKU: {item.sku || 'sem SKU'}</small>
                              </div>
                              <button
                                type="button"
                                className="eop-react-link-button"
                                onClick={() =>
                                  updateOrderDraft((current) => ({
                                    ...current,
                                    items: current.items.filter((_, currentIndex) => currentIndex !== index),
                                  }))
                                }
                              >
                                Remover
                              </button>
                            </div>

                            <div className="eop-react-form eop-react-form--compact">
                              <label className="eop-react-form__field">
                                <span>Preco base</span>
                                <input type="text" value={formatCurrency(item.price, orderDetail.currency)} readOnly />
                              </label>

                              <label className="eop-react-form__field">
                                <span>Quantidade</span>
                                <input
                                  type="number"
                                  min="1"
                                  step="1"
                                  value={item.quantity}
                                  onChange={(event) =>
                                    updateOrderDraft((current) => ({
                                      ...current,
                                      items: current.items.map((currentItem, currentIndex) =>
                                        currentIndex === index
                                          ? {
                                              ...currentItem,
                                              quantity: Math.max(1, Math.round(parseNumber(event.target.value))),
                                            }
                                          : currentItem
                                      ),
                                    }))
                                  }
                                />
                              </label>

                              <label className="eop-react-form__field">
                                <span>Tipo de desconto</span>
                                <select
                                  value={item.discount_type}
                                  onChange={(event) =>
                                    updateOrderDraft((current) => ({
                                      ...current,
                                      items: current.items.map((currentItem, currentIndex) =>
                                        currentIndex === index
                                          ? {
                                              ...currentItem,
                                              discount_type: event.target.value as 'fixed' | 'percent',
                                            }
                                          : currentItem
                                      ),
                                    }))
                                  }
                                >
                                  <option value="fixed">Valor fixo</option>
                                  <option value="percent">Percentual</option>
                                </select>
                              </label>

                              <label className="eop-react-form__field">
                                <span>Valor do desconto</span>
                                <input
                                  type="number"
                                  min="0"
                                  step="0.01"
                                  value={item.discount_value}
                                  onChange={(event) =>
                                    updateOrderDraft((current) => ({
                                      ...current,
                                      items: current.items.map((currentItem, currentIndex) =>
                                        currentIndex === index
                                          ? {
                                              ...currentItem,
                                              discount_value: parseNumber(event.target.value),
                                            }
                                          : currentItem
                                      ),
                                    }))
                                  }
                                />
                              </label>
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  ) : null}

                  {!orderLoading && !orderError && !orderDraft ? (
                    <p>Selecione um pedido para editar.</p>
                  ) : null}
                </div>
              </div>
            </div>
          ) : null}

          {!viewLoading && !viewError && previewPayload ? (
            <div className="eop-react-block">
              {previewPayload.mode === 'iframe' && previewPayload.url ? (
                <iframe title={previewPayload.surface} src={previewPayload.url} className="eop-react-preview-frame" />
              ) : null}
              {previewPayload.mode === 'html' && previewPayload.html ? (
                <div
                  className="eop-react-preview-html"
                  dangerouslySetInnerHTML={{ __html: previewPayload.html }}
                />
              ) : null}
            </div>
          ) : null}

          {!viewLoading && !viewError && selectedView === 'documentation' ? (
            <div className="eop-react-block">
              <h4>Documentacao canonica</h4>
              <ul className="eop-react-docs">
                {Object.entries(bootstrap.docs).map(([key, url]) => (
                  <li key={key}>
                    <a href={url} target="_blank" rel="noreferrer">
                      {key}
                    </a>
                  </li>
                ))}
              </ul>
            </div>
          ) : null}

          {!viewLoading && !viewError && selectedView === 'license' ? (
            <div className="eop-react-block">
              <h4>Licenca e governanca</h4>
              <p>O novo shell ainda delega a gestao completa da licenca para o fluxo legado.</p>
            </div>
          ) : null}
        </section>
        </main>
      </div>
    </div>
  );
}

export default App;
