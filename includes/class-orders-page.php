<?php
defined( 'ABSPATH' ) || exit;

class EOP_Orders_Page {

    private static $page_hook = '';

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_submenu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'wp_ajax_eop_list_orders', array( __CLASS__, 'ajax_list_orders' ) );
        add_action( 'wp_ajax_eop_load_order', array( __CLASS__, 'ajax_load_order' ) );
        add_action( 'wp_ajax_eop_update_order', array( __CLASS__, 'ajax_update_order' ) );
    }

    public static function register_submenu() {
        // self::$page_hook = add_submenu_page(
        //     'aireset',
        //     __( 'Pedidos', EOP_TEXT_DOMAIN ),
        //     __( 'Pedidos', EOP_TEXT_DOMAIN ),
        //     'edit_shop_orders',
        //     'eop-pedidos',
        //     array( __CLASS__, 'render_page' )
        // );
    }

    public static function enqueue_assets( $hook ) {
        if ( self::$page_hook !== $hook ) {
            return;
        }

        if ( ! function_exists( 'WC' ) || ! WC() ) {
            return;
        }

        $font_url = EOP_Settings::get_font_stylesheet_url();
        if ( $font_url ) {
            wp_enqueue_style( 'eop-orders-selected-font', $font_url, array(), null );
        }

        $wc_version = defined( 'WC_VERSION' ) ? WC_VERSION : EOP_VERSION;

        wp_enqueue_style( 'select2', WC()->plugin_url() . '/assets/css/select2.css', array(), $wc_version );
        wp_enqueue_script( 'select2', WC()->plugin_url() . '/assets/js/select2/select2.full.min.js', array( 'jquery' ), $wc_version, true );

        wp_enqueue_style( 'eop-admin', EOP_PLUGIN_URL . 'assets/css/admin.css', array( 'select2' ), EOP_VERSION );
        wp_enqueue_style( 'eop-orders', EOP_PLUGIN_URL . 'assets/css/orders.css', array( 'eop-admin' ), EOP_VERSION );

        wp_enqueue_script( 'eop-orders', EOP_PLUGIN_URL . 'assets/js/orders.js', array( 'jquery', 'select2' ), EOP_VERSION, true );

        wp_localize_script( 'eop-orders', 'eop_orders_vars', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'eop_nonce' ),
            'edit_url' => admin_url( 'admin.php?page=eop-pedidos&action=edit&order_id=' ),
            'discount_mode' => EOP_Settings::get( 'discount_mode', 'both' ),
            'i18n'     => array(
                'search_product'    => __( 'Buscar produto por nome ou SKU...', EOP_TEXT_DOMAIN ),
                'label_price'       => __( 'Preco', EOP_TEXT_DOMAIN ),
                'label_quantity'    => __( 'Qtd', EOP_TEXT_DOMAIN ),
                'label_discount'    => __( 'Desconto', EOP_TEXT_DOMAIN ),
                'label_discounted_unit_price' => __( 'Valor c/ desconto', EOP_TEXT_DOMAIN ),
                'label_subtotal'    => __( 'Subtotal', EOP_TEXT_DOMAIN ),
                'default_discount_placeholder_percent' => __( '10', EOP_TEXT_DOMAIN ),
                'default_discount_placeholder_fixed'   => __( '10,00', EOP_TEXT_DOMAIN ),
                'default_discount_placeholder_both'    => __( '10 ou 10%', EOP_TEXT_DOMAIN ),
                'default_discount_help_percent'        => __( 'Informe somente porcentagem (%).', EOP_TEXT_DOMAIN ),
                'default_discount_help_fixed'          => __( 'Informe somente valor fixo (R$).', EOP_TEXT_DOMAIN ),
                'default_discount_help_both'           => __( 'Aceita valor fixo ou porcentagem.', EOP_TEXT_DOMAIN ),
                'processing'        => __( 'Salvando...', EOP_TEXT_DOMAIN ),
                'save_label'        => __( 'Salvar alteracoes', EOP_TEXT_DOMAIN ),
                'error'             => __( 'Erro ao salvar. Tente novamente.', EOP_TEXT_DOMAIN ),
                'saved'             => __( 'Pedido atualizado com sucesso!', EOP_TEXT_DOMAIN ),
                'confirm_remove'    => __( 'Remover este item?', EOP_TEXT_DOMAIN ),
                'no_items'          => __( 'Nenhum produto adicionado.', EOP_TEXT_DOMAIN ),
                'missing_products'  => __( 'Adicione ao menos um produto.', EOP_TEXT_DOMAIN ),
                'shipping_calculate'       => __( 'Buscar opcoes de frete', EOP_TEXT_DOMAIN ),
                'shipping_loading'         => __( 'Calculando frete...', EOP_TEXT_DOMAIN ),
                'shipping_missing'         => __( 'Preencha CEP, endereco, cidade e numero para calcular o frete.', EOP_TEXT_DOMAIN ),
                'shipping_summary_default' => __( 'Clique para calcular com o endereco do cliente.', EOP_TEXT_DOMAIN ),
                'shipping_summary_pending' => __( 'Preencha o endereco e escolha uma opcao de frete.', EOP_TEXT_DOMAIN ),
                'shipping_summary_ready'   => __( 'Escolha a opcao de frete que melhor atende o cliente.', EOP_TEXT_DOMAIN ),
                'shipping_panel_hint'      => __( 'Comece pelo CEP. O sistema tenta preencher o endereco automaticamente.', EOP_TEXT_DOMAIN ),
                'shipping_postcode_loading'    => __( 'Buscando endereco pelo CEP...', EOP_TEXT_DOMAIN ),
                'shipping_postcode_found'      => __( 'Endereco encontrado. Confira o numero e o complemento.', EOP_TEXT_DOMAIN ),
                'shipping_postcode_not_found'  => __( 'Nao encontramos esse CEP. Preencha o endereco manualmente.', EOP_TEXT_DOMAIN ),
                'shipping_postcode_invalid'    => __( 'Digite um CEP valido com 8 numeros.', EOP_TEXT_DOMAIN ),
                'shipping_postcode_error'      => __( 'Nao foi possivel buscar o CEP agora. Continue manualmente.', EOP_TEXT_DOMAIN ),
                'shipping_rates_found'         => __( 'Opcoes encontradas. Escolha a melhor para o cliente.', EOP_TEXT_DOMAIN ),
            ),
        ) );
    }

    public static function render_page() {
        if ( class_exists( 'EOP_Admin_Page' ) ) {
            $args = array();

            if ( isset( $_GET['order_id'] ) ) {
                $args['order_id'] = absint( $_GET['order_id'] );
            }

            if ( isset( $_GET['action'] ) && 'edit' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
                $args['action'] = 'edit';
            }

            wp_safe_redirect( EOP_Admin_Page::get_view_url( 'orders', $args ) );
            exit;
        }

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
        }

        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';

        if ( 'edit' === $action && ! empty( $_GET['order_id'] ) ) {
            echo '<div class="wrap"><div class="notice notice-warning"><p>' . esc_html__( 'A edicao agora acontece somente no painel SPA da equipe comercial. No admin, use esta tela apenas para acompanhamento.', EOP_TEXT_DOMAIN ) . '</p></div></div>';
            include EOP_PLUGIN_DIR . 'templates/orders-list.php';
            return;
        }

        include EOP_PLUGIN_DIR . 'templates/orders-list.php';
    }

    /**
     * Get orders created by the plugin.
     */
    public static function get_orders( $args = array() ) {
        $defaults = array(
            'limit'      => 20,
            'paged'      => 1,
            'status'     => 'any',
            'search'     => '',
            'post_confirmation_flow' => 'any',
            'orderby'    => 'date',
            'order'      => 'DESC',
        );

        $args = wp_parse_args( $args, $defaults );

        $query_args = array(
            'limit'      => $args['limit'],
            'paged'      => $args['paged'],
            'status'     => $args['status'],
            'orderby'    => $args['orderby'],
            'order'      => $args['order'],
            'created_via' => 'aireset-expresso-order',
            'paginate'   => true,
        );

        if ( ! empty( $args['search'] ) ) {
            $search = sanitize_text_field( $args['search'] );

            $matched_ids = function_exists( 'wc_order_search' ) ? wc_order_search( $search ) : array();

            if ( is_numeric( $search ) ) {
                $matched_ids[] = absint( $search );
            }

            $matched_ids = array_values( array_filter( array_unique( array_map( 'absint', $matched_ids ) ) ) );

            if ( ! empty( $matched_ids ) ) {
                $query_args['post__in'] = $matched_ids;
            } else {
                $query_args['post__in'] = array( 0 );
            }
        }

        $flow_filter = self::normalize_post_confirmation_flow_filter( $args['post_confirmation_flow'] ?? 'any' );
        $meta_query  = array();

        if ( EOP_Role::is_vendedor() ) {
            $meta_query[] = array(
                'key'     => '_eop_created_by',
                'value'   => get_current_user_id(),
                'compare' => '=',
                'type'    => 'NUMERIC',
            );
        }

        $flow_meta_query = self::get_post_confirmation_flow_meta_query( $flow_filter );

        if ( false === $flow_meta_query ) {
            return self::get_empty_orders_result();
        }

        if ( ! empty( $flow_meta_query ) ) {
            $meta_query = array_merge( $meta_query, $flow_meta_query );
        }

        if ( ! empty( $meta_query ) ) {
            $query_args['meta_query'] = count( $meta_query ) > 1
                ? array_merge( array( 'relation' => 'AND' ), $meta_query )
                : $meta_query;
        }

        return wc_get_orders( $query_args );
    }

    private static function get_empty_orders_result() {
        return (object) array(
            'orders'        => array(),
            'total'         => 0,
            'max_num_pages' => 1,
        );
    }

    public static function ajax_list_orders() {
        check_ajax_referer( 'eop_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sem permissao.', EOP_TEXT_DOMAIN ) ) );
        }

        $paged  = max( 1, absint( $_POST['paged'] ?? 1 ) );
        $search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'any';
        $post_confirmation_flow = isset( $_POST['post_confirmation_flow'] ) ? self::normalize_post_confirmation_flow_filter( wp_unslash( $_POST['post_confirmation_flow'] ) ) : 'any';

        $result = self::get_orders( array(
            'limit'  => 12,
            'paged'  => $paged,
            'status' => $status,
            'search' => $search,
            'post_confirmation_flow' => $post_confirmation_flow,
        ) );

        $orders = array_map( array( __CLASS__, 'prepare_order_list_item' ), $result->orders );

        wp_send_json_success( array(
            'orders'      => $orders,
            'pagination'  => array(
                'page'        => $paged,
                'total_pages' => max( 1, (int) $result->max_num_pages ),
                'total_items' => (int) $result->total,
            ),
            'viewer'      => array(
                'is_admin' => ! EOP_Role::is_vendedor(),
            ),
            '_performance' => class_exists( 'EOP_Performance_Audit' )
                ? EOP_Performance_Audit::get_request_metrics(
                    'orders_list',
                    array(
                        'page' => $paged,
                    )
                )
                : array(),
        ) );
    }

    /**
     * AJAX: Load order data for editing.
     */
    public static function ajax_load_order() {
        check_ajax_referer( 'eop_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sem permissao.', EOP_TEXT_DOMAIN ) ) );
        }

        $order_id = absint( $_POST['order_id'] ?? 0 );
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            wp_send_json_error( array( 'message' => __( 'Pedido nao encontrado.', EOP_TEXT_DOMAIN ) ) );
        }

        if ( ! self::current_user_can_access_order( $order ) ) {
            wp_send_json_error( array( 'message' => __( 'Voce nao pode acessar este pedido.', EOP_TEXT_DOMAIN ) ) );
        }

        $items         = array();
        $discount_mode = EOP_Settings::get( 'discount_mode', 'both' );
        foreach ( $order->get_items() as $item ) {
            if ( ! $item instanceof WC_Order_Item_Product ) {
                continue;
            }

            $product = $item->get_product();
            $subtotal = (float) $item->get_subtotal();
            $total    = (float) $item->get_total();
            $disc     = $subtotal - $total;
            $discount_data = self::resolve_loaded_discount_data(
                $disc,
                $subtotal,
                $discount_mode,
                $item->get_meta( '_eop_discount_type', true ),
                $item->get_meta( '_eop_discount_value', true )
            );

            $image_url = '';
            if ( $product ) {
                $img_id = $product->get_image_id();
                if ( $img_id ) {
                    $src = wp_get_attachment_image_url( $img_id, 'thumbnail' );
                    if ( $src ) {
                        $image_url = $src;
                    }
                }
                if ( ! $image_url ) {
                    $image_url = wc_placeholder_img_src( 'thumbnail' );
                }
            }

            $items[] = array(
                'product_id'     => $item->get_product_id(),
                'name'           => $item->get_name(),
                'sku'            => $product ? $product->get_sku() : '',
                'price'          => $item->get_quantity() > 0 ? $subtotal / $item->get_quantity() : 0,
                'quantity'       => $item->get_quantity(),
                'discount_type'  => $discount_data['type'],
                'discount_value' => $discount_data['value'],
                'image'          => $image_url,
            );
        }

        $discount_fee = 0;
        foreach ( $order->get_fees() as $fee ) {
            if ( (float) $fee->get_total() < 0 ) {
                $discount_fee += abs( (float) $fee->get_total() );
            }
        }

        $shipping_total = (float) $order->get_shipping_total();
        $shipping_method = '';
        foreach ( $order->get_shipping_methods() as $method ) {
            $shipping_method = $method->get_method_title();
            break;
        }

        $document              = $order->get_meta( '_billing_cpf' ) ?: $order->get_meta( '_billing_cnpj' );
        $general_discount_data = self::resolve_loaded_discount_data(
            $discount_fee,
            max( 0, (float) $order->get_subtotal() ),
            $discount_mode,
            $order->get_meta( '_eop_discount_type', true ),
            $order->get_meta( '_eop_discount_value', true )
        );

        wp_send_json_success( array(
            'order_id' => $order->get_id(),
            'status'   => $order->get_status(),
            'customer' => array(
                'user_id'  => $order->get_customer_id(),
                'name'     => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
                'email'    => $order->get_billing_email(),
                'phone'    => $order->get_billing_phone(),
                'document' => $document,
            ),
            'items'           => $items,
            'shipping'        => $shipping_total,
            'shipping_method' => $shipping_method,
            'shipping_address' => array(
                'postcode'     => $order->get_shipping_postcode(),
                'state'        => $order->get_shipping_state(),
                'city'         => $order->get_shipping_city(),
                'address'      => $order->get_shipping_address_1(),
                'number'       => $order->get_meta( '_shipping_number' ),
                'neighborhood' => $order->get_meta( '_shipping_neighborhood' ),
                'address_2'    => $order->get_shipping_address_2(),
            ),
            'discount'      => $general_discount_data['value'],
            'discount_type' => $general_discount_data['type'],
            'notes'         => $order->get_customer_note(),
            'post_confirmation_flow' => class_exists( 'EOP_Post_Confirmation_Flow' ) ? EOP_Post_Confirmation_Flow::get_export_data( $order, 'admin' ) : array(),
            '_performance'     => class_exists( 'EOP_Performance_Audit' )
                ? EOP_Performance_Audit::get_request_metrics(
                    'order_load',
                    array(
                        'order_id' => $order->get_id(),
                    )
                )
                : array(),
        ) );
    }

    /**
     * AJAX: Update existing order.
     */
    public static function ajax_update_order() {
        check_ajax_referer( 'eop_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sem permissao.', EOP_TEXT_DOMAIN ) ) );
        }

        $raw  = wp_unslash( $_POST['order_data'] ?? '' );
        $data = json_decode( $raw, true );

        if ( ! is_array( $data ) || empty( $data['order_id'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Dados invalidos.', EOP_TEXT_DOMAIN ) ) );
        }

        $order_id = absint( $data['order_id'] );
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            wp_send_json_error( array( 'message' => __( 'Pedido nao encontrado.', EOP_TEXT_DOMAIN ) ) );
        }

        if ( ! self::current_user_can_access_order( $order ) ) {
            wp_send_json_error( array( 'message' => __( 'Voce nao pode editar este pedido.', EOP_TEXT_DOMAIN ) ) );
        }

        // --- Update customer ---
        $customer = $data['customer'] ?? array();
        $name     = sanitize_text_field( $customer['name'] ?? '' );
        $email    = sanitize_email( $customer['email'] ?? '' );
        $phone    = sanitize_text_field( $customer['phone'] ?? '' );
        $phone    = preg_replace( '/[^0-9+\(\)\-\s]/', '', $phone );
        $document = preg_replace( '/[^0-9]/', '', sanitize_text_field( $customer['document'] ?? '' ) );

        if ( $name ) {
            $name_parts = explode( ' ', $name, 2 );
            $order->set_billing_first_name( $name_parts[0] );
            $order->set_billing_last_name( $name_parts[1] ?? '' );
        }
        if ( $email ) {
            $order->set_billing_email( $email );
        }
        if ( $phone ) {
            $order->set_billing_phone( $phone );
        }
        if ( $document ) {
            $meta_key = strlen( $document ) <= 11 ? '_billing_cpf' : '_billing_cnpj';
            $order->update_meta_data( $meta_key, $document );
            $order->update_meta_data( '_billing_persontype', strlen( $document ) <= 11 ? '1' : '2' );
        }

        // --- Update shipping address ---
        $shipping_address = isset( $data['shipping_address'] ) && is_array( $data['shipping_address'] ) ? $data['shipping_address'] : array();
        if ( ! empty( $shipping_address ) ) {
            $shipping_line_2_parts = array_filter( array(
                sanitize_text_field( $shipping_address['number'] ?? '' ),
                sanitize_text_field( $shipping_address['neighborhood'] ?? '' ),
                sanitize_text_field( $shipping_address['address_2'] ?? '' ),
            ) );

            $order->set_shipping_first_name( $order->get_billing_first_name() );
            $order->set_shipping_last_name( $order->get_billing_last_name() );
            $order->set_shipping_country( 'BR' );
            $order->set_shipping_state( sanitize_text_field( $shipping_address['state'] ?? '' ) );
            $order->set_shipping_postcode( preg_replace( '/[^0-9]/', '', (string) ( $shipping_address['postcode'] ?? '' ) ) );
            $order->set_shipping_city( sanitize_text_field( $shipping_address['city'] ?? '' ) );
            $order->set_shipping_address_1( sanitize_text_field( $shipping_address['address'] ?? '' ) );
            $order->set_shipping_address_2( implode( ' | ', $shipping_line_2_parts ) );
            $order->update_meta_data( '_shipping_number', sanitize_text_field( $shipping_address['number'] ?? '' ) );
            $order->update_meta_data( '_shipping_neighborhood', sanitize_text_field( $shipping_address['neighborhood'] ?? '' ) );
        }

        // --- Remove existing line items, fees, shipping ---
        foreach ( $order->get_items() as $item_id => $item ) {
            $order->remove_item( $item_id );
        }
        foreach ( $order->get_fees() as $item_id => $fee ) {
            $order->remove_item( $item_id );
        }
        foreach ( $order->get_shipping_methods() as $item_id => $method ) {
            $order->remove_item( $item_id );
        }

        // --- Re-add line items ---
        $items = $data['items'] ?? array();
        $items_subtotal = 0;
        $valid_items    = 0;

        foreach ( $items as $item ) {
            $product_id = absint( $item['product_id'] ?? 0 );
            $quantity   = max( 1, absint( $item['quantity'] ?? 1 ) );
            $product    = wc_get_product( $product_id );

            if ( ! $product || ! $product->exists() ) {
                continue;
            }

            $line_item_id = $order->add_product( $product, $quantity );
            $valid_items++;

            $item_disc_type  = in_array( $item['discount_type'] ?? 'fixed', array( 'fixed', 'percent' ), true ) ? $item['discount_type'] : 'fixed';
            $item_disc_value = floatval( $item['discount_value'] ?? 0 );
            $item_disc_value = max( 0, $item_disc_value );

            $line_total = (float) $product->get_price() * $quantity;

            if ( $item_disc_value > 0 && $line_item_id ) {
                if ( 'percent' === $item_disc_type ) {
                    $disc_amount = min( $line_total, $line_total * $item_disc_value / 100 );
                } else {
                    $disc_amount = min( $line_total, $item_disc_value );
                }

                if ( $disc_amount > 0 ) {
                    $line_item = $order->get_item( $line_item_id );
                    if ( $line_item instanceof WC_Order_Item_Product ) {
                        $line_item->set_subtotal( $line_total );
                        $line_item->set_total( $line_total - $disc_amount );
                        $line_item->update_meta_data( '_eop_discount_type', $item_disc_type );
                        $line_item->update_meta_data( '_eop_discount_value', $item_disc_value );
                        $line_item->save();
                    }
                    $items_subtotal += ( $line_total - $disc_amount );
                } else {
                    $items_subtotal += $line_total;
                }
            } else {
                $items_subtotal += $line_total;
            }
        }

        if ( 0 === $valid_items ) {
            wp_send_json_error( array( 'message' => __( 'Adicione ao menos um produto valido.', EOP_TEXT_DOMAIN ) ) );
        }

        // --- Re-add shipping ---
        $shipping_value = floatval( $data['shipping'] ?? 0 );
        $shipping_value = max( 0, $shipping_value );
        if ( $shipping_value > 0 ) {
            $shipping_item = new WC_Order_Item_Shipping();
            $shipping_item->set_method_title( sanitize_text_field( $data['shipping_method'] ?? __( 'Frete', EOP_TEXT_DOMAIN ) ) );
            $shipping_item->set_method_id( 'flat_rate' );
            $shipping_item->set_total( $shipping_value );
            $order->add_item( $shipping_item );
        }

        // --- Re-add discount ---
        $discount_value = floatval( $data['discount'] ?? 0 );
        $discount_value = max( 0, $discount_value );
        $discount_type  = in_array( $data['discount_type'] ?? 'fixed', array( 'fixed', 'percent' ), true ) ? $data['discount_type'] : 'fixed';

        $order->update_meta_data( '_eop_discount_type', $discount_type );
        $order->update_meta_data( '_eop_discount_value', $discount_value );

        if ( $discount_value > 0 ) {
            if ( 'percent' === $discount_type ) {
                $discount_abs = $items_subtotal * $discount_value / 100;
            } else {
                $discount_abs = $discount_value;
            }
            $discount_abs = max( 0, $discount_abs );

            if ( $discount_abs > 0 ) {
                $fee = new WC_Order_Item_Fee();
                $fee->set_name( __( 'Desconto manual', EOP_TEXT_DOMAIN ) );
                $fee->set_amount( -1 * $discount_abs );
                $fee->set_total( -1 * $discount_abs );
                $order->add_item( $fee );
            }
        }

        // --- Update status ---
        $status  = sanitize_text_field( $data['status'] ?? '' );
        $allowed = array( 'completed', 'pending', 'processing', 'on-hold', 'cancelled' );
        if ( in_array( $status, $allowed, true ) ) {
            $order->set_status( $status );
        }

        // --- Notes ---
        $note = sanitize_textarea_field( $data['note'] ?? '' );
        if ( $note ) {
            $order->add_order_note( $note );
        }

        $order = EOP_Order_Creator::reload_and_recalculate( $order );
        $order->add_order_note( __( 'Pedido atualizado via Expresso Order.', EOP_TEXT_DOMAIN ) );
        $order->save();

        wp_send_json_success( array(
            'order_id'  => $order->get_id(),
            'order_url' => admin_url( 'admin.php?page=eop-pedidos&action=edit&order_id=' . $order->get_id() ),
            'message'   => __( 'Pedido atualizado com sucesso!', EOP_TEXT_DOMAIN ),
        ) );
    }

    public static function current_user_can_access_order( $order ) {
        if ( ! $order instanceof WC_Order ) {
            return false;
        }

        if ( ! EOP_Role::is_vendedor() ) {
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

    public static function normalize_post_confirmation_flow_filter( $value ) {
        $value   = sanitize_key( (string) $value );
        $allowed = array( 'any', 'active', 'pending', 'completed' );

        return in_array( $value, $allowed, true ) ? $value : 'any';
    }

    private static function get_post_confirmation_flow_meta_query( $flow_filter ) {
        $flow_filter = self::normalize_post_confirmation_flow_filter( $flow_filter );

        if ( 'any' === $flow_filter ) {
            return array();
        }

        if ( ! class_exists( 'EOP_Post_Confirmation_Flow' ) || ! EOP_Post_Confirmation_Flow::is_enabled() ) {
            return false;
        }

        $meta_query = array(
            array(
                'key'     => '_eop_is_proposal',
                'value'   => 'yes',
                'compare' => '=',
            ),
        );

        if ( 'completed' === $flow_filter ) {
            $meta_query[] = array(
                'key'     => EOP_Post_Confirmation_Flow::META_FLAG,
                'value'   => 'yes',
                'compare' => '=',
            );
        } elseif ( 'pending' === $flow_filter ) {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key'     => EOP_Post_Confirmation_Flow::META_FLAG,
                    'value'   => 'yes',
                    'compare' => '!=',
                ),
                array(
                    'key'     => EOP_Post_Confirmation_Flow::META_FLAG,
                    'compare' => 'NOT EXISTS',
                ),
            );
        }

        return $meta_query;
    }

    private static function prepare_order_list_item( $order ) {
        $customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        $public_url    = EOP_Public_Proposal::get_public_link( $order );
        $date          = $order->get_date_created();

        return array(
            'id'              => $order->get_id(),
            'number'          => '#' . $order->get_id(),
            'customer_name'   => $customer_name ? $customer_name : __( 'Sem cliente', EOP_TEXT_DOMAIN ),
            'customer_email'  => $order->get_billing_email(),
            'status'          => $order->get_status(),
            'status_label'    => wc_get_order_status_name( $order->get_status() ),
            'total_html'      => wc_price( $order->get_total() ),
            'date_label'      => $date ? $date->date_i18n( 'd/m/Y H:i' ) : '—',
            'edit_url'        => admin_url( 'admin.php?page=eop-pedidos&action=edit&order_id=' . $order->get_id() ),
            'wc_url'          => $order->get_edit_order_url(),
            'pdf_url'         => EOP_Order_Creator::get_pdf_document_url( $order ),
            'public_url'      => $public_url,
            'is_proposal'     => ! empty( $public_url ),
            'post_confirmation_flow_summary' => class_exists( 'EOP_Post_Confirmation_Flow' ) ? EOP_Post_Confirmation_Flow::get_list_summary( $order ) : array(),
            'created_by_name' => (string) $order->get_meta( '_eop_created_by_name' ),
        );
    }

    private static function resolve_loaded_discount_data( $discount_amount, $base_amount, $discount_mode, $stored_type = '', $stored_value = '' ) {
        $discount_amount = max( 0, (float) $discount_amount );
        $base_amount     = max( 0, (float) $base_amount );
        $stored_type     = in_array( $stored_type, array( 'fixed', 'percent' ), true ) ? $stored_type : '';
        $stored_value    = max( 0, (float) $stored_value );

        if ( '' !== $stored_type && $stored_value > 0 ) {
            return array(
                'type'  => $stored_type,
                'value' => round( $stored_value, 2 ),
            );
        }

        if ( $discount_amount <= 0 ) {
            return array(
                'type'  => 'percent' === $discount_mode ? 'percent' : 'fixed',
                'value' => 0,
            );
        }

        if ( 'percent' === $discount_mode ) {
            return array(
                'type'  => 'percent',
                'value' => $base_amount > 0 ? round( ( $discount_amount / $base_amount ) * 100, 2 ) : 0,
            );
        }

        return array(
            'type'  => 'fixed',
            'value' => round( $discount_amount, 2 ),
        );
    }
}
