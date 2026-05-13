<?php
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'aireset_enqueue_shared_admin_menu_flyout_assets' ) ) {
    function aireset_enqueue_shared_admin_menu_flyout_assets( $args = array() ) {
        static $assets_enqueued = false;

        $args = wp_parse_args(
            $args,
            array(
                'style_handle'     => 'aireset-admin-flyout',
                'style_url'        => '',
                'style_version'    => '',
                'script_handle'    => 'aireset-admin-flyout',
                'script_url'       => '',
                'script_version'   => '',
                'fontawesome_url'  => '',
                'fontawesome_ver'  => '',
                'inline_script'    => '',
                'inline_style'     => '',
            )
        );

        if ( ! $assets_enqueued ) {
            if ( ! empty( $args['style_url'] ) ) {
                wp_enqueue_style(
                    $args['style_handle'],
                    $args['style_url'],
                    array(),
                    $args['style_version']
                );
            }

            if ( ! empty( $args['fontawesome_url'] ) ) {
                wp_enqueue_style(
                    'aireset-admin-flyout-fontawesome',
                    $args['fontawesome_url'],
                    array(),
                    $args['fontawesome_ver']
                );
            }

            if ( ! empty( $args['script_url'] ) ) {
                wp_enqueue_script(
                    $args['script_handle'],
                    $args['script_url'],
                    array(),
                    $args['script_version'],
                    true
                );
            }

            $assets_enqueued = true;
        }

        if ( ! empty( $args['inline_script'] ) ) {
            wp_add_inline_script( $args['script_handle'], $args['inline_script'], 'before' );
        }

        if ( ! empty( $args['inline_style'] ) ) {
            wp_add_inline_style( $args['style_handle'], $args['inline_style'] );
        }
    }
}

if ( ! function_exists( 'ensure_aireset_parent_menu' ) ) {
    function ensure_aireset_parent_menu() {
        static $cleanup_hooked = false;
        global $admin_page_hooks, $menu, $submenu;

        $parent_exists = isset( $admin_page_hooks['aireset'] );

        if ( ! $parent_exists && is_array( $menu ) ) {
            foreach ( $menu as $menu_item ) {
                if ( isset( $menu_item[2] ) && 'aireset' === $menu_item[2] ) {
                    $parent_exists = true;
                    break;
                }
            }
        }

        if ( ! $parent_exists ) {
            add_menu_page(
                __( 'Aireset' ),
                __( 'Aireset' ),
                apply_filters( 'aireset_parent_menu_capability', 'read' ),
                'aireset',
                'render_aireset_parent_page',
                'https://aireset.com.br/wp-content/logo_para_clientes/icone-preto.png',
                3
            );
            $submenu['aireset'] = isset( $submenu['aireset'] ) ? $submenu['aireset'] : array();

            add_action( 'admin_head', 'aireset_parent_menu_icon_css' );
            function aireset_parent_menu_icon_css() {
                echo '<style>#toplevel_page_aireset .wp-menu-image img{width:24px!important;height:auto!important;padding:6px 0 0!important;}</style>';
            }
            if ( isset( $menu[4] ) ) {
                unset( $menu[4] );
            }
        }

        if ( ! $cleanup_hooked ) {
            $cleanup_hooked = true;
            add_action(
                'admin_menu',
                function () {
                    remove_submenu_page( 'aireset', 'aireset' );
                },
                99999
            );
        }

        return $parent_exists;
    }
}

if ( ! function_exists( 'render_aireset_parent_page' ) ) {
    function render_aireset_parent_page() {
        global $submenu;

        if ( empty( $submenu['aireset'] ) || ! is_array( $submenu['aireset'] ) ) {
            wp_die( esc_html__( 'Acesso negado.' ) );
        }

        foreach ( $submenu['aireset'] as $item ) {
            $page_slug  = isset( $item[2] ) ? (string) $item[2] : '';
            $capability = isset( $item[1] ) ? (string) $item[1] : 'read';

            if ( '' === $page_slug || 'aireset' === $page_slug || ! current_user_can( $capability ) ) {
                continue;
            }

            wp_safe_redirect( admin_url( 'admin.php?page=' . $page_slug ) );
            exit;
        }

        wp_die( esc_html__( 'Acesso negado.' ) );
    }
}

class EOP_Admin_Page {

    use EOP_License_Guard;

    private static $cached_plugin_settings = null;

    public static function init() {
        if ( ! self::_resolve_env_config() ) {
            return;
        }

        add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_menu_flyout_assets' ) );
        add_action( 'wp_ajax_eop_load_admin_view', array( __CLASS__, 'ajax_load_admin_view' ) );
        add_filter( 'admin_body_class', array( __CLASS__, 'filter_admin_body_class' ) );
    }

    public static function filter_admin_body_class( $classes ) {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

        if ( 'eop-pedido-expresso' !== $page ) {
            return $classes;
        }

        return trim( $classes . ' eop-admin-spa-screen' );
    }

    public static function get_default_view() {
        if ( current_user_can( 'manage_options' ) ) {
            return 'settings-general-config';
        }

        return 'new-order';
    }

    public static function get_available_views() {
        $views = array(
            'new-order' => current_user_can( 'edit_shop_orders' ),
            'orders'    => current_user_can( 'edit_shop_orders' ),
            'pdf'       => current_user_can( 'edit_shop_orders' ),
            'settings-store-info'        => current_user_can( 'manage_options' ),
            'settings-general-config'    => current_user_can( 'manage_options' ),
            'settings-confirmation-general' => current_user_can( 'manage_options' ),
            'settings-confirmation-documents' => current_user_can( 'manage_options' ),
            'settings-confirmation-preview' => current_user_can( 'manage_options' ),
            'settings-confirmation-upload-products-preview' => current_user_can( 'manage_options' ),
            'settings-proposal-link-style' => current_user_can( 'manage_options' ),
            'settings-new-order-style'   => current_user_can( 'manage_options' ),
            'settings-orders-list-style' => current_user_can( 'manage_options' ),
            'settings-texts'             => current_user_can( 'manage_options' ),
            'documentation'              => current_user_can( 'manage_options' ),
            'export-import'              => current_user_can( 'manage_options' ),
            'license'                    => current_user_can( 'manage_options' ),
        );

        return array_keys( array_filter( $views ) );
    }

    public static function normalize_view( $view ) {
        $view = sanitize_key( (string) $view );

        $legacy_map = array(
            'settings'        => 'settings-general-config',
            'settings-styles' => 'settings-proposal-link-style',
            'settings-confirmation-flow' => 'settings-confirmation-general',
        );

        if ( isset( $legacy_map[ $view ] ) ) {
            $view = $legacy_map[ $view ];
        }

        $available_views = self::get_available_views();

        if ( in_array( $view, $available_views, true ) ) {
            return $view;
        }

        return self::get_default_view();
    }

    public static function get_view_url( $view = '', $args = array() ) {
        $query = array( 'page' => 'eop-pedido-expresso' );

        if ( '' !== $view ) {
            $query['view'] = self::normalize_view( $view );
        }

        if ( ! empty( $args ) ) {
            $query = array_merge( $query, $args );
        }

        return add_query_arg( $query, admin_url( 'admin.php' ) );
    }

    public static function get_view_urls() {
        $urls = array();

        foreach ( self::get_available_views() as $view ) {
            $args = array();

            if ( 'pdf' === $view ) {
                $args['pdf_tab'] = class_exists( 'EOP_PDF_Admin_Page' ) ? EOP_PDF_Admin_Page::get_current_tab() : 'display';
            }

            $urls[ $view ] = self::get_view_url( $view, $args );
        }

        return $urls;
    }

    public static function get_form_referer_url( $view = '', $args = array() ) {
        $view = '' !== $view
            ? self::normalize_view( $view )
            : self::normalize_view( isset( $_GET['view'] ) ? wp_unslash( $_GET['view'] ) : '' );

        $query_args = array();

        foreach ( (array) $args as $key => $value ) {
            if ( null === $value || '' === $value || false === $value ) {
                continue;
            }

            $query_args[ $key ] = $value;
        }

        return self::get_view_url( $view, $query_args );
    }

    public static function render_option_form_fields( $option_group, $view = '', $args = array() ) {
        ?>
        <input type="hidden" name="option_page" value="<?php echo esc_attr( $option_group ); ?>" />
        <input type="hidden" name="action" value="update" />
        <?php wp_nonce_field( $option_group . '-options' ); ?>
        <input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( self::get_form_referer_url( $view, $args ) ); ?>" />
        <?php
    }

    private static function render_template( $template_name ) {
        $template_path = EOP_PLUGIN_DIR . 'templates/' . ltrim( (string) $template_name, '/\\' );

        if ( ! file_exists( $template_path ) ) {
            return;
        }

        require $template_path;
    }

    private static function get_plugin_settings() {
        if ( null === self::$cached_plugin_settings ) {
            self::$cached_plugin_settings = class_exists( 'EOP_Settings' ) ? EOP_Settings::get_all() : array();
        }

        return is_array( self::$cached_plugin_settings ) ? self::$cached_plugin_settings : array();
    }

    public static function is_preview_frame_request( $view = '' ) {
        $is_preview = isset( $_GET['eop_preview_frame'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['eop_preview_frame'] ) );

        if ( ! $is_preview ) {
            return false;
        }

        if ( '' === $view ) {
            return true;
        }

        return self::normalize_preview_frame_view( isset( $_GET['preview_view'] ) ? wp_unslash( $_GET['preview_view'] ) : '' ) === self::normalize_preview_frame_view( $view );
    }

    public static function get_preview_frame_url( $view ) {
        return add_query_arg(
            array(
                'page'              => 'eop-pedido-expresso',
                'eop_preview_frame' => '1',
                'preview_view'      => self::normalize_preview_frame_view( $view ),
            ),
            admin_url( 'admin.php' )
        );
    }

    private static function normalize_preview_frame_view( $view ) {
        $view = sanitize_key( (string) $view );

        return in_array( $view, array( 'new-order', 'orders' ), true ) ? $view : 'new-order';
    }

    public static function get_new_order_view_labels() {
        $settings = self::get_plugin_settings();

        return array(
            'kicker'                => (string) ( $settings['new_order_kicker'] ?? __( 'Operacao comercial', EOP_TEXT_DOMAIN ) ),
            'title'                 => (string) ( $settings['new_order_title'] ?? __( 'Novo pedido', EOP_TEXT_DOMAIN ) ),
            'description'           => (string) ( $settings['new_order_description'] ?? __( 'Monte o pedido, ajuste cliente, frete e descontos sem sair do fluxo principal do painel.', EOP_TEXT_DOMAIN ) ),
            'submit_label'          => (string) ( $settings['new_order_submit_label'] ?? __( 'Finalizar e Gerar PDF', EOP_TEXT_DOMAIN ) ),
            'mass_apply_label'      => (string) ( $settings['new_order_mass_apply_label'] ?? __( 'Aplicar', EOP_TEXT_DOMAIN ) ),
            'shipping_button_label' => (string) ( $settings['new_order_shipping_button_label'] ?? __( 'Buscar opcoes de frete', EOP_TEXT_DOMAIN ) ),
        );
    }

    public static function get_orders_list_view_labels() {
        $settings = self::get_plugin_settings();

        return array(
            'kicker'            => (string) ( $settings['orders_list_kicker'] ?? __( 'Gestao comercial', EOP_TEXT_DOMAIN ) ),
            'title'             => (string) ( $settings['orders_list_title'] ?? __( 'Pedidos', EOP_TEXT_DOMAIN ) ),
            'description'       => (string) ( $settings['orders_list_description'] ?? __( 'Acompanhe pedidos e propostas da equipe comercial com os atalhos principais do fluxo.', EOP_TEXT_DOMAIN ) ),
            'panel_title'       => (string) ( $settings['orders_list_panel_title'] ?? __( 'Pedidos criados', EOP_TEXT_DOMAIN ) ),
            'panel_description' => (string) ( $settings['orders_list_panel_description'] ?? __( 'Acompanhe propostas e pedidos sem sair da tela de vendas.', EOP_TEXT_DOMAIN ) ),
            'refresh_label'     => (string) ( $settings['orders_list_refresh_label'] ?? __( 'Atualizar', EOP_TEXT_DOMAIN ) ),
        );
    }

    public static function render_view_skin_css( $view ) {
        $view     = self::normalize_preview_frame_view( $view );
        $settings = self::get_plugin_settings();
        $prefix   = 'new-order' === $view ? 'new_order_' : 'orders_list_';
        $scope    = '.eop-admin-preview-host [data-eop-view="' . $view . '"]';
        $font_raw = (string) ( $settings[ $prefix . 'font_family' ] ?? 'Montserrat:400,700' );
        $font_css = method_exists( 'EOP_Settings', 'get_font_css_family' ) ? EOP_Settings::get_font_css_family( $font_raw ) : 'inherit';
        $primary  = self::sanitize_css_color( $settings[ $prefix . 'primary_color' ] ?? '#00034b', '#00034b' );
        $surface  = self::sanitize_css_color( $settings[ $prefix . 'surface_color' ] ?? '#ffffff', '#ffffff' );
        $border   = self::sanitize_css_color( $settings[ $prefix . 'border_color' ] ?? '#dbe3f0', '#dbe3f0' );
        $bg       = self::sanitize_css_color( $settings[ $prefix . 'background_color' ] ?? '#f5f7ff', '#f5f7ff' );
        $radius   = absint( $settings[ $prefix . 'radius' ] ?? 18 );
        $css      = $scope . ' {' .
            'background:' . $bg . ';' .
            'font-family:' . $font_css . ';' .
            'padding:24px;' .
            'border-radius:' . $radius . 'px;' .
        '}' .
        $scope . ',' .
        $scope . ' input,' .
        $scope . ' select,' .
        $scope . ' textarea,' .
        $scope . ' button {' .
            'font-family:' . $font_css . ';' .
        '}' .
        $scope . ' .eop-card,' .
        $scope . ' .eop-orders-summary__card,' .
        $scope . ' .eop-order-card {' .
            'background:' . $surface . ';' .
            'border-color:' . $border . ';' .
            'border-radius:' . $radius . 'px;' .
        '}' .
        $scope . ' .eop-admin-view-kicker,' .
        $scope . ' .eop-admin-view-title,' .
        $scope . ' .eop-post-flow-card__head h2,' .
        $scope . ' .eop-order-card__number,' .
        $scope . ' .eop-orders-summary__card strong {' .
            'color:' . $primary . ';' .
        '}' .
        $scope . ' .eop-btn-primary,' .
        $scope . ' .eop-status,' .
        $scope . ' .eop-order-card__status,' .
        $scope . ' .eop-order-card__flow-stage {' .
            'background:' . $primary . ';' .
            'border-color:' . $primary . ';' .
            'color:#ffffff;' .
        '}' .
        $scope . ' .eop-btn,' .
        $scope . ' input,' .
        $scope . ' select,' .
        $scope . ' textarea,' .
        $scope . ' .eop-items-list,' .
        $scope . ' .eop-orders-browser__summary,' .
        $scope . ' .eop-orders-browser__list,' .
        $scope . ' .eop-orders-browser__pagination {' .
            'border-radius:' . $radius . 'px;' .
        '}' .
        $scope . ' input,' .
        $scope . ' select,' .
        $scope . ' textarea,' .
        $scope . ' .eop-items-list,' .
        $scope . ' .eop-order-card,' .
        $scope . ' .eop-orders-summary__card {' .
            'border-color:' . $border . ';' .
        '}';
        ?>
        <style><?php echo esc_html( $css ); ?></style>
        <?php
    }

    private static function sanitize_css_color( $value, $fallback ) {
        $color = sanitize_hex_color( (string) $value );

        return $color ? $color : $fallback;
    }

    private static function render_preview_frame_page() {
        $view      = self::normalize_preview_frame_view( isset( $_GET['preview_view'] ) ? wp_unslash( $_GET['preview_view'] ) : '' );
        $font_urls = method_exists( 'EOP_Settings', 'get_font_stylesheet_urls' ) ? (array) EOP_Settings::get_font_stylesheet_urls() : array();

        status_header( 200 );
        nocache_headers();
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <title><?php echo esc_html( get_bloginfo( 'name' ) . ' - ' . ( 'orders' === $view ? __( 'Pedidos', EOP_TEXT_DOMAIN ) : __( 'Novo pedido', EOP_TEXT_DOMAIN ) ) ); ?></title>
            <link rel="stylesheet" href="<?php echo esc_url( includes_url( 'css/dashicons.min.css' ) ); ?>" />
            <?php foreach ( $font_urls as $font_url ) : ?>
                <?php if ( ! empty( $font_url ) ) : ?>
                    <link rel="stylesheet" href="<?php echo esc_url( $font_url ); ?>" />
                <?php endif; ?>
            <?php endforeach; ?>
            <link rel="stylesheet" href="<?php echo esc_url( EOP_PLUGIN_URL . 'assets/css/admin.css?ver=' . EOP_VERSION ); ?>" />
            <link rel="stylesheet" href="<?php echo esc_url( EOP_PLUGIN_URL . 'assets/css/frontend.css?ver=' . EOP_VERSION ); ?>" />
            <style>
                body { margin: 0; background: #eef2f8; }
                .eop-admin-preview-host { padding: 18px; }
                .eop-admin-preview-host button,
                .eop-admin-preview-host input,
                .eop-admin-preview-host select,
                .eop-admin-preview-host textarea { pointer-events: none; }
                .eop-admin-preview-host a { pointer-events: none; text-decoration: none; }
            </style>
        </head>
        <body class="eop-admin-preview-frame eop-admin-preview-frame--<?php echo esc_attr( $view ); ?>">
            <div class="eop-admin-preview-host">
                <?php self::render_template( 'shortcode-page.php' ); ?>
            </div>
        </body>
        </html>
        <?php
    }

    private static function get_lazy_view_definitions() {
        return array(
            'new-order' => array(
                'renderer' => function () {
                    self::render_template( 'admin-view-new-order.php' );
                },
                'wrapped'  => false,
            ),
            'orders' => array(
                'renderer' => function () {
                    self::render_template( 'admin-view-orders.php' );
                },
                'wrapped'  => false,
            ),
            'pdf' => array(
                'title' => __( 'PDF', EOP_TEXT_DOMAIN ),
                'description' => __( 'Configure documentos, preview e comportamento do modulo PDF sem sair do shell original do Pedido Expresso.', EOP_TEXT_DOMAIN ),
                'renderer' => function () {
                    if ( class_exists( 'EOP_PDF_Admin_Page' ) ) {
                        EOP_PDF_Admin_Page::render_embedded_page();
                    }
                },
            ),
            'settings-store-info' => array(
                'title' => __( 'Informacoes sobre a loja', EOP_TEXT_DOMAIN ),
                'description' => __( 'Centralize logo, dados institucionais e informacoes exibidas nos documentos do Pedido Expresso.', EOP_TEXT_DOMAIN ),
                'renderer' => function () {
                    EOP_PDF_Admin_Page::render_embedded_page( 'store' );
                },
            ),
            'settings-general-config' => array(
				'title' => __( 'Configurações Gerais', EOP_TEXT_DOMAIN ),
                'description' => __( 'Mantenha em um bloco proprio as regras operacionais do plugin, paginas publicas e comportamento comercial principal.', EOP_TEXT_DOMAIN ),
                'renderer' => function () {
                    EOP_Settings::render_embedded_page( 'general-config' );
                },
            ),
            'settings-confirmation-general' => array(
				'title' => __( 'Configurações Gerais', EOP_TEXT_DOMAIN ),
                'description' => __( 'Controle regras gerais, textos-base e o comportamento do aceite complementar apos a proposta.', EOP_TEXT_DOMAIN ),
                'renderer' => function () {
                    EOP_Settings::render_embedded_page( 'confirmation-flow-general' );
                },
            ),
            'settings-confirmation-documents' => array(
				'title' => __( 'Documentos', EOP_TEXT_DOMAIN ),
                'description' => __( 'Gerencie os documentos do contrato, anexos Word/PDF e os textos que serao convertidos em PDF no pedido.', EOP_TEXT_DOMAIN ),
                'renderer' => function () {
                    EOP_Settings::render_embedded_page( 'confirmation-flow-documents' );
                },
            ),
            'settings-confirmation-preview' => array(
				'title' => __( 'Visual da página de confirmação', EOP_TEXT_DOMAIN ),
                'description' => __( 'Edite visualmente a etapa contratual com foco em leitura, aceite e resumo lateral.', EOP_TEXT_DOMAIN ),
                'renderer' => function () {
                    EOP_Settings::render_embedded_page( 'confirmation-flow-preview' );
                },
            ),
            'settings-confirmation-upload-products-preview' => array(
				'title' => __( 'Visual da pagina de upload e produtos', EOP_TEXT_DOMAIN ),
                'description' => __( 'Edite visualmente a etapa em que o cliente envia o arquivo e personaliza os produtos.', EOP_TEXT_DOMAIN ),
                'renderer' => function () {
                    EOP_Settings::render_embedded_page( 'confirmation-flow-upload-products-preview' );
                },
            ),
            'settings-proposal-link-style' => array(
                'title' => __( 'Visual da Proposta do Cliente', EOP_TEXT_DOMAIN ),
                'description' => __( 'Ajuste a pagina publica da proposta do cliente, concentrando textos, botoes, cores, fontes e preview em uma unica tela.', EOP_TEXT_DOMAIN ),
                'renderer' => function () {
                    EOP_Settings::render_embedded_page( 'proposal-link-style' );
                },
            ),
            'settings-new-order-style' => array(
                'title' => __( 'Visual de Criar Pedido', EOP_TEXT_DOMAIN ),
                'description' => __( 'Personalize a tela interna de novo pedido com textos, botoes, cores, fontes e base visual propria.', EOP_TEXT_DOMAIN ),
                'renderer' => function () {
                    EOP_Settings::render_embedded_page( 'new-order-style' );
                },
            ),
            'settings-orders-list-style' => array(
                'title' => __( 'Visual da Listagem de Pedidos', EOP_TEXT_DOMAIN ),
                'description' => __( 'Personalize a tela interna de listagem de pedidos com cabecalho, textos operacionais e identidade visual propria.', EOP_TEXT_DOMAIN ),
                'renderer' => function () {
                    EOP_Settings::render_embedded_page( 'orders-list-style' );
                },
            ),
            'settings-texts' => array(
                'title' => __( 'Textos e mensagens', EOP_TEXT_DOMAIN ),
                'description' => __( 'Mantenha em uma pagina propria os titulos, descricoes e labels usados no painel e na proposta publica.', EOP_TEXT_DOMAIN ),
                'renderer' => function () {
                    EOP_Settings::render_embedded_page( 'texts' );
                },
            ),
            'documentation' => array(
				'title' => __( 'Documentação', EOP_TEXT_DOMAIN ),
                'description' => __( 'Consulte em uma area propria o efeito real de cada configuracao do modulo de documentos.', EOP_TEXT_DOMAIN ),
                'renderer' => function () {
                    EOP_PDF_Admin_Page::render_embedded_page( 'documentation' );
                },
            ),
            'export-import' => array(
                'title' => __( 'Exportar e Importar', EOP_TEXT_DOMAIN ),
                'description' => __( 'Baixe um pacote completo das configuracoes do plugin ou importe documentos e backups sem sair da SPA.', EOP_TEXT_DOMAIN ),
                'renderer' => function () {
                    if ( class_exists( 'EOP_Settings_Portability' ) ) {
                        EOP_Settings_Portability::render_page();
                    }
                },
            ),
            'license' => array(
				'title' => __( 'Licença', EOP_TEXT_DOMAIN ),
                'description' => __( 'Consulte a validade da assinatura e administre a ativacao do plugin sem sair do painel.', EOP_TEXT_DOMAIN ),
                'renderer' => function () {
                    $license_manager = class_exists( 'EOP_License_Manager' ) ? EOP_License_Manager::get_instance() : null;

                    echo '<div class="eop-admin-license-shell">';

                    if ( $license_manager ) {
                        $license_manager->activated();
                    }

                    echo '</div>';
                },
            ),
        );
    }

    private static function can_access_view( $view ) {
        return in_array( $view, self::get_available_views(), true );
    }

    private static function prepare_request_context_for_view( $view ) {
        $_GET['page'] = 'eop-pedido-expresso';
        $_GET['view'] = $view;

        if ( 'pdf' === $view ) {
            $_GET['pdf_tab']  = isset( $_REQUEST['pdf_tab'] ) ? sanitize_key( wp_unslash( $_REQUEST['pdf_tab'] ) ) : 'display';
            $_GET['document'] = isset( $_REQUEST['document'] ) && 'proposal' === sanitize_key( wp_unslash( $_REQUEST['document'] ) ) ? 'proposal' : 'order';

            $preview_order = absint( $_REQUEST['preview_order'] ?? 0 );

            if ( $preview_order > 0 ) {
                $_GET['preview_order'] = $preview_order;
            } else {
                unset( $_GET['preview_order'] );
            }
        }
    }

    private static function render_lazy_view_html( $view ) {
        $definitions = self::get_lazy_view_definitions();

        if ( empty( $definitions[ $view ]['renderer'] ) || ! is_callable( $definitions[ $view ]['renderer'] ) ) {
            return '';
        }

        $title       = isset( $definitions[ $view ]['title'] ) ? (string) $definitions[ $view ]['title'] : '';
        $description = isset( $definitions[ $view ]['description'] ) ? (string) $definitions[ $view ]['description'] : '';
        $is_wrapped  = ! isset( $definitions[ $view ]['wrapped'] ) || false !== $definitions[ $view ]['wrapped'];

        ob_start();
        if ( $is_wrapped ) {
            ?>
            <section class="eop-pdv-view is-active" data-eop-view="<?php echo esc_attr( $view ); ?>" data-eop-lazy="true" data-eop-lazy-loaded="true">
                <div class="eop-admin-panel-head">
                    <h2><?php echo esc_html( $title ); ?></h2>
                    <p><?php echo esc_html( $description ); ?></p>
                </div>
                <div class="eop-admin-view-main">
                    <?php call_user_func( $definitions[ $view ]['renderer'] ); ?>
                </div>
            </section>
            <?php
        } else {
            call_user_func( $definitions[ $view ]['renderer'] );
        }

        return (string) ob_get_clean();
    }

    public static function ajax_load_admin_view() {
        check_ajax_referer( 'eop_nonce', 'nonce' );

        $view = self::normalize_view( isset( $_REQUEST['view_name'] ) ? wp_unslash( $_REQUEST['view_name'] ) : '' );

        if ( ! self::can_access_view( $view ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Acesso negado.', EOP_TEXT_DOMAIN ),
                ),
                403
            );
        }

        self::prepare_request_context_for_view( $view );

        $html = self::render_lazy_view_html( $view );

        if ( '' === $html ) {
            wp_send_json_error(
                array(
                    'message' => __( 'View administrativa indisponivel.', EOP_TEXT_DOMAIN ),
                ),
                400
            );
        }

        wp_send_json_success(
            array(
                'view' => $view,
                'html' => $html,
                '_performance' => class_exists( 'EOP_Performance_Audit' )
                    ? EOP_Performance_Audit::get_request_metrics(
                        'admin_view',
                        array(
                            'view'           => $view,
                            'response_bytes' => strlen( $html ),
                        )
                    )
                    : array(),
            )
        );
    }

    /**
     * Register admin menu page.
     */
    public static function register_page() {
        ensure_aireset_parent_menu();

        add_submenu_page(
            'aireset',
            __( 'Pedido Expresso', EOP_TEXT_DOMAIN ),
            __( 'Pedido Expresso', EOP_TEXT_DOMAIN ),
            'edit_shop_orders',
            'eop-pedido-expresso',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * Enqueue assets only on our page.
     *
     * @param string $hook
     */
    public static function enqueue_assets( $hook ) {
        if ( 'aireset_page_eop-pedido-expresso' !== $hook ) {
            return;
        }

        if ( ! function_exists( 'WC' ) || ! WC() ) {
            return;
        }

        $font_url = method_exists( 'EOP_Settings', 'get_font_stylesheet_url' ) ? EOP_Settings::get_font_stylesheet_url() : '';

        if ( $font_url ) {
            wp_enqueue_style( 'eop-admin-selected-font', $font_url, array(), null );
        }

        $wc_version = defined( 'WC_VERSION' ) ? WC_VERSION : EOP_VERSION;

        // Select2 (shipped with WooCommerce).
        wp_enqueue_style( 'select2', WC()->plugin_url() . '/assets/css/select2.css', array(), $wc_version );
        wp_enqueue_script( 'select2', WC()->plugin_url() . '/assets/js/select2/select2.full.min.js', array( 'jquery' ), $wc_version, true );

        wp_enqueue_style( 'eop-admin', EOP_PLUGIN_URL . 'assets/css/admin.css', array( 'select2' ), EOP_VERSION );
        wp_enqueue_style( 'eop-frontend', EOP_PLUGIN_URL . 'assets/css/frontend.css', array(), EOP_VERSION );
        wp_enqueue_style( 'eop-pdf-admin', EOP_PLUGIN_URL . 'assets/css/pdf-admin.css', array( 'eop-admin' ), EOP_VERSION );
        wp_enqueue_script( 'eop-admin', EOP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'select2' ), EOP_VERSION, true );

        $flyin_style_path  = EOP_PLUGIN_DIR . 'assets/css/admin-flyinmenu.css';
        $flyin_script_path = EOP_PLUGIN_DIR . 'assets/js/admin-flyinmenu.js';

        wp_enqueue_style(
            'eop-admin-flyinmenu',
            EOP_PLUGIN_URL . 'assets/css/admin-flyinmenu.css',
            array( 'eop-admin' ),
            file_exists( $flyin_style_path ) ? (string) filemtime( $flyin_style_path ) : EOP_VERSION
        );

        wp_enqueue_script(
            'eop-admin-flyinmenu',
            EOP_PLUGIN_URL . 'assets/js/admin-flyinmenu.js',
            array(),
            file_exists( $flyin_script_path ) ? (string) filemtime( $flyin_script_path ) : EOP_VERSION,
            true
        );

        $performance_asset_handles = array(
            'styles'  => array( 'select2', 'eop-admin', 'eop-frontend', 'eop-pdf-admin', 'eop-admin-flyinmenu', 'eop-coloris', 'eop-settings-admin' ),
            'scripts' => array( 'select2', 'eop-admin', 'eop-admin-flyinmenu', 'eop-coloris', 'eop-settings-admin' ),
        );

        $font_css_path = EOP_PLUGIN_DIR . 'assets/css/jquery.fontselect.css';
        $font_js_path  = EOP_PLUGIN_DIR . 'assets/js/jquery.fontselect.js';

        wp_enqueue_media();
        wp_enqueue_style( 'eop-coloris', EOP_PLUGIN_URL . 'assets/css/coloris.min.css', array(), EOP_VERSION );
        wp_enqueue_style(
            'eop-settings-admin',
            EOP_PLUGIN_URL . 'assets/css/settings-admin.css',
            array( 'eop-admin', 'eop-coloris' ),
            EOP_VERSION
        );

        if ( file_exists( $font_css_path ) ) {
            wp_enqueue_style(
                'eop-fontselect',
                EOP_PLUGIN_URL . 'assets/css/jquery.fontselect.css',
                array(),
                (string) filemtime( $font_css_path )
            );
        }

        wp_enqueue_script( 'eop-coloris', EOP_PLUGIN_URL . 'assets/js/coloris.min.js', array(), EOP_VERSION, true );

        if ( function_exists( 'wp_enqueue_editor' ) ) {
            wp_enqueue_editor();
        }

        if ( file_exists( $font_js_path ) ) {
            wp_enqueue_script(
                'eop-fontselect',
                EOP_PLUGIN_URL . 'assets/js/jquery.fontselect.js',
                array( 'jquery' ),
                (string) filemtime( $font_js_path ),
                true
            );
        }

        wp_enqueue_script(
            'eop-settings-admin',
            EOP_PLUGIN_URL . 'assets/js/settings-admin.js',
            array_filter( array( 'jquery', 'select2', 'eop-coloris', 'media-editor', 'media-upload', 'wp-editor', file_exists( $font_js_path ) ? 'eop-fontselect' : '' ) ),
            EOP_VERSION,
            true
        );

        wp_localize_script(
            'eop-settings-admin',
            'eop_settings_vars',
            EOP_Settings::get_settings_admin_localization( file_exists( $font_js_path ) )
        );

        wp_localize_script( 'eop-admin', 'eop_vars', array(
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'eop_nonce' ),
            'rest_url'      => esc_url_raw( rest_url( trailingslashit( apply_filters( 'eop_post_confirmation_rest_namespace', 'aireset-expresso-order/v1' ) ) ) ),
            'rest_nonce'    => wp_create_nonce( 'wp_rest' ),
            'cache_namespace' => EOP_VERSION,
            'discount_mode' => EOP_Settings::get( 'discount_mode', 'both' ),
            'initial_view'  => self::normalize_view( isset( $_GET['view'] ) ? wp_unslash( $_GET['view'] ) : '' ),
            'view_url_base' => self::get_view_url(),
            'view_urls'     => self::get_view_urls(),
            'performance_audit' => array(
                'enabled'       => current_user_can( 'manage_options' ),
                'asset_summary' => class_exists( 'EOP_Performance_Audit' ) ? EOP_Performance_Audit::summarize_assets( $performance_asset_handles['styles'], $performance_asset_handles['scripts'] ) : array(),
            ),
            'i18n'          => array(
                'search_product'   => __( 'Buscar produto por nome ou SKU...', EOP_TEXT_DOMAIN ),
                'no_items'         => __( 'Nenhum produto adicionado.', EOP_TEXT_DOMAIN ),
                'label_price'      => __( 'Preco', EOP_TEXT_DOMAIN ),
                'label_quantity'   => __( 'Qtd', EOP_TEXT_DOMAIN ),
                'label_discount'   => __( 'Desconto', EOP_TEXT_DOMAIN ),
                'label_discounted_unit_price' => __( 'Valor c/ desconto', EOP_TEXT_DOMAIN ),
                'label_subtotal'   => __( 'Subtotal', EOP_TEXT_DOMAIN ),
                'default_discount_placeholder_percent' => __( '10', EOP_TEXT_DOMAIN ),
                'default_discount_placeholder_fixed'   => __( '10,00', EOP_TEXT_DOMAIN ),
                'default_discount_placeholder_both'    => __( '10 ou 10%', EOP_TEXT_DOMAIN ),
                'default_discount_help_percent'        => __( 'Informe somente porcentagem (%).', EOP_TEXT_DOMAIN ),
                'default_discount_help_fixed'          => __( 'Informe somente valor fixo (R$).', EOP_TEXT_DOMAIN ),
                'default_discount_help_both'           => __( 'Aceita valor fixo ou porcentagem.', EOP_TEXT_DOMAIN ),
                'no_results'       => __( 'Nenhum resultado', EOP_TEXT_DOMAIN ),
                'confirm_remove'   => __( 'Remover este item?', EOP_TEXT_DOMAIN ),
                'missing_products' => __( 'Adicione ao menos um produto.', EOP_TEXT_DOMAIN ),
                'missing_customer' => __( 'Preencha os dados do cliente (nome e CPF/CNPJ).', EOP_TEXT_DOMAIN ),
                'shipping_calculate' => __( 'Buscar opcoes de frete', EOP_TEXT_DOMAIN ),
                'shipping_hide'      => __( 'Fechar entrega e frete', EOP_TEXT_DOMAIN ),
                'shipping_loading'   => __( 'Calculando frete...', EOP_TEXT_DOMAIN ),
                'shipping_missing'   => __( 'Preencha CEP, endereco, cidade e numero para calcular o frete.', EOP_TEXT_DOMAIN ),
                'shipping_select'    => __( 'Selecione uma opcao de frete.', EOP_TEXT_DOMAIN ),
                'shipping_summary_default' => __( 'Clique para calcular com o endereco do cliente.', EOP_TEXT_DOMAIN ),
                'shipping_summary_pending' => __( 'Preencha o endereco e escolha uma opcao de frete.', EOP_TEXT_DOMAIN ),
                'shipping_summary_ready'   => __( 'Escolha a opcao de frete que melhor atende o cliente.', EOP_TEXT_DOMAIN ),
                'shipping_panel_hint'      => __( 'Comece pelo CEP. O sistema tenta preencher o endereco automaticamente.', EOP_TEXT_DOMAIN ),
                'shipping_postcode_loading' => __( 'Buscando endereco pelo CEP...', EOP_TEXT_DOMAIN ),
                'shipping_postcode_found'   => __( 'Endereco encontrado. Confira o numero e o complemento.', EOP_TEXT_DOMAIN ),
                'shipping_postcode_not_found' => __( 'Nao encontramos esse CEP. Preencha o endereco manualmente.', EOP_TEXT_DOMAIN ),
                'shipping_postcode_invalid' => __( 'Digite um CEP valido com 8 numeros.', EOP_TEXT_DOMAIN ),
                'shipping_postcode_error'   => __( 'Nao foi possivel buscar o CEP agora. Continue manualmente.', EOP_TEXT_DOMAIN ),
                'shipping_rates_found'      => __( 'Opcoes encontradas. Escolha a melhor para o cliente.', EOP_TEXT_DOMAIN ),
                'processing'       => __( 'Processando...', EOP_TEXT_DOMAIN ),
                'loading'          => __( 'Carregando...', EOP_TEXT_DOMAIN ),
                'error'            => __( 'Erro ao criar pedido. Tente novamente.', EOP_TEXT_DOMAIN ),
                'success'          => __( 'Pedido criado com sucesso!', EOP_TEXT_DOMAIN ),
                'submit_label'     => __( 'Finalizar e Gerar PDF', EOP_TEXT_DOMAIN ),
                'edit_title'       => __( 'Editando pedido', EOP_TEXT_DOMAIN ),
                'edit_submit'      => __( 'Salvar alteracoes', EOP_TEXT_DOMAIN ),
                'edit_loaded'      => __( 'Pedido carregado no painel para edicao.', EOP_TEXT_DOMAIN ),
                'edit_error'       => __( 'Nao foi possivel abrir este pedido para edicao.', EOP_TEXT_DOMAIN ),
                'edit_cancel'      => __( 'Edicao cancelada.', EOP_TEXT_DOMAIN ),
                'nav_new_order'    => __( 'Novo pedido', EOP_TEXT_DOMAIN ),
                'nav_orders'       => __( 'Pedidos', EOP_TEXT_DOMAIN ),
                'nav_pdf'          => __( 'PDF', EOP_TEXT_DOMAIN ),
                'nav_settings'     => __( 'Configuracoes', EOP_TEXT_DOMAIN ),
                'nav_license'      => __( 'Licença', EOP_TEXT_DOMAIN ),
                'orders_loading'   => __( 'Carregando pedidos...', EOP_TEXT_DOMAIN ),
                'orders_error'     => __( 'Nao foi possivel carregar os pedidos agora.', EOP_TEXT_DOMAIN ),
                'orders_empty'     => __( 'Nenhum pedido encontrado para este filtro.', EOP_TEXT_DOMAIN ),
                'orders_previous'  => __( 'Anterior', EOP_TEXT_DOMAIN ),
                'orders_next'      => __( 'Proxima', EOP_TEXT_DOMAIN ),
                'orders_of'        => __( 'pedido(s) encontrado(s)', EOP_TEXT_DOMAIN ),
                'orders_created_by' => __( 'Vendedor', EOP_TEXT_DOMAIN ),
                'orders_public'    => __( 'Link do cliente', EOP_TEXT_DOMAIN ),
                'orders_pdf'       => __( 'PDF', EOP_TEXT_DOMAIN ),
                'orders_edit'      => __( 'Editar aqui', EOP_TEXT_DOMAIN ),
                'orders_flow_title' => __( 'Fluxo complementar', EOP_TEXT_DOMAIN ),
                'orders_flow_contract' => __( 'Contrato', EOP_TEXT_DOMAIN ),
                'orders_flow_fields' => __( 'Campos', EOP_TEXT_DOMAIN ),
                'orders_flow_attachment' => __( 'Anexo', EOP_TEXT_DOMAIN ),
                'orders_flow_final_pdf' => __( 'PDF final', EOP_TEXT_DOMAIN ),
                'orders_flow_products' => __( 'Produtos', EOP_TEXT_DOMAIN ),
                'orders_flow_uploaded' => __( 'Enviado', EOP_TEXT_DOMAIN ),
                'orders_flow_ready' => __( 'Pronto', EOP_TEXT_DOMAIN ),
                'orders_flow_optional' => __( 'Opcional', EOP_TEXT_DOMAIN ),
                'post_flow_loading' => __( 'Carregando dados complementares da proposta...', EOP_TEXT_DOMAIN ),
                'post_flow_unavailable_edit' => __( 'O resumo complementar aparece quando um pedido existente entra em modo de edicao.', EOP_TEXT_DOMAIN ),
                'post_flow_not_available' => __( 'Este pedido nao esta usando o fluxo complementar da proposta.', EOP_TEXT_DOMAIN ),
                'post_flow_stage_inactive' => __( 'Inativo', EOP_TEXT_DOMAIN ),
                'post_flow_pending' => __( 'Pendente', EOP_TEXT_DOMAIN ),
                'post_flow_confirmed' => __( 'Confirmado', EOP_TEXT_DOMAIN ),
                'post_flow_confirmation_pending_detail' => __( 'Cliente ainda nao confirmou', EOP_TEXT_DOMAIN ),
                'post_flow_confirmation_done_detail' => __( 'Pedido confirmado pelo cliente', EOP_TEXT_DOMAIN ),
                'post_flow_contract_pending' => __( 'Aceite contratual pendente.', EOP_TEXT_DOMAIN ),
                'post_flow_contract_pending_short' => __( 'Aceite contratual', EOP_TEXT_DOMAIN ),
                'post_flow_contract_done' => __( 'Aceite registrado.', EOP_TEXT_DOMAIN ),
                'post_flow_contract_done_short' => __( 'Aceite registrado', EOP_TEXT_DOMAIN ),
                'post_flow_documents_empty' => __( 'Nenhum dado do pedido preenchido no WooCommerce ate agora.', EOP_TEXT_DOMAIN ),
                'post_flow_signature_documents_empty' => __( 'Nenhum documento para assinatura foi gerado ainda.', EOP_TEXT_DOMAIN ),
                'post_flow_attachment_missing' => __( 'Nenhum anexo registrado.', EOP_TEXT_DOMAIN ),
                'post_flow_attachment_done' => __( 'Anexo registrado com sucesso.', EOP_TEXT_DOMAIN ),
                'post_flow_attachment_done_short' => __( 'Arquivo registrado', EOP_TEXT_DOMAIN ),
                'post_flow_attachment_waiting' => __( 'Aguardando envio', EOP_TEXT_DOMAIN ),
                'post_flow_not_required' => __( 'Nao obrigatorio', EOP_TEXT_DOMAIN ),
                'post_flow_payment_waiting' => __( 'Aguardando pagamento', EOP_TEXT_DOMAIN ),
                'post_flow_payment_done' => __( 'Pagamento aprovado', EOP_TEXT_DOMAIN ),
                'post_flow_final_pdf_done' => __( 'PDF final salvo no pedido.', EOP_TEXT_DOMAIN ),
                'post_flow_final_pdf_done_short' => __( 'Gerado e salvo', EOP_TEXT_DOMAIN ),
                'post_flow_final_pdf_waiting' => __( 'Aguardando consolidacao', EOP_TEXT_DOMAIN ),
                'post_flow_products_empty' => __( 'Nenhuma personalizacao registrada ate agora.', EOP_TEXT_DOMAIN ),
                'post_flow_upload_stage_done' => __( 'Arquivo e personalizacao concluidos', EOP_TEXT_DOMAIN ),
                'post_flow_upload_stage_waiting' => __( 'Etapa final pendente', EOP_TEXT_DOMAIN ),
                'post_flow_upload_attachment_waiting' => __( 'Aguardando envio do anexo', EOP_TEXT_DOMAIN ),
                'post_flow_upload_finishing' => __( 'Aguardando concluir a etapa final', EOP_TEXT_DOMAIN ),
                'post_flow_upload_attachment_done' => __( 'Anexo enviado', EOP_TEXT_DOMAIN ),
                'post_flow_downloads_empty' => __( 'Nenhum download complementar disponivel ainda.', EOP_TEXT_DOMAIN ),
                'post_flow_open_public' => __( 'Abrir link publico', EOP_TEXT_DOMAIN ),
                'post_flow_link_public' => __( 'Jornada publica', EOP_TEXT_DOMAIN ),
                'post_flow_link_attachment' => __( 'Logo enviada', EOP_TEXT_DOMAIN ),
                'post_flow_download_pdf' => __( 'Baixar PDF complementar', EOP_TEXT_DOMAIN ),
                'post_flow_download_final_pdf' => __( 'Baixar PDF final da personalizacao', EOP_TEXT_DOMAIN ),
                'post_flow_stat_confirmation' => __( 'Aguardando confirmacao do pedido', EOP_TEXT_DOMAIN ),
                'post_flow_stat_payment' => __( 'Pagamento', EOP_TEXT_DOMAIN ),
                'post_flow_stat_contract' => __( 'Contrato', EOP_TEXT_DOMAIN ),
                'post_flow_stat_upload' => __( 'Upload e personalizacao', EOP_TEXT_DOMAIN ),
                'post_flow_stat_stage' => __( 'Etapa atual', EOP_TEXT_DOMAIN ),
                'post_flow_stat_documents' => __( 'Dados do pedido', EOP_TEXT_DOMAIN ),
                'post_flow_stat_signature_documents' => __( 'Documentos para assinatura', EOP_TEXT_DOMAIN ),
                'post_flow_stat_attachment' => __( 'Anexo', EOP_TEXT_DOMAIN ),
                'post_flow_stat_final_pdf' => __( 'PDF final', EOP_TEXT_DOMAIN ),
                'post_flow_stat_products' => __( 'Produtos', EOP_TEXT_DOMAIN ),
                'post_flow_summary_ready' => __( 'Payload estruturado pronto para PDF, admin e integracoes futuras.', EOP_TEXT_DOMAIN ),
                'post_flow_completed_at' => __( 'Concluido em', EOP_TEXT_DOMAIN ),
                'post_flow_locked' => __( 'Bloqueado', EOP_TEXT_DOMAIN ),
                'post_flow_customized' => __( 'Personalizado', EOP_TEXT_DOMAIN ),
                'post_flow_stage_label' => __( 'Etapa do fluxo', EOP_TEXT_DOMAIN ),
                'post_flow_stage_apply' => __( 'Atualizar etapa', EOP_TEXT_DOMAIN ),
                'post_flow_stage_saving' => __( 'Salvando etapa...', EOP_TEXT_DOMAIN ),
                'post_flow_stage_hint_auto' => __( 'Use Automatico para voltar ao fluxo calculado pelo sistema.', EOP_TEXT_DOMAIN ),
                'post_flow_stage_hint_manual' => __( 'Etapa travada manualmente. Use Automatico para voltar ao fluxo calculado pelo sistema.', EOP_TEXT_DOMAIN ),
                'focus_mode_enter' => __( 'Ocultar interface do WordPress', EOP_TEXT_DOMAIN ),
                'focus_mode_exit' => __( 'Voltar a mostrar interface do WordPress', EOP_TEXT_DOMAIN ),
                'focus_mode_label_enter' => __( 'Modo foco', EOP_TEXT_DOMAIN ),
                'focus_mode_label_exit' => __( 'Sair do foco', EOP_TEXT_DOMAIN ),
                'lazy_loading_view' => __( 'Carregando tela...', EOP_TEXT_DOMAIN ),
                'performance_title' => __( 'Baseline de performance', EOP_TEXT_DOMAIN ),
                'performance_subtitle' => __( 'Mede a abertura da SPA, trocas de view, PDF e requests principais desta sessao.', EOP_TEXT_DOMAIN ),
                'performance_empty' => __( 'Nenhuma medicao registrada ainda nesta sessao.', EOP_TEXT_DOMAIN ),
                'performance_clear' => __( 'Limpar baseline da sessao', EOP_TEXT_DOMAIN ),
                'performance_col_flow' => __( 'Fluxo', EOP_TEXT_DOMAIN ),
                'performance_col_source' => __( 'Origem', EOP_TEXT_DOMAIN ),
                'performance_col_total' => __( 'Tempo total', EOP_TEXT_DOMAIN ),
                'performance_col_php' => __( 'PHP', EOP_TEXT_DOMAIN ),
                'performance_col_response' => __( 'Resposta', EOP_TEXT_DOMAIN ),
                'performance_col_memory' => __( 'Pico memoria', EOP_TEXT_DOMAIN ),
                'performance_summary_assets' => __( 'Assets atuais', EOP_TEXT_DOMAIN ),
                'performance_summary_queries' => __( 'Queries', EOP_TEXT_DOMAIN ),
                'performance_summary_navigation' => __( 'Navegacao inicial', EOP_TEXT_DOMAIN ),
            ),
        ) );
    }

    public static function enqueue_menu_flyout_assets() {
        if ( ! is_admin() ) {
            return;
        }

        $current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $pdf_children = array(
            array(
                'key'   => 'eop-view-pdf-display',
                'label' => __( 'Configuracoes de exibicao', EOP_TEXT_DOMAIN ),
                'icon'  => 'dashicons-admin-home',
                'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'display' ) ),
                'query' => array(
                    'page'    => 'eop-pedido-expresso',
                    'view'    => 'pdf',
                    'pdf_tab' => 'display',
                ),
            ),
        );

        $items = array(
            array(
                'key'   => 'eop-view-new-order',
                'label' => __( 'Novo pedido', EOP_TEXT_DOMAIN ),
                'icon'  => 'dashicons-cart',
                'url'   => self::get_view_url( 'new-order' ),
                'query' => array(
                    'page' => 'eop-pedido-expresso',
                    'view' => 'new-order',
                ),
            ),
            array(
                'key'   => 'eop-view-orders',
                'label' => __( 'Pedidos', EOP_TEXT_DOMAIN ),
                'icon'  => 'dashicons-list-view',
                'url'   => self::get_view_url( 'orders' ),
                'query' => array(
                    'page' => 'eop-pedido-expresso',
                    'view' => 'orders',
                ),
            ),
            array(
                'key'   => 'eop-view-pdf',
                'label' => __( 'PDF', EOP_TEXT_DOMAIN ),
                'icon'  => 'dashicons-media-document',
                'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'display' ) ),
                'query' => array(
                    'page'    => 'eop-pedido-expresso',
                    'view'    => 'pdf',
                    'pdf_tab' => 'display',
                ),
                'children' => $pdf_children,
            ),
        );

        if ( current_user_can( 'manage_options' ) ) {
            $general_children = array(
                array(
                    'key'   => 'eop-view-settings-store-info',
                    'label' => __( 'Informacoes sobre a loja', EOP_TEXT_DOMAIN ),
                    'icon'  => 'dashicons-store',
                    'url'   => self::get_view_url( 'settings-store-info' ),
                    'query' => array(
                        'page' => 'eop-pedido-expresso',
                        'view' => 'settings-store-info',
                    ),
                ),
                array(
                    'key'   => 'eop-view-settings-general-config',
					'label' => __( 'Configurações Gerais', EOP_TEXT_DOMAIN ),
                    'icon'  => 'dashicons-admin-settings',
                    'url'   => self::get_view_url( 'settings-general-config' ),
                    'query' => array(
                        'page' => 'eop-pedido-expresso',
                        'view' => 'settings-general-config',
                    ),
                ),
                array(
                    'key'   => 'eop-view-settings-proposal-link-style',
                    'label' => __( 'Visual da Proposta do Cliente', EOP_TEXT_DOMAIN ),
                    'icon'  => 'dashicons-format-image',
                    'url'   => self::get_view_url( 'settings-proposal-link-style' ),
                    'query' => array(
                        'page' => 'eop-pedido-expresso',
                        'view' => 'settings-proposal-link-style',
                    ),
                ),
                array(
                    'key'   => 'eop-view-settings-new-order-style',
                    'label' => __( 'Visual de Criar Pedido', EOP_TEXT_DOMAIN ),
                    'icon'  => 'dashicons-cart',
                    'url'   => self::get_view_url( 'settings-new-order-style' ),
                    'query' => array(
                        'page' => 'eop-pedido-expresso',
                        'view' => 'settings-new-order-style',
                    ),
                ),
                array(
                    'key'   => 'eop-view-settings-orders-list-style',
                    'label' => __( 'Visual da Listagem de Pedidos', EOP_TEXT_DOMAIN ),
                    'icon'  => 'dashicons-list-view',
                    'url'   => self::get_view_url( 'settings-orders-list-style' ),
                    'query' => array(
                        'page' => 'eop-pedido-expresso',
                        'view' => 'settings-orders-list-style',
                    ),
                ),
                array(
                    'key'   => 'eop-view-settings-texts',
                    'label' => __( 'Textos', EOP_TEXT_DOMAIN ),
                    'icon'  => 'dashicons-edit-large',
                    'url'   => self::get_view_url( 'settings-texts' ),
                    'query' => array(
                        'page' => 'eop-pedido-expresso',
                        'view' => 'settings-texts',
                    ),
                ),
            );

            $confirmation_children = array(
                array(
                    'key'   => 'eop-view-settings-confirmation-general',
					'label' => __( 'Configurações Gerais', EOP_TEXT_DOMAIN ),
                    'icon'  => 'dashicons-admin-settings',
                    'url'   => self::get_view_url( 'settings-confirmation-general' ),
                    'query' => array(
                        'page' => 'eop-pedido-expresso',
                        'view' => 'settings-confirmation-general',
                    ),
                ),
                array(
                    'key'   => 'eop-view-settings-confirmation-documents',
                    'label' => __( 'Documentos', EOP_TEXT_DOMAIN ),
                    'icon'  => 'dashicons-media-document',
                    'url'   => self::get_view_url( 'settings-confirmation-documents' ),
                    'query' => array(
                        'page' => 'eop-pedido-expresso',
                        'view' => 'settings-confirmation-documents',
                    ),
                ),
                array(
                    'key'   => 'eop-view-settings-confirmation-preview',
					'label' => __( 'Visual da página de confirmação', EOP_TEXT_DOMAIN ),
                    'icon'  => 'dashicons-visibility',
                    'url'   => self::get_view_url( 'settings-confirmation-preview' ),
                    'query' => array(
                        'page' => 'eop-pedido-expresso',
                        'view' => 'settings-confirmation-preview',
                    ),
                ),
                array(
                    'key'   => 'eop-view-settings-confirmation-upload-products-preview',
					'label' => __( 'Visual da pagina de upload e produtos', EOP_TEXT_DOMAIN ),
                    'icon'  => 'dashicons-upload',
                    'url'   => self::get_view_url( 'settings-confirmation-upload-products-preview' ),
                    'query' => array(
                        'page' => 'eop-pedido-expresso',
                        'view' => 'settings-confirmation-upload-products-preview',
                    ),
                ),
            );

            $pdf_children = array_merge(
                $pdf_children,
                array(
                    array(
                        'key'   => 'eop-view-pdf-order-settings',
                        'label' => __( 'Configuracoes do Pedido', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-media-text',
                        'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'order-settings' ) ),
                        'query' => array(
                            'page'    => 'eop-pedido-expresso',
                            'view'    => 'pdf',
                            'pdf_tab' => 'order-settings',
                        ),
                    ),
                    array(
                        'key'   => 'eop-view-pdf-order-columns',
                        'label' => __( 'Colunas do Pedido', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-editor-table',
                        'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'order-columns' ) ),
                        'query' => array(
                            'page'    => 'eop-pedido-expresso',
                            'view'    => 'pdf',
                            'pdf_tab' => 'order-columns',
                        ),
                    ),
                    array(
                        'key'   => 'eop-view-pdf-order-texts',
                        'label' => __( 'Textos do Pedido', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-edit-page',
                        'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'order-texts' ) ),
                        'query' => array(
                            'page'    => 'eop-pedido-expresso',
                            'view'    => 'pdf',
                            'pdf_tab' => 'order-texts',
                        ),
                    ),
                    array(
                        'key'   => 'eop-view-pdf-order-style',
                        'label' => __( 'Estilo do Pedido', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-art',
                        'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'order-style' ) ),
                        'query' => array(
                            'page'    => 'eop-pedido-expresso',
                            'view'    => 'pdf',
                            'pdf_tab' => 'order-style',
                        ),
                    ),
                    array(
                        'key'   => 'eop-view-pdf-proposal-settings',
                        'label' => __( 'Configuracoes da Proposta', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-media-default',
                        'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'proposal-settings' ) ),
                        'query' => array(
                            'page'    => 'eop-pedido-expresso',
                            'view'    => 'pdf',
                            'pdf_tab' => 'proposal-settings',
                        ),
                    ),
                    array(
                        'key'   => 'eop-view-pdf-proposal-columns',
                        'label' => __( 'Colunas da Proposta', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-editor-table',
                        'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'proposal-columns' ) ),
                        'query' => array(
                            'page'    => 'eop-pedido-expresso',
                            'view'    => 'pdf',
                            'pdf_tab' => 'proposal-columns',
                        ),
                    ),
                    array(
                        'key'   => 'eop-view-pdf-proposal-texts',
                        'label' => __( 'Textos da Proposta', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-edit-page',
                        'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'proposal-texts' ) ),
                        'query' => array(
                            'page'    => 'eop-pedido-expresso',
                            'view'    => 'pdf',
                            'pdf_tab' => 'proposal-texts',
                        ),
                    ),
                    array(
                        'key'   => 'eop-view-pdf-proposal-style',
                        'label' => __( 'Estilo da Proposta', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-art',
                        'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'proposal-style' ) ),
                        'query' => array(
                            'page'    => 'eop-pedido-expresso',
                            'view'    => 'pdf',
                            'pdf_tab' => 'proposal-style',
                        ),
                    ),
                    array(
                        'key'   => 'eop-view-pdf-edocuments',
                        'label' => __( 'Documentos eletronicos', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-media-spreadsheet',
                        'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'edocuments' ) ),
                        'query' => array(
                            'page'    => 'eop-pedido-expresso',
                            'view'    => 'pdf',
                            'pdf_tab' => 'edocuments',
                        ),
                    ),
                    array(
                        'key'   => 'eop-view-pdf-advanced',
                        'label' => __( 'Avancado', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-admin-tools',
                        'url'   => self::get_view_url( 'pdf', array( 'pdf_tab' => 'advanced' ) ),
                        'query' => array(
                            'page'    => 'eop-pedido-expresso',
                            'view'    => 'pdf',
                            'pdf_tab' => 'advanced',
                        ),
                    ),
                )
            );

            $items = array_merge(
                array(
                    array(
                        'key'   => 'eop-view-general',
                        'label' => __( 'Geral', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-admin-generic',
                        'url'   => self::get_view_url( 'settings-general-config' ),
                        'query' => array(
                            'page' => 'eop-pedido-expresso',
                            'view' => 'settings-general-config',
                        ),
                        'children' => $general_children,
                    ),
                    array(
                        'key'   => 'eop-view-confirmation-flow',
						'label' => __( 'Fluxo de Confirmação', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-yes-alt',
                        'url'   => self::get_view_url( 'settings-confirmation-general' ),
                        'query' => array(
                            'page' => 'eop-pedido-expresso',
                            'view' => 'settings-confirmation-general',
                        ),
                        'children' => $confirmation_children,
                    ),
                ),
                $items,
                array(
                    array(
                        'key'   => 'eop-view-documentation',
						'label' => __( 'Documentação', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-book-alt',
                        'url'   => self::get_view_url( 'documentation' ),
                        'query' => array(
                            'page' => 'eop-pedido-expresso',
                            'view' => 'documentation',
                        ),
                    ),
                    array(
                        'key'   => 'eop-view-license',
						'label' => __( 'Licença', EOP_TEXT_DOMAIN ),
                        'icon'  => 'dashicons-admin-network',
                        'url'   => self::get_view_url( 'license' ),
                        'query' => array(
                            'page' => 'eop-pedido-expresso',
                            'view' => 'license',
                        ),
                    ),
                )
            );

            foreach ( $items as $index => $item ) {
                if ( 'eop-view-pdf' === $item['key'] ) {
                    $items[ $index ]['children'] = $pdf_children;
                    break;
                }
            }
        }

        $config = array(
            'currentPage' => $current_page,
            'anchorPage'  => 'eop-pedido-expresso',
            'menuRoot'    => 'toplevel_page_aireset',
            'title'       => __( 'Pedido Expresso', EOP_TEXT_DOMAIN ),
            'items'       => $items,
        );

        $flyout_style_path  = EOP_PLUGIN_DIR . 'assets/css/admin-menu-flyout.css';
        $flyout_script_path = EOP_PLUGIN_DIR . 'assets/js/admin-menu-flyout.js';

        aireset_enqueue_shared_admin_menu_flyout_assets(
            array(
                'style_url'       => EOP_PLUGIN_URL . 'assets/css/admin-menu-flyout.css',
                'style_version'    => file_exists( $flyout_style_path ) ? (string) filemtime( $flyout_style_path ) : EOP_VERSION,
                'script_url'       => EOP_PLUGIN_URL . 'assets/js/admin-menu-flyout.js',
                'script_version'   => file_exists( $flyout_script_path ) ? (string) filemtime( $flyout_script_path ) : EOP_VERSION,
                'fontawesome_url'  => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css',
                'fontawesome_ver'  => '6.3.0',
                'inline_script'    => 'window.airesetAdminFlyouts=window.airesetAdminFlyouts||[];'
                    . 'window.airesetAdminFlyouts.push(' . wp_json_encode( $config ) . ');',
            )
        );
    }

    /**
     * Render the admin page.
     */
    public static function render_page() {
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
        }

        if ( self::is_preview_frame_request() ) {
            self::render_preview_frame_page();
            return;
        }

        include EOP_PLUGIN_DIR . 'templates/admin-page.php';
    }
}
