export type BootstrapPayload = {
  user: {
    id: number;
    displayName: string;
    email: string;
  };
  license: {
    valid: boolean;
  };
  flags: {
    newAdminEnabled: boolean;
    hasBuiltBundle: boolean;
    canManageSettings: boolean;
    canEditShopOrders: boolean;
    legacyForced: boolean;
  };
  branding: {
    panelTitle: string;
    panelSubtitle: string;
    logoUrl: string;
    primaryColor: string;
    surfaceColor: string;
    borderColor: string;
    borderRadius: number;
    fontFamily: string;
  };
  routes: {
    restRoot: string;
    legacyRoot: string;
    viewUrls: Record<string, string>;
    legacyViewUrls?: Record<string, string>;
  };
  views: string[];
  initialView: string;
  sections: string[];
  docs: Record<string, string>;
};

export type NavItem = {
  view: string;
  label: string;
  icon: string;
};

export type NavGroup = {
  id: string;
  label: string;
  icon: string;
  items: NavItem[];
};

export type SettingsField = {
  key: string;
  type: 'text' | 'select' | 'toggle' | 'textarea' | 'media' | 'color' | 'number' | 'multiselect';
  label: string;
  group?: string;
  subgroup?: string;
  default?: string;
  help?: string;
  searchSource?: 'products' | 'categories';
  minChars?: number;
  selected?: Array<{
    value: string;
    label: string;
  }>;
  options?: Array<{
    value: string;
    label: string;
  }>;
};

export type ConfirmationDocument = {
  key: string;
  title: string;
  description: string;
  source_type: 'editor' | 'attachment';
  body: string;
  attachment_id: number;
  attachment_name?: string;
  attachment_url?: string;
  button_label: string;
  view_label: string;
};

export type ConfirmationDocumentsPayload = {
  documents: ConfirmationDocument[];
  placeholderTokens: string[];
};

export type SettingsMeta = {
  title: string;
  description: string;
  intro: string;
  introLinkLabel: string;
  introLinkUrl: string;
  saveLabel: string;
};

export type SettingsPayload = {
  section: string;
  source: string;
  values: Record<string, unknown>;
  fields?: SettingsField[];
  meta?: SettingsMeta;
};

export type FlowStageControls = {
  can_update?: boolean;
  current?: string;
  options?: Array<{ value: string; label: string }>;
};

export type FlowSummary = {
  active_for_order?: boolean;
  stage_label?: string;
  contract?: { accepted?: boolean };
  documents?: { completed?: number; total?: number };
  attachment?: { uploaded?: boolean; required?: boolean };
  final_pdf?: { ready?: boolean };
  products?: { completed?: number; editable?: number };
  stage_controls?: FlowStageControls | null;
};

export type OrderSummary = {
  id: number;
  number: string;
  status: string;
  status_label?: string;
  total: number;
  total_html?: string;
  currency: string;
  customer_name: string;
  customer_email?: string;
  created_at: string;
  date_label?: string;
  edit_url: string;
  wc_url?: string;
  pdf_url?: string;
  public_url: string;
  is_proposal?: boolean;
  created_by_name?: string;
  post_confirmation_flow_summary?: FlowSummary;
};

export type OrdersPayload = {
  items: OrderSummary[];
  pagination: {
    page: number;
    per_page: number;
    total_items: number;
    total_pages: number;
  };
  viewer?: {
    is_admin?: boolean;
  };
};

export type PreviewPayload = {
  surface: string;
  mode: 'iframe' | 'html';
  url?: string;
  html?: string;
  source: string;
};

export type OrderCustomer = {
  user_id: number;
  name: string;
  email: string;
  phone: string;
  document: string;
};

export type OrderItem = {
  product_id: number;
  name: string;
  sku: string;
  price: number;
  quantity: number;
  discount_type: 'fixed' | 'percent';
  discount_value: number;
  image: string;
};

export type ShippingAddress = {
  postcode: string;
  state: string;
  city: string;
  address: string;
  number: string;
  neighborhood: string;
  address_2: string;
};

export type ShippingRate = {
  id: string;
  label: string;
  cost: number;
  tax: number;
  method_id: string;
  instance_id: number;
  meta_data: Record<string, string>;
  package: number;
};

export type OrderDetail = OrderSummary & {
  order_id: number;
  customer: OrderCustomer;
  items: OrderItem[];
  shipping: number;
  shipping_method: string;
  shipping_address: ShippingAddress;
  shipping_rate: ShippingRate | null;
  discount: number;
  discount_type: 'fixed' | 'percent';
  notes: string;
  post_confirmation_flow?: unknown;
};

export type OrderDetailResponse = {
  order: OrderDetail;
  message?: string;
};

export type OrderDraft = {
  order_id: number;
  status: string;
  customer: OrderCustomer;
  items: OrderItem[];
  shipping: number;
  shipping_method: string;
  shipping_address: ShippingAddress;
  shipping_rate: ShippingRate | null;
  discount: number;
  discount_type: 'fixed' | 'percent';
  notes: string;
};

export type ProductResult = {
  id: number;
  text: string;
  price: number;
  name: string;
  sku: string;
  image: string;
};

export type ProductSearchResponse = {
  results: ProductResult[];
};

export type CustomerSearchResult = OrderCustomer & {
  found: boolean;
};

export type ShippingPackage = {
  package_key: number;
  package_name: string;
  rates: ShippingRate[];
};

export type ShippingRatesResponse = {
  rates: ShippingPackage[];
};

export type NewOrderDraft = {
  customer: OrderCustomer;
  items: OrderItem[];
  shipping: number;
  shipping_method: string;
  shipping_address: ShippingAddress;
  shipping_rate: ShippingRate | null;
  discount: number;
  discount_type: 'fixed' | 'percent';
  status: string;
};

export type OrderCreateResponse = {
  result: {
    order_id: number;
    order_url: string;
    pdf_url: string;
    public_url: string;
    flow_mode: string;
  };
  order: OrderSummary | null;
};

export type AdminSpaConfig = {
  rest_root?: string;
  rest_nonce?: string;
  legacy_admin_url?: string;
  initial_view?: string;
  discount_mode?: 'both' | 'percent' | 'fixed';
};
