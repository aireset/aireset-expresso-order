<?php
defined( 'ABSPATH' ) || exit;

class EOP_PDF_Admin_Page {

    use EOP_License_Guard;

    private static $page_hook = '';
    private static $settings_hook = '';
    private static $tab_hooks = array();
    private static $hidden_submenu_hooked = false;

    public static function init() {
        if ( ! self::_resolve_env_config() ) {
            return;
        }

        add_action( 'admin_menu', array( __CLASS__, 'register_submenu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'wp_ajax_eop_load_pdf_tab', array( __CLASS__, 'ajax_load_pdf_tab' ) );
        add_action( 'admin_post_eop_pdf_purge_cache', array( __CLASS__, 'handle_purge_cache' ) );
        add_action( 'admin_post_eop_pdf_reset_counters', array( __CLASS__, 'handle_reset_counters' ) );
        add_action( 'admin_post_eop_download_edoc_xml', array( __CLASS__, 'handle_download_edoc_xml' ) );
    }

    public static function register_submenu() {
        ensure_aireset_parent_menu();

        // self::$page_hook = add_submenu_page(
        //     'aireset',
        //     __( 'PDF', EOP_TEXT_DOMAIN ),
        //     __( 'PDF', EOP_TEXT_DOMAIN ),
        //     'edit_shop_orders',
        //     'eop-pdf',
        //     array( __CLASS__, 'render_documents_page' )
        // );

        // self::$settings_hook = add_submenu_page(
        //     'aireset',
        //     __( 'Configuracoes do PDF', EOP_TEXT_DOMAIN ),
        //     __( 'Configuracoes do PDF', EOP_TEXT_DOMAIN ),
        //     'manage_options',
        //     'eop-pdf-configuracoes',
        //     array( __CLASS__, 'render_settings_page' )
        // );

        // foreach ( self::get_tabs() as $tab => $label ) {
        //     $slug = self::get_tab_page_slug( $tab );

        //     if ( 'eop-pdf' === $slug ) {
        //         continue;
        //     }

        //     self::$tab_hooks[ $tab ] = add_submenu_page(
        //         'aireset',
        //         $label,
        //         $label,
        //         self::get_tab_capability( $tab ),
        //         $slug,
        //         function () use ( $tab ) {
        //             self::render_tab_page( $tab );
        //         }
        //     );
        // }

        // if ( ! self::$hidden_submenu_hooked ) {
        //     self::$hidden_submenu_hooked = true;

        //     add_action(
        //         'admin_menu',
        //         function () {
        //             remove_submenu_page( 'aireset', 'eop-pdf-configuracoes' );
        //             foreach ( self::get_tabs() as $tab => $label ) {
        //                 $slug = self::get_tab_page_slug( $tab );

        //                 if ( 'eop-pdf' !== $slug ) {
        //                     remove_submenu_page( 'aireset', $slug );
        //                 }
        //             }
        //         },
        //         999
        //     );
        // }
    }

    public static function enqueue_assets( $hook ) {
        if ( self::$page_hook !== $hook && self::$settings_hook !== $hook && ! in_array( $hook, self::$tab_hooks, true ) ) {
            return;
        }

        $font_url = EOP_Settings::get_font_stylesheet_url();
        if ( $font_url ) {
            wp_enqueue_style( 'eop-pdf-selected-font', $font_url, array(), null );
        }

        wp_enqueue_media();
        wp_enqueue_style( 'eop-coloris', EOP_PLUGIN_URL . 'assets/css/coloris.min.css', array(), EOP_VERSION );
        wp_enqueue_style( 'eop-admin', EOP_PLUGIN_URL . 'assets/css/admin.css', array(), EOP_VERSION );
        wp_enqueue_style( 'eop-orders', EOP_PLUGIN_URL . 'assets/css/orders.css', array( 'eop-admin' ), EOP_VERSION );
        wp_enqueue_style( 'eop-settings-admin', EOP_PLUGIN_URL . 'assets/css/settings-admin.css', array( 'eop-admin', 'eop-coloris' ), EOP_VERSION );
        wp_enqueue_style( 'eop-pdf-admin', EOP_PLUGIN_URL . 'assets/css/pdf-admin.css', array( 'eop-settings-admin' ), EOP_VERSION );
        wp_enqueue_script( 'eop-coloris', EOP_PLUGIN_URL . 'assets/js/coloris.min.js', array(), EOP_VERSION, true );
        wp_enqueue_script(
            'eop-settings-admin',
            EOP_PLUGIN_URL . 'assets/js/settings-admin.js',
            array( 'jquery', 'eop-coloris', 'media-editor', 'media-upload' ),
            EOP_VERSION,
            true
        );
        wp_localize_script(
            'eop-settings-admin',
            'eop_settings_vars',
            array(
                'has_fontselect'   => false,
                'font_placeholder' => __( 'Escolha uma fonte Google', EOP_TEXT_DOMAIN ),
                'media_title'      => __( 'Selecionar logo', EOP_TEXT_DOMAIN ),
                'media_button'     => __( 'Usar esta imagem', EOP_TEXT_DOMAIN ),
                'remove_logo'      => __( 'Remover logo', EOP_TEXT_DOMAIN ),
                'select_logo'      => __( 'Selecionar logo', EOP_TEXT_DOMAIN ),
                'change_logo'      => __( 'Trocar logo', EOP_TEXT_DOMAIN ),
                'no_logo'          => __( 'Nenhum logo selecionado ainda.', EOP_TEXT_DOMAIN ),
                'color_default'    => __( 'Padrao', EOP_TEXT_DOMAIN ),
                'color_clear'      => __( 'Limpar', EOP_TEXT_DOMAIN ),
                'color_close'      => __( 'Fechar', EOP_TEXT_DOMAIN ),
                'pdf_help_map'     => class_exists( 'EOP_PDF_Settings' ) ? EOP_PDF_Settings::get_admin_tooltip_map() : array(),
                'help_label'       => __( 'Ajuda da configuracao', EOP_TEXT_DOMAIN ),
                'help_statuses'    => array(
                    'active'       => __( 'Ativo', EOP_TEXT_DOMAIN ),
                    'experimental' => __( 'Experimental', EOP_TEXT_DOMAIN ),
                ),
            )
        );
    }

    public static function render_documents_page() {
        if ( class_exists( 'EOP_Admin_Page' ) ) {
            wp_safe_redirect( self::get_tab_url( 'display' ) );
            exit;
        }

        self::render_page( 'display' );
    }

    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
        }

        wp_safe_redirect( self::get_tab_url( 'order-settings' ) );
        exit;
    }

    public static function get_tab_capability( $tab ) {
        $tab = self::normalize_tab( $tab );

        return in_array( $tab, array( 'store', 'order-settings', 'order-columns', 'order-texts', 'order-style', 'proposal-settings', 'proposal-columns', 'proposal-texts', 'proposal-style', 'edocuments', 'advanced' ), true ) ? 'manage_options' : 'edit_shop_orders';
    }

    public static function get_tabs() {
        return array(
            'store'         => __( 'Informacoes sobre a loja', EOP_TEXT_DOMAIN ),
            'display'       => __( 'Configuracoes de exibicao', EOP_TEXT_DOMAIN ),
            'order-settings' => __( 'Configuracoes do Pedido', EOP_TEXT_DOMAIN ),
            'order-columns' => __( 'Colunas do Pedido', EOP_TEXT_DOMAIN ),
            'order-texts'   => __( 'Textos do Pedido', EOP_TEXT_DOMAIN ),
            'order-style'   => __( 'Visual do Pedido', EOP_TEXT_DOMAIN ),
            'proposal-settings' => __( 'Configuracoes da Proposta', EOP_TEXT_DOMAIN ),
            'proposal-columns'  => __( 'Colunas da Proposta', EOP_TEXT_DOMAIN ),
            'proposal-texts'    => __( 'Textos da Proposta', EOP_TEXT_DOMAIN ),
            'proposal-style'    => __( 'Visual da Proposta', EOP_TEXT_DOMAIN ),
            'edocuments'    => __( 'Documentos eletronicos', EOP_TEXT_DOMAIN ),
            'advanced'      => __( 'Avancado', EOP_TEXT_DOMAIN ),
            'documentation' => __( 'Documentacao', EOP_TEXT_DOMAIN ),
        );
    }

    public static function get_spa_nav_tabs() {
        $tabs = array();

        foreach ( self::get_tabs() as $tab => $label ) {
            if ( in_array( $tab, array( 'store', 'documentation' ), true ) ) {
                continue;
            }

            if ( current_user_can( self::get_tab_capability( $tab ) ) ) {
                $tabs[ $tab ] = $label;
            }
        }

        return $tabs;
    }

    public static function get_accessible_tabs() {
        $tabs = array();

        foreach ( self::get_tabs() as $tab => $label ) {
            if ( current_user_can( self::get_tab_capability( $tab ) ) ) {
                $tabs[ $tab ] = $label;
            }
        }

        return $tabs;
    }

    public static function normalize_tab( $tab, $default = 'general' ) {
        $tab = sanitize_key( (string) $tab );

        if ( 'general' === $tab ) {
            $tab = 'display';
        }

        if ( 'documents' === $tab ) {
            $document = isset( $_GET['document'] ) ? sanitize_key( wp_unslash( $_GET['document'] ) ) : 'order';
            $tab      = 'proposal' === $document ? 'proposal-settings' : 'order-settings';
        }

        $tabs = self::get_tabs();
        $default = 'general' === $default ? 'display' : sanitize_key( (string) $default );

        if ( isset( $tabs[ $tab ] ) ) {
            return $tab;
        }

        return isset( $tabs[ $default ] ) ? $default : 'display';
    }

    public static function get_tab_page_slug( $tab ) {
        $tab = self::normalize_tab( $tab );

        if ( 'display' === $tab ) {
            return 'eop-pdf';
        }

        return 'eop-pdf-' . $tab;
    }

    public static function get_current_tab() {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

        if ( self::is_spa_request() && isset( $_GET['pdf_tab'] ) ) {
            return self::normalize_tab( wp_unslash( $_GET['pdf_tab'] ), 'display' );
        }

        if ( 0 === strpos( $page, 'eop-pdf-' ) ) {
            return self::normalize_tab( substr( $page, 8 ), 'display' );
        }

        if ( isset( $_GET['tab'] ) ) {
            return self::normalize_tab( wp_unslash( $_GET['tab'] ), 'display' );
        }

        return 'display';
    }

    public static function get_tab_url( $tab, $args = array() ) {
        $tab = self::normalize_tab( $tab );

        if ( class_exists( 'EOP_Admin_Page' ) ) {
            return EOP_Admin_Page::get_view_url(
                'pdf',
                array_merge(
                    array(
                        'pdf_tab' => $tab,
                    ),
                    $args
                )
            );
        }

        $query = array_merge(
            array(
                'page' => self::get_tab_page_slug( $tab ),
            ),
            $args
        );

        return add_query_arg( $query, admin_url( 'admin.php' ) );
    }

    public static function render_tab_page( $tab ) {
        $tab = self::normalize_tab( $tab );

        if ( ! current_user_can( self::get_tab_capability( $tab ) ) ) {
            wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
        }

        if ( class_exists( 'EOP_Admin_Page' ) && ! self::is_spa_request() ) {
            wp_safe_redirect( self::get_tab_url( $tab ) );
            exit;
        }

        self::render_page( $tab );
    }

    public static function render_embedded_page( $default_tab = 'display' ) {
        $default_tab = self::normalize_tab( $default_tab );
        $tab         = $default_tab;

        if ( self::is_spa_request() || ! class_exists( 'EOP_Admin_Page' ) ) {
            $tab = self::normalize_tab( self::get_current_tab(), $default_tab );
        }

        if ( ! current_user_can( self::get_tab_capability( $tab ) ) ) {
            wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
        }

        self::render_page( $tab, true );
    }

    public static function is_spa_request() {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';

        return 'eop-pedido-expresso' === $page && 'pdf' === $view;
    }

    private static function render_page( $default_tab, $embedded = false ) {
        $default_tab = self::normalize_tab( $default_tab );
        $embedded    = (bool) $embedded;
        $tab         = $default_tab;
        include EOP_PLUGIN_DIR . 'templates/pdf-admin-page.php';
    }

    public static function get_pdf_tab_payload( $request = null ) {
        $source        = $request instanceof WP_REST_Request ? $request : null;
        $tab           = $source ? self::normalize_tab( (string) $source->get_param( 'pdf_tab' ), 'display' ) : ( isset( $_REQUEST['pdf_tab'] ) ? self::normalize_tab( wp_unslash( $_REQUEST['pdf_tab'] ), 'display' ) : 'display' );
        $document      = $source ? sanitize_key( (string) $source->get_param( 'document' ) ) : ( isset( $_REQUEST['document'] ) ? sanitize_key( wp_unslash( $_REQUEST['document'] ) ) : 'order' );
        $preview_order = $source ? absint( $source->get_param( 'preview_order' ) ) : absint( $_REQUEST['preview_order'] ?? 0 );

        if ( ! current_user_can( self::get_tab_capability( $tab ) ) ) {
            return new WP_Error(
                'eop_pdf_tab_forbidden',
                __( 'Acesso negado.', EOP_TEXT_DOMAIN ),
                array( 'status' => 403 )
            );
        }

        if ( 'proposal' !== $document ) {
            $document = 'order';
        }

        $_GET['page']      = 'eop-pedido-expresso';
        $_GET['view']      = 'pdf';
        $_GET['pdf_tab']   = $tab;
        $_GET['document']  = $document;

        if ( $preview_order > 0 ) {
            $_GET['preview_order'] = $preview_order;
        } else {
            unset( $_GET['preview_order'] );
        }

        ob_start();
        self::render_page( $tab, true );
        $html = ob_get_clean();

        return array(
            'html' => $html,
            'tab'  => $tab,
            '_performance' => class_exists( 'EOP_Performance_Audit' )
                ? EOP_Performance_Audit::get_request_metrics(
                    'pdf_tab',
                    array(
                        'tab'            => $tab,
                        'document'       => $document,
                        'response_bytes' => strlen( $html ),
                    )
                )
                : array(),
        );
    }

    public static function ajax_load_pdf_tab() {
        check_ajax_referer( 'eop_nonce', 'nonce' );

        $payload = self::get_pdf_tab_payload();

        if ( is_wp_error( $payload ) ) {
            wp_send_json_error(
                array(
                    'message' => $payload->get_error_message(),
                ),
                (int) ( $payload->get_error_data()['status'] ?? 400 )
            );
        }

        wp_send_json_success( $payload );
    }

    public static function handle_purge_cache() {
        self::assert_admin_action_permissions( 'eop_pdf_purge_cache' );

        if ( class_exists( 'EOP_Document_Manager' ) ) {
            EOP_Document_Manager::purge_cached_documents_manually();
        }

        wp_safe_redirect( self::get_tab_url( 'advanced', array( 'eop_pdf_action' => 'cache_purged' ) ) );
        exit;
    }

    public static function handle_reset_counters() {
        self::assert_admin_action_permissions( 'eop_pdf_reset_counters' );

        if ( class_exists( 'EOP_Document_Manager' ) ) {
            EOP_Document_Manager::reset_document_counters();
        }

        wp_safe_redirect( self::get_tab_url( 'advanced', array( 'eop_pdf_action' => 'counters_reset' ) ) );
        exit;
    }

    public static function handle_download_edoc_xml() {
        self::assert_admin_action_permissions( 'eop_download_edoc_xml' );

        if ( ! class_exists( 'EOP_Document_Manager' ) ) {
            wp_die( esc_html__( 'Modulo XML indisponivel.', EOP_TEXT_DOMAIN ) );
        }

        $order_id      = absint( $_GET['order_id'] ?? 0 );
        $document_type = isset( $_GET['document'] ) ? sanitize_key( wp_unslash( $_GET['document'] ) ) : '';
        $order         = $order_id ? wc_get_order( $order_id ) : null;

        if ( ! $order instanceof WC_Order ) {
            wp_die( esc_html__( 'Pedido nao encontrado.', EOP_TEXT_DOMAIN ) );
        }

        $xml = EOP_Document_Manager::get_edocument_xml_preview( $order, $document_type );

        if ( '' === $xml ) {
            wp_die( esc_html__( 'XML indisponivel para este pedido.', EOP_TEXT_DOMAIN ) );
        }

        nocache_headers();
        header( 'Content-Type: application/xml; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . EOP_Document_Manager::get_edocument_filename( $order, $document_type ) . '"' );

        echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    private static function assert_admin_action_permissions( $nonce_action ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
        }

        check_admin_referer( $nonce_action );
    }
}
