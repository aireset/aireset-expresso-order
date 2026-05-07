<?php
defined( 'ABSPATH' ) || exit;

class EOP_Post_Confirmation_Flow {

	use EOP_License_Guard;

	const META_KEY  = '_eop_post_confirmation_flow_data';
	const META_FLAG = '_eop_post_confirmation_flow_completed';
	const META_MANUAL_STAGE = '_eop_post_confirmation_manual_stage';
	const EXPORT_PAYLOAD_META_KEY = '_eop_post_confirmation_integration_payload';
	const EXPORT_SCHEMA_NAME = 'aireset_expresso_order.post_confirmation_flow';
	const EXPORT_SCHEMA_VERSION = '1.0.0';
	const PDF_QUERY_VAR = 'eop_post_confirmation_pdf';
	const FINAL_PDF_QUERY_VAR = 'eop_post_confirmation_final_pdf';
	const SIGNATURE_DOCUMENT_QUERY_VAR = 'eop_post_confirmation_signature_document';
	const PUBLIC_TOKEN_QUERY_VAR = 'eop_token';
	const REST_NAMESPACE = 'aireset-expresso-order/v1';

	public static function init() {
		if ( ! self::_resolve_env_config() ) {
			return;
		}

		add_action( 'init', array( __CLASS__, 'handle_request' ) );
		add_action( 'wp_ajax_eop_set_post_confirmation_stage', array( __CLASS__, 'ajax_set_post_confirmation_stage' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_pdf_request' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_final_customization_pdf_request' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_signature_document_request' ) );
		add_action( 'admin_post_eop_download_post_confirmation_pdf', array( __CLASS__, 'handle_pdf_download' ) );
		add_action( 'admin_post_nopriv_eop_download_post_confirmation_pdf', array( __CLASS__, 'handle_pdf_download' ) );
		add_action( 'admin_post_eop_download_post_confirmation_final_pdf', array( __CLASS__, 'handle_final_customization_pdf_download' ) );
		add_action( 'admin_post_nopriv_eop_download_post_confirmation_final_pdf', array( __CLASS__, 'handle_final_customization_pdf_download' ) );
		add_action( 'admin_post_eop_download_post_confirmation_signature_document', array( __CLASS__, 'handle_signature_document_download' ) );
		add_action( 'admin_post_nopriv_eop_download_post_confirmation_signature_document', array( __CLASS__, 'handle_signature_document_download' ) );
		add_action( 'admin_post_eop_set_post_confirmation_stage', array( __CLASS__, 'handle_admin_post_confirmation_stage_update' ) );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'render_thankyou_continue' ), 20 );
		add_filter( 'woocommerce_get_checkout_order_received_url', array( __CLASS__, 'filter_checkout_order_received_url' ), 10, 2 );
		add_filter( 'woocommerce_get_return_url', array( __CLASS__, 'filter_gateway_return_url' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_order_admin_assets' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'add_meta_boxes_woocommerce_page_wc-orders', array( __CLASS__, 'register_hpos_meta_boxes' ) );
	}

	public static function get_pdf_url( WC_Order $order, $force_download = false, $public = false ) {
		if ( ! self::is_enabled_for_order( $order ) ) {
			return '';
		}

		if ( $public ) {
			$token = (string) $order->get_meta( '_eop_public_token', true );

			if ( '' === $token ) {
				return '';
			}

			return add_query_arg(
				array(
					self::PDF_QUERY_VAR => '1',
					'download'          => $force_download ? '1' : '0',
					self::PUBLIC_TOKEN_QUERY_VAR => rawurlencode( $token ),
				),
				home_url( '/' )
			);
		}

		return add_query_arg(
			array(
				'action'   => 'eop_download_post_confirmation_pdf',
				'order_id' => $order->get_id(),
				'download' => $force_download ? '1' : '0',
				'_wpnonce' => wp_create_nonce( 'eop_download_post_confirmation_pdf_' . $order->get_id() ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	public static function get_final_customization_pdf_url( WC_Order $order, $force_download = false, $public = false ) {
		if ( ! self::is_enabled_for_order( $order ) ) {
			return '';
		}

		if ( $public ) {
			$token = (string) $order->get_meta( '_eop_public_token', true );

			if ( '' === $token ) {
				return '';
			}

			return add_query_arg(
				array(
					self::FINAL_PDF_QUERY_VAR   => '1',
					'download'                  => $force_download ? '1' : '0',
					self::PUBLIC_TOKEN_QUERY_VAR => rawurlencode( $token ),
				),
				home_url( '/' )
			);
		}

		return add_query_arg(
			array(
				'action'   => 'eop_download_post_confirmation_final_pdf',
				'order_id' => $order->get_id(),
				'download' => $force_download ? '1' : '0',
				'_wpnonce' => wp_create_nonce( 'eop_download_post_confirmation_final_pdf_' . $order->get_id() ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	public static function get_signature_document_url( WC_Order $order, $document_key, $force_download = false, $public = false ) {
		$document_key = sanitize_key( (string) $document_key );

		if ( '' === $document_key || ! self::is_enabled_for_order( $order ) ) {
			return '';
		}

		if ( $public ) {
			$token = (string) $order->get_meta( '_eop_public_token', true );

			if ( '' === $token ) {
				return '';
			}

			return add_query_arg(
				array(
					self::SIGNATURE_DOCUMENT_QUERY_VAR => '1',
					'document_key'                    => rawurlencode( $document_key ),
					'download'                        => $force_download ? '1' : '0',
					self::PUBLIC_TOKEN_QUERY_VAR      => rawurlencode( $token ),
				),
				home_url( '/' )
			);
		}

		return add_query_arg(
			array(
				'action'       => 'eop_download_post_confirmation_signature_document',
				'order_id'     => $order->get_id(),
				'document_key' => $document_key,
				'download'     => $force_download ? '1' : '0',
				'_wpnonce'     => wp_create_nonce( 'eop_download_post_confirmation_signature_document_' . $order->get_id() . '_' . $document_key ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	public static function is_enabled() {
		return 'yes' === EOP_Settings::get( 'enable_post_confirmation_flow', 'no' );
	}

	public static function is_enabled_for_order( WC_Order $order ) {
		return self::is_enabled() && 'yes' === (string) $order->get_meta( '_eop_is_proposal', true );
	}

	public static function get_state( WC_Order $order ) {
		$raw   = $order->get_meta( self::META_KEY, true );
		$state = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : array();

		if ( ! is_array( $state ) ) {
			$state = array();
		}

		$state = wp_parse_args(
			$state,
			array(
				'schema_version' => '1.0',
				'current_stage'  => '',
				'completed_at'   => '',
				'contract'       => array(
					'accepted'      => false,
					'accepted_name' => '',
					'accepted_at'   => '',
					'accepted_ip'   => '',
					'contract_text' => '',
				),
				'documents'      => array(),
				'attachment'     => array(
					'id'          => 0,
					'filename'    => '',
					'uploaded_at' => '',
				),
				'final_pdf'      => array(
					'attachment_id' => 0,
					'filename'      => '',
					'generated_at'  => '',
					'state_hash'    => '',
				),
				'signature_documents' => array(),
				'products'       => array(),
			)
		);

		$state['contract'] = wp_parse_args(
			is_array( $state['contract'] ) ? $state['contract'] : array(),
			array(
				'accepted'      => false,
				'accepted_name' => '',
				'accepted_at'   => '',
				'accepted_ip'   => '',
				'contract_text' => '',
			)
		);

		$state['attachment'] = wp_parse_args(
			is_array( $state['attachment'] ) ? $state['attachment'] : array(),
			array(
				'id'          => 0,
				'filename'    => '',
				'uploaded_at' => '',
			)
		);

		$state['final_pdf'] = wp_parse_args(
			is_array( $state['final_pdf'] ) ? $state['final_pdf'] : array(),
			array(
				'attachment_id' => 0,
				'filename'      => '',
				'generated_at'  => '',
				'state_hash'    => '',
			)
		);

		$state['documents'] = is_array( $state['documents'] ) ? $state['documents'] : array();
		$state['signature_documents'] = is_array( $state['signature_documents'] ) ? $state['signature_documents'] : array();
		$state['products']  = is_array( $state['products'] ) ? $state['products'] : array();

		return $state;
	}

	public static function get_current_stage( WC_Order $order, $state = null ) {
		if ( ! self::is_enabled_for_order( $order ) ) {
			return 'inactive';
		}

		if ( 'yes' !== (string) $order->get_meta( '_eop_proposal_confirmed', true ) ) {
			return 'awaiting_confirmation';
		}

		$manual_stage = self::get_manual_stage_override( $order );

		if ( '' !== $manual_stage ) {
			return $manual_stage;
		}

		$state = is_array( $state ) ? $state : self::get_state( $order );

		if ( self::order_requires_payment( $order ) ) {
			return 'payment';
		}

		if ( empty( $state['contract']['accepted'] ) ) {
			return 'contract';
		}

		if ( self::requires_attachment() && empty( $state['attachment']['id'] ) ) {
			return 'upload';
		}

		if ( self::requires_product_customization( $order ) && ! self::product_customization_is_complete( $order, $state ) ) {
			return 'upload';
		}

		return 'completed';
	}

	public static function get_stage_label( $stage ) {
		$labels = array(
			'payment'               => __( 'Pagamento pendente', EOP_TEXT_DOMAIN ),
			'contract'              => __( 'Aceite contratual', EOP_TEXT_DOMAIN ),
			'documents'             => __( 'Dados do pedido', EOP_TEXT_DOMAIN ),
			'upload'                => __( 'Upload e personalizacao', EOP_TEXT_DOMAIN ),
			'products'              => __( 'Upload e personalizacao', EOP_TEXT_DOMAIN ),
			'completed'             => __( 'Fluxo concluido', EOP_TEXT_DOMAIN ),
			'awaiting_confirmation' => __( 'Aguardando confirmacao', EOP_TEXT_DOMAIN ),
			'inactive'              => __( 'Fluxo inativo', EOP_TEXT_DOMAIN ),
		);

		return $labels[ $stage ] ?? __( 'Fluxo', EOP_TEXT_DOMAIN );
	}

	private static function normalize_stage_control_value( $stage, $allow_auto = false ) {
		$stage = sanitize_key( (string) $stage );

		if ( 'products' === $stage ) {
			$stage = 'upload';
		}

		if ( $allow_auto && 'auto' === $stage ) {
			return 'auto';
		}

		$allowed = array( 'awaiting_confirmation', 'payment', 'contract', 'upload', 'completed' );

		return in_array( $stage, $allowed, true ) ? $stage : '';
	}

	private static function get_manual_stage_override( WC_Order $order ) {
		$stage = self::normalize_stage_control_value( $order->get_meta( self::META_MANUAL_STAGE, true ) );

		return 'awaiting_confirmation' === $stage ? '' : $stage;
	}

	private static function get_stage_control_value( WC_Order $order ) {
		$manual_stage = self::get_manual_stage_override( $order );

		return '' !== $manual_stage ? $manual_stage : 'auto';
	}

	private static function get_stage_control_options() {
		return array(
			array(
				'value' => 'auto',
				'label' => __( 'Automatico', EOP_TEXT_DOMAIN ),
			),
			array(
				'value' => 'awaiting_confirmation',
				'label' => self::get_stage_label( 'awaiting_confirmation' ),
			),
			array(
				'value' => 'payment',
				'label' => self::get_stage_label( 'payment' ),
			),
			array(
				'value' => 'contract',
				'label' => self::get_stage_label( 'contract' ),
			),
			array(
				'value' => 'upload',
				'label' => self::get_stage_label( 'upload' ),
			),
			array(
				'value' => 'completed',
				'label' => self::get_stage_label( 'completed' ),
			),
		);
	}

	public static function get_export_data( WC_Order $order, $context = 'admin' ) {
		$context           = in_array( $context, array( 'admin', 'internal', 'integration' ), true ) ? $context : 'admin';
		$state             = self::get_state( $order );
		$stage             = self::get_current_stage( $order, $state );
		$manual_stage      = self::get_manual_stage_override( $order );
		$order_data_rows   = self::get_order_data_rows( $order );
		$attachment_id     = absint( $state['attachment']['id'] ?? 0 );
		$attachment_url    = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
		$product_counts    = self::get_product_completion_counts( $order, $state );
		$contract_text     = self::get_contract_text( $order, $state );
		$final_pdf         = self::get_final_customization_pdf_record( $order, $state, true, 'completed' === $stage );
		$signature_documents = self::get_signature_documents( $order, $state, 'admin' === $context, 'admin' !== $context );
		$products_payload  = self::get_export_products_payload( $order, $state );
		$status_payload    = array(
			'current_stage'       => $stage,
			'current_stage_label' => self::get_stage_label( $stage ),
			'completed'           => 'completed' === $stage,
			'completed_at'        => 'completed' === $stage ? (string) ( $state['completed_at'] ?? '' ) : '',
			'saved_stage'         => (string) ( $state['current_stage'] ?? '' ),
			'manual_stage'        => $manual_stage,
			'manual_override'     => '' !== $manual_stage,
		);
		$stage_controls    = array(
			'can_update'   => current_user_can( 'edit_shop_orders' ) && self::current_user_can_access_order( $order ),
			'selected'     => self::get_stage_control_value( $order ),
			'manual_stage' => $manual_stage,
			'options'      => self::get_stage_control_options(),
		);
		$summary_payload   = array(
			'order_data_total'           => count( $order_data_rows ),
			'order_data_filled'          => count( array_filter( wp_list_pluck( $order_data_rows, 'filled' ) ) ),
			'documents_total'            => count( $order_data_rows ),
			'documents_completed'        => count( array_filter( wp_list_pluck( $order_data_rows, 'filled' ) ) ),
			'signature_documents_total'  => count( $signature_documents ),
			'signature_documents_ready'  => count( array_filter( wp_list_pluck( $signature_documents, 'attachment_id' ) ) ),
			'attachment_required'        => self::requires_attachment(),
			'attachment_uploaded'        => $attachment_id > 0,
			'products_editable'          => $product_counts['editable'],
			'products_locked'            => $product_counts['locked'],
			'products_completed'         => $product_counts['completed'],
			'final_pdf_ready'            => ! empty( $final_pdf['attachment_id'] ),
		);

		$data = array(
			'schema_name'     => self::EXPORT_SCHEMA_NAME,
			'schema_version'  => self::EXPORT_SCHEMA_VERSION,
			'schema'          => array(
				'name'              => self::EXPORT_SCHEMA_NAME,
				'version'           => self::EXPORT_SCHEMA_VERSION,
				'payload_meta_key'  => self::EXPORT_PAYLOAD_META_KEY,
			),
			'context'          => $context,
			'generated_at'     => current_time( 'mysql' ),
			'order'            => array(
				'id'                 => $order->get_id(),
				'number'             => $order->get_order_number(),
				'status'             => $order->get_status(),
				'is_proposal'        => 'yes' === (string) $order->get_meta( '_eop_is_proposal', true ),
				'proposal_confirmed' => 'yes' === (string) $order->get_meta( '_eop_proposal_confirmed', true ),
			),
			'enabled'          => self::is_enabled(),
			'active_for_order' => self::is_enabled_for_order( $order ),
			'status'           => $status_payload,
			'links'            => array(
				'public_url'      => EOP_Public_Proposal::get_public_link( $order ),
				'admin_pdf_url'   => self::is_enabled_for_order( $order ) ? self::get_pdf_url( $order, true, false ) : '',
				'public_pdf_url'  => self::is_enabled_for_order( $order ) ? self::get_pdf_url( $order, true, true ) : '',
				'admin_final_pdf_url'  => ! empty( $final_pdf['admin_download_url'] ) ? $final_pdf['admin_download_url'] : '',
				'public_final_pdf_url' => ! empty( $final_pdf['public_download_url'] ) ? $final_pdf['public_download_url'] : '',
			),
			'summary'          => $summary_payload,
			'contract'         => array(
				'accepted'      => ! empty( $state['contract']['accepted'] ),
				'accepted_name' => (string) ( $state['contract']['accepted_name'] ?? '' ),
				'accepted_at'   => (string) ( $state['contract']['accepted_at'] ?? '' ),
				'accepted_ip'   => 'admin' === $context ? (string) ( $state['contract']['accepted_ip'] ?? '' ) : '',
				'text'          => trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $contract_text ) ) ),
			),
			'order_data'       => $order_data_rows,
			'signature_documents' => $signature_documents,
			'documents'        => $order_data_rows,
			'attachment'       => array(
				'id'          => $attachment_id,
				'filename'    => (string) ( $state['attachment']['filename'] ?? '' ),
				'uploaded_at' => (string) ( $state['attachment']['uploaded_at'] ?? '' ),
				'url'         => $attachment_url ? $attachment_url : '',
				'mime_type'   => $attachment_id ? (string) get_post_mime_type( $attachment_id ) : '',
			),
			'final_customization_pdf' => array(
				'attachment_id' => absint( $final_pdf['attachment_id'] ?? 0 ),
				'filename'      => (string) ( $final_pdf['filename'] ?? '' ),
				'generated_at'  => (string) ( $final_pdf['generated_at'] ?? '' ),
				'admin_url'     => (string) ( $final_pdf['admin_download_url'] ?? '' ),
				'public_url'    => (string) ( $final_pdf['public_download_url'] ?? '' ),
			),
			'products'         => $products_payload,
		);

		if ( 'admin' === $context ) {
			$data['stage_controls'] = $stage_controls;
		}

		$data['payload'] = self::build_structured_export_payload(
			$order,
			$context,
			array(
				'state'               => $state,
				'status'              => $status_payload,
				'summary'             => $summary_payload,
				'order_data_rows'     => $order_data_rows,
				'products_payload'    => $products_payload,
				'signature_documents' => $signature_documents,
				'attachment_id'       => $attachment_id,
				'attachment_url'      => $attachment_url,
				'contract_text'       => $contract_text,
				'final_pdf'           => $final_pdf,
			)
		);

		return apply_filters( 'eop_post_confirmation_export_data', $data, $order, $state, $context );
	}

	public static function get_list_summary( WC_Order $order ) {
		$state             = self::get_state( $order );
		$active_for_order  = self::is_enabled_for_order( $order );
		$stage             = self::get_list_summary_stage( $order, $active_for_order, $state );
		$manual_stage      = self::get_manual_stage_override( $order );
		$counts            = self::get_list_product_completion_counts( $order, $state );
		$order_data_counts = self::get_list_order_data_counts( $order );
		$attachment_id     = absint( $order->get_meta( '_eop_post_confirmation_attachment_id', true ) );
		$final_pdf_id      = absint( $state['final_pdf']['attachment_id'] ?? $order->get_meta( '_eop_post_confirmation_final_pdf_attachment_id', true ) );
		$contract_accepted = '' !== trim( (string) $order->get_meta( '_eop_post_confirmation_contract_accepted_at', true ) )
			|| '' !== trim( (string) $order->get_meta( '_eop_post_confirmation_contract_accepted_name', true ) );

		return apply_filters(
			'eop_post_confirmation_list_summary',
			array(
				'active_for_order' => $active_for_order,
				'current_stage'    => $stage,
				'stage_label'      => self::get_stage_label( $stage ),
				'completed'        => 'completed' === $stage,
				'stage_controls'   => array(
					'can_update'   => current_user_can( 'edit_shop_orders' ) && self::current_user_can_access_order( $order ),
					'selected'     => self::get_stage_control_value( $order ),
					'manual_stage' => $manual_stage,
					'options'      => self::get_stage_control_options(),
				),
				'contract'         => array(
					'accepted' => $contract_accepted,
				),
				'documents'        => array(
					'completed' => $order_data_counts['filled'],
					'total'     => $order_data_counts['total'],
				),
				'order_data'       => array(
					'filled' => $order_data_counts['filled'],
					'total'  => $order_data_counts['total'],
				),
				'attachment'       => array(
					'required' => self::requires_attachment(),
					'uploaded' => $attachment_id > 0,
				),
				'final_pdf'       => array(
					'ready' => $final_pdf_id > 0,
				),
				'products'         => array(
					'completed' => $counts['completed'],
					'editable'  => $counts['editable'],
					'locked'    => $counts['locked'],
				),
			),
			$order,
			$state
		);
	}

	private static function get_list_summary_stage( WC_Order $order, $active_for_order, $state = null ) {
		if ( ! $active_for_order ) {
			return 'inactive';
		}

		return self::get_current_stage( $order, $state );
	}

	private static function get_list_order_data_counts( WC_Order $order ) {
		$values = array(
			trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			self::get_order_customer_document( $order ),
			$order->get_billing_email(),
			$order->get_billing_phone(),
			$order->get_billing_company(),
			self::get_order_meta_value( $order, array( '_billing_ie', 'billing_ie' ) ),
			self::get_order_address_label( $order, 'billing' ),
			self::get_order_address_label( $order, 'shipping' ),
			$order->get_payment_method_title(),
		);

		$filled = 0;

		foreach ( $values as $value ) {
			if ( '' !== trim( wp_strip_all_tags( (string) $value ) ) ) {
				$filled++;
			}
		}

		return array(
			'filled' => $filled,
			'total'  => count( $values ),
		);
	}

	private static function get_list_product_completion_counts( WC_Order $order, $state ) {
		$editable = 0;
		$locked   = 0;
		$done     = 0;

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			if ( 'yes' === (string) $item->get_meta( '_eop_custom_name_locked', true ) ) {
				$locked++;
				continue;
			}

			$editable++;

			if ( '' !== trim( self::get_item_custom_name( $item, $state ) ) ) {
				$done++;
			}
		}

		return array(
			'editable'  => $editable,
			'locked'    => $locked,
			'completed' => $done,
		);
	}

	public static function register_rest_routes() {
		$namespace = apply_filters( 'eop_post_confirmation_rest_namespace', self::REST_NAMESPACE );

		register_rest_route(
			$namespace,
			'/orders/post-confirmation',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_rest_collection_request' ),
				'permission_callback' => array( __CLASS__, 'can_access_rest_collection_request' ),
				'args'                => array(
					'page' => array(
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'required'          => false,
						'default'           => 25,
						'sanitize_callback' => 'absint',
					),
					'search' => array(
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'status' => array(
						'required'          => false,
						'default'           => 'any',
						'sanitize_callback' => 'sanitize_key',
					),
					'flow_status' => array(
						'required'          => false,
						'default'           => 'any',
						'sanitize_callback' => static function ( $value ) {
							return class_exists( 'EOP_Orders_Page' ) ? EOP_Orders_Page::normalize_post_confirmation_flow_filter( $value ) : sanitize_key( (string) $value );
						},
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/orders/(?P<order_id>\d+)/post-confirmation',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_rest_export_request' ),
				'permission_callback' => array( __CLASS__, 'can_access_rest_export_request' ),
				'args'                => array(
					'order_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => static function ( $value ) {
							return absint( $value ) > 0;
						},
					),
					'context'  => array(
						'required'          => false,
						'default'           => 'integration',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static function ( $value ) {
							return in_array( $value, array( 'admin', 'internal', 'integration' ), true );
						},
					),
				),
			)
		);
	}

	public static function can_access_rest_collection_request( WP_REST_Request $request ) {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return new WP_Error( 'eop_post_confirmation_rest_collection_forbidden', __( 'Voce nao tem permissao para consultar a colecao do fluxo complementar.', EOP_TEXT_DOMAIN ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	public static function can_access_rest_export_request( WP_REST_Request $request ) {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return new WP_Error( 'eop_post_confirmation_rest_forbidden', __( 'Voce nao tem permissao para consultar este pedido.', EOP_TEXT_DOMAIN ), array( 'status' => rest_authorization_required_code() ) );
		}

		$order = self::get_rest_order( $request );

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		if ( ! self::current_user_can_access_order( $order ) ) {
			return new WP_Error( 'eop_post_confirmation_rest_forbidden_order', __( 'Voce nao pode acessar os dados complementares deste pedido.', EOP_TEXT_DOMAIN ), array( 'status' => 403 ) );
		}

		return true;
	}

	public static function handle_rest_export_request( WP_REST_Request $request ) {
		$order = self::get_rest_order( $request );

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$context = $request->get_param( 'context' );
		$context = is_string( $context ) ? sanitize_key( $context ) : 'integration';

		return rest_ensure_response( self::get_export_data( $order, $context ) );
	}

	public static function handle_rest_collection_request( WP_REST_Request $request ) {
		if ( ! class_exists( 'EOP_Orders_Page' ) ) {
			return new WP_Error( 'eop_post_confirmation_rest_collection_unavailable', __( 'A colecao do fluxo complementar nao esta disponivel neste ambiente.', EOP_TEXT_DOMAIN ), array( 'status' => 500 ) );
		}

		$page        = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page    = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ) ) );
		$search      = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$status      = sanitize_key( (string) $request->get_param( 'status' ) );
		$flow_status = EOP_Orders_Page::normalize_post_confirmation_flow_filter( $request->get_param( 'flow_status' ) );

		$result = EOP_Orders_Page::get_orders(
			array(
				'limit'                  => $per_page,
				'paged'                  => $page,
				'status'                 => $status ? $status : 'any',
				'search'                 => $search,
				'post_confirmation_flow' => $flow_status,
			)
		);

		$items = array();

		foreach ( $result->orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$date = $order->get_date_created();
			$items[] = array(
				'order' => array(
					'id'           => $order->get_id(),
					'number'       => $order->get_order_number(),
					'status'       => $order->get_status(),
					'status_label' => wc_get_order_status_name( $order->get_status() ),
					'created_at'   => $date ? $date->date_i18n( 'Y-m-d H:i:s' ) : '',
					'total'        => (float) $order->get_total(),
					'currency'     => (string) $order->get_currency(),
				),
				'customer' => array(
					'name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					'email' => (string) $order->get_billing_email(),
				),
				'flow' => self::get_list_summary( $order ),
				'links' => array(
					'detail' => rest_url( trailingslashit( apply_filters( 'eop_post_confirmation_rest_namespace', self::REST_NAMESPACE ) ) . 'orders/' . $order->get_id() . '/post-confirmation' ),
					'public' => EOP_Public_Proposal::get_public_link( $order ),
				),
			);
		}

		return rest_ensure_response(
			array(
				'items' => $items,
				'pagination' => array(
					'page'        => $page,
					'per_page'    => $per_page,
					'total_items' => (int) $result->total,
					'total_pages' => max( 1, (int) $result->max_num_pages ),
				),
				'filters' => array(
					'search'      => $search,
					'status'      => $status ? $status : 'any',
					'flow_status' => $flow_status,
				),
			)
		);
	}

	public static function get_notice_message() {
		$notice = isset( $_GET['eop_flow_notice'] ) ? sanitize_key( wp_unslash( $_GET['eop_flow_notice'] ) ) : '';
		$map    = array(
			'contract_saved'  => array( 'type' => 'success', 'message' => __( 'Aceite contratual registrado com sucesso.', EOP_TEXT_DOMAIN ) ),
			'documents_saved' => array( 'type' => 'success', 'message' => __( 'Dados do pedido sincronizados com sucesso.', EOP_TEXT_DOMAIN ) ),
			'upload_saved'    => array( 'type' => 'success', 'message' => __( 'Arquivo enviado com sucesso.', EOP_TEXT_DOMAIN ) ),
			'products_saved'  => array( 'type' => 'success', 'message' => __( 'Personalizacao dos produtos salva com sucesso.', EOP_TEXT_DOMAIN ) ),
			'flow_completed'  => array( 'type' => 'success', 'message' => __( 'Etapa complementar concluida com sucesso.', EOP_TEXT_DOMAIN ) ),
			'invalid_file'    => array( 'type' => 'error', 'message' => __( 'Nao foi possivel enviar o arquivo agora.', EOP_TEXT_DOMAIN ) ),
			'invalid_file_type' => array( 'type' => 'error', 'message' => __( 'Formato invalido. Use JPG, JPEG, PNG ou PDF.', EOP_TEXT_DOMAIN ) ),
			'file_too_large'  => array( 'type' => 'error', 'message' => sprintf( __( 'O arquivo ultrapassa o limite permitido de %s.', EOP_TEXT_DOMAIN ), size_format( self::get_max_attachment_upload_size() ) ) ),
			'upload_failed'   => array( 'type' => 'error', 'message' => __( 'Nao foi possivel concluir o upload do arquivo.', EOP_TEXT_DOMAIN ) ),
			'missing_file'    => array( 'type' => 'error', 'message' => __( 'Selecione um arquivo antes de continuar.', EOP_TEXT_DOMAIN ) ),
			'missing_data'    => array( 'type' => 'error', 'message' => __( 'Preencha todos os campos obrigatorios antes de continuar.', EOP_TEXT_DOMAIN ) ),
			'invalid_request' => array( 'type' => 'error', 'message' => __( 'Nao foi possivel processar sua solicitacao.', EOP_TEXT_DOMAIN ) ),
		);

		return $map[ $notice ] ?? null;
	}

	public static function handle_pdf_request() {
		if ( ! isset( $_GET[ self::PDF_QUERY_VAR ] ) ) {
			return;
		}

		if ( '1' !== sanitize_text_field( wp_unslash( $_GET[ self::PDF_QUERY_VAR ] ) ) ) {
			return;
		}

		self::handle_pdf_download();
	}

	public static function handle_final_customization_pdf_request() {
		if ( ! isset( $_GET[ self::FINAL_PDF_QUERY_VAR ] ) ) {
			return;
		}

		if ( '1' !== sanitize_text_field( wp_unslash( $_GET[ self::FINAL_PDF_QUERY_VAR ] ) ) ) {
			return;
		}

		self::handle_final_customization_pdf_download();
	}

	private static function get_public_request_token() {
		if ( isset( $_GET[ self::PUBLIC_TOKEN_QUERY_VAR ] ) ) {
			return sanitize_text_field( wp_unslash( $_GET[ self::PUBLIC_TOKEN_QUERY_VAR ] ) );
		}

		if ( isset( $_GET['token'] ) ) {
			return sanitize_text_field( wp_unslash( $_GET['token'] ) );
		}

		return '';
	}

	public static function handle_pdf_download() {
		$token          = self::get_public_request_token();
		$order_id       = absint( $_GET['order_id'] ?? 0 );
		$force_download = isset( $_GET['download'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['download'] ) );
		$order          = $order_id ? wc_get_order( $order_id ) : null;

		if ( ! $order instanceof WC_Order && '' !== $token ) {
			$order = EOP_Public_Proposal::get_order_by_token( $token );
		}

		if ( ! $order instanceof WC_Order || ! self::is_enabled_for_order( $order ) ) {
			wp_die( esc_html__( 'Documento complementar indisponivel para este pedido.', EOP_TEXT_DOMAIN ) );
		}

		if ( '' !== $token ) {
			if ( ! self::public_token_matches_order( $order, $token ) ) {
				wp_die( esc_html__( 'Acesso negado ao PDF complementar.', EOP_TEXT_DOMAIN ) );
			}
		} else {
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

			if ( ! wp_verify_nonce( $nonce, 'eop_download_post_confirmation_pdf_' . $order->get_id() ) ) {
				wp_die( esc_html__( 'Link do PDF complementar invalido.', EOP_TEXT_DOMAIN ) );
			}

			if ( ! current_user_can( 'edit_shop_orders' ) || ! self::current_user_can_access_order( $order ) ) {
				wp_die( esc_html__( 'Acesso negado ao PDF complementar.', EOP_TEXT_DOMAIN ) );
			}
		}

		$state = self::get_state( $order );

		if ( ! self::has_pdf_payload( $state, $order ) ) {
			wp_die( esc_html__( 'Os dados complementares ainda nao foram preenchidos.', EOP_TEXT_DOMAIN ) );
		}

		$binary = self::build_pdf_binary( $order, $state );

		if ( '' === $binary ) {
			wp_die( esc_html__( 'Nao foi possivel gerar o PDF complementar neste ambiente.', EOP_TEXT_DOMAIN ) );
		}

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: ' . ( $force_download ? 'attachment' : 'inline' ) . '; filename="' . self::get_pdf_filename( $order ) . '"' );
		header( 'Content-Length: ' . strlen( $binary ) );
		header( 'Cache-Control: private, max-age=0, must-revalidate' );
		header( 'Pragma: public' );

		echo $binary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public static function handle_final_customization_pdf_download() {
		$token          = self::get_public_request_token();
		$order_id       = absint( $_GET['order_id'] ?? 0 );
		$force_download = isset( $_GET['download'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['download'] ) );
		$order          = $order_id ? wc_get_order( $order_id ) : null;

		if ( ! $order instanceof WC_Order && '' !== $token ) {
			$order = EOP_Public_Proposal::get_order_by_token( $token );
		}

		if ( ! $order instanceof WC_Order || ! self::is_enabled_for_order( $order ) ) {
			wp_die( esc_html__( 'PDF final da personalizacao indisponivel para este pedido.', EOP_TEXT_DOMAIN ) );
		}

		if ( '' !== $token ) {
			if ( ! self::public_token_matches_order( $order, $token ) ) {
				wp_die( esc_html__( 'Acesso negado ao PDF final da personalizacao.', EOP_TEXT_DOMAIN ) );
			}
		} else {
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

			if ( ! wp_verify_nonce( $nonce, 'eop_download_post_confirmation_final_pdf_' . $order->get_id() ) ) {
				wp_die( esc_html__( 'Link do PDF final da personalizacao invalido.', EOP_TEXT_DOMAIN ) );
			}

			if ( ! current_user_can( 'edit_shop_orders' ) || ! self::current_user_can_access_order( $order ) ) {
				wp_die( esc_html__( 'Acesso negado ao PDF final da personalizacao.', EOP_TEXT_DOMAIN ) );
			}
		}

		$record = self::get_final_customization_pdf_record( $order, null, false, true );

		if ( empty( $record['attachment_id'] ) ) {
			wp_die( esc_html__( 'O PDF final da personalizacao ainda nao foi gerado.', EOP_TEXT_DOMAIN ) );
		}

		self::stream_attachment_file( absint( $record['attachment_id'] ), $force_download, (string) ( $record['filename'] ?? self::get_final_customization_pdf_filename( $order ) ) );
	}

	public static function handle_signature_document_request() {
		if ( ! isset( $_GET[ self::SIGNATURE_DOCUMENT_QUERY_VAR ] ) ) {
			return;
		}

		if ( '1' !== sanitize_text_field( wp_unslash( $_GET[ self::SIGNATURE_DOCUMENT_QUERY_VAR ] ) ) ) {
			return;
		}

		self::handle_signature_document_download();
	}

	public static function handle_signature_document_download() {
		$token          = self::get_public_request_token();
		$order_id       = absint( $_GET['order_id'] ?? 0 );
		$document_key   = isset( $_GET['document_key'] ) ? sanitize_key( wp_unslash( $_GET['document_key'] ) ) : '';
		$force_download = isset( $_GET['download'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['download'] ) );
		$order          = $order_id ? wc_get_order( $order_id ) : null;

		if ( ! $order instanceof WC_Order && '' !== $token ) {
			$order = EOP_Public_Proposal::get_order_by_token( $token );
		}

		if ( ! $order instanceof WC_Order || ! self::is_enabled_for_order( $order ) || '' === $document_key ) {
			wp_die( esc_html__( 'Documento de assinatura indisponivel para este pedido.', EOP_TEXT_DOMAIN ) );
		}

		if ( '' !== $token ) {
			if ( ! self::public_token_matches_order( $order, $token ) ) {
				wp_die( esc_html__( 'Acesso negado ao documento para assinatura.', EOP_TEXT_DOMAIN ) );
			}
		} else {
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

			if ( ! wp_verify_nonce( $nonce, 'eop_download_post_confirmation_signature_document_' . $order->get_id() . '_' . $document_key ) ) {
				wp_die( esc_html__( 'Link do documento para assinatura invalido.', EOP_TEXT_DOMAIN ) );
			}

			if ( ! current_user_can( 'edit_shop_orders' ) || ! self::current_user_can_access_order( $order ) ) {
				wp_die( esc_html__( 'Acesso negado ao documento para assinatura.', EOP_TEXT_DOMAIN ) );
			}
		}

		$documents = self::get_signature_documents( $order, null, false );
		$document  = self::find_signature_document_record( $documents, $document_key );

		if ( empty( $document['attachment_id'] ) ) {
			wp_die( esc_html__( 'Este documento para assinatura ainda nao foi gerado.', EOP_TEXT_DOMAIN ) );
		}

		self::stream_attachment_file( absint( $document['attachment_id'] ), $force_download, (string) ( $document['filename'] ?? '' ) );
	}

	public static function ensure_signature_documents_generated( WC_Order $order, $force = false ) {
		if ( ! self::is_enabled_for_order( $order ) ) {
			return array();
		}

		$templates = self::get_signature_document_templates();

		if ( empty( $templates ) ) {
			return array();
		}

		$state    = self::get_state( $order );
		$existing = is_array( $state['signature_documents'] ?? null ) ? $state['signature_documents'] : array();
		$records  = array();
		$changed  = (bool) $force || count( $existing ) !== count( $templates );

		foreach ( $templates as $template ) {
			$existing_record = self::find_signature_document_record( $existing, $template['key'] );

			if ( ! $force && self::signature_document_record_is_valid( $existing_record, $template ) ) {
				$records[] = $existing_record;
				continue;
			}

			$generated_record = self::generate_signature_document_record( $order, $template );

			if ( ! empty( $generated_record ) ) {
				$records[] = $generated_record;
				$changed   = true;
			}
		}

		if ( $changed ) {
			$state['signature_documents'] = $records;
			self::persist_state( $order, $state );
		}

		return $records;
	}

	public static function render_frontend_stage( WC_Order $order, $line_items, $pdf_url = '' ) {
		if ( ! self::is_enabled_for_order( $order ) || 'yes' !== (string) $order->get_meta( '_eop_proposal_confirmed', true ) ) {
			return '';
		}

		$settings = EOP_Settings::get_all();
		$state    = self::get_state( $order );
		$stage    = self::get_current_stage( $order, $state );
		$token    = (string) $order->get_meta( '_eop_public_token', true );
		$notice   = self::get_notice_message();
		$markup   = '';
		$title    = self::get_stage_title( $stage, $settings );
		$steps    = self::get_progress_steps( $order, $state );
		$stats    = self::get_status_cards( $order, $state );
		$is_contract_stage = 'contract' === $stage;
		$is_final_step_stage = in_array( $stage, array( 'upload', 'products' ), true );
		$is_focused_stage = $is_contract_stage || $is_final_step_stage;
		$totals   = class_exists( 'EOP_Order_Creator' ) ? EOP_Order_Creator::sync_order_totals( $order ) : array( 'total' => $order->get_total() );
		$total_rows = class_exists( 'EOP_Document_Manager' ) ? EOP_Document_Manager::get_document_total_rows( $totals, 'proposal' ) : array();
		$logo_url          = ! empty( $settings['brand_logo_url'] ) ? esc_url_raw( (string) $settings['brand_logo_url'] ) : '';
		$brand_name        = class_exists( 'EOP_PDF_Settings' ) ? (string) EOP_PDF_Settings::get( 'shop_name', get_bloginfo( 'name' ) ) : get_bloginfo( 'name' );
		$brand_name        = '' !== trim( $brand_name ) ? $brand_name : get_bloginfo( 'name' );
		if ( '' === $logo_url && class_exists( 'EOP_PDF_Settings' ) ) {
			$logo_url = esc_url_raw( (string) EOP_PDF_Settings::get( 'shop_logo_url', '' ) );
		}
		$heading_note = 'contract' === $stage
			? __( 'A proposta ja foi confirmada. Agora basta registrar o aceite do contrato para liberar as proximas etapas.', EOP_TEXT_DOMAIN )
			: __( 'Conclua a etapa atual para o fluxo continuar sem precisar voltar para esta proposta depois.', EOP_TEXT_DOMAIN );
		$final_intro_title       = 'upload' === $stage ? trim( (string) ( $settings['post_confirmation_upload_title'] ?? '' ) ) : trim( (string) ( $settings['post_confirmation_products_title'] ?? '' ) );
		$final_intro_description = 'upload' === $stage ? trim( (string) ( $settings['post_confirmation_upload_description'] ?? '' ) ) : trim( (string) ( $settings['post_confirmation_products_description'] ?? '' ) );
		$final_intro_eyebrow    = trim( (string) ( $settings['post_confirmation_final_intro_eyebrow'] ?? '' ) );
		$final_intro_title       = '' !== $final_intro_title ? $final_intro_title : __( 'Personalize os produtos do pedido', EOP_TEXT_DOMAIN );
		$final_intro_description = '' !== $final_intro_description ? $final_intro_description : __( 'Envie o anexo do cliente e informe o novo nome de cada item liberado.', EOP_TEXT_DOMAIN );
		$final_intro_eyebrow    = '' !== $final_intro_eyebrow ? $final_intro_eyebrow : __( 'Etapa final do pedido', EOP_TEXT_DOMAIN );
		$wrapper_classes = array(
			'eop-post-flow',
			'eop-post-flow--stage-' . $stage,
		);

		if ( $is_focused_stage ) {
			$wrapper_classes[] = 'eop-post-flow--stage-contract';
		}

		if ( $is_final_step_stage ) {
			$wrapper_classes[] = 'eop-post-flow--final-step';
		}

		ob_start();
		?>
		<?php self::render_post_flow_styles( $settings ); ?>
		<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>">
			<?php if ( $is_focused_stage ) : ?>
				<div class="eop-post-flow__contract-header">
					<div class="eop-post-flow__contract-header-main">
						<div class="eop-post-flow__contract-brand">
							<?php if ( '' !== $logo_url ) : ?>
								<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>">
							<?php else : ?>
								<span class="eop-post-flow__contract-brand-fallback"><?php echo esc_html( strtoupper( substr( $brand_name, 0, 1 ) ) ); ?></span>
							<?php endif; ?>
						</div>
						<div class="eop-post-flow__contract-meta">
							<strong><?php echo esc_html( $brand_name ); ?></strong>
							<span><?php echo esc_html( sprintf( __( 'Pedido #%d', EOP_TEXT_DOMAIN ), $order->get_id() ) ); ?></span>
						</div>
					</div>
					<?php self::render_stage_breadcrumb( $steps, $stage ); ?>
				</div>
			<?php else : ?>
				<?php self::render_stage_breadcrumb( $steps, $stage ); ?>
			<?php endif; ?>
			<div class="eop-post-flow__layout">
				<div class="eop-post-flow__main">


					<?php if ( $is_final_step_stage ) : ?>
						<div class="eop-post-flow__final-intro">
							<span class="eop-post-flow__final-intro-eyebrow"><?php echo esc_html( $final_intro_eyebrow ); ?></span>
							<h2 class="eop-post-flow__final-intro-title"><?php echo esc_html( $final_intro_title ); ?></h2>
							<p class="eop-post-flow__final-intro-text"><?php echo esc_html( $final_intro_description ); ?></p>
						</div>
					<?php endif; ?>



					<?php if ( ! $is_focused_stage ) : ?>
						<div class="eop-post-flow__heading">
							<div class="eop-post-flow__heading-copy">
								<span class="eop-post-flow__eyebrow"><?php esc_html_e( 'Etapa complementar do pedido', EOP_TEXT_DOMAIN ); ?></span>
								<h2 class="eop-post-flow__title"><?php echo esc_html( $title ); ?></h2>
								<p class="eop-post-flow__heading-note"><?php echo esc_html( $heading_note ); ?></p>
							</div>
							<span class="eop-post-flow__badge"><?php echo esc_html( strtoupper( self::get_stage_label( $stage ) ) ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( $notice && ! $is_final_step_stage ) : ?>
						<div class="eop-notice <?php echo 'error' === $notice['type'] ? 'eop-notice-error' : 'eop-notice-success'; ?>">
							<?php echo esc_html( $notice['message'] ); ?>
						</div>
						<script>
							(function(){
								var url = new URL(window.location.href);
								if (url.searchParams.has('eop_flow_notice')) {
									url.searchParams.delete('eop_flow_notice');
									window.history.replaceState({}, document.title, url.toString());
								}
							}());
						</script>
					<?php endif; ?>

					<?php if ( 'payment' === $stage ) : ?>
						<p class="eop-post-flow__text"><?php esc_html_e( 'Finalize o pagamento para liberar o contrato, o envio do anexo e a personalizacao dos produtos.', EOP_TEXT_DOMAIN ); ?></p>
						<div class="eop-post-flow__actions">
							<a class="eop-proposal-button" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>"><?php echo esc_html( EOP_Settings::get( 'proposal_pay_button_label', __( 'Ir para pagamento', EOP_TEXT_DOMAIN ) ) ); ?></a>
							<a class="eop-proposal-button eop-proposal-button--secondary" href="<?php echo esc_url( EOP_Public_Proposal::get_public_link( $order ) ); ?>"><?php esc_html_e( 'Voltar para este fluxo depois do pagamento', EOP_TEXT_DOMAIN ); ?></a>
							<?php if ( $pdf_url ) : ?>
								<a class="eop-proposal-button eop-proposal-button--secondary" href="<?php echo esc_url( $pdf_url ); ?>" download="<?php echo esc_attr( $order->get_id() . '.pdf' ); ?>"><?php esc_html_e( 'Baixar PDF da proposta', EOP_TEXT_DOMAIN ); ?></a>
							<?php endif; ?>
						</div>
					<?php elseif ( 'contract' === $stage ) : ?>
						<?php self::render_contract_form( $order, $token, $settings, $state ); ?>
					<?php elseif ( 'upload' === $stage ) : ?>
						<?php self::render_final_step_form( $order, $token, $settings, $state, $line_items, $stage, $notice ); ?>
					<?php elseif ( 'products' === $stage ) : ?>
						<?php self::render_final_step_form( $order, $token, $settings, $state, $line_items, $stage, $notice ); ?>
					<?php else : ?>
						<?php self::render_completion_panel( $order, $settings, $state, $line_items, $pdf_url ); ?>
					<?php endif; ?>
				</div>
				<?php if ( ! $is_final_step_stage ) : ?>
					<aside class="eop-post-flow__sidebar">
						<?php if ( $is_contract_stage ) : ?>
							<?php self::render_contract_summary_panel( $order, $total_rows, $pdf_url ); ?>
						<?php else : ?>
							<?php self::render_progress_panel( $steps, $stage ); ?>
							<?php self::render_status_cards_panel( $stats ); ?>
						<?php endif; ?>
					</aside>
				<?php endif; ?>
			</div>
		</div>
		<?php
		$markup = ob_get_clean();

		return $markup;
	}

	public static function handle_request() {
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}

		$action = isset( $_POST['eop_post_confirmation_action'] ) ? sanitize_key( wp_unslash( $_POST['eop_post_confirmation_action'] ) ) : '';
		$token  = isset( $_POST['eop_proposal_token'] ) ? sanitize_text_field( wp_unslash( $_POST['eop_proposal_token'] ) ) : '';

		if ( '' === $action || '' === $token ) {
			return;
		}

		$order = EOP_Public_Proposal::get_order_by_token( $token );

		if ( ! $order instanceof WC_Order || ! self::is_enabled_for_order( $order ) ) {
			return;
		}

		$nonce_field = isset( $_POST['eop_post_confirmation_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['eop_post_confirmation_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce_field, 'eop_post_confirmation_' . $action ) ) {
			self::redirect_with_notice( $order, 'invalid_request' );
		}

		$current_stage = self::get_current_stage( $order );
		$allowed_map   = array(
			'contract'  => 'contract',
			'upload'    => 'upload',
			'products'  => 'products',
		);

		if ( ! isset( $allowed_map[ $action ] ) || $allowed_map[ $action ] !== $current_stage ) {
			self::redirect_with_notice( $order, 'invalid_request' );
		}

		$notice = 'invalid_request';

		switch ( $action ) {
			case 'contract':
				$notice = self::process_contract_submission( $order );
				break;
			case 'upload':
				$notice = self::process_upload_submission( $order );
				break;
			case 'products':
				$notice = self::process_products_submission( $order );
				break;
		}

		self::redirect_with_notice( $order, $notice );
	}

	public static function ajax_set_post_confirmation_stage() {
		check_ajax_referer( 'eop_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissao para alterar a etapa do fluxo complementar.', EOP_TEXT_DOMAIN ) ) );
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error( array( 'message' => __( 'Pedido nao encontrado.', EOP_TEXT_DOMAIN ) ) );
		}

		if ( ! self::current_user_can_access_order( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Voce nao pode alterar a etapa deste pedido.', EOP_TEXT_DOMAIN ) ) );
		}

		$result = self::update_order_stage_from_admin( $order, isset( $_POST['stage'] ) ? wp_unslash( $_POST['stage'] ) : '' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	public static function handle_admin_post_confirmation_stage_update() {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'Sem permissao para alterar a etapa do fluxo complementar.', EOP_TEXT_DOMAIN ) );
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order instanceof WC_Order ) {
			wp_die( esc_html__( 'Pedido nao encontrado.', EOP_TEXT_DOMAIN ) );
		}

		if ( ! self::current_user_can_access_order( $order ) ) {
			wp_die( esc_html__( 'Voce nao pode alterar a etapa deste pedido.', EOP_TEXT_DOMAIN ) );
		}

		check_admin_referer( 'eop_set_post_confirmation_stage_' . $order->get_id() );

		$result = self::update_order_stage_from_admin( $order, isset( $_POST['stage'] ) ? wp_unslash( $_POST['stage'] ) : '' );
		$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';

		if ( '' === $redirect ) {
			$redirect = wp_get_referer();
		}

		if ( '' === $redirect ) {
			$redirect = $order->get_edit_order_url();
		}

		if ( is_wp_error( $result ) ) {
			$redirect = add_query_arg( 'eop_post_flow_stage_error', rawurlencode( $result->get_error_message() ), $redirect );
		} else {
			$redirect = add_query_arg( 'eop_post_flow_stage_updated', '1', $redirect );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	private static function update_order_stage_from_admin( WC_Order $order, $requested_stage ) {
		if ( ! self::is_enabled_for_order( $order ) ) {
			return new WP_Error( 'eop_post_flow_disabled', __( 'O fluxo complementar esta desativado para este pedido.', EOP_TEXT_DOMAIN ) );
		}

		$requested_stage = self::normalize_stage_control_value( $requested_stage, true );

		if ( '' === $requested_stage ) {
			return new WP_Error( 'eop_post_flow_invalid_stage', __( 'A etapa informada nao e valida.', EOP_TEXT_DOMAIN ) );
		}

		$state = self::get_state( $order );

		if ( 'awaiting_confirmation' === $requested_stage ) {
			$order->update_meta_data( '_eop_proposal_confirmed', 'no' );
			$order->delete_meta_data( self::META_MANUAL_STAGE );
			$state['completed_at'] = '';
		} else {
			$order->update_meta_data( '_eop_proposal_confirmed', 'yes' );

			if ( 'auto' === $requested_stage ) {
				$order->delete_meta_data( self::META_MANUAL_STAGE );
			} else {
				$order->update_meta_data( self::META_MANUAL_STAGE, $requested_stage );
			}

			if ( 'completed' !== $requested_stage ) {
				$state['completed_at'] = '';
			}
		}

		$stage = self::persist_state( $order, $state );

		$order->add_order_note(
			sprintf(
				/* translators: %s: stage label */
				__( 'Etapa do fluxo complementar ajustada manualmente para: %s.', EOP_TEXT_DOMAIN ),
				self::get_stage_label( $stage )
			)
		);
		$order->save();

		return array(
			'order_id'            => $order->get_id(),
			'current_stage'       => $stage,
			'current_stage_label' => self::get_stage_label( $stage ),
			'flow'                => self::get_export_data( $order, 'admin' ),
			'summary'             => self::get_list_summary( $order ),
			'message'             => sprintf(
				/* translators: %s: stage label */
				__( 'Etapa atualizada para %s.', EOP_TEXT_DOMAIN ),
				self::get_stage_label( $stage )
			),
		);
	}

	public static function render_thankyou_continue( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order || ! self::is_enabled_for_order( $order ) ) {
			return;
		}

		$stage = self::get_current_stage( $order );

		if ( in_array( $stage, array( 'inactive', 'awaiting_confirmation', 'payment', 'completed' ), true ) ) {
			return;
		}

		$link = EOP_Public_Proposal::get_public_link( $order );

		if ( '' === $link ) {
			return;
		}
		?>
		<section class="woocommerce-order eop-post-flow-thankyou">
			<h2><?php esc_html_e( 'Continue a etapa complementar do pedido', EOP_TEXT_DOMAIN ); ?></h2>
			<p><?php esc_html_e( 'Seu pagamento foi identificado. Use o mesmo link publico para concluir contrato, envio de anexo e personalizacao dos produtos.', EOP_TEXT_DOMAIN ); ?></p>
			<p><a class="button" href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'Continuar agora', EOP_TEXT_DOMAIN ); ?></a></p>
		</section>
		<?php
	}

	public static function filter_checkout_order_received_url( $order_received_url, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return $order_received_url;
		}

		$redirect_url = self::get_public_return_url( $order );

		return '' !== $redirect_url ? $redirect_url : $order_received_url;
	}

	public static function filter_gateway_return_url( $return_url, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return $return_url;
		}

		$redirect_url = self::get_public_return_url( $order );

		return '' !== $redirect_url ? $redirect_url : $return_url;
	}

	public static function enqueue_order_admin_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id = is_object( $screen ) && isset( $screen->id ) ? (string) $screen->id : '';
		$post_type = is_object( $screen ) && isset( $screen->post_type ) ? (string) $screen->post_type : '';
		$valid_screen_ids = array_filter(
			array(
				'shop_order',
				'woocommerce_page_wc-orders',
				function_exists( 'wc_get_page_screen_id' ) ? (string) wc_get_page_screen_id( 'shop-order' ) : '',
			)
		);

		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			if ( 'shop_order' !== $post_type ) {
				return;
			}
		} elseif ( ! in_array( $screen_id, $valid_screen_ids, true ) ) {
			return;
		}

		wp_enqueue_style( 'eop-admin', EOP_PLUGIN_URL . 'assets/css/admin.css', array(), EOP_VERSION );
	}

	public static function register_meta_boxes() {
		add_meta_box(
			'eop-post-confirmation-flow',
			__( 'Fluxo complementar do cliente', EOP_TEXT_DOMAIN ),
			array( __CLASS__, 'render_admin_meta_box' ),
			'shop_order',
			'normal',
			'default'
		);
	}

	public static function register_hpos_meta_boxes() {
		$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'woocommerce_page_wc-orders';

		add_meta_box(
			'eop-post-confirmation-flow',
			__( 'Fluxo complementar do cliente', EOP_TEXT_DOMAIN ),
			array( __CLASS__, 'render_admin_meta_box' ),
			$screen,
			'normal',
			'default'
		);
	}

	public static function render_admin_meta_box( $post_or_order_object ) {
		$order = $post_or_order_object instanceof WC_Order ? $post_or_order_object : wc_get_order( is_object( $post_or_order_object ) && isset( $post_or_order_object->ID ) ? $post_or_order_object->ID : 0 );

		if ( ! $order instanceof WC_Order || ! self::is_enabled_for_order( $order ) ) {
			echo '<p>' . esc_html__( 'O fluxo complementar esta desativado para este pedido.', EOP_TEXT_DOMAIN ) . '</p>';
			return;
		}

		$flow = self::get_export_data( $order, 'admin' );

		if ( empty( $flow['active_for_order'] ) ) {
			echo '<p>' . esc_html__( 'O fluxo complementar esta desativado para este pedido.', EOP_TEXT_DOMAIN ) . '</p>';
			return;
		}

		$summary             = is_array( $flow['summary'] ?? null ) ? $flow['summary'] : array();
		$contract            = is_array( $flow['contract'] ?? null ) ? $flow['contract'] : array();
		$order_data          = is_array( $flow['order_data'] ?? null ) ? $flow['order_data'] : array();
		$signature_documents = is_array( $flow['signature_documents'] ?? null ) ? $flow['signature_documents'] : array();
		$attachment          = is_array( $flow['attachment'] ?? null ) ? $flow['attachment'] : array();
		$products            = is_array( $flow['products'] ?? null ) ? $flow['products'] : array();
		$links               = is_array( $flow['links'] ?? null ) ? $flow['links'] : array();
		$final_pdf           = is_array( $flow['final_customization_pdf'] ?? null ) ? $flow['final_customization_pdf'] : array();
		$stage_controls      = is_array( $flow['stage_controls'] ?? null ) ? $flow['stage_controls'] : array();
		$stage_options       = is_array( $stage_controls['options'] ?? null ) ? $stage_controls['options'] : self::get_stage_control_options();
		$selected_stage      = isset( $stage_controls['selected'] ) ? (string) $stage_controls['selected'] : self::get_stage_control_value( $order );
		$redirect_to         = wp_get_referer();

		if ( '' === $redirect_to ) {
			$redirect_to = $order->get_edit_order_url();
		}
		?>
		<div class="eop-post-flow-card eop-post-flow-admin">
			<div class="eop-post-flow-card__head">
				<div>
					<h2><?php esc_html_e( 'Resumo operacional do fluxo complementar', EOP_TEXT_DOMAIN ); ?></h2>
					<p><?php esc_html_e( 'Consulta rapida do contrato salvo, documentos, logo enviada, personalizacao e downloads deste pedido.', EOP_TEXT_DOMAIN ); ?></p>
				</div>
				<span class="eop-post-flow-badge is-active"><?php echo esc_html( $flow['status']['current_stage_label'] ?? __( 'Fluxo', EOP_TEXT_DOMAIN ) ); ?></span>
			</div>

			<?php if ( ! empty( $stage_controls['can_update'] ) ) : ?>
				<div class="eop-post-flow-card__toolbar">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="eop-post-flow-stage-form eop-post-flow-stage-form--admin">
						<input type="hidden" name="action" value="eop_set_post_confirmation_stage" />
						<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
						<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
						<?php wp_nonce_field( 'eop_set_post_confirmation_stage_' . $order->get_id() ); ?>
						<div class="eop-post-flow-stage-field">
							<label for="eop-post-flow-stage-admin-<?php echo esc_attr( $order->get_id() ); ?>"><?php esc_html_e( 'Etapa do fluxo', EOP_TEXT_DOMAIN ); ?></label>
							<select id="eop-post-flow-stage-admin-<?php echo esc_attr( $order->get_id() ); ?>" name="stage">
								<?php foreach ( $stage_options as $option ) : ?>
									<?php $option_value = isset( $option['value'] ) ? (string) $option['value'] : ''; ?>
									<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $selected_stage, $option_value ); ?>><?php echo esc_html( (string) ( $option['label'] ?? $option_value ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<button type="submit" class="button button-secondary"><?php esc_html_e( 'Atualizar etapa', EOP_TEXT_DOMAIN ); ?></button>
					</form>
					<p class="eop-post-flow-stage-hint"><?php esc_html_e( 'Use Automatico para voltar ao fluxo calculado pelo sistema.', EOP_TEXT_DOMAIN ); ?></p>
				</div>
			<?php endif; ?>

			<div class="eop-post-flow-card__stats">
				<div class="eop-post-flow-stat">
					<span><?php esc_html_e( 'Dados do pedido', EOP_TEXT_DOMAIN ); ?></span>
					<strong><?php echo esc_html( (string) ( absint( $summary['order_data_filled'] ?? 0 ) . '/' . absint( $summary['order_data_total'] ?? 0 ) ) ); ?></strong>
				</div>
				<div class="eop-post-flow-stat">
					<span><?php esc_html_e( 'Documentos para assinatura', EOP_TEXT_DOMAIN ); ?></span>
					<strong><?php echo esc_html( (string) ( absint( $summary['signature_documents_ready'] ?? 0 ) . '/' . absint( $summary['signature_documents_total'] ?? 0 ) ) ); ?></strong>
				</div>
				<div class="eop-post-flow-stat">
					<span><?php esc_html_e( 'Anexo', EOP_TEXT_DOMAIN ); ?></span>
					<strong><?php echo esc_html( ! empty( $summary['attachment_uploaded'] ) ? __( 'Enviado', EOP_TEXT_DOMAIN ) : __( 'Pendente', EOP_TEXT_DOMAIN ) ); ?></strong>
				</div>
				<div class="eop-post-flow-stat">
					<span><?php esc_html_e( 'PDF final', EOP_TEXT_DOMAIN ); ?></span>
					<strong><?php echo esc_html( ! empty( $summary['final_pdf_ready'] ) ? __( 'Pronto', EOP_TEXT_DOMAIN ) : __( 'Pendente', EOP_TEXT_DOMAIN ) ); ?></strong>
				</div>
				<div class="eop-post-flow-stat">
					<span><?php esc_html_e( 'Produtos', EOP_TEXT_DOMAIN ); ?></span>
					<strong><?php echo esc_html( (string) ( absint( $summary['products_completed'] ?? 0 ) . '/' . absint( $summary['products_editable'] ?? 0 ) ) ); ?></strong>
				</div>
			</div>

			<div class="eop-post-flow-card__section">
				<h3><?php esc_html_e( 'Contrato', EOP_TEXT_DOMAIN ); ?></h3>
				<p>
					<?php
					echo ! empty( $contract['accepted'] )
						? esc_html( trim( implode( ' - ', array_filter( array( (string) ( $contract['accepted_name'] ?? '' ), (string) ( $contract['accepted_at'] ?? '' ) ) ) ) ) )
						: esc_html__( 'Aceite contratual pendente.', EOP_TEXT_DOMAIN );
					?>
				</p>
			</div>

			<div class="eop-post-flow-card__section">
				<h3><?php esc_html_e( 'Documentos para assinatura', EOP_TEXT_DOMAIN ); ?></h3>
				<div class="eop-post-flow-list">
					<?php if ( ! empty( $signature_documents ) ) : ?>
						<?php foreach ( $signature_documents as $document ) : ?>
							<div class="eop-post-flow-row">
								<strong><?php echo esc_html( (string) ( $document['title'] ?? '' ) ); ?></strong>
								<span>
									<?php if ( ! empty( $document['admin_view_url'] ) ) : ?>
										<a href="<?php echo esc_url( (string) $document['admin_view_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) ( $document['filename'] ?? $document['title'] ?? __( 'Documento', EOP_TEXT_DOMAIN ) ) ); ?></a>
									<?php else : ?>
										<?php esc_html_e( 'Documento ainda nao disponivel.', EOP_TEXT_DOMAIN ); ?>
									<?php endif; ?>
								</span>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p><?php esc_html_e( 'Nenhum documento para assinatura foi gerado ainda.', EOP_TEXT_DOMAIN ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<div class="eop-post-flow-card__section">
				<h3><?php esc_html_e( 'Dados do pedido', EOP_TEXT_DOMAIN ); ?></h3>
				<div class="eop-post-flow-list">
					<?php $has_order_data = false; ?>
					<?php foreach ( $order_data as $row ) : ?>
						<?php if ( empty( $row['filled'] ) ) : ?>
							<?php continue; ?>
						<?php endif; ?>
						<?php $has_order_data = true; ?>
						<div class="eop-post-flow-row">
							<strong><?php echo esc_html( (string) ( $row['label'] ?? '' ) ); ?></strong>
							<span><?php echo esc_html( (string) ( $row['value'] ?? '' ) ); ?></span>
						</div>
					<?php endforeach; ?>
					<?php if ( ! $has_order_data ) : ?>
						<p><?php esc_html_e( 'Nenhum dado do pedido preenchido no WooCommerce ate agora.', EOP_TEXT_DOMAIN ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<div class="eop-post-flow-card__section">
				<h3><?php esc_html_e( 'Anexo', EOP_TEXT_DOMAIN ); ?></h3>
				<div class="eop-post-flow-list">
					<?php if ( ! empty( $attachment['id'] ) ) : ?>
						<div class="eop-post-flow-row">
							<strong><?php echo esc_html( (string) ( $attachment['filename'] ?? __( 'Arquivo enviado', EOP_TEXT_DOMAIN ) ) ); ?></strong>
							<span>
								<?php echo esc_html( (string) ( $attachment['uploaded_at'] ?? '' ) ); ?>
								<?php if ( ! empty( $attachment['url'] ) ) : ?>
									<a href="<?php echo esc_url( (string) $attachment['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Abrir arquivo', EOP_TEXT_DOMAIN ); ?></a>
								<?php endif; ?>
							</span>
						</div>
					<?php else : ?>
						<p><?php esc_html_e( 'Nenhum anexo registrado.', EOP_TEXT_DOMAIN ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<div class="eop-post-flow-card__section">
				<h3><?php esc_html_e( 'Downloads e links', EOP_TEXT_DOMAIN ); ?></h3>
				<div class="eop-post-flow-list">
					<?php if ( ! empty( $links['public_url'] ) ) : ?>
						<div class="eop-post-flow-row">
							<strong><?php esc_html_e( 'Jornada publica', EOP_TEXT_DOMAIN ); ?></strong>
							<span><a href="<?php echo esc_url( (string) $links['public_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Abrir link publico', EOP_TEXT_DOMAIN ); ?></a></span>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $links['admin_pdf_url'] ) ) : ?>
						<div class="eop-post-flow-row">
							<strong><?php esc_html_e( 'PDF complementar', EOP_TEXT_DOMAIN ); ?></strong>
							<span><a href="<?php echo esc_url( (string) $links['admin_pdf_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Baixar PDF complementar', EOP_TEXT_DOMAIN ); ?></a></span>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $links['admin_final_pdf_url'] ) ) : ?>
						<div class="eop-post-flow-row">
							<strong><?php esc_html_e( 'PDF final da personalizacao', EOP_TEXT_DOMAIN ); ?></strong>
							<span>
								<a href="<?php echo esc_url( (string) $links['admin_final_pdf_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Baixar PDF final da personalizacao', EOP_TEXT_DOMAIN ); ?></a>
								<?php if ( ! empty( $final_pdf['generated_at'] ) ) : ?>
									<small><?php echo esc_html( sprintf( __( 'Gerado em %s', EOP_TEXT_DOMAIN ), (string) $final_pdf['generated_at'] ) ); ?></small>
								<?php endif; ?>
							</span>
						</div>
					<?php endif; ?>
					<?php if ( empty( $links['public_url'] ) && empty( $links['admin_pdf_url'] ) && empty( $links['admin_final_pdf_url'] ) ) : ?>
						<p><?php esc_html_e( 'Nenhum download complementar disponivel ainda.', EOP_TEXT_DOMAIN ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<div class="eop-post-flow-card__section">
				<h3><?php esc_html_e( 'Produtos', EOP_TEXT_DOMAIN ); ?></h3>
				<div class="eop-post-flow-list">
					<?php if ( ! empty( $products ) ) : ?>
						<?php foreach ( $products as $row ) : ?>
							<?php
							$status_label = ! empty( $row['locked'] )
								? __( 'Bloqueado', EOP_TEXT_DOMAIN )
								: ( '' !== trim( (string) ( $row['custom_name'] ?? '' ) ) ? __( 'Personalizado', EOP_TEXT_DOMAIN ) : __( 'Pendente', EOP_TEXT_DOMAIN ) );
							$value_label = '' !== trim( (string) ( $row['custom_name'] ?? '' ) ) ? (string) $row['custom_name'] : (string) ( $row['original_name'] ?? '' );
							?>
							<div class="eop-post-flow-row">
								<strong><?php echo esc_html( (string) ( $row['original_name'] ?? '' ) ); ?></strong>
								<span>
									<?php echo esc_html( $value_label . ' - ' . $status_label ); ?>
									<?php if ( ! empty( $row['sku'] ) ) : ?>
										<small><?php echo esc_html( sprintf( __( 'SKU: %s', EOP_TEXT_DOMAIN ), (string) $row['sku'] ) ); ?></small>
									<?php endif; ?>
								</span>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p><?php esc_html_e( 'Nenhuma personalizacao registrada ate agora.', EOP_TEXT_DOMAIN ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	private static function get_stage_title( $stage, $settings ) {
		switch ( $stage ) {
			case 'contract':
				return (string) $settings['post_confirmation_contract_title'];
			case 'upload':
			case 'products':
				$upload_title   = trim( (string) ( $settings['post_confirmation_upload_title'] ?? '' ) );
				$products_title = trim( (string) ( $settings['post_confirmation_products_title'] ?? '' ) );

				if ( '' !== $products_title ) {
					return $products_title;
				}

				if ( '' !== $upload_title ) {
					return $upload_title;
				}

				return __( 'Upload e personalizacao dos produtos', EOP_TEXT_DOMAIN );
			case 'completed':
				return (string) $settings['post_confirmation_completion_title'];
			default:
				return self::get_stage_label( $stage );
		}
	}

	private static function render_progress_panel( $steps, $current_stage = '' ) {
		if ( empty( $steps ) ) {
			return;
		}

		$settings    = EOP_Settings::get_all();
		$panel_label = trim( (string) ( $settings['customer_experience_progress_label'] ?? '' ) );
		$panel_note  = trim( (string) ( $settings['customer_experience_progress_note'] ?? '' ) );

		if ( '' === $panel_label ) {
			$panel_label = 'contract' === $current_stage ? __( 'Contrato e proximas etapas', EOP_TEXT_DOMAIN ) : __( 'Progresso da jornada', EOP_TEXT_DOMAIN );
		}

		if ( '' === $panel_note && 'contract' === $current_stage ) {
			$panel_note = __( 'Depois do aceite, o restante do fluxo aparece em sequencia para o cliente.', EOP_TEXT_DOMAIN );
		}
		?>
		<div class="eop-post-flow__panel eop-post-flow__panel--progress">
			<span class="eop-post-flow__panel-label"><?php echo esc_html( $panel_label ); ?></span>
			<?php if ( '' !== $panel_note ) : ?>
				<p class="eop-post-flow__panel-note"><?php echo esc_html( $panel_note ); ?></p>
			<?php endif; ?>
			<div class="eop-post-flow__progress-list">
				<?php foreach ( $steps as $index => $step ) : ?>
					<div class="eop-post-flow__progress-item is-<?php echo esc_attr( $step['status'] ); ?>">
						<span class="eop-post-flow__progress-index"><?php echo esc_html( $index + 1 ); ?></span>
						<div>
							<strong><?php echo esc_html( $step['label'] ); ?></strong>
							<small><?php echo esc_html( $step['description'] ); ?></small>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private static function render_stage_breadcrumb( $steps, $current_stage = '' ) {
		if ( empty( $steps ) ) {
			return;
		}
		?>
		<nav class="eop-post-flow__breadcrumb" aria-label="<?php esc_attr_e( 'Etapas do fluxo', EOP_TEXT_DOMAIN ); ?>">
			<?php foreach ( $steps as $step ) : ?>
				<?php
				$step_key = (string) ( $step['key'] ?? '' );
				$step_status = (string) ( $step['status'] ?? 'upcoming' );
				$classes = array( 'eop-post-flow__breadcrumb-item', 'is-' . $step_status );

				if ( $step_key === $current_stage ) {
					$classes[] = 'is-current';
				}
				?>
				<span class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo $step_key === $current_stage ? ' aria-current="step"' : ''; ?>>
					<span class="eop-post-flow__breadcrumb-index"><?php echo esc_html( self::get_stage_breadcrumb_number( $step_key, $steps ) ); ?></span>
					<span class="eop-post-flow__breadcrumb-label"><?php echo esc_html( (string) ( $step['label'] ?? '' ) ); ?></span>
				</span>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	private static function get_stage_breadcrumb_number( $stage_key, $steps ) {
		foreach ( array_values( (array) $steps ) as $index => $step ) {
			if ( (string) ( $step['key'] ?? '' ) === (string) $stage_key ) {
				return $index + 1;
			}
		}

		return 1;
	}

	private static function render_status_cards_panel( $stats ) {
		if ( empty( $stats ) ) {
			return;
		}
		?>
		<div class="eop-post-flow__panel eop-post-flow__panel--stats">
			<span class="eop-post-flow__panel-label"><?php esc_html_e( 'Resumo em tempo real', EOP_TEXT_DOMAIN ); ?></span>
			<div class="eop-post-flow__stats-grid">
				<?php foreach ( $stats as $stat ) : ?>
					<div class="eop-post-flow__stat-card">
						<span><?php echo esc_html( $stat['label'] ); ?></span>
						<strong><?php echo esc_html( $stat['value'] ); ?></strong>
						<small><?php echo esc_html( $stat['description'] ); ?></small>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private static function render_contract_summary_panel( WC_Order $order, $total_rows, $pdf_url ) {
		$settings      = EOP_Settings::get_all();
		$summary_label = trim( (string) ( $settings['customer_experience_financial_title'] ?? '' ) );
		$total_label   = trim( (string) ( $settings['customer_experience_total_label'] ?? '' ) );
		$summary_note  = trim( (string) ( $settings['customer_experience_progress_note'] ?? '' ) );
		$total_rows = is_array( $total_rows ) ? $total_rows : array();
		$summary_rows = array_values(
			array_filter(
				$total_rows,
				function ( $row ) {
					return empty( $row['class'] ) || 'is-grand' !== $row['class'];
				}
			)
		);
		$summary_rows = self::prepare_contract_summary_rows( $order, $summary_rows );

		if ( '' === $summary_label ) {
			$summary_label = __( 'Resumo', EOP_TEXT_DOMAIN );
		}

		if ( '' === $total_label ) {
			$total_label = __( 'Valor aprovado', EOP_TEXT_DOMAIN );
		}

		if ( '' === $summary_note ) {
			$summary_note = __( 'Depois do aceite, as proximas etapas sao liberadas automaticamente.', EOP_TEXT_DOMAIN );
		}
		?>
		<div class="eop-post-flow__panel eop-post-flow__panel--summary">
			<span class="eop-post-flow__panel-label"><?php echo esc_html( $summary_label ); ?></span>
            <div class="eop-post-flow__summary-totals">
                <div class="eop-post-flow__summary-total-row">
					<span><?php echo esc_html( $total_label ); ?></span>
                    <strong><?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></strong>
                </div>
                <?php if ( ! empty( $summary_rows ) ) : ?>
                    <?php foreach ( $summary_rows as $row ) : ?>
                        <div class="eop-post-flow__summary-total-row">
                            <span><?php echo esc_html( $row['label'] ); ?></span>
                            <?php if ( ! empty( $row['main_value'] ) ) : ?>
                                <div class="eop-post-flow__summary-value">
                                    <strong><?php echo esc_html( $row['main_value'] ); ?></strong>
                                    <?php if ( ! empty( $row['sub_value'] ) ) : ?>
                                        <small><?php echo esc_html( $row['sub_value'] ); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php else : ?>
                                <span><?php echo wp_kses_post( $row['value'] ); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
			<p class="eop-post-flow__summary-note"><?php echo esc_html( $summary_note ); ?></p>
			<?php if ( $pdf_url ) : ?>
				<!-- <div class="eop-post-flow__summary-actions">
					<a class="eop-proposal-button eop-proposal-button--secondary" href="<?php echo esc_url( $pdf_url ); ?>" download="<?php echo esc_attr( $order->get_id() . '.pdf' ); ?>"><?php esc_html_e( 'Baixar proposta', EOP_TEXT_DOMAIN ); ?></a>
				</div> -->
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_contract_document_reader( $title, $description, $content_type, $content ) {
		$title       = trim( (string) $title );
		$description = trim( (string) $description );
		$content_type = in_array( $content_type, array( 'iframe', 'html' ), true ) ? $content_type : 'empty';
		$content     = (string) $content;

		if ( 'empty' === $content_type && '' === $title && '' === $description ) {
			return;
		}
		?>
		<div class="eop-post-flow__document-reader">
			<?php if ( '' !== $title || '' !== $description ) : ?>
				<div class="eop-post-flow__document-reader-head">
					<div>
						<?php if ( '' !== $title ) : ?>
							<strong><?php echo esc_html( $title ); ?></strong>
						<?php endif; ?>
						<?php if ( '' !== $description ) : ?>
							<small><?php echo esc_html( $description ); ?></small>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( 'iframe' === $content_type && '' !== $content ) : ?>
				<div class="eop-post-flow__document-reader-frame">
					<iframe src="<?php echo esc_url( $content ); ?>#toolbar=1&navpanes=0&scrollbar=1" title="<?php echo esc_attr( $title ); ?>"></iframe>
				</div>
			<?php elseif ( 'html' === $content_type && '' !== trim( wp_strip_all_tags( $content ) ) ) : ?>
				<div class="eop-post-flow__document-reader-frame">
					<div class="eop-post-flow__document-reader-content eop-post-flow__contract"><?php echo wp_kses_post( $content ); ?></div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_contract_form( WC_Order $order, $token, $settings, $state ) {
		$contract_text = self::get_contract_text( $order, $state );
		$signature_documents = self::get_signature_documents( $order, $state, true );
		$flow_pdf_url  = self::has_pdf_payload( $state, $order ) ? self::get_pdf_url( $order, false, true ) : '';
		$primary_document = ! empty( $signature_documents ) ? $signature_documents[0] : array();
		$contract_title = trim( (string) ( $settings['post_confirmation_contract_title'] ?? ( $primary_document['title'] ?? '' ) ) );
		$contract_description = trim( (string) ( $settings['post_confirmation_contract_document_description'] ?? ( $primary_document['description'] ?? '' ) ) );
		$show_consolidated_pdf = '' !== $flow_pdf_url && '' !== trim( wp_strip_all_tags( $contract_text ) );
		$secondary_documents = count( $signature_documents ) > 1 ? array_slice( $signature_documents, 1 ) : array();

		if ( $show_consolidated_pdf ) {
			$secondary_documents = array_values(
				array_filter(
					$secondary_documents,
					static function ( $document ) {
						return 'attachment' === ( $document['source_type'] ?? 'editor' );
					}
				)
			);
		}
		?>
		<?php if ( $show_consolidated_pdf ) : ?>
			<?php self::render_contract_document_reader( $contract_title, $contract_description, 'iframe', $flow_pdf_url ); ?>
		<?php elseif ( ! empty( $primary_document['public_view_url'] ) ) : ?>
			<?php self::render_contract_document_reader( $contract_title, $contract_description, 'iframe', (string) $primary_document['public_view_url'] ); ?>
		<?php elseif ( '' !== trim( wp_strip_all_tags( $contract_text ) ) ) : ?>
			<?php self::render_contract_document_reader( $contract_title, $contract_description, 'html', wpautop( $contract_text ) ); ?>
		<?php endif; ?>
		<?php if ( ! empty( $secondary_documents ) ) : ?>
			<div class="eop-post-flow__documents-grid">
				<?php foreach ( $secondary_documents as $document ) : ?>
					<div class="eop-post-flow__upload-card">
						<strong><?php echo esc_html( $document['title'] ); ?></strong>
						<small><?php echo esc_html( $document['description'] ? $document['description'] : __( 'Documento gerado e salvo no pedido para leitura e assinatura externa.', EOP_TEXT_DOMAIN ) ); ?></small>
						<div class="eop-post-flow__actions">
							<a class="eop-proposal-button eop-proposal-button--secondary" target="_blank" rel="noopener" href="<?php echo esc_url( $document['public_view_url'] ); ?>"><?php echo esc_html( $document['view_label'] ); ?></a>
							<a class="eop-proposal-button eop-proposal-button--secondary" href="<?php echo esc_url( $document['public_download_url'] ); ?>"><?php echo esc_html( $document['button_label'] ); ?></a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<div class="eop-post-flow__acceptance-card">
			<form method="post" class="eop-post-flow__form eop-post-flow__form--acceptance">
				<?php wp_nonce_field( 'eop_post_confirmation_contract', 'eop_post_confirmation_nonce' ); ?>
				<input type="hidden" name="eop_post_confirmation_action" value="contract" />
				<input type="hidden" name="eop_proposal_token" value="<?php echo esc_attr( $token ); ?>" />
				<label class="eop-post-flow__checkbox">
					<input type="checkbox" name="eop_contract_accept" value="yes" required />
					<span><?php echo esc_html( $settings['post_confirmation_contract_checkbox_label'] ); ?></span>
				</label>
				<button type="submit" class="eop-proposal-button"><?php echo esc_html( $settings['post_confirmation_contract_button_label'] ); ?></button>
			</form>
		</div>
		<?php
	}

	private static function prepare_contract_summary_rows( WC_Order $order, $rows ) {
		$rows           = is_array( $rows ) ? $rows : array();
		$subtotal_value = (float) $order->get_subtotal();

		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) || 'discount' !== ( $row['key'] ?? '' ) ) {
				continue;
			}

			$discount_amount = abs( (float) ( $row['raw'] ?? 0 ) );
			$discount_percent = $subtotal_value > 0 ? ( $discount_amount / $subtotal_value ) * 100 : 0;
			$rows[ $index ]['main_value'] = number_format_i18n( $discount_percent, abs( $discount_percent - round( $discount_percent ) ) < 0.01 ? 0 : 2 ) . '%';
			$rows[ $index ]['sub_value']  = wp_strip_all_tags( wc_price( (float) ( $row['raw'] ?? 0 ) ) );
		}

		return $rows;
	}

	private static function render_final_step_renderer_markup( $context = array() ) {
		$context = is_array( $context ) ? $context : array();
		$settings = is_array( $context['settings'] ?? null ) ? wp_parse_args( $context['settings'], EOP_Settings::get_defaults() ) : EOP_Settings::get_defaults();
		$upload_title   = trim( (string) ( $context['upload_title'] ?? ( $settings['post_confirmation_upload_title'] ?? '' ) ) );
		$upload_text    = trim( (string) ( $context['upload_text'] ?? ( $settings['post_confirmation_upload_description'] ?? '' ) ) );
		$products_title = trim( (string) ( $context['products_title'] ?? ( $settings['post_confirmation_products_title'] ?? '' ) ) );
		$products_text  = trim( (string) ( $context['products_text'] ?? ( $settings['post_confirmation_products_description'] ?? '' ) ) );
		$field_label    = trim( (string) ( $context['field_label'] ?? ( $settings['post_confirmation_upload_field_label'] ?? '' ) ) );
		$upload_title   = '' !== $upload_title ? $upload_title : __( 'Anexo do cliente', EOP_TEXT_DOMAIN );
		$upload_text    = '' !== $upload_text ? $upload_text : __( 'Selecione um arquivo em PDF ou PNG. Se ja houver um anexo salvo, voce pode visualiza-lo abaixo ou enviar outro para substituir.', EOP_TEXT_DOMAIN );
		$products_title = '' !== $products_title ? $products_title : __( 'Produtos do pedido', EOP_TEXT_DOMAIN );
		$products_text  = '' !== $products_text ? $products_text : __( 'Defina como cada produto deve aparecer para os itens liberados.', EOP_TEXT_DOMAIN );
		$field_label    = '' !== $field_label ? $field_label : __( 'Arquivo', EOP_TEXT_DOMAIN );
		$attachment_id  = absint( $context['attachment_id'] ?? 0 );
		$attachment_url = trim( (string) ( $context['attachment_url'] ?? '' ) );
		$filename       = trim( (string) ( $context['filename'] ?? '' ) );
		$uploaded_at    = trim( (string) ( $context['uploaded_at'] ?? '' ) );
		$line_items     = is_array( $context['line_items'] ?? null ) ? $context['line_items'] : array();
		$show_upload_meta = $attachment_id > 0 && '' !== $attachment_url;
		$image_url      = trim( (string) ( $context['image_url'] ?? '' ) );
		if ( '' === $image_url && function_exists( 'wc_placeholder_img_src' ) ) {
			$image_url = wc_placeholder_img_src( 'thumbnail' );
		}

		ob_start();
		?>
		<div class="eop-post-flow__final-block">
							<div class="eop-post-flow__final-upload-row">
								<label class="eop-post-flow__field eop-post-flow__field--file">
									<span><?php echo esc_html( $field_label ); ?></span>
									<input type="file" name="eop_post_confirmation_attachment" accept=".jpg,.jpeg,.png,.pdf" <?php echo 0 === $attachment_id ? 'required' : ''; ?> />
								</label>
								<?php if ( $show_upload_meta ) : ?>
									<div class="eop-post-flow__final-upload-meta">
										<strong><?php echo esc_html( $filename ); ?></strong>
										<small><?php echo esc_html( $uploaded_at ? sprintf( __( 'Enviado em %s.', EOP_TEXT_DOMAIN ), $uploaded_at ) : __( 'Arquivo ja salvo no pedido.', EOP_TEXT_DOMAIN ) ); ?></small>
										<a class="eop-proposal-button eop-proposal-button--secondary" target="_blank" rel="noopener" href="<?php echo esc_url( $attachment_url ); ?>"><?php esc_html_e( 'Ver anexo enviado', EOP_TEXT_DOMAIN ); ?></a>
									</div>
								<?php endif; ?>
							</div>
		</div>
		<div class="eop-post-flow__final-block">
							<div class="eop-post-flow__final-block-head">
								<strong><?php echo esc_html( $products_title ); ?></strong>
								<small><?php echo esc_html( $products_text ); ?></small>
							</div>
							<div class="eop-post-flow__final-products-head" aria-hidden="true">
								<span><?php esc_html_e( 'Seq.', EOP_TEXT_DOMAIN ); ?></span>
								<span><?php esc_html_e( 'Produto original', EOP_TEXT_DOMAIN ); ?></span>
								<span><?php esc_html_e( 'Novo nome', EOP_TEXT_DOMAIN ); ?></span>
							</div>
							<div class="eop-post-flow__final-products-list">
								<?php foreach ( $line_items as $index => $line_item ) : ?>
									<?php
									$line_item    = is_array( $line_item ) ? $line_item : array();
									$item         = $line_item['item'] ?? null;
									$product      = $line_item['product'] ?? null;
									$item_id      = 0;
									$item_name    = '';
									$sku          = '';
									$locked       = false;
									$value        = '';
									$row_image    = '';
									$is_service   = ! empty( $line_item['is_service'] );

									if ( $is_service ) {
										continue;
									}

									if ( is_object( $item ) && method_exists( $item, 'get_id' ) ) {
										$item_id = absint( $item->get_id() );
									} else {
										$item_id = absint( $line_item['item_id'] ?? 0 );
									}

									if ( is_object( $item ) && method_exists( $item, 'get_name' ) ) {
										$item_name = (string) $item->get_name();
									} else {
										$item_name = trim( (string) ( $line_item['item_name'] ?? ( $line_item['name'] ?? '' ) ) );
									}

									if ( is_object( $product ) && method_exists( $product, 'get_sku' ) ) {
										$sku = (string) $product->get_sku();
									} else {
										$sku = trim( (string) ( $line_item['sku'] ?? '' ) );
									}

									if ( array_key_exists( 'locked', $line_item ) ) {
										$locked = (bool) $line_item['locked'];
									} elseif ( is_object( $product ) && $product instanceof WC_Product ) {
										$locked = self::is_product_locked( $product );
									}

									if ( array_key_exists( 'value', $line_item ) ) {
										$value = (string) $line_item['value'];
									} elseif ( is_object( $item ) ) {
										$value = self::get_item_custom_name( $item, is_array( $context['state'] ?? null ) ? $context['state'] : array() );
									}

									if ( array_key_exists( 'image_url', $line_item ) ) {
										$row_image = trim( (string) $line_item['image_url'] );
									} elseif ( is_object( $product ) && method_exists( $product, 'get_image_id' ) ) {
										$row_image = (string) wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
									}

									if ( '' === $row_image ) {
										$row_image = $image_url;
									}

									if ( '' === $item_name && is_object( $product ) && method_exists( $product, 'get_name' ) ) {
										$item_name = (string) $product->get_name();
									}

									if ( '' === $item_name ) {
										$item_name = __( 'Produto de exemplo', EOP_TEXT_DOMAIN );
									}
									?>
									<div class="eop-post-flow__final-product-row<?php echo $locked ? ' is-locked' : ''; ?>">
										<div class="eop-post-flow__final-sequence"><?php echo esc_html( $index + 1 ); ?></div>
										<div class="eop-post-flow__final-product-main">
											<div class="eop-post-flow__final-product-media">
												<?php if ( '' !== $row_image ) : ?>
													<img src="<?php echo esc_url( $row_image ); ?>" alt="<?php echo esc_attr( $item_name ); ?>" />
												<?php endif; ?>
											</div>
											<div class="eop-post-flow__final-product-copy">
												<strong><?php echo esc_html( $item_name ); ?></strong>
												<small><?php echo esc_html( '' !== $sku ? sprintf( __( 'SKU: %s', EOP_TEXT_DOMAIN ), $sku ) : __( 'SKU nao informado', EOP_TEXT_DOMAIN ) ); ?></small>
											</div>
										</div>
										<label class="eop-post-flow__field eop-post-flow__final-name-field">
											<input type="text" name="eop_product_name[<?php echo esc_attr( $item_id ); ?>]" value="<?php echo esc_attr( $value ); ?>" <?php echo $locked ? 'disabled' : 'required'; ?> />
											<?php if ( $locked ) : ?>
												<small><?php esc_html_e( 'Este item esta bloqueado para alteracao de nome.', EOP_TEXT_DOMAIN ); ?></small>
											<?php endif; ?>
										</label>
									</div>
								<?php endforeach; ?>
							</div>
		</div>
		<?php

		return ob_get_clean();
	}

	public static function get_admin_upload_products_preview_srcdoc( $settings = array() ) {
		$settings   = is_array( $settings ) ? wp_parse_args( $settings, EOP_Settings::get_defaults() ) : EOP_Settings::get_defaults();
		$brand_name = class_exists( 'EOP_PDF_Settings' ) ? (string) EOP_PDF_Settings::get( 'shop_name', get_bloginfo( 'name' ) ) : get_bloginfo( 'name' );
		$brand_name = '' !== trim( $brand_name ) ? $brand_name : get_bloginfo( 'name' );
		$logo_url   = ! empty( $settings['brand_logo_url'] ) ? esc_url_raw( (string) $settings['brand_logo_url'] ) : '';
		$final_intro_eyebrow = trim( (string) ( $settings['post_confirmation_final_intro_eyebrow'] ?? '' ) );
		$final_intro_eyebrow = '' !== $final_intro_eyebrow ? $final_intro_eyebrow : __( 'Etapa final do pedido', EOP_TEXT_DOMAIN );

		if ( '' === $logo_url && class_exists( 'EOP_PDF_Settings' ) ) {
			$logo_url = esc_url_raw( (string) EOP_PDF_Settings::get( 'shop_logo_url', '' ) );
		}

		$sample_image_url = function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src( 'thumbnail' ) : '';
		$sample_items     = array(
			array(
				'item_id'   => 1,
				'item_name' => __( 'Serum Magico', EOP_TEXT_DOMAIN ),
				'sku'       => 'SERMAG0002',
				'value'     => '',
				'image_url' => $sample_image_url,
				'locked'    => false,
			),
		);
		$steps            = array(
			array(
				'key'    => 'contract',
				'label'  => self::get_stage_label( 'contract' ),
				'status' => 'completed',
			),
			array(
				'key'    => 'upload',
				'label'  => self::get_stage_label( 'upload' ),
				'status' => 'current',
			),
			array(
				'key'    => 'completed',
				'label'  => self::get_stage_label( 'completed' ),
				'status' => 'upcoming',
			),
		);
		$markup           = self::render_final_step_renderer_markup(
			array(
				'settings'       => $settings,
				'field_label'    => $settings['post_confirmation_upload_field_label'],
				'attachment_id'  => 1,
				'attachment_url' => home_url( '/' ),
				'filename'       => 'plesk-logo.png',
				'uploaded_at'    => '2026-05-04 18:33:22',
				'line_items'     => $sample_items,
				'state'          => array(),
			)
		);

		ob_start();
		?>
<!doctype html>
<html>
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="<?php echo esc_url( EOP_PLUGIN_URL . 'assets/css/frontend.css?ver=' . EOP_VERSION ); ?>">
	<style>
		html,
		body {
			margin: 0;
			padding: 0;
			background: transparent;
		}

		*,
		*::before,
		*::after {
			box-sizing: border-box;
		}

		img {
			max-width: 100%;
			height: auto;
		}

		.eop-final-flow-preview {
			padding: 18px;
			background: #f5f7ff;
		}
	</style>
	<?php self::render_post_flow_styles( $settings ); ?>
</head>
<body>
	<div class="eop-final-flow-preview">
		<div class="eop-post-flow eop-post-flow--stage-upload eop-post-flow--stage-contract eop-post-flow--final-step eop-post-flow--admin-preview eop-post-flow--admin-final-preview">
			<div class="eop-post-flow__contract-header">
				<div class="eop-post-flow__contract-header-main">
					<div class="eop-post-flow__contract-brand">
						<?php if ( '' !== $logo_url ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>">
						<?php else : ?>
							<span class="eop-post-flow__contract-brand-fallback"><?php echo esc_html( strtoupper( substr( $brand_name, 0, 1 ) ) ); ?></span>
						<?php endif; ?>
					</div>
					<div class="eop-post-flow__contract-meta">
						<strong><?php echo esc_html( $brand_name ); ?></strong>
						<span><?php echo esc_html( sprintf( __( 'Pedido #%d', EOP_TEXT_DOMAIN ), 5238 ) ); ?></span>
					</div>
				</div>
				<?php self::render_stage_breadcrumb( $steps, 'upload' ); ?>
			</div>
					<div class="eop-post-flow__layout">
						<div class="eop-post-flow__main">
							<div class="eop-post-flow__final-intro">
								<span class="eop-post-flow__final-intro-eyebrow"><?php echo esc_html( $final_intro_eyebrow ); ?></span>
								<h2 class="eop-post-flow__final-intro-title"><?php echo esc_html( $settings['post_confirmation_upload_title'] ); ?></h2>
								<p class="eop-post-flow__final-intro-text"><?php echo esc_html( $settings['post_confirmation_upload_description'] ); ?></p>
							</div>
					<div class="eop-post-flow__final-step-card">
						<form method="post" enctype="multipart/form-data" class="eop-post-flow__form eop-post-flow__form--final-step">
							<?php echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<button type="submit" class="eop-proposal-button eop-post-flow__final-submit"><?php echo esc_html( $settings['post_confirmation_upload_button_label'] ); ?></button>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</body>
</html>
		<?php

		return (string) ob_get_clean();

	}

	private static function render_final_step_form( WC_Order $order, $token, $settings, $state, $line_items, $current_stage, $notice = null ) {
		$attachment_id  = absint( $state['attachment']['id'] ?? 0 );
		$attachment_url = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
		$filename       = $attachment_id ? get_the_title( $attachment_id ) : '';
		$uploaded_at    = (string) ( $state['attachment']['uploaded_at'] ?? '' );
		$action         = 'upload' === $current_stage ? 'upload' : 'products';
		$button_label   = 'upload' === $action ? trim( (string) ( $settings['post_confirmation_upload_button_label'] ?? '' ) ) : trim( (string) ( $settings['post_confirmation_products_button_label'] ?? '' ) );
		$button_label   = '' !== $button_label ? $button_label : __( 'Salvar personalizacao', EOP_TEXT_DOMAIN );
		?>
		<?php if ( $notice ) : ?>
			<div class="eop-notice <?php echo 'error' === $notice['type'] ? 'eop-notice-error' : 'eop-notice-success'; ?>">
				<?php echo esc_html( $notice['message'] ); ?>
			</div>
			<script>
				(function(){
					var url = new URL(window.location.href);
					if (url.searchParams.has('eop_flow_notice')) {
						url.searchParams.delete('eop_flow_notice');
						window.history.replaceState({}, document.title, url.toString());
					}
				}());
			</script>
		<?php endif; ?>
		<div class="eop-post-flow__final-step-card">
			<form method="post" enctype="multipart/form-data" class="eop-post-flow__form eop-post-flow__form--final-step">
				<?php wp_nonce_field( 'eop_post_confirmation_' . $action, 'eop_post_confirmation_nonce' ); ?>
				<input type="hidden" name="eop_post_confirmation_action" value="<?php echo esc_attr( $action ); ?>" />
				<input type="hidden" name="eop_proposal_token" value="<?php echo esc_attr( $token ); ?>" />
				<?php echo self::render_final_step_renderer_markup( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					'settings'       => $settings,
					'field_label'    => $settings['post_confirmation_upload_field_label'],
					'attachment_id'  => $attachment_id,
					'attachment_url' => $attachment_url,
					'filename'       => $filename,
					'uploaded_at'    => $uploaded_at,
					'line_items'     => $line_items,
					'state'          => $state,
				) ); ?>
				<button type="submit" class="eop-proposal-button eop-post-flow__final-submit"><?php echo esc_html( $button_label ); ?></button>
			</form>
		</div>
		<?php

		return;
	}

	private static function render_completion_panel( WC_Order $order, $settings, $state, $line_items, $pdf_url ) {
		$flow_pdf_url = self::get_pdf_url( $order, true, true );
		$final_pdf    = self::get_final_customization_pdf_record( $order, $state, true, true );
		?>
		<p class="eop-post-flow__text"><?php echo esc_html( $settings['post_confirmation_completion_description'] ); ?></p>
		<p class="eop-post-flow__helper"><?php esc_html_e( 'Seu contrato, os dados do pedido, o anexo e a personalizacao dos produtos ja foram consolidados para a equipe.', EOP_TEXT_DOMAIN ); ?></p>
		<?php if ( ! empty( $line_items ) ) : ?>
			<div class="eop-post-flow__completion-grid">
				<?php foreach ( $line_items as $line_item ) : ?>
					<?php
					$item        = $line_item['item'];
					$product     = $line_item['product'];
					$image_url   = $product ? wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) : '';
					$custom_name = self::get_item_custom_name( $item, $state );

					if ( ! $image_url ) {
						$image_url = wc_placeholder_img_src( 'thumbnail' );
					}
					?>
					<div class="eop-post-flow__completion-item">
						<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $item->get_name() ); ?>" />
						<div>
							<strong><?php echo esc_html( $item->get_name() ); ?></strong>
							<p><?php echo esc_html( $custom_name ? $custom_name : $item->get_name() ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<div class="eop-post-flow__actions">
			<?php if ( ! empty( $final_pdf['public_download_url'] ) ) : ?>
				<a class="eop-proposal-button" href="<?php echo esc_url( $final_pdf['public_download_url'] ); ?>" download="<?php echo esc_attr( $final_pdf['filename'] ); ?>"><?php esc_html_e( 'Baixar PDF final da personalizacao', EOP_TEXT_DOMAIN ); ?></a>
			<?php endif; ?>
			<?php if ( $flow_pdf_url ) : ?>
				<a class="eop-proposal-button eop-proposal-button--secondary" href="<?php echo esc_url( $flow_pdf_url ); ?>" download="<?php echo esc_attr( self::get_pdf_filename( $order ) ); ?>"><?php esc_html_e( 'Baixar PDF complementar', EOP_TEXT_DOMAIN ); ?></a>
			<?php endif; ?>
			<?php if ( $pdf_url ) : ?>
				<a class="eop-proposal-button eop-proposal-button--secondary" href="<?php echo esc_url( $pdf_url ); ?>" download="documento-<?php echo esc_attr( time() ); ?>.pdf"><?php esc_html_e( 'Baixar PDF da proposta', EOP_TEXT_DOMAIN ); ?></a>
			<?php endif; ?>
			<a class="eop-proposal-button eop-proposal-button--secondary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Voltar para o site', EOP_TEXT_DOMAIN ); ?></a>
		</div>
		<?php
	}

	private static function process_contract_submission( WC_Order $order ) {
		$accept = isset( $_POST['eop_contract_accept'] ) ? sanitize_text_field( wp_unslash( $_POST['eop_contract_accept'] ) ) : '';

		if ( 'yes' !== $accept ) {
			return 'missing_data';
		}

		$state                       = self::get_state( $order );
		$state['contract']['accepted']      = true;
		$state['contract']['accepted_name'] = '';
		$state['contract']['accepted_at']   = current_time( 'mysql' );
		$state['contract']['accepted_ip']   = self::get_client_ip();
		$state['contract']['contract_text'] = self::build_contract_documents_snapshot( $order );

		self::persist_state( $order, $state );
		$order->add_order_note( __( 'Aceite contratual registrado.', EOP_TEXT_DOMAIN ) );
		$order->save();

		return 'contract_saved';
	}

	private static function process_upload_submission( WC_Order $order ) {
		return self::process_final_step_submission( $order, true );
	}

	private static function process_products_submission( WC_Order $order ) {
		return self::process_final_step_submission( $order, false );
	}

	private static function process_final_step_submission( WC_Order $order, $attachment_required_if_missing ) {
		$state          = self::get_state( $order );
		$has_attachment = ! empty( $state['attachment']['id'] );

		if ( $attachment_required_if_missing && ! $has_attachment && empty( $_FILES['eop_post_confirmation_attachment']['name'] ) ) {
			return 'missing_file';
		}

		$upload_result = self::maybe_store_final_step_attachment( $state );

		if ( ! empty( $upload_result['error'] ) ) {
			return (string) $upload_result['error'];
		}

		$state = $upload_result['state'];
		$product_result = self::save_final_step_product_names( $order, $state );

		if ( ! empty( $product_result['error'] ) ) {
			self::persist_state( $order, $state );
			return (string) $product_result['error'];
		}

		$state = $product_result['state'];
		$stage = self::persist_state( $order, $state );

		if ( 'completed' === $stage ) {
			self::ensure_final_customization_pdf_generated( $order, true );
			return 'flow_completed';
		}

		return ! empty( $upload_result['uploaded'] ) ? 'upload_saved' : 'products_saved';
	}

	private static function maybe_store_final_step_attachment( $state ) {
		if ( empty( $_FILES['eop_post_confirmation_attachment']['name'] ) ) {
			return array(
				'state'    => $state,
				'uploaded' => false,
				'error'    => '',
			);
		}

		$attachment_id = self::store_upload( $_FILES['eop_post_confirmation_attachment'] );

		if ( is_wp_error( $attachment_id ) ) {
			return array(
				'state'    => $state,
				'uploaded' => false,
				'error'    => self::get_upload_notice_from_error( $attachment_id ),
			);
		}

		$state['attachment']['id']          = $attachment_id;
		$state['attachment']['filename']    = get_the_title( $attachment_id );
		$state['attachment']['uploaded_at'] = current_time( 'mysql' );

		return array(
			'state'    => $state,
			'uploaded' => true,
			'error'    => '',
		);
	}

	private static function save_final_step_product_names( WC_Order $order, $state ) {
		$values  = isset( $_POST['eop_product_name'] ) ? wp_unslash( $_POST['eop_product_name'] ) : array();
		$values  = is_array( $values ) ? $values : array();
		$updates = array();

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();
			$item_id = $item->get_id();
			$locked  = self::is_product_locked( $product );
			$sku     = $product ? (string) $product->get_sku() : '';

			if ( self::is_service_product( $product ) ) {
				continue;
			}

			if ( $locked ) {
				$updates[] = array(
					'item'          => $item,
					'item_id'       => $item_id,
					'locked'        => true,
					'original_name' => $item->get_name(),
					'sku'           => $sku,
					'custom_name'   => '',
				);
				continue;
			}

			$value = isset( $values[ $item_id ] ) ? sanitize_text_field( $values[ $item_id ] ) : '';

			if ( '' === $value ) {
				return array(
					'state' => $state,
					'error' => 'missing_data',
				);
			}

			$updates[] = array(
				'item'          => $item,
				'item_id'       => $item_id,
				'locked'        => false,
				'original_name' => $item->get_name(),
				'sku'           => $sku,
				'custom_name'   => $value,
			);
		}

		foreach ( $updates as $update ) {
			/** @var WC_Order_Item_Product $item */
			$item = $update['item'];
			$state['products'][ $update['item_id'] ] = array(
				'locked'        => $update['locked'],
				'original_name' => $update['original_name'],
				'sku'           => $update['sku'],
				'custom_name'   => $update['custom_name'],
			);

			$item->update_meta_data( '_eop_original_product_snapshot', $update['original_name'] );
			$item->update_meta_data( '_eop_custom_name_locked', $update['locked'] ? 'yes' : 'no' );

			if ( $update['locked'] ) {
				$item->delete_meta_data( '_eop_custom_product_name' );
			} else {
				$item->update_meta_data( '_eop_custom_product_name', $update['custom_name'] );
			}

			$item->save();
		}

		return array(
			'state' => $state,
			'error' => '',
		);
	}

	private static function persist_state( WC_Order $order, $state ) {
		$stage                  = self::get_current_stage( $order, $state );
		$state['current_stage'] = $stage;

		if ( 'completed' === $stage && empty( $state['completed_at'] ) ) {
			$state['completed_at'] = current_time( 'mysql' );
		}

		$order->update_meta_data( self::META_KEY, wp_json_encode( $state ) );
		$order->update_meta_data( self::META_FLAG, 'completed' === $stage ? 'yes' : 'no' );
		$order->update_meta_data( '_eop_post_confirmation_flow_stage', $stage );

		if ( ! empty( $state['contract']['accepted_at'] ) ) {
			$order->update_meta_data( '_eop_post_confirmation_contract_accepted_at', $state['contract']['accepted_at'] );
		}

		if ( ! empty( $state['contract']['accepted_name'] ) ) {
			$order->update_meta_data( '_eop_post_confirmation_contract_accepted_name', $state['contract']['accepted_name'] );
		}

		if ( ! empty( $state['contract']['accepted_ip'] ) ) {
			$order->update_meta_data( '_eop_post_confirmation_contract_accepted_ip', $state['contract']['accepted_ip'] );
		}

		if ( ! empty( $state['contract']['contract_text'] ) ) {
			$order->update_meta_data( '_eop_post_confirmation_contract_text', wp_kses_post( $state['contract']['contract_text'] ) );
		}

		if ( ! empty( $state['attachment']['id'] ) ) {
			$order->update_meta_data( '_eop_post_confirmation_attachment_id', absint( $state['attachment']['id'] ) );
		}

		if ( ! empty( $state['final_pdf']['attachment_id'] ) ) {
			$order->update_meta_data( '_eop_post_confirmation_final_pdf_attachment_id', absint( $state['final_pdf']['attachment_id'] ) );
		}

		if ( ! empty( $state['final_pdf']['generated_at'] ) ) {
			$order->update_meta_data( '_eop_post_confirmation_final_pdf_generated_at', sanitize_text_field( (string) $state['final_pdf']['generated_at'] ) );
		}

		$order->update_meta_data(
			self::EXPORT_PAYLOAD_META_KEY,
			wp_json_encode(
				self::build_structured_export_payload(
					$order,
					'integration',
					array(
						'state'            => $state,
						'ensure_generated' => false,
					)
				)
			)
		);

		$order->save();

		return $stage;
	}

	private static function redirect_with_notice( WC_Order $order, $notice ) {
		$redirect = add_query_arg( 'eop_flow_notice', sanitize_key( $notice ), EOP_Public_Proposal::get_public_link( $order ) );

		wp_safe_redirect( $redirect );
		exit;
	}

	private static function order_requires_payment( WC_Order $order ) {
		return 'yes' === EOP_Settings::get( 'enable_checkout_confirmation', 'no' ) && method_exists( $order, 'needs_payment' ) && $order->needs_payment();
	}

	private static function requires_attachment() {
		return 'yes' === EOP_Settings::get( 'post_confirmation_require_attachment', 'yes' );
	}

	private static function requires_product_customization( WC_Order $order ) {
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();

			if ( ! self::is_service_product( $product ) && ! self::is_product_locked( $product ) ) {
				return true;
			}
		}

		return false;
	}

	private static function product_customization_is_complete( WC_Order $order, $state ) {
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			/** @var WC_Order_Item_Product $item */
			$product = $item->get_product();

			if ( self::is_service_product( $product ) || self::is_product_locked( $product ) ) {
				continue;
			}

			if ( '' === trim( self::get_item_custom_name( $item, $state ) ) ) {
				return false;
			}
		}

		return true;
	}

	private static function get_item_custom_name( WC_Order_Item_Product $item, $state ) {
		$item_id = $item->get_id();
		$value   = (string) $item->get_meta( '_eop_custom_product_name', true );

		if ( '' !== $value ) {
			return $value;
		}

		return (string) ( $state['products'][ $item_id ]['custom_name'] ?? '' );
	}

	private static function get_item_customizations( WC_Order $order, $state ) {
		$rows = array();

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();
			if ( self::is_service_product( $product ) ) {
				continue;
			}

			$rows[]  = array(
				'original_name' => (string) $item->get_meta( '_eop_original_product_snapshot', true ) ?: $item->get_name(),
				'custom_name'   => self::get_item_custom_name( $item, $state ),
				'locked'        => 'yes' === (string) $item->get_meta( '_eop_custom_name_locked', true ),
				'sku'           => $product ? (string) $product->get_sku() : '',
				'quantity'      => (int) $item->get_quantity(),
			);
		}

		return $rows;
	}

	private static function get_export_products_payload( WC_Order $order, $state ) {
		$rows = array();

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product       = $item->get_product();
			if ( self::is_service_product( $product ) ) {
				continue;
			}

			$image_id      = $product ? $product->get_image_id() : 0;
			$rows[] = array(
				'item_id'       => $item->get_id(),
				'product_id'    => $item->get_product_id(),
				'variation_id'  => $item->get_variation_id(),
				'quantity'      => $item->get_quantity(),
				'original_name' => (string) $item->get_meta( '_eop_original_product_snapshot', true ) ?: $item->get_name(),
				'custom_name'   => self::get_item_custom_name( $item, $state ),
				'locked'        => 'yes' === (string) $item->get_meta( '_eop_custom_name_locked', true ),
				'sku'           => $product ? (string) $product->get_sku() : '',
				'image'         => $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' ),
			);
		}

		return $rows;
	}

	private static function build_structured_export_payload( WC_Order $order, $context = 'integration', $parts = array() ) {
		$context             = in_array( $context, array( 'admin', 'internal', 'integration' ), true ) ? $context : 'integration';
		$parts               = is_array( $parts ) ? $parts : array();
		$state               = is_array( $parts['state'] ?? null ) ? $parts['state'] : self::get_state( $order );
		$ensure_generated    = ! array_key_exists( 'ensure_generated', $parts ) || (bool) $parts['ensure_generated'];
		$status              = is_array( $parts['status'] ?? null ) ? $parts['status'] : array();
		$order_data_rows     = is_array( $parts['order_data_rows'] ?? null ) ? $parts['order_data_rows'] : self::get_order_data_rows( $order );
		$products_payload    = is_array( $parts['products_payload'] ?? null ) ? $parts['products_payload'] : self::get_export_products_payload( $order, $state );
		$stage               = isset( $status['current_stage'] ) ? sanitize_key( (string) $status['current_stage'] ) : self::get_current_stage( $order, $state );
		$attachment_id       = isset( $parts['attachment_id'] ) ? absint( $parts['attachment_id'] ) : absint( $state['attachment']['id'] ?? 0 );
		$attachment_url      = array_key_exists( 'attachment_url', $parts ) ? (string) $parts['attachment_url'] : ( $attachment_id ? wp_get_attachment_url( $attachment_id ) : '' );
		$signature_documents = is_array( $parts['signature_documents'] ?? null ) ? $parts['signature_documents'] : self::get_signature_documents( $order, $state, true, $ensure_generated );
		$final_pdf           = is_array( $parts['final_pdf'] ?? null ) ? $parts['final_pdf'] : self::get_final_customization_pdf_record( $order, $state, true, $ensure_generated && 'completed' === $stage );
		$contract_text       = array_key_exists( 'contract_text', $parts ) ? (string) $parts['contract_text'] : self::get_contract_text( $order, $state );

		if ( empty( $status ) ) {
			$status = array(
				'current_stage'       => $stage,
				'current_stage_label' => self::get_stage_label( $stage ),
				'completed'           => 'completed' === $stage,
				'completed_at'        => (string) ( $state['completed_at'] ?? '' ),
				'saved_stage'         => (string) ( $state['current_stage'] ?? '' ),
			);
		}

		$summary = is_array( $parts['summary'] ?? null ) ? $parts['summary'] : array(
			'order_data_total'           => count( $order_data_rows ),
			'order_data_filled'          => count( array_filter( wp_list_pluck( $order_data_rows, 'filled' ) ) ),
			'documents_total'            => count( $order_data_rows ),
			'documents_completed'        => count( array_filter( wp_list_pluck( $order_data_rows, 'filled' ) ) ),
			'signature_documents_total'  => count( $signature_documents ),
			'signature_documents_ready'  => count( array_filter( wp_list_pluck( $signature_documents, 'attachment_id' ) ) ),
			'attachment_required'        => self::requires_attachment(),
			'attachment_uploaded'        => $attachment_id > 0,
			'products_editable'          => count( array_filter( $products_payload, static function ( $row ) {
				return empty( $row['locked'] );
			} ) ),
			'products_locked'            => count( array_filter( $products_payload, static function ( $row ) {
				return ! empty( $row['locked'] );
			} ) ),
			'products_completed'         => count( array_filter( $products_payload, static function ( $row ) {
				return empty( $row['locked'] ) && '' !== trim( (string) ( $row['custom_name'] ?? '' ) );
			} ) ),
			'final_pdf_ready'            => ! empty( $final_pdf['attachment_id'] ),
		);

		return array(
			'schema_name'    => self::EXPORT_SCHEMA_NAME,
			'schema_version' => self::EXPORT_SCHEMA_VERSION,
			'context'        => $context,
			'generated_at'   => current_time( 'mysql' ),
			'order'          => array(
				'id'       => $order->get_id(),
				'number'   => $order->get_order_number(),
				'status'   => $order->get_status(),
				'currency' => (string) $order->get_currency(),
				'total'    => (float) $order->get_total(),
				'customer' => array(
					'name'     => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					'email'    => (string) $order->get_billing_email(),
					'document' => self::get_order_customer_document( $order ),
				),
				'flags'    => array(
					'enabled'            => self::is_enabled(),
					'active_for_order'   => self::is_enabled_for_order( $order ),
					'is_proposal'        => 'yes' === (string) $order->get_meta( '_eop_is_proposal', true ),
					'proposal_confirmed' => 'yes' === (string) $order->get_meta( '_eop_proposal_confirmed', true ),
				),
			),
			'snapshots'      => array(
				'contract'   => array(
					'accepted'      => ! empty( $state['contract']['accepted'] ),
					'accepted_name' => (string) ( $state['contract']['accepted_name'] ?? '' ),
					'accepted_at'   => (string) ( $state['contract']['accepted_at'] ?? '' ),
					'text_snapshot' => trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $contract_text ) ) ),
				),
				'order_data' => $order_data_rows,
				'items'      => array_map(
					static function ( $item ) {
						return array(
							'item_id'       => absint( $item['item_id'] ?? 0 ),
							'product_id'    => absint( $item['product_id'] ?? 0 ),
							'variation_id'  => absint( $item['variation_id'] ?? 0 ),
							'quantity'      => absint( $item['quantity'] ?? 0 ),
							'original_name' => (string) ( $item['original_name'] ?? '' ),
							'custom_name'   => (string) ( $item['custom_name'] ?? '' ),
							'locked'        => ! empty( $item['locked'] ),
							'sku'           => (string) ( $item['sku'] ?? '' ),
							'image_url'     => (string) ( $item['image'] ?? '' ),
						);
					},
					$products_payload
				),
			),
			'derived'        => array(
				'status'  => $status,
				'summary' => $summary,
			),
			'references'     => array(
				'public_url'       => EOP_Public_Proposal::get_public_link( $order ),
				'brand_attachment' => array(
					'attachment_id' => $attachment_id,
					'filename'      => (string) ( $state['attachment']['filename'] ?? '' ),
					'uploaded_at'   => (string) ( $state['attachment']['uploaded_at'] ?? '' ),
					'mime_type'     => $attachment_id ? (string) get_post_mime_type( $attachment_id ) : '',
					'file_url'      => $attachment_url ? (string) $attachment_url : '',
				),
				'contract_pdf'      => array(
					'admin_download_url'  => self::is_enabled_for_order( $order ) ? self::get_pdf_url( $order, true, false ) : '',
					'public_download_url' => self::is_enabled_for_order( $order ) ? self::get_pdf_url( $order, true, true ) : '',
				),
				'signature_documents' => array_map(
					static function ( $document ) {
						return array(
							'key'                 => sanitize_key( (string) ( $document['key'] ?? '' ) ),
							'title'               => (string) ( $document['title'] ?? '' ),
							'description'         => (string) ( $document['description'] ?? '' ),
							'source_type'         => (string) ( $document['source_type'] ?? '' ),
							'attachment_id'       => absint( $document['attachment_id'] ?? 0 ),
							'filename'            => (string) ( $document['filename'] ?? '' ),
							'button_label'        => (string) ( $document['button_label'] ?? '' ),
							'view_label'          => (string) ( $document['view_label'] ?? '' ),
							'admin_view_url'      => (string) ( $document['admin_view_url'] ?? '' ),
							'admin_download_url'  => (string) ( $document['admin_download_url'] ?? '' ),
							'public_view_url'     => (string) ( $document['public_view_url'] ?? '' ),
							'public_download_url' => (string) ( $document['public_download_url'] ?? '' ),
						);
					},
					$signature_documents
				),
				'final_customization_pdf' => array(
					'attachment_id'       => absint( $final_pdf['attachment_id'] ?? 0 ),
					'filename'            => (string) ( $final_pdf['filename'] ?? '' ),
					'generated_at'        => (string) ( $final_pdf['generated_at'] ?? '' ),
					'admin_download_url'  => (string) ( $final_pdf['admin_download_url'] ?? '' ),
					'public_download_url' => (string) ( $final_pdf['public_download_url'] ?? '' ),
				),
			),
		);
	}

	private static function get_progress_steps( WC_Order $order, $state ) {
		$current_stage = self::get_current_stage( $order, $state );
		$steps         = array();

		if ( self::order_requires_payment( $order ) || 'payment' === $current_stage ) {
			$steps[] = array(
				'key'         => 'payment',
				'label'       => self::get_stage_label( 'payment' ),
				'description' => __( 'Pagamento para liberar o restante do fluxo.', EOP_TEXT_DOMAIN ),
			);
		}

		$steps[] = array(
			'key'         => 'contract',
			'label'       => self::get_stage_label( 'contract' ),
			'description' => __( 'Aceite do contrato e identificacao do cliente.', EOP_TEXT_DOMAIN ),
		);

		if ( self::requires_attachment() || 'upload' === $current_stage ) {
			$steps[] = array(
				'key'         => 'upload',
				'label'       => self::get_stage_label( 'upload' ),
				'description' => __( 'Envio do arquivo solicitado pela equipe.', EOP_TEXT_DOMAIN ),
			);
		}

		// if ( self::requires_product_customization( $order ) || 'products' === $current_stage ) {
		// 	$steps[] = array(
		// 		'key'         => 'products',
		// 		'label'       => self::get_stage_label( 'products' ),
		// 		'description' => __( 'Definicao do nome desejado para os itens liberados.', EOP_TEXT_DOMAIN ),
		// 	);
		// }

		$steps[] = array(
			'key'         => 'completed',
			'label'       => self::get_stage_label( 'completed' ),
			'description' => __( 'Resumo final, documentos e proximo repasse interno.', EOP_TEXT_DOMAIN ),
		);

		$reached_current = false;

		foreach ( $steps as $index => $step ) {
			$status = 'upcoming';

			if ( 'completed' === $current_stage ) {
				$status = 'completed' === $step['key'] ? 'current' : 'completed';
			} elseif ( $step['key'] === $current_stage ) {
				$status          = 'current';
				$reached_current = true;
			} elseif ( ! $reached_current ) {
				$status = 'completed';
			}

			$steps[ $index ]['status'] = $status;
		}

		return $steps;
	}

	private static function get_status_cards( WC_Order $order, $state ) {
		$order_data_rows = self::get_order_data_rows( $order );
		$documents_total = count( $order_data_rows );
		$documents_done  = count( array_filter( wp_list_pluck( $order_data_rows, 'filled' ) ) );
		$product_counts  = self::get_product_completion_counts( $order, $state );
		$attachment_id   = absint( $state['attachment']['id'] ?? 0 );

		return array(
			array(
				'label'       => __( 'Contrato', EOP_TEXT_DOMAIN ),
				'value'       => ! empty( $state['contract']['accepted'] ) ? __( 'Registrado', EOP_TEXT_DOMAIN ) : __( 'Pendente', EOP_TEXT_DOMAIN ),
				'description' => ! empty( $state['contract']['accepted_name'] ) ? (string) $state['contract']['accepted_name'] : __( 'Aguardando aceite do cliente.', EOP_TEXT_DOMAIN ),
			),
			array(
				'label'       => __( 'Dados do pedido', EOP_TEXT_DOMAIN ),
				'value'       => $documents_total > 0 ? $documents_done . '/' . $documents_total : __( 'Nao se aplica', EOP_TEXT_DOMAIN ),
				'description' => $documents_total > 0 ? __( 'Dados aproveitados diretamente do cadastro do pedido.', EOP_TEXT_DOMAIN ) : __( 'Nenhum dado preenchido no pedido ate agora.', EOP_TEXT_DOMAIN ),
			),
			array(
				'label'       => __( 'Anexo', EOP_TEXT_DOMAIN ),
				'value'       => $attachment_id ? __( 'Enviado', EOP_TEXT_DOMAIN ) : ( self::requires_attachment() ? __( 'Pendente', EOP_TEXT_DOMAIN ) : __( 'Opcional', EOP_TEXT_DOMAIN ) ),
				'description' => $attachment_id ? (string) ( $state['attachment']['filename'] ?? __( 'Arquivo registrado.', EOP_TEXT_DOMAIN ) ) : __( 'Status do arquivo solicitado.', EOP_TEXT_DOMAIN ),
			),
			array(
				'label'       => __( 'Produtos', EOP_TEXT_DOMAIN ),
				'value'       => $product_counts['completed'] . '/' . $product_counts['editable'],
				'description' => $product_counts['locked'] > 0 ? sprintf( __( '%d item(ns) bloqueado(s) para edicao.', EOP_TEXT_DOMAIN ), $product_counts['locked'] ) : __( 'Todos os itens podem ser personalizados.', EOP_TEXT_DOMAIN ),
			),
		);
	}

	private static function get_product_completion_counts( WC_Order $order, $state ) {
		$editable = 0;
		$locked   = 0;
		$done     = 0;

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();

			if ( self::is_service_product( $product ) ) {
				continue;
			}

			if ( self::is_product_locked( $product ) ) {
				$locked++;
				continue;
			}

			$editable++;

			if ( '' !== trim( self::get_item_custom_name( $item, $state ) ) ) {
				$done++;
			}
		}

		return array(
			'editable'  => $editable,
			'locked'    => $locked,
			'completed' => $done,
		);
	}

	private static function get_product_completion_counts_from_line_items( $line_items, $state ) {
		$editable = 0;
		$locked   = 0;
		$done     = 0;

		foreach ( $line_items as $line_item ) {
			$item    = $line_item['item'];
			$product = $line_item['product'];

			if ( self::is_service_product( $product ) ) {
				continue;
			}

			if ( self::is_product_locked( $product ) ) {
				$locked++;
				continue;
			}

			$editable++;

			if ( $item instanceof WC_Order_Item_Product && '' !== trim( self::get_item_custom_name( $item, $state ) ) ) {
				$done++;
			}
		}

		return array(
			'editable'  => $editable,
			'locked'    => $locked,
			'completed' => $done,
		);
	}

	private static function has_pdf_payload( $state, WC_Order $order = null ) {
		if ( $order instanceof WC_Order && '' !== trim( wp_strip_all_tags( self::get_contract_text( $order, $state ) ) ) ) {
			return true;
		}

		return ! empty( $state['contract']['accepted'] )
			|| ! empty( array_filter( $state['documents'] ?? array() ) )
			|| ! empty( $state['attachment']['id'] )
			|| ! empty( $state['products'] );
	}

	private static function public_token_matches_order( WC_Order $order, $token ) {
		$expected = (string) $order->get_meta( '_eop_public_token', true );

		return '' !== $expected && hash_equals( $expected, (string) $token );
	}

	private static function get_rest_order( WP_REST_Request $request ) {
		$order_id = absint( $request->get_param( 'order_id' ) );
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order instanceof WC_Order ) {
			return new WP_Error( 'eop_post_confirmation_rest_order_not_found', __( 'Pedido nao encontrado.', EOP_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}

		return $order;
	}

	private static function current_user_can_access_order( WC_Order $order ) {
		if ( ! class_exists( 'EOP_Role' ) || ! EOP_Role::is_vendedor() ) {
			return true;
		}

		$user_id    = get_current_user_id();
		$creator_id = absint( $order->get_meta( '_eop_created_by' ) );

		if ( $creator_id && $creator_id === $user_id ) {
			return true;
		}

		$post = get_post( $order->get_id() );

		return ( $post && (int) $post->post_author === $user_id );
	}

	private static function get_pdf_filename( WC_Order $order ) {
		return sanitize_file_name( 'complemento-proposta-' . $order->get_id() . '.pdf' );
	}

	private static function get_final_customization_pdf_record( WC_Order $order, $state = null, $include_urls = false, $ensure_generated = true ) {
		if ( ! self::is_enabled_for_order( $order ) ) {
			return array();
		}

		$state  = is_array( $state ) ? $state : self::get_state( $order );
		$record = is_array( $state['final_pdf'] ?? null ) ? $state['final_pdf'] : array();

		if ( $ensure_generated && 'completed' === self::get_current_stage( $order, $state ) ) {
			$record = self::ensure_final_customization_pdf_generated( $order );
		}

		if ( empty( $record['attachment_id'] ) ) {
			return array();
		}

		$record['attachment_id'] = absint( $record['attachment_id'] );
		$record['filename']      = (string) ( $record['filename'] ?? '' );
		$record['generated_at']  = (string) ( $record['generated_at'] ?? '' );
		$record['state_hash']    = (string) ( $record['state_hash'] ?? '' );

		if ( ! $include_urls ) {
			return $record;
		}

		$record['admin_view_url']      = self::get_final_customization_pdf_url( $order, false, false );
		$record['admin_download_url']  = self::get_final_customization_pdf_url( $order, true, false );
		$record['public_view_url']     = self::get_final_customization_pdf_url( $order, false, true );
		$record['public_download_url'] = self::get_final_customization_pdf_url( $order, true, true );

		return $record;
	}

	public static function ensure_final_customization_pdf_generated( WC_Order $order, $force = false ) {
		if ( ! self::is_enabled_for_order( $order ) ) {
			return array();
		}

		$state = self::get_state( $order );

		if ( 'completed' !== self::get_current_stage( $order, $state ) ) {
			return array();
		}

		$existing_record = is_array( $state['final_pdf'] ?? null ) ? $state['final_pdf'] : array();

		if ( ! $force && self::final_customization_pdf_record_is_valid( $existing_record, $order, $state ) ) {
			return $existing_record;
		}

		$binary = self::build_final_customization_pdf_binary( $order, $state );

		if ( '' === $binary ) {
			return array();
		}

		$attachment_id = self::store_generated_final_customization_pdf( $order, $binary );

		if ( ! $attachment_id ) {
			return array();
		}

		$record = array(
			'attachment_id' => $attachment_id,
			'filename'      => wp_basename( (string) get_attached_file( $attachment_id ) ),
			'generated_at'  => current_time( 'mysql' ),
			'state_hash'    => self::get_final_customization_pdf_state_hash( $order, $state ),
		);

		$state['final_pdf'] = $record;
		self::persist_state( $order, $state );

		return $record;
	}

	private static function final_customization_pdf_record_is_valid( $record, WC_Order $order, $state ) {
		if ( empty( $record ) || empty( $record['attachment_id'] ) ) {
			return false;
		}

		$attachment_id = absint( $record['attachment_id'] );
		$file_path     = get_attached_file( $attachment_id );
		$mime_type     = self::get_attachment_mime_type( $attachment_id );
		$state_hash    = self::get_final_customization_pdf_state_hash( $order, $state );

		return ! empty( $file_path )
			&& file_exists( $file_path )
			&& 'application/pdf' === $mime_type
			&& (string) ( $record['state_hash'] ?? '' ) === $state_hash;
	}

	private static function get_final_customization_pdf_state_hash( WC_Order $order, $state ) {
		unset( $order );

		return md5(
			wp_json_encode(
				array(
					'attachment' => array(
						'id'          => absint( $state['attachment']['id'] ?? 0 ),
						'filename'    => (string) ( $state['attachment']['filename'] ?? '' ),
						'uploaded_at' => (string) ( $state['attachment']['uploaded_at'] ?? '' ),
					),
					'products'   => is_array( $state['products'] ?? null ) ? $state['products'] : array(),
					'completed_at' => (string) ( $state['completed_at'] ?? '' ),
				)
			)
		);
	}

	private static function get_final_customization_pdf_filename( WC_Order $order ) {
		return sanitize_file_name( 'personalizacao-final-' . $order->get_id() . '.pdf' );
	}

	private static function build_final_customization_pdf_binary( WC_Order $order, $state ) {
		$html = self::get_final_customization_pdf_html( $order, $state );

		if ( '' === $html ) {
			return '';
		}

		$binary = self::maybe_build_dompdf_pdf( $html );

		if ( '' !== $binary ) {
			return $binary;
		}

		return self::maybe_build_headless_pdf( $html, self::get_final_customization_pdf_filename( $order ) );
	}

	private static function get_final_customization_pdf_html( WC_Order $order, $state ) {
		$settings          = EOP_Settings::get_all();
		$font_css          = method_exists( 'EOP_Settings', 'get_font_css_family' ) ? EOP_Settings::get_font_css_family( $settings['font_family'] ?? '' ) : "'Segoe UI', sans-serif";
		$customer_name     = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$order_date        = $order->get_date_created();
		$order_rows        = self::get_item_customizations( $order, $state );
		$attachment_id     = absint( $state['attachment']['id'] ?? 0 );
		$attachment_label  = (string) ( $state['attachment']['filename'] ?? '' );
		$attachment_preview = self::get_final_customization_pdf_attachment_preview( $attachment_id );

		if ( empty( $order_rows ) ) {
			return '';
		}

		ob_start();
		?>
		<!doctype html>
		<html>
		<head>
			<meta charset="utf-8">
			<style>
				@page { size: A4; margin: 16mm; }
				body { margin: 0; color: #172033; background: #ffffff; font-family: <?php echo esc_html( $font_css ); ?>; font-size: 12px; line-height: 1.6; }
				* { box-sizing: border-box; }
				h1, h2, h3, p { margin: 0; }
				.eop-final-pdf__header { padding: 24px; border: 1px solid #dbe3f0; border-radius: 18px; background: #f7f9fc; }
				.eop-final-pdf__eyebrow { display: block; margin-bottom: 10px; font-size: 10px; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; color: #61708d; }
				.eop-final-pdf__title { margin-bottom: 10px; font-size: 28px; line-height: 1.1; color: #11162f; }
				.eop-final-pdf__lead { color: #44506a; }
				.eop-final-pdf__meta { margin-top: 16px; width: 100%; border-collapse: collapse; }
				.eop-final-pdf__meta td { padding: 6px 0; vertical-align: top; }
				.eop-final-pdf__meta td:first-child { width: 150px; color: #61708d; }
				.eop-final-pdf__section { margin-top: 22px; }
				.eop-final-pdf__section-title { margin-bottom: 12px; font-size: 15px; color: #11162f; }
				.eop-final-pdf__upload { padding: 18px; border: 1px solid #dbe3f0; border-radius: 18px; background: #ffffff; }
				.eop-final-pdf__upload img { display: block; max-width: 220px; max-height: 120px; margin-bottom: 12px; }
				.eop-final-pdf__upload-note { color: #61708d; }
				.eop-final-pdf__table { width: 100%; border-collapse: collapse; }
				.eop-final-pdf__table th, .eop-final-pdf__table td { padding: 12px 14px; border: 1px solid #dbe3f0; text-align: left; vertical-align: top; }
				.eop-final-pdf__table th { background: #f7f9fc; color: #11162f; font-size: 11px; letter-spacing: 0.04em; text-transform: uppercase; }
				.eop-final-pdf__table td small { display: block; margin-top: 4px; color: #61708d; }
				.eop-final-pdf__footer { margin-top: 22px; color: #61708d; font-size: 11px; }
			</style>
		</head>
		<body>
			<section class="eop-final-pdf__header">
				<span class="eop-final-pdf__eyebrow"><?php esc_html_e( 'Fluxo complementar concluido', EOP_TEXT_DOMAIN ); ?></span>
				<h1 class="eop-final-pdf__title"><?php esc_html_e( 'Resumo final da personalizacao', EOP_TEXT_DOMAIN ); ?></h1>
				<p class="eop-final-pdf__lead"><?php esc_html_e( 'Documento consolidado com o anexo enviado e a relacao de itens originais e personalizados para a equipe interna.', EOP_TEXT_DOMAIN ); ?></p>
				<table class="eop-final-pdf__meta">
					<tr>
						<td><?php esc_html_e( 'Pedido', EOP_TEXT_DOMAIN ); ?></td>
						<td><?php echo esc_html( $order->get_order_number() ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Cliente', EOP_TEXT_DOMAIN ); ?></td>
						<td><?php echo esc_html( '' !== $customer_name ? $customer_name : __( 'Nao informado', EOP_TEXT_DOMAIN ) ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Data do pedido', EOP_TEXT_DOMAIN ); ?></td>
						<td><?php echo esc_html( $order_date ? wc_format_datetime( $order_date ) : __( 'Nao informada', EOP_TEXT_DOMAIN ) ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Fluxo concluido em', EOP_TEXT_DOMAIN ); ?></td>
						<td><?php echo esc_html( ! empty( $state['completed_at'] ) ? (string) $state['completed_at'] : __( 'Nao informado', EOP_TEXT_DOMAIN ) ); ?></td>
					</tr>
				</table>
			</section>

			<section class="eop-final-pdf__section">
				<h2 class="eop-final-pdf__section-title"><?php esc_html_e( 'Anexo enviado pelo cliente', EOP_TEXT_DOMAIN ); ?></h2>
				<div class="eop-final-pdf__upload">
					<?php if ( 'image' === $attachment_preview['type'] && '' !== $attachment_preview['src'] ) : ?>
						<img src="<?php echo esc_attr( $attachment_preview['src'] ); ?>" alt="<?php echo esc_attr( $attachment_label ); ?>" />
					<?php endif; ?>
					<p><strong><?php echo esc_html( '' !== $attachment_label ? $attachment_label : __( 'Nenhum anexo enviado', EOP_TEXT_DOMAIN ) ); ?></strong></p>
					<?php if ( 'file' === $attachment_preview['type'] ) : ?>
						<p class="eop-final-pdf__upload-note"><?php esc_html_e( 'O arquivo enviado foi registrado no pedido, mas nao pode ser incorporado visualmente neste PDF porque nao e uma imagem.', EOP_TEXT_DOMAIN ); ?></p>
					<?php elseif ( '' === $attachment_label ) : ?>
						<p class="eop-final-pdf__upload-note"><?php esc_html_e( 'Nenhum arquivo complementar foi anexado nesta etapa.', EOP_TEXT_DOMAIN ); ?></p>
					<?php endif; ?>
				</div>
			</section>

			<section class="eop-final-pdf__section">
				<h2 class="eop-final-pdf__section-title"><?php esc_html_e( 'Itens personalizados', EOP_TEXT_DOMAIN ); ?></h2>
				<table class="eop-final-pdf__table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Produto original', EOP_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Nome personalizado', EOP_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Quantidade', EOP_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Status', EOP_TEXT_DOMAIN ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $order_rows as $row ) : ?>
							<tr>
								<td>
									<?php echo esc_html( $row['original_name'] ); ?>
									<?php if ( '' !== $row['sku'] ) : ?>
										<small><?php echo esc_html( sprintf( __( 'SKU: %s', EOP_TEXT_DOMAIN ), $row['sku'] ) ); ?></small>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( '' !== $row['custom_name'] ? $row['custom_name'] : __( 'Nao informado', EOP_TEXT_DOMAIN ) ); ?></td>
								<td><?php echo esc_html( $row['quantity'] ); ?></td>
								<td><?php echo esc_html( $row['locked'] ? __( 'Bloqueado', EOP_TEXT_DOMAIN ) : __( 'Personalizado', EOP_TEXT_DOMAIN ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>

			<p class="eop-final-pdf__footer"><?php esc_html_e( 'Este arquivo foi gerado automaticamente a partir do fluxo complementar salvo no pedido.', EOP_TEXT_DOMAIN ); ?></p>
		</body>
		</html>
		<?php

		return ob_get_clean();
	}

	private static function get_final_customization_pdf_attachment_preview( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id ) {
			return array(
				'type' => '',
				'src'  => '',
			);
		}

		$file_path = get_attached_file( $attachment_id );
		$mime_type = self::get_attachment_mime_type( $attachment_id );

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return array(
				'type' => '',
				'src'  => '',
			);
		}

		if ( 0 !== strpos( (string) $mime_type, 'image/' ) ) {
			return array(
				'type' => 'file',
				'src'  => '',
			);
		}

		$binary = (string) @file_get_contents( $file_path );

		if ( '' === $binary ) {
			return array(
				'type' => 'file',
				'src'  => '',
			);
		}

		return array(
			'type' => 'image',
			'src'  => 'data:' . $mime_type . ';base64,' . base64_encode( $binary ),
		);
	}

	private static function store_generated_final_customization_pdf( WC_Order $order, $binary ) {
		$filename = self::get_final_customization_pdf_filename( $order );
		$upload   = wp_upload_bits( $filename, null, $binary );

		if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
			return 0;
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'application/pdf',
				'post_title'     => __( 'PDF final da personalizacao', EOP_TEXT_DOMAIN ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_parent'    => $order->get_id(),
			),
			$upload['file'],
			$order->get_id()
		);

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		update_attached_file( $attachment_id, $upload['file'] );

		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );

		if ( ! is_wp_error( $metadata ) && is_array( $metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		return absint( $attachment_id );
	}

	private static function build_pdf_binary( WC_Order $order, $state ) {
		$html = self::get_pdf_html( $order, $state );

		if ( '' === $html ) {
			return '';
		}

		$binary = self::maybe_build_dompdf_pdf( $html );

		if ( '' !== $binary ) {
			return $binary;
		}

		return self::maybe_build_headless_pdf( $html, self::get_pdf_filename( $order ) );
	}

	private static function get_pdf_html( WC_Order $order, $state ) {
		unset( $state );

		$settings          = EOP_Settings::get_all();
		$font_css          = method_exists( 'EOP_Settings', 'get_font_css_family' ) ? EOP_Settings::get_font_css_family( $settings['font_family'] ?? '' ) : "'Segoe UI', sans-serif";
		$document_sections = self::get_contract_document_sections( $order );

		if ( empty( $document_sections ) ) {
			return '';
		}

		ob_start();
		?>
		<!doctype html>
		<html>
		<head>
			<meta charset="utf-8">
			<style>
				@page { size: A4; margin: 16mm; }
				body { margin: 0; color: #172033; background: #ffffff; font-family: <?php echo esc_html( $font_css ); ?>; font-size: 13px; line-height: 1.65; }
				* { box-sizing: border-box; }
				h1,h2,h3,p { margin: 0; }
				.eop-flow-pdf-document { page-break-after: always; }
				.eop-flow-pdf-document:last-child { page-break-after: auto; }
				.eop-flow-pdf-document__title { margin: 0 0 16px; font-size: 26px; line-height: 1.15; letter-spacing: -0.03em; color: #11162f; }
				.eop-flow-pdf-document__body { color: #172033; }
				.eop-flow-pdf-document__body h1,
				.eop-flow-pdf-document__body h2,
				.eop-flow-pdf-document__body h3,
				.eop-flow-pdf-document__body h4 { margin: 0 0 12px; color: #11162f; line-height: 1.2; }
				.eop-flow-pdf-document__body p { margin: 0 0 12px; }
				.eop-flow-pdf-document__body ul,
				.eop-flow-pdf-document__body ol { margin: 0 0 12px 20px; padding: 0; }
				.eop-flow-pdf-document__body li { margin-bottom: 6px; }
				.eop-flow-pdf-document__body table { width: 100%; border-collapse: collapse; margin: 0 0 12px; }
				.eop-flow-pdf-document__body th,
				.eop-flow-pdf-document__body td { padding: 8px 10px; border: 1px solid #dbe3f0; text-align: left; vertical-align: top; }
				.eop-flow-pdf-document__body hr { margin: 18px 0; border: 0; border-top: 1px solid #dbe3f0; }
			</style>
		</head>
		<body>
			<?php foreach ( $document_sections as $section ) : ?>
				<section class="eop-flow-pdf-document">
					<?php if ( '' !== $section['title'] ) : ?>
						<h1 class="eop-flow-pdf-document__title"><?php echo esc_html( $section['title'] ); ?></h1>
					<?php endif; ?>
					<div class="eop-flow-pdf-document__body"><?php echo wp_kses_post( wpautop( $section['body'] ) ); ?></div>
				</section>
			<?php endforeach; ?>
		</body>
		</html>
		<?php

		return ob_get_clean();
	}

	private static function maybe_build_dompdf_pdf( $html ) {
		if ( ! self::ensure_reference_dompdf_loaded() ) {
			return '';
		}

		if ( ! class_exists( '\\WPO\\IPS\\Vendor\\Dompdf\\Dompdf' ) || ! class_exists( '\\WPO\\IPS\\Vendor\\Dompdf\\Options' ) ) {
			return '';
		}

		$runtime_paths = self::get_pdf_runtime_paths();

		if ( empty( $runtime_paths['temp'] ) || empty( $runtime_paths['fonts'] ) ) {
			return '';
		}

		try {
			$options = array(
				'tempDir'                 => $runtime_paths['temp'],
				'fontDir'                 => $runtime_paths['fonts'],
				'fontCache'               => $runtime_paths['fonts'],
				'isRemoteEnabled'         => true,
				'isHtml5ParserEnabled'    => true,
				'isPhpEnabled'            => false,
				'defaultFont'             => 'Helvetica',
				'chroot'                  => array_values( array_unique( array_map( 'wp_normalize_path', array_filter( array( WP_CONTENT_DIR, WP_PLUGIN_DIR, EOP_PLUGIN_DIR, $runtime_paths['base'] ) ) ) ) ),
			);

			$dompdf = new \WPO\IPS\Vendor\Dompdf\Dompdf( new \WPO\IPS\Vendor\Dompdf\Options( $options ) );
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();

			return (string) $dompdf->output();
		} catch ( Throwable $throwable ) {
			return '';
		}
	}

	private static function maybe_build_headless_pdf( $html, $filename ) {
		$browser       = self::get_browser_pdf_executable();
		$runtime_paths = self::get_pdf_runtime_paths();

		if ( '' === $browser || empty( $runtime_paths['temp'] ) ) {
			return '';
		}

		$html_file = trailingslashit( $runtime_paths['temp'] ) . wp_generate_uuid4() . '.html';
		$pdf_file  = trailingslashit( $runtime_paths['temp'] ) . sanitize_file_name( $filename );

		if ( false === file_put_contents( $html_file, $html ) ) {
			return '';
		}

		@unlink( $pdf_file );

		$command = sprintf(
			'%s --headless --disable-gpu --run-all-compositor-stages-before-draw --virtual-time-budget=2000 --print-to-pdf=%s --print-to-pdf-no-header %s',
			escapeshellarg( $browser ),
			escapeshellarg( $pdf_file ),
			escapeshellarg( self::path_to_file_url( $html_file ) )
		);

		exec( $command, $output, $status );

		@unlink( $html_file );

		if ( 0 !== (int) $status || ! file_exists( $pdf_file ) ) {
			@unlink( $pdf_file );
			return '';
		}

		$binary = (string) file_get_contents( $pdf_file );
		@unlink( $pdf_file );

		return $binary;
	}

	private static function ensure_reference_dompdf_loaded() {
		if ( class_exists( '\\WPO\\IPS\\Vendor\\Dompdf\\Dompdf' ) && class_exists( '\\WPO\\IPS\\Vendor\\Dompdf\\Options' ) ) {
			return true;
		}

		$plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . 'woocommerce-pdf-invoices-packing-slips/';
		$autoloads  = array(
			$plugin_dir . 'vendor/autoload.php',
			$plugin_dir . 'vendor/strauss/autoload.php',
		);

		foreach ( $autoloads as $autoload ) {
			if ( file_exists( $autoload ) ) {
				require_once $autoload;
			}
		}

		return class_exists( '\\WPO\\IPS\\Vendor\\Dompdf\\Dompdf' ) && class_exists( '\\WPO\\IPS\\Vendor\\Dompdf\\Options' );
	}

	private static function get_pdf_runtime_paths() {
		$upload_dir = wp_upload_dir();

		if ( empty( $upload_dir['basedir'] ) ) {
			return array();
		}

		$base_dir = trailingslashit( $upload_dir['basedir'] ) . 'eop-post-confirmation-pdf';
		$temp_dir = trailingslashit( $base_dir ) . 'tmp';
		$font_dir = trailingslashit( $base_dir ) . 'fonts';

		if ( ! wp_mkdir_p( $temp_dir ) || ! wp_mkdir_p( $font_dir ) ) {
			return array();
		}

		return array(
			'base'  => $base_dir,
			'temp'  => $temp_dir,
			'fonts' => $font_dir,
		);
	}

	private static function get_browser_pdf_executable() {
		$candidates = array(
			'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
			'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
			'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
			'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
			'/usr/bin/google-chrome',
			'/usr/bin/chromium',
			'/usr/bin/chromium-browser',
			'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
		);

		foreach ( $candidates as $candidate ) {
			if ( '' !== $candidate && file_exists( $candidate ) && is_executable( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}

	private static function path_to_file_url( $path ) {
		$normalized = wp_normalize_path( (string) $path );

		if ( preg_match( '/^[A-Za-z]:\//', $normalized ) ) {
			return 'file:///' . str_replace( ' ', '%20', $normalized );
		}

		return 'file://' . str_replace( ' ', '%20', $normalized );
	}

	private static function is_product_locked( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return false;
		}

		$locked = self::get_locked_product_tokens();
		$tokens = array_filter(
			array(
				(string) $product->get_id(),
				(string) $product->get_parent_id(),
				(string) $product->get_sku(),
			)
		);

		foreach ( $tokens as $token ) {
			if ( in_array( strtolower( trim( $token ) ), $locked, true ) ) {
				return true;
			}
		}

		return false;
	}

	private static function is_service_product( $product ) {
		return class_exists( 'EOP_Settings' ) && method_exists( 'EOP_Settings', 'is_service_product' ) && EOP_Settings::is_service_product( $product );
	}

	private static function get_locked_product_tokens() {
		$raw = (string) EOP_Settings::get( 'post_confirmation_locked_products', '' );
		$raw = strtolower( $raw );
		$raw = str_replace( array( "\r", "\n", ';' ), ',', $raw );

		return array_values( array_unique( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) ) );
	}

	private static function get_signature_document_templates() {
		if ( ! class_exists( 'EOP_Settings' ) ) {
			return array();
		}

		if ( method_exists( 'EOP_Settings', 'get_post_confirmation_contract_documents' ) ) {
			$documents = EOP_Settings::get_post_confirmation_contract_documents();
		} elseif ( method_exists( 'EOP_Settings', 'get_post_confirmation_signature_documents' ) ) {
			$documents = EOP_Settings::get_post_confirmation_signature_documents();
		} else {
			return array();
		}

		return array_values(
			array_filter(
				$documents,
				static function ( $document ) {
					if ( ! is_array( $document ) ) {
						return false;
					}

					if ( 'attachment' === ( $document['source_type'] ?? 'editor' ) ) {
						return ! empty( $document['attachment_id'] );
					}

					return '' !== trim( wp_strip_all_tags( (string) ( $document['body'] ?? '' ) ) );
				}
			)
		);
	}

	public static function get_admin_contract_preview_payload( $settings = array() ) {
		$settings  = is_array( $settings ) ? wp_parse_args( $settings, EOP_Settings::get_defaults() ) : EOP_Settings::get_all();
		$documents = EOP_Settings::get_post_confirmation_contract_documents( $settings );
		$primary   = ! empty( $documents ) ? self::get_signature_document_preview_payload( $documents[0] ) : array();

		return array(
			'document_count'       => count( $documents ),
			'additional_documents' => max( 0, count( $documents ) - 1 ),
			'primary_document'     => $primary,
		);
	}

	public static function render_admin_contract_preview_markup( $settings = array() ) {
		$settings = is_array( $settings ) ? wp_parse_args( $settings, EOP_Settings::get_defaults() ) : EOP_Settings::get_all();
		$preview  = self::get_admin_contract_preview_payload( $settings );
		$primary_document = is_array( $preview['primary_document'] ?? null ) ? $preview['primary_document'] : array();
		$contract_title   = trim( (string) ( $settings['post_confirmation_contract_title'] ?? ( $primary_document['title'] ?? '' ) ) );
		$contract_description = trim( (string) ( $settings['post_confirmation_contract_document_description'] ?? ( $primary_document['description'] ?? '' ) ) );
		$brand_name       = class_exists( 'EOP_PDF_Settings' ) ? (string) EOP_PDF_Settings::get( 'shop_name', get_bloginfo( 'name' ) ) : get_bloginfo( 'name' );
		$brand_name       = '' !== trim( $brand_name ) ? $brand_name : get_bloginfo( 'name' );
		$logo_url         = ! empty( $settings['brand_logo_url'] ) ? esc_url_raw( (string) $settings['brand_logo_url'] ) : '';

		if ( '' === $logo_url && class_exists( 'EOP_PDF_Settings' ) ) {
			$logo_url = esc_url_raw( (string) EOP_PDF_Settings::get( 'shop_logo_url', '' ) );
		}

		$summary_label = trim( (string) ( $settings['customer_experience_financial_title'] ?? '' ) );
		$total_label   = trim( (string) ( $settings['customer_experience_total_label'] ?? '' ) );
		$summary_note  = trim( (string) ( $settings['customer_experience_progress_note'] ?? '' ) );

		if ( '' === $summary_label ) {
			$summary_label = __( 'Resumo', EOP_TEXT_DOMAIN );
		}

		if ( '' === $total_label ) {
			$total_label = __( 'Valor aprovado', EOP_TEXT_DOMAIN );
		}

		if ( '' === $summary_note ) {
			$summary_note = __( 'Depois do aceite, as proximas etapas sao liberadas automaticamente.', EOP_TEXT_DOMAIN );
		}

		$summary_rows = array(
			array(
				'label' => __( 'Subtotal', EOP_TEXT_DOMAIN ),
				'value' => 'R$ 382,90',
			),
			array(
				'label' => __( 'Frete', EOP_TEXT_DOMAIN ),
				'value' => 'R$ 0,00',
			),
			array(
				'label'      => __( 'Desconto', EOP_TEXT_DOMAIN ),
				'main_value' => '25%',
				'sub_value'  => '-R$ 95,73',
			),
		);

		ob_start();
		self::render_post_flow_styles( $settings );
		?>
		<div class="eop-post-flow eop-post-flow--stage-contract eop-post-flow--admin-preview">
			<div class="eop-post-flow__contract-header">
				<div class="eop-post-flow__contract-header-main">
					<div class="eop-post-flow__contract-brand">
						<?php if ( '' !== $logo_url ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>">
						<?php else : ?>
							<span class="eop-post-flow__contract-brand-fallback"><?php echo esc_html( strtoupper( substr( $brand_name, 0, 1 ) ) ); ?></span>
						<?php endif; ?>
					</div>
					<div class="eop-post-flow__contract-meta">
						<strong><?php echo esc_html( $brand_name ); ?></strong>
						<span><?php echo esc_html( sprintf( __( 'Pedido #%d', EOP_TEXT_DOMAIN ), 5238 ) ); ?></span>
					</div>
				</div>
			</div>
			<div class="eop-post-flow__layout">
				<div class="eop-post-flow__main">
					<?php if ( ! empty( $primary_document['preview_url'] ) && 'pdf' === ( $primary_document['preview_type'] ?? '' ) ) : ?>
						<?php self::render_contract_document_reader( $contract_title, $contract_description, 'iframe', (string) $primary_document['preview_url'] ); ?>
					<?php elseif ( ! empty( $primary_document['preview_html'] ) ) : ?>
						<?php self::render_contract_document_reader( $contract_title, $contract_description, 'html', (string) $primary_document['preview_html'] ); ?>
					<?php endif; ?>
					<div class="eop-post-flow__acceptance-card">
						<div class="eop-post-flow__form eop-post-flow__form--acceptance">
							<label class="eop-post-flow__checkbox">
								<input type="checkbox" value="yes" />
								<span><?php echo esc_html( $settings['post_confirmation_contract_checkbox_label'] ); ?></span>
							</label>
							<button type="button" class="eop-proposal-button"><?php echo esc_html( $settings['post_confirmation_contract_button_label'] ); ?></button>
						</div>
					</div>
				</div>
				<aside class="eop-post-flow__sidebar">
					<div class="eop-post-flow__panel eop-post-flow__panel--summary">
						<span class="eop-post-flow__panel-label"><?php echo esc_html( $summary_label ); ?></span>
						<div class="eop-post-flow__summary-totals">
							<div class="eop-post-flow__summary-total-row">
								<span><?php echo esc_html( $total_label ); ?></span>
								<strong>R$ 287,18</strong>
							</div>
							<?php foreach ( $summary_rows as $row ) : ?>
								<div class="eop-post-flow__summary-total-row">
									<span><?php echo esc_html( $row['label'] ); ?></span>
									<?php if ( ! empty( $row['main_value'] ) ) : ?>
										<div class="eop-post-flow__summary-value">
											<strong><?php echo esc_html( $row['main_value'] ); ?></strong>
											<small><?php echo esc_html( $row['sub_value'] ); ?></small>
										</div>
									<?php else : ?>
										<strong><?php echo esc_html( $row['value'] ); ?></strong>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
						<p class="eop-post-flow__summary-note"><?php echo esc_html( $summary_note ); ?></p>
					</div>
				</aside>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	private static function render_post_flow_styles( $settings = array() ) {
		$settings             = is_array( $settings ) ? wp_parse_args( $settings, EOP_Settings::get_defaults() ) : EOP_Settings::get_all();
		$experience_accent    = $settings['customer_experience_accent_color'] ?? $settings['primary_color'];
		$experience_text      = $settings['customer_experience_text_color'] ?? $settings['proposal_text_color'];
		$experience_muted     = $settings['customer_experience_muted_color'] ?? $settings['proposal_muted_color'];
		$experience_page      = self::build_background_fill(
			$settings['customer_experience_background_mode'] ?? 'gradient',
			$settings['customer_experience_background_color'] ?? '#edf2fb',
			$settings['customer_experience_background_secondary_color'] ?? '#f7f9fc',
			'180deg'
		);
		$experience_panel     = self::build_background_fill(
			$settings['customer_experience_panel_background_mode'] ?? 'solid',
			$settings['customer_experience_panel_background_color'] ?? $settings['proposal_card_color'],
			$settings['customer_experience_panel_background_secondary_color'] ?? '#f7f9fc',
			'180deg'
		);
		$experience_side      = self::build_background_fill(
			$settings['customer_experience_sidebar_background_mode'] ?? 'solid',
			$settings['customer_experience_sidebar_background_color'] ?? '#f6f8fc',
			$settings['customer_experience_sidebar_background_secondary_color'] ?? '#ffffff',
			'180deg'
		);
		$experience_hero      = self::build_background_fill(
			$settings['customer_experience_hero_background_mode'] ?? 'gradient',
			$settings['customer_experience_hero_background_color'] ?? $settings['primary_color'],
			$settings['customer_experience_hero_background_secondary_color'] ?? '#243553',
			'135deg'
		);
		$button_radius        = max( 14, min( 24, absint( $settings['border_radius'] ?? 18 ) ) );
		$title_size           = max( 24, min( 76, absint( $settings['customer_experience_title_size'] ?? 46 ) ) );
		$text_size            = max( 13, min( 24, absint( $settings['customer_experience_text_size'] ?? 16 ) ) );
		$font_family          = self::format_font_family_css_value( $settings['customer_experience_font_family'] ?? '' );
		$document_title_size  = max( 18, min( 32, (int) round( $title_size * 0.43 ) ) );
		$document_note_size   = max( 12, min( 18, $text_size - 1 ) );
		$summary_label_size   = max( 12, min( 16, $text_size - 3 ) );
		$summary_value_size   = max( 15, min( 22, $text_size ) );
		$summary_note_size    = max( 12, min( 16, $text_size - 4 ) );
		$accent_soft          = self::build_alpha_color( $experience_accent, 0.12, 'rgba(215,138,47,.12)' );
		$accent_border        = self::build_alpha_color( $experience_accent, 0.20, 'rgba(215,138,47,.2)' );
		$accent_shadow        = self::build_alpha_color( $experience_accent, 0.20, 'rgba(215,138,47,.2)' );
		$accent_shadow_soft   = self::build_alpha_color( $experience_accent, 0.08, 'rgba(215,138,47,.08)' );
		$accent_glow          = self::build_alpha_color( $experience_accent, 0.28, 'rgba(215,138,47,.28)' );
		?>
		<style>
			.eop-post-flow {
				--eop-post-flow-accent: <?php echo esc_html( $experience_accent ); ?>;
				--eop-post-flow-accent-soft: <?php echo esc_html( $accent_soft ); ?>;
				--eop-post-flow-accent-border: <?php echo esc_html( $accent_border ); ?>;
				--eop-post-flow-accent-shadow: <?php echo esc_html( $accent_shadow ); ?>;
				--eop-post-flow-accent-shadow-soft: <?php echo esc_html( $accent_shadow_soft ); ?>;
				--eop-post-flow-accent-glow: <?php echo esc_html( $accent_glow ); ?>;
				--eop-post-flow-text: <?php echo esc_html( $experience_text ); ?>;
				--eop-post-flow-muted: <?php echo esc_html( $experience_muted ); ?>;
				--eop-post-flow-page-bg: <?php echo esc_html( $experience_page ); ?>;
				--eop-post-flow-panel-bg: <?php echo esc_html( $experience_panel ); ?>;
				--eop-post-flow-side-bg: <?php echo esc_html( $experience_side ); ?>;
				--eop-post-flow-hero-bg: <?php echo esc_html( $experience_hero ); ?>;
				--eop-post-flow-font-family: <?php echo esc_html( $font_family ); ?>;
				--eop-post-flow-title-size: <?php echo esc_html( $title_size ); ?>px;
				--eop-post-flow-text-size: <?php echo esc_html( $text_size ); ?>px;
				--eop-post-flow-button-radius: <?php echo esc_html( $button_radius ); ?>px;
				--eop-post-flow-document-title-size: <?php echo esc_html( $document_title_size ); ?>px;
				--eop-post-flow-document-note-size: <?php echo esc_html( $document_note_size ); ?>px;
				--eop-post-flow-summary-label-size: <?php echo esc_html( $summary_label_size ); ?>px;
				--eop-post-flow-summary-value-size: <?php echo esc_html( $summary_value_size ); ?>px;
				--eop-post-flow-summary-note-size: <?php echo esc_html( $summary_note_size ); ?>px;
			}
			<?php echo self::get_post_flow_custom_style_rules( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</style>
		<?php
	}

	private static function get_post_flow_custom_style_rules( $settings ) {
		if ( ! class_exists( 'EOP_Settings' ) || ! method_exists( 'EOP_Settings', 'get_post_confirmation_upload_products_style_sections' ) ) {
			return '';
		}

		$sections = EOP_Settings::get_post_confirmation_upload_products_style_sections();
		$rules    = array();

		foreach ( $sections as $section ) {
			foreach ( (array) ( $section['fields'] ?? array() ) as $field_key => $field ) {
				if ( empty( $field['css'] ) ) {
					continue;
				}

				$value = $settings[ $field_key ] ?? ( $field['default'] ?? '' );
				$value = self::format_post_flow_custom_style_value( $value, $field );

				if ( '' === $value ) {
					continue;
				}

				foreach ( (array) $field['css'] as $css_map ) {
					$selector = trim( (string) ( $css_map['selector'] ?? '' ) );
					$property = trim( (string) ( $css_map['property'] ?? '' ) );

					if ( '' === $selector || '' === $property ) {
						continue;
					}

					if ( ! isset( $rules[ $selector ] ) ) {
						$rules[ $selector ] = array();
					}

					$rules[ $selector ][ $property ] = $value;
				}
			}
		}

		if ( empty( $rules ) ) {
			return '';
		}

		$output = '';

		foreach ( $rules as $selector => $declarations ) {
			if ( empty( $declarations ) ) {
				continue;
			}

			$output .= $selector . '{';

			foreach ( $declarations as $property => $value ) {
				$output .= $property . ':' . $value . ';';
			}

			$output .= '}';
		}

		return $output;
	}

	private static function format_post_flow_custom_style_value( $value, $field ) {
		$type  = (string) ( $field['type'] ?? 'text' );
		$value = is_string( $value ) ? trim( $value ) : (string) $value;

		if ( '' === $value ) {
			return '';
		}

		if ( 'font' === $type ) {
			return self::format_font_family_css_value( $value );
		}

		if ( 'number' === $type ) {
			$unit = (string) ( $field['unit'] ?? '' );
			return '' !== $unit ? absint( $value ) . $unit : (string) absint( $value );
		}

		return preg_replace( '/[^a-zA-Z0-9#.%\s,\-():"_\/]/', '', $value );
	}

	private static function format_font_family_css_value( $font_family ) {
		$font_family = is_string( $font_family ) ? trim( $font_family ) : '';

		if ( '' === $font_family ) {
			return '"Montserrat", sans-serif';
		}

		$family = preg_replace( '/:.+$/', '', $font_family );
		$family = trim( str_replace( '+', ' ', (string) $family ) );
		$family = preg_replace( '/[^a-zA-Z0-9\s\-_,.]/', '', $family );
		$family = trim( (string) $family, " \t\n\r\0\x0B," );

		if ( '' === $family ) {
			return '"Montserrat", sans-serif';
		}

		$fallback = false !== stripos( $family, 'serif' ) ? 'serif' : 'sans-serif';

		return sprintf( '"%s", %s', $family, $fallback );
	}

	private static function build_alpha_color( $color, $alpha, $fallback = '' ) {
		$color = is_string( $color ) ? trim( $color ) : '';
		$hex   = sanitize_hex_color( $color );

		if ( ! $hex ) {
			return (string) $fallback;
		}

		$hex = ltrim( $hex, '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$alpha = max( 0, min( 1, (float) $alpha ) );
		$r     = hexdec( substr( $hex, 0, 2 ) );
		$g     = hexdec( substr( $hex, 2, 2 ) );
		$b     = hexdec( substr( $hex, 4, 2 ) );
		$a     = rtrim( rtrim( number_format( $alpha, 2, '.', '' ), '0' ), '.' );

		if ( '' === $a ) {
			$a = '0';
		}

		return sprintf( 'rgba(%1$d,%2$d,%3$d,%4$s)', $r, $g, $b, $a );
	}

	private static function build_background_fill( $mode, $primary, $secondary, $angle = '180deg' ) {
		$mode      = in_array( $mode, array( 'solid', 'gradient' ), true ) ? $mode : 'solid';
		$primary   = is_string( $primary ) && '' !== trim( $primary ) ? trim( $primary ) : '#ffffff';
		$secondary = is_string( $secondary ) && '' !== trim( $secondary ) ? trim( $secondary ) : $primary;
		$angle     = is_string( $angle ) && '' !== trim( $angle ) ? trim( $angle ) : '180deg';

		if ( 'gradient' === $mode ) {
			return sprintf( 'linear-gradient(%1$s,%2$s 0%%,%3$s 100%%)', $angle, $primary, $secondary );
		}

		return $primary;
	}

	public static function render_admin_upload_products_preview_markup( $settings = array() ) {
		$srcdoc = self::get_admin_upload_products_preview_srcdoc( $settings );

		ob_start();
		?>
		<div class="eop-proposal-preview-card" data-eop-proposal-preview-card data-preview-viewport="desktop">
			<div class="eop-proposal-preview-card__copy">
				<div class="eop-proposal-preview-card__eyebrow"><?php esc_html_e( 'Preview ao vivo', EOP_TEXT_DOMAIN ); ?></div>
				<h3><?php esc_html_e( 'Veja a pagina publica antes de salvar', EOP_TEXT_DOMAIN ); ?></h3>
				<p><?php esc_html_e( 'Este preview usa o mesmo renderer do fluxo do cliente, agora isolado em iframe e com dados de exemplo.', EOP_TEXT_DOMAIN ); ?></p>
				<div class="eop-proposal-preview-card__status is-ready" aria-live="polite">
					<span class="eop-proposal-preview-card__status-dot" aria-hidden="true"></span>
					<span class="eop-proposal-preview-card__status-text"><?php esc_html_e( 'Preview pronto em Desktop.', EOP_TEXT_DOMAIN ); ?></span>
				</div>
				<div class="eop-proposal-preview-card__tools">
					<button type="button" class="button eop-proposal-preview-viewport is-active" data-preview-viewport="desktop" aria-pressed="true"><?php esc_html_e( 'Desktop', EOP_TEXT_DOMAIN ); ?></button>
					<button type="button" class="button eop-proposal-preview-viewport" data-preview-viewport="mobile" aria-pressed="false"><?php esc_html_e( 'Mobile', EOP_TEXT_DOMAIN ); ?></button>
				</div>
			</div>
			<div class="eop-proposal-preview-card__stage">
				<div class="eop-proposal-preview-card__shell">
					<iframe class="eop-proposal-preview-render" title="<?php esc_attr_e( 'Preview da pagina do cliente', EOP_TEXT_DOMAIN ); ?>" srcdoc="<?php echo esc_attr( $srcdoc ); ?>"></iframe>
				</div>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	private static function get_signature_document_preview_payload( $template ) {
		$template      = is_array( $template ) ? $template : array();
		$attachment_id = absint( $template['attachment_id'] ?? 0 );
		$mime_type     = $attachment_id ? self::get_attachment_mime_type( $attachment_id ) : '';
		$preview_type  = 'empty';
		$preview_url   = '';
		$preview_html  = '';

		if ( 'attachment' === ( $template['source_type'] ?? 'editor' ) && $attachment_id ) {
			if ( 'application/pdf' === $mime_type ) {
				$preview_type = 'pdf';
				$preview_url  = (string) wp_get_attachment_url( $attachment_id );
			} else {
				$extracted_text = self::extract_text_from_attachment( $attachment_id );

				if ( '' !== $extracted_text ) {
					$preview_type = 'text';
					$preview_html = wpautop( esc_html( $extracted_text ) );
				}
			}
		} elseif ( '' !== trim( wp_strip_all_tags( (string) ( $template['body'] ?? '' ) ) ) ) {
			$preview_type = 'text';
			$preview_html = wpautop( wp_kses_post( (string) $template['body'] ) );
		}

		return array(
			'title'        => (string) ( $template['title'] ?? '' ),
			'description'  => (string) ( $template['description'] ?? '' ),
			'view_label'   => (string) ( $template['view_label'] ?? __( 'Visualizar PDF', EOP_TEXT_DOMAIN ) ),
			'button_label' => (string) ( $template['button_label'] ?? __( 'Baixar PDF', EOP_TEXT_DOMAIN ) ),
			'preview_type' => $preview_type,
			'preview_url'  => $preview_url,
			'preview_html' => $preview_html,
		);
	}

	private static function get_signature_documents( WC_Order $order, $state = null, $include_urls = false, $ensure_generated = true ) {
		$state = is_array( $state ) ? $state : self::get_state( $order );
		$documents = is_array( $state['signature_documents'] ?? null ) ? $state['signature_documents'] : array();

		if ( $ensure_generated && 'yes' === (string) $order->get_meta( '_eop_proposal_confirmed', true ) ) {
			$documents = self::ensure_signature_documents_generated( $order );
		}

		if ( empty( $documents ) && $include_urls ) {
			$documents = self::get_signature_document_placeholder_records();
		}

		$documents = array_values(
			array_filter(
				$documents,
				static function ( $document ) use ( $include_urls ) {
					return $include_urls || ! empty( $document['attachment_id'] );
				}
			)
		);

		if ( ! $include_urls ) {
			return $documents;
		}

		return array_map(
			function ( $document ) use ( $order ) {
				$document_key = sanitize_key( (string) ( $document['key'] ?? '' ) );

				$document['admin_view_url']     = self::get_signature_document_url( $order, $document_key, false, false );
				$document['admin_download_url'] = self::get_signature_document_url( $order, $document_key, true, false );
				$document['public_view_url']    = self::get_signature_document_url( $order, $document_key, false, true );
				$document['public_download_url'] = self::get_signature_document_url( $order, $document_key, true, true );

				return $document;
			},
			$documents
		);
	}

	private static function get_signature_document_placeholder_records() {
		$templates = self::get_signature_document_templates();

		if ( empty( $templates ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $template ) {
						$record = array(
							'key'          => sanitize_key( (string) ( $template['key'] ?? '' ) ),
							'title'        => (string) ( $template['title'] ?? '' ),
							'description'  => (string) ( $template['description'] ?? '' ),
							'source_type'  => (string) ( $template['source_type'] ?? 'editor' ),
							'button_label' => (string) ( $template['button_label'] ?? __( 'Baixar PDF', EOP_TEXT_DOMAIN ) ),
							'view_label'   => (string) ( $template['view_label'] ?? __( 'Visualizar PDF', EOP_TEXT_DOMAIN ) ),
							'attachment_id' => 0,
							'filename'     => '',
						);

						if ( '' === $record['key'] ) {
							return array();
						}

						if ( 'attachment' === $record['source_type'] ) {
							$attachment_id = absint( $template['attachment_id'] ?? 0 );

							if ( $attachment_id > 0 ) {
								$record['source_attachment_id'] = $attachment_id;
								$record['filename']             = wp_basename( (string) get_attached_file( $attachment_id ) );
							}
						}

						return $record;
					},
					$templates
				),
				static function ( $record ) {
					return ! empty( $record['key'] );
				}
			)
		);
	}

	private static function find_signature_document_record( $documents, $document_key ) {
		$document_key = sanitize_key( (string) $document_key );

		foreach ( (array) $documents as $document ) {
			if ( $document_key === sanitize_key( (string) ( $document['key'] ?? '' ) ) ) {
				return is_array( $document ) ? $document : array();
			}
		}

		return array();
	}

	private static function signature_document_record_is_valid( $record, $template ) {
		if ( empty( $record ) || empty( $record['attachment_id'] ) ) {
			return false;
		}

		$attachment_id = absint( $record['attachment_id'] );
		$file_path     = get_attached_file( $attachment_id );
		$mime_type     = self::get_attachment_mime_type( $attachment_id );
		$template_hash = self::get_signature_document_template_hash( $template );

		return ! empty( $file_path )
			&& file_exists( $file_path )
			&& 'application/pdf' === $mime_type
			&& (string) ( $record['template_hash'] ?? '' ) === $template_hash;
	}

	private static function generate_signature_document_record( WC_Order $order, $template ) {
		$template_hash = self::get_signature_document_template_hash( $template );
		$record        = array(
			'key'           => sanitize_key( (string) ( $template['key'] ?? '' ) ),
			'title'         => (string) ( $template['title'] ?? '' ),
			'description'   => (string) ( $template['description'] ?? '' ),
			'source_type'   => (string) ( $template['source_type'] ?? 'editor' ),
			'button_label'  => (string) ( $template['button_label'] ?? __( 'Baixar PDF', EOP_TEXT_DOMAIN ) ),
			'view_label'    => (string) ( $template['view_label'] ?? __( 'Visualizar PDF', EOP_TEXT_DOMAIN ) ),
			'generated_at'  => current_time( 'mysql' ),
			'template_hash' => $template_hash,
		);

		if ( 'attachment' === $record['source_type'] ) {
			$attachment_id = absint( $template['attachment_id'] ?? 0 );
			$file_path     = $attachment_id ? get_attached_file( $attachment_id ) : '';
			$mime_type     = $attachment_id ? self::get_attachment_mime_type( $attachment_id ) : '';

			if ( ! $attachment_id || empty( $file_path ) || ! file_exists( $file_path ) ) {
				return array();
			}

			if ( 'application/pdf' !== $mime_type ) {
				$extracted_text = self::extract_text_from_attachment( $attachment_id );

				if ( '' === $extracted_text ) {
					return array();
				}

				$html = self::get_signature_document_html(
					$order,
					array_merge(
						(array) $template,
						array(
							'source_type' => 'editor',
							'body'        => nl2br( esc_html( $extracted_text ) ),
						)
					)
				);
				$binary = self::build_signature_document_binary( $order, $template, $html );
				$generated_attachment_id = '' !== $binary ? self::store_generated_signature_document( $order, $template, $binary ) : 0;

				if ( ! $generated_attachment_id ) {
					return array();
				}

				$record['attachment_id']        = $generated_attachment_id;
				$record['source_attachment_id'] = $attachment_id;
				$record['filename']             = wp_basename( (string) get_attached_file( $generated_attachment_id ) );

				return $record;
			}

			$record['attachment_id']        = $attachment_id;
			$record['source_attachment_id'] = $attachment_id;
			$record['filename']             = wp_basename( $file_path );

			return $record;
		}

		$html = self::get_signature_document_html( $order, $template );

		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return array();
		}

		$binary = self::build_signature_document_binary( $order, $template, $html );

		if ( '' === $binary ) {
			return array();
		}

		$attachment_id = self::store_generated_signature_document( $order, $template, $binary );

		if ( ! $attachment_id ) {
			return array();
		}

		$record['attachment_id'] = $attachment_id;
		$record['filename']      = wp_basename( (string) get_attached_file( $attachment_id ) );

		return $record;
	}

	private static function get_signature_document_template_hash( $template ) {
		return md5( wp_json_encode( $template ) );
	}

	private static function get_signature_document_html( WC_Order $order, $template ) {
		$settings        = EOP_Settings::get_all();
		$font_css        = method_exists( 'EOP_Settings', 'get_font_css_family' ) ? EOP_Settings::get_font_css_family( $settings['font_family'] ?? '' ) : "'Segoe UI', sans-serif";
		$company_name    = trim( (string) ( $settings['pdf_company_name'] ?? '' ) );
		$company_doc     = trim( (string) ( $settings['pdf_company_document'] ?? '' ) );
		$company_address = trim( (string) ( $settings['pdf_company_address'] ?? '' ) );
		$title           = (string) ( $template['title'] ?? '' );
		$description     = (string) ( $template['description'] ?? '' );
		$body            = self::resolve_signature_document_template( $order, (string) ( $template['body'] ?? '' ) );

		ob_start();
		?>
		<!doctype html>
		<html>
		<head>
			<meta charset="utf-8">
			<style>
				@page { size: A4; margin: 16mm; }
				body { margin: 0; color: #172033; background: #ffffff; font-family: <?php echo esc_html( $font_css ); ?>; font-size: 13px; line-height: 1.6; }
				* { box-sizing: border-box; }
				.eop-signature-pdf { display: grid; gap: 18px; }
				.eop-signature-pdf__hero { padding: 24px; border-radius: 20px; background: linear-gradient(145deg, #0f1d4d 0%, #172e7a 100%); color: #fff; }
				.eop-signature-pdf__hero strong { display: block; margin-bottom: 8px; font-size: 11px; letter-spacing: .12em; text-transform: uppercase; opacity: .78; }
				.eop-signature-pdf__hero h1 { margin: 0; font-size: 28px; line-height: 1.12; }
				.eop-signature-pdf__hero p { margin: 10px 0 0; color: rgba(255,255,255,.84); }
				.eop-signature-pdf__meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
				.eop-signature-pdf__meta-card { padding: 16px 18px; border: 1px solid #dbe3f0; border-radius: 18px; }
				.eop-signature-pdf__meta-card span { display: block; margin-bottom: 6px; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #5b6474; }
				.eop-signature-pdf__body { padding: 18px; border: 1px solid #e4e9f6; border-radius: 18px; background: #fbfcff; }
				.eop-signature-pdf__body h1,
				.eop-signature-pdf__body h2,
				.eop-signature-pdf__body h3 { margin: 0 0 10px; }
				.eop-signature-pdf__body p { margin: 0 0 12px; }
				.eop-signature-pdf__body ul,
				.eop-signature-pdf__body ol { margin: 0 0 12px 20px; }
			</style>
		</head>
		<body>
			<div class="eop-signature-pdf">
				<div class="eop-signature-pdf__hero">
					<strong><?php esc_html_e( 'Documento para assinatura', EOP_TEXT_DOMAIN ); ?></strong>
					<h1><?php echo esc_html( $title ); ?></h1>
					<?php if ( $description ) : ?><p><?php echo esc_html( $description ); ?></p><?php endif; ?>
				</div>
				<div class="eop-signature-pdf__meta">
					<div class="eop-signature-pdf__meta-card">
						<span><?php esc_html_e( 'Pedido', EOP_TEXT_DOMAIN ); ?></span>
						<strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong>
					</div>
					<div class="eop-signature-pdf__meta-card">
						<span><?php esc_html_e( 'Empresa', EOP_TEXT_DOMAIN ); ?></span>
						<strong><?php echo esc_html( $company_name ?: get_bloginfo( 'name' ) ); ?></strong>
						<?php if ( $company_doc ) : ?><p><?php echo esc_html( $company_doc ); ?></p><?php endif; ?>
						<?php if ( $company_address ) : ?><p><?php echo esc_html( $company_address ); ?></p><?php endif; ?>
					</div>
				</div>
				<div class="eop-signature-pdf__body"><?php echo wp_kses_post( wpautop( $body ) ); ?></div>
			</div>
		</body>
		</html>
		<?php

		return ob_get_clean();
	}

	private static function resolve_signature_document_template( WC_Order $order, $template ) {
		if ( '' === $template ) {
			return '';
		}

		return strtr( (string) $template, self::get_contract_placeholder_replacements( $order ) );
	}

	private static function build_signature_document_binary( WC_Order $order, $template, $html ) {
		$filename = self::get_signature_document_filename( $order, $template );
		$binary   = self::maybe_build_dompdf_pdf( $html );

		if ( '' !== $binary ) {
			return $binary;
		}

		return self::maybe_build_headless_pdf( $html, $filename );
	}

	private static function get_signature_document_filename( WC_Order $order, $template ) {
		$key = sanitize_title( (string) ( $template['key'] ?? $template['title'] ?? 'documento' ) );

		return sanitize_file_name( 'assinatura-' . $order->get_id() . '-' . $key . '.pdf' );
	}

	private static function store_generated_signature_document( WC_Order $order, $template, $binary ) {
		$filename = self::get_signature_document_filename( $order, $template );
		$upload   = wp_upload_bits( $filename, null, $binary );

		if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
			return 0;
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'application/pdf',
				'post_title'     => sanitize_text_field( (string) ( $template['title'] ?? $filename ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_parent'    => $order->get_id(),
			),
			$upload['file'],
			$order->get_id()
		);

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		update_attached_file( $attachment_id, $upload['file'] );

		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );

		if ( ! is_wp_error( $metadata ) && is_array( $metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		return absint( $attachment_id );
	}

	private static function stream_attachment_file( $attachment_id, $force_download = false, $filename = '' ) {
		$file_path = get_attached_file( $attachment_id );
		$mime_type = (string) get_post_mime_type( $attachment_id );

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'Arquivo nao encontrado.', EOP_TEXT_DOMAIN ) );
		}

		nocache_headers();
		header( 'Content-Type: ' . ( $mime_type ? $mime_type : 'application/octet-stream' ) );
		header( 'Content-Disposition: ' . ( $force_download ? 'attachment' : 'inline' ) . '; filename="' . sanitize_file_name( $filename ? $filename : wp_basename( $file_path ) ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: private, max-age=0, must-revalidate' );
		header( 'Pragma: public' );

		readfile( $file_path );
		exit;
	}

	private static function get_attachment_mime_type( $attachment_id ) {
		$mime_type = (string) get_post_mime_type( $attachment_id );
		$file_path = get_attached_file( $attachment_id );

		if ( '' === $mime_type && ! empty( $file_path ) ) {
			$filetype = wp_check_filetype( $file_path );
			$mime_type = (string) ( $filetype['type'] ?? '' );
		}

		return $mime_type;
	}

	private static function extract_text_from_attachment( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );
		$mime_type = self::get_attachment_mime_type( $attachment_id );

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return '';
		}

		if ( false !== strpos( $mime_type, 'wordprocessingml.document' ) || 'docx' === strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) ) ) {
			return self::extract_text_from_docx_file( $file_path );
		}

		if ( false !== strpos( $mime_type, 'msword' ) || 'doc' === strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) ) ) {
			return self::extract_text_from_doc_file( $file_path );
		}

		return '';
	}

	private static function extract_text_from_docx_file( $file_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return '';
		}

		$zip = new ZipArchive();

		if ( true !== $zip->open( $file_path ) ) {
			return '';
		}

		$xml = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( false === $xml || '' === $xml ) {
			return '';
		}

		$text = str_replace( array( '</w:p>', '</w:tr>', '</w:tc>' ), array( "\n\n", "\n", ' ' ), $xml );
		$text = preg_replace( '/<w:tab[^>]*\/>/i', "\t", (string) $text );
		$text = wp_strip_all_tags( $text );

		return self::normalize_extracted_document_text( $text );
	}

	private static function extract_text_from_doc_file( $file_path ) {
		$command_path = self::find_cli_binary( array( 'antiword' ) );

		if ( '' === $command_path ) {
			return '';
		}

		$output = array();
		$return = 0;
		exec( escapeshellarg( $command_path ) . ' ' . escapeshellarg( $file_path ) . ' 2>&1', $output, $return );

		if ( 0 !== $return || empty( $output ) ) {
			return '';
		}

		return self::normalize_extracted_document_text( implode( "\n", $output ) );
	}

	private static function find_cli_binary( $candidates ) {
		$paths = array_filter( explode( PATH_SEPARATOR, (string) getenv( 'PATH' ) ) );

		foreach ( (array) $candidates as $candidate ) {
			foreach ( $paths as $path ) {
				$path = rtrim( (string) $path, DIRECTORY_SEPARATOR );

				foreach ( array( $path . DIRECTORY_SEPARATOR . $candidate, $path . DIRECTORY_SEPARATOR . $candidate . '.exe' ) as $binary ) {
					if ( is_file( $binary ) && is_readable( $binary ) ) {
						return $binary;
					}
				}
			}
		}

		return '';
	}

	private static function normalize_extracted_document_text( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES, get_bloginfo( 'charset' ) ? get_bloginfo( 'charset' ) : 'UTF-8' );
		$text = preg_replace( '/\r\n|\r/', "\n", $text );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );
		$text = preg_replace( '/[ \t]+/', ' ', $text );
		$text = preg_replace( '/ ?\n ?/', "\n", $text );

		return trim( wp_strip_all_tags( (string) $text ) );
	}

	private static function get_document_fields( $settings = null ) {
		return EOP_Settings::get_post_confirmation_document_fields( is_array( $settings ) ? $settings : EOP_Settings::get_all() );
	}

	public static function get_contract_placeholder_tokens() {
		return array(
			'{site_name}',
			'{order_id}',
			'{order_number}',
			'{order_total}',
			'{billing_first_name}',
			'{billing_last_name}',
			'{billing_full_name}',
			'{billing_email}',
			'{billing_phone}',
			'{billing_company}',
			'{billing_document}',
			'{billing_cpf}',
			'{billing_cnpj}',
			'{billing_ie}',
			'{billing_address_1}',
			'{billing_address_2}',
			'{billing_number}',
			'{billing_neighborhood}',
			'{billing_city}',
			'{billing_state}',
			'{billing_postcode}',
			'{billing_country}',
			'{billing_full_address}',
			'{shipping_first_name}',
			'{shipping_last_name}',
			'{shipping_full_name}',
			'{shipping_company}',
			'{shipping_address_1}',
			'{shipping_address_2}',
			'{shipping_number}',
			'{shipping_neighborhood}',
			'{shipping_city}',
			'{shipping_state}',
			'{shipping_postcode}',
			'{shipping_country}',
			'{shipping_full_address}',
			'{payment_method}',
			'{order_date}',
			'{contract_date}',
			'{contract_time}',
			'{contract_datetime}',
			'{contract_acceptance_date}',
			'{contract_acceptance_time}',
			'{contract_acceptance_datetime}',
		);
	}

	private static function get_public_return_url( WC_Order $order ) {
		if ( 'yes' !== EOP_Settings::get( 'enable_checkout_confirmation', 'no' ) || ! self::is_enabled_for_order( $order ) || 'yes' !== (string) $order->get_meta( '_eop_proposal_confirmed', true ) ) {
			return '';
		}

		return (string) EOP_Public_Proposal::get_public_link( $order );
	}

	private static function get_contract_text( WC_Order $order, $state = null ) {
		$state         = is_array( $state ) ? $state : self::get_state( $order );
		$contract_text = trim( (string) ( $state['contract']['contract_text'] ?? '' ) );

		if ( '' !== $contract_text ) {
			return $contract_text;
		}

		$contract_text = self::build_contract_documents_snapshot( $order );

		if ( '' !== trim( wp_strip_all_tags( $contract_text ) ) ) {
			return $contract_text;
		}

		return self::resolve_contract_template( $order );
	}

	private static function get_contract_document_sections( WC_Order $order ) {
		$documents = self::get_signature_document_templates();
		$sections  = array();

		foreach ( $documents as $document ) {
			if ( 'attachment' === ( $document['source_type'] ?? 'editor' ) ) {
				continue;
			}

			$body = trim( (string) self::resolve_signature_document_template( $order, (string) ( $document['body'] ?? '' ) ) );

			if ( '' === trim( wp_strip_all_tags( $body ) ) ) {
				continue;
			}

			$sections[] = array(
				'title' => trim( (string) ( $document['title'] ?? '' ) ),
				'body'  => $body,
			);
		}

		return $sections;
	}

	private static function build_contract_documents_snapshot( WC_Order $order ) {
		$document_sections = self::get_contract_document_sections( $order );
		$sections          = array();

		foreach ( $document_sections as $document_section ) {
			$section = '';

			if ( '' !== $document_section['title'] ) {
				$section .= '<h3>' . esc_html( $document_section['title'] ) . '</h3>';
			}

			$section    .= $document_section['body'];
			$sections[] = $section;
		}

		if ( empty( $sections ) ) {
			return '';
		}

		return implode( "\n<hr />\n", $sections );
	}

	private static function resolve_contract_template( WC_Order $order, $template = null ) {
		$template = is_string( $template ) ? $template : (string) EOP_Settings::get( 'post_confirmation_contract_body', '' );

		if ( '' === $template ) {
			return '';
		}

		return strtr( $template, self::get_contract_placeholder_replacements( $order ) );
	}

	private static function get_contract_placeholder_replacements( WC_Order $order ) {
		$billing_document     = self::get_order_customer_document( $order );
		$billing_cpf          = self::get_order_meta_value( $order, array( '_billing_cpf', 'billing_cpf' ) );
		$billing_cnpj         = self::get_order_meta_value( $order, array( '_billing_cnpj', 'billing_cnpj' ) );
		$billing_ie           = self::get_order_meta_value( $order, array( '_billing_ie', 'billing_ie' ) );
		$billing_number       = self::get_order_meta_value( $order, array( '_billing_number', 'billing_number' ) );
		$billing_neighborhood = self::get_order_meta_value( $order, array( '_billing_neighborhood', 'billing_neighborhood' ) );
		$shipping_number      = self::get_order_meta_value( $order, array( '_shipping_number', 'shipping_number' ) );
		$shipping_neighborhood = self::get_order_meta_value( $order, array( '_shipping_neighborhood', 'shipping_neighborhood' ) );
		$billing_full_name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$shipping_full_name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
		$order_date         = $order->get_date_created();
		$contract_accepted_at = trim( (string) $order->get_meta( '_eop_post_confirmation_contract_accepted_at', true ) );
		$date_format        = (string) get_option( 'date_format', 'd/m/Y' );
		$time_format        = (string) get_option( 'time_format', 'H:i' );
		$replacements       = array(
			'site_name'             => get_bloginfo( 'name' ),
			'order_id'              => (string) $order->get_id(),
			'order_number'          => (string) $order->get_order_number(),
			'order_total'           => wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ),
			'billing_first_name'    => $order->get_billing_first_name(),
			'billing_last_name'     => $order->get_billing_last_name(),
			'billing_full_name'     => $billing_full_name,
			'billing_email'         => $order->get_billing_email(),
			'billing_phone'         => $order->get_billing_phone(),
			'billing_company'       => $order->get_billing_company(),
			'billing_document'      => $billing_document,
			'billing_cpf'           => $billing_cpf,
			'billing_cnpj'          => $billing_cnpj,
			'billing_ie'            => $billing_ie,
			'billing_address_1'     => $order->get_billing_address_1(),
			'billing_address_2'     => $order->get_billing_address_2(),
			'billing_number'        => $billing_number,
			'billing_neighborhood'  => $billing_neighborhood,
			'billing_city'          => $order->get_billing_city(),
			'billing_state'         => $order->get_billing_state(),
			'billing_postcode'      => $order->get_billing_postcode(),
			'billing_country'       => $order->get_billing_country(),
			'billing_full_address'  => self::implode_address_parts(
				array(
					$order->get_billing_address_1(),
					$billing_number,
					$order->get_billing_address_2(),
					$billing_neighborhood,
					$order->get_billing_city(),
					$order->get_billing_state(),
					$order->get_billing_postcode(),
					$order->get_billing_country(),
				)
			),
			'shipping_first_name'   => $order->get_shipping_first_name(),
			'shipping_last_name'    => $order->get_shipping_last_name(),
			'shipping_full_name'    => $shipping_full_name,
			'shipping_company'      => $order->get_shipping_company(),
			'shipping_address_1'    => $order->get_shipping_address_1(),
			'shipping_address_2'    => $order->get_shipping_address_2(),
			'shipping_number'       => $shipping_number,
			'shipping_neighborhood' => $shipping_neighborhood,
			'shipping_city'         => $order->get_shipping_city(),
			'shipping_state'        => $order->get_shipping_state(),
			'shipping_postcode'     => $order->get_shipping_postcode(),
			'shipping_country'      => $order->get_shipping_country(),
			'shipping_full_address' => self::implode_address_parts(
				array(
					$order->get_shipping_address_1(),
					$shipping_number,
					$order->get_shipping_address_2(),
					$shipping_neighborhood,
					$order->get_shipping_city(),
					$order->get_shipping_state(),
					$order->get_shipping_postcode(),
					$order->get_shipping_country(),
				)
			),
			'payment_method'        => $order->get_payment_method_title(),
			'order_date'            => $order_date ? wc_format_datetime( $order_date ) : '',
			'contract_date'         => self::format_contract_placeholder_datetime( $contract_accepted_at, $date_format ),
			'contract_time'         => self::format_contract_placeholder_datetime( $contract_accepted_at, $time_format ),
			'contract_datetime'     => self::format_contract_placeholder_datetime( $contract_accepted_at, trim( $date_format . ' ' . $time_format ) ),
			'contract_acceptance_date' => self::format_contract_placeholder_datetime( $contract_accepted_at, $date_format ),
			'contract_acceptance_time' => self::format_contract_placeholder_datetime( $contract_accepted_at, $time_format ),
			'contract_acceptance_datetime' => self::format_contract_placeholder_datetime( $contract_accepted_at, trim( $date_format . ' ' . $time_format ) ),
		);

		$prepared = array();
		$charset  = get_bloginfo( 'charset' );

		foreach ( $replacements as $key => $value ) {
			$sanitized_value = html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, $charset ? $charset : 'UTF-8' );
			$prepared[ '{' . $key . '}' ]   = $sanitized_value;
			$prepared[ '{{' . $key . '}}' ] = $sanitized_value;
		}

		return $prepared;
	}

	private static function format_contract_placeholder_datetime( $datetime_string, $format ) {
		$datetime_string = trim( (string) $datetime_string );
		$format          = trim( (string) $format );

		if ( '' === $datetime_string || '' === $format ) {
			return '';
		}

		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );

		try {
			$datetime = new DateTimeImmutable( $datetime_string, $timezone );
		} catch ( Exception $exception ) {
			return '';
		}

		return function_exists( 'wp_date' )
			? wp_date( $format, $datetime->getTimestamp(), $timezone )
			: date_i18n( $format, $datetime->getTimestamp() );
	}

	private static function implode_address_parts( $parts ) {
		$parts = array_filter(
			array_map(
				static function ( $part ) {
					return trim( wp_strip_all_tags( (string) $part ) );
				},
				(array) $parts
			)
		);

		return implode( ', ', $parts );
	}

	private static function get_order_data_rows( WC_Order $order ) {
		$rows = array(
			array(
				'key'   => 'billing_full_name',
				'label' => __( 'Cliente', EOP_TEXT_DOMAIN ),
				'value' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			),
			array(
				'key'   => 'billing_document',
				'label' => __( 'CPF / CNPJ', EOP_TEXT_DOMAIN ),
				'value' => self::get_order_customer_document( $order ),
			),
			array(
				'key'   => 'billing_email',
				'label' => __( 'E-mail', EOP_TEXT_DOMAIN ),
				'value' => $order->get_billing_email(),
			),
			array(
				'key'   => 'billing_phone',
				'label' => __( 'Telefone', EOP_TEXT_DOMAIN ),
				'value' => $order->get_billing_phone(),
			),
			array(
				'key'   => 'billing_company',
				'label' => __( 'Empresa', EOP_TEXT_DOMAIN ),
				'value' => $order->get_billing_company(),
			),
			array(
				'key'   => 'billing_ie',
				'label' => __( 'Inscricao estadual', EOP_TEXT_DOMAIN ),
				'value' => self::get_order_meta_value( $order, array( '_billing_ie', 'billing_ie' ) ),
			),
			array(
				'key'   => 'billing_address',
				'label' => __( 'Endereco de cobranca', EOP_TEXT_DOMAIN ),
				'value' => self::get_order_address_label( $order, 'billing' ),
			),
			array(
				'key'   => 'shipping_address',
				'label' => __( 'Endereco de entrega', EOP_TEXT_DOMAIN ),
				'value' => self::get_order_address_label( $order, 'shipping' ),
			),
			array(
				'key'   => 'payment_method',
				'label' => __( 'Forma de pagamento', EOP_TEXT_DOMAIN ),
				'value' => $order->get_payment_method_title(),
			),
		);

		return array_values(
			array_map(
				static function ( $row ) {
					$value = trim( wp_strip_all_tags( (string) ( $row['value'] ?? '' ) ) );

					return array(
						'key'    => (string) $row['key'],
						'label'  => (string) $row['label'],
						'value'  => $value,
						'filled' => '' !== $value,
					);
				},
				$rows
			)
		);
	}

	private static function get_order_customer_document( WC_Order $order ) {
		return self::get_order_meta_value( $order, array( '_billing_cnpj', 'billing_cnpj', '_billing_cpf', 'billing_cpf' ) );
	}

	private static function get_order_meta_value( WC_Order $order, $keys ) {
		foreach ( (array) $keys as $key ) {
			$value = trim( (string) $order->get_meta( $key, true ) );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	private static function get_order_address_label( WC_Order $order, $type ) {
		$is_shipping  = 'shipping' === $type;
		$number       = self::get_order_meta_value( $order, $is_shipping ? array( '_shipping_number', 'shipping_number' ) : array( '_billing_number', 'billing_number' ) );
		$neighborhood = self::get_order_meta_value( $order, $is_shipping ? array( '_shipping_neighborhood', 'shipping_neighborhood' ) : array( '_billing_neighborhood', 'billing_neighborhood' ) );
		$parts        = $is_shipping
			? array( $order->get_shipping_address_1(), $number, $order->get_shipping_address_2(), $neighborhood, $order->get_shipping_city(), $order->get_shipping_state(), $order->get_shipping_postcode(), $order->get_shipping_country() )
			: array( $order->get_billing_address_1(), $number, $order->get_billing_address_2(), $neighborhood, $order->get_billing_city(), $order->get_billing_state(), $order->get_billing_postcode(), $order->get_billing_country() );

		return self::implode_address_parts( $parts );
	}

	private static function get_allowed_attachment_upload_mimes() {
		return apply_filters(
			'eop_post_confirmation_allowed_attachment_mimes',
			array(
				'jpg|jpeg' => 'image/jpeg',
				'png'      => 'image/png',
				'pdf'      => 'application/pdf',
			)
		);
	}

	private static function get_max_attachment_upload_size() {
		$default_limit = 8 * MB_IN_BYTES;
		$wp_limit      = function_exists( 'wp_max_upload_size' ) ? (int) wp_max_upload_size() : 0;

		if ( $wp_limit > 0 ) {
			$default_limit = min( $default_limit, $wp_limit );
		}

		return max( 1, (int) apply_filters( 'eop_post_confirmation_max_attachment_upload_size', $default_limit ) );
	}

	private static function get_upload_notice_from_error( WP_Error $error ) {
		$code = sanitize_key( (string) $error->get_error_code() );

		if ( in_array( $code, array( 'invalid_file_type', 'file_too_large', 'upload_failed', 'missing_file' ), true ) ) {
			return $code;
		}

		return 'invalid_file';
	}

	private static function store_upload( $file ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( ! is_array( $file ) || empty( $file['name'] ) || empty( $file['tmp_name'] ) ) {
			return new WP_Error( 'missing_file', __( 'Nenhum arquivo foi enviado.', EOP_TEXT_DOMAIN ) );
		}

		$upload_error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_OK;

		if ( UPLOAD_ERR_OK !== $upload_error ) {
			return new WP_Error( 'upload_failed', __( 'Falha ao receber o arquivo enviado.', EOP_TEXT_DOMAIN ) );
		}

		$max_upload_size = self::get_max_attachment_upload_size();

		if ( ! empty( $file['size'] ) && (int) $file['size'] > $max_upload_size ) {
			return new WP_Error( 'file_too_large', __( 'Arquivo acima do limite permitido.', EOP_TEXT_DOMAIN ) );
		}

		$allowed_mimes = self::get_allowed_attachment_upload_mimes();
		$file_check    = wp_check_filetype_and_ext( (string) $file['tmp_name'], (string) $file['name'], $allowed_mimes );
		$detected_type = isset( $file_check['type'] ) ? (string) $file_check['type'] : '';

		if ( '' === $detected_type || ! in_array( $detected_type, array_values( $allowed_mimes ), true ) ) {
			return new WP_Error( 'invalid_file_type', __( 'Formato de arquivo nao permitido.', EOP_TEXT_DOMAIN ) );
		}

		$uploaded = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => $allowed_mimes,
			)
		);

		if ( isset( $uploaded['error'] ) || empty( $uploaded['file'] ) ) {
			return new WP_Error( 'upload_failed', __( 'Arquivo invalido.', EOP_TEXT_DOMAIN ) );
		}

		$attachment = array(
			'post_mime_type' => sanitize_mime_type( $uploaded['type'] ),
			'post_title'     => sanitize_text_field( wp_basename( $uploaded['file'] ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $uploaded['file'] );

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return new WP_Error( 'invalid_file', __( 'Nao foi possivel registrar o arquivo.', EOP_TEXT_DOMAIN ) );
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return absint( $attachment_id );
	}

	private static function get_client_ip() {
		$candidates = array(
			$_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
			$_SERVER['REMOTE_ADDR'] ?? '',
		);

		foreach ( $candidates as $candidate ) {
			$candidate = trim( explode( ',', (string) $candidate )[0] );

			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}

		return '';
	}
}
