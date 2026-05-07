<?php
defined( 'ABSPATH' ) || exit;

class EOP_Settings {

    const OPTION_KEY = 'eop_settings';
    private static $page_hook = '';

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_legacy_request' ) );
        add_action( 'admin_menu', array( __CLASS__, 'register_submenu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_filter( 'admin_body_class', array( __CLASS__, 'filter_admin_body_class' ) );
    }

    public static function get_defaults() {
        $defaults = array(
            'flow_mode'                              => 'proposal',
            'discount_mode'                          => 'both',
            'enable_checkout_confirmation'           => 'no',
            'service_products'                       => '',
            'service_product_categories'             => '',
            'order_page_id'                          => 0,
            'proposal_page_id'                       => 0,
            'brand_logo_url'                         => '',
            'primary_color'                          => '#00034b',
            'surface_color'                          => '#ffffff',
            'border_color'                           => '#dbe3f0',
            'proposal_background_color'              => '#f5f7ff',
            'proposal_card_color'                    => '#ffffff',
            'proposal_text_color'                    => '#172033',
            'proposal_muted_color'                   => '#5b6474',
            'proposal_max_width'                     => '1120',
            'proposal_title_size'                    => '40',
            'proposal_text_size'                     => '16',
            'border_radius'                          => '18',
            'font_family'                            => 'Montserrat:400,700',
            'customer_experience_font_family'        => 'Montserrat:400,700',
            'customer_experience_background_mode'    => 'gradient',
            'customer_experience_background_color'   => '#edf2fb',
            'customer_experience_background_secondary_color' => '#f7f9fc',
            'customer_experience_hero_background_mode' => 'gradient',
            'customer_experience_hero_background_color' => '#0f1b35',
            'customer_experience_hero_background_secondary_color' => '#243553',
            'customer_experience_panel_background_mode' => 'solid',
            'customer_experience_panel_background_color' => '#ffffff',
            'customer_experience_panel_background_secondary_color' => '#f7f9fc',
            'customer_experience_sidebar_background_mode' => 'solid',
            'customer_experience_sidebar_background_color' => '#f6f8fc',
            'customer_experience_sidebar_background_secondary_color' => '#ffffff',
            'customer_experience_accent_color'       => '#d78a2f',
            'customer_experience_text_color'         => '#16243a',
            'customer_experience_muted_color'        => '#66768d',
            'customer_experience_title_size'         => '46',
            'customer_experience_text_size'          => '16',
            'customer_experience_eyebrow'            => 'Experiencia do cliente',
            'customer_experience_title'              => 'Sua proposta esta pronta para seguir',
            'customer_experience_description'        => 'Confira os detalhes finais, valide os documentos e conclua a etapa atual em uma unica jornada.',
            'customer_experience_total_label'        => 'Investimento aprovado',
            'customer_experience_total_note'         => 'Assim que a etapa atual for concluida, o pedido segue para o time responsavel.',
            'customer_experience_items_eyebrow'      => '',
            'customer_experience_items_title'        => 'Itens',
            'customer_experience_summary_eyebrow'    => 'Contexto rapido',
            'customer_experience_summary_title'      => 'Visao do pedido',
            'customer_experience_financial_eyebrow'  => '',
            'customer_experience_financial_title'    => 'Resumo',
            'customer_experience_actions_eyebrow'    => 'Proxima acao',
            'customer_experience_actions_title'      => 'Como seguir agora',
            'customer_experience_progress_label'     => 'Mapa da jornada',
            'customer_experience_progress_note'      => 'As proximas etapas sao liberadas em sequencia para evitar retrabalho.',
            'panel_title'                            => 'Pedido Expresso',
            'panel_subtitle'                         => 'Monte o pedido, gere a proposta e compartilhe com o cliente.',
            'proposal_title'                         => 'Sua proposta esta pronta',
            'proposal_description'                   => 'Revise os itens e confirme para continuar.',
            'proposal_button_label'                  => 'Confirmar proposta',
            'proposal_pay_button_label'              => 'Ir para pagamento',
            'pdf_company_name'                       => get_bloginfo( 'name' ),
            'pdf_company_document'                   => '',
            'pdf_company_address'                    => '',
            'pdf_footer_note'                        => __( 'Documento gerado pelo Aireset Expresso Order.', EOP_TEXT_DOMAIN ),
        );

        foreach ( self::get_post_confirmation_document_slots() as $slot ) {
            $defaults[ 'post_confirmation_document_' . $slot . '_label' ]       = sprintf( __( 'Campo adicional %d', EOP_TEXT_DOMAIN ), $slot );
            $defaults[ 'post_confirmation_document_' . $slot . '_placeholder' ] = '';
        }

        return array_merge(
            $defaults,
            array(
                'enable_post_confirmation_flow'             => 'no',
                'post_confirmation_contract_title'          => __( 'Leia e confirme o contrato abaixo', EOP_TEXT_DOMAIN ),
                'post_confirmation_contract_body'           => __( 'Use este espaco para inserir o texto contratual que o cliente precisa ler e aceitar antes de continuar.', EOP_TEXT_DOMAIN ),
                'post_confirmation_contract_document_description' => __( 'Documento principal exibido na etapa de aceite.', EOP_TEXT_DOMAIN ),
                'post_confirmation_contract_checkbox_label' => __( 'Li e aceito o contrato acima.', EOP_TEXT_DOMAIN ),
                'post_confirmation_contract_button_label'   => __( 'Confirmar e continuar', EOP_TEXT_DOMAIN ),
                'post_confirmation_signature_documents'     => array(),
                'post_confirmation_documents_title'         => __( 'Dados do pedido', EOP_TEXT_DOMAIN ),
                'post_confirmation_documents_description'   => __( 'Os dados do cliente, documento e endereco sao aproveitados automaticamente do pedido WooCommerce.', EOP_TEXT_DOMAIN ),
                'post_confirmation_documents_button_label'  => __( 'Atualizar dados', EOP_TEXT_DOMAIN ),
                'post_confirmation_require_attachment'      => 'yes',
                'post_confirmation_upload_title'            => __( 'Envie o arquivo solicitado', EOP_TEXT_DOMAIN ),
                'post_confirmation_upload_description'      => __( 'Aceitamos arquivos JPG, PNG ou PDF.', EOP_TEXT_DOMAIN ),
                'post_confirmation_final_intro_eyebrow'    => __( 'Etapa final do pedido', EOP_TEXT_DOMAIN ),
                'post_confirmation_upload_field_label'      => __( 'Arquivo', EOP_TEXT_DOMAIN ),
                'post_confirmation_upload_button_label'     => __( 'Enviar arquivo', EOP_TEXT_DOMAIN ),
                'post_confirmation_products_title'          => __( 'Personalize os nomes dos produtos', EOP_TEXT_DOMAIN ),
                'post_confirmation_products_description'    => __( 'Informe como cada nome deve aparecer para os itens liberados.', EOP_TEXT_DOMAIN ),
                'post_confirmation_products_button_label'   => __( 'Salvar personalizacao', EOP_TEXT_DOMAIN ),
                'post_confirmation_locked_products'         => '',
                'post_confirmation_completion_title'        => __( 'Etapa complementar concluida', EOP_TEXT_DOMAIN ),
                'post_confirmation_completion_description'  => __( 'Recebemos suas informacoes e o pedido seguira para a equipe responsavel.', EOP_TEXT_DOMAIN ),
            )
        );

        foreach ( self::get_post_confirmation_upload_products_style_sections() as $section ) {
            foreach ( $section['fields'] as $field_key => $field ) {
                if ( array_key_exists( 'default', $field ) ) {
                    $defaults[ $field_key ] = $field['default'];
                }
            }
        }

        return $defaults;
    }

    public static function get_all() {
        return wp_parse_args( get_option( self::OPTION_KEY, array() ), self::get_defaults() );
    }

    public static function get( $key, $default = null ) {
        $settings = self::get_all();

        return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
    }

    public static function register_settings() {
        register_setting( 'eop_settings_group', self::OPTION_KEY, array( __CLASS__, 'sanitize_settings' ) );
    }

    public static function sanitize_settings( $input ) {
        $defaults                      = self::get_defaults();
        $existing                      = self::get_all();
        $input                         = is_array( $input ) ? $input : array();
        $submitted_input               = $input;
        $input                         = wp_parse_args( $input, $existing );
        $signature_documents           = self::sanitize_signature_documents_collection( $input['post_confirmation_signature_documents'] ?? $defaults['post_confirmation_signature_documents'] );
        $signature_documents_submitted = array_key_exists( 'post_confirmation_signature_documents', $submitted_input );

        $sanitized = array(
            'flow_mode'                             => in_array( $input['flow_mode'] ?? $defaults['flow_mode'], array( 'proposal', 'direct_order' ), true ) ? (string) ( $input['flow_mode'] ?? $defaults['flow_mode'] ) : $defaults['flow_mode'],
            'discount_mode'                         => in_array( $input['discount_mode'] ?? $defaults['discount_mode'], array( 'both', 'percent', 'fixed' ), true ) ? (string) ( $input['discount_mode'] ?? $defaults['discount_mode'] ) : $defaults['discount_mode'],
            'enable_checkout_confirmation'         => 'yes' === ( $input['enable_checkout_confirmation'] ?? 'no' ) ? 'yes' : 'no',
            'service_products'                     => sanitize_text_field( str_replace( array( "\r", "\n", ';' ), ',', (string) ( $input['service_products'] ?? $defaults['service_products'] ) ) ),
            'service_product_categories'           => sanitize_text_field( str_replace( array( "\r", "\n", ';' ), ',', (string) ( $input['service_product_categories'] ?? $defaults['service_product_categories'] ) ) ),
            'order_page_id'                        => absint( $input['order_page_id'] ?? $defaults['order_page_id'] ),
            'proposal_page_id'                     => absint( $input['proposal_page_id'] ?? $defaults['proposal_page_id'] ),
            'brand_logo_url'                       => esc_url_raw( $input['brand_logo_url'] ?? $defaults['brand_logo_url'] ),
            'primary_color'                        => self::sanitize_color( $input['primary_color'] ?? $defaults['primary_color'], $defaults['primary_color'] ),
            'surface_color'                        => self::sanitize_color( $input['surface_color'] ?? $defaults['surface_color'], $defaults['surface_color'] ),
            'border_color'                         => self::sanitize_color( $input['border_color'] ?? $defaults['border_color'], $defaults['border_color'] ),
            'proposal_background_color'            => self::sanitize_color( $input['proposal_background_color'] ?? $defaults['proposal_background_color'], $defaults['proposal_background_color'] ),
            'proposal_card_color'                  => self::sanitize_color( $input['proposal_card_color'] ?? $defaults['proposal_card_color'], $defaults['proposal_card_color'] ),
            'proposal_text_color'                  => self::sanitize_color( $input['proposal_text_color'] ?? $defaults['proposal_text_color'], $defaults['proposal_text_color'] ),
            'proposal_muted_color'                 => self::sanitize_color( $input['proposal_muted_color'] ?? $defaults['proposal_muted_color'], $defaults['proposal_muted_color'] ),
            'proposal_max_width'                   => (string) max( 720, min( 1600, absint( $input['proposal_max_width'] ?? $defaults['proposal_max_width'] ) ) ),
            'proposal_title_size'                  => (string) max( 22, min( 72, absint( $input['proposal_title_size'] ?? $defaults['proposal_title_size'] ) ) ),
            'proposal_text_size'                   => (string) max( 12, min( 24, absint( $input['proposal_text_size'] ?? $defaults['proposal_text_size'] ) ) ),
            'border_radius'                        => (string) max( 0, min( 48, absint( $input['border_radius'] ?? $defaults['border_radius'] ) ) ),
            'font_family'                          => self::sanitize_font_family( $input['font_family'] ?? $defaults['font_family'], $defaults['font_family'] ),
            'customer_experience_font_family'      => self::sanitize_font_family( $input['customer_experience_font_family'] ?? $defaults['customer_experience_font_family'], $defaults['customer_experience_font_family'] ),
            'customer_experience_background_mode'  => in_array( $input['customer_experience_background_mode'] ?? $defaults['customer_experience_background_mode'], array( 'solid', 'gradient' ), true ) ? (string) ( $input['customer_experience_background_mode'] ?? $defaults['customer_experience_background_mode'] ) : $defaults['customer_experience_background_mode'],
            'customer_experience_background_color' => self::sanitize_color( $input['customer_experience_background_color'] ?? $defaults['customer_experience_background_color'], $defaults['customer_experience_background_color'] ),
            'customer_experience_background_secondary_color' => self::sanitize_color( $input['customer_experience_background_secondary_color'] ?? $defaults['customer_experience_background_secondary_color'], $defaults['customer_experience_background_secondary_color'] ),
            'customer_experience_hero_background_mode' => in_array( $input['customer_experience_hero_background_mode'] ?? $defaults['customer_experience_hero_background_mode'], array( 'solid', 'gradient' ), true ) ? (string) ( $input['customer_experience_hero_background_mode'] ?? $defaults['customer_experience_hero_background_mode'] ) : $defaults['customer_experience_hero_background_mode'],
            'customer_experience_hero_background_color' => self::sanitize_color( $input['customer_experience_hero_background_color'] ?? $defaults['customer_experience_hero_background_color'], $defaults['customer_experience_hero_background_color'] ),
            'customer_experience_hero_background_secondary_color' => self::sanitize_color( $input['customer_experience_hero_background_secondary_color'] ?? $defaults['customer_experience_hero_background_secondary_color'], $defaults['customer_experience_hero_background_secondary_color'] ),
            'customer_experience_panel_background_mode' => in_array( $input['customer_experience_panel_background_mode'] ?? $defaults['customer_experience_panel_background_mode'], array( 'solid', 'gradient' ), true ) ? (string) ( $input['customer_experience_panel_background_mode'] ?? $defaults['customer_experience_panel_background_mode'] ) : $defaults['customer_experience_panel_background_mode'],
            'customer_experience_panel_background_color' => self::sanitize_color( $input['customer_experience_panel_background_color'] ?? $defaults['customer_experience_panel_background_color'], $defaults['customer_experience_panel_background_color'] ),
            'customer_experience_panel_background_secondary_color' => self::sanitize_color( $input['customer_experience_panel_background_secondary_color'] ?? $defaults['customer_experience_panel_background_secondary_color'], $defaults['customer_experience_panel_background_secondary_color'] ),
            'customer_experience_sidebar_background_mode' => in_array( $input['customer_experience_sidebar_background_mode'] ?? $defaults['customer_experience_sidebar_background_mode'], array( 'solid', 'gradient' ), true ) ? (string) ( $input['customer_experience_sidebar_background_mode'] ?? $defaults['customer_experience_sidebar_background_mode'] ) : $defaults['customer_experience_sidebar_background_mode'],
            'customer_experience_sidebar_background_color' => self::sanitize_color( $input['customer_experience_sidebar_background_color'] ?? $defaults['customer_experience_sidebar_background_color'], $defaults['customer_experience_sidebar_background_color'] ),
            'customer_experience_sidebar_background_secondary_color' => self::sanitize_color( $input['customer_experience_sidebar_background_secondary_color'] ?? $defaults['customer_experience_sidebar_background_secondary_color'], $defaults['customer_experience_sidebar_background_secondary_color'] ),
            'customer_experience_accent_color'     => self::sanitize_color( $input['customer_experience_accent_color'] ?? $defaults['customer_experience_accent_color'], $defaults['customer_experience_accent_color'] ),
            'customer_experience_text_color'       => self::sanitize_color( $input['customer_experience_text_color'] ?? $defaults['customer_experience_text_color'], $defaults['customer_experience_text_color'] ),
            'customer_experience_muted_color'      => self::sanitize_color( $input['customer_experience_muted_color'] ?? $defaults['customer_experience_muted_color'], $defaults['customer_experience_muted_color'] ),
            'customer_experience_title_size'       => (string) max( 24, min( 76, absint( $input['customer_experience_title_size'] ?? $defaults['customer_experience_title_size'] ) ) ),
            'customer_experience_text_size'        => (string) max( 13, min( 24, absint( $input['customer_experience_text_size'] ?? $defaults['customer_experience_text_size'] ) ) ),
            'customer_experience_eyebrow'          => sanitize_text_field( $input['customer_experience_eyebrow'] ?? $defaults['customer_experience_eyebrow'] ),
            'customer_experience_title'            => sanitize_text_field( $input['customer_experience_title'] ?? $defaults['customer_experience_title'] ),
            'customer_experience_description'      => sanitize_textarea_field( $input['customer_experience_description'] ?? $defaults['customer_experience_description'] ),
            'customer_experience_total_label'      => sanitize_text_field( $input['customer_experience_total_label'] ?? $defaults['customer_experience_total_label'] ),
            'customer_experience_total_note'       => sanitize_textarea_field( $input['customer_experience_total_note'] ?? $defaults['customer_experience_total_note'] ),
            'customer_experience_items_eyebrow'    => sanitize_text_field( $input['customer_experience_items_eyebrow'] ?? $defaults['customer_experience_items_eyebrow'] ),
            'customer_experience_items_title'      => sanitize_text_field( $input['customer_experience_items_title'] ?? $defaults['customer_experience_items_title'] ),
            'customer_experience_summary_eyebrow'  => sanitize_text_field( $input['customer_experience_summary_eyebrow'] ?? $defaults['customer_experience_summary_eyebrow'] ),
            'customer_experience_summary_title'    => sanitize_text_field( $input['customer_experience_summary_title'] ?? $defaults['customer_experience_summary_title'] ),
            'customer_experience_financial_eyebrow' => sanitize_text_field( $input['customer_experience_financial_eyebrow'] ?? $defaults['customer_experience_financial_eyebrow'] ),
            'customer_experience_financial_title'  => sanitize_text_field( $input['customer_experience_financial_title'] ?? $defaults['customer_experience_financial_title'] ),
            'customer_experience_actions_eyebrow'  => sanitize_text_field( $input['customer_experience_actions_eyebrow'] ?? $defaults['customer_experience_actions_eyebrow'] ),
            'customer_experience_actions_title'    => sanitize_text_field( $input['customer_experience_actions_title'] ?? $defaults['customer_experience_actions_title'] ),
            'customer_experience_progress_label'   => sanitize_text_field( $input['customer_experience_progress_label'] ?? $defaults['customer_experience_progress_label'] ),
            'customer_experience_progress_note'    => sanitize_textarea_field( $input['customer_experience_progress_note'] ?? $defaults['customer_experience_progress_note'] ),
            'panel_title'                          => sanitize_text_field( $input['panel_title'] ?? $defaults['panel_title'] ),
            'panel_subtitle'                       => sanitize_textarea_field( $input['panel_subtitle'] ?? $defaults['panel_subtitle'] ),
            'proposal_title'                       => sanitize_text_field( $input['proposal_title'] ?? $defaults['proposal_title'] ),
            'proposal_description'                 => sanitize_textarea_field( $input['proposal_description'] ?? $defaults['proposal_description'] ),
            'proposal_button_label'                => sanitize_text_field( $input['proposal_button_label'] ?? $defaults['proposal_button_label'] ),
            'proposal_pay_button_label'            => sanitize_text_field( $input['proposal_pay_button_label'] ?? $defaults['proposal_pay_button_label'] ),
            'pdf_company_name'                     => sanitize_text_field( $input['pdf_company_name'] ?? $defaults['pdf_company_name'] ),
            'pdf_company_document'                 => sanitize_text_field( $input['pdf_company_document'] ?? $defaults['pdf_company_document'] ),
            'pdf_company_address'                  => sanitize_textarea_field( $input['pdf_company_address'] ?? $defaults['pdf_company_address'] ),
            'pdf_footer_note'                      => sanitize_textarea_field( $input['pdf_footer_note'] ?? $defaults['pdf_footer_note'] ),
            'enable_post_confirmation_flow'        => 'yes' === ( $input['enable_post_confirmation_flow'] ?? 'no' ) ? 'yes' : 'no',
            'post_confirmation_contract_title'     => sanitize_text_field( $input['post_confirmation_contract_title'] ?? $defaults['post_confirmation_contract_title'] ),
            'post_confirmation_contract_body'      => $signature_documents_submitted ? '' : wp_kses_post( $input['post_confirmation_contract_body'] ?? $defaults['post_confirmation_contract_body'] ),
            'post_confirmation_contract_document_description' => sanitize_textarea_field( $input['post_confirmation_contract_document_description'] ?? $defaults['post_confirmation_contract_document_description'] ),
            'post_confirmation_contract_checkbox_label' => sanitize_text_field( $input['post_confirmation_contract_checkbox_label'] ?? $defaults['post_confirmation_contract_checkbox_label'] ),
            'post_confirmation_contract_button_label' => sanitize_text_field( $input['post_confirmation_contract_button_label'] ?? $defaults['post_confirmation_contract_button_label'] ),
            'post_confirmation_signature_documents' => $signature_documents,
            'post_confirmation_documents_title'    => sanitize_text_field( $input['post_confirmation_documents_title'] ?? $defaults['post_confirmation_documents_title'] ),
            'post_confirmation_documents_description' => sanitize_textarea_field( $input['post_confirmation_documents_description'] ?? $defaults['post_confirmation_documents_description'] ),
            'post_confirmation_documents_button_label' => sanitize_text_field( $input['post_confirmation_documents_button_label'] ?? $defaults['post_confirmation_documents_button_label'] ),
            'post_confirmation_require_attachment' => 'yes' === ( $input['post_confirmation_require_attachment'] ?? 'yes' ) ? 'yes' : 'no',
            'post_confirmation_upload_title'       => sanitize_text_field( $input['post_confirmation_upload_title'] ?? $defaults['post_confirmation_upload_title'] ),
            'post_confirmation_upload_description' => sanitize_textarea_field( $input['post_confirmation_upload_description'] ?? $defaults['post_confirmation_upload_description'] ),
            'post_confirmation_final_intro_eyebrow' => sanitize_text_field( $input['post_confirmation_final_intro_eyebrow'] ?? $defaults['post_confirmation_final_intro_eyebrow'] ),
            'post_confirmation_upload_field_label' => sanitize_text_field( $input['post_confirmation_upload_field_label'] ?? $defaults['post_confirmation_upload_field_label'] ),
            'post_confirmation_upload_button_label' => sanitize_text_field( $input['post_confirmation_upload_button_label'] ?? $defaults['post_confirmation_upload_button_label'] ),
            'post_confirmation_products_title'     => sanitize_text_field( $input['post_confirmation_products_title'] ?? $defaults['post_confirmation_products_title'] ),
            'post_confirmation_products_description' => sanitize_textarea_field( $input['post_confirmation_products_description'] ?? $defaults['post_confirmation_products_description'] ),
            'post_confirmation_products_button_label' => sanitize_text_field( $input['post_confirmation_products_button_label'] ?? $defaults['post_confirmation_products_button_label'] ),
            'post_confirmation_locked_products'    => sanitize_text_field( str_replace( array( "\r", "\n", ';' ), ',', (string) ( $input['post_confirmation_locked_products'] ?? $defaults['post_confirmation_locked_products'] ) ) ),
            'post_confirmation_completion_title'   => sanitize_text_field( $input['post_confirmation_completion_title'] ?? $defaults['post_confirmation_completion_title'] ),
            'post_confirmation_completion_description' => sanitize_textarea_field( $input['post_confirmation_completion_description'] ?? $defaults['post_confirmation_completion_description'] ),
        );

        foreach ( self::get_post_confirmation_document_slots() as $slot ) {
            $sanitized[ 'post_confirmation_document_' . $slot . '_label' ]       = sanitize_text_field( $input[ 'post_confirmation_document_' . $slot . '_label' ] ?? $defaults[ 'post_confirmation_document_' . $slot . '_label' ] );
            $sanitized[ 'post_confirmation_document_' . $slot . '_placeholder' ] = sanitize_text_field( $input[ 'post_confirmation_document_' . $slot . '_placeholder' ] ?? $defaults[ 'post_confirmation_document_' . $slot . '_placeholder' ] );
        }

        foreach ( self::get_post_confirmation_upload_products_style_sections() as $section ) {
            foreach ( $section['fields'] as $field_key => $field ) {
                $sanitized[ $field_key ] = self::sanitize_post_confirmation_visual_field(
                    $input[ $field_key ] ?? ( $defaults[ $field_key ] ?? '' ),
                    $field
                );
            }
        }

        return $sanitized;
    }

    public static function get_post_confirmation_document_slots() {
        return array();
    }

    public static function get_confirmation_flow_preview_data( $settings = array() ) {
        $settings = ! empty( $settings ) && is_array( $settings ) ? wp_parse_args( $settings, self::get_defaults() ) : self::get_all();

        if ( class_exists( 'EOP_Post_Confirmation_Flow' ) && method_exists( 'EOP_Post_Confirmation_Flow', 'get_admin_contract_preview_payload' ) ) {
            return EOP_Post_Confirmation_Flow::get_admin_contract_preview_payload( $settings );
        }

        return array(
            'document_count'       => 0,
            'additional_documents' => 0,
            'primary_document'     => array(),
        );
    }

    public static function get_post_confirmation_document_fields( $settings = array() ) {
		$settings = ! empty( $settings ) && is_array( $settings ) ? wp_parse_args( $settings, self::get_defaults() ) : self::get_all();
        $fields   = array();

        foreach ( self::get_post_confirmation_document_slots() as $slot ) {
            $label = trim( (string) ( $settings[ 'post_confirmation_document_' . $slot . '_label' ] ?? '' ) );

            if ( '' === $label ) {
                continue;
            }

            $fields[] = array(
                'key'         => 'document_' . $slot,
                'label'       => $label,
                'placeholder' => (string) ( $settings[ 'post_confirmation_document_' . $slot . '_placeholder' ] ?? '' ),
            );
        }

        return $fields;
    }

    public static function get_post_confirmation_upload_products_style_sections() {
        return array(
            array(
                'id'          => 'header',
                'label'       => __( 'Header superior', EOP_TEXT_DOMAIN ),
                'description' => __( 'Controle o topo azul com logo e informacoes do pedido.', EOP_TEXT_DOMAIN ),
                'fields'      => array(
                    'post_confirmation_visual_header_background_color' => array( 'label' => __( 'Fundo do header', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#0f1b35', 'css' => array( array( 'selector' => '.eop-post-flow--final-step .eop-post-flow__contract-header', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_header_padding'          => array( 'label' => __( 'Padding', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '20px 20px 24px', 'css' => array( array( 'selector' => '.eop-post-flow--final-step .eop-post-flow__contract-header', 'property' => 'padding' ) ) ),
                    'post_confirmation_visual_header_margin'           => array( 'label' => __( 'Margin', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow--final-step .eop-post-flow__contract-header', 'property' => 'margin' ) ) ),
                    'post_confirmation_visual_header_border_radius'    => array( 'label' => __( 'Border radius (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '0', 'min' => 0, 'max' => 48, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow--final-step .eop-post-flow__contract-header', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_visual_header_border_width'     => array( 'label' => __( 'Borda (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '0', 'min' => 0, 'max' => 12, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow--final-step .eop-post-flow__contract-header', 'property' => 'border-width' ) ) ),
                    'post_confirmation_visual_header_border_style'     => array( 'label' => __( 'Estilo da borda', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-post-flow--final-step .eop-post-flow__contract-header', 'property' => 'border-style' ) ) ),
                    'post_confirmation_visual_header_border_color'     => array( 'label' => __( 'Cor da borda', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#0f1b35', 'css' => array( array( 'selector' => '.eop-post-flow--final-step .eop-post-flow__contract-header', 'property' => 'border-color' ) ) ),
                    'post_confirmation_visual_header_box_shadow'       => array( 'label' => __( 'Box shadow', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'default' => 'none', 'css' => array( array( 'selector' => '.eop-post-flow--final-step .eop-post-flow__contract-header', 'property' => 'box-shadow' ) ) ),
                    'post_confirmation_visual_header_min_height'       => array( 'label' => __( 'Altura minima (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '0', 'min' => 0, 'max' => 420, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow--final-step .eop-post-flow__contract-header', 'property' => 'min-height' ) ) ),
                    'post_confirmation_visual_header_card_background_color' => array( 'label' => __( 'Fundo dos cards internos', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#31425f', 'css' => array( array( 'selector' => '.eop-post-flow__contract-brand, .eop-post-flow__contract-meta', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_header_card_border_radius' => array( 'label' => __( 'Radius dos cards internos (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '18', 'min' => 0, 'max' => 48, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__contract-brand, .eop-post-flow__contract-meta', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_visual_header_title_color'      => array( 'label' => __( 'Cor do nome da marca', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta strong', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_header_meta_color'       => array( 'label' => __( 'Cor do texto auxiliar', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#cfd7ea', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta span', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_header_font_family'      => array( 'label' => __( 'Fonte do header', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta', 'property' => 'font-family' ) ) ),
                    'post_confirmation_visual_header_title_size'       => array( 'label' => __( 'Tamanho do nome da marca (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '18', 'min' => 10, 'max' => 48, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta strong', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_header_meta_size'        => array( 'label' => __( 'Tamanho do pedido (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '13', 'min' => 10, 'max' => 36, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta span', 'property' => 'font-size' ) ) ),
                ),
            ),
            array(
                'id'          => 'breadcrumbs',
                'label'       => __( 'Breadcrumbs', EOP_TEXT_DOMAIN ),
                'description' => __( 'Ajuste as pilulas de progresso no topo da jornada.', EOP_TEXT_DOMAIN ),
                'fields'      => array(
                    'post_confirmation_visual_breadcrumb_gap'                     => array( 'label' => __( 'Espacamento entre itens (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '12', 'min' => 0, 'max' => 48, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb', 'property' => 'gap' ) ) ),
                    'post_confirmation_visual_breadcrumb_font_family'             => array( 'label' => __( 'Fonte', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'font-family' ) ) ),
                    'post_confirmation_visual_breadcrumb_font_size'               => array( 'label' => __( 'Tamanho do texto (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '14', 'min' => 10, 'max' => 32, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_breadcrumb_padding'                 => array( 'label' => __( 'Padding dos itens', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '12px 18px', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'padding' ) ) ),
                    'post_confirmation_visual_breadcrumb_border_radius'           => array( 'label' => __( 'Radius dos itens (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '999', 'min' => 0, 'max' => 999, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_visual_breadcrumb_border_width'            => array( 'label' => __( 'Borda dos itens (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '1', 'min' => 0, 'max' => 12, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'border-width' ) ) ),
                    'post_confirmation_visual_breadcrumb_border_style'            => array( 'label' => __( 'Estilo da borda', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'border-style' ) ) ),
                    'post_confirmation_visual_breadcrumb_border_color'            => array( 'label' => __( 'Cor da borda', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#dbe3f0', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'border-color' ) ) ),
                    'post_confirmation_visual_breadcrumb_background_color'        => array( 'label' => __( 'Fundo padrao', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_breadcrumb_text_color'              => array( 'label' => __( 'Texto padrao', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_breadcrumb_current_background_color' => array( 'label' => __( 'Fundo do item atual', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item.is-current', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_breadcrumb_current_text_color'      => array( 'label' => __( 'Texto do item atual', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item.is-current', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_breadcrumb_completed_background_color' => array( 'label' => __( 'Fundo do item concluido', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item.is-completed', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_breadcrumb_completed_text_color'    => array( 'label' => __( 'Texto do item concluido', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item.is-completed', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_breadcrumb_index_background_color'  => array( 'label' => __( 'Fundo do numero', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#eff3fb', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-index', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_breadcrumb_index_color'             => array( 'label' => __( 'Cor do numero', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-index', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_breadcrumb_current_index_background_color' => array( 'label' => __( 'Fundo do numero atual', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#d78a2f', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item.is-current .eop-post-flow__breadcrumb-index', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_breadcrumb_current_index_color'     => array( 'label' => __( 'Cor do numero atual', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item.is-current .eop-post-flow__breadcrumb-index', 'property' => 'color' ) ) ),
                ),
            ),
            array(
                'id'          => 'intro',
                'label'       => __( 'Bloco de titulo', EOP_TEXT_DOMAIN ),
                'description' => __( 'Personalize o card inicial com label superior, titulo e descricao.', EOP_TEXT_DOMAIN ),
                'fields'      => array(
                    'post_confirmation_visual_intro_background_color' => array( 'label' => __( 'Fundo do bloco', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_intro_padding'          => array( 'label' => __( 'Padding', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '34px 36px', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro', 'property' => 'padding' ) ) ),
                    'post_confirmation_visual_intro_margin'           => array( 'label' => __( 'Margin', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro', 'property' => 'margin' ) ) ),
                    'post_confirmation_visual_intro_border_radius'    => array( 'label' => __( 'Border radius (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '38', 'min' => 0, 'max' => 80, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_visual_intro_border_width'     => array( 'label' => __( 'Borda (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '1', 'min' => 0, 'max' => 12, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro', 'property' => 'border-width' ) ) ),
                    'post_confirmation_visual_intro_border_style'     => array( 'label' => __( 'Estilo da borda', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-post-flow__final-intro', 'property' => 'border-style' ) ) ),
                    'post_confirmation_visual_intro_border_color'     => array( 'label' => __( 'Cor da borda', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#dbe3f0', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro', 'property' => 'border-color' ) ) ),
                    'post_confirmation_visual_intro_box_shadow'       => array( 'label' => __( 'Box shadow', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'default' => '0 16px 36px rgba(15, 27, 53, .06)', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro', 'property' => 'box-shadow' ) ) ),
                    'post_confirmation_visual_intro_font_family'      => array( 'label' => __( 'Fonte', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro', 'property' => 'font-family' ) ) ),
                    'post_confirmation_visual_intro_eyebrow_color'    => array( 'label' => __( 'Cor da etiqueta superior', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#64748b', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro-eyebrow', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_intro_eyebrow_size'     => array( 'label' => __( 'Tamanho da etiqueta (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '15', 'min' => 10, 'max' => 32, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro-eyebrow', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_intro_title_color'      => array( 'label' => __( 'Cor do titulo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro-title', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_intro_title_size'       => array( 'label' => __( 'Tamanho do titulo (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '56', 'min' => 18, 'max' => 90, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro-title', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_intro_text_color'       => array( 'label' => __( 'Cor da descricao', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#66768d', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro-text', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_intro_text_size'        => array( 'label' => __( 'Tamanho da descricao (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '17', 'min' => 10, 'max' => 40, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro-text', 'property' => 'font-size' ) ) ),
                ),
            ),
            array(
                'id'          => 'upload',
                'label'       => __( 'Bloco de upload', EOP_TEXT_DOMAIN ),
                'description' => __( 'Controle a area do campo de anexo e do arquivo enviado.', EOP_TEXT_DOMAIN ),
                'fields'      => array(
                    'post_confirmation_visual_upload_background_color' => array( 'label' => __( 'Fundo do bloco', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__final-step-card .eop-post-flow__final-block:first-child', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_upload_padding'          => array( 'label' => __( 'Padding do bloco', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__final-step-card .eop-post-flow__final-block:first-child', 'property' => 'padding' ) ) ),
                    'post_confirmation_visual_upload_margin'           => array( 'label' => __( 'Margin do bloco', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__final-step-card .eop-post-flow__final-block:first-child', 'property' => 'margin' ) ) ),
                    'post_confirmation_visual_upload_border_radius'    => array( 'label' => __( 'Radius do bloco (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '0', 'min' => 0, 'max' => 80, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__final-step-card .eop-post-flow__final-block:first-child', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_visual_upload_box_shadow'       => array( 'label' => __( 'Sombra do bloco', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'default' => 'none', 'css' => array( array( 'selector' => '.eop-post-flow__final-step-card .eop-post-flow__final-block:first-child', 'property' => 'box-shadow' ) ) ),
                    'post_confirmation_visual_upload_label_color'      => array( 'label' => __( 'Cor da label do arquivo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#64748b', 'css' => array( array( 'selector' => '.eop-post-flow__field--file > span', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_upload_label_size'       => array( 'label' => __( 'Tamanho da label (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '15', 'min' => 10, 'max' => 32, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__field--file > span', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_upload_input_background_color' => array( 'label' => __( 'Fundo do campo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__field--file input', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_upload_input_text_color' => array( 'label' => __( 'Cor do texto do campo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__field--file input', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_upload_input_border_color' => array( 'label' => __( 'Cor da borda do campo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#d6defd', 'css' => array( array( 'selector' => '.eop-post-flow__field--file input', 'property' => 'border-color' ) ) ),
                    'post_confirmation_visual_upload_input_border_radius' => array( 'label' => __( 'Radius do campo (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '18', 'min' => 0, 'max' => 60, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__field--file input', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_visual_upload_input_padding'    => array( 'label' => __( 'Padding do campo', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '14px 16px', 'css' => array( array( 'selector' => '.eop-post-flow__field--file input', 'property' => 'padding' ) ) ),
                    'post_confirmation_visual_upload_meta_background_color' => array( 'label' => __( 'Fundo do card do anexo salvo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#eef3ff', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_upload_meta_title_color' => array( 'label' => __( 'Cor do nome do anexo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta strong', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_upload_meta_text_color'  => array( 'label' => __( 'Cor da data do anexo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#66768d', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta small', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_upload_meta_padding'     => array( 'label' => __( 'Padding do card do anexo', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '28px', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta', 'property' => 'padding' ) ) ),
                    'post_confirmation_visual_upload_meta_border_radius' => array( 'label' => __( 'Radius do card do anexo (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '26', 'min' => 0, 'max' => 60, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta', 'property' => 'border-radius' ) ) ),
                ),
            ),
            array(
                'id'          => 'products',
                'label'       => __( 'Produtos', EOP_TEXT_DOMAIN ),
                'description' => __( 'Controle o bloco de personalizacao dos produtos e as linhas da lista.', EOP_TEXT_DOMAIN ),
                'fields'      => array(
                    'post_confirmation_visual_products_title_color' => array( 'label' => __( 'Cor do titulo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__final-block-head strong', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_products_description_color' => array( 'label' => __( 'Cor da descricao', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#66768d', 'css' => array( array( 'selector' => '.eop-post-flow__final-block-head small', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_products_heading_color' => array( 'label' => __( 'Cor do cabecalho da tabela', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#64748b', 'css' => array( array( 'selector' => '.eop-post-flow__final-products-head', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_products_font_family'  => array( 'label' => __( 'Fonte da secao', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'css' => array( array( 'selector' => '.eop-post-flow__final-products-list, .eop-post-flow__final-block-head', 'property' => 'font-family' ) ) ),
                    'post_confirmation_visual_products_row_background_color' => array( 'label' => __( 'Fundo das linhas', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__final-product-row', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_products_row_border_color' => array( 'label' => __( 'Cor da borda das linhas', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#dbe3f0', 'css' => array( array( 'selector' => '.eop-post-flow__final-product-row', 'property' => 'border-color' ) ) ),
                    'post_confirmation_visual_products_row_border_radius' => array( 'label' => __( 'Radius das linhas (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '28', 'min' => 0, 'max' => 80, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__final-product-row', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_visual_products_row_padding'  => array( 'label' => __( 'Padding das linhas', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '26px', 'css' => array( array( 'selector' => '.eop-post-flow__final-product-row', 'property' => 'padding' ) ) ),
                    'post_confirmation_visual_products_row_shadow'   => array( 'label' => __( 'Sombra das linhas', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'default' => 'none', 'css' => array( array( 'selector' => '.eop-post-flow__final-product-row', 'property' => 'box-shadow' ) ) ),
                    'post_confirmation_visual_products_name_color'   => array( 'label' => __( 'Cor do nome do produto', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__final-product-copy strong', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_products_sku_color'    => array( 'label' => __( 'Cor do SKU', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#66768d', 'css' => array( array( 'selector' => '.eop-post-flow__final-product-copy small', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_products_input_background_color' => array( 'label' => __( 'Fundo do input do novo nome', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__final-name-field input', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_products_input_text_color' => array( 'label' => __( 'Cor do input do novo nome', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__final-name-field input', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_products_input_border_color' => array( 'label' => __( 'Borda do input do novo nome', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#c9d3e6', 'css' => array( array( 'selector' => '.eop-post-flow__final-name-field input', 'property' => 'border-color' ) ) ),
                    'post_confirmation_visual_products_input_border_radius' => array( 'label' => __( 'Radius do input (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '8', 'min' => 0, 'max' => 40, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__final-name-field input', 'property' => 'border-radius' ) ) ),
                ),
            ),
            array(
                'id'          => 'button',
                'label'       => __( 'Botao principal', EOP_TEXT_DOMAIN ),
                'description' => __( 'Defina o visual do CTA final da etapa.', EOP_TEXT_DOMAIN ),
                'fields'      => array(
                    'post_confirmation_visual_button_background_color' => array( 'label' => __( 'Fundo do botao', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#00034b', 'css' => array( array( 'selector' => '.eop-post-flow__final-submit', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_button_text_color'       => array( 'label' => __( 'Cor do texto', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__final-submit', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_button_font_family'      => array( 'label' => __( 'Fonte', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'css' => array( array( 'selector' => '.eop-post-flow__final-submit', 'property' => 'font-family' ) ) ),
                    'post_confirmation_visual_button_font_size'        => array( 'label' => __( 'Tamanho da fonte (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '20', 'min' => 10, 'max' => 40, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__final-submit', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_button_font_weight'      => array( 'label' => __( 'Peso da fonte', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => '700', 'choices' => array( '400' => '400', '500' => '500', '600' => '600', '700' => '700', '800' => '800' ), 'css' => array( array( 'selector' => '.eop-post-flow__final-submit', 'property' => 'font-weight' ) ) ),
                    'post_confirmation_visual_button_padding'          => array( 'label' => __( 'Padding', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '16px 24px', 'css' => array( array( 'selector' => '.eop-post-flow__final-submit', 'property' => 'padding' ) ) ),
                    'post_confirmation_visual_button_margin'           => array( 'label' => __( 'Margin', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '8px 0 0', 'css' => array( array( 'selector' => '.eop-post-flow__final-submit', 'property' => 'margin' ) ) ),
                    'post_confirmation_visual_button_border_radius'    => array( 'label' => __( 'Border radius (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '24', 'min' => 0, 'max' => 60, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__final-submit', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_visual_button_border_width'     => array( 'label' => __( 'Borda (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '0', 'min' => 0, 'max' => 12, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__final-submit', 'property' => 'border-width' ) ) ),
                    'post_confirmation_visual_button_border_style'     => array( 'label' => __( 'Estilo da borda', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-post-flow__final-submit', 'property' => 'border-style' ) ) ),
                    'post_confirmation_visual_button_border_color'     => array( 'label' => __( 'Cor da borda', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#00034b', 'css' => array( array( 'selector' => '.eop-post-flow__final-submit', 'property' => 'border-color' ) ) ),
                    'post_confirmation_visual_button_box_shadow'       => array( 'label' => __( 'Box shadow', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'default' => '0 16px 30px rgba(0, 3, 75, .20)', 'css' => array( array( 'selector' => '.eop-post-flow__final-submit', 'property' => 'box-shadow' ) ) ),
                ),
            ),
        );
    }

    private static function sanitize_post_confirmation_visual_field( $value, $definition ) {
        $type    = (string) ( $definition['type'] ?? 'text' );
        $default = $definition['default'] ?? '';

        switch ( $type ) {
            case 'color':
                return self::sanitize_color( $value, $default );
            case 'font':
                return self::sanitize_font_family( $value, $default );
            case 'number':
                $min = isset( $definition['min'] ) ? (int) $definition['min'] : 0;
                $max = isset( $definition['max'] ) ? (int) $definition['max'] : 9999;
                return (string) max( $min, min( $max, absint( $value ) ) );
            case 'select':
                $choices = array_keys( (array) ( $definition['choices'] ?? array() ) );
                $value   = (string) $value;
                return in_array( $value, $choices, true ) ? $value : (string) $default;
            case 'box':
                return self::sanitize_css_box_value( $value, $default );
            case 'shadow':
                return self::sanitize_css_shadow_value( $value, $default );
            case 'textarea':
                return sanitize_textarea_field( $value );
            default:
                return sanitize_text_field( $value );
        }
    }

    private static function sanitize_css_box_value( $value, $default = '0' ) {
        $value = is_string( $value ) ? trim( wp_strip_all_tags( $value ) ) : '';

        if ( '' === $value ) {
            return (string) $default;
        }

        if ( preg_match( '/[^0-9a-zA-Z.%\s\-(),]/', $value ) ) {
            return (string) $default;
        }

        return $value;
    }

    private static function sanitize_css_shadow_value( $value, $default = 'none' ) {
        $value = is_string( $value ) ? trim( wp_strip_all_tags( $value ) ) : '';

        if ( '' === $value ) {
            return (string) $default;
        }

        if ( preg_match( '/[^0-9a-zA-Z#.%\s,\-()]/', $value ) ) {
            return (string) $default;
        }

        return $value;
    }

    private static function render_post_confirmation_upload_products_visual_editor( $settings ) {
        ?>
        <div class="eop-visual-editor">
            <?php self::render_post_confirmation_upload_products_accordion_content( $settings ); ?>
            <?php self::render_post_confirmation_upload_products_accordion_global( $settings ); ?>
            <?php foreach ( self::get_post_confirmation_upload_products_style_sections() as $section ) : ?>
                <?php self::render_post_confirmation_upload_products_style_section( $section, $settings ); ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private static function render_post_confirmation_upload_products_accordion_content( $settings ) {
        ?>
        <div class="eop-accordion eop-visual-accordion">
            <button type="button" class="eop-accordion__toggle" aria-expanded="true">
                <span>
                    <strong><?php esc_html_e( 'Conteudo da etapa', EOP_TEXT_DOMAIN ); ?></strong>
                    <small><?php esc_html_e( 'Edite os textos principais do upload, da personalizacao e da etiqueta superior.', EOP_TEXT_DOMAIN ); ?></small>
                </span>
                <span class="eop-accordion__icon">-</span>
            </button>
            <div class="eop-accordion__body">
                <div class="eop-settings-grid">
                    <div class="eop-settings-field">
                        <label for="eop_post_confirmation_final_intro_eyebrow_visual"><?php esc_html_e( 'Texto "Etapa final"', EOP_TEXT_DOMAIN ); ?></label>
                        <input id="eop_post_confirmation_final_intro_eyebrow_visual" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_final_intro_eyebrow]" value="<?php echo esc_attr( $settings['post_confirmation_final_intro_eyebrow'] ); ?>" />
                    </div>
                    <div class="eop-settings-field is-full">
                        <label for="eop_post_confirmation_upload_title_visual"><?php esc_html_e( 'Titulo do upload', EOP_TEXT_DOMAIN ); ?></label>
                        <input id="eop_post_confirmation_upload_title_visual" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_upload_title]" value="<?php echo esc_attr( $settings['post_confirmation_upload_title'] ); ?>" />
                    </div>
                    <div class="eop-settings-field is-full">
                        <label for="eop_post_confirmation_upload_description_visual"><?php esc_html_e( 'Descricao do upload', EOP_TEXT_DOMAIN ); ?></label>
                        <textarea id="eop_post_confirmation_upload_description_visual" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_upload_description]"><?php echo esc_textarea( $settings['post_confirmation_upload_description'] ); ?></textarea>
                    </div>
                    <div class="eop-settings-field">
                        <label for="eop_post_confirmation_upload_field_label_visual"><?php esc_html_e( 'Label do arquivo', EOP_TEXT_DOMAIN ); ?></label>
                        <input id="eop_post_confirmation_upload_field_label_visual" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_upload_field_label]" value="<?php echo esc_attr( $settings['post_confirmation_upload_field_label'] ); ?>" />
                    </div>
                    <div class="eop-settings-field">
                        <label for="eop_post_confirmation_upload_button_label_visual"><?php esc_html_e( 'Botao do upload', EOP_TEXT_DOMAIN ); ?></label>
                        <input id="eop_post_confirmation_upload_button_label_visual" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_upload_button_label]" value="<?php echo esc_attr( $settings['post_confirmation_upload_button_label'] ); ?>" />
                    </div>
                    <div class="eop-settings-field is-full">
                        <label for="eop_post_confirmation_products_title_visual"><?php esc_html_e( 'Titulo da personalizacao', EOP_TEXT_DOMAIN ); ?></label>
                        <input id="eop_post_confirmation_products_title_visual" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_products_title]" value="<?php echo esc_attr( $settings['post_confirmation_products_title'] ); ?>" />
                    </div>
                    <div class="eop-settings-field is-full">
                        <label for="eop_post_confirmation_products_description_visual"><?php esc_html_e( 'Descricao da personalizacao', EOP_TEXT_DOMAIN ); ?></label>
                        <textarea id="eop_post_confirmation_products_description_visual" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_products_description]"><?php echo esc_textarea( $settings['post_confirmation_products_description'] ); ?></textarea>
                    </div>
                    <div class="eop-settings-field">
                        <label for="eop_post_confirmation_products_button_label_visual"><?php esc_html_e( 'Botao da personalizacao', EOP_TEXT_DOMAIN ); ?></label>
                        <input id="eop_post_confirmation_products_button_label_visual" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_products_button_label]" value="<?php echo esc_attr( $settings['post_confirmation_products_button_label'] ); ?>" />
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_post_confirmation_upload_products_accordion_global( $settings ) {
        ?>
        <div class="eop-accordion eop-visual-accordion">
            <button type="button" class="eop-accordion__toggle" aria-expanded="false">
                <span>
                    <strong><?php esc_html_e( 'Base visual da experiencia', EOP_TEXT_DOMAIN ); ?></strong>
                    <small><?php esc_html_e( 'Defina a base geral de fonte, cores e fundo da pagina publica.', EOP_TEXT_DOMAIN ); ?></small>
                </span>
                <span class="eop-accordion__icon">+</span>
            </button>
            <div class="eop-accordion__body" hidden>
                <div class="eop-settings-grid">
                    <div class="eop-settings-field is-full">
                        <label for="eop_customer_experience_font_family_upload_products_preview"><?php esc_html_e( 'Fonte da experiencia publica', EOP_TEXT_DOMAIN ); ?></label>
                        <input id="eop_customer_experience_font_family_upload_products_preview" class="select_font eop-font-field" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_font_family]" value="<?php echo esc_attr( $settings['customer_experience_font_family'] ); ?>" />
                    </div>
                    <div class="eop-settings-field">
                        <label for="eop_customer_experience_title_size_upload_products_preview"><?php esc_html_e( 'Tamanho do titulo principal (px)', EOP_TEXT_DOMAIN ); ?></label>
                        <input id="eop_customer_experience_title_size_upload_products_preview" type="number" min="24" max="76" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_title_size]" value="<?php echo esc_attr( $settings['customer_experience_title_size'] ); ?>" />
                    </div>
                    <div class="eop-settings-field">
                        <label for="eop_customer_experience_text_size_upload_products_preview"><?php esc_html_e( 'Tamanho do texto base (px)', EOP_TEXT_DOMAIN ); ?></label>
                        <input id="eop_customer_experience_text_size_upload_products_preview" type="number" min="13" max="24" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_text_size]" value="<?php echo esc_attr( $settings['customer_experience_text_size'] ); ?>" />
                    </div>
                    <div class="eop-settings-field">
                        <label for="eop_customer_experience_background_color_upload_products_preview"><?php esc_html_e( 'Fundo da pagina', EOP_TEXT_DOMAIN ); ?></label>
                        <input id="eop_customer_experience_background_color_upload_products_preview" class="eop-color-field" type="text" data-default-color="#edf2fb" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_background_color]" value="<?php echo esc_attr( $settings['customer_experience_background_color'] ); ?>" />
                    </div>
                    <div class="eop-settings-field">
                        <label for="eop_customer_experience_hero_background_color_upload_products_preview"><?php esc_html_e( 'Fundo do topo', EOP_TEXT_DOMAIN ); ?></label>
                        <input id="eop_customer_experience_hero_background_color_upload_products_preview" class="eop-color-field" type="text" data-default-color="#0f1b35" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_hero_background_color]" value="<?php echo esc_attr( $settings['customer_experience_hero_background_color'] ); ?>" />
                    </div>
                    <div class="eop-settings-field">
                        <label for="eop_customer_experience_panel_background_color_upload_products_preview"><?php esc_html_e( 'Fundo dos cards', EOP_TEXT_DOMAIN ); ?></label>
                        <input id="eop_customer_experience_panel_background_color_upload_products_preview" class="eop-color-field" type="text" data-default-color="#ffffff" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_panel_background_color]" value="<?php echo esc_attr( $settings['customer_experience_panel_background_color'] ); ?>" />
                    </div>
                    <div class="eop-settings-field">
                        <label for="eop_customer_experience_accent_color_upload_products_preview"><?php esc_html_e( 'Cor de destaque', EOP_TEXT_DOMAIN ); ?></label>
                        <input id="eop_customer_experience_accent_color_upload_products_preview" class="eop-color-field" type="text" data-default-color="#d78a2f" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_accent_color]" value="<?php echo esc_attr( $settings['customer_experience_accent_color'] ); ?>" />
                    </div>
                    <div class="eop-settings-field">
                        <label for="eop_customer_experience_text_color_upload_products_preview"><?php esc_html_e( 'Texto principal', EOP_TEXT_DOMAIN ); ?></label>
                        <input id="eop_customer_experience_text_color_upload_products_preview" class="eop-color-field" type="text" data-default-color="#16243a" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_text_color]" value="<?php echo esc_attr( $settings['customer_experience_text_color'] ); ?>" />
                    </div>
                    <div class="eop-settings-field">
                        <label for="eop_customer_experience_muted_color_upload_products_preview"><?php esc_html_e( 'Texto auxiliar', EOP_TEXT_DOMAIN ); ?></label>
                        <input id="eop_customer_experience_muted_color_upload_products_preview" class="eop-color-field" type="text" data-default-color="#66768d" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_muted_color]" value="<?php echo esc_attr( $settings['customer_experience_muted_color'] ); ?>" />
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_post_confirmation_upload_products_style_section( $section, $settings ) {
        ?>
        <div class="eop-accordion eop-visual-accordion">
            <button type="button" class="eop-accordion__toggle" aria-expanded="false">
                <span>
                    <strong><?php echo esc_html( (string) $section['label'] ); ?></strong>
                    <small><?php echo esc_html( (string) $section['description'] ); ?></small>
                </span>
                <span class="eop-accordion__icon">+</span>
            </button>
            <div class="eop-accordion__body" hidden>
                <div class="eop-settings-grid">
                    <?php foreach ( $section['fields'] as $field_key => $field ) : ?>
                        <?php self::render_post_confirmation_upload_products_style_field( $field_key, $field, $settings ); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_post_confirmation_upload_products_style_field( $field_key, $field, $settings ) {
        $value      = $settings[ $field_key ] ?? ( $field['default'] ?? '' );
        $input_id   = 'eop_' . sanitize_html_class( $field_key );
        $field_type = (string) ( $field['type'] ?? 'text' );
        $is_full    = ! empty( $field['full'] );
        ?>
        <div class="eop-settings-field<?php echo $is_full ? ' is-full' : ''; ?>">
            <label for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( (string) $field['label'] ); ?></label>
            <?php if ( 'color' === $field_type ) : ?>
                <input id="<?php echo esc_attr( $input_id ); ?>" class="eop-color-field" type="text" data-default-color="<?php echo esc_attr( (string) ( $field['default'] ?? '' ) ); ?>" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $field_key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>" />
            <?php elseif ( 'font' === $field_type ) : ?>
                <input id="<?php echo esc_attr( $input_id ); ?>" class="select_font eop-font-field" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $field_key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>" />
            <?php elseif ( 'textarea' === $field_type ) : ?>
                <textarea id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $field_key ); ?>]"><?php echo esc_textarea( (string) $value ); ?></textarea>
            <?php elseif ( 'select' === $field_type ) : ?>
                <select id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $field_key ); ?>]">
                    <?php foreach ( (array) ( $field['choices'] ?? array() ) as $choice_value => $choice_label ) : ?>
                        <option value="<?php echo esc_attr( (string) $choice_value ); ?>"<?php selected( (string) $value, (string) $choice_value ); ?>><?php echo esc_html( (string) $choice_label ); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ( 'number' === $field_type ) : ?>
                <input id="<?php echo esc_attr( $input_id ); ?>" type="number" min="<?php echo esc_attr( (string) ( $field['min'] ?? 0 ) ); ?>" max="<?php echo esc_attr( (string) ( $field['max'] ?? 9999 ) ); ?>" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $field_key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>" />
            <?php else : ?>
                <input id="<?php echo esc_attr( $input_id ); ?>" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $field_key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>" />
            <?php endif; ?>
        </div>
        <?php
    }

    public static function get_post_confirmation_signature_documents( $settings = array() ) {
		$settings  = ! empty( $settings ) && is_array( $settings ) ? wp_parse_args( $settings, self::get_defaults() ) : self::get_all();
        $documents = $settings['post_confirmation_signature_documents'] ?? array();

        return self::sanitize_signature_documents_collection( $documents );
    }

    public static function get_post_confirmation_contract_documents( $settings = array() ) {
		$settings        = ! empty( $settings ) && is_array( $settings ) ? wp_parse_args( $settings, self::get_defaults() ) : self::get_all();
        $documents       = self::get_post_confirmation_signature_documents( $settings );
        $legacy_contract = trim( (string) ( $settings['post_confirmation_contract_body'] ?? '' ) );

        if ( '' === trim( wp_strip_all_tags( $legacy_contract ) ) ) {
            return $documents;
        }

        array_unshift(
            $documents,
            array(
                'key'           => 'contrato-principal',
                'title'         => sanitize_text_field( $settings['post_confirmation_contract_title'] ?? __( 'Contrato principal', EOP_TEXT_DOMAIN ) ),
                'description'   => sanitize_textarea_field( $settings['post_confirmation_contract_document_description'] ?? __( 'Documento principal exibido na etapa de aceite.', EOP_TEXT_DOMAIN ) ),
                'source_type'   => 'editor',
                'body'          => wp_kses_post( $legacy_contract ),
                'attachment_id' => 0,
                'button_label'  => __( 'Baixar PDF', EOP_TEXT_DOMAIN ),
                'view_label'    => __( 'Visualizar PDF', EOP_TEXT_DOMAIN ),
            )
        );

        return self::sanitize_signature_documents_collection( $documents );
    }

    public static function get_post_confirmation_locked_product_selector_state( $settings = array() ) {
		$settings = ! empty( $settings ) && is_array( $settings ) ? wp_parse_args( $settings, self::get_defaults() ) : self::get_all();
        $raw      = str_replace( array( "\r", "\n", ';' ), ',', (string) ( $settings['post_confirmation_locked_products'] ?? '' ) );

        return self::get_product_selector_state_from_raw( $raw );
    }

    public static function get_service_product_selector_state( $settings = array() ) {
		$settings = ! empty( $settings ) && is_array( $settings ) ? wp_parse_args( $settings, self::get_defaults() ) : self::get_all();
        $raw      = str_replace( array( "\r", "\n", ';' ), ',', (string) ( $settings['service_products'] ?? '' ) );

        return self::get_product_selector_state_from_raw( $raw );
    }

    public static function get_service_product_tokens( $settings = array() ) {
		$settings = ! empty( $settings ) && is_array( $settings ) ? wp_parse_args( $settings, self::get_defaults() ) : self::get_all();
        $raw      = str_replace( array( "\r", "\n", ';' ), ',', (string) ( $settings['service_products'] ?? '' ) );

        return array_values( array_unique( array_filter( array_map( 'strtolower', array_map( 'trim', explode( ',', $raw ) ) ) ) ) );
    }

    public static function get_service_product_category_selector_state( $settings = array() ) {
		$settings = ! empty( $settings ) && is_array( $settings ) ? wp_parse_args( $settings, self::get_defaults() ) : self::get_all();
        $raw      = str_replace( array( "\r", "\n", ';' ), ',', (string) ( $settings['service_product_categories'] ?? '' ) );

        return self::get_service_product_category_selector_state_from_raw( $raw );
    }

    public static function get_service_product_category_tokens( $settings = array() ) {
		$settings = ! empty( $settings ) && is_array( $settings ) ? wp_parse_args( $settings, self::get_defaults() ) : self::get_all();
        $raw      = str_replace( array( "\r", "\n", ';' ), ',', (string) ( $settings['service_product_categories'] ?? '' ) );

        return array_values( array_unique( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) ) );
    }

    public static function is_service_product( $product ) {
        if ( ! $product instanceof WC_Product ) {
            return false;
        }

        $tokens          = self::get_service_product_tokens();
        $category_tokens  = self::get_service_product_category_tokens();

        if ( ! empty( $tokens ) ) {
            $candidates = array_filter(
                array(
                    strtolower( (string) $product->get_id() ),
                    strtolower( (string) $product->get_parent_id() ),
                    strtolower( (string) $product->get_sku() ),
                ),
                static function ( $value ) {
                    return '' !== trim( (string) $value ) && '0' !== (string) $value;
                }
            );

            if ( ! empty( array_intersect( $tokens, $candidates ) ) ) {
                return true;
            }
        }

        if ( empty( $category_tokens ) ) {
            return false;
        }

        $category_ids = $product->get_category_ids();

        if ( empty( $category_ids ) && $product->get_parent_id() ) {
            $parent_product = wc_get_product( $product->get_parent_id() );

            if ( $parent_product instanceof WC_Product ) {
                $category_ids = $parent_product->get_category_ids();
            }
        }

        $category_candidates = array_values(
            array_filter(
                array_map(
                    'strval',
                    array_map( 'absint', is_array( $category_ids ) ? $category_ids : array() )
                ),
                static function ( $value ) {
                    return '' !== trim( (string) $value ) && '0' !== (string) $value;
                }
            )
        );

        return ! empty( array_intersect( $category_tokens, $category_candidates ) );
    }

    private static function get_product_selector_state_from_raw( $raw ) {
        $tokens   = array_values( array_unique( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) ) );

        if ( ! class_exists( 'WC_Product' ) || ! function_exists( 'wc_get_product' ) || ! function_exists( 'wc_get_products' ) ) {
            return array(
                'options'          => array(),
                'missing_tokens'   => $tokens,
                'serialized_value' => implode( ',', $tokens ),
            );
        }

        $options = array();
        $missing = array();

        foreach ( $tokens as $token ) {
            $product = null;

            if ( ctype_digit( $token ) ) {
                $product = wc_get_product( absint( $token ) );
            } else {
                $product_ids = wc_get_products(
                    array(
                        'sku'    => sanitize_text_field( $token ),
                        'limit'  => 1,
                        'status' => array( 'publish', 'private' ),
                        'return' => 'ids',
                    )
                );

                if ( ! empty( $product_ids ) ) {
                    $product = wc_get_product( (int) reset( $product_ids ) );
                }
            }

            if ( ! $product instanceof WC_Product ) {
                $missing[] = $token;
                continue;
            }

            $product_id = $product->get_id();
            $label      = $product->get_name();

            if ( $product->get_sku() ) {
                $label .= ' [' . $product->get_sku() . ']';
            }

            if ( '' !== (string) $product->get_price() ) {
                $label .= ' - ' . wp_strip_all_tags( wc_price( $product->get_price() ) );
            }

            $options[ $product_id ] = array(
                'id'   => $product_id,
                'text' => $label,
            );
        }

        return array(
            'options'          => array_values( $options ),
            'missing_tokens'   => $missing,
            'serialized_value' => implode( ',', array_merge( array_map( 'strval', array_keys( $options ) ), $missing ) ),
        );
    }

    private static function get_service_product_category_selector_state_from_raw( $raw ) {
        $tokens = array_values( array_unique( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) ) );

        if ( ! taxonomy_exists( 'product_cat' ) || ! function_exists( 'get_term' ) || ! function_exists( 'get_terms' ) ) {
            return array(
                'options'          => array(),
                'missing_tokens'   => $tokens,
                'serialized_value' => implode( ',', $tokens ),
            );
        }

        $options = array();
        $missing = array();

        foreach ( $tokens as $token ) {
            $term = null;

            if ( ctype_digit( $token ) ) {
                $term = get_term( absint( $token ), 'product_cat' );
            } else {
                $terms = get_terms(
                    array(
                        'taxonomy'   => 'product_cat',
                        'hide_empty' => false,
                        'search'     => sanitize_text_field( $token ),
                        'number'     => 1,
                    )
                );

                if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                    $term = reset( $terms );
                }
            }

            if ( ! $term || is_wp_error( $term ) ) {
                $missing[] = $token;
                continue;
            }

            $term_id = $term->term_id;
            $label   = $term->name;
            $parents = array_reverse( get_ancestors( $term_id, 'product_cat' ) );
            $parts   = array();

            foreach ( $parents as $parent_id ) {
                $parent = get_term( $parent_id, 'product_cat' );

                if ( $parent && ! is_wp_error( $parent ) ) {
                    $parts[] = $parent->name;
                }
            }

            $parts[] = $label;
            $label    = implode( ' / ', array_filter( $parts ) );

            $options[ $term_id ] = array(
                'id'   => $term_id,
                'text' => $label,
            );
        }

        return array(
            'options'          => array_values( $options ),
            'missing_tokens'   => $missing,
            'serialized_value' => implode( ',', array_merge( array_map( 'strval', array_keys( $options ) ), $missing ) ),
        );
    }

    private static function sanitize_signature_documents_collection( $documents ) {
        if ( ! is_array( $documents ) ) {
            return array();
        }

        $sanitized = array();

        foreach ( $documents as $index => $document ) {
            if ( ! is_array( $document ) ) {
                continue;
            }

            $title         = sanitize_text_field( $document['title'] ?? '' );
            $description   = sanitize_text_field( $document['description'] ?? '' );
            $source_type   = in_array( $document['source_type'] ?? 'editor', array( 'editor', 'attachment' ), true ) ? $document['source_type'] : 'editor';
            $body          = wp_kses_post( $document['body'] ?? '' );
            $attachment_id = absint( $document['attachment_id'] ?? 0 );
            $button_label  = sanitize_text_field( $document['button_label'] ?? '' );
            $view_label    = sanitize_text_field( $document['view_label'] ?? '' );
            $key_source    = sanitize_title( $document['key'] ?? '' );

            if ( '' === $title && '' === $description && '' === trim( wp_strip_all_tags( $body ) ) && 0 === $attachment_id ) {
                continue;
            }

            if ( '' === $title ) {
                $title = sprintf( __( 'Documento %d', EOP_TEXT_DOMAIN ), count( $sanitized ) + 1 );
            }

            $sanitized[] = array(
                'key'           => '' !== $key_source ? $key_source : 'documento-' . ( $index + 1 ),
                'title'         => $title,
                'description'   => $description,
                'source_type'   => $source_type,
                'body'          => $body,
                'attachment_id' => $attachment_id,
                'button_label'  => '' !== $button_label ? $button_label : __( 'Baixar PDF', EOP_TEXT_DOMAIN ),
                'view_label'    => '' !== $view_label ? $view_label : __( 'Visualizar PDF', EOP_TEXT_DOMAIN ),
            );
        }

        return array_values( $sanitized );
    }

    private static function sanitize_color( $value, $fallback ) {
        $color = sanitize_hex_color( $value );

        return $color ? $color : $fallback;
    }

    private static function sanitize_font_family( $value, $fallback ) {
        $value = sanitize_text_field( (string) $value );
        $value = preg_replace( '/[^A-Za-z0-9,\:\+\-\s]/', '', $value );
        $value = trim( preg_replace( '/\s+/', ' ', $value ) );

        return '' !== $value ? $value : $fallback;
    }

    public static function get_font_stylesheet_url( $font_value = '' ) {
        $font_value = self::sanitize_font_family( $font_value ?: self::get( 'font_family', self::get_defaults()['font_family'] ), self::get_defaults()['font_family'] );

        if ( '' === $font_value ) {
            return '';
        }

        return 'https://fonts.googleapis.com/css?family=' . str_replace( '%2B', '+', rawurlencode( $font_value ) ) . '&display=swap';
    }

    public static function get_font_stylesheet_urls( $font_values = array() ) {
        $font_values = is_array( $font_values ) ? $font_values : array( $font_values );
        $urls        = array();

        foreach ( $font_values as $font_value ) {
            $url = self::get_font_stylesheet_url( (string) $font_value );

            if ( '' !== $url ) {
                $urls[ $url ] = $url;
            }
        }

        return array_values( $urls );
    }

    public static function get_font_css_family( $font_value = '' ) {
        $font_value = self::sanitize_font_family( $font_value ?: self::get( 'font_family', self::get_defaults()['font_family'] ), self::get_defaults()['font_family'] );

        if ( false !== strpos( $font_value, ',' ) && false === strpos( $font_value, ':' ) ) {
            return $font_value;
        }

        $family = preg_replace( '/:.+$/', '', $font_value );
        $family = trim( str_replace( '+', ' ', $family ) );

        if ( '' === $family ) {
            $family = preg_replace( '/:.+$/', '', self::get_defaults()['font_family'] );
            $family = trim( str_replace( '+', ' ', $family ) );
        }

        return $family;
    }

    private static function build_help_tooltip_payload( $label, $help, $effect = '' ) {
        return array(
            'label'  => (string) $label,
            'help'   => (string) $help,
            'effect' => (string) $effect,
        );
    }

    private static function get_confirmation_general_tooltip_map() {
        return array(
            'enable_post_confirmation_flow' => self::build_help_tooltip_payload( __( 'Ativar fluxo complementar', EOP_TEXT_DOMAIN ), __( 'Liga a jornada adicional exibida depois que a proposta ja foi confirmada.', EOP_TEXT_DOMAIN ), __( 'Quando desligado, o fluxo termina na confirmacao da proposta.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_contract_title' => self::build_help_tooltip_payload( __( 'Titulo do contrato', EOP_TEXT_DOMAIN ), __( 'Titulo principal da etapa de aceite contratual.', EOP_TEXT_DOMAIN ), __( 'Aparece no topo da primeira etapa do fluxo complementar.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_contract_document_description' => self::build_help_tooltip_payload( __( 'Descricao do documento principal', EOP_TEXT_DOMAIN ), __( 'Texto curto exibido abaixo do titulo do documento principal na etapa contratual.', EOP_TEXT_DOMAIN ), __( 'Serve como contexto rapido antes da leitura e do aceite.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_contract_checkbox_label' => self::build_help_tooltip_payload( __( 'Texto do aceite', EOP_TEXT_DOMAIN ), __( 'Frase que acompanha o checkbox de aceite do contrato.', EOP_TEXT_DOMAIN ), __( 'Deixa explicito o consentimento antes do avanço.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_contract_button_label' => self::build_help_tooltip_payload( __( 'Botao do contrato', EOP_TEXT_DOMAIN ), __( 'Texto do botao que envia o aceite e libera a proxima etapa do fluxo.', EOP_TEXT_DOMAIN ), __( 'Muda a chamada para a acao principal na etapa do contrato.', EOP_TEXT_DOMAIN ) ),
            'service_products' => self::build_help_tooltip_payload( __( 'Produtos considerados servicos', EOP_TEXT_DOMAIN ), __( 'Selecione produtos que devem aparecer separados dos produtos comuns nos totalizadores.', EOP_TEXT_DOMAIN ), __( 'Tambem remove esses itens da edicao de nomes no fluxo complementar.', EOP_TEXT_DOMAIN ) ),
            'service_product_categories' => self::build_help_tooltip_payload( __( 'Categorias de produtos considerados servicos', EOP_TEXT_DOMAIN ), __( 'Selecione categorias inteiras que devem entrar na mesma regra dos servicos.', EOP_TEXT_DOMAIN ), __( 'Qualquer produto de uma categoria marcada aqui passa a ser tratado como servico.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_locked_products' => self::build_help_tooltip_payload( __( 'Produtos bloqueados', EOP_TEXT_DOMAIN ), __( 'Selecione os produtos cujo nome nao deve ser alterado na etapa final de personalizacao.', EOP_TEXT_DOMAIN ), __( 'Esses itens ficam protegidos contra edicao do nome pelo cliente.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_require_attachment' => self::build_help_tooltip_payload( __( 'Exigir anexo', EOP_TEXT_DOMAIN ), __( 'Define se o envio do anexo sera obrigatorio antes de continuar o fluxo.', EOP_TEXT_DOMAIN ), __( 'Quando ativado, o cliente precisa anexar o arquivo para seguir.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_upload_title' => self::build_help_tooltip_payload( __( 'Titulo do upload', EOP_TEXT_DOMAIN ), __( 'Titulo principal da etapa em que o cliente envia arquivos complementares.', EOP_TEXT_DOMAIN ), __( 'Aparece no topo do bloco de upload.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_upload_field_label' => self::build_help_tooltip_payload( __( 'Label do anexo', EOP_TEXT_DOMAIN ), __( 'Texto que identifica o campo de envio do arquivo nessa etapa.', EOP_TEXT_DOMAIN ), __( 'Ajuda o cliente a entender o que precisa anexar.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_upload_description' => self::build_help_tooltip_payload( __( 'Descricao do upload', EOP_TEXT_DOMAIN ), __( 'Texto explicativo que orienta o cliente sobre qual documento ou material deve enviar.', EOP_TEXT_DOMAIN ), __( 'Fica logo abaixo do titulo da etapa de upload.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_upload_button_label' => self::build_help_tooltip_payload( __( 'Botao do upload', EOP_TEXT_DOMAIN ), __( 'Texto do botao que confirma o envio do anexo e leva o cliente adiante.', EOP_TEXT_DOMAIN ), __( 'Muda a chamada da acao principal da etapa de upload.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_products_title' => self::build_help_tooltip_payload( __( 'Titulo da personalizacao', EOP_TEXT_DOMAIN ), __( 'Titulo principal da etapa em que o cliente revisa ou personaliza informacoes dos produtos.', EOP_TEXT_DOMAIN ), __( 'Fica no topo da tela de personalizacao.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_products_description' => self::build_help_tooltip_payload( __( 'Descricao da personalizacao', EOP_TEXT_DOMAIN ), __( 'Texto auxiliar que explica como o cliente deve preencher ou revisar os dados dos produtos.', EOP_TEXT_DOMAIN ), __( 'Serve como instrucao curta dessa etapa.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_products_button_label' => self::build_help_tooltip_payload( __( 'Botao da personalizacao', EOP_TEXT_DOMAIN ), __( 'Texto do botao que confirma a personalizacao dos produtos.', EOP_TEXT_DOMAIN ), __( 'Muda a chamada da acao principal da etapa de produtos.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_completion_title' => self::build_help_tooltip_payload( __( 'Titulo da conclusao', EOP_TEXT_DOMAIN ), __( 'Titulo mostrado ao final do fluxo, quando todas as etapas foram concluidas.', EOP_TEXT_DOMAIN ), __( 'Identifica visualmente que o processo terminou.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_completion_description' => self::build_help_tooltip_payload( __( 'Descricao da conclusao', EOP_TEXT_DOMAIN ), __( 'Mensagem final exibida depois que o cliente conclui contrato, anexo e personalizacao.', EOP_TEXT_DOMAIN ), __( 'Serve como fechamento e orientacao final do fluxo.', EOP_TEXT_DOMAIN ) ),
        );
    }

    private static function render_help_label( $tag, $text, $help_key, $attributes = array() ) {
        $tag        = in_array( $tag, array( 'label', 'span' ), true ) ? $tag : 'span';
        $text       = (string) $text;
        $help_key   = sanitize_key( (string) $help_key );
        $attributes = is_array( $attributes ) ? $attributes : array();
        $attr_html  = '';

        foreach ( $attributes as $attr_name => $attr_value ) {
            if ( '' === (string) $attr_name || null === $attr_value || false === $attr_value ) {
                continue;
            }

            $attr_html .= sprintf( ' %s="%s"', esc_attr( $attr_name ), esc_attr( (string) $attr_value ) );
        }

        $attr_html .= sprintf( ' data-eop-help-key="%s"', esc_attr( $help_key ) );

        printf( '<%1$s%2$s>%3$s</%1$s>', esc_attr( $tag ), $attr_html, esc_html( $text ) );
    }

    public static function get_settings_admin_localization( $has_fontselect = false ) {
		$contract_placeholders = array();

		if ( class_exists( 'EOP_Post_Confirmation_Flow' ) && method_exists( 'EOP_Post_Confirmation_Flow', 'get_contract_placeholder_tokens' ) ) {
			$contract_placeholders = array_values( EOP_Post_Confirmation_Flow::get_contract_placeholder_tokens() );
		}

        return array(
            'has_fontselect'               => (bool) $has_fontselect,
            'font_placeholder'             => __( 'Escolha uma fonte Google', EOP_TEXT_DOMAIN ),
            'media_title'                  => __( 'Selecionar logo', EOP_TEXT_DOMAIN ),
            'media_button'                 => __( 'Usar esta imagem', EOP_TEXT_DOMAIN ),
            'remove_logo'                  => __( 'Remover logo', EOP_TEXT_DOMAIN ),
            'select_logo'                  => __( 'Selecionar logo', EOP_TEXT_DOMAIN ),
            'change_logo'                  => __( 'Trocar logo', EOP_TEXT_DOMAIN ),
            'no_logo'                      => __( 'Nenhum logo selecionado ainda.', EOP_TEXT_DOMAIN ),
            'ajax_url'                     => admin_url( 'admin-ajax.php' ),
            'nonce'                        => wp_create_nonce( 'eop_nonce' ),
            'locked_placeholder'           => __( 'Busque produtos por nome ou SKU...', EOP_TEXT_DOMAIN ),
            'locked_no_results'            => __( 'Nenhum produto encontrado.', EOP_TEXT_DOMAIN ),
            'document_media_title'         => __( 'Selecionar arquivo do documento', EOP_TEXT_DOMAIN ),
            'document_media_button'        => __( 'Usar este arquivo', EOP_TEXT_DOMAIN ),
            'document_pdf_empty'           => __( 'Nenhum arquivo anexado ainda.', EOP_TEXT_DOMAIN ),
            'document_select_pdf'          => __( 'Selecionar arquivo', EOP_TEXT_DOMAIN ),
            'document_change_pdf'          => __( 'Trocar arquivo', EOP_TEXT_DOMAIN ),
            'document_default_title'       => __( 'Novo documento', EOP_TEXT_DOMAIN ),
            'document_source_editor'       => __( 'Conteúdo do documento', EOP_TEXT_DOMAIN ),
            'document_source_attachment'   => __( 'Arquivo do documento', EOP_TEXT_DOMAIN ),
            'document_edit'                => __( 'Editar', EOP_TEXT_DOMAIN ),
            'document_close'               => __( 'Fechar', EOP_TEXT_DOMAIN ),
            'document_placeholder_menu_label' => __( 'Inserir placeholder', EOP_TEXT_DOMAIN ),
            'document_placeholder_empty'   => __( 'Nenhum placeholder disponível.', EOP_TEXT_DOMAIN ),
            'document_placeholder_group_order' => __( 'Pedido e cobrança', EOP_TEXT_DOMAIN ),
            'document_placeholder_group_contract' => __( 'Contrato e aceite', EOP_TEXT_DOMAIN ),
            'document_placeholder_group_shipping' => __( 'Entrega', EOP_TEXT_DOMAIN ),
            'contract_placeholders'        => $contract_placeholders,
            'confirmation_general_help_map' => self::get_confirmation_general_tooltip_map(),
        );
    }

    private static function normalize_admin_section( $section ) {
        $section = sanitize_key( (string) $section );
        $allowed = array(
            'all',
            'general-config',
            'confirmation-flow-general',
            'confirmation-flow-documents',
            'confirmation-flow-preview',
            'confirmation-flow-upload-products-preview',
            'order-link-style',
            'proposal-link-style',
            'customer-experience',
            'texts',
        );

        return in_array( $section, $allowed, true ) ? $section : 'all';
    }

    private static function should_render_admin_section( $active_section, $section_key ) {
        return self::normalize_admin_section( $active_section ) === sanitize_key( (string) $section_key ) || 'all' === self::normalize_admin_section( $active_section );
    }

    private static function render_signature_document_item( $index, $document ) {
        $document        = is_array( $document ) ? $document : array();
        $attachment_name = ! empty( $document['attachment_id'] ) ? get_the_title( absint( $document['attachment_id'] ) ) : '';
        $title           = isset( $document['title'] ) ? (string) $document['title'] : '';
        $source_type     = isset( $document['source_type'] ) ? (string) $document['source_type'] : 'editor';
        $source_label    = 'attachment' === $source_type ? __( 'Arquivo do documento', EOP_TEXT_DOMAIN ) : __( 'Conteúdo do documento', EOP_TEXT_DOMAIN );
        ?>
        <div class="eop-signature-document is-collapsed" data-signature-document data-index="<?php echo esc_attr( $index ); ?>" data-expanded="false">
            <div class="eop-signature-document__header">
                <div class="eop-signature-document__summary">
                    <strong class="eop-signature-document__heading" data-signature-document-heading><?php echo esc_html( '' !== $title ? $title : __( 'Novo documento', EOP_TEXT_DOMAIN ) ); ?></strong>
                    <span class="eop-signature-document__type" data-signature-document-source-label><?php echo esc_html( $source_label ); ?></span>
                </div>
                <div class="eop-signature-document__actions">
                    <button type="button" class="button button-secondary eop-signature-document__edit" data-signature-document-toggle aria-expanded="false"><?php esc_html_e( 'Editar', EOP_TEXT_DOMAIN ); ?></button>
                    <button type="button" class="button-link-delete eop-signature-document__remove" data-signature-document-remove aria-label="<?php esc_attr_e( 'Excluir documento', EOP_TEXT_DOMAIN ); ?>" title="<?php esc_attr_e( 'Excluir documento', EOP_TEXT_DOMAIN ); ?>">
                        <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                        <span class="screen-reader-text"><?php esc_html_e( 'Excluir documento', EOP_TEXT_DOMAIN ); ?></span>
                    </button>
                </div>
            </div>
            <div class="eop-signature-document__body is-hidden" data-signature-document-body>
                <div class="eop-signature-document__grid">
                <input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_signature_documents][<?php echo esc_attr( $index ); ?>][key]" value="<?php echo esc_attr( $document['key'] ?? '' ); ?>" />
                <div class="eop-settings-field">
					<label><?php esc_html_e( 'Título do documento', EOP_TEXT_DOMAIN ); ?></label>
                    <input type="text" data-signature-document-title name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_signature_documents][<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $title ); ?>" />
                </div>
                <div class="eop-settings-field">
                    <label><?php esc_html_e( 'Tipo do documento', EOP_TEXT_DOMAIN ); ?></label>
                    <select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_signature_documents][<?php echo esc_attr( $index ); ?>][source_type]" data-signature-document-source>
                        <option value="editor" <?php selected( $source_type, 'editor' ); ?>><?php esc_html_e( 'Conteúdo do documento', EOP_TEXT_DOMAIN ); ?></option>
                        <option value="attachment" <?php selected( $source_type, 'attachment' ); ?>><?php esc_html_e( 'Arquivo do documento', EOP_TEXT_DOMAIN ); ?></option>
                    </select>
                </div>
                <input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_signature_documents][<?php echo esc_attr( $index ); ?>][description]" value="<?php echo esc_attr( $document['description'] ?? '' ); ?>" />
                <div class="eop-settings-field is-full eop-signature-document__panel<?php echo 'attachment' === $source_type ? '' : ' is-hidden'; ?>" data-signature-document-panel="attachment">
                    <label><?php esc_html_e( 'Arquivo do documento', EOP_TEXT_DOMAIN ); ?></label>
                    <input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_signature_documents][<?php echo esc_attr( $index ); ?>][attachment_id]" value="<?php echo esc_attr( absint( $document['attachment_id'] ?? 0 ) ); ?>" data-signature-document-attachment-id />
                    <div class="eop-signature-document__attachment-shell">
                        <div class="eop-signature-document__attachment-name" data-signature-document-attachment-name><?php echo esc_html( $attachment_name ? $attachment_name : __( 'Nenhum arquivo anexado ainda.', EOP_TEXT_DOMAIN ) ); ?></div>
                        <div class="eop-signature-document__attachment-actions">
                            <button type="button" class="button button-secondary" data-signature-document-attachment-select><?php echo esc_html( $attachment_name ? __( 'Trocar arquivo', EOP_TEXT_DOMAIN ) : __( 'Selecionar arquivo', EOP_TEXT_DOMAIN ) ); ?></button>
                            <button type="button" class="button-link-delete eop-signature-document__attachment-remove<?php echo $attachment_name ? '' : ' is-hidden'; ?>" data-signature-document-attachment-remove aria-label="<?php esc_attr_e( 'Remover arquivo', EOP_TEXT_DOMAIN ); ?>" title="<?php esc_attr_e( 'Remover arquivo', EOP_TEXT_DOMAIN ); ?>">
                                <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                <span class="screen-reader-text"><?php esc_html_e( 'Remover arquivo', EOP_TEXT_DOMAIN ); ?></span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="eop-settings-field is-full eop-signature-document__panel<?php echo 'editor' === $source_type ? '' : ' is-hidden'; ?>" data-signature-document-panel="editor">
					<label><?php esc_html_e( 'Conteúdo do documento', EOP_TEXT_DOMAIN ); ?></label>
                    <textarea id="eop_signature_document_body_<?php echo esc_attr( $index ); ?>" class="eop-signature-document__editor" data-signature-document-editor name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_signature_documents][<?php echo esc_attr( $index ); ?>][body]"><?php echo esc_textarea( $document['body'] ?? '' ); ?></textarea>
                </div>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_confirmation_documents_section( $signature_documents ) {
        $signature_documents = is_array( $signature_documents ) ? array_values( $signature_documents ) : array();
        ?>
        <section class="eop-settings-card">
            <div class="eop-signature-documents" data-signature-documents data-option-key="<?php echo esc_attr( self::OPTION_KEY ); ?>" data-next-index="<?php echo esc_attr( count( $signature_documents ) ); ?>">
                <div class="eop-signature-documents__intro">
                    <span><?php esc_html_e( 'Fluxo de Confirmação', EOP_TEXT_DOMAIN ); ?></span>
                    <h3><?php esc_html_e( 'Listagem de documentos', EOP_TEXT_DOMAIN ); ?></h3>
                    <p><?php esc_html_e( 'Cada documento aparece como um card. Escolha se ele sera escrito no editor ou enviado como arquivo.', EOP_TEXT_DOMAIN ); ?></p>
                </div>
                <button type="button" class="button eop-signature-documents__create" data-signature-document-add><?php esc_html_e( 'Cadastrar documento', EOP_TEXT_DOMAIN ); ?></button>
                <input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_signature_documents][__sentinel]" value="1" />
                <div class="eop-signature-documents__empty<?php echo ! empty( $signature_documents ) ? ' is-hidden' : ''; ?>" data-signature-documents-empty>
                    <strong><?php esc_html_e( 'Nenhum documento cadastrado ainda.', EOP_TEXT_DOMAIN ); ?></strong>
                    <p><?php esc_html_e( 'Clique em cadastrar documento para criar o primeiro item do fluxo contratual.', EOP_TEXT_DOMAIN ); ?></p>
                </div>
                <div class="eop-signature-documents__list" data-signature-documents-list>
                    <?php foreach ( $signature_documents as $index => $document ) : ?>
                        <?php self::render_signature_document_item( $index, $document ); ?>
                    <?php endforeach; ?>
                </div>
                <script type="text/template" id="eop-signature-document-template">
                    <div class="eop-signature-document is-collapsed" data-signature-document data-index="__INDEX__" data-expanded="false">
                        <div class="eop-signature-document__header">
                            <div class="eop-signature-document__summary">
                                <strong class="eop-signature-document__heading" data-signature-document-heading><?php esc_html_e( 'Novo documento', EOP_TEXT_DOMAIN ); ?></strong>
                                <span class="eop-signature-document__type" data-signature-document-source-label><?php esc_html_e( 'Conteúdo do documento', EOP_TEXT_DOMAIN ); ?></span>
                            </div>
                            <div class="eop-signature-document__actions">
                                <button type="button" class="button button-secondary eop-signature-document__edit" data-signature-document-toggle aria-expanded="false"><?php esc_html_e( 'Editar', EOP_TEXT_DOMAIN ); ?></button>
                                <button type="button" class="button-link-delete eop-signature-document__remove" data-signature-document-remove aria-label="<?php esc_attr_e( 'Excluir documento', EOP_TEXT_DOMAIN ); ?>" title="<?php esc_attr_e( 'Excluir documento', EOP_TEXT_DOMAIN ); ?>">
                                    <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                    <span class="screen-reader-text"><?php esc_html_e( 'Excluir documento', EOP_TEXT_DOMAIN ); ?></span>
                                </button>
                            </div>
                        </div>
                        <div class="eop-signature-document__body is-hidden" data-signature-document-body>
                            <div class="eop-signature-document__grid">
                            <input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_signature_documents][__INDEX__][key]" value="" />
                            <div class="eop-settings-field">
                                <label><?php esc_html_e( 'Título do documento', EOP_TEXT_DOMAIN ); ?></label>
                                <input type="text" data-signature-document-title name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_signature_documents][__INDEX__][title]" value="" />
                            </div>
                            <div class="eop-settings-field">
                                <label><?php esc_html_e( 'Tipo do documento', EOP_TEXT_DOMAIN ); ?></label>
                                <select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_signature_documents][__INDEX__][source_type]" data-signature-document-source>
                                    <option value="editor"><?php esc_html_e( 'Conteúdo do documento', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="attachment"><?php esc_html_e( 'Arquivo do documento', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_signature_documents][__INDEX__][description]" value="" />
                            <div class="eop-settings-field is-full eop-signature-document__panel is-hidden" data-signature-document-panel="attachment">
                                <label><?php esc_html_e( 'Arquivo do documento', EOP_TEXT_DOMAIN ); ?></label>
                                <input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_signature_documents][__INDEX__][attachment_id]" value="" data-signature-document-attachment-id />
                                <div class="eop-signature-document__attachment-shell">
                                    <div class="eop-signature-document__attachment-name" data-signature-document-attachment-name><?php esc_html_e( 'Nenhum arquivo anexado ainda.', EOP_TEXT_DOMAIN ); ?></div>
                                    <div class="eop-signature-document__attachment-actions">
                                        <button type="button" class="button button-secondary" data-signature-document-attachment-select><?php esc_html_e( 'Selecionar arquivo', EOP_TEXT_DOMAIN ); ?></button>
                                        <button type="button" class="button-link-delete eop-signature-document__attachment-remove is-hidden" data-signature-document-attachment-remove aria-label="<?php esc_attr_e( 'Remover arquivo', EOP_TEXT_DOMAIN ); ?>" title="<?php esc_attr_e( 'Remover arquivo', EOP_TEXT_DOMAIN ); ?>">
                                            <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                            <span class="screen-reader-text"><?php esc_html_e( 'Remover arquivo', EOP_TEXT_DOMAIN ); ?></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="eop-settings-field is-full eop-signature-document__panel" data-signature-document-panel="editor">
                                <label><?php esc_html_e( 'Conteúdo do documento', EOP_TEXT_DOMAIN ); ?></label>
                                <textarea id="eop_signature_document_body___INDEX__" class="eop-signature-document__editor" data-signature-document-editor name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_signature_documents][__INDEX__][body]"></textarea>
                            </div>
                            </div>
                        </div>
                    </div>
                </script>
                <small class="eop-settings-help"><?php esc_html_e( 'Arquivos Word sao convertidos em PDF quando o documento for gerado para o pedido.', EOP_TEXT_DOMAIN ); ?></small>
                <?php if ( class_exists( 'EOP_Post_Confirmation_Flow' ) && method_exists( 'EOP_Post_Confirmation_Flow', 'get_contract_placeholder_tokens' ) ) : ?>
                    <small class="eop-settings-help"><?php esc_html_e( 'Use o menu Inserir placeholder no editor para adicionar os dados dinamicos sem copiar manualmente.', EOP_TEXT_DOMAIN ); ?></small>
                    <small class="eop-settings-help">
                        <?php
                        printf(
                            esc_html__( 'Placeholders disponiveis: %s', EOP_TEXT_DOMAIN ),
                            esc_html( implode( ', ', EOP_Post_Confirmation_Flow::get_contract_placeholder_tokens() ) )
                        );
                        ?>
                    </small>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }

    public static function maybe_redirect_legacy_request() {
        if ( ! class_exists( 'EOP_Admin_Page' ) ) {
            return;
        }

        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $legacy_page_map = array(
            'eop-configuracoes'                => 'settings',
            'eop-pedido-expresso-configuracoes' => 'settings',
            'eop-pedido-expresso-aparencia'    => 'settings-styles',
            'eop-pedido-expresso-fluxo'        => 'settings-confirmation-flow',
        );

        if ( ! isset( $legacy_page_map[ $page ] ) ) {
            return;
        }

        wp_safe_redirect( EOP_Admin_Page::get_view_url( $legacy_page_map[ $page ] ) );
        exit;
    }

    public static function register_submenu() {
        if ( class_exists( 'EOP_Admin_Page' ) ) {
            return;
        }

        if ( function_exists( 'ensure_aireset_parent_menu' ) ) {
            ensure_aireset_parent_menu();
        }

        self::$page_hook = add_submenu_page(
            'aireset',
            __( 'Configuracoes', EOP_TEXT_DOMAIN ),
            __( 'Configuracoes', EOP_TEXT_DOMAIN ),
            'manage_options',
            'eop-configuracoes',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function enqueue_assets( $hook ) {
        if ( class_exists( 'EOP_Admin_Page' ) ) {
            return;
        }

        if ( self::$page_hook !== $hook ) {
            return;
        }

        if ( ! function_exists( 'WC' ) || ! WC() ) {
            return;
        }

        $font_url = self::get_font_stylesheet_url();

        if ( $font_url ) {
            wp_enqueue_style( 'eop-settings-selected-font', $font_url, array(), null );
        }

        $wc_version   = defined( 'WC_VERSION' ) ? WC_VERSION : EOP_VERSION;
        $font_css_path = EOP_PLUGIN_DIR . 'assets/css/jquery.fontselect.css';
        $font_js_path  = EOP_PLUGIN_DIR . 'assets/js/jquery.fontselect.js';

        wp_enqueue_style( 'select2', WC()->plugin_url() . '/assets/css/select2.css', array(), $wc_version );
        wp_enqueue_script( 'select2', WC()->plugin_url() . '/assets/js/select2/select2.full.min.js', array( 'jquery' ), $wc_version, true );
        wp_enqueue_style( 'eop-admin', EOP_PLUGIN_URL . 'assets/css/admin.css', array( 'select2' ), EOP_VERSION );
        wp_enqueue_style( 'eop-frontend', EOP_PLUGIN_URL . 'assets/css/frontend.css', array(), EOP_VERSION );

        wp_enqueue_media();

        if ( function_exists( 'wp_enqueue_editor' ) ) {
            wp_enqueue_editor();
        }

        wp_enqueue_style( 'eop-coloris', EOP_PLUGIN_URL . 'assets/css/coloris.min.css', array(), EOP_VERSION );
        wp_enqueue_style(
            'eop-settings-admin',
            EOP_PLUGIN_URL . 'assets/css/settings-admin.css',
            array_filter( array( 'eop-admin', 'eop-coloris', wp_style_is( 'select2', 'registered' ) ? 'select2' : '' ) ),
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
            array_filter( array( 'jquery', 'eop-coloris', 'media-editor', 'media-upload', 'wp-editor', wp_script_is( 'select2', 'registered' ) ? 'select2' : '', file_exists( $font_js_path ) ? 'eop-fontselect' : '' ) ),
            EOP_VERSION,
            true
        );

        wp_localize_script(
            'eop-settings-admin',
            'eop_settings_vars',
            self::get_settings_admin_localization( file_exists( $font_js_path ) )
        );
    }

    public static function filter_admin_body_class( $classes ) {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

        if ( ! in_array( $page, array( 'eop-configuracoes', 'eop-pedido-expresso-configuracoes', 'eop-pedido-expresso-aparencia', 'eop-pedido-expresso-fluxo' ), true ) ) {
            return $classes;
        }

        return trim( $classes . ' eop-settings-screen' );
    }

    private static function render_standalone_page() {
        ?>
        <div class="wrap">
            <?php self::render_embedded_page( 'all' ); ?>
        </div>
        <?php
    }

    public static function render_page() {
        if ( class_exists( 'EOP_Admin_Page' ) ) {
            wp_safe_redirect( EOP_Admin_Page::get_view_url( 'settings' ) );
            exit;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
        }

        self::render_standalone_page();
    }

    public static function render_embedded_page( $section = 'all' ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Acesso negado.', EOP_TEXT_DOMAIN ) );
        }

        $section                        = self::normalize_admin_section( $section );
        $settings                       = self::get_all();
        $should_render_general_config   = self::should_render_admin_section( $section, 'general-config' );
        $should_render_confirmation_general = self::should_render_admin_section( $section, 'confirmation-flow-general' );
        $should_render_confirmation_documents = self::should_render_admin_section( $section, 'confirmation-flow-documents' );
        $should_render_confirmation_preview = self::should_render_admin_section( $section, 'confirmation-flow-preview' );
        $should_render_confirmation_upload_products_preview = self::should_render_admin_section( $section, 'confirmation-flow-upload-products-preview' );
        $pages                          = $should_render_general_config ? get_pages() : array();
        $service_selector               = $should_render_general_config ? self::get_service_product_selector_state( $settings ) : array(
            'options'          => array(),
            'missing_tokens'   => array(),
            'serialized_value' => '',
        );
        $service_category_selector      = $should_render_general_config ? self::get_service_product_category_selector_state( $settings ) : array(
            'options'          => array(),
            'missing_tokens'   => array(),
            'serialized_value' => '',
        );
        $locked_selector                = $should_render_confirmation_general ? self::get_post_confirmation_locked_product_selector_state( $settings ) : array(
            'options'          => array(),
            'missing_tokens'   => array(),
            'serialized_value' => '',
        );
        $signature_documents            = ( $should_render_confirmation_general || $should_render_confirmation_documents || $should_render_confirmation_preview ) ? self::get_post_confirmation_contract_documents( $settings ) : array();
        $confirmation_preview           = $should_render_confirmation_preview ? self::get_confirmation_flow_preview_data( $settings ) : array();
        ?>
        <div class="eop-settings-page eop-settings-page--embedded">
            <form method="post" action="options.php" class="eop-settings-form">
                <?php EOP_Admin_Page::render_option_form_fields( 'eop_settings_group' ); ?>
                <?php if ( isset( $_GET['settings-updated'] ) && 'true' === wp_unslash( $_GET['settings-updated'] ) ) : ?>
                    <div class="eop-settings-feedback eop-settings-feedback--success">
                        <?php esc_html_e( 'Configuracoes salvas com sucesso.', EOP_TEXT_DOMAIN ); ?>
                    </div>
                <?php endif; ?>
                <div class="eop-settings-sections">
                        <?php if ( self::should_render_admin_section( $section, 'general-config' ) ) : ?>
                        <section class="eop-settings-card">
                            <h2><?php esc_html_e( 'Fluxo', EOP_TEXT_DOMAIN ); ?></h2>
                            <p><?php esc_html_e( 'Defina como a equipe comercial trabalha hoje e como o cliente recebe a proposta.', EOP_TEXT_DOMAIN ); ?></p>
                            <div class="eop-settings-grid">
                                <div class="eop-settings-field">
                                    <label for="eop_flow_mode"><?php esc_html_e( 'Modo do fluxo', EOP_TEXT_DOMAIN ); ?></label>
                                    <select id="eop_flow_mode" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[flow_mode]">
                                        <option value="proposal" <?php selected( $settings['flow_mode'], 'proposal' ); ?>><?php esc_html_e( 'Proposta publica', EOP_TEXT_DOMAIN ); ?></option>
                                        <option value="direct_order" <?php selected( $settings['flow_mode'], 'direct_order' ); ?>><?php esc_html_e( 'Pedido direto', EOP_TEXT_DOMAIN ); ?></option>
                                    </select>
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_discount_mode"><?php esc_html_e( 'Modo do desconto', EOP_TEXT_DOMAIN ); ?></label>
                                    <select id="eop_discount_mode" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[discount_mode]">
                                        <option value="both" <?php selected( $settings['discount_mode'], 'both' ); ?>><?php esc_html_e( 'Porcentagem e valor fixo', EOP_TEXT_DOMAIN ); ?></option>
                                        <option value="percent" <?php selected( $settings['discount_mode'], 'percent' ); ?>><?php esc_html_e( 'Somente porcentagem (%)', EOP_TEXT_DOMAIN ); ?></option>
                                        <option value="fixed" <?php selected( $settings['discount_mode'], 'fixed' ); ?>><?php esc_html_e( 'Somente valor fixo (R$)', EOP_TEXT_DOMAIN ); ?></option>
                                    </select>
                                    <small class="eop-settings-help"><?php esc_html_e( 'Define se o campo de desconto aceita porcentagem, valor fixo ou ambos.', EOP_TEXT_DOMAIN ); ?></small>
                                </div>
                                <div class="eop-settings-field">
                                    <span><?php esc_html_e( 'Liberar pagamento apos confirmacao', EOP_TEXT_DOMAIN ); ?></span>
                                    <div class="eop-settings-switch-shell">
                                        <input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_checkout_confirmation]" value="<?php echo esc_attr( $settings['enable_checkout_confirmation'] ); ?>" />
                                        <button
                                            type="button"
                                            class="eop-settings-switcher<?php echo 'yes' === $settings['enable_checkout_confirmation'] ? ' is-enabled' : ''; ?>"
                                            role="switch"
                                            aria-checked="<?php echo 'yes' === $settings['enable_checkout_confirmation'] ? 'true' : 'false'; ?>"
                                            data-target-name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_checkout_confirmation]"
                                            data-enabled-value="yes"
                                            data-disabled-value="no"
                                            aria-label="<?php esc_attr_e( 'Alternar pagamento apos confirmacao', EOP_TEXT_DOMAIN ); ?>"
                                        >
                                            <span class="eop-settings-switcher__label eop-settings-switcher__label--off">Off</span>
                                            <span class="eop-settings-switcher__thumb" aria-hidden="true"></span>
                                            <span class="eop-settings-switcher__label eop-settings-switcher__label--on">On</span>
                                        </button>
                                        <span class="eop-settings-switcher__status" aria-live="polite">
                                            <?php echo 'yes' === $settings['enable_checkout_confirmation'] ? esc_html__( 'Ativado', EOP_TEXT_DOMAIN ) : esc_html__( 'Desativado', EOP_TEXT_DOMAIN ); ?>
                                        </span>
                                    </div>
                                    <small class="eop-settings-help"><?php esc_html_e( 'Mostra o botao de pagar apenas depois que o cliente confirmar a proposta.', EOP_TEXT_DOMAIN ); ?></small>
                                </div>
                                <div class="eop-settings-field is-full">
                                    <?php self::render_help_label( 'label', __( 'Produtos considerados servicos', EOP_TEXT_DOMAIN ), 'service_products', array( 'for' => 'eop_service_products_selector' ) ); ?>
                                    <input id="eop_service_products" type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[service_products]" value="<?php echo esc_attr( $service_selector['serialized_value'] ); ?>" />
                                    <select
                                        id="eop_service_products_selector"
                                        class="eop-settings-product-selector"
                                        data-target-input="#eop_service_products"
                                        data-search-action="eop_search_products"
                                        data-placeholder="<?php echo esc_attr__( 'Busque produtos por nome ou SKU...', EOP_TEXT_DOMAIN ); ?>"
                                        data-no-results="<?php echo esc_attr__( 'Nenhum produto encontrado.', EOP_TEXT_DOMAIN ); ?>"
                                        data-minimum-input-length="3"
                                        multiple
                                    >
                                        <?php foreach ( $service_selector['options'] as $option ) : ?>
                                            <option value="<?php echo esc_attr( $option['id'] ); ?>" selected="selected"><?php echo esc_html( $option['text'] ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="eop-settings-help"><?php esc_html_e( 'Esses itens aparecem em uma linha Servicos antes do total e nao entram na edicao do fluxo complementar.', EOP_TEXT_DOMAIN ); ?></small>
                                    <?php if ( ! empty( $service_selector['missing_tokens'] ) ) : ?>
                                        <small class="eop-settings-help"><?php echo esc_html( sprintf( __( 'Tokens antigos preservados ate a proxima atualizacao desta lista: %s', EOP_TEXT_DOMAIN ), implode( ', ', $service_selector['missing_tokens'] ) ) ); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="eop-settings-field is-full">
                                    <?php self::render_help_label( 'label', __( 'Categorias de produtos considerados servicos', EOP_TEXT_DOMAIN ), 'service_product_categories', array( 'for' => 'eop_service_product_categories_selector' ) ); ?>
                                    <input id="eop_service_product_categories" type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[service_product_categories]" value="<?php echo esc_attr( $service_category_selector['serialized_value'] ); ?>" />
                                    <select
                                        id="eop_service_product_categories_selector"
                                        class="eop-settings-category-selector"
                                        data-target-input="#eop_service_product_categories"
                                        data-search-action="eop_search_product_categories"
                                        data-placeholder="<?php echo esc_attr__( 'Busque categorias de produto...', EOP_TEXT_DOMAIN ); ?>"
                                        data-no-results="<?php echo esc_attr__( 'Nenhuma categoria encontrada.', EOP_TEXT_DOMAIN ); ?>"
                                        data-minimum-input-length="1"
                                        multiple
                                    >
                                        <?php foreach ( $service_category_selector['options'] as $option ) : ?>
                                            <option value="<?php echo esc_attr( $option['id'] ); ?>" selected="selected"><?php echo esc_html( $option['text'] ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="eop-settings-help"><?php esc_html_e( 'Essas categorias fazem qualquer produto delas entrar no grupo de servicos nos totalizadores e no fluxo complementar.', EOP_TEXT_DOMAIN ); ?></small>
                                    <?php if ( ! empty( $service_category_selector['missing_tokens'] ) ) : ?>
                                        <small class="eop-settings-help"><?php echo esc_html( sprintf( __( 'Tokens antigos preservados ate a proxima atualizacao desta lista: %s', EOP_TEXT_DOMAIN ), implode( ', ', $service_category_selector['missing_tokens'] ) ) ); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="eop-settings-field is-full">
                                    <label for="eop_order_page"><?php esc_html_e( 'Pagina do pedido', EOP_TEXT_DOMAIN ); ?></label>
                                    <select id="eop_order_page" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[order_page_id]">
                                        <option value="0"><?php esc_html_e( 'Selecione uma pagina', EOP_TEXT_DOMAIN ); ?></option>
                                        <?php foreach ( $pages as $page ) : ?>
                                            <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( (int) $settings['order_page_id'], (int) $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="eop-settings-help"><?php esc_html_e( 'Pagina usada para o shortcode [expresso_order]. O plugin cria essa pagina automaticamente na ativacao.', EOP_TEXT_DOMAIN ); ?></small>
                                </div>
                                <div class="eop-settings-field is-full">
                                    <label for="eop_proposal_page"><?php esc_html_e( 'Pagina da proposta', EOP_TEXT_DOMAIN ); ?></label>
                                    <select id="eop_proposal_page" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_page_id]">
                                        <option value="0"><?php esc_html_e( 'Selecione uma pagina', EOP_TEXT_DOMAIN ); ?></option>
                                        <?php foreach ( $pages as $page ) : ?>
                                            <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( (int) $settings['proposal_page_id'], (int) $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="eop-settings-help"><?php esc_html_e( 'Pagina publica do shortcode [expresso_order_proposal]. O plugin tambem repara esse vinculo automaticamente.', EOP_TEXT_DOMAIN ); ?></small>
                                </div>
                            </div>
                        </section>

                        <section class="eop-settings-card">
                            <h2><?php esc_html_e( 'PDF nativo', EOP_TEXT_DOMAIN ); ?></h2>
                            <p><?php esc_html_e( 'Defina os dados exibidos pelo gerador interno de PDF do plugin.', EOP_TEXT_DOMAIN ); ?></p>
                            <div class="eop-settings-grid">
                                <div class="eop-settings-field">
                                    <label for="eop_pdf_company_name"><?php esc_html_e( 'Nome da empresa', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_pdf_company_name" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pdf_company_name]" value="<?php echo esc_attr( $settings['pdf_company_name'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_pdf_company_document"><?php esc_html_e( 'Documento da empresa', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_pdf_company_document" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pdf_company_document]" value="<?php echo esc_attr( $settings['pdf_company_document'] ); ?>" />
                                </div>
                                <div class="eop-settings-field is-full">
                                    <label for="eop_pdf_company_address"><?php esc_html_e( 'Endereco da empresa', EOP_TEXT_DOMAIN ); ?></label>
                                    <textarea id="eop_pdf_company_address" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pdf_company_address]"><?php echo esc_textarea( $settings['pdf_company_address'] ); ?></textarea>
                                </div>
                                <div class="eop-settings-field is-full">
                                    <label for="eop_pdf_footer_note"><?php esc_html_e( 'Rodape do documento', EOP_TEXT_DOMAIN ); ?></label>
                                    <textarea id="eop_pdf_footer_note" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pdf_footer_note]"><?php echo esc_textarea( $settings['pdf_footer_note'] ); ?></textarea>
                                </div>
                            </div>
                        </section>

                        <?php endif; ?>

                        <?php if ( $should_render_confirmation_general ) : ?>
                        <section class="eop-settings-card">
                            <h2><?php esc_html_e( 'Fluxo complementar apos a proposta', EOP_TEXT_DOMAIN ); ?></h2>
                            <p><?php esc_html_e( 'Centralize aqui as regras gerais, os textos principais e o comportamento do fluxo depois que a proposta ja foi aprovada.', EOP_TEXT_DOMAIN ); ?></p>
                            <div class="eop-settings-grid">
                                <div class="eop-settings-field is-full">
                                    <?php self::render_help_label( 'span', __( 'Ativar fluxo complementar', EOP_TEXT_DOMAIN ), 'enable_post_confirmation_flow' ); ?>
                                    <div class="eop-settings-switch-shell">
                                        <input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_post_confirmation_flow]" value="<?php echo esc_attr( $settings['enable_post_confirmation_flow'] ); ?>" />
                                        <button
                                            type="button"
                                            class="eop-settings-switcher<?php echo 'yes' === $settings['enable_post_confirmation_flow'] ? ' is-enabled' : ''; ?>"
                                            role="switch"
                                            aria-checked="<?php echo 'yes' === $settings['enable_post_confirmation_flow'] ? 'true' : 'false'; ?>"
                                            data-target-name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_post_confirmation_flow]"
                                            data-enabled-value="yes"
                                            data-disabled-value="no"
                                            aria-label="<?php esc_attr_e( 'Alternar fluxo complementar', EOP_TEXT_DOMAIN ); ?>"
                                        >
                                            <span class="eop-settings-switcher__label eop-settings-switcher__label--off">Off</span>
                                            <span class="eop-settings-switcher__thumb" aria-hidden="true"></span>
                                            <span class="eop-settings-switcher__label eop-settings-switcher__label--on">On</span>
                                        </button>
                                        <span class="eop-settings-switcher__status" aria-live="polite">
                                            <?php echo 'yes' === $settings['enable_post_confirmation_flow'] ? esc_html__( 'Ativado', EOP_TEXT_DOMAIN ) : esc_html__( 'Desativado', EOP_TEXT_DOMAIN ); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="eop-settings-field">
                                    <?php self::render_help_label( 'label', __( 'Produtos bloqueados', EOP_TEXT_DOMAIN ), 'post_confirmation_locked_products', array( 'for' => 'eop_post_confirmation_locked_products_selector' ) ); ?>
                                    <input id="eop_post_confirmation_locked_products" type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_locked_products]" value="<?php echo esc_attr( $locked_selector['serialized_value'] ); ?>" />
                                    <select id="eop_post_confirmation_locked_products_selector" class="eop-settings-product-selector" data-target-input="#eop_post_confirmation_locked_products" multiple>
                                        <?php foreach ( $locked_selector['options'] as $option ) : ?>
                                            <option value="<?php echo esc_attr( $option['id'] ); ?>" selected="selected"><?php echo esc_html( $option['text'] ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="eop-settings-help"><?php esc_html_e( 'Busque produtos por nome ou SKU para bloquear a alteracao do nome na etapa final.', EOP_TEXT_DOMAIN ); ?></small>
                                    <?php if ( ! empty( $locked_selector['missing_tokens'] ) ) : ?>
                                        <small class="eop-settings-help"><?php echo esc_html( sprintf( __( 'Tokens antigos preservados ate a proxima atualizacao desta lista: %s', EOP_TEXT_DOMAIN ), implode( ', ', $locked_selector['missing_tokens'] ) ) ); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="eop-settings-field is-full">
                                    <?php self::render_help_label( 'span', __( 'Exigir anexo', EOP_TEXT_DOMAIN ), 'post_confirmation_require_attachment' ); ?>
                                    <div class="eop-settings-switch-shell">
                                        <input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_require_attachment]" value="<?php echo esc_attr( $settings['post_confirmation_require_attachment'] ); ?>" />
                                        <button
                                            type="button"
                                            class="eop-settings-switcher<?php echo 'yes' === $settings['post_confirmation_require_attachment'] ? ' is-enabled' : ''; ?>"
                                            role="switch"
                                            aria-checked="<?php echo 'yes' === $settings['post_confirmation_require_attachment'] ? 'true' : 'false'; ?>"
                                            data-target-name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_require_attachment]"
                                            data-enabled-value="yes"
                                            data-disabled-value="no"
                                            aria-label="<?php esc_attr_e( 'Alternar anexo obrigatorio', EOP_TEXT_DOMAIN ); ?>"
                                        >
                                            <span class="eop-settings-switcher__label eop-settings-switcher__label--off">Off</span>
                                            <span class="eop-settings-switcher__thumb" aria-hidden="true"></span>
                                            <span class="eop-settings-switcher__label eop-settings-switcher__label--on">On</span>
                                        </button>
                                        <span class="eop-settings-switcher__status" aria-live="polite">
                                            <?php echo 'yes' === $settings['post_confirmation_require_attachment'] ? esc_html__( 'Ativado', EOP_TEXT_DOMAIN ) : esc_html__( 'Desativado', EOP_TEXT_DOMAIN ); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="eop-settings-field">
                                    <?php self::render_help_label( 'label', __( 'Titulo do upload', EOP_TEXT_DOMAIN ), 'post_confirmation_upload_title', array( 'for' => 'eop_post_confirmation_upload_title' ) ); ?>
                                    <input id="eop_post_confirmation_upload_title" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_upload_title]" value="<?php echo esc_attr( $settings['post_confirmation_upload_title'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <?php self::render_help_label( 'label', __( 'Label do anexo', EOP_TEXT_DOMAIN ), 'post_confirmation_upload_field_label', array( 'for' => 'eop_post_confirmation_upload_field_label' ) ); ?>
                                    <input id="eop_post_confirmation_upload_field_label" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_upload_field_label]" value="<?php echo esc_attr( $settings['post_confirmation_upload_field_label'] ); ?>" />
                                </div>
                                <div class="eop-settings-field is-full">
                                    <?php self::render_help_label( 'label', __( 'Descricao do upload', EOP_TEXT_DOMAIN ), 'post_confirmation_upload_description', array( 'for' => 'eop_post_confirmation_upload_description' ) ); ?>
                                    <textarea id="eop_post_confirmation_upload_description" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_upload_description]"><?php echo esc_textarea( $settings['post_confirmation_upload_description'] ); ?></textarea>
                                </div>
                                <div class="eop-settings-field">
                                    <?php self::render_help_label( 'label', __( 'Botao do upload', EOP_TEXT_DOMAIN ), 'post_confirmation_upload_button_label', array( 'for' => 'eop_post_confirmation_upload_button_label' ) ); ?>
                                    <input id="eop_post_confirmation_upload_button_label" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_upload_button_label]" value="<?php echo esc_attr( $settings['post_confirmation_upload_button_label'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <?php self::render_help_label( 'label', __( 'Titulo da personalizacao', EOP_TEXT_DOMAIN ), 'post_confirmation_products_title', array( 'for' => 'eop_post_confirmation_products_title' ) ); ?>
                                    <input id="eop_post_confirmation_products_title" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_products_title]" value="<?php echo esc_attr( $settings['post_confirmation_products_title'] ); ?>" />
                                </div>
                                <div class="eop-settings-field is-full">
                                    <?php self::render_help_label( 'label', __( 'Descricao da personalizacao', EOP_TEXT_DOMAIN ), 'post_confirmation_products_description', array( 'for' => 'eop_post_confirmation_products_description' ) ); ?>
                                    <textarea id="eop_post_confirmation_products_description" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_products_description]"><?php echo esc_textarea( $settings['post_confirmation_products_description'] ); ?></textarea>
                                </div>
                                <div class="eop-settings-field">
                                    <?php self::render_help_label( 'label', __( 'Botao da personalizacao', EOP_TEXT_DOMAIN ), 'post_confirmation_products_button_label', array( 'for' => 'eop_post_confirmation_products_button_label' ) ); ?>
                                    <input id="eop_post_confirmation_products_button_label" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_products_button_label]" value="<?php echo esc_attr( $settings['post_confirmation_products_button_label'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <?php self::render_help_label( 'label', __( 'Titulo da conclusao', EOP_TEXT_DOMAIN ), 'post_confirmation_completion_title', array( 'for' => 'eop_post_confirmation_completion_title' ) ); ?>
                                    <input id="eop_post_confirmation_completion_title" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_completion_title]" value="<?php echo esc_attr( $settings['post_confirmation_completion_title'] ); ?>" />
                                </div>
                                <div class="eop-settings-field is-full">
                                    <?php self::render_help_label( 'label', __( 'Descricao da conclusao', EOP_TEXT_DOMAIN ), 'post_confirmation_completion_description', array( 'for' => 'eop_post_confirmation_completion_description' ) ); ?>
                                    <textarea id="eop_post_confirmation_completion_description" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_completion_description]"><?php echo esc_textarea( $settings['post_confirmation_completion_description'] ); ?></textarea>
                                </div>
                            </div>
                        </section>
                        <?php endif; ?>

                        <?php if ( $should_render_confirmation_documents ) : ?>
                            <?php self::render_confirmation_documents_section( $signature_documents ); ?>
                        <?php endif; ?>

                        <?php if ( $should_render_confirmation_preview ) : ?>
                        <section class="eop-settings-card eop-contract-preview-settings">
                            <h2><?php esc_html_e( 'Visual da pagina contratual', EOP_TEXT_DOMAIN ); ?></h2>
                            <p><?php esc_html_e( 'Ajuste o visual da etapa de aceite e veja como a pagina publica vai ficar antes de publicar.', EOP_TEXT_DOMAIN ); ?></p>
                            <div class="eop-settings-grid">
                                <div class="eop-settings-field is-full">
                                    <label for="eop_post_confirmation_contract_title_visual"><?php esc_html_e( 'Titulo do contrato', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_post_confirmation_contract_title_visual" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_contract_title]" value="<?php echo esc_attr( $settings['post_confirmation_contract_title'] ); ?>" />
                                </div>
                                <div class="eop-settings-field is-full">
                                    <label for="eop_post_confirmation_contract_document_description_visual"><?php esc_html_e( 'Descricao do documento principal', EOP_TEXT_DOMAIN ); ?></label>
                                    <textarea id="eop_post_confirmation_contract_document_description_visual" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_contract_document_description]"><?php echo esc_textarea( $settings['post_confirmation_contract_document_description'] ); ?></textarea>
                                </div>
                                <div class="eop-settings-field is-full">
                                    <label for="eop_post_confirmation_contract_checkbox_label_visual"><?php esc_html_e( 'Texto do aceite', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_post_confirmation_contract_checkbox_label_visual" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_contract_checkbox_label]" value="<?php echo esc_attr( $settings['post_confirmation_contract_checkbox_label'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_post_confirmation_contract_button_label_visual"><?php esc_html_e( 'Botao principal', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_post_confirmation_contract_button_label_visual" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_contract_button_label]" value="<?php echo esc_attr( $settings['post_confirmation_contract_button_label'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_financial_title_visual"><?php esc_html_e( 'Titulo do resumo lateral', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_financial_title_visual" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_financial_title]" value="<?php echo esc_attr( $settings['customer_experience_financial_title'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_total_label_visual"><?php esc_html_e( 'Label do valor aprovado', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_total_label_visual" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_total_label]" value="<?php echo esc_attr( $settings['customer_experience_total_label'] ); ?>" />
                                </div>
                                <div class="eop-settings-field is-full">
                                    <label for="eop_customer_experience_progress_note_visual"><?php esc_html_e( 'Texto final do resumo', EOP_TEXT_DOMAIN ); ?></label>
                                    <textarea id="eop_customer_experience_progress_note_visual" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_progress_note]"><?php echo esc_textarea( $settings['customer_experience_progress_note'] ); ?></textarea>
                                </div>
                                <div class="eop-settings-field is-full">
                                    <label for="eop_customer_experience_font_family_preview"><?php esc_html_e( 'Fonte da experiencia publica', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_font_family_preview" class="select_font eop-font-field" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_font_family]" value="<?php echo esc_attr( $settings['customer_experience_font_family'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_background_mode_preview"><?php esc_html_e( 'Tipo do fundo da pagina', EOP_TEXT_DOMAIN ); ?></label>
                                    <select id="eop_customer_experience_background_mode_preview" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_background_mode]">
                                        <option value="solid"<?php selected( $settings['customer_experience_background_mode'], 'solid' ); ?>><?php esc_html_e( 'Cor unica', EOP_TEXT_DOMAIN ); ?></option>
                                        <option value="gradient"<?php selected( $settings['customer_experience_background_mode'], 'gradient' ); ?>><?php esc_html_e( 'Gradiente', EOP_TEXT_DOMAIN ); ?></option>
                                    </select>
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_title_size_preview"><?php esc_html_e( 'Tamanho do titulo principal (px)', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_title_size_preview" type="number" min="24" max="76" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_title_size]" value="<?php echo esc_attr( $settings['customer_experience_title_size'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_text_size_preview"><?php esc_html_e( 'Tamanho do texto base (px)', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_text_size_preview" type="number" min="13" max="24" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_text_size]" value="<?php echo esc_attr( $settings['customer_experience_text_size'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_background_color_preview"><?php esc_html_e( 'Fundo da pagina', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_background_color_preview" class="eop-color-field" type="text" data-default-color="#edf2fb" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_background_color]" value="<?php echo esc_attr( $settings['customer_experience_background_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_background_secondary_color_preview"><?php esc_html_e( 'Segunda cor do fundo da pagina', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_background_secondary_color_preview" class="eop-color-field" type="text" data-default-color="#f7f9fc" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_background_secondary_color]" value="<?php echo esc_attr( $settings['customer_experience_background_secondary_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_hero_background_mode_preview"><?php esc_html_e( 'Tipo do fundo do topo', EOP_TEXT_DOMAIN ); ?></label>
                                    <select id="eop_customer_experience_hero_background_mode_preview" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_hero_background_mode]">
                                        <option value="solid"<?php selected( $settings['customer_experience_hero_background_mode'], 'solid' ); ?>><?php esc_html_e( 'Cor unica', EOP_TEXT_DOMAIN ); ?></option>
                                        <option value="gradient"<?php selected( $settings['customer_experience_hero_background_mode'], 'gradient' ); ?>><?php esc_html_e( 'Gradiente', EOP_TEXT_DOMAIN ); ?></option>
                                    </select>
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_hero_background_color_preview"><?php esc_html_e( 'Fundo do topo', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_hero_background_color_preview" class="eop-color-field" type="text" data-default-color="#0f1b35" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_hero_background_color]" value="<?php echo esc_attr( $settings['customer_experience_hero_background_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_hero_background_secondary_color_preview"><?php esc_html_e( 'Segunda cor do topo', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_hero_background_secondary_color_preview" class="eop-color-field" type="text" data-default-color="#243553" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_hero_background_secondary_color]" value="<?php echo esc_attr( $settings['customer_experience_hero_background_secondary_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_panel_background_mode_preview"><?php esc_html_e( 'Tipo do fundo dos cards', EOP_TEXT_DOMAIN ); ?></label>
                                    <select id="eop_customer_experience_panel_background_mode_preview" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_panel_background_mode]">
                                        <option value="solid"<?php selected( $settings['customer_experience_panel_background_mode'], 'solid' ); ?>><?php esc_html_e( 'Cor unica', EOP_TEXT_DOMAIN ); ?></option>
                                        <option value="gradient"<?php selected( $settings['customer_experience_panel_background_mode'], 'gradient' ); ?>><?php esc_html_e( 'Gradiente', EOP_TEXT_DOMAIN ); ?></option>
                                    </select>
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_panel_background_color_preview"><?php esc_html_e( 'Fundo dos cards', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_panel_background_color_preview" class="eop-color-field" type="text" data-default-color="#ffffff" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_panel_background_color]" value="<?php echo esc_attr( $settings['customer_experience_panel_background_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_panel_background_secondary_color_preview"><?php esc_html_e( 'Segunda cor dos cards', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_panel_background_secondary_color_preview" class="eop-color-field" type="text" data-default-color="#f7f9fc" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_panel_background_secondary_color]" value="<?php echo esc_attr( $settings['customer_experience_panel_background_secondary_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_sidebar_background_mode_preview"><?php esc_html_e( 'Tipo do fundo lateral', EOP_TEXT_DOMAIN ); ?></label>
                                    <select id="eop_customer_experience_sidebar_background_mode_preview" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_sidebar_background_mode]">
                                        <option value="solid"<?php selected( $settings['customer_experience_sidebar_background_mode'], 'solid' ); ?>><?php esc_html_e( 'Cor unica', EOP_TEXT_DOMAIN ); ?></option>
                                        <option value="gradient"<?php selected( $settings['customer_experience_sidebar_background_mode'], 'gradient' ); ?>><?php esc_html_e( 'Gradiente', EOP_TEXT_DOMAIN ); ?></option>
                                    </select>
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_sidebar_background_color_preview"><?php esc_html_e( 'Fundo do resumo lateral', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_sidebar_background_color_preview" class="eop-color-field" type="text" data-default-color="#f6f8fc" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_sidebar_background_color]" value="<?php echo esc_attr( $settings['customer_experience_sidebar_background_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_sidebar_background_secondary_color_preview"><?php esc_html_e( 'Segunda cor do fundo lateral', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_sidebar_background_secondary_color_preview" class="eop-color-field" type="text" data-default-color="#ffffff" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_sidebar_background_secondary_color]" value="<?php echo esc_attr( $settings['customer_experience_sidebar_background_secondary_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_accent_color_preview"><?php esc_html_e( 'Cor de destaque', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_accent_color_preview" class="eop-color-field" type="text" data-default-color="#d78a2f" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_accent_color]" value="<?php echo esc_attr( $settings['customer_experience_accent_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_text_color_preview"><?php esc_html_e( 'Texto principal', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_text_color_preview" class="eop-color-field" type="text" data-default-color="#16243a" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_text_color]" value="<?php echo esc_attr( $settings['customer_experience_text_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_muted_color_preview"><?php esc_html_e( 'Texto auxiliar', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_muted_color_preview" class="eop-color-field" type="text" data-default-color="#66768d" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_muted_color]" value="<?php echo esc_attr( $settings['customer_experience_muted_color'] ); ?>" />
                                </div>
                            </div>
                        </section>

                        <section class="eop-settings-card eop-contract-preview-card">
                            <h2><?php esc_html_e( 'Preview da etapa contratual', EOP_TEXT_DOMAIN ); ?></h2>
                            <p><?php esc_html_e( 'Leitura visual da pagina publica com o documento principal, o aceite e o resumo lateral.', EOP_TEXT_DOMAIN ); ?></p>
                            <?php if ( class_exists( 'EOP_Post_Confirmation_Flow' ) && method_exists( 'EOP_Post_Confirmation_Flow', 'render_admin_contract_preview_markup' ) ) : ?>
                                <?php echo EOP_Post_Confirmation_Flow::render_admin_contract_preview_markup( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php endif; ?>
                        </section>
                        <?php endif; ?>

                        <?php if ( $should_render_confirmation_upload_products_preview ) : ?>
                        <section class="eop-settings-card eop-contract-preview-settings">
                            <h2><?php esc_html_e( 'Visual da pagina de upload e produtos', EOP_TEXT_DOMAIN ); ?></h2>
                            <p><?php esc_html_e( 'Ajuste os textos e o visual da etapa final em que o cliente envia o arquivo e personaliza os produtos.', EOP_TEXT_DOMAIN ); ?></p>
                            <?php self::render_post_confirmation_upload_products_visual_editor( $settings ); ?>
                        </section>

                        <section class="eop-settings-card eop-contract-preview-card">
                            <h2><?php esc_html_e( 'Preview da etapa de upload e produtos', EOP_TEXT_DOMAIN ); ?></h2>
                            <p><?php esc_html_e( 'Leitura visual da pagina publica com upload do arquivo, anexo salvo e personalizacao dos produtos.', EOP_TEXT_DOMAIN ); ?></p>
                            <?php if ( class_exists( 'EOP_Post_Confirmation_Flow' ) && method_exists( 'EOP_Post_Confirmation_Flow', 'render_admin_upload_products_preview_markup' ) ) : ?>
                                <?php echo EOP_Post_Confirmation_Flow::render_admin_upload_products_preview_markup( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php endif; ?>
                        </section>
                        <?php endif; ?>

                        <?php if ( self::should_render_admin_section( $section, 'order-link-style' ) ) : ?>
                        <section class="eop-settings-card eop-proposal-preview-settings">
                            <h2><?php esc_html_e( 'Visual do Link do Pedido', EOP_TEXT_DOMAIN ); ?></h2>
                            <p><?php esc_html_e( 'Separe a identidade visual principal do shell e do link do pedido para ajustes rapidos de marca.', EOP_TEXT_DOMAIN ); ?></p>
                            <?php if ( class_exists( 'EOP_Public_Proposal' ) && method_exists( 'EOP_Public_Proposal', 'render_admin_preview_card' ) ) : ?>
                                <?php echo EOP_Public_Proposal::render_admin_preview_card( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php endif; ?>
                            <div class="eop-settings-grid eop-proposal-preview-settings__grid">
                                <div class="eop-settings-field is-full">
                                    <label for="eop_brand_logo_url"><?php esc_html_e( 'Logo', EOP_TEXT_DOMAIN ); ?></label>
                                    <div class="eop-settings-media">
                                        <div class="eop-settings-media__preview<?php echo $settings['brand_logo_url'] ? ' has-image' : ''; ?>" data-media-preview>
                                            <?php if ( $settings['brand_logo_url'] ) : ?>
                                                <img src="<?php echo esc_url( $settings['brand_logo_url'] ); ?>" alt="<?php esc_attr_e( 'Preview da logo', EOP_TEXT_DOMAIN ); ?>" />
                                            <?php else : ?>
                                                <span class="eop-settings-media__empty"><?php esc_html_e( 'Nenhum logo selecionado ainda.', EOP_TEXT_DOMAIN ); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="eop-settings-media__details">
                                            <input
                                                id="eop_brand_logo_url"
                                                type="url"
                                                class="eop-settings-media__url"
                                                value="<?php echo esc_attr( $settings['brand_logo_url'] ); ?>"
                                                readonly
                                                data-media-url
                                            />
                                            <div class="eop-settings-media__actions">
                                                <button type="button" class="button button-secondary eop-settings-media__select" data-media-select>
                                                    <?php echo $settings['brand_logo_url'] ? esc_html__( 'Trocar logo', EOP_TEXT_DOMAIN ) : esc_html__( 'Selecionar logo', EOP_TEXT_DOMAIN ); ?>
                                                </button>
                                                <button type="button" class="button button-link-delete eop-settings-media__remove<?php echo $settings['brand_logo_url'] ? '' : ' is-hidden'; ?>" data-media-remove>
                                                    <?php esc_html_e( 'Remover logo', EOP_TEXT_DOMAIN ); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <small class="eop-settings-help"><?php esc_html_e( 'Use a biblioteca de midia para selecionar a logo usada na pagina publica do cliente.', EOP_TEXT_DOMAIN ); ?></small>
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_primary_color"><?php esc_html_e( 'Cor principal', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_primary_color" class="eop-color-field" type="text" data-default-color="#00034b" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[primary_color]" value="<?php echo esc_attr( $settings['primary_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_surface_color"><?php esc_html_e( 'Cor de fundo dos cards', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_surface_color" class="eop-color-field" type="text" data-default-color="#ffffff" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[surface_color]" value="<?php echo esc_attr( $settings['surface_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_border_color"><?php esc_html_e( 'Cor da borda', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_border_color" class="eop-color-field" type="text" data-default-color="#dbe3f0" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[border_color]" value="<?php echo esc_attr( $settings['border_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_radius"><?php esc_html_e( 'Radius', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_radius" type="number" min="0" max="48" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[border_radius]" value="<?php echo esc_attr( $settings['border_radius'] ); ?>" />
                                </div>
                                <div class="eop-settings-field is-full">
                                    <label for="eop_font_family"><?php esc_html_e( 'Fonte', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_font_family" class="select_font eop-font-field" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[font_family]" value="<?php echo esc_attr( $settings['font_family'] ); ?>" />
                                    <small class="eop-settings-help"><?php esc_html_e( 'Selecione uma fonte do Google no mesmo padrao usado em outros plugins Aireset.', EOP_TEXT_DOMAIN ); ?></small>
                                </div>
                            </div>
                        </section>

                        <?php endif; ?>

                        <?php if ( self::should_render_admin_section( $section, 'proposal-link-style' ) ) : ?>
                        <section class="eop-settings-card">
                            <h2><?php esc_html_e( 'Visual da pagina do cliente', EOP_TEXT_DOMAIN ); ?></h2>
                            <p><?php esc_html_e( 'Deixe a proposta publica com uma identidade propria, sem depender do visual interno do vendedor.', EOP_TEXT_DOMAIN ); ?></p>
                            <div class="eop-settings-grid">
                                <div class="eop-settings-field">
                                    <label for="eop_proposal_background_color"><?php esc_html_e( 'Fundo da pagina', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_proposal_background_color" class="eop-color-field" type="text" data-default-color="#f5f7ff" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_background_color]" value="<?php echo esc_attr( $settings['proposal_background_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_proposal_card_color"><?php esc_html_e( 'Fundo do card', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_proposal_card_color" class="eop-color-field" type="text" data-default-color="#ffffff" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_card_color]" value="<?php echo esc_attr( $settings['proposal_card_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_proposal_text_color"><?php esc_html_e( 'Texto principal', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_proposal_text_color" class="eop-color-field" type="text" data-default-color="#172033" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_text_color]" value="<?php echo esc_attr( $settings['proposal_text_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_proposal_muted_color"><?php esc_html_e( 'Texto auxiliar', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_proposal_muted_color" class="eop-color-field" type="text" data-default-color="#5b6474" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_muted_color]" value="<?php echo esc_attr( $settings['proposal_muted_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_proposal_max_width"><?php esc_html_e( 'Largura maxima da proposta (px)', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_proposal_max_width" type="number" min="720" max="1600" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_max_width]" value="<?php echo esc_attr( $settings['proposal_max_width'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_proposal_title_size"><?php esc_html_e( 'Tamanho do titulo (px)', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_proposal_title_size" type="number" min="22" max="72" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_title_size]" value="<?php echo esc_attr( $settings['proposal_title_size'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_proposal_text_size"><?php esc_html_e( 'Tamanho do texto base (px)', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_proposal_text_size" type="number" min="12" max="24" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_text_size]" value="<?php echo esc_attr( $settings['proposal_text_size'] ); ?>" />
                                </div>
                            </div>
                        </section>
                        <?php endif; ?>

                        <?php if ( self::should_render_admin_section( $section, 'customer-experience' ) ) : ?>
                        <section class="eop-settings-card">
                            <h2><?php esc_html_e( 'Experiencia publica confirmada', EOP_TEXT_DOMAIN ); ?></h2>
                            <p><?php esc_html_e( 'Edite em um unico lugar o visual da pagina confirmada e do fluxo complementar visto pelo cliente.', EOP_TEXT_DOMAIN ); ?></p>
                            <div class="eop-settings-grid">
                                <div class="eop-settings-field is-full">
                                    <label for="eop_customer_experience_font_family"><?php esc_html_e( 'Fonte da experiencia publica', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_font_family" class="select_font eop-font-field" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_font_family]" value="<?php echo esc_attr( $settings['customer_experience_font_family'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_title_size"><?php esc_html_e( 'Tamanho do titulo principal (px)', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_title_size" type="number" min="24" max="76" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_title_size]" value="<?php echo esc_attr( $settings['customer_experience_title_size'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_text_size"><?php esc_html_e( 'Tamanho do texto base (px)', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_text_size" type="number" min="13" max="24" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_text_size]" value="<?php echo esc_attr( $settings['customer_experience_text_size'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_background_color"><?php esc_html_e( 'Fundo da pagina', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_background_color" class="eop-color-field" type="text" data-default-color="#edf2fb" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_background_color]" value="<?php echo esc_attr( $settings['customer_experience_background_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_hero_background_color"><?php esc_html_e( 'Fundo do hero', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_hero_background_color" class="eop-color-field" type="text" data-default-color="#0f1b35" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_hero_background_color]" value="<?php echo esc_attr( $settings['customer_experience_hero_background_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_panel_background_color"><?php esc_html_e( 'Fundo dos cards principais', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_panel_background_color" class="eop-color-field" type="text" data-default-color="#ffffff" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_panel_background_color]" value="<?php echo esc_attr( $settings['customer_experience_panel_background_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_sidebar_background_color"><?php esc_html_e( 'Fundo dos cards laterais', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_sidebar_background_color" class="eop-color-field" type="text" data-default-color="#f6f8fc" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_sidebar_background_color]" value="<?php echo esc_attr( $settings['customer_experience_sidebar_background_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_accent_color"><?php esc_html_e( 'Cor de destaque', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_accent_color" class="eop-color-field" type="text" data-default-color="#d78a2f" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_accent_color]" value="<?php echo esc_attr( $settings['customer_experience_accent_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_text_color"><?php esc_html_e( 'Texto principal', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_text_color" class="eop-color-field" type="text" data-default-color="#16243a" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_text_color]" value="<?php echo esc_attr( $settings['customer_experience_text_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_muted_color"><?php esc_html_e( 'Texto auxiliar', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_muted_color" class="eop-color-field" type="text" data-default-color="#66768d" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_muted_color]" value="<?php echo esc_attr( $settings['customer_experience_muted_color'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_eyebrow"><?php esc_html_e( 'Label superior', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_eyebrow" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_eyebrow]" value="<?php echo esc_attr( $settings['customer_experience_eyebrow'] ); ?>" />
                                </div>
                                <div class="eop-settings-field is-full">
                                    <label for="eop_customer_experience_title"><?php esc_html_e( 'Titulo principal', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_title" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_title]" value="<?php echo esc_attr( $settings['customer_experience_title'] ); ?>" />
                                </div>
                                <div class="eop-settings-field is-full">
                                    <label for="eop_customer_experience_description"><?php esc_html_e( 'Descricao principal', EOP_TEXT_DOMAIN ); ?></label>
                                    <textarea id="eop_customer_experience_description" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_description]"><?php echo esc_textarea( $settings['customer_experience_description'] ); ?></textarea>
                                </div>
                                <div class="eop-settings-field is-full">
                                    <label for="eop_customer_experience_total_note"><?php esc_html_e( 'Texto de apoio do total', EOP_TEXT_DOMAIN ); ?></label>
                                    <textarea id="eop_customer_experience_total_note" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_total_note]"><?php echo esc_textarea( $settings['customer_experience_total_note'] ); ?></textarea>
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_items_eyebrow"><?php esc_html_e( 'Label da secao de itens', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_items_eyebrow" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_items_eyebrow]" value="<?php echo esc_attr( $settings['customer_experience_items_eyebrow'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_items_title"><?php esc_html_e( 'Titulo da secao de itens', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_items_title" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_items_title]" value="<?php echo esc_attr( $settings['customer_experience_items_title'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_summary_eyebrow"><?php esc_html_e( 'Label do resumo lateral', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_summary_eyebrow" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_summary_eyebrow]" value="<?php echo esc_attr( $settings['customer_experience_summary_eyebrow'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_summary_title"><?php esc_html_e( 'Titulo do resumo lateral', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_summary_title" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_summary_title]" value="<?php echo esc_attr( $settings['customer_experience_summary_title'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_financial_eyebrow"><?php esc_html_e( 'Label do resumo financeiro', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_financial_eyebrow" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_financial_eyebrow]" value="<?php echo esc_attr( $settings['customer_experience_financial_eyebrow'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_actions_eyebrow"><?php esc_html_e( 'Label do card de acao', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_actions_eyebrow" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_actions_eyebrow]" value="<?php echo esc_attr( $settings['customer_experience_actions_eyebrow'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_actions_title"><?php esc_html_e( 'Titulo do card de acao', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_actions_title" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_actions_title]" value="<?php echo esc_attr( $settings['customer_experience_actions_title'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_customer_experience_progress_label"><?php esc_html_e( 'Titulo do mapa de jornada', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_customer_experience_progress_label" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_progress_label]" value="<?php echo esc_attr( $settings['customer_experience_progress_label'] ); ?>" />
                                </div>
                            </div>
                        </section>
                        <?php endif; ?>

                        <?php if ( self::should_render_admin_section( $section, 'texts' ) ) : ?>
                        <section class="eop-settings-card">
                            <h2><?php esc_html_e( 'Textos', EOP_TEXT_DOMAIN ); ?></h2>
                            <p><?php esc_html_e( 'Refine a narrativa do painel de vendedor e da proposta publica.', EOP_TEXT_DOMAIN ); ?></p>
                            <div class="eop-settings-grid">
                                <div class="eop-settings-field">
                                    <label for="eop_panel_title"><?php esc_html_e( 'Titulo do painel', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_panel_title" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[panel_title]" value="<?php echo esc_attr( $settings['panel_title'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_proposal_title"><?php esc_html_e( 'Titulo da proposta', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_proposal_title" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_title]" value="<?php echo esc_attr( $settings['proposal_title'] ); ?>" />
                                </div>
                                <div class="eop-settings-field is-full">
                                    <label for="eop_panel_subtitle"><?php esc_html_e( 'Subtitulo do painel', EOP_TEXT_DOMAIN ); ?></label>
                                    <textarea id="eop_panel_subtitle" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[panel_subtitle]"><?php echo esc_textarea( $settings['panel_subtitle'] ); ?></textarea>
                                </div>
                                <div class="eop-settings-field is-full">
                                    <label for="eop_proposal_description"><?php esc_html_e( 'Descricao da proposta', EOP_TEXT_DOMAIN ); ?></label>
                                    <textarea id="eop_proposal_description" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_description]"><?php echo esc_textarea( $settings['proposal_description'] ); ?></textarea>
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_proposal_button_label"><?php esc_html_e( 'Texto do botao da proposta', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_proposal_button_label" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_button_label]" value="<?php echo esc_attr( $settings['proposal_button_label'] ); ?>" />
                                </div>
                                <div class="eop-settings-field">
                                    <label for="eop_proposal_pay_button_label"><?php esc_html_e( 'Texto do botao de pagamento', EOP_TEXT_DOMAIN ); ?></label>
                                    <input id="eop_proposal_pay_button_label" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_pay_button_label]" value="<?php echo esc_attr( $settings['proposal_pay_button_label'] ); ?>" />
                                </div>
                            </div>
                        </section>
                        <?php endif; ?>
                </div>
                <div class="eop-settings-submit eop-admin-submitbar">
                    <?php submit_button( __( 'Salvar alteracoes', EOP_TEXT_DOMAIN ), 'primary large', 'submit', false ); ?>
                </div>
            </form>
        </div>
        <?php
    }
}
