<?php
defined( 'ABSPATH' ) || exit;

class EOP_Admin_SPA {

	const OPTION_KEY     = 'eop_admin_experimental';
	const REST_NAMESPACE = 'aireset-expresso-order/v1/admin';

	private static $manifest = null;
	private static $section_definitions = null;

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	public static function register_settings() {
		register_setting(
			'eop_settings_group',
			self::OPTION_KEY,
			array( __CLASS__, 'sanitize_settings' )
		);
	}

	public static function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'enabled' => 'yes' === ( $input['enabled'] ?? 'no' ) ? 'yes' : 'no',
		);
	}

	public static function get_settings() {
		$stored = get_option( self::OPTION_KEY, null );

		return wp_parse_args(
			is_array( $stored ) ? $stored : array(),
			array(
				'enabled' => 'yes',
			)
		);
	}

	public static function is_enabled() {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return false;
		}

		if ( ! self::has_built_assets() ) {
			return false;
		}

		if ( self::is_legacy_forced() ) {
			return false;
		}

		$settings = self::get_settings();
		$enabled  = 'yes' === (string) $settings['enabled'];

		return (bool) apply_filters( 'eop_enable_admin_spa', $enabled, $settings );
	}

	public static function is_legacy_forced() {
		return current_user_can( 'manage_options' )
			&& isset( $_GET['eop_admin_legacy'] )
			&& '1' === sanitize_text_field( wp_unslash( $_GET['eop_admin_legacy'] ) );
	}

	public static function has_built_assets() {
		return is_array( self::get_manifest() );
	}

	public static function enqueue_assets( $hook ) {
		if ( 'aireset_page_eop-pedido-expresso' !== $hook ) {
			return;
		}

		$entry = self::get_entry_manifest();

		if ( empty( $entry['file'] ) ) {
			return;
		}

		// Disponibiliza wp.media para o seletor de logo das telas de settings no SPA.
		wp_enqueue_media();

		// Coloris: mesmo seletor de cor do admin legado, usado pelos campos color do SPA.
		wp_enqueue_style( 'eop-coloris', EOP_PLUGIN_URL . 'assets/css/coloris.min.css', array(), EOP_VERSION );
		wp_enqueue_script( 'eop-coloris', EOP_PLUGIN_URL . 'assets/js/coloris.min.js', array(), EOP_VERSION, true );

		$asset_base = trailingslashit( EOP_PLUGIN_URL . 'assets/admin-spa/dist' );
		$file_path  = EOP_PLUGIN_DIR . 'assets/admin-spa/dist/' . ltrim( (string) $entry['file'], '/\\' );

		add_filter( 'script_loader_tag', array( __CLASS__, 'filter_script_loader_tag' ), 10, 3 );

		wp_enqueue_script(
			'eop-admin-spa',
			$asset_base . ltrim( (string) $entry['file'], '/\\' ),
			array(),
			file_exists( $file_path ) ? (string) filemtime( $file_path ) : EOP_VERSION,
			true
		);

		wp_add_inline_script(
			'eop-admin-spa',
			'window.eopAdminSpaConfig=' . wp_json_encode( self::get_client_config() ) . ';',
			'before'
		);

		if ( ! empty( $entry['css'] ) && is_array( $entry['css'] ) ) {
			foreach ( $entry['css'] as $index => $css_file ) {
				$css_file = ltrim( (string) $css_file, '/\\' );
				$css_path = EOP_PLUGIN_DIR . 'assets/admin-spa/dist/' . $css_file;
				wp_enqueue_style(
					'eop-admin-spa-' . $index,
					$asset_base . $css_file,
					array(),
					file_exists( $css_path ) ? (string) filemtime( $css_path ) : EOP_VERSION
				);
			}
		}

		// Estilos legados dos componentes operacionais (Novo pedido / Pedidos)
		// renderizados pelos componentes React (.eop-pdv-grid, .eop-card,
		// .eop-accordion, .eop-totals, .eop-shipping-*, .eop-order-card-*, etc.).
		// O modo fullscreen do shell (que esconderia o menu do WP) fica desativado
		// em filter_admin_body_class, entao carregar o admin.css completo e seguro.
		$admin_css_path = EOP_PLUGIN_DIR . 'assets/css/admin.css';
		wp_enqueue_style(
			'eop-admin-spa-legacy',
			EOP_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			file_exists( $admin_css_path ) ? (string) filemtime( $admin_css_path ) : EOP_VERSION
		);

		// Mesmo CSS da tela de vendas (shortcode [expresso_order]). Como o conteudo
		// React do admin usa os MESMOS componentes (NewOrderForm/OrdersBrowser) e o
		// wrapper tem a classe .eop-pdv, as regras `.eop-pdv ...` do frontend.css
		// estilizam o conteudo identico a tela de vendas. Carregado depois do
		// admin.css para vencer por ordem/especificidade.
		$frontend_css_path = EOP_PLUGIN_DIR . 'assets/css/frontend.css';
		wp_enqueue_style(
			'eop-admin-spa-frontend',
			EOP_PLUGIN_URL . 'assets/css/frontend.css',
			array( 'eop-admin-spa-legacy' ),
			file_exists( $frontend_css_path ) ? (string) filemtime( $frontend_css_path ) : EOP_VERSION
		);

		// CSS do recolher de sidebar (botao "<"): colapsa a nav do plugin para uma
		// faixa de icones com submenus em fly-in (.eop-admin-spa.is-sidebar-collapsed).
		$flyin_css_path = EOP_PLUGIN_DIR . 'assets/css/admin-flyinmenu.css';
		if ( file_exists( $flyin_css_path ) ) {
			wp_enqueue_style(
				'eop-admin-spa-flyinmenu',
				EOP_PLUGIN_URL . 'assets/css/admin-flyinmenu.css',
				array( 'eop-admin-spa-legacy' ),
				(string) filemtime( $flyin_css_path )
			);
		}

		// Estrutura visual do preview de PDF (.eop-pdf-preview) nas telas de PDF do SPA.
		$pdf_css_path = EOP_PLUGIN_DIR . 'assets/css/pdf-admin.css';
		if ( file_exists( $pdf_css_path ) ) {
			wp_enqueue_style(
				'eop-admin-spa-pdf',
				EOP_PLUGIN_URL . 'assets/css/pdf-admin.css',
				array( 'eop-admin-spa-legacy' ),
				(string) filemtime( $pdf_css_path )
			);
		}
	}

	public static function filter_script_loader_tag( $tag, $handle, $src ) {
		if ( 'eop-admin-spa' !== $handle ) {
			return $tag;
		}

		return '<script type="module" src="' . esc_url( $src ) . '"></script>';
	}

	public static function render_page() {
		include EOP_PLUGIN_DIR . 'templates/admin-app.php';
	}

	public static function get_client_config() {
		return array(
			'rest_root'         => esc_url_raw( rest_url( self::REST_NAMESPACE ) ),
			'rest_nonce'        => wp_create_nonce( 'wp_rest' ),
			'legacy_admin_url'  => self::get_legacy_url(),
			'initial_view'      => EOP_Admin_Page::normalize_view( isset( $_GET['view'] ) ? wp_unslash( $_GET['view'] ) : '' ),
			'documentation_url' => EOP_PLUGIN_URL . 'docs/ARCHITECTURE.md',
		);
	}

	public static function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/bootstrap',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( __CLASS__, 'can_access_admin_app' ),
				'callback'            => array( __CLASS__, 'handle_bootstrap_request' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/settings/(?P<section>[a-z0-9-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
					'callback'            => array( __CLASS__, 'handle_get_settings_request' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
					'callback'            => array( __CLASS__, 'handle_update_settings_request' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/previews/(?P<surface>[a-z0-9-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
				'callback'            => array( __CLASS__, 'handle_preview_request' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/confirmation-documents',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
					'callback'            => array( __CLASS__, 'handle_get_confirmation_documents_request' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
					'callback'            => array( __CLASS__, 'handle_save_confirmation_documents_request' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/views/(?P<view_name>[a-z0-9-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( __CLASS__, 'can_access_admin_app' ),
				'callback'            => array( __CLASS__, 'handle_lazy_view_request' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/pdf-tabs/(?P<pdf_tab>[a-z0-9-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( __CLASS__, 'can_access_admin_app' ),
				'callback'            => array( __CLASS__, 'handle_pdf_tab_request' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/customers/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( __CLASS__, 'can_access_admin_app' ),
				'callback'            => array( __CLASS__, 'handle_customer_search_request' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( __CLASS__, 'can_access_admin_app' ),
				'callback'            => array( __CLASS__, 'handle_products_search_request' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/product-categories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( __CLASS__, 'can_access_admin_app' ),
				'callback'            => array( __CLASS__, 'handle_product_categories_search_request' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/shipping/rates',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( __CLASS__, 'can_access_admin_app' ),
				'callback'            => array( __CLASS__, 'handle_shipping_rates_request' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/orders',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( __CLASS__, 'can_access_admin_app' ),
					'callback'            => array( __CLASS__, 'handle_orders_collection_request' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => array( __CLASS__, 'can_access_admin_app' ),
					'callback'            => array( __CLASS__, 'handle_orders_create_request' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/orders/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( __CLASS__, 'can_access_admin_app' ),
					'callback'            => array( __CLASS__, 'handle_order_detail_request' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'permission_callback' => array( __CLASS__, 'can_access_admin_app' ),
					'callback'            => array( __CLASS__, 'handle_order_update_request' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/orders/(?P<id>\d+)/post-confirmation/stage',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'permission_callback' => array( __CLASS__, 'can_access_admin_app' ),
				'callback'            => array( __CLASS__, 'handle_order_post_confirmation_stage_request' ),
			)
		);
	}

	public static function can_access_admin_app() {
		return current_user_can( 'edit_shop_orders' );
	}

	public static function can_manage_settings() {
		return current_user_can( 'manage_options' );
	}

	public static function handle_bootstrap_request() {
		return rest_ensure_response( self::get_bootstrap_payload() );
	}

	public static function handle_get_settings_request( WP_REST_Request $request ) {
		$section = sanitize_key( (string) $request['section'] );
		$payload = self::get_section_payload( $section );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		return rest_ensure_response( $payload );
	}

	public static function handle_update_settings_request( WP_REST_Request $request ) {
		$section = sanitize_key( (string) $request['section'] );
		$config  = self::get_section_definition( $section );

		if ( is_wp_error( $config ) ) {
			return $config;
		}

		$body   = $request->get_json_params();
		$body   = is_array( $body ) ? $body : array();
		$values = isset( $body['values'] ) && is_array( $body['values'] ) ? $body['values'] : array();

		if ( 'pdf' === $config['source'] ) {
			$existing   = get_option( EOP_PDF_Settings::OPTION_KEY, array() );
			$merged     = array_merge( is_array( $existing ) ? $existing : array(), self::filter_allowed_settings_keys( $values, $config ) );
			$sanitized  = EOP_PDF_Settings::sanitize_settings( $merged );
			update_option( EOP_PDF_Settings::OPTION_KEY, $sanitized );
		} else {
			$existing   = get_option( EOP_Settings::OPTION_KEY, array() );
			$merged     = array_merge( is_array( $existing ) ? $existing : array(), self::filter_allowed_settings_keys( $values, $config ) );
			$sanitized  = EOP_Settings::sanitize_settings( $merged );
			update_option( EOP_Settings::OPTION_KEY, $sanitized );
		}

		return self::handle_get_settings_request( $request );
	}

	public static function handle_order_post_confirmation_stage_request( WP_REST_Request $request ) {
		if ( ! class_exists( 'EOP_Post_Confirmation_Flow' ) || ! method_exists( 'EOP_Post_Confirmation_Flow', 'get_admin_stage_update_payload' ) ) {
			return new WP_Error( 'eop_post_flow_unavailable', __( 'Fluxo complementar indisponivel.', EOP_TEXT_DOMAIN ), array( 'status' => 500 ) );
		}

		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : array();

		$result = EOP_Post_Confirmation_Flow::get_admin_stage_update_payload(
			absint( $request['id'] ),
			$body['stage'] ?? $request->get_param( 'stage' )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	public static function handle_preview_request( WP_REST_Request $request ) {
		$surface  = sanitize_key( (string) $request['surface'] );
		$settings = EOP_Settings::get_all();

		switch ( $surface ) {
			case 'new-order':
				return rest_ensure_response(
					array(
						'surface' => $surface,
						'mode'    => 'iframe',
						'url'     => EOP_Admin_Page::get_preview_frame_url( 'new-order' ),
						'source'  => 'public-shortcode',
					)
				);

			case 'orders-list':
				return rest_ensure_response(
					array(
						'surface' => $surface,
						'mode'    => 'iframe',
						'url'     => EOP_Admin_Page::get_preview_frame_url( 'orders' ),
						'source'  => 'public-shortcode',
					)
				);

			case 'proposal':
				if ( class_exists( 'EOP_Public_Proposal' ) && method_exists( 'EOP_Public_Proposal', 'render_admin_preview_card' ) ) {
					return rest_ensure_response(
						array(
							'surface' => $surface,
							'mode'    => 'html',
							'html'    => EOP_Public_Proposal::render_admin_preview_card( $settings ),
							'source'  => 'public-proposal-renderer',
						)
					);
				}
				break;

			case 'confirmation-contract':
				if ( class_exists( 'EOP_Post_Confirmation_Flow' ) && method_exists( 'EOP_Post_Confirmation_Flow', 'render_admin_contract_preview_markup' ) ) {
					return rest_ensure_response(
						array(
							'surface' => $surface,
							'mode'    => 'html',
							'html'    => EOP_Post_Confirmation_Flow::render_admin_contract_preview_markup( $settings ),
							'source'  => 'post-confirmation-contract-renderer',
						)
					);
				}
				break;

			case 'confirmation-upload-products':
				if ( class_exists( 'EOP_Post_Confirmation_Flow' ) && method_exists( 'EOP_Post_Confirmation_Flow', 'render_admin_upload_products_preview_markup' ) ) {
					return rest_ensure_response(
						array(
							'surface' => $surface,
							'mode'    => 'html',
							'html'    => EOP_Post_Confirmation_Flow::render_admin_upload_products_preview_markup( $settings ),
							'source'  => 'post-confirmation-upload-renderer',
						)
					);
				}
				break;

				case 'pdf-order':
				case 'pdf-proposal':
					if ( class_exists( 'EOP_Document_Manager' ) && method_exists( 'EOP_Document_Manager', 'get_preview_html' ) ) {
						$document_type = 'pdf-proposal' === $surface ? 'proposal' : 'order';
						$preview_order = EOP_Document_Manager::get_preview_order();

						if ( $preview_order instanceof WC_Order ) {
							return rest_ensure_response(
								array(
									'surface' => $surface,
									'mode'    => 'html',
									'html'    => '<div class="eop-pdf-preview-shell">' . EOP_Document_Manager::get_preview_html( $preview_order, $document_type, false ) . '</div>',
									'source'  => 'pdf-document-renderer',
								)
							);
						}

						return rest_ensure_response(
							array(
								'surface' => $surface,
								'mode'    => 'html',
								'html'    => '<div class="eop-react-preview-empty"><p>' . esc_html__( 'Crie um pedido para visualizar o PDF.', EOP_TEXT_DOMAIN ) . '</p></div>',
								'source'  => 'pdf-document-renderer',
							)
						);
					}
					break;
		}

		return new WP_Error(
			'eop_admin_spa_preview_unavailable',
			__( 'Preview indisponivel para esta superficie.', EOP_TEXT_DOMAIN ),
			array( 'status' => 404 )
		);
	}

	public static function handle_get_confirmation_documents_request() {
		return rest_ensure_response( self::get_confirmation_documents_payload() );
	}

	public static function handle_save_confirmation_documents_request( WP_REST_Request $request ) {
		$body     = $request->get_json_params();
		$body     = is_array( $body ) ? $body : array();
		$incoming = isset( $body['documents'] ) && is_array( $body['documents'] ) ? $body['documents'] : array();

		$normalized = array();

		foreach ( $incoming as $document ) {
			if ( ! is_array( $document ) ) {
				continue;
			}

			$normalized[] = array(
				'key'           => (string) ( $document['key'] ?? '' ),
				'title'         => (string) ( $document['title'] ?? '' ),
				'description'   => (string) ( $document['description'] ?? '' ),
				'source_type'   => (string) ( $document['source_type'] ?? 'editor' ),
				'body'          => (string) ( $document['body'] ?? '' ),
				'attachment_id' => absint( $document['attachment_id'] ?? 0 ),
				'button_label'  => (string) ( $document['button_label'] ?? '' ),
				'view_label'    => (string) ( $document['view_label'] ?? '' ),
			);
		}

		// Reaproveita o sanitizador oficial (mesmo caminho do save legado por options.php).
		$sanitized = EOP_Settings::sanitize_settings( array( 'post_confirmation_signature_documents' => $normalized ) );
		update_option( EOP_Settings::OPTION_KEY, $sanitized );

		return self::handle_get_confirmation_documents_request();
	}

	private static function get_confirmation_documents_payload() {
		if ( method_exists( 'EOP_Settings', 'get_post_confirmation_contract_documents' ) ) {
			$documents = EOP_Settings::get_post_confirmation_contract_documents();
		} elseif ( method_exists( 'EOP_Settings', 'get_post_confirmation_signature_documents' ) ) {
			$documents = EOP_Settings::get_post_confirmation_signature_documents();
		} else {
			$documents = array();
		}

		$items = array();

		foreach ( (array) $documents as $document ) {
			$attachment_id   = absint( $document['attachment_id'] ?? 0 );
			$attachment_name = '';
			$attachment_url  = '';

			if ( $attachment_id > 0 ) {
				$file            = get_attached_file( $attachment_id );
				$attachment_name = $file ? wp_basename( $file ) : (string) get_the_title( $attachment_id );
				$attachment_url  = (string) wp_get_attachment_url( $attachment_id );
			}

			$items[] = array(
				'key'             => (string) ( $document['key'] ?? '' ),
				'title'           => (string) ( $document['title'] ?? '' ),
				'description'     => (string) ( $document['description'] ?? '' ),
				'source_type'     => in_array( $document['source_type'] ?? 'editor', array( 'editor', 'attachment' ), true ) ? (string) $document['source_type'] : 'editor',
				'body'            => (string) ( $document['body'] ?? '' ),
				'attachment_id'   => $attachment_id,
				'attachment_name' => $attachment_name,
				'attachment_url'  => $attachment_url,
				'button_label'    => (string) ( $document['button_label'] ?? '' ),
				'view_label'      => (string) ( $document['view_label'] ?? '' ),
			);
		}

		$tokens = ( class_exists( 'EOP_Post_Confirmation_Flow' ) && method_exists( 'EOP_Post_Confirmation_Flow', 'get_contract_placeholder_tokens' ) )
			? (array) EOP_Post_Confirmation_Flow::get_contract_placeholder_tokens()
			: array();

		return array(
			'documents'         => $items,
			'placeholderTokens' => array_values( $tokens ),
		);
	}

	public static function handle_lazy_view_request( WP_REST_Request $request ) {
		// Alguns renderers legados (ex.: portabilidade) podem emitir saida direta
		// durante o request REST, corrompendo o JSON. Bufferiza e descarta qualquer
		// vazamento; o HTML util ja vem dentro do payload['html'].
		ob_start();
		$payload = EOP_Admin_Page::get_lazy_view_payload( (string) $request->get_param( 'view_name' ), $request );
		ob_end_clean();

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		return rest_ensure_response( $payload );
	}

	public static function handle_pdf_tab_request( WP_REST_Request $request ) {
		if ( ! class_exists( 'EOP_PDF_Admin_Page' ) || ! method_exists( 'EOP_PDF_Admin_Page', 'get_pdf_tab_payload' ) ) {
			return new WP_Error(
				'eop_pdf_tab_unavailable',
				__( 'Aba PDF indisponivel.', EOP_TEXT_DOMAIN ),
				array( 'status' => 404 )
			);
		}

		$payload = EOP_PDF_Admin_Page::get_pdf_tab_payload( $request );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		return rest_ensure_response( $payload );
	}

	public static function handle_customer_search_request( WP_REST_Request $request ) {
		$result = EOP_Ajax_Handlers::find_customer_by_document( (string) $request->get_param( 'document' ) );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response( $result );
	}

	public static function handle_products_search_request( WP_REST_Request $request ) {
		$term = sanitize_text_field( (string) $request->get_param( 'term' ) );

		return rest_ensure_response( EOP_Ajax_Handlers::search_products_payload( $term ) );
	}

	public static function handle_product_categories_search_request( WP_REST_Request $request ) {
		$term = sanitize_text_field( (string) $request->get_param( 'term' ) );

		return rest_ensure_response( EOP_Ajax_Handlers::search_product_categories_payload( $term ) );
	}

	public static function handle_shipping_rates_request( WP_REST_Request $request ) {
		$body    = $request->get_json_params();
		$body    = is_array( $body ) ? $body : array();
		$items   = isset( $body['items'] ) && is_array( $body['items'] ) ? $body['items'] : array();
		$address = isset( $body['address'] ) && is_array( $body['address'] ) ? $body['address'] : array();
		$result  = EOP_Shipping_Calculator::calculate_rates_from_payload( $items, $address );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response( $result );
	}

	public static function handle_orders_collection_request( WP_REST_Request $request ) {
		$page     = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
		$per_page = max( 1, min( 50, absint( $request->get_param( 'per_page' ) ?: 12 ) ) );
		$search   = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$status   = sanitize_key( (string) $request->get_param( 'status' ) );
		$flow     = sanitize_key( (string) $request->get_param( 'flow' ) );

		$result = EOP_Orders_Page::get_orders(
			array(
				'limit'                  => $per_page,
				'paged'                  => $page,
				'search'                 => $search,
				'status'                 => '' !== $status ? $status : 'any',
				'post_confirmation_flow' => '' !== $flow ? $flow : 'any',
			)
		);

		$orders = array();

		foreach ( (array) $result->orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$orders[] = self::prepare_order_summary( $order );
		}

		return rest_ensure_response(
			array(
				'items'      => $orders,
				'orders'     => $orders,
				'pagination' => array(
					'page'        => $page,
					'per_page'    => $per_page,
					'total_items' => (int) $result->total,
					'total_pages' => max( 1, (int) $result->max_num_pages ),
				),
				'viewer'     => array(
					'is_admin' => ! EOP_Role::is_vendedor(),
				),
			)
		);
	}

	public static function handle_orders_create_request( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : array();
		$data = isset( $body['order'] ) && is_array( $body['order'] ) ? $body['order'] : $body;

		$result = EOP_Ajax_Handlers::create_order_from_payload( $data );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		$order = wc_get_order( absint( $result['order_id'] ?? 0 ) );

		return rest_ensure_response(
			array(
				'result' => $result,
				'order'  => $order instanceof WC_Order ? self::prepare_order_summary( $order ) : null,
			)
		);
	}

	public static function handle_order_detail_request( WP_REST_Request $request ) {
		$order = wc_get_order( absint( $request['id'] ) );

		if ( ! $order instanceof WC_Order ) {
			return new WP_Error(
				'eop_admin_spa_order_not_found',
				__( 'Pedido nao encontrado.', EOP_TEXT_DOMAIN ),
				array( 'status' => 404 )
			);
		}

		if ( ! EOP_Orders_Page::current_user_can_access_order( $order ) ) {
			return new WP_Error(
				'eop_admin_spa_order_forbidden',
				__( 'Sem permissao para este pedido.', EOP_TEXT_DOMAIN ),
				array( 'status' => 403 )
			);
		}

		$payload = EOP_Orders_Page::get_order_editor_payload( $order );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		return rest_ensure_response(
			array(
				'order' => array_merge( self::prepare_order_summary( $order ), $payload ),
			)
		);
	}

	public static function handle_order_update_request( WP_REST_Request $request ) {
		$order = wc_get_order( absint( $request['id'] ) );

		if ( ! $order instanceof WC_Order ) {
			return new WP_Error(
				'eop_admin_spa_order_not_found',
				__( 'Pedido nao encontrado.', EOP_TEXT_DOMAIN ),
				array( 'status' => 404 )
			);
		}

		if ( ! EOP_Orders_Page::current_user_can_access_order( $order ) ) {
			return new WP_Error(
				'eop_admin_spa_order_forbidden',
				__( 'Sem permissao para este pedido.', EOP_TEXT_DOMAIN ),
				array( 'status' => 403 )
			);
		}

		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : array();
		$data = isset( $body['order'] ) && is_array( $body['order'] ) ? $body['order'] : $body;

		$data['order_id'] = $order->get_id();

		$result = EOP_Orders_Page::update_order_from_payload( $order, $data );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		$refreshed_order = wc_get_order( $order->get_id() );
		$payload         = EOP_Orders_Page::get_order_editor_payload( $refreshed_order );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		return rest_ensure_response(
			array(
				'message' => (string) $result['message'],
				'order'   => array_merge( self::prepare_order_summary( $refreshed_order ), $payload ),
			)
		);
	}

	public static function get_bootstrap_payload() {
		$current_user = wp_get_current_user();
		$current_view = EOP_Admin_Page::normalize_view( isset( $_GET['view'] ) ? wp_unslash( $_GET['view'] ) : '' );
		$settings     = class_exists( 'EOP_Settings' ) ? EOP_Settings::get_all() : array();
		$font_family  = method_exists( 'EOP_Settings', 'get_font_css_family' )
			? EOP_Settings::get_font_css_family( $settings['font_family'] ?? 'Montserrat:400,700' )
			: "'Montserrat', sans-serif";

		return array(
			'user'       => array(
				'id'          => (int) $current_user->ID,
				'displayName' => (string) $current_user->display_name,
				'email'       => (string) $current_user->user_email,
			),
			'license'    => array(
				'valid' => class_exists( 'EOP_License_Manager' ) ? (bool) EOP_License_Manager::is_valid() : false,
			),
			'flags'      => array(
				'newAdminEnabled'      => self::is_enabled(),
				'hasBuiltBundle'       => self::has_built_assets(),
				'canManageSettings'    => current_user_can( 'manage_options' ),
				'canEditShopOrders'    => current_user_can( 'edit_shop_orders' ),
				'legacyForced'         => self::is_legacy_forced(),
			),
			'branding'   => array(
				'panelTitle'    => (string) ( $settings['panel_title'] ?? __( 'Pedido Expresso', EOP_TEXT_DOMAIN ) ),
				'panelSubtitle' => (string) ( $settings['panel_subtitle'] ?? __( 'Monte o pedido, gere a proposta e compartilhe com o cliente.', EOP_TEXT_DOMAIN ) ),
				'logoUrl'       => esc_url_raw( EOP_PLUGIN_URL . 'assets/images/logo-aireset.png' ),
				'primaryColor'  => sanitize_hex_color( $settings['primary_color'] ?? '#00034b' ) ?: '#00034b',
				'surfaceColor'  => sanitize_hex_color( $settings['surface_color'] ?? '#ffffff' ) ?: '#ffffff',
				'borderColor'   => sanitize_hex_color( $settings['border_color'] ?? '#dbe3f0' ) ?: '#dbe3f0',
				'borderRadius'  => absint( $settings['border_radius'] ?? 18 ),
				'fontFamily'    => (string) $font_family,
			),
			'routes'      => array(
				'restRoot'       => esc_url_raw( rest_url( self::REST_NAMESPACE ) ),
				'legacyRoot'     => EOP_Admin_Page::get_view_url(),
				'viewUrls'       => EOP_Admin_Page::get_view_urls(),
				'legacyViewUrls' => self::get_legacy_view_urls(),
			),
			'views'       => EOP_Admin_Page::get_available_views(),
			'initialView' => $current_view,
			'sections'    => array_keys( self::get_section_definitions() ),
			'docs'        => array(
				'architecture' => EOP_PLUGIN_URL . 'docs/ARCHITECTURE.md',
				'roadmap'      => EOP_PLUGIN_URL . 'docs/ROADMAP.md',
				'operations'   => EOP_PLUGIN_URL . 'docs/OPERATIONS.md',
				'legal'        => EOP_PLUGIN_URL . 'docs/LEGAL_AND_AI_POLICY.md',
			),
		);
	}

	public static function get_enable_preview_url() {
		return EOP_Admin_Page::get_view_url( EOP_Admin_Page::normalize_view( isset( $_GET['view'] ) ? wp_unslash( $_GET['view'] ) : '' ) );
	}

	public static function get_legacy_url() {
		return add_query_arg(
			array(
				'eop_admin_legacy' => '1',
			),
			EOP_Admin_Page::get_view_url( EOP_Admin_Page::normalize_view( isset( $_GET['view'] ) ? wp_unslash( $_GET['view'] ) : '' ) )
		);
	}

	private static function get_legacy_view_urls() {
		$urls = array();

		foreach ( EOP_Admin_Page::get_available_views() as $view ) {
			$urls[ $view ] = add_query_arg(
				array(
					'eop_admin_legacy' => '1',
				),
				EOP_Admin_Page::get_view_url( $view )
			);
		}

		return $urls;
	}

	private static function get_manifest() {
		if ( null !== self::$manifest ) {
			return self::$manifest;
		}

		$path = EOP_PLUGIN_DIR . 'assets/admin-spa/dist/.vite/manifest.json';

		if ( ! file_exists( $path ) ) {
			self::$manifest = false;
			return self::$manifest;
		}

		$decoded = json_decode( (string) file_get_contents( $path ), true );
		self::$manifest = is_array( $decoded ) ? $decoded : false;

		return self::$manifest;
	}

	private static function get_entry_manifest() {
		$manifest = self::get_manifest();

		if ( ! is_array( $manifest ) ) {
			return array();
		}

		if ( isset( $manifest['assets/admin-spa/src/main.tsx'] ) ) {
			return $manifest['assets/admin-spa/src/main.tsx'];
		}

		if ( isset( $manifest['main.tsx'] ) ) {
			return $manifest['main.tsx'];
		}

		return (array) reset( $manifest );
	}

	private static function get_section_definitions() {
		if ( null !== self::$section_definitions ) {
			return self::$section_definitions;
		}

		self::$section_definitions = array(
			'store' => array(
				'source' => 'pdf',
				'exact'  => array(
					'shop_logo_url',
					'shop_logo_height',
					'shop_name',
					'shop_email',
					'shop_postcode',
					'shop_address_line_1',
					'shop_address_line_2',
					'shop_city',
					'shop_state',
					'shop_country',
					'shop_phone',
					'shop_vat_number',
					'shop_chamber_of_commerce',
					'shop_extra_1',
					'shop_extra_2',
					'shop_extra_3',
					'shop_footer',
				),
			),
			'general' => array(
				'source' => 'settings',
				'exact'  => array( 'flow_mode', 'discount_mode', 'enable_checkout_confirmation', 'service_products', 'service_product_categories', 'order_page_id', 'proposal_page_id', 'enable_post_confirmation_flow' ),
			),
			'proposal' => array(
				'source' => 'settings',
				'prefix' => array( 'proposal_', 'customer_experience_' ),
			),
			'new-order' => array(
				'source' => 'settings',
				'exact'  => array( 'panel_title', 'panel_subtitle', 'font_family', 'primary_color', 'surface_color', 'border_color', 'border_radius' ),
				'prefix' => array( 'new_order_' ),
			),
			'orders-list' => array(
				'source' => 'settings',
				'prefix' => array( 'orders_list_' ),
			),
			'confirmation-general' => array(
				'source' => 'settings',
				'exact'  => array( 'enable_post_confirmation_flow', 'post_confirmation_documents_title', 'post_confirmation_documents_description', 'post_confirmation_documents_button_label', 'post_confirmation_require_attachment' ),
				'prefix' => array( 'post_confirmation_stage_' ),
			),
			'confirmation-contract' => array(
				'source' => 'settings',
				'prefix' => array( 'post_confirmation_contract_', 'post_confirmation_signature_documents' ),
			),
			'confirmation-upload-products' => array(
				'source' => 'settings',
				'prefix' => array( 'post_confirmation_upload_', 'post_confirmation_products_', 'post_confirmation_final_' ),
				'exact'  => array( 'post_confirmation_locked_products' ),
			),
			'pdf-display' => array(
				'source' => 'pdf',
				'exact'  => array( 'display_mode', 'paper_size', 'template_name', 'ink_saving_mode', 'test_mode', 'font_subsetting', 'extended_currency_symbol' ),
			),
			'pdf-order' => array(
				'source' => 'pdf',
				'exact'  => array( 'order_enabled', 'order_attach_email', 'order_mark_printed', 'order_show_shipping', 'order_show_billing', 'order_show_email', 'order_show_phone', 'order_show_notes', 'order_myaccount_download', 'order_show_total_subtotal', 'order_show_total_shipping', 'order_show_total_discount', 'order_show_total_total', 'order_prefix', 'order_suffix', 'order_padding', 'order_next_number', 'order_reset_yearly' ),
			),
			'pdf-order-columns' => array(
				'source' => 'pdf',
				'exact'  => array( 'order_show_item_index', 'order_show_sku', 'order_show_quantity', 'order_show_unit_price', 'order_show_discount', 'order_show_discounted_unit_price', 'order_show_line_total', 'order_quantity_position', 'order_unit_price_position', 'order_discount_position', 'order_discounted_unit_price_position', 'order_line_total_position' ),
			),
			'pdf-order-texts' => array(
				'source' => 'pdf',
				'exact'  => array( 'order_item_index_label', 'order_product_label', 'order_quantity_label', 'order_unit_price_label', 'order_discount_label', 'order_discount_display_mode', 'order_discount_suffix', 'order_discounted_unit_price_label', 'order_discounted_unit_price_suffix', 'order_line_total_label' ),
			),
			'pdf-order-style' => array(
				'source' => 'pdf',
				'exact'  => array( 'order_header_background_color', 'order_header_text_color', 'order_body_text_color', 'order_muted_text_color', 'order_border_color', 'order_title_font_size', 'order_meta_font_size', 'order_table_header_font_size', 'order_table_body_font_size', 'order_table_body_line_height', 'order_totals_font_size', 'order_note_font_size' ),
			),
			'pdf-proposal' => array(
				'source' => 'pdf',
				'exact'  => array( 'proposal_enabled', 'proposal_attach_email', 'proposal_mark_printed', 'proposal_show_shipping', 'proposal_show_billing', 'proposal_show_email', 'proposal_show_phone', 'proposal_show_notes', 'proposal_public_pdf', 'proposal_show_total_subtotal', 'proposal_show_total_shipping', 'proposal_show_total_discount', 'proposal_show_total_total', 'proposal_prefix', 'proposal_suffix', 'proposal_padding', 'proposal_next_number', 'proposal_reset_yearly' ),
			),
			'pdf-proposal-columns' => array(
				'source' => 'pdf',
				'exact'  => array( 'proposal_show_item_index', 'proposal_show_sku', 'proposal_show_quantity', 'proposal_show_unit_price', 'proposal_show_discount', 'proposal_show_discounted_unit_price', 'proposal_show_line_total', 'proposal_quantity_position', 'proposal_unit_price_position', 'proposal_discount_position', 'proposal_discounted_unit_price_position', 'proposal_line_total_position' ),
			),
			'pdf-proposal-texts' => array(
				'source' => 'pdf',
				'exact'  => array( 'proposal_item_index_label', 'proposal_product_label', 'proposal_quantity_label', 'proposal_unit_price_label', 'proposal_discount_label', 'proposal_discount_display_mode', 'proposal_discount_suffix', 'proposal_discounted_unit_price_label', 'proposal_discounted_unit_price_suffix', 'proposal_line_total_label' ),
			),
			'pdf-proposal-style' => array(
				'source' => 'pdf',
				'exact'  => array( 'proposal_header_background_color', 'proposal_header_text_color', 'proposal_body_text_color', 'proposal_muted_text_color', 'proposal_border_color', 'proposal_title_font_size', 'proposal_meta_font_size', 'proposal_table_header_font_size', 'proposal_table_body_font_size', 'proposal_table_body_line_height', 'proposal_totals_font_size', 'proposal_note_font_size' ),
			),
			'pdf-edocuments' => array(
				'source' => 'pdf',
				'exact'  => array( 'edoc_enabled', 'edoc_format', 'edoc_embed_pdf', 'edoc_preview_xml', 'edoc_logging', 'edoc_supplier_scheme', 'edoc_customer_scheme', 'edoc_network_endpoint', 'edoc_network_eas' ),
			),
			'pdf-advanced' => array(
				'source' => 'pdf',
				'exact'  => array( 'advanced_link_access', 'advanced_pretty_links', 'advanced_html_output', 'advanced_debug', 'advanced_order_note_logs', 'advanced_auto_cleanup', 'advanced_danger_zone' ),
			),
		);

		return self::$section_definitions;
	}

	private static function get_section_definition( $section ) {
		$definitions = self::get_section_definitions();

		if ( empty( $definitions[ $section ] ) ) {
			return new WP_Error(
				'eop_admin_spa_unknown_section',
				__( 'Secao de configuracao desconhecida.', EOP_TEXT_DOMAIN ),
				array( 'status' => 404 )
			);
		}

		$config = $definitions[ $section ];

		// Secoes da ponte: as chaves editaveis sao exatamente as do schema do
		// legado. Garante que chaves compartilhadas SEM o prefixo da secao
		// (ex.: border_color, border_radius) sejam permitidas na leitura e no
		// salvamento, evitando campos sumindo em relacao ao admin legado.
		$bridge_sections = array( 'new-order', 'orders-list', 'proposal', 'confirmation-contract', 'confirmation-upload-products' );

		if ( in_array( $section, $bridge_sections, true ) && method_exists( 'EOP_Settings', 'get_spa_field_schema' ) ) {
			$schema_keys = array();

			foreach ( (array) EOP_Settings::get_spa_field_schema( $section ) as $field ) {
				if ( ! empty( $field['key'] ) ) {
					$schema_keys[] = (string) $field['key'];
				}
			}

			$existing_exact  = isset( $config['exact'] ) && is_array( $config['exact'] ) ? $config['exact'] : array();
			$config['exact'] = array_values( array_unique( array_merge( $existing_exact, $schema_keys ) ) );
		}

		return $config;
	}

	private static function get_section_payload( $section ) {
		$config = self::get_section_definition( $section );

		if ( is_wp_error( $config ) ) {
			return $config;
		}

		$all_values = 'pdf' === $config['source'] ? EOP_PDF_Settings::get_all() : EOP_Settings::get_all();
		$values     = self::filter_allowed_settings_keys( $all_values, $config );

		return array(
			'section' => $section,
			'source'  => $config['source'],
			'values'  => $values,
			'fields'  => self::get_section_fields( $section, $values ),
			'meta'    => self::get_section_meta( $section ),
		);
	}

	/**
	 * Metadados de apresentacao da secao (cabecalho, intro e label do botao),
	 * para o SPA reproduzir o visual do admin legado por dominio.
	 */
	private static function get_section_meta( $section ) {
		$defaults = array(
			'title'          => '',
			'description'    => '',
			'intro'          => '',
			'introLinkLabel' => '',
			'introLinkUrl'   => '',
			'saveLabel'      => __( 'Salvar configuracoes', EOP_TEXT_DOMAIN ),
		);

		switch ( $section ) {
			case 'store':
				return array_merge(
					$defaults,
					array(
						'title'          => __( 'Informacoes sobre a loja', EOP_TEXT_DOMAIN ),
						'description'    => __( 'Centralize logo, dados institucionais e informacoes exibidas nos documentos do Pedido Expresso.', EOP_TEXT_DOMAIN ),
						'intro'          => __( 'Nome da loja, endereco, telefone, e-mail e documento compartilham a mesma base do Aireset Default e do WooCommerce. Alterando aqui, o outro plugin e as configuracoes da loja tambem refletem os dados. Voce tambem pode conferir em', EOP_TEXT_DOMAIN ),
						'introLinkLabel' => __( 'WooCommerce > Configuracoes > Geral', EOP_TEXT_DOMAIN ),
						'introLinkUrl'   => admin_url( 'admin.php?page=wc-settings&tab=general' ),
						'saveLabel'      => __( 'Salvar informacoes da loja', EOP_TEXT_DOMAIN ),
					)
				);

			case 'general':
				return array_merge(
					$defaults,
					array(
						'title'       => __( 'Configuracoes gerais', EOP_TEXT_DOMAIN ),
						'description' => __( 'Defina o fluxo de venda, descontos, paginas e o fluxo complementar do Pedido Expresso.', EOP_TEXT_DOMAIN ),
					)
				);

			case 'proposal':
				return array_merge(
					$defaults,
					array(
						'title'       => __( 'Visual da proposta do cliente', EOP_TEXT_DOMAIN ),
						'description' => __( 'Personalize a aparencia da proposta publica enviada ao cliente.', EOP_TEXT_DOMAIN ),
					)
				);

			case 'new-order':
				return array_merge(
					$defaults,
					array(
						'title'       => __( 'Visual do formulario de pedido', EOP_TEXT_DOMAIN ),
						'description' => __( 'Ajuste cores, fonte e textos do formulario de novo pedido.', EOP_TEXT_DOMAIN ),
					)
				);

			case 'orders-list':
				return array_merge(
					$defaults,
					array(
						'title'       => __( 'Visual da listagem de pedidos', EOP_TEXT_DOMAIN ),
						'description' => __( 'Configure a aparencia da lista de pedidos.', EOP_TEXT_DOMAIN ),
					)
				);

			case 'confirmation-general':
				return array_merge(
					$defaults,
					array(
						'title'       => __( 'Fluxo de confirmacao', EOP_TEXT_DOMAIN ),
						'description' => __( 'Defina o comportamento geral da jornada de confirmacao apos a proposta.', EOP_TEXT_DOMAIN ),
					)
				);

			case 'confirmation-contract':
				return array_merge(
					$defaults,
					array(
						'title'       => __( 'Documentos e contrato', EOP_TEXT_DOMAIN ),
						'description' => __( 'Configure os documentos e o contrato exibidos na etapa de confirmacao.', EOP_TEXT_DOMAIN ),
					)
				);

			case 'confirmation-upload-products':
				return array_merge(
					$defaults,
					array(
						'title'       => __( 'Upload e produtos', EOP_TEXT_DOMAIN ),
						'description' => __( 'Configure as etapas de envio de arquivos e personalizacao de produtos.', EOP_TEXT_DOMAIN ),
					)
				);

			case 'pdf-display':
				return array_merge(
					$defaults,
					array(
						'title'       => __( 'Visualizacao do PDF', EOP_TEXT_DOMAIN ),
						'description' => __( 'Como o documento e aberto, tamanho do papel, modelo e ajustes de renderizacao.', EOP_TEXT_DOMAIN ),
					)
				);

			case 'pdf-order':
				return array_merge(
					$defaults,
					array(
						'title'       => __( 'Documento do pedido', EOP_TEXT_DOMAIN ),
						'description' => __( 'Ativacao, dados exibidos, totais e numeracao do PDF do pedido.', EOP_TEXT_DOMAIN ),
					)
				);

			case 'pdf-order-columns':
				return array_merge(
					$defaults,
					array(
						'title'       => __( 'Colunas do pedido', EOP_TEXT_DOMAIN ),
						'description' => __( 'Quais colunas aparecem na tabela de itens do pedido e em que ordem.', EOP_TEXT_DOMAIN ),
					)
				);

			case 'pdf-order-texts':
				return array_merge(
					$defaults,
					array(
						'title'       => __( 'Textos do pedido', EOP_TEXT_DOMAIN ),
						'description' => __( 'Rotulos e sufixos personalizados das colunas da tabela do pedido.', EOP_TEXT_DOMAIN ),
					)
				);

			case 'pdf-order-style':
				return array_merge(
					$defaults,
					array(
						'title'       => __( 'Estilo do pedido', EOP_TEXT_DOMAIN ),
						'description' => __( 'Cores e tipografia do PDF do pedido.', EOP_TEXT_DOMAIN ),
					)
				);

			case 'pdf-proposal':
				return array_merge(
					$defaults,
					array(
						'title'       => __( 'Documento da proposta', EOP_TEXT_DOMAIN ),
						'description' => __( 'Geracao, dados exibidos, acesso publico, totais e numeracao da proposta.', EOP_TEXT_DOMAIN ),
					)
				);

			case 'pdf-proposal-columns':
				return array_merge(
					$defaults,
					array(
						'title'       => __( 'Colunas da proposta', EOP_TEXT_DOMAIN ),
						'description' => __( 'Quais colunas aparecem na tabela da proposta e em que ordem.', EOP_TEXT_DOMAIN ),
					)
				);

			case 'pdf-proposal-texts':
				return array_merge(
					$defaults,
					array(
						'title'       => __( 'Textos da proposta', EOP_TEXT_DOMAIN ),
						'description' => __( 'Rotulos das colunas e formato do desconto na tabela da proposta.', EOP_TEXT_DOMAIN ),
					)
				);

			case 'pdf-proposal-style':
				return array_merge(
					$defaults,
					array(
						'title'       => __( 'Estilo da proposta', EOP_TEXT_DOMAIN ),
						'description' => __( 'Cores e tipografia do documento de proposta.', EOP_TEXT_DOMAIN ),
					)
				);

			case 'pdf-edocuments':
				return array_merge(
					$defaults,
					array(
						'title'       => __( 'Documentos eletronicos', EOP_TEXT_DOMAIN ),
						'description' => __( 'Exportacao XML experimental, formato, identificadores e rede Peppol.', EOP_TEXT_DOMAIN ),
					)
				);

			case 'pdf-advanced':
				return array_merge(
					$defaults,
					array(
						'title'       => __( 'Avancado', EOP_TEXT_DOMAIN ),
						'description' => __( 'Acesso ao link, saida, logs de diagnostico e ferramentas de manutencao.', EOP_TEXT_DOMAIN ),
					)
				);
		}

		return $defaults;
	}

	private static function get_section_fields( $section, $values = array() ) {
		switch ( $section ) {
			case 'confirmation-general':
				return array(
					array(
						'key'   => 'enable_post_confirmation_flow',
						'type'  => 'toggle',
						'label' => __( 'Ativar fluxo complementar', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Liga a jornada adicional exibida depois da confirmacao da proposta.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'post_confirmation_documents_title',
						'type'  => 'text',
						'label' => __( 'Titulo da etapa de documentos', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'post_confirmation_documents_description',
						'type'  => 'textarea',
						'label' => __( 'Descricao da etapa de documentos', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'post_confirmation_documents_button_label',
						'type'  => 'text',
						'label' => __( 'Texto do botao de documentos', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'post_confirmation_require_attachment',
						'type'  => 'toggle',
						'label' => __( 'Exigir anexo do cliente', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Obriga o cliente a enviar um arquivo para concluir a etapa.', EOP_TEXT_DOMAIN ),
					),
				);

			case 'new-order':
			case 'orders-list':
			case 'proposal':
			case 'confirmation-contract':
			case 'confirmation-upload-products':
				if ( method_exists( 'EOP_Settings', 'get_spa_field_schema' ) ) {
					$schema       = EOP_Settings::get_spa_field_schema( $section );
					$allowed_keys = array_keys( is_array( $values ) ? $values : array() );

					// So expoe campos cujas chaves pertencem a secao (garante que todo
					// campo renderizado e de fato salvavel pela allow-list do POST).
					$schema = array_values(
						array_filter(
							$schema,
							function ( $field ) use ( $allowed_keys ) {
								return in_array( $field['key'], $allowed_keys, true );
							}
						)
					);

					// Produtos bloqueados: multiselect com busca (apenas na tela de upload/produtos).
					if ( 'confirmation-upload-products' === $section && in_array( 'post_confirmation_locked_products', $allowed_keys, true ) ) {
						$schema[] = array(
							'key'          => 'post_confirmation_locked_products',
							'type'         => 'multiselect',
							'group'        => __( 'Produtos bloqueados', EOP_TEXT_DOMAIN ),
							'searchSource' => 'products',
							'minChars'     => 3,
							'selected'     => self::format_selector_options( EOP_Settings::get_post_confirmation_locked_product_selector_state() ),
							'label'        => __( 'Produtos bloqueados', EOP_TEXT_DOMAIN ),
							'help'         => __( 'Produtos cujo nome nao pode ser alterado pelo cliente na etapa final de personalizacao.', EOP_TEXT_DOMAIN ),
						);
					}

					if ( ! empty( $schema ) ) {
						return $schema;
					}
				}
				break;

			case 'store':
				return array(
					array(
						'key'   => 'shop_logo_url',
						'type'  => 'media',
						'label' => __( 'Logo/Cabecalho da loja', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'shop_logo_height',
						'type'  => 'text',
						'label' => __( 'Altura do logo', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'shop_name',
						'type'  => 'text',
						'label' => __( 'Nome da loja', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'shop_email',
						'type'  => 'text',
						'label' => __( 'E-mail da loja', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'shop_postcode',
						'type'  => 'text',
						'label' => __( 'Endereco (CEP)', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'shop_address_line_1',
						'type'  => 'text',
						'label' => __( 'Endereco linha 1', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'shop_address_line_2',
						'type'  => 'text',
						'label' => __( 'Endereco linha 2', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'shop_city',
						'type'  => 'text',
						'label' => __( 'Cidade', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'shop_state',
						'type'  => 'text',
						'label' => __( 'Estado (UF)', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'shop_country',
						'type'  => 'text',
						'label' => __( 'Pais (ISO2)', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'shop_phone',
						'type'  => 'text',
						'label' => __( 'Telefone da loja', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'shop_vat_number',
						'type'  => 'text',
						'label' => __( 'CNPJ / Documento', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'shop_chamber_of_commerce',
						'type'  => 'text',
						'label' => __( 'Camara de comercio / registro', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'shop_extra_1',
						'type'  => 'text',
						'label' => __( 'Campo extra 1', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'shop_extra_2',
						'type'  => 'text',
						'label' => __( 'Campo extra 2', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'shop_extra_3',
						'type'  => 'text',
						'label' => __( 'Campo extra 3', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'shop_footer',
						'type'  => 'textarea',
						'label' => __( 'Rodape / informacoes adicionais', EOP_TEXT_DOMAIN ),
					),
				);

			case 'general':
				$page_options = array(
					array(
						'value' => '0',
						'label' => __( 'Selecione uma pagina', EOP_TEXT_DOMAIN ),
					),
				);

				foreach ( get_pages() as $page ) {
					if ( ! $page instanceof WP_Post ) {
						continue;
					}

					$page_options[] = array(
						'value' => (string) $page->ID,
						'label' => (string) $page->post_title,
					);
				}

				$flow_group = __( 'Fluxo', EOP_TEXT_DOMAIN );

				$service_products_selected   = self::format_selector_options(
					EOP_Settings::get_service_product_selector_state()
				);
				$service_categories_selected = self::format_selector_options(
					EOP_Settings::get_service_product_category_selector_state()
				);

				return array(
					array(
						'key'     => 'flow_mode',
						'type'    => 'select',
						'group'   => $flow_group,
						'label'   => __( 'Modo do fluxo', EOP_TEXT_DOMAIN ),
						'help'    => __( 'Define se a operacao gera proposta publica ou pedido direto.', EOP_TEXT_DOMAIN ),
						'options' => array(
							array(
								'value' => 'proposal',
								'label' => __( 'Proposta publica', EOP_TEXT_DOMAIN ),
							),
							array(
								'value' => 'direct_order',
								'label' => __( 'Pedido direto', EOP_TEXT_DOMAIN ),
							),
						),
					),
					array(
						'key'     => 'discount_mode',
						'type'    => 'select',
						'group'   => $flow_group,
						'label'   => __( 'Modo do desconto', EOP_TEXT_DOMAIN ),
						'help'    => __( 'Define se o campo de desconto aceita porcentagem, valor fixo ou ambos.', EOP_TEXT_DOMAIN ),
						'options' => array(
							array(
								'value' => 'both',
								'label' => __( 'Porcentagem e valor fixo', EOP_TEXT_DOMAIN ),
							),
							array(
								'value' => 'percent',
								'label' => __( 'Somente porcentagem (%)', EOP_TEXT_DOMAIN ),
							),
							array(
								'value' => 'fixed',
								'label' => __( 'Somente valor fixo (R$)', EOP_TEXT_DOMAIN ),
							),
						),
					),
					array(
						'key'   => 'enable_checkout_confirmation',
						'type'  => 'toggle',
						'group' => $flow_group,
						'label' => __( 'Liberar pagamento apos confirmacao', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra o botao de pagar apenas depois que o cliente confirmar a proposta.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'          => 'service_products',
						'type'         => 'multiselect',
						'group'        => $flow_group,
						'searchSource' => 'products',
						'minChars'     => 3,
						'selected'     => $service_products_selected,
						'label'        => __( 'Produtos considerados servicos', EOP_TEXT_DOMAIN ),
						'help'         => __( 'Selecione produtos que aparecem em uma linha Servicos antes do total e nao entram na edicao de nomes do fluxo complementar.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'          => 'service_product_categories',
						'type'         => 'multiselect',
						'group'        => $flow_group,
						'searchSource' => 'categories',
						'minChars'     => 1,
						'selected'     => $service_categories_selected,
						'label'        => __( 'Categorias de produtos considerados servicos', EOP_TEXT_DOMAIN ),
						'help'         => __( 'Qualquer produto dessas categorias entra no grupo de servicos nos totalizadores e no fluxo complementar.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'     => 'order_page_id',
						'type'    => 'select',
						'group'   => $flow_group,
						'label'   => __( 'Pagina do pedido', EOP_TEXT_DOMAIN ),
						'help'    => __( 'Pagina usada para o shortcode [expresso_order].', EOP_TEXT_DOMAIN ),
						'options' => $page_options,
					),
					array(
						'key'     => 'proposal_page_id',
						'type'    => 'select',
						'group'   => $flow_group,
						'label'   => __( 'Pagina da proposta', EOP_TEXT_DOMAIN ),
						'help'    => __( 'Pagina publica do shortcode [expresso_order_proposal].', EOP_TEXT_DOMAIN ),
						'options' => $page_options,
					),
				);

			case 'pdf-display':
				return array(
					array(
						'key'     => 'display_mode',
						'type'    => 'select',
						'group'   => __( 'Visualizacao', EOP_TEXT_DOMAIN ),
						'label'   => __( 'Como visualizar o PDF', EOP_TEXT_DOMAIN ),
						'help'    => __( 'Define se o navegador abre o arquivo em uma nova aba ou inicia download imediato.', EOP_TEXT_DOMAIN ),
						'options' => array(
							array( 'value' => 'new_tab', 'label' => __( 'Abrir em nova aba', EOP_TEXT_DOMAIN ) ),
							array( 'value' => 'download', 'label' => __( 'Baixar automaticamente', EOP_TEXT_DOMAIN ) ),
						),
					),
					array(
						'key'     => 'paper_size',
						'type'    => 'select',
						'group'   => __( 'Visualizacao', EOP_TEXT_DOMAIN ),
						'label'   => __( 'Tamanho do papel', EOP_TEXT_DOMAIN ),
						'help'    => __( 'Escolhe a area fisica do documento entre A4 e Letter.', EOP_TEXT_DOMAIN ),
						'options' => array(
							array( 'value' => 'a4', 'label' => __( 'A4', EOP_TEXT_DOMAIN ) ),
							array( 'value' => 'letter', 'label' => __( 'Carta (Letter)', EOP_TEXT_DOMAIN ) ),
						),
					),
					array(
						'key'     => 'template_name',
						'type'    => 'select',
						'group'   => __( 'Visualizacao', EOP_TEXT_DOMAIN ),
						'label'   => __( 'Modelo do documento', EOP_TEXT_DOMAIN ),
						'help'    => __( 'Seleciona a variacao visual usada para montar o HTML do documento.', EOP_TEXT_DOMAIN ),
						'options' => array(
							array( 'value' => 'simple', 'label' => __( 'Simples', EOP_TEXT_DOMAIN ) ),
							array( 'value' => 'compact', 'label' => __( 'Compacto', EOP_TEXT_DOMAIN ) ),
							array( 'value' => 'minimal', 'label' => __( 'Minimalista', EOP_TEXT_DOMAIN ) ),
						),
					),
					array(
						'key'   => 'ink_saving_mode',
						'type'  => 'toggle',
						'group' => __( 'Renderizacao', EOP_TEXT_DOMAIN ),
						'label' => __( 'Economia de tinta', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Aplica uma versao mais enxuta do layout, com menos peso visual.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'test_mode',
						'type'  => 'toggle',
						'group' => __( 'Renderizacao', EOP_TEXT_DOMAIN ),
						'label' => __( 'Modo de teste', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Marca visualmente o documento como ambiente de teste para evitar uso indevido.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'font_subsetting',
						'type'  => 'toggle',
						'group' => __( 'Renderizacao', EOP_TEXT_DOMAIN ),
						'label' => __( 'Font subsetting', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Controla se o Dompdf embute apenas os glifos usados ou a fonte inteira.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'extended_currency_symbol',
						'type'  => 'toggle',
						'group' => __( 'Renderizacao', EOP_TEXT_DOMAIN ),
						'label' => __( 'Simbolo de moeda estendido', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Usa o simbolo monetario completo na renderizacao, melhorando moedas com glifos especiais.', EOP_TEXT_DOMAIN ),
					),
				);

			case 'pdf-order':
				return array(
					array(
						'key'   => 'order_enabled',
						'type'  => 'toggle',
						'group' => __( 'Documento', EOP_TEXT_DOMAIN ),
						'label' => __( 'Documento habilitado', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Liga ou desliga a geracao do PDF do pedido.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_attach_email',
						'type'  => 'toggle',
						'group' => __( 'Documento', EOP_TEXT_DOMAIN ),
						'label' => __( 'Anexar em e-mails', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Inclui o PDF como anexo nos e-mails compativeis do WooCommerce.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_mark_printed',
						'type'  => 'toggle',
						'group' => __( 'Documento', EOP_TEXT_DOMAIN ),
						'label' => __( 'Marcar como impresso', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Registra metadados de impressao sempre que o documento e aberto ou baixado.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_show_shipping',
						'type'  => 'toggle',
						'group' => __( 'Dados exibidos', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir endereco de entrega', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra o bloco com dados de entrega do cliente.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_show_billing',
						'type'  => 'toggle',
						'group' => __( 'Dados exibidos', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir endereco de cobranca', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra o bloco com endereco de cobranca do cliente.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_show_email',
						'type'  => 'toggle',
						'group' => __( 'Dados exibidos', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir e-mail do cliente', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra o e-mail do cliente no resumo do documento.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_show_phone',
						'type'  => 'toggle',
						'group' => __( 'Dados exibidos', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir telefone do cliente', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra o telefone do cliente no resumo do documento.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_show_notes',
						'type'  => 'toggle',
						'group' => __( 'Dados exibidos', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir notas do cliente', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra as observacoes do pedido na parte final do documento.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_myaccount_download',
						'type'  => 'toggle',
						'group' => __( 'Acesso', EOP_TEXT_DOMAIN ),
						'label' => __( 'Download no Minha Conta', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra o link do PDF do pedido para o cliente logado em Minha Conta.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_show_total_subtotal',
						'type'  => 'toggle',
						'group' => __( 'Totais', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir subtotal', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra a linha de subtotal antes de frete, desconto e total final.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_show_total_shipping',
						'type'  => 'toggle',
						'group' => __( 'Totais', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir frete', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra a linha de frete no bloco de totais.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_show_total_discount',
						'type'  => 'toggle',
						'group' => __( 'Totais', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir desconto total', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra a linha com o desconto total do pedido.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_show_total_total',
						'type'  => 'toggle',
						'group' => __( 'Totais', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir total final', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra a linha final com o total consolidado.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_prefix',
						'type'  => 'text',
						'group' => __( 'Numeracao', EOP_TEXT_DOMAIN ),
						'label' => __( 'Prefixo do numero', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Texto colocado antes do numero sequencial do documento.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_suffix',
						'type'  => 'text',
						'group' => __( 'Numeracao', EOP_TEXT_DOMAIN ),
						'label' => __( 'Sufixo do numero', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Texto colocado depois do numero sequencial.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_padding',
						'type'  => 'number',
						'group' => __( 'Numeracao', EOP_TEXT_DOMAIN ),
						'label' => __( 'Padding', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Quantidade de zeros a esquerda do numero. Exemplo: padding 4 gera 0001.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_next_number',
						'type'  => 'number',
						'group' => __( 'Numeracao', EOP_TEXT_DOMAIN ),
						'label' => __( 'Proximo numero', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Numero que sera usado no proximo documento ainda sem sequencial persistido.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_reset_yearly',
						'type'  => 'toggle',
						'group' => __( 'Numeracao', EOP_TEXT_DOMAIN ),
						'label' => __( 'Reset anual', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Reinicia a sequencia em 1 quando o ano corrente mudar.', EOP_TEXT_DOMAIN ),
					),
				);

			case 'pdf-order-columns':
				return array(
					array(
						'key'   => 'order_show_item_index',
						'type'  => 'toggle',
						'group' => __( 'Colunas visiveis', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir numero do item', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra a coluna com a numeracao sequencial de cada item.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_show_sku',
						'type'  => 'toggle',
						'group' => __( 'Colunas visiveis', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir SKU do produto', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra a linha de SKU abaixo do nome do item.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_show_quantity',
						'type'  => 'toggle',
						'group' => __( 'Colunas visiveis', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir coluna de quantidade', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra a coluna com quantidade do item.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_show_unit_price',
						'type'  => 'toggle',
						'group' => __( 'Colunas visiveis', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir coluna de valor unitario', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra o valor original por unidade.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_show_discount',
						'type'  => 'toggle',
						'group' => __( 'Colunas visiveis', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir coluna de desconto', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra percentual e valor unitario descontado.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_show_discounted_unit_price',
						'type'  => 'toggle',
						'group' => __( 'Colunas visiveis', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir valor unitario com desconto', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra o valor unitario final apos desconto.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_show_line_total',
						'type'  => 'toggle',
						'group' => __( 'Colunas visiveis', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir coluna de total do item', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra o total final por linha de item.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_quantity_position',
						'type'  => 'number',
						'group' => __( 'Ordem das colunas', EOP_TEXT_DOMAIN ),
						'label' => __( 'Posicao da quantidade', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Quanto menor, mais a esquerda.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_unit_price_position',
						'type'  => 'number',
						'group' => __( 'Ordem das colunas', EOP_TEXT_DOMAIN ),
						'label' => __( 'Posicao do valor unitario', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Quanto menor, mais a esquerda.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_discount_position',
						'type'  => 'number',
						'group' => __( 'Ordem das colunas', EOP_TEXT_DOMAIN ),
						'label' => __( 'Posicao do desconto', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Quanto menor, mais a esquerda.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_discounted_unit_price_position',
						'type'  => 'number',
						'group' => __( 'Ordem das colunas', EOP_TEXT_DOMAIN ),
						'label' => __( 'Posicao do valor com desconto', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Quanto menor, mais a esquerda.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_line_total_position',
						'type'  => 'number',
						'group' => __( 'Ordem das colunas', EOP_TEXT_DOMAIN ),
						'label' => __( 'Posicao do total do item', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Quanto menor, mais a esquerda.', EOP_TEXT_DOMAIN ),
					),
				);

			case 'pdf-order-texts':
				return array(
					array(
						'key'   => 'order_item_index_label',
						'type'  => 'text',
						'label' => __( 'Texto da coluna de numero do item', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Personaliza o nome da coluna de numeracao dos itens.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_product_label',
						'type'  => 'text',
						'label' => __( 'Texto da coluna de produto', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Define o texto do cabecalho da coluna principal.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_quantity_label',
						'type'  => 'text',
						'label' => __( 'Texto da coluna de quantidade', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Personaliza o nome da coluna de quantidade.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_unit_price_label',
						'type'  => 'text',
						'label' => __( 'Texto da coluna de valor unitario', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Personaliza o nome da coluna de valor unitario.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_discount_label',
						'type'  => 'text',
						'label' => __( 'Texto da coluna de desconto', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Personaliza o nome da coluna de desconto.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'     => 'order_discount_display_mode',
						'type'    => 'select',
						'label'   => __( 'Formato da coluna de desconto', EOP_TEXT_DOMAIN ),
						'help'    => __( 'Escolhe se a coluna de desconto mostra porcentagem, valor monetario ou ambos.', EOP_TEXT_DOMAIN ),
						'options' => array(
							array( 'value' => 'percent', 'label' => __( 'Porcentagem', EOP_TEXT_DOMAIN ) ),
							array( 'value' => 'currency', 'label' => __( 'Valor', EOP_TEXT_DOMAIN ) ),
							array( 'value' => 'both', 'label' => __( 'Ambos', EOP_TEXT_DOMAIN ) ),
						),
					),
					array(
						'key'   => 'order_discount_suffix',
						'type'  => 'text',
						'label' => __( 'Texto complementar da coluna de desconto', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Texto opcional mostrado depois do valor monetario do desconto por unidade.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_discounted_unit_price_label',
						'type'  => 'text',
						'label' => __( 'Texto da coluna de valor unitario com desconto', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Personaliza o nome da coluna de valor final por unidade.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_discounted_unit_price_suffix',
						'type'  => 'text',
						'label' => __( 'Texto complementar do valor unitario com desconto', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Texto opcional mostrado depois do valor unitario ja com desconto.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_line_total_label',
						'type'  => 'text',
						'label' => __( 'Texto da coluna de total do item', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Personaliza o nome da coluna de total do item.', EOP_TEXT_DOMAIN ),
					),
				);

			case 'pdf-order-style':
				return array(
					array(
						'key'   => 'order_header_background_color',
						'type'  => 'color',
						'group' => __( 'Cores', EOP_TEXT_DOMAIN ),
						'label' => __( 'Fundo do cabecalho', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Cor de fundo da faixa de cabecalho do documento.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_header_text_color',
						'type'  => 'color',
						'group' => __( 'Cores', EOP_TEXT_DOMAIN ),
						'label' => __( 'Texto do cabecalho', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Cor do texto exibido sobre o cabecalho.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_body_text_color',
						'type'  => 'color',
						'group' => __( 'Cores', EOP_TEXT_DOMAIN ),
						'label' => __( 'Texto do corpo', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Cor principal do texto no corpo do documento.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_muted_text_color',
						'type'  => 'color',
						'group' => __( 'Cores', EOP_TEXT_DOMAIN ),
						'label' => __( 'Texto secundario', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Cor de textos auxiliares e menos destacados.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_border_color',
						'type'  => 'color',
						'group' => __( 'Cores', EOP_TEXT_DOMAIN ),
						'label' => __( 'Cor das bordas', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Cor das linhas e bordas da tabela e dos blocos.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_title_font_size',
						'type'  => 'number',
						'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ),
						'label' => __( 'Tamanho do titulo', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Tamanho da fonte do titulo principal do documento.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_meta_font_size',
						'type'  => 'number',
						'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ),
						'label' => __( 'Tamanho dos metadados', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Tamanho da fonte dos dados de cabecalho, como numero e data.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_table_header_font_size',
						'type'  => 'number',
						'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ),
						'label' => __( 'Tamanho do cabecalho da tabela', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Tamanho da fonte do cabecalho das colunas da tabela.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_table_body_font_size',
						'type'  => 'number',
						'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ),
						'label' => __( 'Tamanho do corpo da tabela', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Tamanho da fonte das linhas de itens da tabela.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_table_body_line_height',
						'type'  => 'text',
						'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ),
						'label' => __( 'Altura da linha do corpo da tabela', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Controla o espacamento vertical das linhas de itens na tabela do PDF.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_totals_font_size',
						'type'  => 'number',
						'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ),
						'label' => __( 'Tamanho dos totais', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Tamanho da fonte do bloco de totais.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'order_note_font_size',
						'type'  => 'number',
						'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ),
						'label' => __( 'Tamanho das notas', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Tamanho da fonte das observacoes e do rodape.', EOP_TEXT_DOMAIN ),
					),
				);

			case 'pdf-proposal':
				return array(
					array(
						'key'   => 'proposal_enabled',
						'type'  => 'toggle',
						'group' => __( 'Documento', EOP_TEXT_DOMAIN ),
						'label' => __( 'Documento habilitado', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Liga ou desliga a geracao do PDF da proposta.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_attach_email',
						'type'  => 'toggle',
						'group' => __( 'Documento', EOP_TEXT_DOMAIN ),
						'label' => __( 'Anexar em e-mails', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Inclui o PDF como anexo nos e-mails compativeis do WooCommerce.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_mark_printed',
						'type'  => 'toggle',
						'group' => __( 'Documento', EOP_TEXT_DOMAIN ),
						'label' => __( 'Marcar como impresso', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Registra metadados de impressao sempre que o documento e aberto ou baixado.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_show_shipping',
						'type'  => 'toggle',
						'group' => __( 'Dados exibidos', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir endereco de entrega', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra o bloco com dados de entrega do cliente.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_show_billing',
						'type'  => 'toggle',
						'group' => __( 'Dados exibidos', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir endereco de cobranca', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra o bloco com endereco de cobranca do cliente.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_show_email',
						'type'  => 'toggle',
						'group' => __( 'Dados exibidos', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir e-mail do cliente', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra o e-mail do cliente no resumo do documento.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_show_phone',
						'type'  => 'toggle',
						'group' => __( 'Dados exibidos', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir telefone do cliente', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra o telefone do cliente no resumo do documento.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_show_notes',
						'type'  => 'toggle',
						'group' => __( 'Dados exibidos', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir notas do cliente', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra as observacoes do pedido na parte final do documento.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_public_pdf',
						'type'  => 'toggle',
						'group' => __( 'Acesso', EOP_TEXT_DOMAIN ),
						'label' => __( 'Permitir PDF publico da proposta', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Permite que o cliente baixe o PDF publico da proposta.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_show_total_subtotal',
						'type'  => 'toggle',
						'group' => __( 'Totais', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir subtotal', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra a linha de subtotal antes de frete, desconto e total final.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_show_total_shipping',
						'type'  => 'toggle',
						'group' => __( 'Totais', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir frete', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra a linha de frete no bloco de totais.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_show_total_discount',
						'type'  => 'toggle',
						'group' => __( 'Totais', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir desconto total', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra a linha com o desconto total do pedido.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_show_total_total',
						'type'  => 'toggle',
						'group' => __( 'Totais', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir total final', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra a linha final com o total consolidado.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_prefix',
						'type'  => 'text',
						'group' => __( 'Numeracao', EOP_TEXT_DOMAIN ),
						'label' => __( 'Prefixo do numero', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Texto colocado antes do numero sequencial do documento.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_suffix',
						'type'  => 'text',
						'group' => __( 'Numeracao', EOP_TEXT_DOMAIN ),
						'label' => __( 'Sufixo do numero', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Texto colocado depois do numero sequencial.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_padding',
						'type'  => 'number',
						'group' => __( 'Numeracao', EOP_TEXT_DOMAIN ),
						'label' => __( 'Padding', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Quantidade de zeros a esquerda do numero sequencial.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_next_number',
						'type'  => 'number',
						'group' => __( 'Numeracao', EOP_TEXT_DOMAIN ),
						'label' => __( 'Proximo numero', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Numero que sera usado no proximo documento ainda sem sequencial persistido.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_reset_yearly',
						'type'  => 'toggle',
						'group' => __( 'Numeracao', EOP_TEXT_DOMAIN ),
						'label' => __( 'Reset anual', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Reinicia a sequencia em 1 quando o ano corrente mudar.', EOP_TEXT_DOMAIN ),
					),
				);

			case 'pdf-proposal-columns':
				return array(
					array(
						'key'   => 'proposal_show_item_index',
						'type'  => 'toggle',
						'group' => __( 'Colunas visiveis', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir numero do item', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra a coluna com a posicao sequencial de cada item.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_show_sku',
						'type'  => 'toggle',
						'group' => __( 'Colunas visiveis', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir SKU do produto', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra a linha de SKU abaixo do nome do item.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_show_quantity',
						'type'  => 'toggle',
						'group' => __( 'Colunas visiveis', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir coluna de quantidade', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra a coluna com quantidade do item.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_show_unit_price',
						'type'  => 'toggle',
						'group' => __( 'Colunas visiveis', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir coluna de valor unitario', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra o valor original por unidade.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_show_discount',
						'type'  => 'toggle',
						'group' => __( 'Colunas visiveis', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir coluna de desconto', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra percentual e valor unitario descontado.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_show_discounted_unit_price',
						'type'  => 'toggle',
						'group' => __( 'Colunas visiveis', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir coluna de valor unitario com desconto', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra o valor unitario final apos desconto.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_show_line_total',
						'type'  => 'toggle',
						'group' => __( 'Colunas visiveis', EOP_TEXT_DOMAIN ),
						'label' => __( 'Exibir coluna de total do item', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra o total final por linha de item.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_quantity_position',
						'type'  => 'number',
						'group' => __( 'Ordem das colunas', EOP_TEXT_DOMAIN ),
						'label' => __( 'Posicao da quantidade', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Quanto menor, mais a esquerda.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_unit_price_position',
						'type'  => 'number',
						'group' => __( 'Ordem das colunas', EOP_TEXT_DOMAIN ),
						'label' => __( 'Posicao do valor unitario', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Quanto menor, mais a esquerda.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_discount_position',
						'type'  => 'number',
						'group' => __( 'Ordem das colunas', EOP_TEXT_DOMAIN ),
						'label' => __( 'Posicao do desconto', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Quanto menor, mais a esquerda.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_discounted_unit_price_position',
						'type'  => 'number',
						'group' => __( 'Ordem das colunas', EOP_TEXT_DOMAIN ),
						'label' => __( 'Posicao do valor unitario com desconto', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Quanto menor, mais a esquerda.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_line_total_position',
						'type'  => 'number',
						'group' => __( 'Ordem das colunas', EOP_TEXT_DOMAIN ),
						'label' => __( 'Posicao do total do item', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Quanto menor, mais a esquerda.', EOP_TEXT_DOMAIN ),
					),
				);

			case 'pdf-proposal-texts':
				return array(
					array(
						'key'   => 'proposal_item_index_label',
						'type'  => 'text',
						'label' => __( 'Texto da coluna de numero do item', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Personaliza o nome da coluna de numero do item.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_product_label',
						'type'  => 'text',
						'label' => __( 'Texto da coluna de produto', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Define o texto do cabecalho da coluna principal.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_quantity_label',
						'type'  => 'text',
						'label' => __( 'Texto da coluna de quantidade', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Personaliza o nome da coluna de quantidade.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_unit_price_label',
						'type'  => 'text',
						'label' => __( 'Texto da coluna de valor unitario', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Personaliza o nome da coluna de valor unitario.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_discount_label',
						'type'  => 'text',
						'label' => __( 'Texto da coluna de desconto', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Personaliza o nome da coluna de desconto.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'     => 'proposal_discount_display_mode',
						'type'    => 'select',
						'label'   => __( 'Formato da coluna de desconto', EOP_TEXT_DOMAIN ),
						'help'    => __( 'Escolhe se a coluna de desconto mostra porcentagem, valor monetario ou ambos.', EOP_TEXT_DOMAIN ),
						'options' => array(
							array( 'value' => 'percent', 'label' => __( 'Porcentagem', EOP_TEXT_DOMAIN ) ),
							array( 'value' => 'currency', 'label' => __( 'Valor', EOP_TEXT_DOMAIN ) ),
							array( 'value' => 'both', 'label' => __( 'Ambos', EOP_TEXT_DOMAIN ) ),
						),
					),
					array(
						'key'   => 'proposal_discount_suffix',
						'type'  => 'text',
						'label' => __( 'Texto complementar da coluna de desconto', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Texto opcional mostrado depois do valor monetario do desconto por unidade.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_discounted_unit_price_label',
						'type'  => 'text',
						'label' => __( 'Texto da coluna de valor unitario com desconto', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Personaliza o nome da coluna de valor final por unidade.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_discounted_unit_price_suffix',
						'type'  => 'text',
						'label' => __( 'Texto complementar do valor unitario com desconto', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Texto opcional mostrado depois do valor unitario ja com desconto.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_line_total_label',
						'type'  => 'text',
						'label' => __( 'Texto da coluna de total do item', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Personaliza o nome da coluna de total do item.', EOP_TEXT_DOMAIN ),
					),
				);

			case 'pdf-proposal-style':
				return array(
					array(
						'key'   => 'proposal_header_background_color',
						'type'  => 'color',
						'group' => __( 'Cores', EOP_TEXT_DOMAIN ),
						'label' => __( 'Cor de fundo do cabecalho', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Cor de fundo da faixa superior do documento.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_header_text_color',
						'type'  => 'color',
						'group' => __( 'Cores', EOP_TEXT_DOMAIN ),
						'label' => __( 'Cor do texto do cabecalho', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Cor do texto exibido no cabecalho do documento.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_body_text_color',
						'type'  => 'color',
						'group' => __( 'Cores', EOP_TEXT_DOMAIN ),
						'label' => __( 'Cor do texto do corpo', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Cor principal do texto no corpo do documento.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_muted_text_color',
						'type'  => 'color',
						'group' => __( 'Cores', EOP_TEXT_DOMAIN ),
						'label' => __( 'Cor do texto secundario', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Cor usada em textos auxiliares e de menor destaque.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_border_color',
						'type'  => 'color',
						'group' => __( 'Cores', EOP_TEXT_DOMAIN ),
						'label' => __( 'Cor das bordas', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Cor das linhas e bordas da tabela e dos blocos.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_title_font_size',
						'type'  => 'number',
						'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ),
						'label' => __( 'Tamanho da fonte do titulo', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Tamanho da fonte do titulo principal do documento.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_meta_font_size',
						'type'  => 'number',
						'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ),
						'label' => __( 'Tamanho da fonte dos metadados', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Tamanho da fonte dos dados de cabecalho e identificacao.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_table_header_font_size',
						'type'  => 'number',
						'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ),
						'label' => __( 'Tamanho da fonte do cabecalho da tabela', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Tamanho da fonte dos titulos das colunas da tabela.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_table_body_font_size',
						'type'  => 'number',
						'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ),
						'label' => __( 'Tamanho da fonte do corpo da tabela', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Tamanho da fonte das linhas de itens da tabela.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_table_body_line_height',
						'type'  => 'text',
						'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ),
						'label' => __( 'Altura da linha do corpo da tabela', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Controla o espacamento vertical das linhas de itens na tabela do PDF.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_totals_font_size',
						'type'  => 'number',
						'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ),
						'label' => __( 'Tamanho da fonte dos totais', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Tamanho da fonte do bloco de totais do documento.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'proposal_note_font_size',
						'type'  => 'number',
						'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ),
						'label' => __( 'Tamanho da fonte das notas', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Tamanho da fonte das observacoes e do rodape do documento.', EOP_TEXT_DOMAIN ),
					),
				);

			case 'pdf-edocuments':
				return array(
					array(
						'key'   => 'edoc_enabled',
						'type'  => 'toggle',
						'group' => __( 'Documentos eletronicos', EOP_TEXT_DOMAIN ),
						'label' => __( 'Ativar documentos eletronicos', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Liga a montagem do XML tecnico experimental do documento.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'     => 'edoc_format',
						'type'    => 'select',
						'group'   => __( 'Documentos eletronicos', EOP_TEXT_DOMAIN ),
						'label'   => __( 'Formato / sintaxe', EOP_TEXT_DOMAIN ),
						'help'    => __( 'Escolhe a estrutura-base do XML tecnico.', EOP_TEXT_DOMAIN ),
						'options' => array(
							array( 'value' => 'ubl', 'label' => __( 'UBL', EOP_TEXT_DOMAIN ) ),
							array( 'value' => 'cii', 'label' => __( 'CII', EOP_TEXT_DOMAIN ) ),
							array( 'value' => 'peppol', 'label' => __( 'Peppol BIS', EOP_TEXT_DOMAIN ) ),
						),
					),
					array(
						'key'   => 'edoc_embed_pdf',
						'type'  => 'toggle',
						'group' => __( 'Documentos eletronicos', EOP_TEXT_DOMAIN ),
						'label' => __( 'Embutir PDF', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Inclui referencia ao PDF no XML tecnico quando disponivel.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'edoc_preview_xml',
						'type'  => 'toggle',
						'group' => __( 'Documentos eletronicos', EOP_TEXT_DOMAIN ),
						'label' => __( 'Habilitar preview XML', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Mostra o XML tecnico gerado para o pedido selecionado no preview.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'edoc_logging',
						'type'  => 'toggle',
						'group' => __( 'Documentos eletronicos', EOP_TEXT_DOMAIN ),
						'label' => __( 'Habilitar logs', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Registra a geracao e falhas dos documentos eletronicos.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'edoc_supplier_scheme',
						'type'  => 'text',
						'group' => __( 'Identificacao', EOP_TEXT_DOMAIN ),
						'label' => __( 'Identificador do fornecedor', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Esquema usado para identificar o fornecedor no XML, como CNPJ ou GLN.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'edoc_customer_scheme',
						'type'  => 'text',
						'group' => __( 'Identificacao', EOP_TEXT_DOMAIN ),
						'label' => __( 'Identificador do cliente', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Esquema usado para identificar o cliente no XML.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'edoc_network_endpoint',
						'type'  => 'text',
						'group' => __( 'Rede Peppol', EOP_TEXT_DOMAIN ),
						'label' => __( 'Peppol Endpoint ID', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Endpoint tecnico usado em cenarios Peppol.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'edoc_network_eas',
						'type'  => 'text',
						'group' => __( 'Rede Peppol', EOP_TEXT_DOMAIN ),
						'label' => __( 'Peppol EAS', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Electronic Address Scheme usado pelo endpoint de rede.', EOP_TEXT_DOMAIN ),
					),
				);

			case 'pdf-advanced':
				return array(
					array(
						'key'     => 'advanced_link_access',
						'type'    => 'select',
						'group'   => __( 'Acesso ao link', EOP_TEXT_DOMAIN ),
						'label'   => __( 'Politica de acesso ao link', EOP_TEXT_DOMAIN ),
						'help'    => __( 'Define se o link privado exige nonce, sessao do dono do pedido ou token compartilhavel.', EOP_TEXT_DOMAIN ),
						'options' => array(
							array( 'value' => 'private_nonce', 'label' => __( 'Privado (nonce do admin)', EOP_TEXT_DOMAIN ) ),
							array( 'value' => 'public_token', 'label' => __( 'Publico por token', EOP_TEXT_DOMAIN ) ),
							array( 'value' => 'order_owner', 'label' => __( 'Dono do pedido', EOP_TEXT_DOMAIN ) ),
						),
					),
					array(
						'key'   => 'advanced_pretty_links',
						'type'  => 'toggle',
						'group' => __( 'Acesso ao link', EOP_TEXT_DOMAIN ),
						'label' => __( 'Pretty links', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Usa rota frontal amigavel em vez de admin-post.php para o download.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'advanced_html_output',
						'type'  => 'toggle',
						'group' => __( 'Saida', EOP_TEXT_DOMAIN ),
						'label' => __( 'Forcar output HTML', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Desliga o preview HTML lateral e mantem apenas a geracao final do documento.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'advanced_debug',
						'type'  => 'toggle',
						'group' => __( 'Diagnostico', EOP_TEXT_DOMAIN ),
						'label' => __( 'Debug do modulo', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Liga logs tecnicos de geracao, fallback e acesso do modulo PDF.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'advanced_order_note_logs',
						'type'  => 'toggle',
						'group' => __( 'Diagnostico', EOP_TEXT_DOMAIN ),
						'label' => __( 'Logar nas notas do pedido', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Replica eventos do modulo PDF nas notas internas do pedido.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'advanced_auto_cleanup',
						'type'  => 'toggle',
						'group' => __( 'Manutencao', EOP_TEXT_DOMAIN ),
						'label' => __( 'Limpeza automatica', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Remove cache antigo e arquivos temporarios do modulo automaticamente.', EOP_TEXT_DOMAIN ),
					),
					array(
						'key'   => 'advanced_danger_zone',
						'type'  => 'toggle',
						'group' => __( 'Manutencao', EOP_TEXT_DOMAIN ),
						'label' => __( 'Danger zone', EOP_TEXT_DOMAIN ),
						'help'  => __( 'Desbloqueia operacoes administrativas como limpeza manual de cache e reset de contadores.', EOP_TEXT_DOMAIN ),
					),
				);
		}

		return self::get_generic_section_fields( $values );
	}

	/**
	 * Converte o `options` ([{id,text}]) de um selector-state legado no formato
	 * de opcoes selecionadas do SPA ([{value,label}]).
	 */
	private static function format_selector_options( $state ) {
		$options = isset( $state['options'] ) && is_array( $state['options'] ) ? $state['options'] : array();
		$result  = array();

		foreach ( $options as $option ) {
			$result[] = array(
				'value' => (string) ( $option['id'] ?? '' ),
				'label' => (string) ( $option['text'] ?? '' ),
			);
		}

		return $result;
	}

	private static function get_generic_section_fields( $values ) {
		$values = is_array( $values ) ? $values : array();
		$fields = array();

		foreach ( $values as $key => $value ) {
			if ( ! is_scalar( $value ) && null !== $value ) {
				continue;
			}

			$key = sanitize_key( (string) $key );

			if ( '' === $key ) {
				continue;
			}

			$string_value = (string) $value;
			$type         = 'text';
			$options      = array();

			if ( in_array( $string_value, array( 'yes', 'no' ), true ) || preg_match( '/^(enable|require|show|hide|allow)_/', $key ) ) {
				$type = 'toggle';
			} elseif ( false !== strpos( $key, 'description' ) || false !== strpos( $key, 'body' ) || false !== strpos( $key, 'note' ) || strlen( $string_value ) > 120 ) {
				$type = 'textarea';
			}

			$field = array(
				'key'   => $key,
				'type'  => $type,
				'label' => self::format_field_label( $key ),
			);

			if ( ! empty( $options ) ) {
				$field['options'] = $options;
			}

			$fields[] = $field;
		}

		usort(
			$fields,
			function ( $a, $b ) {
				return strcasecmp( (string) $a['label'], (string) $b['label'] );
			}
		);

		return $fields;
	}

	private static function format_field_label( $key ) {
		$label = str_replace( '_', ' ', (string) $key );
		$label = preg_replace( '/\s+/', ' ', $label );

		return ucwords( trim( $label ) );
	}

	private static function filter_allowed_settings_keys( $values, $config ) {
		$values = is_array( $values ) ? $values : array();

		$exact  = isset( $config['exact'] ) && is_array( $config['exact'] ) ? $config['exact'] : array();
		$prefix = isset( $config['prefix'] ) && is_array( $config['prefix'] ) ? $config['prefix'] : array();

		if ( empty( $exact ) && empty( $prefix ) ) {
			return $values;
		}

		$output = array();

		foreach ( $values as $key => $value ) {
			if ( in_array( $key, $exact, true ) ) {
				$output[ $key ] = $value;
				continue;
			}

			foreach ( $prefix as $pattern ) {
				if ( 0 === strpos( $key, $pattern ) ) {
					$output[ $key ] = $value;
					break;
				}
			}
		}

		return $output;
	}

	private static function prepare_order_summary( WC_Order $order ) {
		$customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$public_url    = class_exists( 'EOP_Public_Proposal' ) ? EOP_Public_Proposal::get_public_link( $order ) : '';
		$date          = $order->get_date_created();

		return array(
			'id'              => (int) $order->get_id(),
			'number'          => '#' . $order->get_order_number(),
			'status'          => (string) $order->get_status(),
			'status_label'    => wc_get_order_status_name( $order->get_status() ),
			'total'           => (float) $order->get_total(),
			'total_html'      => wc_price( $order->get_total() ),
			'currency'        => (string) $order->get_currency(),
			'customer_name'   => $customer_name ? $customer_name : __( 'Sem cliente', EOP_TEXT_DOMAIN ),
			'customer_email'  => $order->get_billing_email(),
			'created_at'      => $date ? $date->date_i18n( 'Y-m-d H:i:s' ) : '',
			'date_label'      => $date ? $date->date_i18n( 'd/m/Y H:i' ) : '—',
			'edit_url'        => EOP_Admin_Page::get_view_url( 'orders', array( 'action' => 'edit', 'order_id' => $order->get_id() ) ),
			'wc_url'          => $order->get_edit_order_url(),
			'pdf_url'         => class_exists( 'EOP_Order_Creator' ) ? EOP_Order_Creator::get_pdf_document_url( $order ) : '',
			'public_url'      => $public_url,
			'is_proposal'     => ! empty( $public_url ),
			'post_confirmation_flow_summary' => class_exists( 'EOP_Post_Confirmation_Flow' ) ? EOP_Post_Confirmation_Flow::get_list_summary( $order ) : array(),
			'created_by_name' => (string) $order->get_meta( '_eop_created_by_name' ),
		);
	}
}
