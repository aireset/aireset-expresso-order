import type {
  AdminSpaConfig,
  BootstrapPayload,
  ConfirmationDocument,
  ConfirmationDocumentsPayload,
  CustomerSearchResult,
  NewOrderDraft,
  OrderCreateResponse,
  OrderDetailResponse,
  OrderDraft,
  OrdersPayload,
  PreviewPayload,
  ProductSearchResponse,
  SettingsPayload,
  ShippingAddress,
  ShippingRatesResponse,
  OrderItem,
} from './types';

declare global {
  interface Window {
    eopAdminSpaConfig?: AdminSpaConfig;
    eopAdminSpaBootstrap?: BootstrapPayload;
  }
}

export function getAdminSpaConfig(): AdminSpaConfig | null {
  const config = window.eopAdminSpaConfig;

  if (!config?.rest_root || !config?.rest_nonce) {
    return null;
  }

  return config;
}

// Bootstrap injetado inline pelo PHP no render da pagina, eliminando o round-trip
// REST no primeiro carregamento. Cai para o fetch REST se ausente.
export function getInlineBootstrap(): BootstrapPayload | null {
  return window.eopAdminSpaBootstrap ?? null;
}

function getRestUrl(path: string): string {
  const config = getAdminSpaConfig();

  if (!config?.rest_root) {
    throw new Error('Configuracao REST do admin SPA nao encontrada.');
  }

  return `${config.rest_root.replace(/\/$/, '')}/${path.replace(/^\//, '')}`;
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const config = getAdminSpaConfig();

  if (!config?.rest_nonce) {
    throw new Error('Nonce REST do admin SPA nao encontrado.');
  }

  const response = await fetch(getRestUrl(path), {
    ...init,
    headers: {
      ...(init.body ? { 'Content-Type': 'application/json' } : {}),
      ...(init.headers || {}),
      'X-WP-Nonce': config.rest_nonce,
    },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    throw new Error(`Falha na requisicao ${path} (${response.status})`);
  }

  return (await response.json()) as T;
}

export const adminApi = {
  getBootstrap: () => request<BootstrapPayload>('bootstrap'),
  getSettings: (section: string) => request<SettingsPayload>(`settings/${section}`),
  updateSettings: (section: string, values: Record<string, string>) =>
    request<SettingsPayload>(`settings/${section}`, {
      method: 'POST',
      body: JSON.stringify({ values }),
    }),
  getPreview: (surface: string) => request<PreviewPayload>(`previews/${surface}`),
  getConfirmationDocuments: () => request<ConfirmationDocumentsPayload>('confirmation-documents'),
  saveConfirmationDocuments: (documents: ConfirmationDocument[]) =>
    request<ConfirmationDocumentsPayload>('confirmation-documents', {
      method: 'POST',
      body: JSON.stringify({ documents }),
    }),
  getOrders: (params: { status?: string; flow?: string; search?: string; page?: number } = {}) => {
    const query = new URLSearchParams();
    if (params.status && params.status !== 'any') query.set('status', params.status);
    if (params.flow && params.flow !== 'any') query.set('flow', params.flow);
    if (params.search) query.set('search', params.search);
    if (params.page && params.page > 1) query.set('page', String(params.page));
    const qs = query.toString();
    return request<OrdersPayload>(qs ? `orders?${qs}` : 'orders');
  },
  getOrder: (id: number) => request<OrderDetailResponse>(`orders/${id}`),
  createOrder: (order: NewOrderDraft) =>
    request<OrderCreateResponse>('orders', {
      method: 'POST',
      body: JSON.stringify({ order }),
    }),
  updateOrder: (id: number, order: OrderDraft) =>
    request<OrderDetailResponse>(`orders/${id}`, {
      method: 'PUT',
      body: JSON.stringify({ order }),
    }),
  searchCustomer: (document: string) => {
    const params = new URLSearchParams({ document });

    return request<CustomerSearchResult>(`customers/search?${params.toString()}`);
  },
  searchProducts: (term: string) => {
    const params = new URLSearchParams({ term });

    return request<ProductSearchResponse>(`products?${params.toString()}`);
  },
  searchProductCategories: (term: string) => {
    const params = new URLSearchParams({ term });

    return request<{ results: Array<{ id: number; text: string }> }>(
      `product-categories?${params.toString()}`
    );
  },
  calculateShipping: (items: OrderItem[], address: ShippingAddress) =>
    request<ShippingRatesResponse>('shipping/rates', {
      method: 'POST',
      body: JSON.stringify({ items, address }),
    }),
  updateOrderStage: (id: number, stage: string) =>
    request<unknown>(`orders/${id}/post-confirmation/stage`, {
      method: 'PUT',
      body: JSON.stringify({ stage }),
    }),
};
