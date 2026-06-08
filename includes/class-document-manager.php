<?php
defined( 'ABSPATH' ) || exit;

class EOP_Document_Manager {

    use EOP_License_Guard;

	const PUBLIC_TOKEN_QUERY_VAR = 'eop_token';

    public static function init() {
        self::maybe_bootstrap_pdf_output_buffer();

        if ( ! self::_resolve_env_config() ) {
            return;
        }

        add_action( 'admin_post_eop_download_pdf', array( __CLASS__, 'handle_download' ) );
        add_action( 'admin_post_nopriv_eop_download_pdf', array( __CLASS__, 'handle_download' ) );
        add_action( 'template_redirect', array( __CLASS__, 'handle_frontend_download_request' ) );

        if ( class_exists( 'EOP_PDF_Settings' ) ) {
            add_action( 'update_option_' . EOP_PDF_Settings::OPTION_KEY, array( __CLASS__, 'maybe_invalidate_cached_documents' ), 10, 2 );
        }
    }

    private static function maybe_bootstrap_pdf_output_buffer() {
        if ( ! self::is_pdf_download_request() ) {
            return;
        }

        ob_start();
    }

    private static function is_pdf_download_request() {
        $download_flag = isset( $_REQUEST['eop_download_pdf'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['eop_download_pdf'] ) ) : '';
        $action        = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

        return '1' === $download_flag || 'eop_download_pdf' === $action;
    }

    private static function discard_buffered_output() {
        $buffered_output = '';

        while ( ob_get_level() > 0 ) {
            $chunk = ob_get_clean();

            if ( false === $chunk ) {
                break;
            }

            if ( '' !== $chunk ) {
                $buffered_output = $chunk . $buffered_output;
            }
        }

        return $buffered_output;
    }

    public static function get_pdf_document_url( WC_Order $order, $document_type = '', $force_download = false ) {
        $document_type = self::normalize_document_type( $document_type ? $document_type : self::detect_document_type( $order ) );
        $args          = self::get_private_download_args( $order, $document_type, $force_download );

        if ( self::use_pretty_pdf_links() ) {
            $args['eop_download_pdf'] = '1';

            return add_query_arg( $args, home_url( '/' ) );
        }

        return add_query_arg(
            array_merge(
                array(
                    'action' => 'eop_download_pdf',
                ),
                $args
            ),
            admin_url( 'admin-post.php' )
        );
    }

    public static function is_expresso_order( $order ) {
        return $order instanceof WC_Order && 'aireset-expresso-order' === (string) $order->get_created_via();
    }

    public static function get_document_type_for_order( WC_Order $order ) {
        return self::detect_document_type( $order );
    }

    public static function is_matching_document_type( WC_Order $order, $document_type = '' ) {
        $document_type = self::normalize_document_type( $document_type ? $document_type : self::detect_document_type( $order ) );

        return $document_type === self::detect_document_type( $order );
    }

    public static function resolve_document_type_for_order( WC_Order $order, $document_type = '' ) {
        $document_type = self::normalize_document_type( $document_type ? $document_type : self::detect_document_type( $order ) );

        if ( self::is_matching_document_type( $order, $document_type ) ) {
            return $document_type;
        }

        return self::detect_document_type( $order );
    }

    public static function is_document_enabled( WC_Order $order, $document_type = '' ) {
        if ( ! self::is_expresso_order( $order ) ) {
            return false;
        }

        $document_type = self::resolve_document_type_for_order( $order, $document_type ? $document_type : self::detect_document_type( $order ) );
        $settings      = self::get_pdf_settings();

        if ( 'proposal' === $document_type && 'yes' !== ( $settings['proposal_public_pdf'] ?? 'yes' ) && 'yes' === (string) $order->get_meta( '_eop_is_proposal', true ) ) {
            return 'yes' === ( $settings['proposal_enabled'] ?? 'yes' );
        }

        return 'yes' === ( $settings[ $document_type . '_enabled' ] ?? 'yes' );
    }

    public static function can_customer_download( WC_Order $order, $document_type = '' ) {
        if ( ! is_user_logged_in() || ! self::is_expresso_order( $order ) ) {
            return false;
        }

        $document_type = self::normalize_document_type( $document_type ? $document_type : self::detect_document_type( $order ) );
        $settings      = self::get_pdf_settings();
        $user_id       = get_current_user_id();
        $customer_id   = absint( $order->get_customer_id() );

        if ( $customer_id <= 0 || $customer_id !== $user_id ) {
            return false;
        }

        if ( 'proposal' === $document_type ) {
            return false;
        }

        return self::is_document_enabled( $order, $document_type ) && 'yes' === ( $settings['order_myaccount_download'] ?? 'no' );
    }

    public static function can_public_proposal_download( WC_Order $order ) {
        $settings = self::get_pdf_settings();

        return self::is_expresso_order( $order )
            && 'yes' === (string) $order->get_meta( '_eop_is_proposal', true )
            && 'yes' === ( $settings['proposal_enabled'] ?? 'yes' )
            && 'yes' === ( $settings['proposal_public_pdf'] ?? 'yes' );
    }

    public static function get_public_document_url( WC_Order $order, $force_download = false ) {
        $token = (string) $order->get_meta( '_eop_public_token', true );

        if ( '' === $token || ! self::can_public_proposal_download( $order ) ) {
            return '';
        }

        $args = array(
            'document' => 'proposal',
            'download' => $force_download ? '1' : '0',
			self::PUBLIC_TOKEN_QUERY_VAR => rawurlencode( $token ),
        );

        if ( self::use_pretty_pdf_links() ) {
            $args['eop_download_pdf'] = '1';

            return add_query_arg( $args, home_url( '/' ) );
        }

        return add_query_arg(
            array_merge(
                array(
                    'action' => 'eop_download_pdf',
                ),
                $args
            ),
            admin_url( 'admin-post.php' )
        );
    }

    public static function handle_frontend_download_request() {
        $is_front_request = isset( $_GET['eop_download_pdf'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['eop_download_pdf'] ) );

        if ( ! $is_front_request ) {
            return;
        }

        self::handle_download();
    }

    public static function get_document_record( WC_Order $order ) {
        $document_type = self::detect_document_type( $order );

        return array(
            'order' => array(
                'url'          => 'order' === $document_type && self::is_document_enabled( $order, 'order' ) ? self::get_pdf_document_url( $order, 'order' ) : '',
                'generated_at' => (string) $order->get_meta( '_eop_pdf_order_generated_at', true ),
                'generated_by' => absint( $order->get_meta( '_eop_pdf_order_generated_by', true ) ),
            ),
            'proposal' => array(
                'url'          => 'proposal' === $document_type && self::is_document_enabled( $order, 'proposal' ) ? self::get_pdf_document_url( $order, 'proposal' ) : '',
                'public_url'   => 'proposal' === $document_type ? self::get_public_document_url( $order ) : '',
                'generated_at' => (string) $order->get_meta( '_eop_pdf_proposal_generated_at', true ),
                'generated_by' => absint( $order->get_meta( '_eop_pdf_proposal_generated_by', true ) ),
            ),
        );
    }

    public static function get_document_number_label( WC_Order $order, $document_type = '', $create = false ) {
        if ( ! self::is_expresso_order( $order ) ) {
            return '';
        }

        $document_type = self::normalize_document_type( $document_type ? $document_type : self::detect_document_type( $order ) );

        return self::get_document_number( $order, $document_type, (bool) $create );
    }

    public static function get_document_display_settings( $document_type = 'order' ) {
        $settings = self::get_pdf_settings();
        $prefix   = 'proposal' === self::normalize_document_type( $document_type ) ? 'proposal' : 'order';

        return array(
            'show_email'          => $settings[ $prefix . '_show_email' ] ?? 'yes',
            'show_phone'          => $settings[ $prefix . '_show_phone' ] ?? 'yes',
            'show_shipping'       => $settings[ $prefix . '_show_shipping' ] ?? 'yes',
            'show_billing'        => $settings[ $prefix . '_show_billing' ] ?? 'no',
            'show_notes'          => $settings[ $prefix . '_show_notes' ] ?? 'yes',
            'show_sku'            => $settings[ $prefix . '_show_sku' ] ?? 'yes',
            'show_item_index'     => $settings[ $prefix . '_show_item_index' ] ?? 'yes',
            'show_quantity'       => $settings[ $prefix . '_show_quantity' ] ?? 'yes',
            'show_unit_price'     => $settings[ $prefix . '_show_unit_price' ] ?? 'yes',
            'show_discount'       => $settings[ $prefix . '_show_discount' ] ?? 'yes',
            'discount_display_mode' => $settings[ $prefix . '_discount_display_mode' ] ?? 'both',
            'show_discounted_unit_price' => $settings[ $prefix . '_show_discounted_unit_price' ] ?? 'yes',
            'show_line_total'     => $settings[ $prefix . '_show_line_total' ] ?? 'yes',
            'show_total_subtotal' => $settings[ $prefix . '_show_total_subtotal' ] ?? 'yes',
            'show_total_shipping' => $settings[ $prefix . '_show_total_shipping' ] ?? 'yes',
            'show_total_discount' => $settings[ $prefix . '_show_total_discount' ] ?? 'yes',
            'show_total_total'    => $settings[ $prefix . '_show_total_total' ] ?? 'yes',
        );
    }

    public static function get_document_visual_settings( $document_type = 'order' ) {
        $settings = self::get_pdf_settings();
        $prefix   = 'proposal' === self::normalize_document_type( $document_type ) ? 'proposal' : 'order';
        $defaults = class_exists( 'EOP_PDF_Settings' )
            ? EOP_PDF_Settings::get_document_visual_defaults( $document_type )
            : array(
                'header_background_color' => '#111111',
                'header_text_color'       => '#ffffff',
                'body_text_color'         => '#172033',
                'muted_text_color'        => '#5b6474',
                'border_color'            => '#e3e8f1',
                'title_font_size'         => '34',
                'meta_font_size'          => '13',
                'table_header_font_size'  => '12',
                'table_body_font_size'    => '14',
                'table_body_line_height'  => '1.35',
                'totals_font_size'        => '16',
                'note_font_size'          => '13',
            );

        return array(
            'header_background_color' => self::sanitize_pdf_hex_color( $settings[ $prefix . '_header_background_color' ] ?? $defaults['header_background_color'], $defaults['header_background_color'] ),
            'header_text_color'       => self::sanitize_pdf_hex_color( $settings[ $prefix . '_header_text_color' ] ?? $defaults['header_text_color'], $defaults['header_text_color'] ),
            'body_text_color'         => self::sanitize_pdf_hex_color( $settings[ $prefix . '_body_text_color' ] ?? $defaults['body_text_color'], $defaults['body_text_color'] ),
            'muted_text_color'        => self::sanitize_pdf_hex_color( $settings[ $prefix . '_muted_text_color' ] ?? $defaults['muted_text_color'], $defaults['muted_text_color'] ),
            'border_color'            => self::sanitize_pdf_hex_color( $settings[ $prefix . '_border_color' ] ?? $defaults['border_color'], $defaults['border_color'] ),
            'title_font_size'         => self::sanitize_pdf_font_size( $settings[ $prefix . '_title_font_size' ] ?? $defaults['title_font_size'], $defaults['title_font_size'] ),
            'meta_font_size'          => self::sanitize_pdf_font_size( $settings[ $prefix . '_meta_font_size' ] ?? $defaults['meta_font_size'], $defaults['meta_font_size'] ),
            'table_header_font_size'  => self::sanitize_pdf_font_size( $settings[ $prefix . '_table_header_font_size' ] ?? $defaults['table_header_font_size'], $defaults['table_header_font_size'] ),
            'table_body_font_size'    => self::sanitize_pdf_font_size( $settings[ $prefix . '_table_body_font_size' ] ?? $defaults['table_body_font_size'], $defaults['table_body_font_size'] ),
            'table_body_line_height'  => self::sanitize_pdf_line_height( $settings[ $prefix . '_table_body_line_height' ] ?? $defaults['table_body_line_height'], $defaults['table_body_line_height'] ),
            'totals_font_size'        => self::sanitize_pdf_font_size( $settings[ $prefix . '_totals_font_size' ] ?? $defaults['totals_font_size'], $defaults['totals_font_size'] ),
            'note_font_size'          => self::sanitize_pdf_font_size( $settings[ $prefix . '_note_font_size' ] ?? $defaults['note_font_size'], $defaults['note_font_size'] ),
        );
    }

    public static function get_document_discount_suffix( $document_type = 'order' ) {
        $settings = self::get_pdf_settings();
        $prefix   = 'proposal' === self::normalize_document_type( $document_type ) ? 'proposal' : 'order';
        $default  = '/ un.';

        if ( class_exists( 'EOP_PDF_Settings' ) ) {
            $defaults = EOP_PDF_Settings::get_defaults();
            $default  = (string) ( $defaults[ $prefix . '_discount_suffix' ] ?? $default );
        }

        return sanitize_text_field( (string) ( $settings[ $prefix . '_discount_suffix' ] ?? $default ) );
    }

    public static function get_document_discounted_unit_price_suffix( $document_type = 'order' ) {
        $settings = self::get_pdf_settings();
        $prefix   = 'proposal' === self::normalize_document_type( $document_type ) ? 'proposal' : 'order';
        $default  = '/ un.';

        if ( class_exists( 'EOP_PDF_Settings' ) ) {
            $defaults = EOP_PDF_Settings::get_defaults();
            $default  = (string) ( $defaults[ $prefix . '_discounted_unit_price_suffix' ] ?? $default );
        }

        return sanitize_text_field( (string) ( $settings[ $prefix . '_discounted_unit_price_suffix' ] ?? $default ) );
    }

    public static function get_document_discount_display_mode( $document_type = 'order' ) {
        $settings = self::get_pdf_settings();
        $prefix   = 'proposal' === self::normalize_document_type( $document_type ) ? 'proposal' : 'order';
        $mode     = sanitize_key( (string) ( $settings[ $prefix . '_discount_display_mode' ] ?? 'both' ) );

        return in_array( $mode, array( 'percent', 'currency', 'both' ), true ) ? $mode : 'both';
    }

    public static function get_document_item_labels( $document_type = 'order' ) {
        $settings = self::get_pdf_settings();
        $prefix   = 'proposal' === self::normalize_document_type( $document_type ) ? 'proposal' : 'order';
        $labels   = class_exists( 'EOP_PDF_Settings' )
            ? EOP_PDF_Settings::get_document_item_label_defaults( $document_type )
            : array(
                'product'               => __( 'Produto', EOP_TEXT_DOMAIN ),
                'quantity'              => __( 'Quantidade', EOP_TEXT_DOMAIN ),
                'unit_price'            => __( 'Valor unitário', EOP_TEXT_DOMAIN ),
                'discount'              => __( 'Desconto aplicado', EOP_TEXT_DOMAIN ),
                'discounted_unit_price' => __( 'Valor unitário com desconto', EOP_TEXT_DOMAIN ),
                'line_total'            => __( 'Total', EOP_TEXT_DOMAIN ),
            );

        foreach ( $labels as $key => $default ) {
            $setting_key    = $prefix . '_' . $key . '_label';
            $configured     = isset( $settings[ $setting_key ] ) ? sanitize_text_field( (string) $settings[ $setting_key ] ) : '';
            $labels[ $key ] = class_exists( 'EOP_PDF_Settings' )
                ? EOP_PDF_Settings::resolve_document_item_label( $document_type, $key, $configured, $default )
                : ( '' !== $configured ? $configured : $default );
        }

        return $labels;
    }

    public static function get_document_item_columns( $document_type = 'order' ) {
        $config  = self::get_document_display_settings( $document_type );
        $labels  = self::get_document_item_labels( $document_type );
        $columns = array();
        $positions = class_exists( 'EOP_PDF_Settings' )
            ? EOP_PDF_Settings::get_document_item_position_defaults( $document_type )
            : array(
                'quantity'              => '10',
                'unit_price'            => '20',
                'discount'              => '30',
                'discounted_unit_price' => '40',
                'line_total'            => '50',
            );

        foreach ( $positions as $key => $default_position ) {
            $positions[ $key ] = max( 1, absint( self::get_pdf_settings()[ self::normalize_document_type( $document_type ) . '_' . $key . '_position' ] ?? $default_position ) );
        }

        if ( 'yes' === $config['show_quantity'] ) {
            $columns[] = array(
                'key'   => 'quantity',
                'label' => $labels['quantity'],
                'position' => $positions['quantity'],
            );
        }

        if ( 'yes' === $config['show_unit_price'] ) {
            $columns[] = array(
                'key'   => 'unit_price',
                'label' => $labels['unit_price'],
                'position' => $positions['unit_price'],
            );
        }

        if ( 'yes' === $config['show_discount'] ) {
            $columns[] = array(
                'key'   => 'discount',
                'label' => $labels['discount'],
                'position' => $positions['discount'],
            );
        }

        if ( 'yes' === $config['show_discounted_unit_price'] ) {
            $columns[] = array(
                'key'   => 'discounted_unit_price',
                'label' => $labels['discounted_unit_price'],
                'position' => $positions['discounted_unit_price'],
            );
        }

        if ( 'yes' === $config['show_line_total'] ) {
            $columns[] = array(
                'key'   => 'line_total',
                'label' => $labels['line_total'],
                'position' => $positions['line_total'],
            );
        }

        usort(
            $columns,
            function ( $left, $right ) {
                $left_position  = absint( $left['position'] ?? 0 );
                $right_position = absint( $right['position'] ?? 0 );

                if ( $left_position === $right_position ) {
                    return strcmp( (string) ( $left['key'] ?? '' ), (string) ( $right['key'] ?? '' ) );
                }

                return $left_position <=> $right_position;
            }
        );

        return $columns;
    }

    public static function get_document_total_rows( $totals, $document_type = 'order' ) {
        $config = self::get_document_display_settings( $document_type );
        $rows   = array();

        if ( 'yes' === $config['show_total_subtotal'] ) {
            $rows[] = array(
                'key'   => 'subtotal',
                'label' => __( 'Subtotal', EOP_TEXT_DOMAIN ),
                'raw'   => (float) ( $totals['items_subtotal'] ?? 0 ),
                'value' => wc_price( (float) ( $totals['items_subtotal'] ?? 0 ) ),
                'class' => '',
            );
        }

        if ( 'yes' === $config['show_total_shipping'] ) {
            $rows[] = array(
                'key'   => 'shipping',
                'label' => __( 'Frete', EOP_TEXT_DOMAIN ),
                'raw'   => (float) ( $totals['shipping_total'] ?? 0 ),
                'value' => wc_price( (float) ( $totals['shipping_total'] ?? 0 ) ),
                'class' => '',
            );
        }

        $tax_total = isset( $totals['tax_total'] ) ? (float) $totals['tax_total'] : 0.0;

        if ( $tax_total > 0.0 ) {
            $rows[] = array(
                'key'   => 'tax',
                'label' => __( 'Impostos', EOP_TEXT_DOMAIN ),
                'raw'   => $tax_total,
                'value' => wc_price( $tax_total ),
                'class' => '',
            );
        }

        if ( 'yes' === $config['show_total_discount'] ) {
            $rows[] = array(
                'key'   => 'discount',
                'label' => __( 'Desconto', EOP_TEXT_DOMAIN ),
                'raw'   => (float) ( $totals['discount_total'] ?? 0 ) * -1,
                'value' => wc_price( (float) ( $totals['discount_total'] ?? 0 ) * -1 ),
                'class' => '',
            );
        }

        $services_total = isset( $totals['services_total'] ) ? (float) $totals['services_total'] : 0.0;

        if ( $services_total > 0.0 ) {
            $rows[] = array(
                'key'   => 'services',
                'label' => __( 'Serviços', EOP_TEXT_DOMAIN ),
                'raw'   => $services_total,
                'value' => wc_price( $services_total ),
                'class' => '',
            );
        }

        if ( 'yes' === $config['show_total_total'] ) {
            $rows[] = array(
                'key'   => 'total',
                'label' => __( 'Total', EOP_TEXT_DOMAIN ),
                'raw'   => (float) ( $totals['grand_total'] ?? 0 ),
                'value' => wc_price( (float) ( $totals['grand_total'] ?? 0 ) ),
                'class' => 'is-grand',
            );
        }

        return $rows;
    }

    public static function get_cached_pdf_path( WC_Order $order, $document_type = '', $force_regenerate = false ) {
        $document_type = self::normalize_document_type( $document_type ? $document_type : self::detect_document_type( $order ) );

        if ( ! self::is_document_enabled( $order, $document_type ) ) {
            return '';
        }

        $upload_dir = wp_upload_dir();
        $base_dir   = trailingslashit( $upload_dir['basedir'] ) . 'eop-pdf';

        self::maybe_cleanup_cache_directories();

        if ( ! wp_mkdir_p( $base_dir ) ) {
            return '';
        }

        $version = (string) $order->get_meta( '_eop_pdf_' . $document_type . '_generated_at', true );
        $stamp   = '' !== $version ? sanitize_file_name( str_replace( array( ':', ' ' ), '-', $version ) ) : 'draft';
        $hash    = class_exists( 'EOP_PDF_Settings' ) ? substr( EOP_PDF_Settings::get_output_signature(), 0, 12 ) : '';
        $suffix  = implode( '-', array_filter( array( $stamp, $hash ) ) );
        $cache_dir = trailingslashit( $base_dir ) . sanitize_file_name( $suffix ?: 'current' );

        if ( ! wp_mkdir_p( $cache_dir ) ) {
            return '';
        }

        $path = trailingslashit( $cache_dir ) . self::get_document_filename( $order, $document_type );

        if ( ! $force_regenerate && file_exists( $path ) ) {
            return $path;
        }

        $binary = self::maybe_build_browser_pdf_document( $order, $document_type );

        if ( '' === $binary ) {
            $binary = self::build_pdf_document( $order, $document_type );
        }
        $result = file_put_contents( $path, $binary );

        if ( false === $result ) {
            return '';
        }

        return $path;
    }

    public static function maybe_invalidate_cached_documents( $old_value, $value ) {
        if ( ! class_exists( 'EOP_PDF_Settings' ) ) {
            return;
        }

        if ( EOP_PDF_Settings::get_output_signature( $old_value ) === EOP_PDF_Settings::get_output_signature( $value ) ) {
            return;
        }

        self::purge_cached_documents();
    }

    private static function purge_cached_documents() {
        $upload_dir = wp_upload_dir();

        if ( empty( $upload_dir['basedir'] ) ) {
            return;
        }

        $base_dir = trailingslashit( $upload_dir['basedir'] ) . 'eop-pdf';

        if ( ! is_dir( $base_dir ) ) {
            return;
        }

        $entries = scandir( $base_dir );

        if ( false === $entries ) {
            return;
        }

        foreach ( $entries as $entry ) {
            if ( '.' === $entry || '..' === $entry ) {
                continue;
            }

            $path = trailingslashit( $base_dir ) . $entry;

            if ( is_dir( $path ) ) {
                self::delete_directory_recursive( $path );
            } elseif ( is_file( $path ) ) {
                @unlink( $path );
            }
        }
    }

    public static function get_recent_orders( $limit = 20 ) {
        return wc_get_orders(
            array(
                'limit'       => max( 1, absint( $limit ) ),
                'status'      => array_keys( wc_get_order_statuses() ),
                'created_via' => 'aireset-expresso-order',
                'orderby'     => 'date',
                'order'       => 'DESC',
            )
        );
    }

    public static function get_preview_order( $order_id = 0 ) {
        $order_id = absint( $order_id );

        if ( $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order instanceof WC_Order && 'aireset-expresso-order' === (string) $order->get_created_via() ) {
                return $order;
            }
        }

        $orders = self::get_recent_orders( 1 );

        if ( empty( $orders[0] ) || ! $orders[0] instanceof WC_Order ) {
            return null;
        }

        return $orders[0];
    }

    public static function is_edocument_enabled() {
        $settings = self::get_pdf_settings();

        return isset( $settings['edoc_enabled'] ) && 'yes' === (string) $settings['edoc_enabled'];
    }

    public static function get_edocument_xml_preview( WC_Order $order, $document_type = '' ) {
        if ( ! self::is_edocument_enabled() || ! self::is_expresso_order( $order ) ) {
            return '';
        }

        $settings      = self::get_pdf_settings();
        $document_type = self::resolve_document_type_for_order( $order, $document_type ? $document_type : self::detect_document_type( $order ) );
        $format        = isset( $settings['edoc_format'] ) ? sanitize_key( (string) $settings['edoc_format'] ) : 'ubl';
        $format        = in_array( $format, array( 'ubl', 'cii', 'peppol' ), true ) ? $format : 'ubl';

        if ( 'cii' === $format ) {
            return self::build_cii_xml_document( $order, $document_type, $settings );
        }

        return self::build_ubl_xml_document( $order, $document_type, $settings, 'peppol' === $format );
    }

    public static function get_edocument_filename( WC_Order $order, $document_type = '' ) {
        $document_type = self::normalize_document_type( $document_type ? $document_type : self::detect_document_type( $order ) );

        return self::get_document_filename_base( $order, $document_type ) . '.xml';
    }

    public static function purge_cached_documents_manually() {
        self::purge_cached_documents();
    }

    public static function reset_document_counters() {
        if ( ! class_exists( 'EOP_PDF_Settings' ) ) {
            return;
        }

        $settings = EOP_PDF_Settings::get_all();

        foreach ( array( 'order', 'proposal' ) as $document_type ) {
            $settings[ $document_type . '_next_number' ]     = '1';
            $settings[ $document_type . '_last_reset_year' ] = '0';
        }

        update_option( EOP_PDF_Settings::OPTION_KEY, $settings );
    }

    public static function get_preview_html( WC_Order $order, $document_type = '', $embed_assets = false ) {
        $settings         = self::get_pdf_settings();
        $document_type    = self::normalize_document_type( $document_type ? $document_type : self::detect_document_type( $order ) );
        $document_config  = self::get_document_display_settings( $document_type );
        $visual_settings  = self::get_document_visual_settings( $document_type );
        $visible_columns  = self::get_document_item_columns( $document_type );
        $column_labels    = self::get_document_item_labels( $document_type );
        $line_items       = self::get_order_line_items_display_data( $order );
        $discount_suffix  = self::get_document_discount_suffix( $document_type );
        $discount_display_mode = self::get_document_discount_display_mode( $document_type );
        $shop_name        = trim( (string) $settings['shop_name'] );
        $shop_logo_url    = self::get_document_logo_source( $settings, $embed_assets );
        $shop_logo_height = self::sanitize_css_measurement( $settings['shop_logo_height'] ?? '' );
        $shop_identity    = self::get_shop_identity_lines( $settings );
        $customer_name    = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        $date             = $order->get_date_created();
        $totals           = EOP_Order_Creator::sync_order_totals( $order );
        $document_number  = self::get_document_number( $order, $document_type, false );
        $template_name    = sanitize_html_class( (string) $settings['template_name'] );
        $paper_size       = strtoupper( (string) $settings['paper_size'] );
        $ink_saving       = 'yes' === $settings['ink_saving_mode'];
        $show_email       = 'yes' === $document_config['show_email'];
        $show_phone       = 'yes' === $document_config['show_phone'];
        $show_shipping    = 'yes' === $document_config['show_shipping'];
        $show_billing     = 'yes' === $document_config['show_billing'];
        $show_notes       = 'yes' === $document_config['show_notes'];
        $show_sku         = 'yes' === $document_config['show_sku'];
        $show_item_index  = 'yes' === $document_config['show_item_index'];
        $test_mode        = ! empty( $settings['test_mode'] ) && 'yes' === (string) $settings['test_mode'];
        $total_rows       = self::get_document_total_rows( $totals, $document_type );
        $billing_label    = self::get_billing_label( $order );
        $preview_style    = implode(
            '; ',
            array(
                '--eop-doc-header-bg: ' . $visual_settings['header_background_color'],
                '--eop-doc-header-text: ' . $visual_settings['header_text_color'],
                '--eop-doc-body-text: ' . $visual_settings['body_text_color'],
                '--eop-doc-muted-text: ' . $visual_settings['muted_text_color'],
                '--eop-doc-border-color: ' . $visual_settings['border_color'],
                '--eop-doc-title-size: ' . absint( $visual_settings['title_font_size'] ) . 'px',
                '--eop-doc-meta-size: ' . absint( $visual_settings['meta_font_size'] ) . 'px',
                '--eop-doc-table-header-size: ' . absint( $visual_settings['table_header_font_size'] ) . 'px',
                '--eop-doc-table-body-size: ' . absint( $visual_settings['table_body_font_size'] ) . 'px',
                '--eop-doc-table-body-line-height: ' . self::sanitize_pdf_line_height( $visual_settings['table_body_line_height'] ?? '1.35', '1.35' ),
                '--eop-doc-totals-size: ' . absint( $visual_settings['totals_font_size'] ) . 'px',
                '--eop-doc-note-size: ' . absint( $visual_settings['note_font_size'] ) . 'px',
            )
        );

        ob_start();
        ?>
        <div class="eop-pdf-preview eop-pdf-preview--<?php echo esc_attr( $template_name ); ?><?php echo $ink_saving ? ' is-ink-saving' : ''; ?>" style="<?php echo esc_attr( $preview_style ); ?>">
            <div class="eop-pdf-preview__sheet eop-pdf-preview__sheet--<?php echo esc_attr( strtolower( $paper_size ) ); ?>">
                <?php if ( $test_mode ) : ?>
                    <div class="eop-pdf-preview__watermark"><?php esc_html_e( 'MODO DE TESTE', EOP_TEXT_DOMAIN ); ?></div>
                <?php endif; ?>
                <div class="eop-pdf-preview__header">
                    <div class="eop-pdf-preview__brand">
                        <?php if ( $shop_logo_url ) : ?>
                            <img class="eop-pdf-preview__logo" src="<?php echo 0 === strpos( $shop_logo_url, 'data:image/' ) ? esc_attr( $shop_logo_url ) : esc_url( $shop_logo_url ); ?>" style="max-height: <?php echo esc_attr( $shop_logo_height ); ?>;" alt="">
                        <?php endif; ?>
                        <div>
                            <strong><?php echo esc_html( $shop_name ?: get_bloginfo( 'name' ) ); ?></strong>
                            <?php foreach ( $shop_identity as $identity_line ) : ?>
                                <span><?php echo esc_html( $identity_line ); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="eop-pdf-preview__meta">
                        <h3><?php echo esc_html( 'proposal' === $document_type ? __( 'PROPOSTA', EOP_TEXT_DOMAIN ) : __( 'PEDIDO', EOP_TEXT_DOMAIN ) ); ?></h3>
                        <div><?php esc_html_e( 'Número do documento:', EOP_TEXT_DOMAIN ); ?> <strong><?php echo esc_html( $document_number ); ?></strong></div>
                        <div><?php esc_html_e( 'Data:', EOP_TEXT_DOMAIN ); ?> <?php echo esc_html( $date ? $date->date_i18n( 'd/m/Y' ) : '—' ); ?></div>
                        <div><?php esc_html_e( 'Pedido WooCommerce:', EOP_TEXT_DOMAIN ); ?> #<?php echo esc_html( $order->get_id() ); ?></div>
                    </div>
                </div>

                <div class="eop-pdf-preview__summary">
                    <div>
                        <span><?php esc_html_e( 'Cliente', EOP_TEXT_DOMAIN ); ?></span>
                        <strong><?php echo esc_html( $customer_name ?: __( 'Não informado', EOP_TEXT_DOMAIN ) ); ?></strong>
                        <?php if ( $show_email && $order->get_billing_email() ) : ?>
                            <small><?php echo esc_html( $order->get_billing_email() ); ?></small>
                        <?php endif; ?>
                        <?php if ( $show_phone && $order->get_billing_phone() ) : ?>
                            <small><?php echo esc_html( $order->get_billing_phone() ); ?></small>
                        <?php endif; ?>
                    </div>
                    <?php if ( $show_shipping ) : ?>
                        <div>
                            <span><?php esc_html_e( 'Entrega', EOP_TEXT_DOMAIN ); ?></span>
                            <strong><?php echo esc_html( self::get_shipping_label( $order ) ); ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ( $show_billing && '' !== $billing_label ) : ?>
                        <div>
                            <span><?php esc_html_e( 'Cobrança', EOP_TEXT_DOMAIN ); ?></span>
                            <strong><?php echo esc_html( $billing_label ); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <table class="eop-pdf-preview__table">
                    <thead>
                        <tr>
                            <?php if ( $show_item_index ) : ?>
                                <th class="eop-pdf-preview__cell eop-pdf-preview__cell--item-index"><?php echo esc_html( $column_labels['item_index'] ); ?></th>
                            <?php endif; ?>
                            <th><?php echo esc_html( $column_labels['product'] ); ?></th>
                            <?php foreach ( $visible_columns as $column ) : ?>
                                <th class="eop-pdf-preview__cell eop-pdf-preview__cell--<?php echo esc_attr( $column['key'] ); ?>"><?php echo esc_html( $column['label'] ); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $line_items as $index => $line_item ) : ?>
                            <?php
                            $item    = $line_item['item'];
                            $product = $line_item['product'];
                            $discount_line = trim( wp_strip_all_tags( $discount_suffix ) );
                            ?>
                            <tr>
                                <?php if ( $show_item_index ) : ?>
                                    <td class="eop-pdf-preview__cell eop-pdf-preview__cell--item-index"><?php echo esc_html( $index + 1 ); ?></td>
                                <?php endif; ?>
                                <td>
                                    <strong><?php echo esc_html( $item->get_name() ); ?></strong>
                                    <?php if ( $show_sku && $product && $product->get_sku() ) : ?>
                                        <small><?php printf( esc_html__( 'SKU: %s', EOP_TEXT_DOMAIN ), esc_html( $product->get_sku() ) ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <?php foreach ( $visible_columns as $column ) : ?>
                                    <td class="eop-pdf-preview__cell eop-pdf-preview__cell--<?php echo esc_attr( $column['key'] ); ?>">
                                        <?php if ( 'quantity' === $column['key'] ) : ?>
                                            <?php echo esc_html( $line_item['quantity'] ); ?>
                                        <?php elseif ( 'unit_price' === $column['key'] ) : ?>
                                            <span class="eop-pdf-preview__money"><?php echo wp_kses_post( wc_price( $line_item['unit_price'] ) ); ?></span>
                                        <?php elseif ( 'discount' === $column['key'] ) : ?>
                                            <?php if ( 'both' === $discount_display_mode ) : ?>
                                                <span class="eop-pdf-preview__discount-line">
                                                    <strong><?php echo esc_html( self::format_percentage( $line_item['discount_percent'] ) ); ?></strong>
                                                    <span class="eop-pdf-preview__discount-meta">
                                                        <span class="eop-pdf-preview__money"><?php echo wp_kses_post( wc_price( $line_item['discount_per_unit'] ) ); ?></span>
                                                        <?php if ( '' !== $discount_line ) : ?>
                                                            <?php echo ' ' . esc_html( $discount_line ); ?>
                                                        <?php endif; ?>
                                                    </span>
                                                </span>
                                            <?php else : ?>
                                                <span class="eop-pdf-preview__money"><?php echo esc_html( self::format_discount_column_value( $line_item, $document_type ) ); ?></span>
                                            <?php endif; ?>
                                        <?php elseif ( 'discounted_unit_price' === $column['key'] ) : ?>
                                            <span class="eop-pdf-preview__money"><?php echo esc_html( self::format_discounted_unit_price_value( $line_item, $document_type ) ); ?></span>
                                        <?php elseif ( 'line_total' === $column['key'] ) : ?>
                                            <span class="eop-pdf-preview__money"><?php echo wp_kses_post( wc_price( $line_item['line_total'] ) ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ( ! empty( $total_rows ) ) : ?>
                    <div class="eop-pdf-preview__totals">
                        <?php foreach ( $total_rows as $row ) : ?>
                            <div class="<?php echo esc_attr( $row['class'] ); ?>">
                                <span><?php echo esc_html( $row['label'] ); ?></span>
                                <strong><?php echo wp_kses_post( $row['value'] ); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ( $show_notes && $order->get_customer_note() ) : ?>
                    <div class="eop-pdf-preview__notes">
                        <span><?php esc_html_e( 'Observações', EOP_TEXT_DOMAIN ); ?></span>
                        <p><?php echo esc_html( $order->get_customer_note() ); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $settings['shop_footer'] ) ) : ?>
                    <div class="eop-pdf-preview__footer"><?php echo esc_html( $settings['shop_footer'] ); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    public static function handle_download() {
        $document_type = self::normalize_document_type( isset( $_GET['document'] ) ? wp_unslash( $_GET['document'] ) : '' );
		$token         = self::get_public_request_token();
        $order_id      = absint( $_GET['order_id'] ?? 0 );
        $force_download = isset( $_GET['download'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['download'] ) );
        $order         = $order_id ? wc_get_order( $order_id ) : null;

        if ( ! $order instanceof WC_Order && '' !== $token ) {
            $order = self::get_order_by_token( $token, $document_type );
        }

        if ( ! $order instanceof WC_Order ) {
            wp_die( esc_html__( 'Documento não encontrado.', EOP_TEXT_DOMAIN ) );
        }

        if ( ! self::is_expresso_order( $order ) ) {
            wp_die( esc_html__( 'Documento indisponível para este pedido.', EOP_TEXT_DOMAIN ) );
        }

        $document_type = self::resolve_document_type_for_order( $order, $document_type );

        $is_public = self::is_public_token_request( $order, $document_type, $token );

        if ( $is_public ) {
            if ( ! self::can_access_via_token( $order, $document_type, $token ) ) {
                wp_die( esc_html__( 'Documento indisponível.', EOP_TEXT_DOMAIN ) );
            }
        } else {
            if ( self::requires_private_nonce() ) {
                $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

                if ( ! wp_verify_nonce( $nonce, 'eop_download_pdf_' . $order->get_id() . '_' . $document_type ) ) {
                    wp_die( esc_html__( 'Link de documento inválido.', EOP_TEXT_DOMAIN ) );
                }
            }

            if ( current_user_can( 'edit_shop_orders' ) ) {
                if ( ! self::current_user_can_access_order( $order ) ) {
                    wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
                }
            } elseif ( ! self::can_customer_download( $order, $document_type ) ) {
                wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
            }
        }

        if ( ! self::is_document_enabled( $order, $document_type ) ) {
            wp_die( esc_html__( 'Documento desativado para este pedido.', EOP_TEXT_DOMAIN ) );
        }

        self::store_document_meta( $order, $document_type, $is_public );
        self::maybe_mark_document_printed( $order, $document_type, $is_public );
        self::get_document_number( $order, $document_type, true );

        $binary = self::maybe_build_browser_pdf_document( $order, $document_type );

        if ( '' === $binary ) {
            $binary = self::build_pdf_document( $order, $document_type );
        }

        $filename = self::get_document_filename( $order, $document_type );
        $disposition = $force_download ? 'attachment' : self::get_content_disposition();
        $discarded_output = self::discard_buffered_output();

        if ( '' !== trim( wp_strip_all_tags( $discarded_output ) ) ) {
            self::log_document_event(
                $order,
                $document_type,
                sprintf(
                    /* translators: %d: number of discarded bytes before the PDF response */
                    __( 'Saída inesperada descartada antes do PDF (%d bytes).', EOP_TEXT_DOMAIN ),
                    strlen( $discarded_output )
                )
            );
        }

        self::log_document_event(
            $order,
            $document_type,
            sprintf(
                /* translators: 1: document type, 2: access mode */
                __( 'Documento %1$s gerado com acesso %2$s.', EOP_TEXT_DOMAIN ),
                $document_type,
                $is_public ? 'token' : 'restrito'
            )
        );

        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: ' . $disposition . '; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $binary ) );
        header( 'Cache-Control: private, max-age=0, must-revalidate' );
        header( 'Pragma: public' );

        echo $binary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
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

    private static function get_public_request_token() {
        if ( isset( $_GET[ self::PUBLIC_TOKEN_QUERY_VAR ] ) ) {
            return sanitize_text_field( wp_unslash( $_GET[ self::PUBLIC_TOKEN_QUERY_VAR ] ) );
        }

        if ( isset( $_GET['token'] ) ) {
            return sanitize_text_field( wp_unslash( $_GET['token'] ) );
        }

        return '';
    }

    private static function public_token_matches_order( WC_Order $order, $token ) {
        return 'yes' === $order->get_meta( '_eop_is_proposal', true ) && hash_equals( (string) $order->get_meta( '_eop_public_token', true ), (string) $token );
    }

    private static function get_order_by_token( $token, $document_type = 'order' ) {
        global $wpdb;

        $meta_keys = array_unique(
            array_filter(
                array(
                    '_eop_public_token',
                    self::get_private_access_token_meta_key( $document_type ),
                    'proposal' === self::normalize_document_type( $document_type ) ? self::get_private_access_token_meta_key( 'proposal' ) : '',
                )
            )
        );

        if ( empty( $meta_keys ) ) {
            return null;
        }

        $placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
        $query        = "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ({$placeholders}) AND meta_value = %s ORDER BY meta_id DESC LIMIT 1";
        $prepared     = $wpdb->prepare( $query, array_merge( $meta_keys, array( $token ) ) );
        $order_id     = absint( $wpdb->get_var( $prepared ) );

        return $order_id ? wc_get_order( $order_id ) : null;
    }

    private static function detect_document_type( WC_Order $order ) {
        return 'yes' === $order->get_meta( '_eop_is_proposal', true ) ? 'proposal' : 'order';
    }

    private static function normalize_document_type( $document_type ) {
        return 'proposal' === sanitize_key( (string) $document_type ) ? 'proposal' : 'order';
    }

    private static function store_document_meta( WC_Order $order, $document_type, $is_public ) {
        $suffix = 'proposal' === $document_type ? 'proposal' : 'order';

        $order->update_meta_data( '_eop_pdf_' . $suffix . '_generated_at', current_time( 'mysql' ) );
        $order->update_meta_data( '_eop_pdf_' . $suffix . '_generated_by', get_current_user_id() );
        $order->update_meta_data( '_eop_pdf_' . $suffix . '_access', $is_public ? 'public' : 'private' );
        $order->save();
    }

    private static function get_document_filename( WC_Order $order, $document_type ) {
        return self::get_document_filename_base( $order, $document_type ) . '.pdf';
    }

    private static function get_document_filename_base( WC_Order $order, $document_type ) {
        $document_type = self::normalize_document_type( $document_type );
        $prefix        = 'proposal' === $document_type ? 'proposta' : 'pedido';

        return sanitize_file_name( $prefix . '-' . $order->get_id() );
    }

    private static function build_pdf_document( WC_Order $order, $document_type ) {
        $settings        = self::get_pdf_settings();
        $pages           = array();
        $content         = '';
        $y               = 0;
        $page_left       = 46;
        $page_right      = 549;
        $content_width   = $page_right - $page_left;
        $document_config = self::get_document_display_settings( $document_type );
        $visual_settings = self::get_document_visual_settings( $document_type );
        $totals          = EOP_Order_Creator::sync_order_totals( $order );
        $line_items      = self::get_order_line_items_display_data( $order );
        $total_rows      = self::get_document_total_rows( $totals, $document_type );
        $columns         = self::get_document_item_columns( $document_type );
        $column_keys     = array_values( array_filter( wp_list_pluck( $columns, 'key' ) ) );
        $column_lookup   = array_fill_keys( $column_keys, true );
        $column_positions = self::get_pdf_table_positions( $column_keys );
        $column_labels   = self::get_document_item_labels( $document_type );
        $date            = $order->get_date_created();
        $document_number = self::get_document_number( $order, $document_type, false );
        $company_name    = trim( (string) $settings['shop_name'] );
        $company_lines   = self::get_shop_identity_lines( $settings );
        $footer_note     = trim( (string) $settings['shop_footer'] );
        $customer_name   = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        $show_sku        = 'yes' === $document_config['show_sku'];
        $show_item_index = 'yes' === $document_config['show_item_index'];
        $show_notes      = 'yes' === $document_config['show_notes'];
        $billing_label   = self::get_billing_label( $order );
        $test_mode       = ! empty( $settings['test_mode'] ) && 'yes' === (string) $settings['test_mode'];
        $title_font_size = self::scale_pdf_font_size( $visual_settings['title_font_size'], 18 );
        $meta_font_size  = self::scale_pdf_font_size( $visual_settings['meta_font_size'], 9 );
        $table_header_font_size = self::scale_pdf_font_size( $visual_settings['table_header_font_size'], 8 );
        $table_body_font_size   = self::scale_pdf_font_size( $visual_settings['table_body_font_size'], 9 );
        $table_body_line_height = self::sanitize_pdf_line_height( $visual_settings['table_body_line_height'] ?? '1.35', '1.35' );
        $totals_font_size       = self::scale_pdf_font_size( $visual_settings['totals_font_size'], 11 );
        $note_font_size         = self::scale_pdf_font_size( $visual_settings['note_font_size'], 9 );
        $company_font_size      = max( 12, $meta_font_size + 5 );
        $summary_label_size     = max( 8, $meta_font_size - 1 );
        $summary_value_size     = max( 10, $meta_font_size + 1 );
        $discount_secondary_size = max( 8, $table_body_font_size - 1 );
        $body_line_step         = max( $table_body_font_size + 1, (int) round( $table_body_font_size * max( 1.0, (float) $table_body_line_height ) ) );
        $header_background_color = $visual_settings['header_background_color'];
        $header_text_color       = $visual_settings['header_text_color'];
        $body_text_color         = $visual_settings['body_text_color'];
        $muted_text_color        = $visual_settings['muted_text_color'];
        $border_color            = $visual_settings['border_color'];
        $product_wrap_length     = $show_item_index ? 20 : 24;
        $item_index_center       = $page_left + 16;
        $product_x               = $show_item_index ? $page_left + 36 : $page_left + 12;

        $start_page = function () use ( &$pages, &$content, &$y ) {
            if ( '' !== $content ) {
                $pages[] = $content;
            }

            $content = '';
            $y       = 794;
        };

        $ensure_space = function ( $height = 18 ) use ( &$y, &$start_page ) {
            if ( $y - $height < 56 ) {
                $start_page();
            }
        };

        $estimate_text_width = function ( $text, $size, $font = 'F1' ) {
            $text   = wp_strip_all_tags( (string) $text );
            $factor = 'F2' === $font ? 0.56 : 0.5;

            return strlen( self::escape_pdf_text( $text ) ) * $size * $factor;
        };

        $draw_line = function ( $x1, $x2, $line_y, $color = '#d0d7e4', $width = 1 ) use ( &$content ) {
            $content .= sprintf(
                "q %s %s w %s %s m %s %s l S Q\n",
                self::get_pdf_stroke_color_operator( $color ),
                self::pdf_number( $width ),
                self::pdf_number( $x1 ),
                self::pdf_number( $line_y ),
                self::pdf_number( $x2 ),
                self::pdf_number( $line_y )
            );
        };

        $fill_rect = function ( $x, $top, $width, $height, $color = '#111111' ) use ( &$content ) {
            $content .= sprintf(
                "q %s %s %s %s %s re f Q\n",
                self::get_pdf_fill_color_operator( $color ),
                self::pdf_number( $x ),
                self::pdf_number( $top - $height ),
                self::pdf_number( $width ),
                self::pdf_number( $height )
            );
        };

        $add_text_at = function ( $text, $x, $line_y, $font = 'F1', $size = 11, $align = 'left', $color = '#172033' ) use ( &$content, $estimate_text_width ) {
            $text = trim( wp_strip_all_tags( (string) $text ) );

            if ( '' === $text ) {
                return;
            }

            $width = $estimate_text_width( $text, $size, $font );

            if ( 'right' === $align ) {
                $x -= $width;
            } elseif ( 'center' === $align ) {
                $x -= $width / 2;
            }

            $content .= sprintf(
                "q %s BT /%s %s Tf 1 0 0 1 %s %s Tm (%s) Tj ET Q\n",
                self::get_pdf_text_color_operator( $color ),
                $font,
                self::pdf_number( $size ),
                self::pdf_number( $x ),
                self::pdf_number( $line_y ),
                self::escape_pdf_text( $text )
            );
        };

        $start_page();

        $document_heading = 'proposal' === $document_type ? __( 'PROPOSTA', EOP_TEXT_DOMAIN ) : __( 'PEDIDO', EOP_TEXT_DOMAIN );
        $document_heading_y = $y + 10;
        $summary_blocks   = array();

        $summary_blocks[] = array(
            'label' => __( 'Cliente', EOP_TEXT_DOMAIN ),
            'value' => $customer_name ?: __( 'Não informado', EOP_TEXT_DOMAIN ),
            'extra' => array_filter(
                array(
                    'yes' === $document_config['show_email'] ? $order->get_billing_email() : '',
                    'yes' === $document_config['show_phone'] ? $order->get_billing_phone() : '',
                )
            ),
        );

        if ( 'yes' === $document_config['show_shipping'] ) {
            $summary_blocks[] = array(
                'label' => __( 'Entrega', EOP_TEXT_DOMAIN ),
                'value' => self::get_shipping_label( $order ),
                'extra' => array(),
            );
        }

        if ( 'yes' === $document_config['show_billing'] && '' !== $billing_label ) {
            $summary_blocks[] = array(
                'label' => __( 'Cobrança', EOP_TEXT_DOMAIN ),
                'value' => $billing_label,
                'extra' => array(),
            );
        }

        $ensure_space( 220 );

        $add_text_at( $company_name ?: get_bloginfo( 'name' ), $page_left, $y, 'F2', $company_font_size, 'left', $body_text_color );

        foreach ( $company_lines as $line_index => $company_line ) {
            $add_text_at( $company_line, $page_left, $y - 24 - ( $line_index * 14 ), 'F1', $meta_font_size, 'left', $muted_text_color );
        }

        $add_text_at( $document_heading, $page_right, $document_heading_y, 'F2', $title_font_size, 'right', $body_text_color );
        if ( $test_mode ) {
            $add_text_at( __( 'MODO DE TESTE', EOP_TEXT_DOMAIN ), $page_right, $y - 16, 'F2', max( 10, $meta_font_size ), 'right', '#c93535' );
        }
        $add_text_at( sprintf( __( 'Número do documento: %s', EOP_TEXT_DOMAIN ), $document_number ), $page_right, $y - 24, 'F1', $meta_font_size, 'right', $muted_text_color );
        $add_text_at( sprintf( __( 'Data: %s', EOP_TEXT_DOMAIN ), $date ? $date->date_i18n( 'd/m/Y' ) : '—' ), $page_right, $y - 40, 'F1', $meta_font_size, 'right', $muted_text_color );
        $add_text_at( sprintf( __( 'Pedido WooCommerce: #%s', EOP_TEXT_DOMAIN ), $order->get_id() ), $page_right, $y - 56, 'F1', $meta_font_size, 'right', $muted_text_color );

        $y -= max( 98, 70 + ( count( $company_lines ) * 14 ) );

        if ( ! empty( $summary_blocks ) ) {
            $block_count = count( $summary_blocks );
            $block_gap   = 18;
            $block_width = ( $content_width - ( $block_gap * max( 0, $block_count - 1 ) ) ) / max( 1, $block_count );
            $max_height  = 0;

            foreach ( $summary_blocks as $index => $block ) {
                $block_x    = $page_left + ( $index * ( $block_width + $block_gap ) );
                $line_y     = $y;
                $block_rows = 1 + count( $block['extra'] );

                $add_text_at( strtoupper( $block['label'] ), $block_x, $line_y, 'F2', $summary_label_size, 'left', $muted_text_color );
                $line_y -= 22;
                $add_text_at( $block['value'], $block_x, $line_y, 'F2', $summary_value_size, 'left', $body_text_color );
                $line_y -= 16;

                foreach ( $block['extra'] as $extra_line ) {
                    $add_text_at( $extra_line, $block_x, $line_y, 'F1', $meta_font_size, 'left', $muted_text_color );
                    $line_y -= 14;
                }

                $max_height = max( $max_height, 22 + ( $block_rows * 16 ) );
            }

            $y -= $max_height + 12;
        }

        $header_top    = $y;
        $header_height = max( 40, 32 + ( $table_header_font_size * 1.4 ) );
        $fill_rect( $page_left, $header_top, $content_width, $header_height, $header_background_color );

        if ( $show_item_index ) {
            $add_text_at( $column_labels['item_index'], $item_index_center, $header_top - 21, 'F2', $table_header_font_size, 'center', $header_text_color );
        }

        $add_text_at( $column_labels['product'], $product_x, $header_top - 21, 'F2', $table_header_font_size, 'left', $header_text_color );

        $render_column_header = function ( $label, $x ) use ( $header_top, $add_text_at, $table_header_font_size, $header_text_color ) {
            $lines      = array_slice( self::wrap_text( $label, 16 ), 0, 3 );
            $line_count = count( $lines );
            $start_gap  = 21 - max( 0, $line_count - 1 ) * 5;

            foreach ( $lines as $line_index => $line ) {
                $add_text_at( $line, $x, $header_top - $start_gap - ( $line_index * 11 ), 'F2', max( 7, $table_header_font_size - 1 ), 'center', $header_text_color );
            }
        };

        foreach ( $columns as $column ) {
            if ( isset( $column_positions[ $column['key'] ?? '' ] ) ) {
                $render_column_header( $column['label'], $column_positions[ $column['key'] ] );
            }
        }

        $y = $header_top - $header_height - 18;

        foreach ( $line_items as $index => $line_item ) {
            $product      = $line_item['product'];
            $product_name = $line_item['item']->get_name();
            $name_lines   = self::wrap_text( $product_name, $product_wrap_length );
            $meta_lines   = array();

            if ( isset( $column_lookup['quantity'] ) ) {
                $meta_lines['quantity'] = (string) $line_item['quantity'];
            }

            if ( isset( $column_lookup['unit_price'] ) ) {
                $meta_lines['unit_price'] = self::format_money( $line_item['unit_price'] );
            }

            if ( isset( $column_lookup['discount'] ) ) {
                $meta_lines['discount'] = self::format_discount_column_value( $line_item, $document_type );
            }

            if ( isset( $column_lookup['discounted_unit_price'] ) ) {
                $meta_lines['discounted_unit_price'] = self::format_discounted_unit_price_value( $line_item, $document_type );
            }

            if ( isset( $column_lookup['line_total'] ) ) {
                $meta_lines['line_total'] = self::format_money( $line_item['line_total'] );
            }

            $sku_line   = $show_sku && $product && $product->get_sku() ? sprintf( __( 'SKU: %s', EOP_TEXT_DOMAIN ), $product->get_sku() ) : '';
            $row_height = max( 26, count( $name_lines ) * $body_line_step + ( '' !== $sku_line ? $meta_font_size + 4 : 0 ), $table_body_font_size ) + 14;

            $ensure_space( $row_height + 18 );

            $row_top = $y;
            $line_y  = $row_top;

            foreach ( $name_lines as $name_line ) {
                $add_text_at( $name_line, $product_x, $line_y, 'F2', $table_body_font_size, 'left', $body_text_color );
                $line_y -= $body_line_step;
            }

            if ( '' !== $sku_line ) {
                $add_text_at( $sku_line, $product_x, $line_y - 1, 'F1', $meta_font_size, 'left', $muted_text_color );
            }

            if ( $show_item_index ) {
                $add_text_at( (string) ( $index + 1 ), $item_index_center, $row_top, 'F2', $table_body_font_size, 'center', $body_text_color );
            }

            foreach ( $columns as $column ) {
                $column_key = $column['key'] ?? '';
                $column_x   = $column_positions[ $column_key ] ?? null;

                if ( null === $column_x ) {
                    continue;
                }

                if ( 'quantity' === $column_key && isset( $meta_lines['quantity'] ) ) {
                    $add_text_at( $meta_lines['quantity'], $column_x, $row_top, 'F1', $table_body_font_size, 'center', $body_text_color );
                    continue;
                }

                if ( 'unit_price' === $column_key && isset( $meta_lines['unit_price'] ) ) {
                    $add_text_at( $meta_lines['unit_price'], $column_x, $row_top, 'F1', $table_body_font_size, 'center', $body_text_color );
                    continue;
                }

                if ( 'discount' === $column_key && isset( $meta_lines['discount'] ) ) {
                    $add_text_at( $meta_lines['discount'], $column_x, $row_top, 'F2', $discount_secondary_size, 'center', $body_text_color );

                    continue;
                }

                if ( 'discounted_unit_price' === $column_key && isset( $meta_lines['discounted_unit_price'] ) ) {
                    $add_text_at( $meta_lines['discounted_unit_price'], $column_x, $row_top, 'F1', $table_body_font_size, 'center', $body_text_color );
                    continue;
                }

                if ( 'line_total' === $column_key && isset( $meta_lines['line_total'] ) ) {
                    $add_text_at( $meta_lines['line_total'], $column_x, $row_top, 'F2', $table_body_font_size, 'center', $body_text_color );
                }
            }

            $row_bottom = $row_top - $row_height;
            $draw_line( $page_left, $page_right, $row_bottom, $border_color, 0.8 );
            $y = $row_bottom - 12;
        }

        if ( ! empty( $total_rows ) ) {
            $totals_y = $y - 2;

            foreach ( $total_rows as $row ) {
                $is_grand = 'is-grand' === $row['class'];

                if ( $is_grand ) {
                    $draw_line( 360, $page_right, $totals_y + 10, $body_text_color, 1.2 );
                }

                $add_text_at( $is_grand ? __( 'Total', EOP_TEXT_DOMAIN ) : $row['label'], 360, $totals_y, $is_grand ? 'F2' : 'F1', $is_grand ? $totals_font_size + 3 : $totals_font_size, 'left', $body_text_color );
                $add_text_at( self::format_money( $row['raw'] ?? 0 ), $page_right, $totals_y, 'F2', $is_grand ? $totals_font_size + 3 : $totals_font_size, 'right', $body_text_color );
                $totals_y -= $is_grand ? ( $totals_font_size + 9 ) : ( $totals_font_size + 5 );
            }

            $y = $totals_y - 8;
        }

        if ( 'proposal' === $document_type ) {
            $confirmation = 'yes' === $order->get_meta( '_eop_proposal_confirmed', true ) ? __( 'Situação da proposta: Confirmada', EOP_TEXT_DOMAIN ) : __( 'Situação da proposta: Aguardando confirmação', EOP_TEXT_DOMAIN );
            $add_text_at( $confirmation, $page_left, $y, 'F1', $note_font_size, 'left', $muted_text_color );
            $y -= 18;
        }

        $notes = trim( (string) $order->get_customer_note() );
        if ( $show_notes && '' !== $notes ) {
            $add_text_at( __( 'Observações', EOP_TEXT_DOMAIN ), $page_left, $y, 'F2', max( 10, $note_font_size + 1 ), 'left', $body_text_color );
            $y -= 16;

            foreach ( self::wrap_text( $notes, 78 ) as $note_line ) {
                $add_text_at( $note_line, $page_left, $y, 'F1', $note_font_size, 'left', $muted_text_color );
                $y -= 14;
            }
        }

        if ( '' !== $footer_note ) {
            $y -= 4;

            foreach ( self::wrap_text( $footer_note, 82 ) as $footer_line ) {
                $add_text_at( $footer_line, $page_left, $y, 'F1', $note_font_size, 'left', $muted_text_color );
                $y -= 12;
            }
        }

        if ( '' !== $content ) {
            $pages[] = $content;
        }

        return self::render_pdf_binary( $pages );
    }

    private static function maybe_build_browser_pdf_document( WC_Order $order, $document_type ) {
        $binary = self::maybe_build_dompdf_document( $order, $document_type );

        if ( '' !== $binary ) {
            return $binary;
        }

        self::log_document_event( $order, $document_type, __( 'Fallback para browser headless acionado após falha ou indisponibilidade do Dompdf.', EOP_TEXT_DOMAIN ) );

        return self::maybe_build_headless_browser_pdf_document( $order, $document_type );
    }

    private static function maybe_build_dompdf_document( WC_Order $order, $document_type ) {
        if ( ! self::ensure_reference_dompdf_loaded() ) {
            return '';
        }

        if ( ! class_exists( '\\WPO\\IPS\\Vendor\\Dompdf\\Dompdf' ) || ! class_exists( '\\WPO\\IPS\\Vendor\\Dompdf\\Options' ) ) {
            return '';
        }

        $html = self::get_preview_print_document_html( $order, $document_type );

        if ( '' === $html ) {
            return '';
        }

        $settings      = self::get_pdf_settings();
        $runtime_paths = self::get_dompdf_runtime_paths();

        if ( empty( $runtime_paths['temp'] ) || empty( $runtime_paths['fonts'] ) ) {
            return '';
        }

        $options = apply_filters(
            'eop_pdf_dompdf_options',
            array(
                'tempDir'                 => $runtime_paths['temp'],
                'fontDir'                 => $runtime_paths['fonts'],
                'fontCache'               => $runtime_paths['fonts'],
                'chroot'                  => self::get_dompdf_chroot_paths( $runtime_paths ),
                'logOutputFile'           => trailingslashit( $runtime_paths['temp'] ) . 'log.htm',
                'defaultFont'             => 'dejavu sans',
                'isRemoteEnabled'         => true,
                'isHtml5ParserEnabled'    => true,
                'isFontSubsettingEnabled' => ! empty( $settings['font_subsetting'] ) && 'yes' === (string) $settings['font_subsetting'],
            ),
            $order,
            $document_type,
            $runtime_paths
        );

        try {
            $dompdf = new \WPO\IPS\Vendor\Dompdf\Dompdf( new \WPO\IPS\Vendor\Dompdf\Options( $options ) );
            $dompdf->loadHtml( $html );
            $dompdf->setPaper( isset( $settings['paper_size'] ) && 'letter' === $settings['paper_size'] ? 'letter' : 'A4', 'portrait' );
            $dompdf->render();

            $binary = $dompdf->output();

            if ( ! is_string( $binary ) || '' === $binary ) {
                return '';
            }

            $pdf_file = trailingslashit( $runtime_paths['temp'] ) . self::get_document_filename( $order, $document_type );

            @unlink( $pdf_file );

            if ( false === file_put_contents( $pdf_file, $binary ) ) {
                return $binary;
            }

            $stored_binary = file_get_contents( $pdf_file );

            @unlink( $pdf_file );

            return false === $stored_binary ? $binary : $stored_binary;
        } catch ( \Throwable $throwable ) {
            self::log_document_event( $order, $document_type, sprintf( __( 'Dompdf falhou: %s', EOP_TEXT_DOMAIN ), $throwable->getMessage() ) );
            return '';
        }
    }

    private static function maybe_build_headless_browser_pdf_document( WC_Order $order, $document_type ) {
        $browser = self::get_browser_pdf_executable();

        if ( '' === $browser || ! function_exists( 'exec' ) ) {
            return '';
        }

        $runtime_paths = self::get_dompdf_runtime_paths();

        if ( empty( $runtime_paths['temp'] ) ) {
            return '';
        }

        $file_base = self::get_document_filename_base( $order, $document_type );
        $html_file = trailingslashit( $runtime_paths['temp'] ) . $file_base . '.html';
        $pdf_file  = trailingslashit( $runtime_paths['temp'] ) . self::get_document_filename( $order, $document_type );

        @unlink( $pdf_file );
        @unlink( $html_file );

        $html = self::get_preview_print_document_html( $order, $document_type );

        if ( '' === $html ) {
            @unlink( $html_file );
            return '';
        }

        if ( false === file_put_contents( $html_file, $html ) ) {
            @unlink( $html_file );
            return '';
        }

        $command = sprintf(
            '%s --headless --disable-gpu --run-all-compositor-stages-before-draw --virtual-time-budget=2000 --print-to-pdf=%s --print-to-pdf-no-header %s %s',
            escapeshellarg( $browser ),
            escapeshellarg( $pdf_file ),
            escapeshellarg( self::path_to_file_url( $html_file ) ),
            self::get_headless_browser_stderr_redirect()
        );

        $output = array();
        $status = 1;

        @exec( $command, $output, $status );

        if ( 0 !== (int) $status || ! file_exists( $pdf_file ) ) {
            self::log_document_event( $order, $document_type, __( 'Headless browser não conseguiu gerar o PDF.', EOP_TEXT_DOMAIN ) );
            @unlink( $html_file );
            @unlink( $pdf_file );
            return '';
        }

        $binary = file_get_contents( $pdf_file );

        @unlink( $html_file );
        @unlink( $pdf_file );

        return false === $binary ? '' : $binary;
    }

    private static function get_headless_browser_stderr_redirect() {
        return strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' ? '2>NUL' : '2>/dev/null';
    }

    private static function get_preview_print_document_html( WC_Order $order, $document_type ) {
        $settings   = self::get_pdf_settings();
        $font_url   = method_exists( 'EOP_Settings', 'get_font_stylesheet_url' ) ? EOP_Settings::get_font_stylesheet_url( $settings['font_family'] ?? '' ) : '';
        $font_css   = method_exists( 'EOP_Settings', 'get_font_css_family' ) ? EOP_Settings::get_font_css_family( $settings['font_family'] ?? '' ) : "'Segoe UI', sans-serif";
        $paper_size = isset( $settings['paper_size'] ) && 'letter' === $settings['paper_size'] ? 'Letter' : 'A4';
        $visual     = self::get_document_visual_settings( $document_type );
        $css_path   = EOP_PLUGIN_DIR . 'assets/css/pdf-admin.css';
        $css        = file_exists( $css_path ) ? (string) file_get_contents( $css_path ) : '';
        $preview    = self::get_preview_html( $order, $document_type, true );

        if ( '' === $preview ) {
            return '';
        }

        $css .= "\n@page { size: {$paper_size}; margin: 6mm; }\n";
        $css .= "html, body { margin: 0; padding: 0; background: #ffffff; font-family: {$font_css}; }\n";
        $css .= "body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }\n";
        $css .= ".eop-pdf-preview { padding: 0 !important; }\n";
        $css .= ".eop-pdf-preview__sheet { max-width: none !important; width: auto !important; margin: 0 auto !important; padding: 18px 0 !important; box-shadow: none !important; }\n";
        $css .= ".eop-pdf-preview__sheet--a4 { max-width: none !important; }\n";
        $css .= ".eop-pdf-preview__sheet--letter { max-width: none !important; }\n";
        $css .= ".eop-pdf-preview__table thead th { background-color: " . $visual['header_background_color'] . " !important; color: " . $visual['header_text_color'] . " !important; }\n";

        return '<!doctype html><html><head><meta charset="utf-8">'
            . ( $font_url ? '<link rel="stylesheet" href="' . esc_url( $font_url ) . '">' : '' )
            . '<style>' . $css . '</style></head><body>' . $preview . '</body></html>';
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

    private static function get_dompdf_runtime_paths() {
        $upload_dir = wp_upload_dir();

        if ( empty( $upload_dir['basedir'] ) ) {
            return array();
        }

        $base_dir = trailingslashit( $upload_dir['basedir'] ) . 'eop-pdf/dompdf';
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

    private static function get_dompdf_chroot_paths( $runtime_paths ) {
        $upload_dir = wp_upload_dir();
        $paths      = array_filter(
            array(
                WP_CONTENT_DIR,
                WP_PLUGIN_DIR,
                EOP_PLUGIN_DIR,
                $upload_dir['basedir'] ?? '',
                $runtime_paths['base'] ?? '',
            )
        );

        return array_values( array_unique( array_map( 'wp_normalize_path', $paths ) ) );
    }

    private static function get_browser_pdf_executable() {
        $candidates = apply_filters(
            'eop_pdf_browser_binaries',
            array(
                'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
                'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
                'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
                'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
                '/usr/bin/google-chrome',
                '/usr/bin/chromium',
                '/usr/bin/chromium-browser',
                '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            )
        );

        foreach ( (array) $candidates as $candidate ) {
            $candidate = (string) $candidate;

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

    public static function get_order_line_items_display_data( WC_Order $order ) {
        $rows                = array();
        $fee_discount_total  = 0.0;
        $line_total_pool     = 0.0;
        $decimals            = wc_get_price_decimals();
        $order_items         = $order->get_items( 'line_item' );
        $line_items_count    = count( $order_items );

        foreach ( $order->get_fees() as $fee ) {
            if ( (float) $fee->get_total() < 0 ) {
                $fee_discount_total += abs( (float) $fee->get_total() );
            }
        }

        foreach ( $order_items as $item ) {
            /** @var WC_Order_Item_Product $item */
            $line_total_pool += max( 0, (float) $item->get_total() );
        }

        $remaining_fee_discount = round( $fee_discount_total, $decimals );
        $remaining_line_total   = round( $line_total_pool, $decimals );
        $line_index             = 0;

        foreach ( $order_items as $item ) {
            /** @var WC_Order_Item_Product $item */
            $quantity             = max( 1, (int) $item->get_quantity() );
            $line_subtotal        = (float) $item->get_subtotal();
            $line_total           = max( 0, (float) $item->get_total() );
            $item_discount_total  = max( 0, $line_subtotal - $line_total );
            $allocated_fee_amount = 0.0;

            if ( $remaining_fee_discount > 0 && $remaining_line_total > 0 ) {
                if ( $line_index === $line_items_count - 1 ) {
                    $allocated_fee_amount = $remaining_fee_discount;
                } else {
                    $allocated_fee_amount = round( $remaining_fee_discount * ( $line_total / $remaining_line_total ), $decimals );
                }

                $allocated_fee_amount = min( $allocated_fee_amount, $remaining_fee_discount, $line_total );
            }

            $remaining_fee_discount = max( 0, round( $remaining_fee_discount - $allocated_fee_amount, $decimals ) );
            $remaining_line_total   = max( 0, round( $remaining_line_total - $line_total, $decimals ) );

            $discount_total     = round( $item_discount_total + $allocated_fee_amount, $decimals );
            $effective_total    = max( 0, round( $line_subtotal - $discount_total, $decimals ) );
            $unit_price         = $quantity > 0 ? $line_subtotal / $quantity : $line_subtotal;
            $discount_per_unit  = $quantity > 0 ? $discount_total / $quantity : $discount_total;
            $discounted_unit_price = $quantity > 0 ? $effective_total / $quantity : $effective_total;
            $discount_percent   = $line_subtotal > 0 ? ( $discount_total / $line_subtotal ) * 100 : 0;

            $rows[] = array(
                'item'              => $item,
                'product'           => $item->get_product(),
                'is_service'        => class_exists( 'EOP_Settings' ) && method_exists( 'EOP_Settings', 'is_service_product' ) && EOP_Settings::is_service_product( $item->get_product() ),
                'quantity'          => $quantity,
                'unit_price'        => round( $unit_price, $decimals ),
                'discount_percent'  => round( $discount_percent, 2 ),
                'discount_per_unit' => round( $discount_per_unit, $decimals ),
                'discounted_unit_price' => round( $discounted_unit_price, $decimals ),
                'discount_total'    => $discount_total,
                'line_total'        => $effective_total,
            );

            $line_index++;
        }

        return $rows;
    }

    private static function render_pdf_binary( $pages ) {
        $objects = array(
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        );

        $kids          = array();
        $object_number = 5;

        foreach ( $pages as $page_content ) {
            $page_object    = $object_number++;
            $content_object = $object_number++;
            $kids[]         = $page_object . ' 0 R';

            $objects[ $page_object ] = '<< /Type /Page /Parent 2 0 R /MediaBox [' . self::get_media_box() . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $content_object . ' 0 R >>';
            $objects[ $content_object ] = '<< /Length ' . strlen( $page_content ) . " >>\nstream\n" . $page_content . "\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids [ ' . implode( ' ', $kids ) . ' ] /Count ' . count( $kids ) . ' >>';

        ksort( $objects );

        $pdf     = "%PDF-1.4\n";
        $offsets = array( 0 );

        foreach ( $objects as $id => $object ) {
            $offsets[ $id ] = strlen( $pdf );
            $pdf           .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xref_offset = strlen( $pdf );
        $max_object  = max( array_keys( $objects ) );

        $pdf .= "xref\n0 " . ( $max_object + 1 ) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ( $i = 1; $i <= $max_object; $i++ ) {
            $offset = isset( $offsets[ $i ] ) ? $offsets[ $i ] : 0;
            $pdf   .= sprintf( "%010d 00000 n \n", $offset );
        }

        $pdf .= "trailer\n<< /Size " . ( $max_object + 1 ) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xref_offset . "\n%%EOF";

        return $pdf;
    }

    private static function wrap_text( $text, $max_chars ) {
        $text      = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
        $max_chars = max( 12, absint( $max_chars ) );

        if ( '' === $text ) {
            return array( '' );
        }

        $wrapped = wordwrap( $text, $max_chars, "\n", true );

        return explode( "\n", $wrapped );
    }

    private static function format_money( $value ) {
        $formatted = wc_price( (float) $value );
        $formatted = html_entity_decode( wp_strip_all_tags( (string) $formatted ), ENT_QUOTES, 'UTF-8' );

        return trim( preg_replace( '/\s+/', ' ', $formatted ) );
    }

    private static function format_percentage( $value ) {
        $value    = max( 0, (float) $value );
        $decimals = abs( $value - round( $value ) ) < 0.01 ? 0 : 2;

        return number_format_i18n( $value, $decimals ) . '%';
    }

    private static function format_discount_column_value( $line_item, $document_type = 'order' ) {
        $mode   = self::get_document_discount_display_mode( $document_type );
        $suffix = self::get_document_discount_suffix( $document_type );
        $parts   = array();

        if ( 'percent' === $mode || 'both' === $mode ) {
            $parts[] = self::format_percentage( $line_item['discount_percent'] ?? 0 );
        }

        if ( 'currency' === $mode || 'both' === $mode ) {
            $money = self::format_money( $line_item['discount_per_unit'] ?? 0 );
            $parts[] = trim( $money . ( '' !== $suffix ? ' ' . $suffix : '' ) );
        }

        return trim( implode( ' ', array_filter( $parts, 'strlen' ) ) );
    }

    private static function format_discounted_unit_price_value( $line_item, $document_type = 'order' ) {
        $suffix = self::get_document_discounted_unit_price_suffix( $document_type );
        $money  = self::format_money( $line_item['discounted_unit_price'] ?? 0 );

        return trim( $money . ( '' !== $suffix ? ' ' . $suffix : '' ) );
    }

    private static function sanitize_pdf_font_size( $value, $default = 12 ) {
        $size     = absint( $value );
        $fallback = max( 8, min( 72, absint( $default ) ) );

        if ( $size < 8 || $size > 72 ) {
            $size = $fallback;
        }

        return $size;
    }

    private static function sanitize_pdf_line_height( $value, $default = '1.35' ) {
        $height   = floatval( str_replace( ',', '.', (string) $value ) );
        $fallback = max( 1.0, min( 3.0, floatval( str_replace( ',', '.', (string) $default ) ) ) );

        if ( $height < 0.8 || $height > 3.0 ) {
            $height = $fallback;
        }

        $formatted = rtrim( rtrim( number_format( $height, 2, '.', '' ), '0' ), '.' );

        return '' === $formatted ? (string) $fallback : $formatted;
    }

    private static function scale_pdf_font_size( $value, $minimum = 8 ) {
        $size = self::sanitize_pdf_font_size( $value, $minimum );

        return max( absint( $minimum ), (int) round( $size * 0.78 ) );
    }

    private static function sanitize_pdf_hex_color( $value, $default = '#111111' ) {
        $color    = sanitize_hex_color( (string) $value );
        $fallback = sanitize_hex_color( (string) $default );

        return $color ? $color : ( $fallback ? $fallback : '#111111' );
    }

    private static function get_pdf_color_components( $color ) {
        $color = ltrim( self::sanitize_pdf_hex_color( $color ), '#' );

        if ( 3 === strlen( $color ) ) {
            $color = preg_replace( '/(.)/', '$1$1', $color );
        }

        return array(
            hexdec( substr( $color, 0, 2 ) ) / 255,
            hexdec( substr( $color, 2, 2 ) ) / 255,
            hexdec( substr( $color, 4, 2 ) ) / 255,
        );
    }

    private static function get_pdf_fill_color_operator( $color ) {
        $components = self::get_pdf_color_components( $color );

        return sprintf(
            '%s %s %s rg',
            self::pdf_number( $components[0] ),
            self::pdf_number( $components[1] ),
            self::pdf_number( $components[2] )
        );
    }

    private static function get_pdf_stroke_color_operator( $color ) {
        $components = self::get_pdf_color_components( $color );

        return sprintf(
            '%s %s %s RG',
            self::pdf_number( $components[0] ),
            self::pdf_number( $components[1] ),
            self::pdf_number( $components[2] )
        );
    }

    private static function get_pdf_text_color_operator( $color ) {
        return self::get_pdf_fill_color_operator( $color );
    }

    private static function escape_pdf_text( $text ) {
        $text = wp_strip_all_tags( (string) $text );

        if ( function_exists( 'iconv' ) ) {
            $converted = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text );

            if ( false !== $converted ) {
                $text = $converted;
            }
        }

        $text = str_replace( array( '\\', '(', ')' ), array( '\\\\', '\(', '\)' ), $text );
        $text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text );

        return $text;
    }

    private static function pdf_number( $number ) {
        return rtrim( rtrim( sprintf( '%.2F', (float) $number ), '0' ), '.' );
    }

    private static function get_pdf_settings() {
        return class_exists( 'EOP_PDF_Settings' ) ? EOP_PDF_Settings::get_all() : array();
    }

    private static function use_pretty_pdf_links() {
        $settings = self::get_pdf_settings();

        return isset( $settings['advanced_pretty_links'] ) && 'yes' === (string) $settings['advanced_pretty_links'];
    }

    private static function get_access_policy() {
        $settings = self::get_pdf_settings();
        $policy   = isset( $settings['advanced_link_access'] ) ? sanitize_key( (string) $settings['advanced_link_access'] ) : 'private_nonce';

        return in_array( $policy, array( 'private_nonce', 'public_token', 'order_owner' ), true ) ? $policy : 'private_nonce';
    }

    private static function requires_private_nonce() {
        return 'private_nonce' === self::get_access_policy();
    }

    private static function get_private_download_args( WC_Order $order, $document_type, $force_download ) {
        $args = array(
            'order_id' => $order->get_id(),
            'document' => $document_type,
            'download' => $force_download ? '1' : '0',
        );

        if ( self::requires_private_nonce() ) {
            $args['_wpnonce'] = wp_create_nonce( 'eop_download_pdf_' . $order->get_id() . '_' . $document_type );
        } elseif ( 'public_token' === self::get_access_policy() ) {
            $args['token'] = rawurlencode( self::get_private_access_token( $order, $document_type ) );
        }

        return $args;
    }

    private static function is_public_token_request( WC_Order $order, $document_type, $token ) {
        return '' !== (string) $token && self::can_access_via_token( $order, $document_type, $token );
    }

    private static function can_access_via_token( WC_Order $order, $document_type, $token ) {
        $token = (string) $token;

        if ( '' === $token ) {
            return false;
        }

        if ( 'proposal' === $document_type && self::public_token_matches_order( $order, $token ) && self::can_public_proposal_download( $order ) ) {
            return true;
        }

        return 'public_token' === self::get_access_policy() && self::private_access_token_matches_order( $order, $document_type, $token );
    }

    private static function get_private_access_token( WC_Order $order, $document_type ) {
        $document_type = self::normalize_document_type( $document_type );
        $meta_key      = self::get_private_access_token_meta_key( $document_type );
        $token         = (string) $order->get_meta( $meta_key, true );

        if ( 'proposal' === $document_type ) {
            $public_token = (string) $order->get_meta( '_eop_public_token', true );

            if ( '' !== $public_token ) {
                return $public_token;
            }
        }

        if ( '' === $token ) {
            $token = wp_generate_password( 32, false, false );
            $order->update_meta_data( $meta_key, $token );
            $order->save();
        }

        return $token;
    }

    private static function get_private_access_token_meta_key( $document_type ) {
        return '_eop_pdf_' . self::normalize_document_type( $document_type ) . '_access_token';
    }

    private static function private_access_token_matches_order( WC_Order $order, $document_type, $token ) {
        $expected = (string) $order->get_meta( self::get_private_access_token_meta_key( $document_type ), true );

        if ( '' === $expected && 'proposal' === self::normalize_document_type( $document_type ) ) {
            $expected = (string) $order->get_meta( '_eop_public_token', true );
        }

        return '' !== $expected && hash_equals( $expected, (string) $token );
    }

    private static function should_auto_cleanup_cache() {
        $settings = self::get_pdf_settings();

        return isset( $settings['advanced_auto_cleanup'] ) && 'yes' === (string) $settings['advanced_auto_cleanup'];
    }

    private static function should_log_debug() {
        $settings = self::get_pdf_settings();

        return isset( $settings['advanced_debug'] ) && 'yes' === (string) $settings['advanced_debug'];
    }

    private static function should_log_order_notes() {
        $settings = self::get_pdf_settings();

        return isset( $settings['advanced_order_note_logs'] ) && 'yes' === (string) $settings['advanced_order_note_logs'];
    }

    private static function log_document_event( WC_Order $order, $document_type, $message ) {
        $prefix = sprintf( '[EOP PDF][%s][pedido %d] ', strtoupper( self::normalize_document_type( $document_type ) ), $order->get_id() );

        if ( self::should_log_debug() ) {
            error_log( $prefix . wp_strip_all_tags( (string) $message ) );
        }

        if ( self::should_log_order_notes() ) {
            $order->add_order_note( wp_strip_all_tags( (string) $message ), false, true );
        }
    }

    private static function maybe_mark_document_printed( WC_Order $order, $document_type, $is_public ) {
        $settings       = self::get_pdf_settings();
        $document_type  = self::normalize_document_type( $document_type );
        $setting_key    = $document_type . '_mark_printed';

        if ( ! isset( $settings[ $setting_key ] ) || 'yes' !== (string) $settings[ $setting_key ] ) {
            return;
        }

        $timestamp = current_time( 'mysql' );
        $user_id   = get_current_user_id();

        $order->update_meta_data( '_eop_pdf_' . $document_type . '_printed_at', $timestamp );
        $order->update_meta_data( '_eop_pdf_' . $document_type . '_printed_by', $user_id );
        $order->update_meta_data( '_eop_pdf_' . $document_type . '_printed_access', $is_public ? 'token' : 'restricted' );
        $order->save();
    }

    private static function should_log_edoc() {
        $settings = self::get_pdf_settings();

        return isset( $settings['edoc_logging'] ) && 'yes' === (string) $settings['edoc_logging'];
    }

    private static function maybe_cleanup_cache_directories() {
        if ( ! self::should_auto_cleanup_cache() ) {
            return;
        }

        $upload_dir = wp_upload_dir();

        if ( empty( $upload_dir['basedir'] ) ) {
            return;
        }

        self::cleanup_directory_by_age( trailingslashit( $upload_dir['basedir'] ) . 'eop-pdf', 7 * DAY_IN_SECONDS, array( 'dompdf' ) );
        self::cleanup_directory_by_age( trailingslashit( $upload_dir['basedir'] ) . 'eop-pdf/dompdf/tmp', DAY_IN_SECONDS );
    }

    private static function cleanup_directory_by_age( $directory, $ttl, $protected = array() ) {
        if ( ! is_dir( $directory ) ) {
            return;
        }

        $entries = scandir( $directory );

        if ( false === $entries ) {
            return;
        }

        $now = time();

        foreach ( $entries as $entry ) {
            if ( '.' === $entry || '..' === $entry || in_array( $entry, $protected, true ) ) {
                continue;
            }

            $path = trailingslashit( $directory ) . $entry;

            if ( is_dir( $path ) ) {
                if ( filemtime( $path ) && ( $now - (int) filemtime( $path ) ) >= $ttl ) {
                    self::delete_directory_recursive( $path );
                }

                continue;
            }

            if ( is_file( $path ) && filemtime( $path ) && ( $now - (int) filemtime( $path ) ) >= $ttl ) {
                @unlink( $path );
            }
        }
    }

    private static function delete_directory_recursive( $directory ) {
        if ( ! is_dir( $directory ) ) {
            return;
        }

        $entries = scandir( $directory );

        if ( false !== $entries ) {
            foreach ( $entries as $entry ) {
                if ( '.' === $entry || '..' === $entry ) {
                    continue;
                }

                $path = trailingslashit( $directory ) . $entry;

                if ( is_dir( $path ) ) {
                    self::delete_directory_recursive( $path );
                } elseif ( is_file( $path ) ) {
                    @unlink( $path );
                }
            }
        }

        @rmdir( $directory );
    }

    private static function sanitize_css_measurement( $value, $fallback = '3cm' ) {
        $value = trim( (string) $value );

        if ( '' === $value ) {
            return $fallback;
        }

        if ( preg_match( '/^\d+(?:\.\d+)?(px|cm|mm|in|pt|pc|em|rem|vh|vw|%)$/', $value ) ) {
            return $value;
        }

        return $fallback;
    }

    private static function maybe_log_edocument_event( WC_Order $order, $message ) {
        if ( self::should_log_edoc() || self::should_log_debug() ) {
            self::log_document_event( $order, self::detect_document_type( $order ), $message );
        }
    }

    private static function build_ubl_xml_document( WC_Order $order, $document_type, $settings, $is_peppol = false ) {
        $number      = self::get_document_number( $order, $document_type, false );
        $line_items  = self::get_order_line_items_display_data( $order );
        $totals      = EOP_Order_Creator::sync_order_totals( $order );
        $date        = $order->get_date_created();
        $issue_date  = $date ? $date->date_i18n( 'Y-m-d' ) : gmdate( 'Y-m-d' );
        $customer_id = $order->get_billing_email() ? $order->get_billing_email() : 'cliente-' . $order->get_id();
        $supplier_id = ! empty( $settings['shop_email'] ) ? $settings['shop_email'] : home_url();
        $xml         = array();

        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">';

        if ( $is_peppol ) {
            $xml[] = '  <cbc:CustomizationID>urn:fdc:peppol.eu:2017:poacc:billing:3.0</cbc:CustomizationID>';
            $xml[] = '  <cbc:ProfileID>urn:fdc:peppol.eu:2017:poacc:billing:01:1.0</cbc:ProfileID>';
        }

        $xml[] = '  <cbc:ID>' . esc_html( $number ) . '</cbc:ID>';
        $xml[] = '  <cbc:IssueDate>' . esc_html( $issue_date ) . '</cbc:IssueDate>';
        $xml[] = '  <cbc:DocumentCurrencyCode>' . esc_html( get_woocommerce_currency() ) . '</cbc:DocumentCurrencyCode>';
        $xml[] = '  <cac:AccountingSupplierParty>';
        $xml[] = '    <cac:Party>';
        $xml[] = '      <cbc:EndpointID schemeID="' . esc_attr( $settings['edoc_supplier_scheme'] ?: 'EMAIL' ) . '">' . esc_html( $supplier_id ) . '</cbc:EndpointID>';
        $xml[] = '      <cac:PartyName><cbc:Name>' . esc_html( $settings['shop_name'] ?: get_bloginfo( 'name' ) ) . '</cbc:Name></cac:PartyName>';
        $xml[] = '    </cac:Party>';
        $xml[] = '  </cac:AccountingSupplierParty>';
        $xml[] = '  <cac:AccountingCustomerParty>';
        $xml[] = '    <cac:Party>';
        $xml[] = '      <cbc:EndpointID schemeID="' . esc_attr( $settings['edoc_customer_scheme'] ?: 'EMAIL' ) . '">' . esc_html( $customer_id ) . '</cbc:EndpointID>';
        $xml[] = '      <cac:PartyName><cbc:Name>' . esc_html( trim( $order->get_formatted_billing_full_name() ) ?: 'Cliente' ) . '</cbc:Name></cac:PartyName>';
        $xml[] = '    </cac:Party>';
        $xml[] = '  </cac:AccountingCustomerParty>';

        foreach ( $line_items as $index => $line_item ) {
            $sku = $line_item['product'] && $line_item['product']->get_sku() ? $line_item['product']->get_sku() : '';

            $xml[] = '  <cac:InvoiceLine>';
            $xml[] = '    <cbc:ID>' . esc_html( (string) ( $index + 1 ) ) . '</cbc:ID>';
            $xml[] = '    <cbc:InvoicedQuantity>' . esc_html( (string) $line_item['quantity'] ) . '</cbc:InvoicedQuantity>';
            $xml[] = '    <cbc:LineExtensionAmount currencyID="' . esc_attr( get_woocommerce_currency() ) . '">' . esc_html( wc_format_decimal( $line_item['line_total'], wc_get_price_decimals() ) ) . '</cbc:LineExtensionAmount>';
            $xml[] = '    <cac:Item>';
            $xml[] = '      <cbc:Name>' . esc_html( $line_item['item']->get_name() ) . '</cbc:Name>';
            if ( '' !== $sku ) {
                $xml[] = '      <cac:SellersItemIdentification><cbc:ID>' . esc_html( $sku ) . '</cbc:ID></cac:SellersItemIdentification>';
            }
            $xml[] = '    </cac:Item>';
            $xml[] = '    <cac:Price><cbc:PriceAmount currencyID="' . esc_attr( get_woocommerce_currency() ) . '">' . esc_html( wc_format_decimal( $line_item['discounted_unit_price'], wc_get_price_decimals() ) ) . '</cbc:PriceAmount></cac:Price>';
            $xml[] = '  </cac:InvoiceLine>';
        }

        $xml[] = '  <cac:LegalMonetaryTotal>';
        $xml[] = '    <cbc:LineExtensionAmount currencyID="' . esc_attr( get_woocommerce_currency() ) . '">' . esc_html( wc_format_decimal( $totals['items_subtotal'], wc_get_price_decimals() ) ) . '</cbc:LineExtensionAmount>';
        $xml[] = '    <cbc:AllowanceTotalAmount currencyID="' . esc_attr( get_woocommerce_currency() ) . '">' . esc_html( wc_format_decimal( $totals['discount_total'], wc_get_price_decimals() ) ) . '</cbc:AllowanceTotalAmount>';
        $xml[] = '    <cbc:PayableAmount currencyID="' . esc_attr( get_woocommerce_currency() ) . '">' . esc_html( wc_format_decimal( $totals['grand_total'], wc_get_price_decimals() ) ) . '</cbc:PayableAmount>';
        $xml[] = '  </cac:LegalMonetaryTotal>';

        if ( ! empty( $settings['edoc_embed_pdf'] ) && 'yes' === (string) $settings['edoc_embed_pdf'] ) {
            $pdf_url = self::get_pdf_document_url( $order, $document_type, true );
            $xml[]   = '  <cac:AdditionalDocumentReference>';
            $xml[]   = '    <cbc:ID>PDF</cbc:ID>';
            $xml[]   = '    <cac:Attachment><cac:ExternalReference><cbc:URI>' . esc_url( $pdf_url ) . '</cbc:URI></cac:ExternalReference></cac:Attachment>';
            $xml[]   = '  </cac:AdditionalDocumentReference>';
        }

        if ( $is_peppol && ! empty( $settings['edoc_network_endpoint'] ) ) {
            $xml[] = '  <cbc:BuyerReference>' . esc_html( $settings['edoc_network_endpoint'] ) . '</cbc:BuyerReference>';
        }

        $xml[] = '</Invoice>';

        self::maybe_log_edocument_event( $order, __( 'XML eletrônico UBL/Peppol gerado no admin.', EOP_TEXT_DOMAIN ) );

        return implode( "\n", $xml );
    }

    private static function build_cii_xml_document( WC_Order $order, $document_type, $settings ) {
        $number      = self::get_document_number( $order, $document_type, false );
        $line_items  = self::get_order_line_items_display_data( $order );
        $totals      = EOP_Order_Creator::sync_order_totals( $order );
        $date        = $order->get_date_created();
        $issue_date  = $date ? $date->date_i18n( 'Ymd' ) : gmdate( 'Ymd' );
        $xml         = array();

        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<CrossIndustryInvoice xmlns="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100">';
        $xml[] = '  <ExchangedDocument>';
        $xml[] = '    <ID>' . esc_html( $number ) . '</ID>';
        $xml[] = '    <IssueDateTime><DateTimeString format="102">' . esc_html( $issue_date ) . '</DateTimeString></IssueDateTime>';
        $xml[] = '  </ExchangedDocument>';
        $xml[] = '  <SupplyChainTradeTransaction>';
        $xml[] = '    <ApplicableHeaderTradeAgreement>';
        $xml[] = '      <SellerTradeParty><Name>' . esc_html( $settings['shop_name'] ?: get_bloginfo( 'name' ) ) . '</Name></SellerTradeParty>';
        $xml[] = '      <BuyerTradeParty><Name>' . esc_html( trim( $order->get_formatted_billing_full_name() ) ?: 'Cliente' ) . '</Name></BuyerTradeParty>';
        $xml[] = '    </ApplicableHeaderTradeAgreement>';

        foreach ( $line_items as $index => $line_item ) {
            $xml[] = '    <IncludedSupplyChainTradeLineItem>';
            $xml[] = '      <AssociatedDocumentLineDocument><LineID>' . esc_html( (string) ( $index + 1 ) ) . '</LineID></AssociatedDocumentLineDocument>';
            $xml[] = '      <SpecifiedTradeProduct><Name>' . esc_html( $line_item['item']->get_name() ) . '</Name></SpecifiedTradeProduct>';
            $xml[] = '      <SpecifiedLineTradeAgreement><NetPriceProductTradePrice><ChargeAmount>' . esc_html( wc_format_decimal( $line_item['discounted_unit_price'], wc_get_price_decimals() ) ) . '</ChargeAmount></NetPriceProductTradePrice></SpecifiedLineTradeAgreement>';
            $xml[] = '      <SpecifiedLineTradeDelivery><BilledQuantity>' . esc_html( (string) $line_item['quantity'] ) . '</BilledQuantity></SpecifiedLineTradeDelivery>';
            $xml[] = '      <SpecifiedLineTradeSettlement><SpecifiedTradeSettlementLineMonetarySummation><LineTotalAmount>' . esc_html( wc_format_decimal( $line_item['line_total'], wc_get_price_decimals() ) ) . '</LineTotalAmount></SpecifiedTradeSettlementLineMonetarySummation></SpecifiedLineTradeSettlement>';
            $xml[] = '    </IncludedSupplyChainTradeLineItem>';
        }

        $xml[] = '    <ApplicableHeaderTradeSettlement>';
        $xml[] = '      <InvoiceCurrencyCode>' . esc_html( get_woocommerce_currency() ) . '</InvoiceCurrencyCode>';
        $xml[] = '      <SpecifiedTradeSettlementHeaderMonetarySummation>';
        $xml[] = '        <LineTotalAmount>' . esc_html( wc_format_decimal( $totals['items_subtotal'], wc_get_price_decimals() ) ) . '</LineTotalAmount>';
        $xml[] = '        <AllowanceTotalAmount>' . esc_html( wc_format_decimal( $totals['discount_total'], wc_get_price_decimals() ) ) . '</AllowanceTotalAmount>';
        $xml[] = '        <GrandTotalAmount>' . esc_html( wc_format_decimal( $totals['grand_total'], wc_get_price_decimals() ) ) . '</GrandTotalAmount>';
        $xml[] = '      </SpecifiedTradeSettlementHeaderMonetarySummation>';
        $xml[] = '    </ApplicableHeaderTradeSettlement>';
        $xml[] = '  </SupplyChainTradeTransaction>';
        $xml[] = '</CrossIndustryInvoice>';

        self::maybe_log_edocument_event( $order, __( 'XML eletrônico CII gerado no admin.', EOP_TEXT_DOMAIN ) );

        return implode( "\n", $xml );
    }

    private static function get_shop_identity_lines( $settings = null ) {
        $settings = is_array( $settings ) ? $settings : self::get_pdf_settings();
        $lines    = array();
        $address  = self::get_shop_address_label( $settings );

        if ( '' !== $address ) {
            $lines[] = $address;
        }

        if ( ! empty( $settings['shop_phone'] ) ) {
            $lines[] = sprintf( __( 'Telefone: %s', EOP_TEXT_DOMAIN ), wp_strip_all_tags( (string) $settings['shop_phone'] ) );
        }

        if ( ! empty( $settings['shop_email'] ) ) {
            $lines[] = sprintf( __( 'E-mail: %s', EOP_TEXT_DOMAIN ), wp_strip_all_tags( (string) $settings['shop_email'] ) );
        }

        if ( ! empty( $settings['shop_vat_number'] ) ) {
            $lines[] = sprintf( __( 'Documento: %s', EOP_TEXT_DOMAIN ), wp_strip_all_tags( (string) $settings['shop_vat_number'] ) );
        }

        if ( ! empty( $settings['shop_chamber_of_commerce'] ) ) {
            $lines[] = sprintf( __( 'Registro: %s', EOP_TEXT_DOMAIN ), wp_strip_all_tags( (string) $settings['shop_chamber_of_commerce'] ) );
        }

        foreach ( array( 'shop_extra_1', 'shop_extra_2', 'shop_extra_3' ) as $extra_key ) {
            if ( ! empty( $settings[ $extra_key ] ) ) {
                $lines[] = wp_strip_all_tags( (string) $settings[ $extra_key ] );
            }
        }

        return array_values( array_filter( array_map( 'trim', $lines ) ) );
    }

    private static function get_pdf_table_positions( $column_keys ) {
        $column_keys = array_values( array_filter( array_map( 'sanitize_key', (array) $column_keys ) ) );
        $count       = count( $column_keys );
        $positions   = array();

        if ( 0 === $count ) {
            return $positions;
        }

        if ( 1 === $count ) {
            $positions[ $column_keys[0] ] = 500;
            return $positions;
        }

        $start = 325;
        $end   = 515;
        $step  = ( $end - $start ) / max( 1, $count - 1 );

        foreach ( $column_keys as $index => $column_key ) {
            $positions[ $column_key ] = (int) round( $start + ( $step * $index ) );
        }

        return $positions;
    }

    private static function get_content_disposition() {
        $settings = self::get_pdf_settings();

        return isset( $settings['display_mode'] ) && 'download' === $settings['display_mode'] ? 'attachment' : 'inline';
    }

    private static function get_media_box() {
        $settings = self::get_pdf_settings();

        return isset( $settings['paper_size'] ) && 'letter' === $settings['paper_size'] ? '0 0 612 792' : '0 0 595 842';
    }

    private static function get_shop_address_label( $settings = null ) {
        $settings = is_array( $settings ) ? $settings : self::get_pdf_settings();

        return implode(
            ', ',
            array_filter(
                array(
                    $settings['shop_address_line_1'] ?? '',
                    $settings['shop_address_line_2'] ?? '',
                    $settings['shop_city'] ?? '',
                    $settings['shop_state'] ?? '',
                    $settings['shop_postcode'] ?? '',
                    $settings['shop_country'] ?? '',
                )
            )
        );
    }

    private static function get_document_logo_source( $settings = null, $embed_assets = false ) {
        $settings = is_array( $settings ) ? $settings : self::get_pdf_settings();
        $logo_url = trim( (string) ( $settings['shop_logo_url'] ?? '' ) );

        if ( '' === $logo_url ) {
            return '';
        }

        if ( ! $embed_assets ) {
            return $logo_url;
        }

        $embedded_logo = self::get_embedded_image_source( $logo_url );

        return '' !== $embedded_logo ? $embedded_logo : $logo_url;
    }

    private static function get_embedded_image_source( $image_url ) {
        $image_url = trim( (string) $image_url );

        if ( '' === $image_url ) {
            return '';
        }

        if ( 0 === strpos( $image_url, 'data:image/' ) ) {
            return $image_url;
        }

        $file_path = self::resolve_local_media_path_from_url( $image_url );

        if ( '' === $file_path || ! is_readable( $file_path ) ) {
            return '';
        }

        $binary = file_get_contents( $file_path );

        if ( false === $binary || '' === $binary ) {
            return '';
        }

        $filetype = wp_check_filetype( $file_path );
        $mime     = ! empty( $filetype['type'] ) ? $filetype['type'] : 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode( $binary );
    }

    private static function resolve_local_media_path_from_url( $url ) {
        $url      = trim( (string) $url );
        $uploads  = wp_get_upload_dir();
        $base_url = ! empty( $uploads['baseurl'] ) ? trailingslashit( $uploads['baseurl'] ) : '';
        $base_dir = ! empty( $uploads['basedir'] ) ? trailingslashit( $uploads['basedir'] ) : '';

        if ( '' !== $base_url && '' !== $base_dir && 0 === strpos( trailingslashit( $url ), $base_url ) ) {
            $relative_path = ltrim( wp_parse_url( $url, PHP_URL_PATH ), '/' );
            $upload_path   = ltrim( wp_parse_url( $base_url, PHP_URL_PATH ), '/' );

            if ( '' !== $relative_path && '' !== $upload_path && 0 === strpos( $relative_path, $upload_path ) ) {
                $file_path = wp_normalize_path( $base_dir . rawurldecode( substr( $relative_path, strlen( $upload_path ) ) ) );

                if ( is_readable( $file_path ) ) {
                    return $file_path;
                }
            }
        }

        $attachment_id = attachment_url_to_postid( $url );

        if ( $attachment_id ) {
            $file_path = get_attached_file( $attachment_id );

            if ( is_string( $file_path ) && is_readable( $file_path ) ) {
                return wp_normalize_path( $file_path );
            }
        }

        return '';
    }

    private static function get_shipping_label( WC_Order $order ) {
        $parts = array_filter(
            array(
                $order->get_shipping_address_1(),
                $order->get_meta( '_shipping_number', true ),
                $order->get_meta( '_shipping_neighborhood', true ),
                $order->get_shipping_city(),
                $order->get_shipping_state(),
            )
        );

        if ( empty( $parts ) ) {
            return __( 'Não informado', EOP_TEXT_DOMAIN );
        }

        return implode( ', ', array_map( 'wp_strip_all_tags', $parts ) );
    }

    private static function get_billing_label( WC_Order $order ) {
        $parts = array_filter(
            array(
                $order->get_billing_address_1(),
                $order->get_meta( '_billing_number', true ),
                $order->get_billing_address_2(),
                $order->get_meta( '_billing_neighborhood', true ),
                $order->get_billing_city(),
                $order->get_billing_state(),
                $order->get_billing_postcode(),
            )
        );

        if ( empty( $parts ) ) {
            return '';
        }

        return implode( ', ', array_map( 'wp_strip_all_tags', $parts ) );
    }

    private static function get_document_number( WC_Order $order, $document_type, $create = false ) {
        $document_type = self::normalize_document_type( $document_type );
        $meta_key      = '_eop_' . $document_type . '_document_number';
        $current       = (string) $order->get_meta( $meta_key, true );

        if ( '' !== $current ) {
            return $current;
        }

        $settings = self::get_pdf_settings();
        $last_year = absint( $settings[ $document_type . '_last_reset_year' ] ?? 0 );
        $year      = absint( gmdate( 'Y', current_time( 'timestamp' ) ) );

        if ( 'yes' === ( $settings[ $document_type . '_reset_yearly' ] ?? 'no' ) && $last_year !== $year ) {
            $settings[ $document_type . '_next_number' ]     = '1';
            $settings[ $document_type . '_last_reset_year' ] = (string) $year;

            if ( class_exists( 'EOP_PDF_Settings' ) ) {
                update_option( EOP_PDF_Settings::OPTION_KEY, $settings );
            }
        }

        $prefix   = (string) ( $settings[ $document_type . '_prefix' ] ?? '' );
        $suffix   = (string) ( $settings[ $document_type . '_suffix' ] ?? '' );
        $padding  = max( 0, absint( $settings[ $document_type . '_padding' ] ?? 0 ) );
        $next     = max( 1, absint( $settings[ $document_type . '_next_number' ] ?? $order->get_id() ) );
        $number   = $prefix . str_pad( (string) $next, $padding, '0', STR_PAD_LEFT ) . $suffix;

        if ( ! $create ) {
            return $number;
        }

        $order->update_meta_data( $meta_key, $number );
        $order->save();

        if ( class_exists( 'EOP_PDF_Settings' ) ) {
            $settings[ $document_type . '_next_number' ] = (string) ( $next + 1 );
            $settings[ $document_type . '_last_reset_year' ] = (string) $year;
            update_option( EOP_PDF_Settings::OPTION_KEY, $settings );
        }

        return $number;
    }
}
