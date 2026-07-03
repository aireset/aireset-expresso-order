<?php
defined( 'ABSPATH' ) || exit;

class EOP_Ajax_Handlers {

    use EOP_License_Guard;

    public static function init() {
        if ( ! self::_prefetch_module_state() ) {
            return;
        }

        add_action( 'wp_ajax_eop_search_customer', array( __CLASS__, 'search_customer' ) );
        add_action( 'wp_ajax_eop_search_products', array( __CLASS__, 'search_products' ) );
        add_action( 'wp_ajax_eop_search_product_categories', array( __CLASS__, 'search_product_categories' ) );
        add_action( 'wp_ajax_eop_create_order', array( __CLASS__, 'create_order' ) );
    }

    /**
     * Search customer by CPF or CNPJ.
     */
    public static function search_customer() {
        check_ajax_referer( 'eop_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sem permissao.', EOP_TEXT_DOMAIN ) ) );
        }

        $document = sanitize_text_field( wp_unslash( $_POST['document'] ?? '' ) );
        $result   = self::find_customer_by_document( $document );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    public static function find_customer_by_document( $document ) {
        $document = sanitize_text_field( $document );
        $document = preg_replace( '/[^0-9]/', '', $document );

        if ( empty( $document ) ) {
            return new WP_Error( 'eop_customer_document_required', __( 'CPF/CNPJ nao informado.', EOP_TEXT_DOMAIN ) );
        }

        // Search in user meta (_billing_cpf and _billing_cnpj).
        $meta_key = strlen( $document ) <= 11 ? '_billing_cpf' : '_billing_cnpj';

        $users = get_users( array(
            'meta_key'   => $meta_key,
            'meta_value' => $document,
            'number'     => 1,
            'fields'     => array( 'ID', 'display_name', 'user_email' ),
        ) );

        if ( empty( $users ) ) {
            // Try the other meta key.
            $alt_key = $meta_key === '_billing_cpf' ? '_billing_cnpj' : '_billing_cpf';
            $users   = get_users( array(
                'meta_key'   => $alt_key,
                'meta_value' => $document,
                'number'     => 1,
                'fields'     => array( 'ID', 'display_name', 'user_email' ),
            ) );
        }

        if ( ! empty( $users ) ) {
            $user  = $users[0];
            $phone = get_user_meta( $user->ID, 'billing_phone', true );
            $name  = get_user_meta( $user->ID, 'billing_first_name', true ) . ' ' . get_user_meta( $user->ID, 'billing_last_name', true );
            $name  = trim( $name ) ?: $user->display_name;

            return array(
                'found'    => true,
                'user_id'  => (int) $user->ID,
                'name'     => $name,
                'email'    => $user->user_email,
                'phone'    => $phone,
                'document' => $document,
            );
        }

        return array(
            'found'    => false,
            'document' => $document,
        );
    }

    /**
     * Search products by title or SKU (Select2 format).
     */
    public static function search_products() {
        check_ajax_referer( 'eop_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sem permissao.', EOP_TEXT_DOMAIN ) ) );
        }

        $term    = sanitize_text_field( wp_unslash( $_GET['term'] ?? '' ) );
        $payload = self::search_products_payload( $term );

        wp_send_json( $payload );
    }

    public static function search_products_payload( $term ) {
        $term = sanitize_text_field( $term );

        $results = array();

        if ( empty( $term ) ) {
            // Pre-carga estilo Select2: lista os primeiros produtos publicados para a UI
            // filtrar localmente, sem exigir digitacao.
            $product_ids = wc_get_products( array(
                'limit'   => 100,
                'status'  => 'publish',
                'orderby' => 'title',
                'order'   => 'ASC',
                'return'  => 'ids',
            ) );
        } else {
            // Search by SKU first.
            $by_sku = wc_get_products( array(
                'sku'    => $term,
                'limit'  => 5,
                'status' => 'publish',
                'return' => 'ids',
            ) );

            // Search by title.
            $by_title = wc_get_products( array(
                's'      => $term,
                'limit'  => 15,
                'status' => 'publish',
                'return' => 'ids',
            ) );

            $product_ids = array_unique( array_merge( $by_sku, $by_title ) );
        }

        $eop_limit = empty( $term ) ? 100 : 20;
        foreach ( array_slice( $product_ids, 0, $eop_limit ) as $pid ) {
            $product = wc_get_product( $pid );
            if ( ! $product ) {
                continue;
            }

            $price_display = html_entity_decode( strip_tags( wc_price( $product->get_price() ) ), ENT_QUOTES, 'UTF-8' );
            $sku_display   = $product->get_sku() ? ' [' . $product->get_sku() . ']' : '';

            $image_url = '';
            $image_id  = $product->get_image_id();
            if ( $image_id ) {
                $src = wp_get_attachment_image_url( $image_id, 'thumbnail' );
                if ( $src ) {
                    $image_url = $src;
                }
            }
            if ( ! $image_url ) {
                $image_url = wc_placeholder_img_src( 'thumbnail' );
            }

            $results[] = array(
                'id'    => $product->get_id(),
                'text'  => $product->get_name() . $sku_display . ' - ' . $price_display,
                'price' => (float) $product->get_price(),
                'name'  => $product->get_name(),
                'sku'   => $product->get_sku(),
                'image' => $image_url,
            );
        }

        return array( 'results' => $results );
    }

    /**
     * Search product categories by name (Select2 format).
     */
    public static function search_product_categories() {
        check_ajax_referer( 'eop_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sem permissao.', EOP_TEXT_DOMAIN ) ) );
        }

        $term = sanitize_text_field( wp_unslash( $_GET['term'] ?? '' ) );
        $payload = self::search_product_categories_payload( $term );

        wp_send_json( $payload );
    }

    public static function search_product_categories_payload( $term ) {
        $term = sanitize_text_field( $term );

        $term_query_args = array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'number'     => empty( $term ) ? 200 : 20,
            'orderby'    => 'name',
            'order'      => 'ASC',
        );

        if ( ! empty( $term ) ) {
            // Pre-carga (term vazio) lista todas; com termo, filtra no servidor.
            $term_query_args['search'] = $term;
        }

        $terms = get_terms( $term_query_args );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return array( 'results' => array() );
        }

        $results = array();

        foreach ( $terms as $term_obj ) {
            if ( ! $term_obj || ! isset( $term_obj->term_id ) ) {
                continue;
            }

            $term_id = (int) $term_obj->term_id;
            $label   = (string) $term_obj->name;
            $parents = array_reverse( get_ancestors( $term_id, 'product_cat' ) );
            $parts   = array();

            foreach ( $parents as $parent_id ) {
                $parent = get_term( $parent_id, 'product_cat' );

                if ( $parent && ! is_wp_error( $parent ) ) {
                    $parts[] = $parent->name;
                }
            }

            $parts[] = $label;
            $label   = implode( ' / ', array_filter( $parts ) );

            $results[] = array(
                'id'   => $term_id,
                'text' => $label,
            );
        }

        return array( 'results' => $results );
    }

    /**
     * Create order via AJAX.
     */
    public static function create_order() {
        check_ajax_referer( 'eop_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sem permissao.', EOP_TEXT_DOMAIN ) ) );
        }

        $raw = wp_unslash( $_POST['order_data'] ?? '' );
        $data = json_decode( $raw, true );

        if ( ! is_array( $data ) ) {
            wp_send_json_error( array( 'message' => __( 'Dados invalidos.', EOP_TEXT_DOMAIN ) ) );
        }

        $result = self::create_order_from_payload( $data );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    public static function create_order_from_payload( array $data ) {
        return EOP_Order_Creator::create( $data );
    }
}
