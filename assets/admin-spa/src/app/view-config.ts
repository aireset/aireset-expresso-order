import type { NavGroup, NavItem } from './types';

export const labels: Record<string, string> = {
  'new-order': 'Novo pedido',
  orders: 'Pedidos',
  pdf: 'PDF',
  'settings-store-info': 'Informacoes da loja',
  'settings-general-config': 'Configuracoes gerais',
  'settings-confirmation-general': 'Confirmacao geral',
  'settings-confirmation-documents': 'Documentos',
  'settings-confirmation-preview': 'Preview contratual',
  'settings-confirmation-upload-products-preview': 'Preview upload e produtos',
  'settings-proposal-link-style': 'Visual da proposta',
  'settings-new-order-style': 'Visual do formulario',
  'settings-orders-list-style': 'Visual da listagem',
  'settings-texts': 'Textos',
  documentation: 'Documentacao',
  'export-import': 'Exportar e importar',
  license: 'Licenca',
};

export const primaryNavItems: NavItem[] = [
  { view: 'new-order', label: 'Novo pedido', icon: 'dashicons-cart' },
  { view: 'orders', label: 'Pedidos', icon: 'dashicons-list-view' },
];

export const navGroups: NavGroup[] = [
  {
    id: 'general',
    label: 'Geral',
    icon: 'dashicons-admin-generic',
    items: [
      { view: 'settings-store-info', label: 'Informacoes sobre a loja', icon: 'dashicons-store' },
      { view: 'settings-general-config', label: 'Configuracoes Gerais', icon: 'dashicons-admin-settings' },
      { view: 'settings-proposal-link-style', label: 'Visual da Proposta do Cliente', icon: 'dashicons-format-image' },
      { view: 'settings-new-order-style', label: 'Visual do Formulario de Pedido', icon: 'dashicons-cart' },
      { view: 'settings-orders-list-style', label: 'Visual da Listagem de Pedidos', icon: 'dashicons-list-view' },
    ],
  },
  {
    id: 'confirmation',
    label: 'Fluxo de Confirmacao',
    icon: 'dashicons-yes-alt',
    items: [
      { view: 'settings-confirmation-general', label: 'Configuracoes Gerais', icon: 'dashicons-admin-settings' },
      { view: 'settings-confirmation-documents', label: 'Documentos', icon: 'dashicons-media-document' },
      { view: 'settings-confirmation-preview', label: 'Visual da pagina de confirmacao', icon: 'dashicons-visibility' },
      { view: 'settings-confirmation-upload-products-preview', label: 'Visual da pagina de upload e produtos', icon: 'dashicons-upload' },
    ],
  },
  {
    id: 'pdf',
    label: 'PDF',
    icon: 'dashicons-media-document',
    items: [
      { view: 'pdf', label: 'Modulo PDF', icon: 'dashicons-media-document' },
    ],
  },
];

export const utilityNavItems: NavItem[] = [
  { view: 'documentation', label: 'Documentacao', icon: 'dashicons-book-alt' },
  { view: 'export-import', label: 'Exportar e Importar', icon: 'dashicons-migrate' },
  { view: 'license', label: 'Licenca', icon: 'dashicons-admin-network' },
];

export const orderStatusOptions = [
  { value: 'pending', label: 'Pendente' },
  { value: 'processing', label: 'Processando' },
  { value: 'on-hold', label: 'Em espera' },
  { value: 'completed', label: 'Concluido' },
  { value: 'cancelled', label: 'Cancelado' },
];

export const settingsSectionByView: Record<string, string> = {
  'settings-store-info': 'store',
  'settings-general-config': 'general',
  'settings-proposal-link-style': 'proposal',
  'settings-new-order-style': 'new-order',
  'settings-orders-list-style': 'orders-list',
  'settings-confirmation-general': 'confirmation-general',
  'settings-confirmation-preview': 'confirmation-contract',
  'settings-confirmation-upload-products-preview': 'confirmation-upload-products',
};

export const previewSurfaceByView: Record<string, string> = {
  'settings-proposal-link-style': 'proposal',
  'settings-new-order-style': 'new-order',
  'settings-confirmation-preview': 'confirmation-contract',
  'settings-confirmation-upload-products-preview': 'confirmation-upload-products',
};
