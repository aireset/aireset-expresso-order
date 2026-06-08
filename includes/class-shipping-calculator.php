<?php
defined( 'ABSPATH' ) || exit;

class EOP_Shipping_Calculator {

    private $original_user_id = null;
    private $cart_backup = array();
    private $customer_backup = array();

    public static function init() {
        $instance = new self();
        add_action( 'wp_ajax_eop_calculate_shipping', array( $instance, 'calculate_shipping' ) );
    }

    public function calculate_shipping() {
        check_ajax_referer( 'eop_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sem permissao.', EOP_TEXT_DOMAIN ) ) );
        }

        $items   = isset( $_POST['items'] ) ? json_decode( wp_unslash( $_POST['items'] ), true ) : array();
        $address = isset( $_POST['address'] ) ? json_decode( wp_unslash( $_POST['address'] ), true ) : array();
        $result  = self::calculate_rates_from_payload(
            is_array( $items ) ? $items : array(),
            is_array( $address ) ? $address : array()
        );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    public static function calculate_rates_from_payload( array $items, array $address ) {
        if ( empty( $items ) ) {
            return new WP_Error( 'eop_shipping_items_required', __( 'Adicione produtos antes de calcular o frete.', EOP_TEXT_DOMAIN ) );
        }

        $instance = new self();
        $address  = $instance->sanitize_address( $address );

        if ( empty( $address['postcode'] ) || empty( $address['city'] ) || empty( $address['address'] ) ) {
            return new WP_Error( 'eop_shipping_address_required', __( 'Preencha CEP, cidade e endereco para calcular o frete.', EOP_TEXT_DOMAIN ) );
        }

        try {
            $instance->bootstrap_frontend_classes();
            $instance->simulate_customer();
            $instance->populate_cart_from_items( $items );
            $instance->set_customer_address( $address );
            WC()->cart->calculate_totals();
            $rates = $instance->collect_rates();
        } catch ( \Exception $e ) {
            $instance->restore_state();
            return new WP_Error( 'eop_shipping_calculation_failed', $e->getMessage() );
        }

        $instance->restore_state();

        if ( empty( $rates ) ) {
            return new WP_Error( 'eop_shipping_rates_empty', __( 'Nenhum metodo de envio disponivel para este endereco.', EOP_TEXT_DOMAIN ) );
        }

        return array( 'rates' => $rates );
    }

    private function sanitize_address( array $address ) {
        return array(
            'country'   => sanitize_text_field( $address['country'] ?? 'BR' ),
            'state'     => sanitize_text_field( $address['state'] ?? '' ),
            'postcode'  => preg_replace( '/[^0-9]/', '', (string) ( $address['postcode'] ?? '' ) ),
            'city'      => sanitize_text_field( $address['city'] ?? '' ),
            'address'   => sanitize_text_field( $address['address'] ?? '' ),
            'number'    => sanitize_text_field( $address['number'] ?? '' ),
            'neighborhood' => sanitize_text_field( $address['neighborhood'] ?? '' ),
            'address_2' => sanitize_text_field( $address['address_2'] ?? '' ),
        );
    }

    private function bootstrap_frontend_classes() {
        if ( ! did_action( 'woocommerce_shipping_init' ) ) {
            do_action( 'woocommerce_shipping_init' );
        }

        WC()->shipping();

        if ( is_null( WC()->cart ) ) {
            wc_load_cart();
        }

        if ( is_null( WC()->customer ) ) {
            WC()->customer = new WC_Customer( 0, true );
        }

        if ( is_null( WC()->session ) ) {
            WC()->session = new WC_Session_Handler();
            WC()->session->init();
        }
    }

    private function simulate_customer() {
        $this->original_user_id = get_current_user_id();
        $this->cart_backup      = WC()->cart->get_cart_contents();
        $this->customer_backup  = array(
            'shipping_country'   => WC()->customer->get_shipping_country(),
            'shipping_state'     => WC()->customer->get_shipping_state(),
            'shipping_postcode'  => WC()->customer->get_shipping_postcode(),
            'shipping_city'      => WC()->customer->get_shipping_city(),
            'shipping_address_1' => WC()->customer->get_shipping_address_1(),
            'shipping_address_2' => WC()->customer->get_shipping_address_2(),
            'billing_country'    => WC()->customer->get_billing_country(),
            'billing_state'      => WC()->customer->get_billing_state(),
            'billing_postcode'   => WC()->customer->get_billing_postcode(),
            'billing_city'       => WC()->customer->get_billing_city(),
        );
    }

    private function populate_cart_from_items( array $items ) {
        $has_shippable = false;

        WC()->cart->empty_cart( false );

        foreach ( $items as $item ) {
            $product_id = absint( $item['product_id'] ?? 0 );
            $quantity   = max( 1, absint( $item['quantity'] ?? 1 ) );
            $product    = wc_get_product( $product_id );

            if ( ! $product ) {
                continue;
            }

            add_filter( 'woocommerce_product_is_in_stock', '__return_true' );
            add_filter( 'woocommerce_variation_is_in_stock', '__return_true' );

            $cart_key = WC()->cart->add_to_cart( $product_id, $quantity );

            remove_filter( 'woocommerce_product_is_in_stock', '__return_true' );
            remove_filter( 'woocommerce_variation_is_in_stock', '__return_true' );

            if ( $cart_key && $product->needs_shipping() ) {
                $has_shippable = true;
            }
        }

        if ( ! $has_shippable ) {
            throw new \Exception( __( 'Os produtos selecionados nao necessitam de envio.', EOP_TEXT_DOMAIN ) );
        }
    }

    private function set_customer_address( array $address ) {
        $address_2 = implode( ' | ', array_filter( array(
            $address['number'],
            $address['neighborhood'],
            $address['address_2'],
        ) ) );

        WC()->customer->set_shipping_country( $address['country'] );
        WC()->customer->set_shipping_state( $address['state'] );
        WC()->customer->set_shipping_postcode( $address['postcode'] );
        WC()->customer->set_shipping_city( $address['city'] );
        WC()->customer->set_shipping_address_1( $address['address'] );
        WC()->customer->set_shipping_address_2( $address_2 );
        WC()->customer->set_billing_country( $address['country'] );
        WC()->customer->set_billing_state( $address['state'] );
        WC()->customer->set_billing_postcode( $address['postcode'] );
        WC()->customer->set_billing_city( $address['city'] );
    }

    private function collect_rates() {
        $packages  = WC()->shipping()->get_packages();
        $all_rates = array();

        if ( empty( $packages ) ) {
            return $all_rates;
        }

        foreach ( $packages as $package_key => $package ) {
            $package_rates = array();

            if ( empty( $package['rates'] ) ) {
                continue;
            }

            foreach ( $package['rates'] as $rate ) {
                $taxes     = $rate->get_shipping_tax();
                $tax_total = is_array( $taxes ) ? array_sum( $taxes ) : 0;
                $meta      = array();

                foreach ( (array) $rate->get_meta_data() as $mk => $mv ) {
                    if ( is_scalar( $mv ) ) {
                        $meta[ $mk ] = (string) $mv;
                    }
                }

                $package_rates[] = array(
                    'id'          => $rate->get_id(),
                    'label'       => $rate->get_label(),
                    'cost'        => (float) $rate->get_cost(),
                    'tax'         => $tax_total,
                    'method_id'   => $rate->get_method_id(),
                    'instance_id' => $rate->get_instance_id(),
                    'meta_data'   => $meta,
                    'package'     => $package_key,
                );
            }

            if ( ! empty( $package_rates ) ) {
                $all_rates[] = array(
                    'package_key'  => $package_key,
                    'package_name' => sprintf( __( 'Pacote %d', EOP_TEXT_DOMAIN ), $package_key + 1 ),
                    'rates'        => $package_rates,
                );
            }
        }

        return $all_rates;
    }

    private function restore_state() {
        if ( $this->original_user_id ) {
            wp_set_current_user( $this->original_user_id );
        }

        if ( WC()->cart ) {
            WC()->cart->empty_cart( false );
        }

        if ( WC()->customer && ! empty( $this->customer_backup ) ) {
            WC()->customer->set_shipping_country( $this->customer_backup['shipping_country'] );
            WC()->customer->set_shipping_state( $this->customer_backup['shipping_state'] );
            WC()->customer->set_shipping_postcode( $this->customer_backup['shipping_postcode'] );
            WC()->customer->set_shipping_city( $this->customer_backup['shipping_city'] );
            WC()->customer->set_shipping_address_1( $this->customer_backup['shipping_address_1'] );
            WC()->customer->set_shipping_address_2( $this->customer_backup['shipping_address_2'] );
            WC()->customer->set_billing_country( $this->customer_backup['billing_country'] );
            WC()->customer->set_billing_state( $this->customer_backup['billing_state'] );
            WC()->customer->set_billing_postcode( $this->customer_backup['billing_postcode'] );
            WC()->customer->set_billing_city( $this->customer_backup['billing_city'] );
        }

        if ( WC()->shipping() ) {
            WC()->shipping()->reset_shipping();
        }
    }
}
