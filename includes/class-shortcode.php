<?php
defined( 'ABSPATH' ) || exit;

class EOP_Shortcode {

    public static function init() {
        add_shortcode( 'expresso_order', array( __CLASS__, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'dequeue_unrelated_frontend_assets' ), 999 );
        add_filter( 'login_redirect', array( __CLASS__, 'preserve_frontend_redirect' ), 20, 3 );
    }

    /**
     * Enqueue assets when shortcode is present.
     */
    public static function maybe_enqueue() {
        global $post;

        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'expresso_order' ) ) {
            return;
        }

        self::enqueue_frontend_assets();
    }

    public static function dequeue_unrelated_frontend_assets() {
        global $post;

        $has_order_shortcode = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'expresso_order' );

        if ( ! $has_order_shortcode && ! is_page( 'pedido-expresso' ) ) {
            return;
        }

        /*
         * Esta pagina e um PDV/proposta autocontido. Terceiros de marketing,
         * tracking e gateways nao sao necessarios para o fluxo de pedido e so
         * inflam o load publico (ver baseline em docs/ROADMAP.md). Removemos os
         * handles claramente nao relacionados, espelhando a limpeza do wp-admin
         * em EOP_Admin_Page::dequeue_unrelated_admin_assets().
         *
         * Importante: NAO removemos `elementor-frontend`/`swiper`/CSS do tema,
         * pois a pagina pode ser construida com Elementor. As listas sao
         * filtraveis para ajuste sem alterar codigo se o tema precisar de algo.
         */
        $style_handles = apply_filters(
            'eop_frontend_dequeue_styles',
            array(
                'elementor-pro-notes-frontend',
                'yay_smtp_global_style',
                'mercadopago_vars_css',
                'woocommerce-mercadopago-admin-notice-css',
                'brands-admin-styles',
            )
        );

        $script_handles = apply_filters(
            'eop_frontend_dequeue_scripts',
            array(
                'elementor-notes',
                'elementor-pro-notes',
                'elementor-pro-notes-app-initiator',
                'elementor-pro-app',
                'elementor-dev-tools',
                'woo-tracks',
                'wc-tracks',
                'jetpack-script-data',
                'yay_smtp_global',
                'woocommerce_mercadopago_admin_notice_js',
            )
        );

        foreach ( (array) $style_handles as $handle ) {
            wp_dequeue_style( $handle );
        }

        foreach ( (array) $script_handles as $handle ) {
            wp_dequeue_script( $handle );
        }
    }

    /**
     * Enqueue the same assets used by the public order shortcode.
     */
    public static function enqueue_frontend_assets() {
        $font_url = method_exists( 'EOP_Settings', 'get_font_stylesheet_url' ) ? EOP_Settings::get_font_stylesheet_url() : '';

        if ( $font_url ) {
            wp_enqueue_style( 'eop-selected-font', $font_url, array(), null );
        }

        wp_enqueue_style( 'eop-frontend', EOP_PLUGIN_URL . 'assets/css/frontend.css', array(), EOP_VERSION );

        // Frontend React (opt-in): usa o frontend.css para o visual e o bundle React no lugar do admin.js.
        if ( self::is_react_frontend() ) {
            self::enqueue_react_frontend();
            return;
        }

        if ( ! is_user_logged_in() || ! current_user_can( 'edit_shop_orders' ) ) {
            return;
        }

        if ( ! function_exists( 'WC' ) || ! WC() ) {
            return;
        }

        wp_enqueue_style( 'select2', WC()->plugin_url() . '/assets/css/select2.css', array(), WC_VERSION );
        wp_enqueue_script( 'select2', WC()->plugin_url() . '/assets/js/select2/select2.full.min.js', array( 'jquery' ), WC_VERSION, true );
        wp_enqueue_script( 'eop-frontend', EOP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'select2' ), EOP_VERSION, true );

        wp_localize_script( 'eop-frontend', 'eop_vars', array(
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'eop_nonce' ),
            'discount_mode' => EOP_Settings::get( 'discount_mode', 'both' ),
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
                'missing_customer' => '',
                'shipping_calculate' => EOP_Settings::get( 'new_order_shipping_button_label', __( 'Buscar opcoes de frete', EOP_TEXT_DOMAIN ) ),
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
                'error'            => __( 'Erro ao criar pedido. Tente novamente.', EOP_TEXT_DOMAIN ),
                'success'          => __( 'Pedido criado com sucesso!', EOP_TEXT_DOMAIN ),
                'submit_label'     => EOP_Settings::get( 'new_order_submit_label', __( 'Finalizar e Gerar PDF', EOP_TEXT_DOMAIN ) ),
                'orders_loading'   => __( 'Carregando pedidos...', EOP_TEXT_DOMAIN ),
                'orders_empty'     => __( 'Nenhum pedido encontrado para este filtro.', EOP_TEXT_DOMAIN ),
                'orders_error'     => __( 'Nao foi possivel carregar os pedidos agora.', EOP_TEXT_DOMAIN ),
                'orders_of'        => __( 'pedido(s) encontrado(s)', EOP_TEXT_DOMAIN ),
                'orders_previous'  => __( 'Anterior', EOP_TEXT_DOMAIN ),
                'orders_next'      => __( 'Proxima', EOP_TEXT_DOMAIN ),
                'orders_proposal'  => __( 'Proposta publica', EOP_TEXT_DOMAIN ),
                'orders_pdf'       => __( 'PDF', EOP_TEXT_DOMAIN ),
                'orders_public'    => __( 'Link do cliente', EOP_TEXT_DOMAIN ),
                'orders_edit'      => __( 'Editar aqui', EOP_TEXT_DOMAIN ),
                'orders_wc'        => __( 'WooCommerce', EOP_TEXT_DOMAIN ),
                'orders_created_by' => __( 'Vendedor', EOP_TEXT_DOMAIN ),
                'edit_title'       => __( 'Editando pedido', EOP_TEXT_DOMAIN ),
                'edit_submit'      => __( 'Salvar alteracoes', EOP_TEXT_DOMAIN ),
                'edit_cancel'      => __( 'Cancelar edicao', EOP_TEXT_DOMAIN ),
                'edit_loaded'      => __( 'Pedido carregado no painel para edicao.', EOP_TEXT_DOMAIN ),
                'edit_error'       => __( 'Nao foi possivel abrir este pedido para edicao.', EOP_TEXT_DOMAIN ),
            ),
        ) );
    }

    /**
     * Render the shortcode.
     */
    public static function render( $atts ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return '<p>' . esc_html__( 'WooCommerce e necessario.', EOP_TEXT_DOMAIN ) . '</p>';
        }

        // Not logged in — show login form.
        if ( ! is_user_logged_in() ) {
            return self::render_login_form();
        }

        // Logged in but no permission.
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            return '<div class="eop-notice eop-notice-error">' . esc_html__( 'Voce nao tem permissao para acessar esta pagina.', EOP_TEXT_DOMAIN ) . '</div>';
        }

        // Frontend React (opt-in por ?eop_react=1). O jQuery segue como padrao.
        if ( self::is_react_frontend() ) {
            $settings = EOP_Settings::get_all();
            $font_css = method_exists( 'EOP_Settings', 'get_font_css_family' ) ? EOP_Settings::get_font_css_family( $settings['font_family'] ?? '' ) : "'Segoe UI', sans-serif";

            ob_start();
            ?>
            <style>
                .eop-pdv {
                    --eop-primary: <?php echo esc_attr( $settings['primary_color'] ?? '#00034b' ); ?>;
                    --eop-surface: <?php echo esc_attr( $settings['surface_color'] ?? '#ffffff' ); ?>;
                    --eop-border: <?php echo esc_attr( $settings['border_color'] ?? '#dbe3f0' ); ?>;
                    --eop-radius: <?php echo esc_attr( absint( $settings['border_radius'] ?? 18 ) ); ?>px;
                    --eop-font-family: <?php echo esc_attr( $font_css ); ?>;
                    font-family: <?php echo esc_attr( $font_css ); ?>;
                }
            </style>
            <script>
                window.eopAdminSpaConfig = <?php echo wp_json_encode(
                    array(
                        'rest_root'    => esc_url_raw( rest_url( 'aireset-expresso-order/v1/admin' ) ),
                        'rest_nonce'   => wp_create_nonce( 'wp_rest' ),
                        'initial_view' => 'orders',
                    )
                ); ?>;
            </script>
            <div id="eop-frontend-app" class="eop-frontend-app"></div>
            <?php
            return ob_get_clean();
        }

        ob_start();
        include EOP_PLUGIN_DIR . 'templates/shortcode-page.php';
        return ob_get_clean();
    }

    /**
     * Frontend React habilitado? Opt-in por `?eop_react=1` para validacao, com
     * o bundle compilado disponivel. Mantemos o jQuery como padrao ate o cutover.
     */
    public static function is_react_frontend() {
        if ( ! is_user_logged_in() || ! current_user_can( 'edit_shop_orders' ) ) {
            return false;
        }

        if ( ! isset( $_GET['eop_react'] ) || '1' !== sanitize_text_field( wp_unslash( $_GET['eop_react'] ) ) ) {
            return false;
        }

        return ! empty( self::get_frontend_dist_entry() );
    }

    private static function get_frontend_dist_entry() {
        $path = EOP_PLUGIN_DIR . 'assets/admin-spa/dist/.vite/manifest.json';

        if ( ! file_exists( $path ) ) {
            return array();
        }

        $manifest = json_decode( (string) file_get_contents( $path ), true );

        if ( ! is_array( $manifest ) ) {
            return array();
        }

        foreach ( array( 'assets/admin-spa/src/frontend.tsx', 'frontend.tsx', 'frontend' ) as $key ) {
            if ( isset( $manifest[ $key ] ) && is_array( $manifest[ $key ] ) ) {
                return $manifest[ $key ];
            }
        }

        return array();
    }

    private static function enqueue_react_frontend() {
        $entry = self::get_frontend_dist_entry();

        if ( empty( $entry['file'] ) ) {
            return;
        }

        $base      = trailingslashit( EOP_PLUGIN_URL . 'assets/admin-spa/dist' );
        $file_path = EOP_PLUGIN_DIR . 'assets/admin-spa/dist/' . ltrim( (string) $entry['file'], '/\\' );

        // Select2 (do WooCommerce) para a busca de produto no frontend React.
        $script_deps = array( 'jquery' );

        if ( function_exists( 'WC' ) && WC() ) {
            $wc_version = defined( 'WC_VERSION' ) ? WC_VERSION : EOP_VERSION;
            wp_enqueue_style( 'select2', WC()->plugin_url() . '/assets/css/select2.css', array(), $wc_version );
            wp_enqueue_script( 'select2', WC()->plugin_url() . '/assets/js/select2/select2.full.min.js', array( 'jquery' ), $wc_version, true );
            $script_deps[] = 'select2';
        }

        add_filter( 'script_loader_tag', array( __CLASS__, 'filter_frontend_module_tag' ), 10, 3 );

        wp_enqueue_script(
            'eop-frontend-react',
            $base . ltrim( (string) $entry['file'], '/\\' ),
            $script_deps,
            file_exists( $file_path ) ? (string) filemtime( $file_path ) : EOP_VERSION,
            true
        );

        wp_add_inline_script(
            'eop-frontend-react',
            'window.eopAdminSpaConfig=' . wp_json_encode(
                array(
                    'rest_root'    => esc_url_raw( rest_url( 'aireset-expresso-order/v1/admin' ) ),
                    'rest_nonce'   => wp_create_nonce( 'wp_rest' ),
                    'initial_view' => 'orders',
                )
            ) . ';',
            'before'
        );

        if ( ! empty( $entry['css'] ) && is_array( $entry['css'] ) ) {
            foreach ( $entry['css'] as $index => $css_file ) {
                $css_file = ltrim( (string) $css_file, '/\\' );
                wp_enqueue_style(
                    'eop-frontend-react-' . $index,
                    $base . $css_file,
                    array(),
                    EOP_VERSION
                );
            }
        }
    }

    public static function filter_frontend_module_tag( $tag, $handle, $src ) {
        if ( 'eop-frontend-react' !== $handle ) {
            return $tag;
        }

        return '<script type="module" src="' . esc_url( $src ) . '"></script>';
    }

    public static function preserve_frontend_redirect( $redirect_to, $requested_redirect, $user ) {
        if ( empty( $requested_redirect ) ) {
            return $redirect_to;
        }

        $requested_host = wp_parse_url( $requested_redirect, PHP_URL_HOST );
        $site_host      = wp_parse_url( home_url(), PHP_URL_HOST );

        if ( $requested_host && $site_host && $requested_host === $site_host ) {
            return $requested_redirect;
        }

        return $redirect_to;
    }

    /**
     * Simple login form for the shortcode page.
     */
    private static function render_login_form() {
        $font_css = method_exists( 'EOP_Settings', 'get_font_css_family' ) ? EOP_Settings::get_font_css_family() : "'Segoe UI', sans-serif";
        $error    = '';
        $redirect = self::get_current_url();

        if ( isset( $_GET['login'] ) && 'failed' === sanitize_key( wp_unslash( $_GET['login'] ) ) ) {
            $error = __( 'Usuario ou senha invalidos.', EOP_TEXT_DOMAIN );
        }

        ob_start();
        ?>
        <style>
            .eop-login-wrap {
                --eop-font-family: <?php echo esc_attr( $font_css ); ?>;
                font-family: <?php echo esc_attr( $font_css ); ?>;
            }
        </style>
        <div class="eop-login-wrap">
            <div class="eop-login-card">
                <div class="eop-login-eyebrow"><?php esc_html_e( 'Area do vendedor', EOP_TEXT_DOMAIN ); ?></div>
                <h2><?php esc_html_e( 'Pedido Expresso - Login', EOP_TEXT_DOMAIN ); ?></h2>
                <p class="eop-login-intro"><?php esc_html_e( 'Entre para montar pedidos rapidamente, gerar proposta e compartilhar com o cliente em poucos cliques.', EOP_TEXT_DOMAIN ); ?></p>
                <?php if ( $error ) : ?>
                    <div class="eop-notice eop-notice-error"><?php echo esc_html( $error ); ?></div>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url( wp_login_url( $redirect ) ); ?>">
                    <div class="eop-login-field">
                        <label for="user_login"><?php esc_html_e( 'Usuario', EOP_TEXT_DOMAIN ); ?></label>
                        <input type="text" id="user_login" name="log" required autocomplete="username" />
                    </div>
                    <div class="eop-login-field">
                        <label for="user_pass"><?php esc_html_e( 'Senha', EOP_TEXT_DOMAIN ); ?></label>
                        <input type="password" id="user_pass" name="pwd" required autocomplete="current-password" />
                    </div>
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect ); ?>" />
                    <input type="hidden" name="rememberme" value="forever" />
                    <button type="submit" class="eop-btn eop-btn-primary"><?php esc_html_e( 'Entrar', EOP_TEXT_DOMAIN ); ?></button>
                </form>
            </div>
            <div class="eop-login-aside">
                <div class="eop-login-aside__badge"><?php esc_html_e( 'Fluxo rapido', EOP_TEXT_DOMAIN ); ?></div>
                <h3><?php esc_html_e( 'Um mini PDV para a equipe comercial', EOP_TEXT_DOMAIN ); ?></h3>
                <p><?php esc_html_e( 'Busque cliente, adicione itens, ajuste frete ou desconto e entregue uma proposta profissional sem navegar pelo admin inteiro do WooCommerce.', EOP_TEXT_DOMAIN ); ?></p>
                <ul class="eop-login-aside__list">
                    <li><?php esc_html_e( 'Busca por CPF ou CNPJ', EOP_TEXT_DOMAIN ); ?></li>
                    <li><?php esc_html_e( 'Montagem rapida de itens', EOP_TEXT_DOMAIN ); ?></li>
                    <li><?php esc_html_e( 'Link publico para o cliente', EOP_TEXT_DOMAIN ); ?></li>
                </ul>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function get_current_url() {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
        $request_uri = esc_url_raw( $request_uri );

        return home_url( $request_uri );
    }
}
