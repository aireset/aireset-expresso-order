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
        add_action( 'init', array( __CLASS__, 'maybe_migrate_accents' ) );
    }

    /**
     * Migracao one-shot: corrige textos da proposta salvos sem acento.
     *
     * So altera valores que batem EXATAMENTE com o texto antigo (padrao sem
     * acento). Se a loja personalizou o texto, nada e tocado. Roda uma unica
     * vez (controlada por flag) e tambem no front, para corrigir a pagina
     * publica sem precisar abrir o admin.
     */
    public static function maybe_migrate_accents() {
        if ( 'done' === get_option( 'eop_accents_migrated_v2' ) ) {
            return;
        }

        $map = array(
            'customer_experience_eyebrow'             => array( 'Experiencia do cliente', 'Experiência do cliente' ),
            'customer_experience_title'               => array( 'Sua proposta esta pronta para seguir', 'Sua proposta está pronta para seguir' ),
            'customer_experience_description'         => array( 'Confira os detalhes finais, valide os documentos e conclua a etapa atual em uma unica jornada.', 'Confira os detalhes finais, valide os documentos e conclua a etapa atual em uma única jornada.' ),
            'customer_experience_total_note'          => array( 'Assim que a etapa atual for concluida, o pedido segue para o time responsavel.', 'Assim que a etapa atual for concluída, o pedido segue para o time responsável.' ),
            'customer_experience_summary_eyebrow'     => array( 'Contexto rapido', 'Contexto rápido' ),
            'customer_experience_summary_title'       => array( 'Visao do pedido', 'Visão do pedido' ),
            'customer_experience_actions_eyebrow'     => array( 'Proxima acao', 'Próxima ação' ),
            'customer_experience_progress_note'       => array( 'As proximas etapas sao liberadas em sequencia para evitar retrabalho.', 'As próximas etapas são liberadas em sequência para evitar retrabalho.' ),
            'customer_experience_notes_label'         => array( 'Observacoes', 'Observações' ),
            'customer_experience_pending_status_text' => array( 'Aguardando confirmacao', 'Aguardando confirmação' ),
            'customer_experience_meta_status_value_preview' => array( 'Aguardando confirmacao', 'Aguardando confirmação' ),
            'customer_experience_meta_deadline_value_preview' => array( 'Entrega em ate 3 dias uteis', 'Entrega em até 3 dias úteis' ),
            'proposal_title'                          => array( 'Sua proposta esta pronta', 'Sua proposta está pronta' ),
            'post_confirmation_contract_body'         => array( 'Use este espaco para inserir o texto contratual que o cliente precisa ler e aceitar antes de continuar.', 'Use este espaço para inserir o texto contratual que o cliente precisa ler e aceitar antes de continuar.' ),
            'post_confirmation_stage_upload_label'    => array( 'Upload e personalizacao', 'Upload e personalização' ),
            'post_confirmation_stage_completed_label' => array( 'Fluxo concluido', 'Fluxo concluído' ),
            'post_confirmation_documents_description' => array( 'Os dados do cliente, documento e endereco sao aproveitados automaticamente do pedido WooCommerce.', 'Os dados do cliente, documento e endereço são aproveitados automaticamente do pedido WooCommerce.' ),
            'post_confirmation_upload_saved_status_text' => array( 'Arquivo ja salvo no pedido.', 'Arquivo já salvo no pedido.' ),
            'post_confirmation_products_sku_empty_label' => array( 'SKU nao informado', 'SKU não informado' ),
            'post_confirmation_products_locked_message' => array( 'Este item esta bloqueado para alteracao de nome.', 'Este item está bloqueado para alteração de nome.' ),
            'post_confirmation_products_button_label' => array( 'Salvar personalizacao', 'Salvar personalização' ),
            'post_confirmation_completion_title'      => array( 'Etapa complementar concluida', 'Etapa complementar concluída' ),
            'post_confirmation_completion_description' => array( 'Recebemos suas informacoes e o pedido segue para a equipe responsavel.', 'Recebemos suas informações e o pedido segue para a equipe responsável.' ),
            'new_order_shipping_button_label'         => array( 'Buscar opcoes de frete', 'Buscar opções de frete' ),
        );

        $settings = get_option( self::OPTION_KEY, array() );

        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        $changed = false;

        foreach ( $map as $key => $pair ) {
            if ( isset( $settings[ $key ] ) && $pair[0] === $settings[ $key ] ) {
                $settings[ $key ] = $pair[1];
                $changed          = true;
            }
        }

        if ( $changed ) {
            update_option( self::OPTION_KEY, $settings );
        }

        update_option( 'eop_accents_migrated_v2', 'done' );
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
            'proposal_title_size'                    => '40px',
            'proposal_text_size'                     => '16px',
            'customer_experience_shared_border_width' => '1px',
            'customer_experience_shared_border_style' => 'solid',
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
            'customer_experience_accent_color'       => '#00ffff',
            'customer_experience_text_color'         => '#16243a',
            'customer_experience_muted_color'        => '#66768d',
            'customer_experience_title_size'         => '46px',
            'customer_experience_text_size'          => '16px',
            'customer_experience_eyebrow'            => 'Experiência do cliente',
            'customer_experience_title'              => 'Sua proposta está pronta para seguir',
            'customer_experience_description'        => 'Confira os detalhes finais, valide os documentos e conclua a etapa atual em uma única jornada.',
            'customer_experience_total_label'        => 'Investimento aprovado',
            'customer_experience_total_note'         => 'Assim que a etapa atual for concluída, o pedido segue para o time responsável.',
            'customer_experience_items_eyebrow'      => '',
            'customer_experience_items_title'        => 'Itens',
            'customer_experience_summary_eyebrow'    => 'Contexto rápido',
            'customer_experience_summary_title'      => 'Visão do pedido',
            'customer_experience_summary_enabled'    => 'yes',
            'customer_experience_financial_eyebrow'  => '',
            'customer_experience_financial_title'    => 'Resumo',
            'customer_experience_financial_enabled'  => 'yes',
            'customer_experience_actions_eyebrow'    => 'Próxima ação',
            'customer_experience_actions_title'      => 'Como seguir agora',
            'customer_experience_actions_enabled'    => 'yes',
            'customer_experience_progress_label'     => 'Mapa da jornada',
            'customer_experience_progress_note'      => 'As próximas etapas são liberadas em sequência para evitar retrabalho.',
            'panel_title'                            => 'Pedido Expresso',
            'panel_subtitle'                         => 'Monte o pedido, gere a proposta e compartilhe com o cliente.',
            'proposal_title'                         => 'Sua proposta está pronta',
            'proposal_description'                   => 'Revise os itens e confirme para continuar.',
            'proposal_button_enabled'                => 'yes',
            'proposal_button_label'                  => 'Confirmar proposta',
            'proposal_pay_button_enabled'            => 'yes',
            'proposal_pay_button_label'              => 'Ir para pagamento',
            'proposal_pdf_button_enabled'            => 'yes',
            'proposal_pdf_button_label'              => 'Baixar PDF',
            'pdf_company_name'                       => get_bloginfo( 'name' ),
            'pdf_company_document'                   => '',
            'pdf_company_address'                    => '',
            'pdf_footer_note'                        => __( 'Documento gerado pelo Aireset Expresso Order.', EOP_TEXT_DOMAIN ),
        );

        foreach ( self::get_post_confirmation_document_slots() as $slot ) {
            $defaults[ 'post_confirmation_document_' . $slot . '_label' ]       = sprintf( __( 'Campo adicional %d', EOP_TEXT_DOMAIN ), $slot );
            $defaults[ 'post_confirmation_document_' . $slot . '_placeholder' ] = '';
        }

        $defaults = array_merge(
            $defaults,
            array(
                'enable_post_confirmation_flow'             => 'no',
                'post_confirmation_contract_title'          => __( 'Leia e confirme o contrato abaixo', EOP_TEXT_DOMAIN ),
                'post_confirmation_contract_body'           => __( 'Use este espaço para inserir o texto contratual que o cliente precisa ler e aceitar antes de continuar.', EOP_TEXT_DOMAIN ),
                'post_confirmation_contract_document_description' => __( 'Documento principal exibido na etapa de aceite.', EOP_TEXT_DOMAIN ),
                'post_confirmation_stage_contract_label'    => __( 'Aceite contratual', EOP_TEXT_DOMAIN ),
                'post_confirmation_stage_upload_label'      => __( 'Upload e personalização', EOP_TEXT_DOMAIN ),
                'post_confirmation_stage_completed_label'   => __( 'Conclusão', EOP_TEXT_DOMAIN ),
                'post_confirmation_contract_checkbox_label' => __( 'Li e aceito o contrato acima.', EOP_TEXT_DOMAIN ),
                'post_confirmation_contract_button_label'   => __( 'Confirmar e continuar', EOP_TEXT_DOMAIN ),
                'post_confirmation_contract_shipping_label' => __( 'Frete', EOP_TEXT_DOMAIN ),
                'post_confirmation_signature_documents'     => array(),
                'post_confirmation_documents_title'         => __( 'Dados do pedido', EOP_TEXT_DOMAIN ),
                'post_confirmation_documents_description'   => __( 'Os dados do cliente, documento e endereço são aproveitados automaticamente do pedido WooCommerce.', EOP_TEXT_DOMAIN ),
                'post_confirmation_documents_button_label'  => __( 'Atualizar dados', EOP_TEXT_DOMAIN ),
                'post_confirmation_require_attachment'      => 'yes',
                'post_confirmation_upload_title'            => __( 'Envie o arquivo solicitado', EOP_TEXT_DOMAIN ),
                'post_confirmation_upload_description'      => __( 'Aceitamos arquivos JPG, PNG ou PDF.', EOP_TEXT_DOMAIN ),
                'post_confirmation_final_intro_eyebrow'     => __( 'Etapa final do pedido', EOP_TEXT_DOMAIN ),
                'post_confirmation_upload_field_label'      => __( 'Arquivo', EOP_TEXT_DOMAIN ),
                'post_confirmation_upload_button_label'     => __( 'Enviar arquivo', EOP_TEXT_DOMAIN ),
                'post_confirmation_upload_saved_date_prefix' => __( 'Enviado em', EOP_TEXT_DOMAIN ),
                'post_confirmation_upload_saved_status_text' => __( 'Arquivo já salvo no pedido.', EOP_TEXT_DOMAIN ),
                'post_confirmation_upload_view_button_label' => __( 'Ver anexo enviado', EOP_TEXT_DOMAIN ),
                'post_confirmation_products_title'          => __( 'Personalize os nomes dos produtos', EOP_TEXT_DOMAIN ),
                'post_confirmation_products_description'    => __( 'Informe como cada nome deve aparecer para os itens liberados.', EOP_TEXT_DOMAIN ),
                'post_confirmation_products_heading_sequence_label' => __( 'Seq.', EOP_TEXT_DOMAIN ),
                'post_confirmation_products_heading_original_label' => __( 'Produto original', EOP_TEXT_DOMAIN ),
                'post_confirmation_products_heading_custom_label' => __( 'Novo nome', EOP_TEXT_DOMAIN ),
                'post_confirmation_products_sku_prefix'    => __( 'SKU:', EOP_TEXT_DOMAIN ),
                'post_confirmation_products_sku_empty_label' => __( 'SKU não informado', EOP_TEXT_DOMAIN ),
                'post_confirmation_products_locked_message' => __( 'Este item está bloqueado para alteração de nome.', EOP_TEXT_DOMAIN ),
                'post_confirmation_products_button_label'   => __( 'Salvar personalização', EOP_TEXT_DOMAIN ),
                'post_confirmation_locked_products'         => '',
                'post_confirmation_completion_title'        => __( 'Etapa complementar concluída', EOP_TEXT_DOMAIN ),
                'post_confirmation_completion_description'  => __( 'Recebemos suas informações e o pedido segue para a equipe responsável.', EOP_TEXT_DOMAIN ),
            )
        );

        foreach ( array_merge( self::get_post_confirmation_contract_style_sections(), self::get_post_confirmation_upload_products_style_sections() ) as $section ) {
            foreach ( $section['fields'] as $field_key => $field ) {
                if ( array_key_exists( 'default', $field ) ) {
                    $defaults[ $field_key ] = $field['default'];
                }
            }
        }

        foreach ( self::get_order_link_visual_sections() as $section ) {
            foreach ( $section['fields'] as $field_key => $field ) {
                if ( array_key_exists( 'default', $field ) ) {
                    $defaults[ $field_key ] = $field['default'];
                }
            }
        }

        foreach ( array_merge( self::get_new_order_visual_sections(), self::get_orders_list_visual_sections() ) as $section ) {
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
            'proposal_title_size'                  => self::sanitize_css_size_value( $input['proposal_title_size'] ?? $defaults['proposal_title_size'], $defaults['proposal_title_size'] ),
            'proposal_text_size'                   => self::sanitize_css_size_value( $input['proposal_text_size'] ?? $defaults['proposal_text_size'], $defaults['proposal_text_size'] ),
            'customer_experience_shared_border_width' => self::sanitize_css_size_value( $input['customer_experience_shared_border_width'] ?? $defaults['customer_experience_shared_border_width'], $defaults['customer_experience_shared_border_width'] ),
            'customer_experience_shared_border_style' => in_array( $input['customer_experience_shared_border_style'] ?? $defaults['customer_experience_shared_border_style'], array( 'solid', 'dashed', 'dotted', 'none' ), true ) ? (string) ( $input['customer_experience_shared_border_style'] ?? $defaults['customer_experience_shared_border_style'] ) : $defaults['customer_experience_shared_border_style'],
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
            'customer_experience_title_size'       => self::sanitize_css_size_value( $input['customer_experience_title_size'] ?? $defaults['customer_experience_title_size'], $defaults['customer_experience_title_size'] ),
            'customer_experience_text_size'        => self::sanitize_css_size_value( $input['customer_experience_text_size'] ?? $defaults['customer_experience_text_size'], $defaults['customer_experience_text_size'] ),
            'customer_experience_eyebrow'          => sanitize_text_field( $input['customer_experience_eyebrow'] ?? $defaults['customer_experience_eyebrow'] ),
            'customer_experience_title'            => sanitize_text_field( $input['customer_experience_title'] ?? $defaults['customer_experience_title'] ),
            'customer_experience_description'      => sanitize_textarea_field( $input['customer_experience_description'] ?? $defaults['customer_experience_description'] ),
            'customer_experience_total_label'      => sanitize_text_field( $input['customer_experience_total_label'] ?? $defaults['customer_experience_total_label'] ),
            'customer_experience_total_note'       => sanitize_textarea_field( $input['customer_experience_total_note'] ?? $defaults['customer_experience_total_note'] ),
            'customer_experience_items_eyebrow'    => sanitize_text_field( $input['customer_experience_items_eyebrow'] ?? $defaults['customer_experience_items_eyebrow'] ),
            'customer_experience_items_title'      => sanitize_text_field( $input['customer_experience_items_title'] ?? $defaults['customer_experience_items_title'] ),
            'customer_experience_summary_eyebrow'  => sanitize_text_field( $input['customer_experience_summary_eyebrow'] ?? $defaults['customer_experience_summary_eyebrow'] ),
            'customer_experience_summary_title'    => sanitize_text_field( $input['customer_experience_summary_title'] ?? $defaults['customer_experience_summary_title'] ),
            'customer_experience_summary_enabled'  => 'yes' === ( $input['customer_experience_summary_enabled'] ?? $defaults['customer_experience_summary_enabled'] ) ? 'yes' : 'no',
            'customer_experience_financial_eyebrow' => sanitize_text_field( $input['customer_experience_financial_eyebrow'] ?? $defaults['customer_experience_financial_eyebrow'] ),
            'customer_experience_financial_title'  => sanitize_text_field( $input['customer_experience_financial_title'] ?? $defaults['customer_experience_financial_title'] ),
            'customer_experience_financial_enabled' => 'yes' === ( $input['customer_experience_financial_enabled'] ?? $defaults['customer_experience_financial_enabled'] ) ? 'yes' : 'no',
            'customer_experience_actions_eyebrow'  => sanitize_text_field( $input['customer_experience_actions_eyebrow'] ?? $defaults['customer_experience_actions_eyebrow'] ),
            'customer_experience_actions_title'    => sanitize_text_field( $input['customer_experience_actions_title'] ?? $defaults['customer_experience_actions_title'] ),
            'customer_experience_actions_enabled'  => 'yes' === ( $input['customer_experience_actions_enabled'] ?? $defaults['customer_experience_actions_enabled'] ) ? 'yes' : 'no',
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
            'post_confirmation_stage_contract_label' => sanitize_text_field( $input['post_confirmation_stage_contract_label'] ?? $defaults['post_confirmation_stage_contract_label'] ),
            'post_confirmation_stage_upload_label' => sanitize_text_field( $input['post_confirmation_stage_upload_label'] ?? $defaults['post_confirmation_stage_upload_label'] ),
            'post_confirmation_stage_completed_label' => sanitize_text_field( $input['post_confirmation_stage_completed_label'] ?? $defaults['post_confirmation_stage_completed_label'] ),
            'post_confirmation_contract_checkbox_label' => sanitize_text_field( $input['post_confirmation_contract_checkbox_label'] ?? $defaults['post_confirmation_contract_checkbox_label'] ),
            'post_confirmation_contract_button_label' => sanitize_text_field( $input['post_confirmation_contract_button_label'] ?? $defaults['post_confirmation_contract_button_label'] ),
            'post_confirmation_contract_shipping_label' => sanitize_text_field( $input['post_confirmation_contract_shipping_label'] ?? $defaults['post_confirmation_contract_shipping_label'] ),
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
            'post_confirmation_upload_saved_date_prefix' => sanitize_text_field( $input['post_confirmation_upload_saved_date_prefix'] ?? $defaults['post_confirmation_upload_saved_date_prefix'] ),
            'post_confirmation_upload_saved_status_text' => sanitize_text_field( $input['post_confirmation_upload_saved_status_text'] ?? $defaults['post_confirmation_upload_saved_status_text'] ),
            'post_confirmation_upload_view_button_label' => sanitize_text_field( $input['post_confirmation_upload_view_button_label'] ?? $defaults['post_confirmation_upload_view_button_label'] ),
            'post_confirmation_products_title'     => sanitize_text_field( $input['post_confirmation_products_title'] ?? $defaults['post_confirmation_products_title'] ),
            'post_confirmation_products_description' => sanitize_textarea_field( $input['post_confirmation_products_description'] ?? $defaults['post_confirmation_products_description'] ),
            'post_confirmation_products_heading_sequence_label' => sanitize_text_field( $input['post_confirmation_products_heading_sequence_label'] ?? $defaults['post_confirmation_products_heading_sequence_label'] ),
            'post_confirmation_products_heading_original_label' => sanitize_text_field( $input['post_confirmation_products_heading_original_label'] ?? $defaults['post_confirmation_products_heading_original_label'] ),
            'post_confirmation_products_heading_custom_label' => sanitize_text_field( $input['post_confirmation_products_heading_custom_label'] ?? $defaults['post_confirmation_products_heading_custom_label'] ),
            'post_confirmation_products_sku_prefix' => sanitize_text_field( $input['post_confirmation_products_sku_prefix'] ?? $defaults['post_confirmation_products_sku_prefix'] ),
            'post_confirmation_products_sku_empty_label' => sanitize_text_field( $input['post_confirmation_products_sku_empty_label'] ?? $defaults['post_confirmation_products_sku_empty_label'] ),
            'post_confirmation_products_locked_message' => sanitize_text_field( $input['post_confirmation_products_locked_message'] ?? $defaults['post_confirmation_products_locked_message'] ),
            'post_confirmation_products_button_label' => sanitize_text_field( $input['post_confirmation_products_button_label'] ?? $defaults['post_confirmation_products_button_label'] ),
            'post_confirmation_locked_products'    => sanitize_text_field( str_replace( array( "\r", "\n", ';' ), ',', (string) ( $input['post_confirmation_locked_products'] ?? $defaults['post_confirmation_locked_products'] ) ) ),
            'post_confirmation_completion_title'   => sanitize_text_field( $input['post_confirmation_completion_title'] ?? $defaults['post_confirmation_completion_title'] ),
            'post_confirmation_completion_description' => sanitize_textarea_field( $input['post_confirmation_completion_description'] ?? $defaults['post_confirmation_completion_description'] ),
        );

        foreach ( self::get_post_confirmation_document_slots() as $slot ) {
            $sanitized[ 'post_confirmation_document_' . $slot . '_label' ]       = sanitize_text_field( $input[ 'post_confirmation_document_' . $slot . '_label' ] ?? $defaults[ 'post_confirmation_document_' . $slot . '_label' ] );
            $sanitized[ 'post_confirmation_document_' . $slot . '_placeholder' ] = sanitize_text_field( $input[ 'post_confirmation_document_' . $slot . '_placeholder' ] ?? $defaults[ 'post_confirmation_document_' . $slot . '_placeholder' ] );
        }

        foreach ( array_merge( self::get_post_confirmation_contract_style_sections(), self::get_post_confirmation_upload_products_style_sections() ) as $section ) {
            foreach ( $section['fields'] as $field_key => $field ) {
                $sanitized[ $field_key ] = self::sanitize_post_confirmation_visual_field(
                    $input[ $field_key ] ?? ( $defaults[ $field_key ] ?? '' ),
                    $field
                );
            }
        }

        foreach ( self::get_order_link_visual_sections() as $section ) {
            foreach ( $section['fields'] as $field_key => $field ) {
                $sanitized[ $field_key ] = self::sanitize_order_link_visual_field(
                    $input[ $field_key ] ?? ( $defaults[ $field_key ] ?? '' ),
                    $field
                );
            }
        }

        foreach ( array_merge( self::get_new_order_visual_sections(), self::get_orders_list_visual_sections() ) as $section ) {
            foreach ( $section['fields'] as $field_key => $field ) {
                $sanitized[ $field_key ] = self::sanitize_order_link_visual_field(
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

    private static function get_post_confirmation_contract_breadcrumb_item_style_fields() {
        $fields               = array();
        $border_style_choices = array(
            'solid'  => 'Solid',
            'dashed' => 'Dashed',
            'dotted' => 'Dotted',
            'none'   => 'None',
        );
        $items                = array(
            'contract'  => __( 'Breadcrumb 1', EOP_TEXT_DOMAIN ),
            'upload'    => __( 'Breadcrumb 2', EOP_TEXT_DOMAIN ),
            'completed' => __( 'Breadcrumb 3', EOP_TEXT_DOMAIN ),
        );

        foreach ( $items as $step_key => $item_label ) {
            $item_selector  = '.eop-post-flow__breadcrumb-item.eop-post-flow__breadcrumb-item--' . sanitize_html_class( $step_key );
            $index_selector = $item_selector . ' .eop-post-flow__breadcrumb-index';
            $prefix         = 'post_confirmation_contract_visual_breadcrumb_' . $step_key . '_';

            $fields[ $prefix . 'font_family' ] = array( 'label' => sprintf( __( 'Fonte do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'css' => array( array( 'selector' => $item_selector, 'property' => 'font-family' ) ) );
            $fields[ $prefix . 'font_size' ] = array( 'label' => sprintf( __( 'Tamanho do texto do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'text', 'default' => '14px', 'css' => array( array( 'selector' => $item_selector, 'property' => 'font-size' ) ) );
            $fields[ $prefix . 'font_weight' ] = array( 'label' => sprintf( __( 'Peso da fonte do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'select', 'default' => '700', 'choices' => array( '400' => '400', '500' => '500', '600' => '600', '700' => '700', '800' => '800' ), 'css' => array( array( 'selector' => $item_selector, 'property' => 'font-weight' ) ) );
            $fields[ $prefix . 'line_height' ] = array( 'label' => sprintf( __( 'Line-height do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'text', 'default' => '1.2', 'css' => array( array( 'selector' => $item_selector, 'property' => 'line-height' ) ) );
            $fields[ $prefix . 'text_color' ] = array( 'label' => sprintf( __( 'Cor do texto do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => $item_selector, 'property' => 'color' ) ) );
            $fields[ $prefix . 'background_color' ] = array( 'label' => sprintf( __( 'Fundo do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => $item_selector, 'property' => 'background' ) ) );
            $fields[ $prefix . 'padding' ] = array( 'label' => sprintf( __( 'Padding do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'box', 'default' => '12px 18px', 'css' => array( array( 'selector' => $item_selector, 'property' => 'padding' ) ) );
            $fields[ $prefix . 'border_radius' ] = array( 'label' => sprintf( __( 'Radius do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'size', 'default' => '999px', 'css' => array( array( 'selector' => $item_selector, 'property' => 'border-radius' ) ) );
            $fields[ $prefix . 'border_width' ] = array( 'label' => sprintf( __( 'Borda do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'size', 'default' => '1px', 'css' => array( array( 'selector' => $item_selector, 'property' => 'border-width' ) ) );
            $fields[ $prefix . 'border_style' ] = array( 'label' => sprintf( __( 'Estilo da borda do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'select', 'default' => 'solid', 'choices' => $border_style_choices, 'css' => array( array( 'selector' => $item_selector, 'property' => 'border-style' ) ) );
            $fields[ $prefix . 'border_color' ] = array( 'label' => sprintf( __( 'Cor da borda do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'color', 'default' => '#dbe3f0', 'css' => array( array( 'selector' => $item_selector, 'property' => 'border-color' ) ) );
            $fields[ $prefix . 'index_background_color' ] = array( 'label' => sprintf( __( 'Fundo do numero do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'color', 'default' => 'contract' === $step_key ? '#00ffff' : '#eff3fb', 'css' => array( array( 'selector' => $index_selector, 'property' => 'background' ) ) );
            $fields[ $prefix . 'index_color' ] = array( 'label' => sprintf( __( 'Cor do numero do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'color', 'default' => 'contract' === $step_key ? '#ffffff' : '#16243a', 'css' => array( array( 'selector' => $index_selector, 'property' => 'color' ) ) );
        }

        return $fields;
    }

    private static function get_post_confirmation_upload_products_breadcrumb_item_style_fields() {
        $fields               = array();
        $border_style_choices = array(
            'solid'  => 'Solid',
            'dashed' => 'Dashed',
            'dotted' => 'Dotted',
            'none'   => 'None',
        );
        $items                = array(
            'contract'  => __( 'Breadcrumb 1', EOP_TEXT_DOMAIN ),
            'upload'    => __( 'Breadcrumb 2', EOP_TEXT_DOMAIN ),
            'completed' => __( 'Breadcrumb 3', EOP_TEXT_DOMAIN ),
        );

        foreach ( $items as $step_key => $item_label ) {
            $item_selector  = '.eop-post-flow__breadcrumb-item.eop-post-flow__breadcrumb-item--' . sanitize_html_class( $step_key );
            $index_selector = $item_selector . ' .eop-post-flow__breadcrumb-index';
            $prefix         = 'post_confirmation_upload_visual_breadcrumb_' . $step_key . '_';

            $fields[ $prefix . 'font_family' ] = array( 'label' => sprintf( __( 'Fonte do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'css' => array( array( 'selector' => $item_selector, 'property' => 'font-family' ) ) );
            $fields[ $prefix . 'font_size' ] = array( 'label' => sprintf( __( 'Tamanho do texto do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'text', 'default' => '14px', 'css' => array( array( 'selector' => $item_selector, 'property' => 'font-size' ) ) );
            $fields[ $prefix . 'font_weight' ] = array( 'label' => sprintf( __( 'Peso da fonte do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'select', 'default' => '700', 'choices' => array( '400' => '400', '500' => '500', '600' => '600', '700' => '700', '800' => '800' ), 'css' => array( array( 'selector' => $item_selector, 'property' => 'font-weight' ) ) );
            $fields[ $prefix . 'line_height' ] = array( 'label' => sprintf( __( 'Line-height do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'text', 'default' => '1.2', 'css' => array( array( 'selector' => $item_selector, 'property' => 'line-height' ) ) );
            $fields[ $prefix . 'text_color' ] = array( 'label' => sprintf( __( 'Cor do texto do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => $item_selector, 'property' => 'color' ) ) );
            $fields[ $prefix . 'background_color' ] = array( 'label' => sprintf( __( 'Fundo do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => $item_selector, 'property' => 'background' ) ) );
            $fields[ $prefix . 'padding' ] = array( 'label' => sprintf( __( 'Padding do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'box', 'default' => '12px 18px', 'css' => array( array( 'selector' => $item_selector, 'property' => 'padding' ) ) );
            $fields[ $prefix . 'border_radius' ] = array( 'label' => sprintf( __( 'Radius do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'size', 'default' => '999px', 'css' => array( array( 'selector' => $item_selector, 'property' => 'border-radius' ) ) );
            $fields[ $prefix . 'border_width' ] = array( 'label' => sprintf( __( 'Borda do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'size', 'default' => '1px', 'css' => array( array( 'selector' => $item_selector, 'property' => 'border-width' ) ) );
            $fields[ $prefix . 'border_style' ] = array( 'label' => sprintf( __( 'Estilo da borda do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'select', 'default' => 'solid', 'choices' => $border_style_choices, 'css' => array( array( 'selector' => $item_selector, 'property' => 'border-style' ) ) );
            $fields[ $prefix . 'border_color' ] = array( 'label' => sprintf( __( 'Cor da borda do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'color', 'default' => '#dbe3f0', 'css' => array( array( 'selector' => $item_selector, 'property' => 'border-color' ) ) );
            $fields[ $prefix . 'index_background_color' ] = array( 'label' => sprintf( __( 'Fundo do numero do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'color', 'default' => 'upload' === $step_key ? '#00ffff' : '#eff3fb', 'css' => array( array( 'selector' => $index_selector, 'property' => 'background' ) ) );
            $fields[ $prefix . 'index_color' ] = array( 'label' => sprintf( __( 'Cor do numero do %s', EOP_TEXT_DOMAIN ), $item_label ), 'type' => 'color', 'default' => 'upload' === $step_key ? '#ffffff' : '#16243a', 'css' => array( array( 'selector' => $index_selector, 'property' => 'color' ) ) );
        }

        return $fields;
    }

    private static function get_post_confirmation_contract_style_field_registry() {
        $registry = array();

        foreach ( self::get_post_confirmation_contract_style_sections() as $section ) {
            foreach ( (array) ( $section['fields'] ?? array() ) as $field_key => $field ) {
                $registry[ $field_key ] = $field;
            }
        }

        return $registry;
    }

    private static function get_post_confirmation_contract_editor_section_fields( $registry, $groups ) {
        $fields = array();

        foreach ( (array) $groups as $group_label => $field_keys ) {
            foreach ( (array) $field_keys as $field_key ) {
                if ( ! isset( $registry[ $field_key ] ) ) {
                    continue;
                }

                $field = $registry[ $field_key ];
                $field['group'] = (string) $group_label;
                $fields[ $field_key ] = $field;
            }
        }

        return $fields;
    }

    public static function get_post_confirmation_contract_style_sections() {
        return array(
            array(
                'id'          => 'contract_header',
                'label'       => __( 'Header superior', EOP_TEXT_DOMAIN ),
                'description' => __( 'Personalize o topo da etapa contratual com logo, pedido e fundo.', EOP_TEXT_DOMAIN ),
                'fields'      => array(
                    'post_confirmation_contract_visual_header_background_color' => array( 'label' => __( 'Fundo do header', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#0f1b35', 'css' => array( array( 'selector' => '.eop-post-flow__contract-header', 'property' => 'background' ) ) ),
                    'post_confirmation_contract_visual_header_padding'          => array( 'label' => __( 'Padding', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '30px 32px 28px', 'css' => array( array( 'selector' => '.eop-post-flow__contract-header', 'property' => 'padding' ) ) ),
                    'post_confirmation_contract_visual_header_margin'           => array( 'label' => __( 'Margin', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0 0 22px', 'css' => array( array( 'selector' => '.eop-post-flow__contract-header', 'property' => 'margin' ) ) ),
                    'post_confirmation_contract_visual_header_border_radius'    => array( 'label' => __( 'Border radius', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '28px', 'css' => array( array( 'selector' => '.eop-post-flow__contract-header', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_contract_visual_header_border_width'     => array( 'label' => __( 'Borda', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '0px', 'css' => array( array( 'selector' => '.eop-post-flow__contract-header', 'property' => 'border-width' ) ) ),
                    'post_confirmation_contract_visual_header_border_style'     => array( 'label' => __( 'Estilo da borda', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-post-flow__contract-header', 'property' => 'border-style' ) ) ),
                    'post_confirmation_contract_visual_header_border_color'     => array( 'label' => __( 'Cor da borda', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#0f1b35', 'css' => array( array( 'selector' => '.eop-post-flow__contract-header', 'property' => 'border-color' ) ) ),
                    'post_confirmation_contract_visual_header_box_shadow'       => array( 'label' => __( 'Box shadow', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'default' => 'none', 'css' => array( array( 'selector' => '.eop-post-flow__contract-header', 'property' => 'box-shadow' ) ) ),
                    'post_confirmation_contract_visual_header_min_height'       => array( 'label' => __( 'Altura minima', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '0px', 'css' => array( array( 'selector' => '.eop-post-flow__contract-header', 'property' => 'min-height' ) ) ),
                    'post_confirmation_contract_visual_header_card_background_color' => array( 'label' => __( 'Fundo dos cards internos', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#31425f', 'css' => array( array( 'selector' => '.eop-post-flow__contract-brand, .eop-post-flow__contract-meta', 'property' => 'background' ) ) ),
                    'post_confirmation_contract_visual_header_card_border_radius' => array( 'label' => __( 'Radius dos cards internos', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '18px', 'css' => array( array( 'selector' => '.eop-post-flow__contract-brand, .eop-post-flow__contract-meta', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_contract_visual_header_font_family'      => array( 'label' => __( 'Fonte do header', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta', 'property' => 'font-family' ) ) ),
                    'post_confirmation_contract_visual_header_title_color'      => array( 'label' => __( 'Cor do nome da marca', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta strong', 'property' => 'color' ) ) ),
                    'post_confirmation_contract_visual_header_title_size'       => array( 'label' => __( 'Tamanho do nome da marca', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '18px', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta strong', 'property' => 'font-size' ) ) ),
                    'post_confirmation_contract_visual_header_title_line_height' => array( 'label' => __( 'Line-height do nome da marca', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1.2', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta strong', 'property' => 'line-height' ) ) ),
                    'post_confirmation_contract_visual_header_title_padding'    => array( 'label' => __( 'Padding do titulo', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta strong', 'property' => 'padding' ) ) ),
                    'post_confirmation_contract_visual_header_title_margin'     => array( 'label' => __( 'Margin do titulo', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta strong', 'property' => 'margin' ) ) ),
                    'post_confirmation_contract_visual_header_meta_color'       => array( 'label' => __( 'Cor do numero do pedido', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#cfd7ea', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta span', 'property' => 'color' ) ) ),
                    'post_confirmation_contract_visual_header_meta_size'        => array( 'label' => __( 'Tamanho do numero do pedido', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '13px', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta span', 'property' => 'font-size' ) ) ),
                    'post_confirmation_contract_visual_header_meta_line_height' => array( 'label' => __( 'Line-height do numero do pedido', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1.3', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta span', 'property' => 'line-height' ) ) ),
                    'post_confirmation_contract_visual_header_meta_padding'     => array( 'label' => __( 'Padding do texto auxiliar', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta span', 'property' => 'padding' ) ) ),
                    'post_confirmation_contract_visual_header_meta_margin'      => array( 'label' => __( 'Margin do texto auxiliar', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta span', 'property' => 'margin' ) ) ),
                ),
            ),
            array(
                'id'          => 'contract_breadcrumbs',
                'label'       => __( 'Breadcrumbs', EOP_TEXT_DOMAIN ),
                'description' => __( 'Controle o visual da trilha de etapas do contrato.', EOP_TEXT_DOMAIN ),
                'fields'      => array_merge(
                    array(
                        'post_confirmation_contract_visual_breadcrumb_gap' => array( 'label' => __( 'Espacamento entre itens (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '12', 'min' => 0, 'max' => 48, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb', 'property' => 'gap' ) ) ),
                        'post_confirmation_contract_visual_breadcrumb_font_family' => array( 'label' => __( 'Fonte', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'font-family' ) ) ),
                        'post_confirmation_contract_visual_breadcrumb_font_size' => array( 'label' => __( 'Tamanho do texto', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '14px', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'font-size' ) ) ),
                        'post_confirmation_contract_visual_breadcrumb_font_weight' => array( 'label' => __( 'Peso da fonte', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => '700', 'choices' => array( '400' => '400', '500' => '500', '600' => '600', '700' => '700', '800' => '800' ), 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'font-weight' ) ) ),
                        'post_confirmation_contract_visual_breadcrumb_line_height' => array( 'label' => __( 'Line-height do texto', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1.2', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'line-height' ) ) ),
                        'post_confirmation_contract_visual_breadcrumb_padding' => array( 'label' => __( 'Padding dos itens', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '12px 18px', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'padding' ) ) ),
                        'post_confirmation_contract_visual_breadcrumb_border_radius' => array( 'label' => __( 'Radius dos itens', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '999px', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'border-radius' ) ) ),
                        'post_confirmation_contract_visual_breadcrumb_border_width' => array( 'label' => __( 'Borda dos itens', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '1px', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'border-width' ) ) ),
                        'post_confirmation_contract_visual_breadcrumb_border_style' => array( 'label' => __( 'Estilo da borda', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'border-style' ) ) ),
                        'post_confirmation_contract_visual_breadcrumb_border_color' => array( 'label' => __( 'Cor da borda', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#dbe3f0', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'border-color' ) ) ),
                        'post_confirmation_contract_visual_breadcrumb_background_color' => array( 'label' => __( 'Fundo padrao', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'background' ) ) ),
                        'post_confirmation_contract_visual_breadcrumb_text_color' => array( 'label' => __( 'Texto padrao', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'color' ) ) ),
                        'post_confirmation_contract_visual_breadcrumb_current_background_color' => array( 'label' => __( 'Fundo do item atual', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item.is-current', 'property' => 'background' ) ) ),
                        'post_confirmation_contract_visual_breadcrumb_current_text_color' => array( 'label' => __( 'Texto do item atual', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item.is-current', 'property' => 'color' ) ) ),
                        'post_confirmation_contract_visual_breadcrumb_completed_background_color' => array( 'label' => __( 'Fundo do item concluido', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item.is-completed', 'property' => 'background' ) ) ),
                        'post_confirmation_contract_visual_breadcrumb_completed_text_color' => array( 'label' => __( 'Texto do item concluido', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item.is-completed', 'property' => 'color' ) ) ),
                        'post_confirmation_contract_visual_breadcrumb_index_color' => array( 'label' => __( 'Cor do numero', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-index', 'property' => 'color' ) ) ),
                        'post_confirmation_contract_visual_breadcrumb_index_background_color' => array( 'label' => __( 'Fundo do numero', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#eff3fb', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-index', 'property' => 'background' ) ) ),
                        'post_confirmation_contract_visual_breadcrumb_current_index_background_color' => array( 'label' => __( 'Fundo do numero atual', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#00ffff', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item.is-current .eop-post-flow__breadcrumb-index', 'property' => 'background' ) ) ),
                        'post_confirmation_contract_visual_breadcrumb_current_index_color' => array( 'label' => __( 'Cor do numero atual', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item.is-current .eop-post-flow__breadcrumb-index', 'property' => 'color' ) ) ),
                    ),
                    self::get_post_confirmation_contract_breadcrumb_item_style_fields()
                ),
            ),
            array(
                'id'          => 'contract_reader',
                'label'       => __( 'Leitor do documento', EOP_TEXT_DOMAIN ),
                'description' => __( 'Personalize o card do documento principal e sua moldura.', EOP_TEXT_DOMAIN ),
                'fields'      => array(
                    'post_confirmation_contract_visual_reader_background_color' => array( 'label' => __( 'Fundo do leitor', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader', 'property' => 'background' ) ) ),
                    'post_confirmation_contract_visual_reader_padding' => array( 'label' => __( 'Padding do leitor', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader', 'property' => 'padding' ) ) ),
                    'post_confirmation_contract_visual_reader_margin' => array( 'label' => __( 'Margin do leitor', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader', 'property' => 'margin' ) ) ),
                    'post_confirmation_contract_visual_reader_border_radius' => array( 'label' => __( 'Border radius do leitor', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '0px', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_contract_visual_reader_border_width' => array( 'label' => __( 'Borda do leitor', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '0px', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader', 'property' => 'border-width' ) ) ),
                    'post_confirmation_contract_visual_reader_border_style' => array( 'label' => __( 'Estilo da borda do leitor', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-post-flow__document-reader', 'property' => 'border-style' ) ) ),
                    'post_confirmation_contract_visual_reader_border_color' => array( 'label' => __( 'Cor da borda do leitor', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#dbe3f0', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader', 'property' => 'border-color' ) ) ),
                    'post_confirmation_contract_visual_reader_box_shadow' => array( 'label' => __( 'Sombra do leitor', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'default' => 'none', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader', 'property' => 'box-shadow' ) ) ),
                    'post_confirmation_contract_visual_reader_font_family' => array( 'label' => __( 'Fonte do leitor', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader', 'property' => 'font-family' ) ) ),
                    'post_confirmation_contract_visual_reader_title_color' => array( 'label' => __( 'Cor do titulo do documento', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader-head strong', 'property' => 'color' ) ) ),
                    'post_confirmation_contract_visual_reader_title_size' => array( 'label' => __( 'Tamanho do titulo do documento', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '20px', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader-head strong', 'property' => 'font-size' ) ) ),
                    'post_confirmation_contract_visual_reader_title_line_height' => array( 'label' => __( 'Line-height do titulo do documento', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1.2', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader-head strong', 'property' => 'line-height' ) ) ),
                    'post_confirmation_contract_visual_reader_description_color' => array( 'label' => __( 'Cor da descricao do documento', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#66768d', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader-head small', 'property' => 'color' ) ) ),
                    'post_confirmation_contract_visual_reader_description_size' => array( 'label' => __( 'Tamanho da descricao do documento', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '15px', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader-head small', 'property' => 'font-size' ) ) ),
                    'post_confirmation_contract_visual_reader_description_line_height' => array( 'label' => __( 'Line-height da descricao do documento', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1.5', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader-head small', 'property' => 'line-height' ) ) ),
                    'post_confirmation_contract_visual_reader_frame_background_color' => array( 'label' => __( 'Fundo da moldura', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader-frame', 'property' => 'background' ) ) ),
                    'post_confirmation_contract_visual_reader_frame_padding' => array( 'label' => __( 'Padding da moldura', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader-frame', 'property' => 'padding' ) ) ),
                    'post_confirmation_contract_visual_reader_frame_border_radius' => array( 'label' => __( 'Radius da moldura', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '30px', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader-frame', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_contract_visual_reader_frame_border_width' => array( 'label' => __( 'Borda da moldura', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '1px', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader-frame', 'property' => 'border-width' ) ) ),
                    'post_confirmation_contract_visual_reader_frame_border_style' => array( 'label' => __( 'Estilo da borda da moldura', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-post-flow__document-reader-frame', 'property' => 'border-style' ) ) ),
                    'post_confirmation_contract_visual_reader_frame_border_color' => array( 'label' => __( 'Cor da borda da moldura', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#dbe3f0', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader-frame', 'property' => 'border-color' ) ) ),
                    'post_confirmation_contract_visual_reader_content_background_color' => array( 'label' => __( 'Fundo do conteudo HTML', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader-content', 'property' => 'background' ) ) ),
                    'post_confirmation_contract_visual_reader_content_text_color' => array( 'label' => __( 'Cor do conteudo HTML', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader-content', 'property' => 'color' ) ) ),
                    'post_confirmation_contract_visual_reader_content_font_size' => array( 'label' => __( 'Tamanho do conteudo HTML', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '16px', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader-content', 'property' => 'font-size' ) ) ),
                    'post_confirmation_contract_visual_reader_content_line_height' => array( 'label' => __( 'Line-height do conteudo HTML', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1.7', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader-content', 'property' => 'line-height' ) ) ),
                    'post_confirmation_contract_visual_reader_content_padding' => array( 'label' => __( 'Padding do conteudo HTML', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '24px', 'css' => array( array( 'selector' => '.eop-post-flow__document-reader-content', 'property' => 'padding' ) ) ),
                ),
            ),
            array(
                'id'          => 'contract_cards',
                'label'       => __( 'Cards de apoio e aceite', EOP_TEXT_DOMAIN ),
                'description' => __( 'Ajuste os cards adicionais, o aceite e os paineis laterais.', EOP_TEXT_DOMAIN ),
                'fields'      => array(
                    'post_confirmation_contract_visual_support_card_background_color' => array( 'label' => __( 'Fundo dos cards adicionais', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__upload-card', 'property' => 'background' ) ) ),
                    'post_confirmation_contract_visual_support_card_padding' => array( 'label' => __( 'Padding dos cards adicionais', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '24px', 'css' => array( array( 'selector' => '.eop-post-flow__upload-card', 'property' => 'padding' ) ) ),
                    'post_confirmation_contract_visual_support_card_border_radius' => array( 'label' => __( 'Radius dos cards adicionais', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '28px', 'css' => array( array( 'selector' => '.eop-post-flow__upload-card', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_contract_visual_support_card_box_shadow' => array( 'label' => __( 'Sombra dos cards adicionais', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'default' => 'none', 'css' => array( array( 'selector' => '.eop-post-flow__upload-card', 'property' => 'box-shadow' ) ) ),
                    'post_confirmation_contract_visual_support_card_title_color' => array( 'label' => __( 'Cor do titulo dos cards adicionais', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__upload-card strong', 'property' => 'color' ) ) ),
                    'post_confirmation_contract_visual_support_card_title_size' => array( 'label' => __( 'Tamanho do titulo dos cards adicionais', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '18px', 'css' => array( array( 'selector' => '.eop-post-flow__upload-card strong', 'property' => 'font-size' ) ) ),
                    'post_confirmation_contract_visual_support_card_title_line_height' => array( 'label' => __( 'Line-height do titulo dos cards adicionais', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1.2', 'css' => array( array( 'selector' => '.eop-post-flow__upload-card strong', 'property' => 'line-height' ) ) ),
                    'post_confirmation_contract_visual_support_card_text_color' => array( 'label' => __( 'Cor do texto dos cards adicionais', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#66768d', 'css' => array( array( 'selector' => '.eop-post-flow__upload-card small', 'property' => 'color' ) ) ),
                    'post_confirmation_contract_visual_support_card_text_size' => array( 'label' => __( 'Tamanho do texto dos cards adicionais', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '14px', 'css' => array( array( 'selector' => '.eop-post-flow__upload-card small', 'property' => 'font-size' ) ) ),
                    'post_confirmation_contract_visual_support_card_text_line_height' => array( 'label' => __( 'Line-height do texto dos cards adicionais', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1.5', 'css' => array( array( 'selector' => '.eop-post-flow__upload-card small', 'property' => 'line-height' ) ) ),
                    'post_confirmation_contract_visual_acceptance_background_color' => array( 'label' => __( 'Fundo do card de aceite', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__acceptance-card', 'property' => 'background' ) ) ),
                    'post_confirmation_contract_visual_acceptance_padding' => array( 'label' => __( 'Padding do card de aceite', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '20px 0 0', 'css' => array( array( 'selector' => '.eop-post-flow__acceptance-card', 'property' => 'padding' ) ) ),
                    'post_confirmation_contract_visual_acceptance_border_radius' => array( 'label' => __( 'Radius do card de aceite', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '0px', 'css' => array( array( 'selector' => '.eop-post-flow__acceptance-card', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_contract_visual_acceptance_box_shadow' => array( 'label' => __( 'Sombra do card de aceite', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'default' => 'none', 'css' => array( array( 'selector' => '.eop-post-flow__acceptance-card', 'property' => 'box-shadow' ) ) ),
                    'post_confirmation_contract_visual_acceptance_text_color' => array( 'label' => __( 'Cor do texto de aceite', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__checkbox span', 'property' => 'color' ) ) ),
                    'post_confirmation_contract_visual_checkbox_font_family' => array( 'label' => __( 'Fonte do texto de aceite', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'css' => array( array( 'selector' => '.eop-post-flow__checkbox span', 'property' => 'font-family' ) ) ),
                    'post_confirmation_contract_visual_checkbox_font_size' => array( 'label' => __( 'Tamanho do texto de aceite', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '16px', 'css' => array( array( 'selector' => '.eop-post-flow__checkbox span', 'property' => 'font-size' ) ) ),
                    'post_confirmation_contract_visual_checkbox_line_height' => array( 'label' => __( 'Line-height do texto de aceite', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1.4', 'css' => array( array( 'selector' => '.eop-post-flow__checkbox span', 'property' => 'line-height' ) ) ),
                    'post_confirmation_contract_visual_sidebar_background_color' => array( 'label' => __( 'Fundo dos paineis laterais', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#f6f8fc', 'css' => array( array( 'selector' => '.eop-post-flow__panel', 'property' => 'background' ) ) ),
                    'post_confirmation_contract_visual_sidebar_label_color' => array( 'label' => __( 'Cor do titulo dos paineis laterais', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__panel-label', 'property' => 'color' ) ) ),
                    'post_confirmation_contract_visual_sidebar_label_size' => array( 'label' => __( 'Tamanho do titulo dos paineis laterais', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '14px', 'css' => array( array( 'selector' => '.eop-post-flow__panel-label', 'property' => 'font-size' ) ) ),
                    'post_confirmation_contract_visual_sidebar_note_color' => array( 'label' => __( 'Cor do texto auxiliar lateral', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#66768d', 'css' => array( array( 'selector' => '.eop-post-flow__panel-note', 'property' => 'color' ) ) ),
                    'post_confirmation_contract_visual_sidebar_note_size' => array( 'label' => __( 'Tamanho do texto auxiliar lateral', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '13px', 'css' => array( array( 'selector' => '.eop-post-flow__panel-note', 'property' => 'font-size' ) ) ),
                    'post_confirmation_contract_visual_sidebar_padding' => array( 'label' => __( 'Padding dos paineis laterais', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '24px', 'css' => array( array( 'selector' => '.eop-post-flow__panel', 'property' => 'padding' ) ) ),
                    'post_confirmation_contract_visual_summary_value_color' => array( 'label' => __( 'Cor dos valores do resumo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__summary-total-row strong', 'property' => 'color' ) ) ),
                    'post_confirmation_contract_visual_summary_value_size' => array( 'label' => __( 'Tamanho dos valores do resumo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '18px', 'css' => array( array( 'selector' => '.eop-post-flow__summary-total-row strong', 'property' => 'font-size' ) ) ),
                    'post_confirmation_contract_visual_summary_text_color' => array( 'label' => __( 'Cor dos labels do resumo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#66768d', 'css' => array( array( 'selector' => '.eop-post-flow__summary-total-row > span', 'property' => 'color' ) ) ),
                    'post_confirmation_contract_visual_summary_text_size' => array( 'label' => __( 'Tamanho dos labels do resumo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '14px', 'css' => array( array( 'selector' => '.eop-post-flow__summary-total-row > span', 'property' => 'font-size' ) ) ),
                    'post_confirmation_contract_visual_summary_note_color' => array( 'label' => __( 'Cor do texto final do resumo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#66768d', 'css' => array( array( 'selector' => '.eop-post-flow__summary-note', 'property' => 'color' ) ) ),
                    'post_confirmation_contract_visual_summary_note_size' => array( 'label' => __( 'Tamanho do texto final do resumo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '13px', 'css' => array( array( 'selector' => '.eop-post-flow__summary-note', 'property' => 'font-size' ) ) ),
                ),
            ),
            array(
                'id'          => 'contract_buttons',
                'label'       => __( 'Botoes', EOP_TEXT_DOMAIN ),
                'description' => __( 'Controle o CTA principal do aceite e os botoes secundarios dos documentos.', EOP_TEXT_DOMAIN ),
                'fields'      => array(
                    'post_confirmation_contract_visual_primary_button_background_color' => array( 'label' => __( 'Fundo do botao principal', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#00ffff', 'css' => array( array( 'selector' => '.eop-post-flow__form--acceptance .eop-proposal-button', 'property' => 'background' ) ) ),
                    'post_confirmation_contract_visual_primary_button_text_color' => array( 'label' => __( 'Texto do botao principal', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__form--acceptance .eop-proposal-button', 'property' => 'color' ) ) ),
                    'post_confirmation_contract_visual_primary_button_font_family' => array( 'label' => __( 'Fonte do botao principal', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'css' => array( array( 'selector' => '.eop-post-flow__form--acceptance .eop-proposal-button', 'property' => 'font-family' ) ) ),
                    'post_confirmation_contract_visual_primary_button_font_size' => array( 'label' => __( 'Tamanho da fonte do botao principal', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '18px', 'css' => array( array( 'selector' => '.eop-post-flow__form--acceptance .eop-proposal-button', 'property' => 'font-size' ) ) ),
                    'post_confirmation_contract_visual_primary_button_line_height' => array( 'label' => __( 'Line-height do botao principal', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1', 'css' => array( array( 'selector' => '.eop-post-flow__form--acceptance .eop-proposal-button', 'property' => 'line-height' ) ) ),
                    'post_confirmation_contract_visual_primary_button_font_weight' => array( 'label' => __( 'Peso da fonte do botao principal', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => '700', 'choices' => array( '400' => '400', '500' => '500', '600' => '600', '700' => '700', '800' => '800' ), 'css' => array( array( 'selector' => '.eop-post-flow__form--acceptance .eop-proposal-button', 'property' => 'font-weight' ) ) ),
                    'post_confirmation_contract_visual_primary_button_padding' => array( 'label' => __( 'Padding do botao principal', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '16px 24px', 'css' => array( array( 'selector' => '.eop-post-flow__form--acceptance .eop-proposal-button', 'property' => 'padding' ) ) ),
                    'post_confirmation_contract_visual_primary_button_margin' => array( 'label' => __( 'Margin do botao principal', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__form--acceptance .eop-proposal-button', 'property' => 'margin' ) ) ),
                    'post_confirmation_contract_visual_primary_button_border_width' => array( 'label' => __( 'Borda do botao principal', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '0px', 'css' => array( array( 'selector' => '.eop-post-flow__form--acceptance .eop-proposal-button', 'property' => 'border-width' ) ) ),
                    'post_confirmation_contract_visual_primary_button_border_style' => array( 'label' => __( 'Estilo da borda do botao principal', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-post-flow__form--acceptance .eop-proposal-button', 'property' => 'border-style' ) ) ),
                    'post_confirmation_contract_visual_primary_button_border_color' => array( 'label' => __( 'Cor da borda do botao principal', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#00ffff', 'css' => array( array( 'selector' => '.eop-post-flow__form--acceptance .eop-proposal-button', 'property' => 'border-color' ) ) ),
                    'post_confirmation_contract_visual_primary_button_border_radius' => array( 'label' => __( 'Radius do botao principal', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '18px', 'css' => array( array( 'selector' => '.eop-post-flow__form--acceptance .eop-proposal-button', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_contract_visual_primary_button_box_shadow' => array( 'label' => __( 'Sombra do botao principal', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'default' => '0 16px 30px rgba(0, 255, 255, .20)', 'css' => array( array( 'selector' => '.eop-post-flow__form--acceptance .eop-proposal-button', 'property' => 'box-shadow' ) ) ),
                    'post_confirmation_contract_visual_secondary_button_background_color' => array( 'label' => __( 'Fundo dos botoes secundarios', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__upload-card .eop-proposal-button--secondary', 'property' => 'background' ) ) ),
                    'post_confirmation_contract_visual_secondary_button_text_color' => array( 'label' => __( 'Texto dos botoes secundarios', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__upload-card .eop-proposal-button--secondary', 'property' => 'color' ) ) ),
                    'post_confirmation_contract_visual_secondary_button_font_family' => array( 'label' => __( 'Fonte dos botoes secundarios', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'css' => array( array( 'selector' => '.eop-post-flow__upload-card .eop-proposal-button--secondary', 'property' => 'font-family' ) ) ),
                    'post_confirmation_contract_visual_secondary_button_font_size' => array( 'label' => __( 'Tamanho da fonte dos botoes secundarios', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '15px', 'css' => array( array( 'selector' => '.eop-post-flow__upload-card .eop-proposal-button--secondary', 'property' => 'font-size' ) ) ),
                    'post_confirmation_contract_visual_secondary_button_line_height' => array( 'label' => __( 'Line-height dos botoes secundarios', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1', 'css' => array( array( 'selector' => '.eop-post-flow__upload-card .eop-proposal-button--secondary', 'property' => 'line-height' ) ) ),
                    'post_confirmation_contract_visual_secondary_button_font_weight' => array( 'label' => __( 'Peso da fonte dos botoes secundarios', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => '700', 'choices' => array( '400' => '400', '500' => '500', '600' => '600', '700' => '700', '800' => '800' ), 'css' => array( array( 'selector' => '.eop-post-flow__upload-card .eop-proposal-button--secondary', 'property' => 'font-weight' ) ) ),
                    'post_confirmation_contract_visual_secondary_button_padding' => array( 'label' => __( 'Padding dos botoes secundarios', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '12px 18px', 'css' => array( array( 'selector' => '.eop-post-flow__upload-card .eop-proposal-button--secondary', 'property' => 'padding' ) ) ),
                    'post_confirmation_contract_visual_secondary_button_border_color' => array( 'label' => __( 'Borda dos botoes secundarios', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#dbe3f0', 'css' => array( array( 'selector' => '.eop-post-flow__upload-card .eop-proposal-button--secondary', 'property' => 'border-color' ) ) ),
                    'post_confirmation_contract_visual_secondary_button_border_width' => array( 'label' => __( 'Largura da borda dos botoes secundarios', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '1px', 'css' => array( array( 'selector' => '.eop-post-flow__upload-card .eop-proposal-button--secondary', 'property' => 'border-width' ) ) ),
                    'post_confirmation_contract_visual_secondary_button_border_style' => array( 'label' => __( 'Estilo da borda dos botoes secundarios', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-post-flow__upload-card .eop-proposal-button--secondary', 'property' => 'border-style' ) ) ),
                    'post_confirmation_contract_visual_secondary_button_border_radius' => array( 'label' => __( 'Radius dos botoes secundarios', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '18px', 'css' => array( array( 'selector' => '.eop-post-flow__upload-card .eop-proposal-button--secondary', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_contract_visual_secondary_button_box_shadow' => array( 'label' => __( 'Sombra dos botoes secundarios', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'default' => 'none', 'css' => array( array( 'selector' => '.eop-post-flow__upload-card .eop-proposal-button--secondary', 'property' => 'box-shadow' ) ) ),
                ),
            ),
        );
    }

    public static function get_post_confirmation_upload_products_style_sections() {
        return array(
            array(
                'id'          => 'header',
                'label'       => __( 'Header superior', EOP_TEXT_DOMAIN ),
                'description' => __( 'Controle o topo azul com logo e informacoes do pedido.', EOP_TEXT_DOMAIN ),
                'fields'      => array(
                    'post_confirmation_visual_header_background_color' => array( 'label' => __( 'Fundo do header', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#0f1b35', 'css' => array( array( 'selector' => '.eop-post-flow--final-step .eop-post-flow__contract-header', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_header_padding'          => array( 'label' => __( 'Padding', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '30px 32px 28px', 'css' => array( array( 'selector' => '.eop-post-flow--final-step .eop-post-flow__contract-header', 'property' => 'padding' ) ) ),
                    'post_confirmation_visual_header_margin'           => array( 'label' => __( 'Margin', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0px 0px 22px', 'css' => array( array( 'selector' => '.eop-post-flow--final-step .eop-post-flow__contract-header', 'property' => 'margin' ) ) ),
                    'post_confirmation_visual_header_border_radius'    => array( 'label' => __( 'Border radius', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '28px', 'css' => array( array( 'selector' => '.eop-post-flow--final-step .eop-post-flow__contract-header', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_visual_header_border_width'     => array( 'label' => __( 'Borda', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '0px', 'css' => array( array( 'selector' => '.eop-post-flow--final-step .eop-post-flow__contract-header', 'property' => 'border-width' ) ) ),
                    'post_confirmation_visual_header_border_style'     => array( 'label' => __( 'Estilo da borda', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-post-flow--final-step .eop-post-flow__contract-header', 'property' => 'border-style' ) ) ),
                    'post_confirmation_visual_header_border_color'     => array( 'label' => __( 'Cor da borda', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#0f1b35', 'css' => array( array( 'selector' => '.eop-post-flow--final-step .eop-post-flow__contract-header', 'property' => 'border-color' ) ) ),
                    'post_confirmation_visual_header_box_shadow'       => array( 'label' => __( 'Box shadow', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'default' => 'none', 'css' => array( array( 'selector' => '.eop-post-flow--final-step .eop-post-flow__contract-header', 'property' => 'box-shadow' ) ) ),
                    'post_confirmation_visual_header_min_height'       => array( 'label' => __( 'Altura minima', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '0px', 'css' => array( array( 'selector' => '.eop-post-flow--final-step .eop-post-flow__contract-header', 'property' => 'min-height' ) ) ),
                    'post_confirmation_visual_header_card_background_color' => array( 'label' => __( 'Fundo dos cards internos', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#31425f', 'css' => array( array( 'selector' => '.eop-post-flow__contract-brand, .eop-post-flow__contract-meta', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_header_card_border_radius' => array( 'label' => __( 'Radius dos cards internos', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '18px', 'css' => array( array( 'selector' => '.eop-post-flow__contract-brand, .eop-post-flow__contract-meta', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_visual_header_title_color'      => array( 'label' => __( 'Cor do nome da marca', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta strong', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_header_meta_color'       => array( 'label' => __( 'Cor do numero do pedido', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#cfd7ea', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta span', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_header_font_family'      => array( 'label' => __( 'Fonte do header', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta', 'property' => 'font-family' ) ) ),
                    'post_confirmation_visual_header_title_size'       => array( 'label' => __( 'Tamanho do nome da marca', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '18px', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta strong', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_header_title_padding'    => array( 'label' => __( 'Padding do titulo', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta strong', 'property' => 'padding' ) ) ),
                    'post_confirmation_visual_header_title_margin'     => array( 'label' => __( 'Margin do titulo', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta strong', 'property' => 'margin' ) ) ),
                    'post_confirmation_visual_header_meta_size'        => array( 'label' => __( 'Tamanho do pedido', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '13px', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta span', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_header_meta_padding'     => array( 'label' => __( 'Padding da descricao', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta span', 'property' => 'padding' ) ) ),
                    'post_confirmation_visual_header_meta_margin'      => array( 'label' => __( 'Margin da descricao', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__contract-meta span', 'property' => 'margin' ) ) ),
                ),
            ),
            array(
                'id'          => 'breadcrumbs',
                'label'       => __( 'Breadcrumbs', EOP_TEXT_DOMAIN ),
                'description' => __( 'Ajuste as pilulas de progresso no topo da jornada.', EOP_TEXT_DOMAIN ),
                'fields'      => array_merge(
                    array(
                        'post_confirmation_visual_breadcrumb_gap'                     => array( 'label' => __( 'Espacamento entre itens (px)', EOP_TEXT_DOMAIN ), 'type' => 'number', 'default' => '12', 'min' => 0, 'max' => 48, 'unit' => 'px', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb', 'property' => 'gap' ) ) ),
                        'post_confirmation_visual_breadcrumb_font_family'             => array( 'label' => __( 'Fonte', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'font-family' ) ) ),
                        'post_confirmation_visual_breadcrumb_font_size'               => array( 'label' => __( 'Tamanho do texto', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '14px', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'font-size' ) ) ),
                        'post_confirmation_visual_breadcrumb_font_weight' => array( 'label' => __( 'Peso da fonte', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => '700', 'choices' => array( '400' => '400', '500' => '500', '600' => '600', '700' => '700', '800' => '800' ), 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'font-weight' ) ) ),
                        'post_confirmation_visual_breadcrumb_line_height' => array( 'label' => __( 'Line-height do texto', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1.2', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'line-height' ) ) ),
                        'post_confirmation_visual_breadcrumb_padding' => array( 'label' => __( 'Padding dos itens', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '12px 18px', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'padding' ) ) ),
                        'post_confirmation_visual_breadcrumb_border_radius' => array( 'label' => __( 'Radius dos itens', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '999px', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'border-radius' ) ) ),
                        'post_confirmation_visual_breadcrumb_border_width' => array( 'label' => __( 'Borda dos itens', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '1px', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'border-width' ) ) ),
                        'post_confirmation_visual_breadcrumb_border_style' => array( 'label' => __( 'Estilo da borda', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'border-style' ) ) ),
                        'post_confirmation_visual_breadcrumb_border_color' => array( 'label' => __( 'Cor da borda', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#dbe3f0', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'border-color' ) ) ),
                        'post_confirmation_visual_breadcrumb_background_color' => array( 'label' => __( 'Fundo padrao', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'background' ) ) ),
                        'post_confirmation_visual_breadcrumb_text_color' => array( 'label' => __( 'Texto padrao', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item', 'property' => 'color' ) ) ),
                        'post_confirmation_visual_breadcrumb_current_background_color' => array( 'label' => __( 'Fundo do item atual', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item.is-current', 'property' => 'background' ) ) ),
                        'post_confirmation_visual_breadcrumb_current_text_color' => array( 'label' => __( 'Texto do item atual', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item.is-current', 'property' => 'color' ) ) ),
                        'post_confirmation_visual_breadcrumb_completed_background_color' => array( 'label' => __( 'Fundo do item concluido', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item.is-completed', 'property' => 'background' ) ) ),
                        'post_confirmation_visual_breadcrumb_completed_text_color' => array( 'label' => __( 'Texto do item concluido', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item.is-completed', 'property' => 'color' ) ) ),
                        'post_confirmation_visual_breadcrumb_index_background_color' => array( 'label' => __( 'Fundo do numero', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#eff3fb', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-index', 'property' => 'background' ) ) ),
                        'post_confirmation_visual_breadcrumb_index_color' => array( 'label' => __( 'Cor do numero', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-index', 'property' => 'color' ) ) ),
                        'post_confirmation_visual_breadcrumb_current_index_background_color' => array( 'label' => __( 'Fundo do numero atual', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#00ffff', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item.is-current .eop-post-flow__breadcrumb-index', 'property' => 'background' ) ) ),
                        'post_confirmation_visual_breadcrumb_current_index_color' => array( 'label' => __( 'Cor do numero atual', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__breadcrumb-item.is-current .eop-post-flow__breadcrumb-index', 'property' => 'color' ) ) ),
                    ),
                    self::get_post_confirmation_upload_products_breadcrumb_item_style_fields()
                ),
            ),
            array(
                'id'          => 'intro',
                'label'       => __( 'Bloco de titulo', EOP_TEXT_DOMAIN ),
                'description' => __( 'Personalize o card inicial com label superior, titulo e descricao.', EOP_TEXT_DOMAIN ),
                'fields'      => array(
                    'post_confirmation_visual_intro_background_color' => array( 'label' => __( 'Fundo do bloco', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => 'transparent', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_intro_padding'          => array( 'label' => __( 'Padding', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro', 'property' => 'padding' ) ) ),
                    'post_confirmation_visual_intro_margin'           => array( 'label' => __( 'Margin', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro', 'property' => 'margin' ) ) ),
                    'post_confirmation_visual_intro_border_radius'    => array( 'label' => __( 'Border radius', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '0px', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_visual_intro_border_width'     => array( 'label' => __( 'Borda', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '0px', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro', 'property' => 'border-width' ) ) ),
                    'post_confirmation_visual_intro_border_style'     => array( 'label' => __( 'Estilo da borda', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'none', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-post-flow__final-intro', 'property' => 'border-style' ) ) ),
                    'post_confirmation_visual_intro_border_color'     => array( 'label' => __( 'Cor da borda', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#dbe3f0', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro', 'property' => 'border-color' ) ) ),
                    'post_confirmation_visual_intro_box_shadow'       => array( 'label' => __( 'Box shadow', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'default' => 'none', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro', 'property' => 'box-shadow' ) ) ),
                    'post_confirmation_visual_intro_font_family'      => array( 'label' => __( 'Fonte', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro', 'property' => 'font-family' ) ) ),
                    'post_confirmation_visual_intro_eyebrow_color'    => array( 'label' => __( 'Cor da etiqueta superior', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#64748b', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro-eyebrow', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_intro_eyebrow_size'     => array( 'label' => __( 'Tamanho da etiqueta', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '15px', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro-eyebrow', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_intro_title_color'      => array( 'label' => __( 'Cor do titulo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro-title', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_intro_title_size'       => array( 'label' => __( 'Tamanho do titulo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '56px', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro-title', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_intro_title_line_height' => array( 'label' => __( 'Line-height do titulo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro-title', 'property' => 'line-height' ) ) ),
                    'post_confirmation_visual_intro_title_padding'    => array( 'label' => __( 'Padding do titulo', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro-title', 'property' => 'padding' ) ) ),
                    'post_confirmation_visual_intro_title_margin'     => array( 'label' => __( 'Margin do titulo', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro-title', 'property' => 'margin' ) ) ),
                    'post_confirmation_visual_intro_text_color'       => array( 'label' => __( 'Cor da descricao', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#66768d', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro-text', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_intro_text_size'        => array( 'label' => __( 'Tamanho da descricao', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '17px', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro-text', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_intro_text_line_height' => array( 'label' => __( 'Line-height da descricao', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '2', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro-text', 'property' => 'line-height' ) ) ),
                    'post_confirmation_visual_intro_text_padding'     => array( 'label' => __( 'Padding da descricao', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro-text', 'property' => 'padding' ) ) ),
                    'post_confirmation_visual_intro_text_margin'      => array( 'label' => __( 'Margin da descricao', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__final-intro-text', 'property' => 'margin' ) ) ),
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
                    'post_confirmation_visual_upload_border_radius'    => array( 'label' => __( 'Radius do bloco', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '0px', 'css' => array( array( 'selector' => '.eop-post-flow__final-step-card .eop-post-flow__final-block:first-child', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_visual_upload_border_width'     => array( 'label' => __( 'Borda do bloco', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '0px', 'css' => array( array( 'selector' => '.eop-post-flow__final-step-card .eop-post-flow__final-block:first-child', 'property' => 'border-width' ) ) ),
                    'post_confirmation_visual_upload_border_style'     => array( 'label' => __( 'Estilo da borda do bloco', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-post-flow__final-step-card .eop-post-flow__final-block:first-child', 'property' => 'border-style' ) ) ),
                    'post_confirmation_visual_upload_border_color'     => array( 'label' => __( 'Cor da borda do bloco', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#dbe3f0', 'css' => array( array( 'selector' => '.eop-post-flow__final-step-card .eop-post-flow__final-block:first-child', 'property' => 'border-color' ) ) ),
                    'post_confirmation_visual_upload_box_shadow'       => array( 'label' => __( 'Sombra do bloco', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'default' => 'none', 'css' => array( array( 'selector' => '.eop-post-flow__final-step-card .eop-post-flow__final-block:first-child', 'property' => 'box-shadow' ) ) ),
                    'post_confirmation_visual_upload_label_color'      => array( 'label' => __( 'Cor da label do arquivo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#64748b', 'css' => array( array( 'selector' => '.eop-post-flow__field--file > span', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_upload_label_size'       => array( 'label' => __( 'Tamanho da label', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '15px', 'css' => array( array( 'selector' => '.eop-post-flow__field--file > span', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_upload_label_line_height' => array( 'label' => __( 'Line-height da label', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1', 'css' => array( array( 'selector' => '.eop-post-flow__field--file > span', 'property' => 'line-height' ) ) ),
                    'post_confirmation_visual_upload_input_background_color' => array( 'label' => __( 'Fundo do campo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__field--file input', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_upload_input_text_color' => array( 'label' => __( 'Cor do texto do campo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__field--file input', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_upload_input_font_size' => array( 'label' => __( 'Tamanho do texto do campo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '16px', 'css' => array( array( 'selector' => '.eop-post-flow__field--file input', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_upload_input_line_height' => array( 'label' => __( 'Line-height do campo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1', 'css' => array( array( 'selector' => '.eop-post-flow__field--file input', 'property' => 'line-height' ) ) ),
                    'post_confirmation_visual_upload_input_border_width' => array( 'label' => __( 'Largura da borda do campo', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '1px', 'css' => array( array( 'selector' => '.eop-post-flow__field--file input', 'property' => 'border-width' ) ) ),
                    'post_confirmation_visual_upload_input_border_style' => array( 'label' => __( 'Estilo da borda do campo', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-post-flow__field--file input', 'property' => 'border-style' ) ) ),
                    'post_confirmation_visual_upload_input_border_color' => array( 'label' => __( 'Cor da borda do campo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#d6defd', 'css' => array( array( 'selector' => '.eop-post-flow__field--file input', 'property' => 'border-color' ) ) ),
                    'post_confirmation_visual_upload_input_border_radius' => array( 'label' => __( 'Radius do campo', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '18px', 'css' => array( array( 'selector' => '.eop-post-flow__field--file input', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_visual_upload_meta_background_color' => array( 'label' => __( 'Fundo do card do anexo salvo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#eef3ff', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_upload_meta_border_width' => array( 'label' => __( 'Borda do card do anexo', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '0px', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta', 'property' => 'border-width' ) ) ),
                    'post_confirmation_visual_upload_meta_border_style' => array( 'label' => __( 'Estilo da borda do card do anexo', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta', 'property' => 'border-style' ) ) ),
                    'post_confirmation_visual_upload_meta_border_color' => array( 'label' => __( 'Cor da borda do card do anexo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#d6defd', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta', 'property' => 'border-color' ) ) ),
                    'post_confirmation_visual_upload_meta_title_color' => array( 'label' => __( 'Cor do nome do anexo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta strong', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_upload_meta_title_size' => array( 'label' => __( 'Tamanho do nome do anexo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '15px', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta strong', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_upload_meta_title_line_height' => array( 'label' => __( 'Line-height do nome do anexo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta strong', 'property' => 'line-height' ) ) ),
                    'post_confirmation_visual_upload_meta_text_color'  => array( 'label' => __( 'Cor da data do anexo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#66768d', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta small', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_upload_meta_text_size'  => array( 'label' => __( 'Tamanho da data do anexo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '14px', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta small', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_upload_meta_text_line_height' => array( 'label' => __( 'Line-height da data do anexo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta small', 'property' => 'line-height' ) ) ),
                    'post_confirmation_visual_upload_meta_padding'     => array( 'label' => __( 'Padding do card do anexo', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '28px', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta', 'property' => 'padding' ) ) ),
                    'post_confirmation_visual_upload_meta_border_radius' => array( 'label' => __( 'Radius do card do anexo', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '26px', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_visual_upload_meta_button_background_color' => array( 'label' => __( 'Fundo do botao do anexo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta .eop-proposal-button--secondary', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_upload_meta_button_text_color' => array( 'label' => __( 'Cor do texto do botao do anexo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta .eop-proposal-button--secondary', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_upload_meta_button_font_family' => array( 'label' => __( 'Fonte do botao do anexo', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta .eop-proposal-button--secondary', 'property' => 'font-family' ) ) ),
                    'post_confirmation_visual_upload_meta_button_font_size' => array( 'label' => __( 'Tamanho do texto do botao do anexo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '14px', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta .eop-proposal-button--secondary', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_upload_meta_button_line_height' => array( 'label' => __( 'Line-height do botao do anexo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta .eop-proposal-button--secondary', 'property' => 'line-height' ) ) ),
                    'post_confirmation_visual_upload_meta_button_font_weight' => array( 'label' => __( 'Peso da fonte do botao do anexo', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => '700', 'choices' => array( '400' => '400', '500' => '500', '600' => '600', '700' => '700', '800' => '800' ), 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta .eop-proposal-button--secondary', 'property' => 'font-weight' ) ) ),
                    'post_confirmation_visual_upload_meta_button_padding' => array( 'label' => __( 'Padding do botao do anexo', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '12px 18px', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta .eop-proposal-button--secondary', 'property' => 'padding' ) ) ),
                    'post_confirmation_visual_upload_meta_button_border_width' => array( 'label' => __( 'Borda do botao do anexo', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '1px', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta .eop-proposal-button--secondary', 'property' => 'border-width' ) ) ),
                    'post_confirmation_visual_upload_meta_button_border_style' => array( 'label' => __( 'Estilo da borda do botao do anexo', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta .eop-proposal-button--secondary', 'property' => 'border-style' ) ) ),
                    'post_confirmation_visual_upload_meta_button_border_color' => array( 'label' => __( 'Cor da borda do botao do anexo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#d6defd', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta .eop-proposal-button--secondary', 'property' => 'border-color' ) ) ),
                    'post_confirmation_visual_upload_meta_button_border_radius' => array( 'label' => __( 'Radius do botao do anexo', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '18px', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta .eop-proposal-button--secondary', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_visual_upload_meta_button_box_shadow' => array( 'label' => __( 'Sombra do botao do anexo', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'default' => 'none', 'css' => array( array( 'selector' => '.eop-post-flow__final-upload-meta .eop-proposal-button--secondary', 'property' => 'box-shadow' ) ) ),
                ),
            ),
            array(
                'id'          => 'products',
                'label'       => __( 'Produtos', EOP_TEXT_DOMAIN ),
                'description' => __( 'Controle o bloco de personalizacao dos produtos e as linhas da lista.', EOP_TEXT_DOMAIN ),
                'fields'      => array(
                    'post_confirmation_visual_products_block_background_color' => array( 'label' => __( 'Fundo do bloco de produtos', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__final-step-card .eop-post-flow__final-block:last-child', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_products_block_padding' => array( 'label' => __( 'Padding do bloco de produtos', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__final-step-card .eop-post-flow__final-block:last-child', 'property' => 'padding' ) ) ),
                    'post_confirmation_visual_products_block_margin' => array( 'label' => __( 'Margin do bloco de produtos', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '0', 'css' => array( array( 'selector' => '.eop-post-flow__final-step-card .eop-post-flow__final-block:last-child', 'property' => 'margin' ) ) ),
                    'post_confirmation_visual_products_block_border_radius' => array( 'label' => __( 'Radius do bloco de produtos', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '0px', 'css' => array( array( 'selector' => '.eop-post-flow__final-step-card .eop-post-flow__final-block:last-child', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_visual_products_block_border_width' => array( 'label' => __( 'Borda do bloco de produtos', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '0px', 'css' => array( array( 'selector' => '.eop-post-flow__final-step-card .eop-post-flow__final-block:last-child', 'property' => 'border-width' ) ) ),
                    'post_confirmation_visual_products_block_border_style' => array( 'label' => __( 'Estilo da borda do bloco de produtos', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-post-flow__final-step-card .eop-post-flow__final-block:last-child', 'property' => 'border-style' ) ) ),
                    'post_confirmation_visual_products_block_border_color' => array( 'label' => __( 'Cor da borda do bloco de produtos', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#dbe3f0', 'css' => array( array( 'selector' => '.eop-post-flow__final-step-card .eop-post-flow__final-block:last-child', 'property' => 'border-color' ) ) ),
                    'post_confirmation_visual_products_block_box_shadow' => array( 'label' => __( 'Sombra do bloco de produtos', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'default' => 'none', 'css' => array( array( 'selector' => '.eop-post-flow__final-step-card .eop-post-flow__final-block:last-child', 'property' => 'box-shadow' ) ) ),
                    'post_confirmation_visual_products_title_color' => array( 'label' => __( 'Cor do titulo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__final-block-head strong', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_products_title_size' => array( 'label' => __( 'Tamanho do titulo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '20px', 'css' => array( array( 'selector' => '.eop-post-flow__final-block-head strong', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_products_title_line_height' => array( 'label' => __( 'Line-height do titulo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1', 'css' => array( array( 'selector' => '.eop-post-flow__final-block-head strong', 'property' => 'line-height' ) ) ),
                    'post_confirmation_visual_products_description_color' => array( 'label' => __( 'Cor da descricao', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#66768d', 'css' => array( array( 'selector' => '.eop-post-flow__final-block-head small', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_products_description_size' => array( 'label' => __( 'Tamanho da descricao', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '16px', 'css' => array( array( 'selector' => '.eop-post-flow__final-block-head small', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_products_description_line_height' => array( 'label' => __( 'Line-height da descricao', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '2', 'css' => array( array( 'selector' => '.eop-post-flow__final-block-head small', 'property' => 'line-height' ) ) ),
                    'post_confirmation_visual_products_heading_color' => array( 'label' => __( 'Cor do cabecalho da tabela', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#64748b', 'css' => array( array( 'selector' => '.eop-post-flow__final-products-head', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_products_heading_size' => array( 'label' => __( 'Tamanho do cabecalho', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '13px', 'css' => array( array( 'selector' => '.eop-post-flow__final-products-head', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_products_heading_line_height' => array( 'label' => __( 'Line-height do cabecalho', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1', 'css' => array( array( 'selector' => '.eop-post-flow__final-products-head', 'property' => 'line-height' ) ) ),
                    'post_confirmation_visual_products_font_family'  => array( 'label' => __( 'Fonte da secao', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'css' => array( array( 'selector' => '.eop-post-flow__final-products-list, .eop-post-flow__final-block-head', 'property' => 'font-family' ) ) ),
                    'post_confirmation_visual_products_row_background_color' => array( 'label' => __( 'Fundo das linhas', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__final-product-row', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_products_row_border_width' => array( 'label' => __( 'Borda das linhas', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '1px', 'css' => array( array( 'selector' => '.eop-post-flow__final-product-row', 'property' => 'border-width' ) ) ),
                    'post_confirmation_visual_products_row_border_style' => array( 'label' => __( 'Estilo da borda das linhas', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-post-flow__final-product-row', 'property' => 'border-style' ) ) ),
                    'post_confirmation_visual_products_row_border_color' => array( 'label' => __( 'Cor da borda das linhas', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#dbe3f0', 'css' => array( array( 'selector' => '.eop-post-flow__final-product-row', 'property' => 'border-color' ) ) ),
                    'post_confirmation_visual_products_row_border_radius' => array( 'label' => __( 'Radius das linhas', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '28px', 'css' => array( array( 'selector' => '.eop-post-flow__final-product-row', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_visual_products_row_padding'  => array( 'label' => __( 'Padding das linhas', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '26px', 'css' => array( array( 'selector' => '.eop-post-flow__final-product-row', 'property' => 'padding' ) ) ),
                    'post_confirmation_visual_products_row_shadow'   => array( 'label' => __( 'Sombra das linhas', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'default' => 'none', 'css' => array( array( 'selector' => '.eop-post-flow__final-product-row', 'property' => 'box-shadow' ) ) ),
                    'post_confirmation_visual_products_name_color'   => array( 'label' => __( 'Cor do nome do produto', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__final-product-copy strong', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_products_name_size'   => array( 'label' => __( 'Tamanho do nome do produto', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '18px', 'css' => array( array( 'selector' => '.eop-post-flow__final-product-copy strong', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_products_name_line_height' => array( 'label' => __( 'Line-height do nome do produto', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1', 'css' => array( array( 'selector' => '.eop-post-flow__final-product-copy strong', 'property' => 'line-height' ) ) ),
                    'post_confirmation_visual_products_sku_color'    => array( 'label' => __( 'Cor do SKU', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#66768d', 'css' => array( array( 'selector' => '.eop-post-flow__final-product-copy small', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_products_sku_size'    => array( 'label' => __( 'Tamanho do SKU', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '14px', 'css' => array( array( 'selector' => '.eop-post-flow__final-product-copy small', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_products_sku_line_height' => array( 'label' => __( 'Line-height do SKU', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1', 'css' => array( array( 'selector' => '.eop-post-flow__final-product-copy small', 'property' => 'line-height' ) ) ),
                    'post_confirmation_visual_products_input_background_color' => array( 'label' => __( 'Fundo do input do novo nome', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-post-flow__final-name-field input', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_products_input_text_color' => array( 'label' => __( 'Cor do input do novo nome', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a', 'css' => array( array( 'selector' => '.eop-post-flow__final-name-field input', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_products_input_font_size' => array( 'label' => __( 'Tamanho do input do novo nome', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '16px', 'css' => array( array( 'selector' => '.eop-post-flow__final-name-field input', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_products_input_line_height' => array( 'label' => __( 'Line-height do input do novo nome', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1', 'css' => array( array( 'selector' => '.eop-post-flow__final-name-field input', 'property' => 'line-height' ) ) ),
                    'post_confirmation_visual_products_input_border_width' => array( 'label' => __( 'Largura da borda do input', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '1px', 'css' => array( array( 'selector' => '.eop-post-flow__final-name-field input', 'property' => 'border-width' ) ) ),
                    'post_confirmation_visual_products_input_border_style' => array( 'label' => __( 'Estilo da borda do input', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-post-flow__final-name-field input', 'property' => 'border-style' ) ) ),
                    'post_confirmation_visual_products_input_border_color' => array( 'label' => __( 'Borda do input do novo nome', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#c9d3e6', 'css' => array( array( 'selector' => '.eop-post-flow__final-name-field input', 'property' => 'border-color' ) ) ),
                    'post_confirmation_visual_products_input_border_radius' => array( 'label' => __( 'Radius do input', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '8px', 'css' => array( array( 'selector' => '.eop-post-flow__final-name-field input', 'property' => 'border-radius' ) ) ),
                ),
            ),
            array(
                'id'          => 'button',
                'label'       => __( 'Botao principal', EOP_TEXT_DOMAIN ),
                'description' => __( 'Defina o visual do CTA final da etapa.', EOP_TEXT_DOMAIN ),
                'fields'      => array(
                    'post_confirmation_visual_button_background_color' => array( 'label' => __( 'Fundo do botao', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#00ffff', 'css' => array( array( 'selector' => '.eop-proposal-button.eop-post-flow__final-submit', 'property' => 'background' ) ) ),
                    'post_confirmation_visual_button_text_color'       => array( 'label' => __( 'Cor do texto', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'css' => array( array( 'selector' => '.eop-proposal-button.eop-post-flow__final-submit', 'property' => 'color' ) ) ),
                    'post_confirmation_visual_button_font_family'      => array( 'label' => __( 'Fonte', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'css' => array( array( 'selector' => '.eop-proposal-button.eop-post-flow__final-submit', 'property' => 'font-family' ) ) ),
                    'post_confirmation_visual_button_font_size'        => array( 'label' => __( 'Tamanho da fonte', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '20px', 'css' => array( array( 'selector' => '.eop-proposal-button.eop-post-flow__final-submit', 'property' => 'font-size' ) ) ),
                    'post_confirmation_visual_button_line_height'      => array( 'label' => __( 'Line-height da fonte', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '1', 'css' => array( array( 'selector' => '.eop-proposal-button.eop-post-flow__final-submit', 'property' => 'line-height' ) ) ),
                    'post_confirmation_visual_button_font_weight'      => array( 'label' => __( 'Peso da fonte', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => '700', 'choices' => array( '400' => '400', '500' => '500', '600' => '600', '700' => '700', '800' => '800' ), 'css' => array( array( 'selector' => '.eop-proposal-button.eop-post-flow__final-submit', 'property' => 'font-weight' ) ) ),
                    'post_confirmation_visual_button_padding'          => array( 'label' => __( 'Padding', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '16px 24px', 'css' => array( array( 'selector' => '.eop-proposal-button.eop-post-flow__final-submit', 'property' => 'padding' ) ) ),
                    'post_confirmation_visual_button_margin'           => array( 'label' => __( 'Margin', EOP_TEXT_DOMAIN ), 'type' => 'box', 'default' => '8px 0 0', 'css' => array( array( 'selector' => '.eop-proposal-button.eop-post-flow__final-submit', 'property' => 'margin' ) ) ),
                    'post_confirmation_visual_button_border_radius'    => array( 'label' => __( 'Border radius', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '24px', 'css' => array( array( 'selector' => '.eop-proposal-button.eop-post-flow__final-submit', 'property' => 'border-radius' ) ) ),
                    'post_confirmation_visual_button_border_width'     => array( 'label' => __( 'Borda', EOP_TEXT_DOMAIN ), 'type' => 'size', 'default' => '0px', 'css' => array( array( 'selector' => '.eop-proposal-button.eop-post-flow__final-submit', 'property' => 'border-width' ) ) ),
                    'post_confirmation_visual_button_border_style'     => array( 'label' => __( 'Estilo da borda', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' ), 'css' => array( array( 'selector' => '.eop-proposal-button.eop-post-flow__final-submit', 'property' => 'border-style' ) ) ),
                    'post_confirmation_visual_button_border_color'     => array( 'label' => __( 'Cor da borda', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#00034b', 'css' => array( array( 'selector' => '.eop-proposal-button.eop-post-flow__final-submit', 'property' => 'border-color' ) ) ),
                    'post_confirmation_visual_button_box_shadow'       => array( 'label' => __( 'Box shadow', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'default' => '0 16px 30px rgba(0, 3, 75, .20)', 'css' => array( array( 'selector' => '.eop-proposal-button.eop-post-flow__final-submit', 'property' => 'box-shadow' ) ) ),
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
            case 'size':
                return self::sanitize_css_size_value( $value, $default );
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

    private static function sanitize_order_link_visual_field( $value, $definition ) {
        $type    = (string) ( $definition['type'] ?? 'text' );
        $default = $definition['default'] ?? '';

        switch ( $type ) {
            case 'color':
                return self::sanitize_color( $value, $default );
            case 'font':
                return self::sanitize_font_family( $value, $default );
            case 'size':
                return self::sanitize_css_size_value( $value, $default );
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

    private static function sanitize_css_size_value( $value, $default = '16px' ) {
        $value = is_string( $value ) ? trim( wp_strip_all_tags( $value ) ) : '';

        if ( '' === $value ) {
            return (string) $default;
        }

        if ( preg_match( '/^\d+(?:\.\d+)?$/', $value ) ) {
            return $value . 'px';
        }

        if ( preg_match( '/[^0-9a-zA-Z#.%\s,\-()\/]/', $value ) ) {
            return (string) $default;
        }

        return $value;
    }

    private static function get_post_confirmation_contract_editor_field_registry() {
        $fill_mode_choices = array(
            'solid'    => __( 'Cor unica', EOP_TEXT_DOMAIN ),
            'gradient' => __( 'Gradiente', EOP_TEXT_DOMAIN ),
        );

        return array_merge(
            array(
                'customer_experience_font_family' => array( 'label' => __( 'Fonte base da pagina', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'full' => true ),
                'customer_experience_title_size' => array( 'label' => __( 'Tamanho do titulo principal da experiencia', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '46px' ),
                'customer_experience_text_size' => array( 'label' => __( 'Tamanho do texto base da experiencia', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => '16px' ),
                'customer_experience_text_color' => array( 'label' => __( 'Cor do texto principal', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a' ),
                'customer_experience_muted_color' => array( 'label' => __( 'Cor do texto auxiliar', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#66768d' ),
                'customer_experience_accent_color' => array( 'label' => __( 'Cor de destaque', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#00ffff' ),
                'customer_experience_background_mode' => array( 'label' => __( 'Modo do fundo da pagina', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'gradient', 'choices' => $fill_mode_choices ),
                'customer_experience_background_color' => array( 'label' => __( 'Cor principal do fundo da pagina', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#edf2fb' ),
                'customer_experience_background_secondary_color' => array( 'label' => __( 'Segunda cor do fundo da pagina', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#f7f9fc' ),
                'customer_experience_panel_background_mode' => array( 'label' => __( 'Modo do fundo do bloco principal', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => $fill_mode_choices ),
                'customer_experience_panel_background_color' => array( 'label' => __( 'Cor principal do bloco principal', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff' ),
                'customer_experience_panel_background_secondary_color' => array( 'label' => __( 'Segunda cor do bloco principal', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#f7f9fc' ),
                'customer_experience_sidebar_background_mode' => array( 'label' => __( 'Modo do fundo da lateral', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => $fill_mode_choices ),
                'customer_experience_sidebar_background_color' => array( 'label' => __( 'Cor principal da lateral', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#f6f8fc' ),
                'customer_experience_sidebar_background_secondary_color' => array( 'label' => __( 'Segunda cor da lateral', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff' ),
                'post_confirmation_stage_contract_label' => array( 'label' => __( 'Texto do breadcrumb 1', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Aceite contratual', EOP_TEXT_DOMAIN ) ),
                'post_confirmation_stage_upload_label' => array( 'label' => __( 'Texto do breadcrumb 2', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Upload e personalização', EOP_TEXT_DOMAIN ) ),
                'post_confirmation_stage_completed_label' => array( 'label' => __( 'Texto do breadcrumb 3', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Conclusão', EOP_TEXT_DOMAIN ) ),
                'post_confirmation_contract_title' => array( 'label' => __( 'Titulo do documento', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Leia e confirme o contrato abaixo', EOP_TEXT_DOMAIN ), 'full' => true ),
                'post_confirmation_contract_document_description' => array( 'label' => __( 'Descricao do documento', EOP_TEXT_DOMAIN ), 'type' => 'textarea', 'default' => __( 'Documento principal exibido na etapa de aceite.', EOP_TEXT_DOMAIN ), 'full' => true ),
                'post_confirmation_contract_checkbox_label' => array( 'label' => __( 'Texto do aceite', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Li e aceito o contrato acima.', EOP_TEXT_DOMAIN ), 'full' => true ),
                'post_confirmation_contract_button_label' => array( 'label' => __( 'Texto do botao principal', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Confirmar e continuar', EOP_TEXT_DOMAIN ) ),
                'customer_experience_financial_title' => array( 'label' => __( 'Titulo do resumo lateral', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => 'Resumo' ),
                'customer_experience_total_label' => array( 'label' => __( 'Label do valor aprovado', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => 'Investimento aprovado' ),
                'customer_experience_subtotal_label' => array( 'label' => __( 'Label do subtotal', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Subtotal', EOP_TEXT_DOMAIN ) ),
                'post_confirmation_contract_shipping_label' => array( 'label' => __( 'Label do frete', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Frete', EOP_TEXT_DOMAIN ) ),
                'customer_experience_discount_label' => array( 'label' => __( 'Label do desconto', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Desconto', EOP_TEXT_DOMAIN ) ),
                'customer_experience_progress_note' => array( 'label' => __( 'Texto final do resumo', EOP_TEXT_DOMAIN ), 'type' => 'textarea', 'default' => 'As próximas etapas são liberadas em sequência para evitar retrabalho.', 'full' => true ),
            ),
            self::get_post_confirmation_contract_style_field_registry()
        );
    }

    private static function get_post_confirmation_contract_editor_sections() {
        $registry = self::get_post_confirmation_contract_editor_field_registry();

        return array(
            array(
                'id'          => 'contract_page_layout',
                'label'       => __( 'Pagina e layout geral', EOP_TEXT_DOMAIN ),
                'description' => __( 'Controle a base visual da pagina contratual antes de entrar nos elementos individuais.', EOP_TEXT_DOMAIN ),
                'expanded'    => true,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Tipografia e paleta', EOP_TEXT_DOMAIN ) => array( 'customer_experience_font_family', 'customer_experience_title_size', 'customer_experience_text_size', 'customer_experience_text_color', 'customer_experience_muted_color', 'customer_experience_accent_color' ),
                        __( 'Fundos', EOP_TEXT_DOMAIN ) => array( 'customer_experience_background_mode', 'customer_experience_background_color', 'customer_experience_background_secondary_color', 'customer_experience_panel_background_mode', 'customer_experience_panel_background_color', 'customer_experience_panel_background_secondary_color', 'customer_experience_sidebar_background_mode', 'customer_experience_sidebar_background_color', 'customer_experience_sidebar_background_secondary_color' ),
                    )
                ),
            ),
            array(
                'id'          => 'contract_header_element',
                'label'       => __( 'Header superior', EOP_TEXT_DOMAIN ),
                'description' => __( 'Visual do topo com logo, nome da marca e identificacao do pedido.', EOP_TEXT_DOMAIN ),
                'expanded'    => true,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Container', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_header_background_color', 'post_confirmation_contract_visual_header_padding', 'post_confirmation_contract_visual_header_margin', 'post_confirmation_contract_visual_header_border_radius', 'post_confirmation_contract_visual_header_border_width', 'post_confirmation_contract_visual_header_border_style', 'post_confirmation_contract_visual_header_border_color', 'post_confirmation_contract_visual_header_box_shadow', 'post_confirmation_contract_visual_header_min_height' ),
                        __( 'Cards internos', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_header_card_background_color', 'post_confirmation_contract_visual_header_card_border_radius' ),
                        __( 'Nome da marca e meta', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_header_font_family', 'post_confirmation_contract_visual_header_title_color', 'post_confirmation_contract_visual_header_title_size', 'post_confirmation_contract_visual_header_title_line_height', 'post_confirmation_contract_visual_header_title_padding', 'post_confirmation_contract_visual_header_title_margin', 'post_confirmation_contract_visual_header_meta_color', 'post_confirmation_contract_visual_header_meta_size', 'post_confirmation_contract_visual_header_meta_line_height', 'post_confirmation_contract_visual_header_meta_padding', 'post_confirmation_contract_visual_header_meta_margin' ),
                    )
                ),
            ),
            array(
                'id'          => 'contract_breadcrumb_1',
                'label'       => __( 'Breadcrumb 1', EOP_TEXT_DOMAIN ),
                'description' => __( 'Texto e estilo do primeiro item da trilha do contrato.', EOP_TEXT_DOMAIN ),
                'expanded'    => true,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Conteudo', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_stage_contract_label' ),
                        __( 'Estrutura', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_breadcrumb_gap' ),
                        __( 'Texto e tipografia', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_breadcrumb_contract_font_family', 'post_confirmation_contract_visual_breadcrumb_contract_font_size', 'post_confirmation_contract_visual_breadcrumb_contract_font_weight', 'post_confirmation_contract_visual_breadcrumb_contract_line_height', 'post_confirmation_contract_visual_breadcrumb_contract_text_color' ),
                        __( 'Fundo e borda', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_breadcrumb_contract_background_color', 'post_confirmation_contract_visual_breadcrumb_contract_padding', 'post_confirmation_contract_visual_breadcrumb_contract_border_radius', 'post_confirmation_contract_visual_breadcrumb_contract_border_width', 'post_confirmation_contract_visual_breadcrumb_contract_border_style', 'post_confirmation_contract_visual_breadcrumb_contract_border_color' ),
                        __( 'Numero', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_breadcrumb_contract_index_background_color', 'post_confirmation_contract_visual_breadcrumb_contract_index_color' ),
                    )
                ),
            ),
            array(
                'id'          => 'contract_breadcrumb_2',
                'label'       => __( 'Breadcrumb 2', EOP_TEXT_DOMAIN ),
                'description' => __( 'Texto e estilo do segundo item da trilha do contrato.', EOP_TEXT_DOMAIN ),
                'expanded'    => false,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Conteudo', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_stage_upload_label' ),
                        __( 'Texto e tipografia', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_breadcrumb_upload_font_family', 'post_confirmation_contract_visual_breadcrumb_upload_font_size', 'post_confirmation_contract_visual_breadcrumb_upload_font_weight', 'post_confirmation_contract_visual_breadcrumb_upload_line_height', 'post_confirmation_contract_visual_breadcrumb_upload_text_color' ),
                        __( 'Fundo e borda', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_breadcrumb_upload_background_color', 'post_confirmation_contract_visual_breadcrumb_upload_padding', 'post_confirmation_contract_visual_breadcrumb_upload_border_radius', 'post_confirmation_contract_visual_breadcrumb_upload_border_width', 'post_confirmation_contract_visual_breadcrumb_upload_border_style', 'post_confirmation_contract_visual_breadcrumb_upload_border_color' ),
                        __( 'Numero', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_breadcrumb_upload_index_background_color', 'post_confirmation_contract_visual_breadcrumb_upload_index_color' ),
                    )
                ),
            ),
            array(
                'id'          => 'contract_breadcrumb_3',
                'label'       => __( 'Breadcrumb 3', EOP_TEXT_DOMAIN ),
                'description' => __( 'Texto e estilo do terceiro item da trilha do contrato.', EOP_TEXT_DOMAIN ),
                'expanded'    => false,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Conteudo', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_stage_completed_label' ),
                        __( 'Texto e tipografia', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_breadcrumb_completed_font_family', 'post_confirmation_contract_visual_breadcrumb_completed_font_size', 'post_confirmation_contract_visual_breadcrumb_completed_font_weight', 'post_confirmation_contract_visual_breadcrumb_completed_line_height', 'post_confirmation_contract_visual_breadcrumb_completed_text_color' ),
                        __( 'Fundo e borda', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_breadcrumb_completed_background_color', 'post_confirmation_contract_visual_breadcrumb_completed_padding', 'post_confirmation_contract_visual_breadcrumb_completed_border_radius', 'post_confirmation_contract_visual_breadcrumb_completed_border_width', 'post_confirmation_contract_visual_breadcrumb_completed_border_style', 'post_confirmation_contract_visual_breadcrumb_completed_border_color' ),
                        __( 'Numero', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_breadcrumb_completed_index_background_color', 'post_confirmation_contract_visual_breadcrumb_completed_index_color' ),
                    )
                ),
            ),
            array(
                'id'          => 'contract_document_element',
                'label'       => __( 'Documento principal', EOP_TEXT_DOMAIN ),
                'description' => __( 'Titulo, descricao e todo o visual do leitor do contrato.', EOP_TEXT_DOMAIN ),
                'expanded'    => true,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Conteudo', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_title', 'post_confirmation_contract_document_description' ),
                        __( 'Bloco do leitor', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_reader_background_color', 'post_confirmation_contract_visual_reader_padding', 'post_confirmation_contract_visual_reader_margin', 'post_confirmation_contract_visual_reader_border_radius', 'post_confirmation_contract_visual_reader_border_width', 'post_confirmation_contract_visual_reader_border_style', 'post_confirmation_contract_visual_reader_border_color', 'post_confirmation_contract_visual_reader_box_shadow', 'post_confirmation_contract_visual_reader_font_family' ),
                        __( 'Titulo e descricao', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_reader_title_color', 'post_confirmation_contract_visual_reader_title_size', 'post_confirmation_contract_visual_reader_title_line_height', 'post_confirmation_contract_visual_reader_description_color', 'post_confirmation_contract_visual_reader_description_size', 'post_confirmation_contract_visual_reader_description_line_height' ),
                        __( 'Moldura e conteudo', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_reader_frame_background_color', 'post_confirmation_contract_visual_reader_frame_padding', 'post_confirmation_contract_visual_reader_frame_border_radius', 'post_confirmation_contract_visual_reader_frame_border_width', 'post_confirmation_contract_visual_reader_frame_border_style', 'post_confirmation_contract_visual_reader_frame_border_color', 'post_confirmation_contract_visual_reader_content_background_color', 'post_confirmation_contract_visual_reader_content_text_color', 'post_confirmation_contract_visual_reader_content_font_size', 'post_confirmation_contract_visual_reader_content_line_height', 'post_confirmation_contract_visual_reader_content_padding' ),
                    )
                ),
            ),
            array(
                'id'          => 'contract_acceptance_element',
                'label'       => __( 'Aceite', EOP_TEXT_DOMAIN ),
                'description' => __( 'Texto e estilo do bloco de confirmacao do contrato.', EOP_TEXT_DOMAIN ),
                'expanded'    => true,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Conteudo', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_checkbox_label' ),
                        __( 'Card de aceite', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_acceptance_background_color', 'post_confirmation_contract_visual_acceptance_padding', 'post_confirmation_contract_visual_acceptance_border_radius', 'post_confirmation_contract_visual_acceptance_box_shadow' ),
                        __( 'Texto do aceite', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_acceptance_text_color', 'post_confirmation_contract_visual_checkbox_font_family', 'post_confirmation_contract_visual_checkbox_font_size', 'post_confirmation_contract_visual_checkbox_line_height' ),
                    )
                ),
            ),
            array(
                'id'          => 'contract_primary_button_element',
                'label'       => __( 'Botao principal', EOP_TEXT_DOMAIN ),
                'description' => __( 'Texto e personalizacao do CTA de confirmacao do contrato.', EOP_TEXT_DOMAIN ),
                'expanded'    => true,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Conteudo', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_button_label' ),
                        __( 'Visual do botao', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_primary_button_background_color', 'post_confirmation_contract_visual_primary_button_text_color', 'post_confirmation_contract_visual_primary_button_font_family', 'post_confirmation_contract_visual_primary_button_font_size', 'post_confirmation_contract_visual_primary_button_line_height', 'post_confirmation_contract_visual_primary_button_font_weight', 'post_confirmation_contract_visual_primary_button_padding', 'post_confirmation_contract_visual_primary_button_margin', 'post_confirmation_contract_visual_primary_button_border_width', 'post_confirmation_contract_visual_primary_button_border_style', 'post_confirmation_contract_visual_primary_button_border_color', 'post_confirmation_contract_visual_primary_button_border_radius', 'post_confirmation_contract_visual_primary_button_box_shadow' ),
                    )
                ),
            ),
            array(
                'id'          => 'contract_summary_element',
                'label'       => __( 'Resumo lateral', EOP_TEXT_DOMAIN ),
                'description' => __( 'Textos e visual do card lateral com totais da etapa contratual.', EOP_TEXT_DOMAIN ),
                'expanded'    => true,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Conteudo', EOP_TEXT_DOMAIN ) => array( 'customer_experience_financial_title', 'customer_experience_total_label', 'customer_experience_subtotal_label', 'post_confirmation_contract_shipping_label', 'customer_experience_discount_label', 'customer_experience_progress_note' ),
                        __( 'Painel lateral', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_sidebar_background_color', 'post_confirmation_contract_visual_sidebar_label_color', 'post_confirmation_contract_visual_sidebar_label_size', 'post_confirmation_contract_visual_sidebar_note_color', 'post_confirmation_contract_visual_sidebar_note_size', 'post_confirmation_contract_visual_sidebar_padding' ),
                        __( 'Linhas e valores', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_summary_value_color', 'post_confirmation_contract_visual_summary_value_size', 'post_confirmation_contract_visual_summary_text_color', 'post_confirmation_contract_visual_summary_text_size', 'post_confirmation_contract_visual_summary_note_color', 'post_confirmation_contract_visual_summary_note_size' ),
                    )
                ),
            ),
            array(
                'id'          => 'contract_support_documents_element',
                'label'       => __( 'Documentos adicionais', EOP_TEXT_DOMAIN ),
                'description' => __( 'Visual dos cards extras e dos botoes secundarios quando houver documentos complementares.', EOP_TEXT_DOMAIN ),
                'expanded'    => false,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Cards adicionais', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_support_card_background_color', 'post_confirmation_contract_visual_support_card_padding', 'post_confirmation_contract_visual_support_card_border_radius', 'post_confirmation_contract_visual_support_card_box_shadow', 'post_confirmation_contract_visual_support_card_title_color', 'post_confirmation_contract_visual_support_card_title_size', 'post_confirmation_contract_visual_support_card_title_line_height', 'post_confirmation_contract_visual_support_card_text_color', 'post_confirmation_contract_visual_support_card_text_size', 'post_confirmation_contract_visual_support_card_text_line_height' ),
                        __( 'Botoes secundarios', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_contract_visual_secondary_button_background_color', 'post_confirmation_contract_visual_secondary_button_text_color', 'post_confirmation_contract_visual_secondary_button_font_family', 'post_confirmation_contract_visual_secondary_button_font_size', 'post_confirmation_contract_visual_secondary_button_line_height', 'post_confirmation_contract_visual_secondary_button_font_weight', 'post_confirmation_contract_visual_secondary_button_padding', 'post_confirmation_contract_visual_secondary_button_border_color', 'post_confirmation_contract_visual_secondary_button_border_width', 'post_confirmation_contract_visual_secondary_button_border_style', 'post_confirmation_contract_visual_secondary_button_border_radius', 'post_confirmation_contract_visual_secondary_button_box_shadow' ),
                    )
                ),
            ),
        );
    }

    private static function render_post_confirmation_contract_visual_editor( $settings ) {
        ?>
        <div class="eop-visual-editor">
            <?php foreach ( self::get_post_confirmation_contract_editor_sections() as $section ) : ?>
                <?php self::render_order_link_visual_section( $section, $settings ); ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private static function render_post_confirmation_contract_accordion_content( $settings ) {
        ?>
        <div class="eop-accordion eop-visual-accordion">
            <button type="button" class="eop-accordion__toggle" aria-expanded="true">
                <span>
                    <strong><?php esc_html_e( 'Conteudo da etapa', EOP_TEXT_DOMAIN ); ?></strong>
                    <small><?php esc_html_e( 'Edite os textos principais do contrato, aceite e resumo lateral.', EOP_TEXT_DOMAIN ); ?></small>
                </span>
                <span class="eop-accordion__icon">-</span>
            </button>
            <div class="eop-accordion__body">
                <div class="eop-visual-groups">
                    <section class="eop-visual-group">
                        <header class="eop-visual-group__head">
                            <strong><?php esc_html_e( 'Documento principal', EOP_TEXT_DOMAIN ); ?></strong>
                        </header>
                        <div class="eop-settings-grid">
                            <div class="eop-settings-field is-full">
                                <label for="eop_post_confirmation_contract_title_visual"><?php esc_html_e( 'Titulo do contrato', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_post_confirmation_contract_title_visual" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_contract_title]" value="<?php echo esc_attr( $settings['post_confirmation_contract_title'] ); ?>" />
                            </div>
                            <div class="eop-settings-field is-full">
                                <label for="eop_post_confirmation_contract_document_description_visual"><?php esc_html_e( 'Descricao do documento principal', EOP_TEXT_DOMAIN ); ?></label>
                                <textarea id="eop_post_confirmation_contract_document_description_visual" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_contract_document_description]"><?php echo esc_textarea( $settings['post_confirmation_contract_document_description'] ); ?></textarea>
                            </div>
                        </div>
                    </section>
                    <section class="eop-visual-group">
                        <header class="eop-visual-group__head">
                            <strong><?php esc_html_e( 'Aceite', EOP_TEXT_DOMAIN ); ?></strong>
                        </header>
                        <div class="eop-settings-grid">
                            <div class="eop-settings-field is-full">
                                <label for="eop_post_confirmation_contract_checkbox_label_visual"><?php esc_html_e( 'Texto do aceite', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_post_confirmation_contract_checkbox_label_visual" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_contract_checkbox_label]" value="<?php echo esc_attr( $settings['post_confirmation_contract_checkbox_label'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_post_confirmation_contract_button_label_visual"><?php esc_html_e( 'Botao principal', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_post_confirmation_contract_button_label_visual" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_contract_button_label]" value="<?php echo esc_attr( $settings['post_confirmation_contract_button_label'] ); ?>" />
                            </div>
                        </div>
                    </section>
                    <section class="eop-visual-group">
                        <header class="eop-visual-group__head">
                            <strong><?php esc_html_e( 'Resumo lateral', EOP_TEXT_DOMAIN ); ?></strong>
                        </header>
                        <div class="eop-settings-grid">
                            <div class="eop-settings-field">
                                <label for="eop_customer_experience_financial_title_visual"><?php esc_html_e( 'Titulo do resumo lateral', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_customer_experience_financial_title_visual" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_financial_title]" value="<?php echo esc_attr( $settings['customer_experience_financial_title'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_customer_experience_total_label_visual"><?php esc_html_e( 'Label do valor aprovado', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_customer_experience_total_label_visual" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_total_label]" value="<?php echo esc_attr( $settings['customer_experience_total_label'] ); ?>" />
                            </div>
                        </div>
                    </section>
                    <section class="eop-visual-group">
                        <header class="eop-visual-group__head">
                            <strong><?php esc_html_e( 'Mapa da jornada', EOP_TEXT_DOMAIN ); ?></strong>
                        </header>
                        <div class="eop-settings-grid">
                            <div class="eop-settings-field">
                                <label for="eop_customer_experience_progress_label_visual"><?php esc_html_e( 'Label do bloco de progresso', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_customer_experience_progress_label_visual" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_progress_label]" value="<?php echo esc_attr( $settings['customer_experience_progress_label'] ); ?>" />
                            </div>
                            <div class="eop-settings-field is-full">
                                <label for="eop_customer_experience_progress_note_journey_visual"><?php esc_html_e( 'Texto auxiliar do bloco de progresso', EOP_TEXT_DOMAIN ); ?></label>
                                <textarea id="eop_customer_experience_progress_note_journey_visual" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_progress_note]"><?php echo esc_textarea( $settings['customer_experience_progress_note'] ); ?></textarea>
                                <small class="eop-settings-help"><?php esc_html_e( 'Este mesmo texto tambem alimenta a observacao final exibida no resumo lateral e no preview desta etapa.', EOP_TEXT_DOMAIN ); ?></small>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_post_confirmation_contract_accordion_global( $settings ) {
        ?>
        <div class="eop-accordion eop-visual-accordion">
            <button type="button" class="eop-accordion__toggle" aria-expanded="false">
                <span>
                    <strong><?php esc_html_e( 'Base visual da experiencia', EOP_TEXT_DOMAIN ); ?></strong>
                    <small><?php esc_html_e( 'Defina a base geral da experiencia publica usada no contrato.', EOP_TEXT_DOMAIN ); ?></small>
                </span>
                <span class="eop-accordion__icon">+</span>
            </button>
            <div class="eop-accordion__body" hidden>
                <div class="eop-visual-groups">
                    <section class="eop-visual-group">
                        <header class="eop-visual-group__head">
                            <strong><?php esc_html_e( 'Tipografia base', EOP_TEXT_DOMAIN ); ?></strong>
                        </header>
                        <div class="eop-settings-grid">
                            <div class="eop-settings-field is-full">
                                <label for="eop_customer_experience_font_family_preview"><?php esc_html_e( 'Fonte da experiencia publica', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_customer_experience_font_family_preview" class="select_font eop-font-field" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_font_family]" value="<?php echo esc_attr( $settings['customer_experience_font_family'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_customer_experience_title_size_preview"><?php esc_html_e( 'Tamanho do titulo principal', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_customer_experience_title_size_preview" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_title_size]" value="<?php echo esc_attr( $settings['customer_experience_title_size'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_customer_experience_text_size_preview"><?php esc_html_e( 'Tamanho do texto base', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_customer_experience_text_size_preview" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_text_size]" value="<?php echo esc_attr( $settings['customer_experience_text_size'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_customer_experience_text_color_preview"><?php esc_html_e( 'Texto principal', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_customer_experience_text_color_preview" class="eop-color-field" type="text" data-default-color="#16243a" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_text_color]" value="<?php echo esc_attr( $settings['customer_experience_text_color'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_customer_experience_muted_color_preview"><?php esc_html_e( 'Texto auxiliar', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_customer_experience_muted_color_preview" class="eop-color-field" type="text" data-default-color="#66768d" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_muted_color]" value="<?php echo esc_attr( $settings['customer_experience_muted_color'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_customer_experience_accent_color_preview"><?php esc_html_e( 'Cor de destaque', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_customer_experience_accent_color_preview" class="eop-color-field" type="text" data-default-color="#00ffff" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_accent_color]" value="<?php echo esc_attr( $settings['customer_experience_accent_color'] ); ?>" />
                            </div>
                        </div>
                    </section>
                    <section class="eop-visual-group">
                        <header class="eop-visual-group__head">
                            <strong><?php esc_html_e( 'Fundos e gradientes', EOP_TEXT_DOMAIN ); ?></strong>
                        </header>
                        <div class="eop-settings-grid">
                            <div class="eop-settings-field">
                                <label for="eop_customer_experience_background_mode_preview"><?php esc_html_e( 'Tipo do fundo da pagina', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_customer_experience_background_mode_preview" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_background_mode]">
                                    <option value="solid"<?php selected( $settings['customer_experience_background_mode'], 'solid' ); ?>><?php esc_html_e( 'Cor unica', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="gradient"<?php selected( $settings['customer_experience_background_mode'], 'gradient' ); ?>><?php esc_html_e( 'Gradiente', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
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
                        </div>
                    </section>
                </div>
            </div>
        </div>
        <?php
    }

    private static function get_post_confirmation_upload_products_style_field_registry() {
        $registry = array();

        foreach ( self::get_post_confirmation_upload_products_style_sections() as $section ) {
            foreach ( (array) ( $section['fields'] ?? array() ) as $field_key => $field ) {
                $registry[ $field_key ] = $field;
            }
        }

        return $registry;
    }

    private static function get_post_confirmation_upload_products_editor_field_registry() {
        $fill_mode_choices = array(
            'solid'    => __( 'Cor unica', EOP_TEXT_DOMAIN ),
            'gradient' => __( 'Gradiente', EOP_TEXT_DOMAIN ),
        );

        return array_merge(
            array(
                'customer_experience_font_family' => array( 'label' => __( 'Fonte base da pagina', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'full' => true ),
                'customer_experience_text_color' => array( 'label' => __( 'Cor do texto principal', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#16243a' ),
                'customer_experience_muted_color' => array( 'label' => __( 'Cor do texto auxiliar', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#66768d' ),
                'customer_experience_accent_color' => array( 'label' => __( 'Cor de destaque', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#00ffff' ),
                'customer_experience_background_mode' => array( 'label' => __( 'Modo do fundo da pagina', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'gradient', 'choices' => $fill_mode_choices ),
                'customer_experience_background_color' => array( 'label' => __( 'Cor principal do fundo da pagina', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#edf2fb' ),
                'customer_experience_background_secondary_color' => array( 'label' => __( 'Segunda cor do fundo da pagina', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#f7f9fc' ),
                'customer_experience_panel_background_mode' => array( 'label' => __( 'Modo do fundo do bloco principal', EOP_TEXT_DOMAIN ), 'type' => 'select', 'default' => 'solid', 'choices' => $fill_mode_choices ),
                'customer_experience_panel_background_color' => array( 'label' => __( 'Cor principal do bloco principal', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff' ),
                'customer_experience_panel_background_secondary_color' => array( 'label' => __( 'Segunda cor do bloco principal', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#f7f9fc' ),
                'post_confirmation_stage_contract_label' => array( 'label' => __( 'Texto do breadcrumb 1', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Aceite contratual', EOP_TEXT_DOMAIN ) ),
                'post_confirmation_stage_upload_label' => array( 'label' => __( 'Texto do breadcrumb 2', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Upload e personalização', EOP_TEXT_DOMAIN ) ),
                'post_confirmation_stage_completed_label' => array( 'label' => __( 'Texto do breadcrumb 3', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Conclusão', EOP_TEXT_DOMAIN ) ),
                'post_confirmation_final_intro_eyebrow' => array( 'label' => __( 'Texto auxiliar superior', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Etapa final do pedido', EOP_TEXT_DOMAIN ) ),
                'post_confirmation_upload_title' => array( 'label' => __( 'Titulo principal da etapa', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Envie o arquivo solicitado', EOP_TEXT_DOMAIN ), 'full' => true ),
                'post_confirmation_upload_description' => array( 'label' => __( 'Descricao principal da etapa', EOP_TEXT_DOMAIN ), 'type' => 'textarea', 'default' => __( 'Aceitamos arquivos JPG, PNG ou PDF.', EOP_TEXT_DOMAIN ), 'full' => true ),
                'post_confirmation_upload_field_label' => array( 'label' => __( 'Label do campo de upload', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Arquivo', EOP_TEXT_DOMAIN ) ),
                'post_confirmation_upload_button_label' => array( 'label' => __( 'Texto do botao na etapa de upload', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Enviar arquivo', EOP_TEXT_DOMAIN ) ),
                'post_confirmation_upload_saved_date_prefix' => array( 'label' => __( 'Prefixo da data do anexo salvo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Enviado em', EOP_TEXT_DOMAIN ) ),
                'post_confirmation_upload_saved_status_text' => array( 'label' => __( 'Texto alternativo sem data', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Arquivo ja salvo no pedido.', EOP_TEXT_DOMAIN ), 'full' => true ),
                'post_confirmation_upload_view_button_label' => array( 'label' => __( 'Texto do botao do anexo salvo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Ver anexo enviado', EOP_TEXT_DOMAIN ) ),
                'post_confirmation_products_title' => array( 'label' => __( 'Titulo da lista de produtos', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Personalize os nomes dos produtos', EOP_TEXT_DOMAIN ), 'full' => true ),
                'post_confirmation_products_description' => array( 'label' => __( 'Descricao da lista de produtos', EOP_TEXT_DOMAIN ), 'type' => 'textarea', 'default' => __( 'Informe como cada nome deve aparecer para os itens liberados.', EOP_TEXT_DOMAIN ), 'full' => true ),
                'post_confirmation_products_heading_sequence_label' => array( 'label' => __( 'Texto da coluna 1', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Seq.', EOP_TEXT_DOMAIN ) ),
                'post_confirmation_products_heading_original_label' => array( 'label' => __( 'Texto da coluna 2', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Produto original', EOP_TEXT_DOMAIN ) ),
                'post_confirmation_products_heading_custom_label' => array( 'label' => __( 'Texto da coluna 3', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Novo nome', EOP_TEXT_DOMAIN ) ),
                'post_confirmation_products_sku_prefix' => array( 'label' => __( 'Prefixo do SKU', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'SKU:', EOP_TEXT_DOMAIN ) ),
                'post_confirmation_products_sku_empty_label' => array( 'label' => __( 'Texto sem SKU', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'SKU não informado', EOP_TEXT_DOMAIN ) ),
                'post_confirmation_products_locked_message' => array( 'label' => __( 'Aviso do item bloqueado', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Este item esta bloqueado para alteracao de nome.', EOP_TEXT_DOMAIN ), 'full' => true ),
                'post_confirmation_products_button_label' => array( 'label' => __( 'Texto do botao principal', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Salvar personalização', EOP_TEXT_DOMAIN ) ),
            ),
            self::get_post_confirmation_upload_products_style_field_registry()
        );
    }

    private static function get_post_confirmation_upload_products_editor_sections() {
        $registry = self::get_post_confirmation_upload_products_editor_field_registry();

        return array(
            array(
                'id'          => 'upload_products_page_layout',
                'label'       => __( 'Pagina e layout geral', EOP_TEXT_DOMAIN ),
                'description' => __( 'Controle a base visual da etapa final antes de entrar nos elementos individuais.', EOP_TEXT_DOMAIN ),
                'expanded'    => true,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Tipografia e paleta', EOP_TEXT_DOMAIN ) => array( 'customer_experience_font_family', 'customer_experience_text_color', 'customer_experience_muted_color', 'customer_experience_accent_color' ),
                        __( 'Fundos', EOP_TEXT_DOMAIN ) => array( 'customer_experience_background_mode', 'customer_experience_background_color', 'customer_experience_background_secondary_color', 'customer_experience_panel_background_mode', 'customer_experience_panel_background_color', 'customer_experience_panel_background_secondary_color' ),
                    )
                ),
            ),
            array(
                'id'          => 'upload_products_header_element',
                'label'       => __( 'Header superior', EOP_TEXT_DOMAIN ),
                'description' => __( 'Visual do topo com logo, nome da marca e identificacao do pedido.', EOP_TEXT_DOMAIN ),
                'expanded'    => true,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Container', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_visual_header_background_color', 'post_confirmation_visual_header_padding', 'post_confirmation_visual_header_margin', 'post_confirmation_visual_header_border_radius', 'post_confirmation_visual_header_border_width', 'post_confirmation_visual_header_border_style', 'post_confirmation_visual_header_border_color', 'post_confirmation_visual_header_box_shadow', 'post_confirmation_visual_header_min_height' ),
                        __( 'Cards internos', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_visual_header_card_background_color', 'post_confirmation_visual_header_card_border_radius' ),
                        __( 'Nome da marca e meta', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_visual_header_font_family', 'post_confirmation_visual_header_title_color', 'post_confirmation_visual_header_title_size', 'post_confirmation_visual_header_title_padding', 'post_confirmation_visual_header_title_margin', 'post_confirmation_visual_header_meta_color', 'post_confirmation_visual_header_meta_size', 'post_confirmation_visual_header_meta_padding', 'post_confirmation_visual_header_meta_margin' ),
                    )
                ),
            ),
            array(
                'id'          => 'upload_products_breadcrumb_1',
                'label'       => __( 'Breadcrumb 1', EOP_TEXT_DOMAIN ),
                'description' => __( 'Texto e estilo do primeiro item da trilha.', EOP_TEXT_DOMAIN ),
                'expanded'    => true,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Conteudo', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_stage_contract_label' ),
                        __( 'Estrutura', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_visual_breadcrumb_gap' ),
                        __( 'Texto e tipografia', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_upload_visual_breadcrumb_contract_font_family', 'post_confirmation_upload_visual_breadcrumb_contract_font_size', 'post_confirmation_upload_visual_breadcrumb_contract_font_weight', 'post_confirmation_upload_visual_breadcrumb_contract_line_height', 'post_confirmation_upload_visual_breadcrumb_contract_text_color' ),
                        __( 'Fundo e borda', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_upload_visual_breadcrumb_contract_background_color', 'post_confirmation_upload_visual_breadcrumb_contract_padding', 'post_confirmation_upload_visual_breadcrumb_contract_border_radius', 'post_confirmation_upload_visual_breadcrumb_contract_border_width', 'post_confirmation_upload_visual_breadcrumb_contract_border_style', 'post_confirmation_upload_visual_breadcrumb_contract_border_color' ),
                        __( 'Numero', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_upload_visual_breadcrumb_contract_index_background_color', 'post_confirmation_upload_visual_breadcrumb_contract_index_color' ),
                    )
                ),
            ),
            array(
                'id'          => 'upload_products_breadcrumb_2',
                'label'       => __( 'Breadcrumb 2', EOP_TEXT_DOMAIN ),
                'description' => __( 'Texto e estilo do segundo item da trilha.', EOP_TEXT_DOMAIN ),
                'expanded'    => true,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Conteudo', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_stage_upload_label' ),
                        __( 'Texto e tipografia', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_upload_visual_breadcrumb_upload_font_family', 'post_confirmation_upload_visual_breadcrumb_upload_font_size', 'post_confirmation_upload_visual_breadcrumb_upload_font_weight', 'post_confirmation_upload_visual_breadcrumb_upload_line_height', 'post_confirmation_upload_visual_breadcrumb_upload_text_color' ),
                        __( 'Fundo e borda', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_upload_visual_breadcrumb_upload_background_color', 'post_confirmation_upload_visual_breadcrumb_upload_padding', 'post_confirmation_upload_visual_breadcrumb_upload_border_radius', 'post_confirmation_upload_visual_breadcrumb_upload_border_width', 'post_confirmation_upload_visual_breadcrumb_upload_border_style', 'post_confirmation_upload_visual_breadcrumb_upload_border_color' ),
                        __( 'Numero', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_upload_visual_breadcrumb_upload_index_background_color', 'post_confirmation_upload_visual_breadcrumb_upload_index_color' ),
                    )
                ),
            ),
            array(
                'id'          => 'upload_products_breadcrumb_3',
                'label'       => __( 'Breadcrumb 3', EOP_TEXT_DOMAIN ),
                'description' => __( 'Texto e estilo do terceiro item da trilha.', EOP_TEXT_DOMAIN ),
                'expanded'    => false,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Conteudo', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_stage_completed_label' ),
                        __( 'Texto e tipografia', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_upload_visual_breadcrumb_completed_font_family', 'post_confirmation_upload_visual_breadcrumb_completed_font_size', 'post_confirmation_upload_visual_breadcrumb_completed_font_weight', 'post_confirmation_upload_visual_breadcrumb_completed_line_height', 'post_confirmation_upload_visual_breadcrumb_completed_text_color' ),
                        __( 'Fundo e borda', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_upload_visual_breadcrumb_completed_background_color', 'post_confirmation_upload_visual_breadcrumb_completed_padding', 'post_confirmation_upload_visual_breadcrumb_completed_border_radius', 'post_confirmation_upload_visual_breadcrumb_completed_border_width', 'post_confirmation_upload_visual_breadcrumb_completed_border_style', 'post_confirmation_upload_visual_breadcrumb_completed_border_color' ),
                        __( 'Numero', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_upload_visual_breadcrumb_completed_index_background_color', 'post_confirmation_upload_visual_breadcrumb_completed_index_color' ),
                    )
                ),
            ),
            array(
                'id'          => 'upload_products_intro_element',
                'label'       => __( 'Intro da etapa', EOP_TEXT_DOMAIN ),
                'description' => __( 'Texto e visual do bloco introdutorio antes do formulario final.', EOP_TEXT_DOMAIN ),
                'expanded'    => true,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Conteudo', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_final_intro_eyebrow', 'post_confirmation_upload_title', 'post_confirmation_upload_description' ),
                        __( 'Container', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_visual_intro_background_color', 'post_confirmation_visual_intro_padding', 'post_confirmation_visual_intro_margin', 'post_confirmation_visual_intro_border_radius', 'post_confirmation_visual_intro_border_width', 'post_confirmation_visual_intro_border_style', 'post_confirmation_visual_intro_border_color', 'post_confirmation_visual_intro_box_shadow', 'post_confirmation_visual_intro_font_family' ),
                        __( 'Texto', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_visual_intro_eyebrow_color', 'post_confirmation_visual_intro_eyebrow_size', 'post_confirmation_visual_intro_title_color', 'post_confirmation_visual_intro_title_size', 'post_confirmation_visual_intro_title_line_height', 'post_confirmation_visual_intro_title_padding', 'post_confirmation_visual_intro_title_margin', 'post_confirmation_visual_intro_text_color', 'post_confirmation_visual_intro_text_size', 'post_confirmation_visual_intro_text_line_height', 'post_confirmation_visual_intro_text_padding', 'post_confirmation_visual_intro_text_margin' ),
                    )
                ),
            ),
            array(
                'id'          => 'upload_products_upload_field_element',
                'label'       => __( 'Campo de upload', EOP_TEXT_DOMAIN ),
                'description' => __( 'Label, input e container principal do envio do arquivo.', EOP_TEXT_DOMAIN ),
                'expanded'    => true,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Conteudo', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_upload_field_label', 'post_confirmation_upload_button_label' ),
                        __( 'Container', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_visual_upload_background_color', 'post_confirmation_visual_upload_padding', 'post_confirmation_visual_upload_margin', 'post_confirmation_visual_upload_border_radius', 'post_confirmation_visual_upload_border_width', 'post_confirmation_visual_upload_border_style', 'post_confirmation_visual_upload_border_color', 'post_confirmation_visual_upload_box_shadow' ),
                        __( 'Label', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_visual_upload_label_color', 'post_confirmation_visual_upload_label_size', 'post_confirmation_visual_upload_label_line_height' ),
                        __( 'Input', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_visual_upload_input_background_color', 'post_confirmation_visual_upload_input_text_color', 'post_confirmation_visual_upload_input_font_size', 'post_confirmation_visual_upload_input_line_height', 'post_confirmation_visual_upload_input_border_width', 'post_confirmation_visual_upload_input_border_style', 'post_confirmation_visual_upload_input_border_color', 'post_confirmation_visual_upload_input_border_radius' ),
                    )
                ),
            ),
            array(
                'id'          => 'upload_products_saved_attachment_element',
                'label'       => __( 'Anexo salvo', EOP_TEXT_DOMAIN ),
                'description' => __( 'Texto e visual do card exibido quando ja existe um arquivo enviado.', EOP_TEXT_DOMAIN ),
                'expanded'    => true,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Conteudo', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_upload_saved_date_prefix', 'post_confirmation_upload_saved_status_text', 'post_confirmation_upload_view_button_label' ),
                        __( 'Card do anexo', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_visual_upload_meta_background_color', 'post_confirmation_visual_upload_meta_padding', 'post_confirmation_visual_upload_meta_border_radius', 'post_confirmation_visual_upload_meta_border_width', 'post_confirmation_visual_upload_meta_border_style', 'post_confirmation_visual_upload_meta_border_color' ),
                        __( 'Nome e status', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_visual_upload_meta_title_color', 'post_confirmation_visual_upload_meta_title_size', 'post_confirmation_visual_upload_meta_title_line_height', 'post_confirmation_visual_upload_meta_text_color', 'post_confirmation_visual_upload_meta_text_size', 'post_confirmation_visual_upload_meta_text_line_height' ),
                        __( 'Botao do anexo', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_visual_upload_meta_button_background_color', 'post_confirmation_visual_upload_meta_button_text_color', 'post_confirmation_visual_upload_meta_button_font_family', 'post_confirmation_visual_upload_meta_button_font_size', 'post_confirmation_visual_upload_meta_button_line_height', 'post_confirmation_visual_upload_meta_button_font_weight', 'post_confirmation_visual_upload_meta_button_padding', 'post_confirmation_visual_upload_meta_button_border_width', 'post_confirmation_visual_upload_meta_button_border_style', 'post_confirmation_visual_upload_meta_button_border_color', 'post_confirmation_visual_upload_meta_button_border_radius', 'post_confirmation_visual_upload_meta_button_box_shadow' ),
                    )
                ),
            ),
            array(
                'id'          => 'upload_products_list_element',
                'label'       => __( 'Lista de produtos', EOP_TEXT_DOMAIN ),
                'description' => __( 'Titulo, descricao e container geral da personalizacao dos itens.', EOP_TEXT_DOMAIN ),
                'expanded'    => true,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Conteudo', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_products_title', 'post_confirmation_products_description' ),
                        __( 'Container', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_visual_products_block_background_color', 'post_confirmation_visual_products_block_padding', 'post_confirmation_visual_products_block_margin', 'post_confirmation_visual_products_block_border_radius', 'post_confirmation_visual_products_block_border_width', 'post_confirmation_visual_products_block_border_style', 'post_confirmation_visual_products_block_border_color', 'post_confirmation_visual_products_block_box_shadow', 'post_confirmation_visual_products_font_family' ),
                        __( 'Titulo e descricao', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_visual_products_title_color', 'post_confirmation_visual_products_title_size', 'post_confirmation_visual_products_title_line_height', 'post_confirmation_visual_products_description_color', 'post_confirmation_visual_products_description_size', 'post_confirmation_visual_products_description_line_height' ),
                    )
                ),
            ),
            array(
                'id'          => 'upload_products_table_head_element',
                'label'       => __( 'Cabecalho da lista', EOP_TEXT_DOMAIN ),
                'description' => __( 'Textos e tipografia das colunas acima dos itens personalizados.', EOP_TEXT_DOMAIN ),
                'expanded'    => false,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Conteudo', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_products_heading_sequence_label', 'post_confirmation_products_heading_original_label', 'post_confirmation_products_heading_custom_label' ),
                        __( 'Tipografia', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_visual_products_heading_color', 'post_confirmation_visual_products_heading_size', 'post_confirmation_visual_products_heading_line_height' ),
                    )
                ),
            ),
            array(
                'id'          => 'upload_products_row_element',
                'label'       => __( 'Linha do produto', EOP_TEXT_DOMAIN ),
                'description' => __( 'Visual do card de cada produto, incluindo nome original e SKU.', EOP_TEXT_DOMAIN ),
                'expanded'    => false,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Conteudo', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_products_sku_prefix', 'post_confirmation_products_sku_empty_label' ),
                        __( 'Card do produto', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_visual_products_row_background_color', 'post_confirmation_visual_products_row_padding', 'post_confirmation_visual_products_row_border_radius', 'post_confirmation_visual_products_row_border_width', 'post_confirmation_visual_products_row_border_style', 'post_confirmation_visual_products_row_border_color', 'post_confirmation_visual_products_row_shadow' ),
                        __( 'Nome e SKU', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_visual_products_name_color', 'post_confirmation_visual_products_name_size', 'post_confirmation_visual_products_name_line_height', 'post_confirmation_visual_products_sku_color', 'post_confirmation_visual_products_sku_size', 'post_confirmation_visual_products_sku_line_height' ),
                    )
                ),
            ),
            array(
                'id'          => 'upload_products_name_field_element',
                'label'       => __( 'Campo de novo nome', EOP_TEXT_DOMAIN ),
                'description' => __( 'Visual do input de personalizacao e do aviso de item bloqueado.', EOP_TEXT_DOMAIN ),
                'expanded'    => false,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Conteudo', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_products_locked_message' ),
                        __( 'Input', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_visual_products_input_background_color', 'post_confirmation_visual_products_input_text_color', 'post_confirmation_visual_products_input_font_size', 'post_confirmation_visual_products_input_line_height', 'post_confirmation_visual_products_input_border_width', 'post_confirmation_visual_products_input_border_style', 'post_confirmation_visual_products_input_border_color', 'post_confirmation_visual_products_input_border_radius' ),
                    )
                ),
            ),
            array(
                'id'          => 'upload_products_primary_button_element',
                'label'       => __( 'Botao principal', EOP_TEXT_DOMAIN ),
                'description' => __( 'Texto e visual do CTA final da etapa de upload e personalizacao.', EOP_TEXT_DOMAIN ),
                'expanded'    => true,
                'fields'      => self::get_post_confirmation_contract_editor_section_fields(
                    $registry,
                    array(
                        __( 'Conteudo', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_products_button_label' ),
                        __( 'Visual do botao', EOP_TEXT_DOMAIN ) => array( 'post_confirmation_visual_button_background_color', 'post_confirmation_visual_button_text_color', 'post_confirmation_visual_button_font_family', 'post_confirmation_visual_button_font_size', 'post_confirmation_visual_button_line_height', 'post_confirmation_visual_button_font_weight', 'post_confirmation_visual_button_padding', 'post_confirmation_visual_button_margin', 'post_confirmation_visual_button_border_width', 'post_confirmation_visual_button_border_style', 'post_confirmation_visual_button_border_color', 'post_confirmation_visual_button_border_radius', 'post_confirmation_visual_button_box_shadow' ),
                    )
                ),
            ),
        );
    }

    /**
     * Renderiza o editor visual completo da etapa de upload e produtos.
     *
     * Essa funcao atua como ponto de entrada do editor dessa tela no admin:
     * monta os accordions de conteudo, a base visual global e as secoes
     * especificas de estilo. Se voce precisar adicionar um novo bloco visual
     * nessa tela, normalmente o encaixe inicial acontece aqui.
     *
     * @param array $settings Configuracoes atuais do plugin.
     */
    private static function render_post_confirmation_upload_products_visual_editor( $settings ) {
        ?>
        <div class="eop-visual-editor">
            <?php foreach ( self::get_post_confirmation_upload_products_editor_sections() as $section ) : ?>
                <?php self::render_order_link_visual_section( $section, $settings ); ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Renderiza o editor visual do link do pedido / pagina publica da proposta.
     *
     * A ideia aqui e seguir o mesmo padrao da tela de upload/produtos:
     * primeiro os accordions de configuracao e depois, fora desta funcao,
     * o preview em iframe. Se precisar reorganizar a experiencia de edicao
     * dessa area, este e o ponto principal para mexer.
     *
     * @param array $settings Configuracoes atuais do plugin.
     */
    private static function render_order_link_visual_editor( $settings ) {
        ?>
        <div class="eop-visual-editor">
            <?php foreach ( self::get_order_link_visual_sections() as $section ) : ?>
                <?php self::render_order_link_visual_section( $section, $settings ); ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private static function render_named_visual_editor( $settings, $sections ) {
        ?>
        <div class="eop-visual-editor">
            <?php foreach ( (array) $sections as $section ) : ?>
                <?php self::render_order_link_visual_section( $section, $settings ); ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private static function render_new_order_visual_editor( $settings ) {
        self::render_named_visual_editor( $settings, self::get_new_order_visual_sections() );
    }

    private static function render_orders_list_visual_editor( $settings ) {
        self::render_named_visual_editor( $settings, self::get_orders_list_visual_sections() );
    }

    public static function render_admin_real_view_preview( $title, $description, $iframe_url, $iframe_title ) {
        ?>
        <div class="eop-proposal-preview-card" data-eop-proposal-preview-card data-preview-viewport="desktop">
            <div class="eop-proposal-preview-card__copy">
                <div class="eop-proposal-preview-card__eyebrow"><?php esc_html_e( 'Preview ao vivo', EOP_TEXT_DOMAIN ); ?></div>
                <h3><?php echo esc_html( $title ); ?></h3>
                <p><?php echo esc_html( $description ); ?></p>
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
                    <iframe class="eop-proposal-preview-render" title="<?php echo esc_attr( $iframe_title ); ?>" src="<?php echo esc_url( $iframe_url ); ?>"></iframe>
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_new_order_preview_card( $settings = array() ) {
        $iframe_url = class_exists( 'EOP_Admin_Page' ) ? EOP_Admin_Page::get_preview_frame_url( 'new-order' ) : '';

        self::render_admin_real_view_preview(
            __( 'Formulario publico de pedido', EOP_TEXT_DOMAIN ),
            __( 'Este preview usa o mesmo template do shortcode [expresso_order] exibido na pagina publica de pedido.', EOP_TEXT_DOMAIN ),
            $iframe_url,
            __( 'Preview do formulario publico de pedido', EOP_TEXT_DOMAIN )
        );
    }

    public static function render_orders_list_preview_card( $settings = array() ) {
        $iframe_url = class_exists( 'EOP_Admin_Page' ) ? EOP_Admin_Page::get_preview_frame_url( 'orders' ) : '';

        self::render_admin_real_view_preview(
            __( 'Tela real da listagem de pedidos', EOP_TEXT_DOMAIN ),
            __( 'Este preview usa a propria tela original de pedidos em um iframe pre-preenchido para manter o layout DRY.', EOP_TEXT_DOMAIN ),
            $iframe_url,
            __( 'Preview da tela real de listagem de pedidos', EOP_TEXT_DOMAIN )
        );
    }

    /**
     * Converte as secoes do editor visual legado no schema de campos do admin SPA.
     *
     * Fonte unica de verdade: reaproveita as definicoes declarativas (label PT,
     * tipo, choices) ja existentes, evitando duplicar campos. Tipos nao suportados
     * pelo SPA caem para 'text' (ex.: 'font' editado como texto com ajuda).
     *
     * @param string $domain new-order|orders-list|proposal
     * @return array[]
     */
    public static function get_spa_field_schema( $domain ) {
        switch ( $domain ) {
            case 'new-order':
                $sections = self::get_new_order_visual_sections();
                break;
            case 'orders-list':
                $sections = self::get_orders_list_visual_sections();
                break;
            case 'proposal':
                $sections = self::get_order_link_visual_sections();
                break;
            case 'confirmation-contract':
                $sections = self::get_post_confirmation_contract_editor_sections();
                break;
            case 'confirmation-upload-products':
                $sections = self::get_post_confirmation_upload_products_editor_sections();
                break;
            default:
                return array();
        }

        $allowed = array( 'text', 'textarea', 'color', 'number', 'select' );
        $fields  = array();
        $seen    = array();

        foreach ( (array) $sections as $section ) {
            $section_fields = isset( $section['fields'] ) && is_array( $section['fields'] ) ? $section['fields'] : array();
            $section_label  = isset( $section['label'] ) ? (string) $section['label'] : '';

            foreach ( $section_fields as $key => $def ) {
                $key = (string) $key;

                if ( '' === $key || isset( $seen[ $key ] ) ) {
                    continue;
                }

                $seen[ $key ] = true;
                $def          = is_array( $def ) ? $def : array();
                $type     = isset( $def['type'] ) ? (string) $def['type'] : 'text';
                $spa_type = in_array( $type, $allowed, true ) ? $type : 'text';

                // Select com escolhas Sim/Nao (yes/no) vira switch (toggle) no SPA.
                if ( 'select' === $spa_type && ! empty( $def['choices'] ) && is_array( $def['choices'] ) ) {
                    $choice_keys = array_map( 'strval', array_keys( $def['choices'] ) );
                    sort( $choice_keys );

                    if ( array( 'no', 'yes' ) === $choice_keys ) {
                        $spa_type = 'toggle';
                    }
                }

                $field = array(
                    'key'      => $key,
                    'type'     => $spa_type,
                    'label'    => isset( $def['label'] ) ? (string) $def['label'] : $key,
                    'group'    => $section_label,
                    'subgroup' => isset( $def['group'] ) ? (string) $def['group'] : '',
                );

                if ( 'select' === $spa_type && ! empty( $def['choices'] ) && is_array( $def['choices'] ) ) {
                    $options = array();
                    foreach ( $def['choices'] as $value => $label ) {
                        $options[] = array(
                            'value' => (string) $value,
                            'label' => (string) $label,
                        );
                    }
                    $field['options'] = $options;
                }

                if ( 'color' === $spa_type && isset( $def['default'] ) ) {
                    $field['default'] = (string) $def['default'];
                }

                // "?" nos campos cujo formato CSS pode confundir o usuario.
                $help_by_type = array(
                    'font'   => __( 'Formato Fonte:pesos, ex.: Montserrat:400,700. O seletor visual de fonte segue no admin legado.', EOP_TEXT_DOMAIN ),
                    'box'    => __( 'Espacamento CSS no formato cima/lados/baixo, ex.: 10px 6px 24px. Aceita 1 a 4 valores.', EOP_TEXT_DOMAIN ),
                    'size'   => __( 'Tamanho CSS, ex.: 18px, 0px ou 1.2rem.', EOP_TEXT_DOMAIN ),
                    'shadow' => __( 'Valor de box-shadow CSS, ex.: 0 8px 16px rgba(0,0,0,.2). Use "none" para remover.', EOP_TEXT_DOMAIN ),
                );

                if ( isset( $help_by_type[ $type ] ) ) {
                    $field['help'] = $help_by_type[ $type ];
                } elseif ( false !== strpos( $key, 'line_height' ) ) {
                    $field['help'] = __( 'Altura da linha (line-height), ex.: 1.2 ou 24px.', EOP_TEXT_DOMAIN );
                }

                $fields[] = $field;
            }
        }

        return $fields;
    }

    private static function get_new_order_visual_sections() {
        return array(
            array(
                'label'       => __( 'Cabecalho do formulario', EOP_TEXT_DOMAIN ),
                'description' => __( 'Controle os textos principais exibidos em /pedido-expresso/.', EOP_TEXT_DOMAIN ),
                'expanded'    => true,
                'fields'      => array(
                    'panel_title'    => array( 'label' => __( 'Titulo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Pedido Expresso', EOP_TEXT_DOMAIN ), 'group' => __( 'Cabecalho', EOP_TEXT_DOMAIN ) ),
                    'panel_subtitle' => array( 'label' => __( 'Descricao', EOP_TEXT_DOMAIN ), 'type' => 'textarea', 'default' => __( 'Monte o pedido, gere a proposta e compartilhe com o cliente.', EOP_TEXT_DOMAIN ), 'full' => true, 'group' => __( 'Cabecalho', EOP_TEXT_DOMAIN ) ),
                ),
            ),
            array(
                'label'       => __( 'Acoes e botoes', EOP_TEXT_DOMAIN ),
                'description' => __( 'Personalize os principais botoes do formulario publico de pedido.', EOP_TEXT_DOMAIN ),
                'expanded'    => false,
                'fields'      => array(
                    'new_order_submit_label' => array( 'label' => __( 'Botao finalizar', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Finalizar e Gerar PDF', EOP_TEXT_DOMAIN ), 'group' => __( 'Botoes', EOP_TEXT_DOMAIN ) ),
                    'new_order_mass_apply_label' => array( 'label' => __( 'Botao aplicar em massa', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Aplicar', EOP_TEXT_DOMAIN ), 'group' => __( 'Botoes', EOP_TEXT_DOMAIN ) ),
                    'new_order_shipping_button_label' => array( 'label' => __( 'Botão do frete', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Buscar opções de frete', EOP_TEXT_DOMAIN ), 'group' => __( 'Botões', EOP_TEXT_DOMAIN ) ),
                ),
            ),
            array(
                'label'       => __( 'Identidade visual', EOP_TEXT_DOMAIN ),
                'description' => __( 'Defina fonte, cores e radius usados pelo formulario publico.', EOP_TEXT_DOMAIN ),
                'expanded'    => false,
                'fields'      => array(
                    'font_family'   => array( 'label' => __( 'Fonte', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'full' => true, 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ) ),
                    'primary_color' => array( 'label' => __( 'Cor principal', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#00034b', 'group' => __( 'Cores', EOP_TEXT_DOMAIN ) ),
                    'surface_color' => array( 'label' => __( 'Fundo dos cards', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'group' => __( 'Cores', EOP_TEXT_DOMAIN ) ),
                    'border_color'  => array( 'label' => __( 'Cor das bordas', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#dbe3f0', 'group' => __( 'Cores', EOP_TEXT_DOMAIN ) ),
                    'border_radius' => array( 'label' => __( 'Radius base', EOP_TEXT_DOMAIN ), 'type' => 'number', 'min' => 0, 'max' => 48, 'default' => '18', 'group' => __( 'Container', EOP_TEXT_DOMAIN ) ),
                ),
            ),
        );
    }

    private static function get_orders_list_visual_sections() {
        return array(
            array(
                'label'       => __( 'Cabecalho da listagem', EOP_TEXT_DOMAIN ),
                'description' => __( 'Controle os textos principais da tela interna de pedidos.', EOP_TEXT_DOMAIN ),
                'expanded'    => true,
                'fields'      => array(
                    'orders_list_kicker' => array( 'label' => __( 'Etiqueta superior', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Gestao comercial', EOP_TEXT_DOMAIN ), 'group' => __( 'Cabecalho', EOP_TEXT_DOMAIN ) ),
                    'orders_list_title' => array( 'label' => __( 'Titulo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Pedidos', EOP_TEXT_DOMAIN ), 'group' => __( 'Cabecalho', EOP_TEXT_DOMAIN ) ),
                    'orders_list_description' => array( 'label' => __( 'Descricao', EOP_TEXT_DOMAIN ), 'type' => 'textarea', 'default' => __( 'Acompanhe pedidos e propostas da equipe comercial com os atalhos principais do fluxo.', EOP_TEXT_DOMAIN ), 'full' => true, 'group' => __( 'Cabecalho', EOP_TEXT_DOMAIN ) ),
                    'orders_list_panel_title' => array( 'label' => __( 'Titulo do bloco principal', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Pedidos criados', EOP_TEXT_DOMAIN ), 'group' => __( 'Listagem', EOP_TEXT_DOMAIN ) ),
                    'orders_list_panel_description' => array( 'label' => __( 'Descricao do bloco principal', EOP_TEXT_DOMAIN ), 'type' => 'textarea', 'default' => __( 'Acompanhe propostas e pedidos sem sair da tela de vendas.', EOP_TEXT_DOMAIN ), 'full' => true, 'group' => __( 'Listagem', EOP_TEXT_DOMAIN ) ),
                    'orders_list_refresh_label' => array( 'label' => __( 'Botao atualizar', EOP_TEXT_DOMAIN ), 'type' => 'text', 'default' => __( 'Atualizar', EOP_TEXT_DOMAIN ), 'group' => __( 'Listagem', EOP_TEXT_DOMAIN ) ),
                ),
            ),
            array(
                'label'       => __( 'Identidade visual', EOP_TEXT_DOMAIN ),
                'description' => __( 'Defina fonte, cores, fundo e radius da listagem de pedidos.', EOP_TEXT_DOMAIN ),
                'expanded'    => false,
                'fields'      => array(
                    'orders_list_font_family' => array( 'label' => __( 'Fonte', EOP_TEXT_DOMAIN ), 'type' => 'font', 'default' => 'Montserrat:400,700', 'full' => true, 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ) ),
                    'orders_list_primary_color' => array( 'label' => __( 'Cor principal', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#00034b', 'group' => __( 'Cores', EOP_TEXT_DOMAIN ) ),
                    'orders_list_surface_color' => array( 'label' => __( 'Fundo dos cards', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#ffffff', 'group' => __( 'Cores', EOP_TEXT_DOMAIN ) ),
                    'orders_list_border_color' => array( 'label' => __( 'Cor das bordas', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#dbe3f0', 'group' => __( 'Cores', EOP_TEXT_DOMAIN ) ),
                    'orders_list_background_color' => array( 'label' => __( 'Fundo da tela', EOP_TEXT_DOMAIN ), 'type' => 'color', 'default' => '#f5f7ff', 'group' => __( 'Container', EOP_TEXT_DOMAIN ) ),
                    'orders_list_radius' => array( 'label' => __( 'Radius base', EOP_TEXT_DOMAIN ), 'type' => 'number', 'min' => 0, 'max' => 48, 'default' => '18', 'group' => __( 'Container', EOP_TEXT_DOMAIN ) ),
                ),
            ),
        );
    }

    /**
     * Define a estrutura declarativa das secoes do editor visual do link.
     *
     * Cada item retornado aqui vira um accordion no admin. Dentro de cada
     * secao, os campos carregam metadados simples como label, tipo, grupo
     * interno e comportamento especial. Em manutencao, o lugar correto para
     * adicionar/remover campos dessa tela e este array.
     *
     * @return array[]
     */
    private static function get_order_link_visual_sections() {
        $yes_no_choices       = array( 'yes' => __( 'Sim', EOP_TEXT_DOMAIN ), 'no' => __( 'Nao', EOP_TEXT_DOMAIN ) );
        $font_weight_choices  = array( '500' => '500', '600' => '600', '700' => '700', '800' => '800', '900' => '900' );
        $hero_weight_choices  = array( '600' => '600', '700' => '700', '800' => '800', '900' => '900' );
        $border_style_choices = array( 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'none' => 'None' );

        return array(
            array(
                'label'       => __( 'Base da pagina', EOP_TEXT_DOMAIN ),
                'description' => __( 'Controle a estrutura global do link publico, incluindo fundo, largura, fonte base e cores compartilhadas.', EOP_TEXT_DOMAIN ),
                'expanded'    => true,
                'fields'      => array(
                    'customer_experience_font_family'      => array( 'label' => __( 'Fonte principal', EOP_TEXT_DOMAIN ), 'type' => 'font', 'full' => true, 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => 'Montserrat:400,700' ),
                    'proposal_max_width'                   => array( 'label' => __( 'Largura maxima da proposta', EOP_TEXT_DOMAIN ), 'type' => 'number', 'min' => 720, 'max' => 1600, 'group' => __( 'Estrutura', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_background_mode'  => array( 'label' => __( 'Modo do fundo da pagina', EOP_TEXT_DOMAIN ), 'type' => 'select', 'group' => __( 'Fundo da pagina', EOP_TEXT_DOMAIN ), 'default' => 'solid', 'choices' => array( 'solid' => __( 'Cor solida', EOP_TEXT_DOMAIN ), 'gradient' => __( 'Gradiente', EOP_TEXT_DOMAIN ) ) ),
                    'customer_experience_background_color' => array( 'label' => __( 'Cor principal do fundo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Fundo da pagina', EOP_TEXT_DOMAIN ), 'default' => '#f5f7ff' ),
                    'customer_experience_background_secondary_color' => array( 'label' => __( 'Cor secundaria do fundo', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Fundo da pagina', EOP_TEXT_DOMAIN ), 'default' => '#f7f9fc' ),
                    'customer_experience_text_color'       => array( 'label' => __( 'Texto principal', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Cores base', EOP_TEXT_DOMAIN ), 'default' => '#16243a' ),
                    'customer_experience_muted_color'      => array( 'label' => __( 'Texto auxiliar', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Cores base', EOP_TEXT_DOMAIN ), 'default' => '#66768d' ),
                    'customer_experience_accent_color'     => array( 'label' => __( 'Cor de destaque', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Cores base', EOP_TEXT_DOMAIN ), 'default' => '#00ffff' ),
                    'border_color'                         => array( 'label' => __( 'Cor de borda compartilhada', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Bordas compartilhadas', EOP_TEXT_DOMAIN ), 'default' => '#dbe3f0' ),
                    'customer_experience_shared_border_width' => array( 'label' => __( 'Espessura da borda compartilhada', EOP_TEXT_DOMAIN ), 'type' => 'size', 'group' => __( 'Bordas compartilhadas', EOP_TEXT_DOMAIN ), 'default' => '1px' ),
                    'customer_experience_shared_border_style' => array( 'label' => __( 'Estilo da borda compartilhada', EOP_TEXT_DOMAIN ), 'type' => 'select', 'group' => __( 'Bordas compartilhadas', EOP_TEXT_DOMAIN ), 'default' => 'solid', 'choices' => $border_style_choices ),
                    'border_radius'                        => array( 'label' => __( 'Radius base', EOP_TEXT_DOMAIN ), 'type' => 'number', 'min' => 0, 'max' => 48, 'group' => __( 'Bordas compartilhadas', EOP_TEXT_DOMAIN ) ),
                ),
            ),
            array(
                'label'       => __( 'Hero principal', EOP_TEXT_DOMAIN ),
                'description' => __( 'Edite o bloco principal do topo com os textos, contraste, fundo e tipografia do destaque inicial.', EOP_TEXT_DOMAIN ),
                'expanded'    => true,
                'fields'      => array(
                    'customer_experience_eyebrow'         => array( 'label' => __( 'Etiqueta superior', EOP_TEXT_DOMAIN ), 'type' => 'text', 'full' => true, 'group' => __( 'Conteudo', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_title'           => array( 'label' => __( 'Titulo principal', EOP_TEXT_DOMAIN ), 'type' => 'text', 'full' => true, 'group' => __( 'Conteudo', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_description'     => array( 'label' => __( 'Descricao principal', EOP_TEXT_DOMAIN ), 'type' => 'textarea', 'full' => true, 'group' => __( 'Conteudo', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_hero_background_mode' => array( 'label' => __( 'Modo do fundo do hero', EOP_TEXT_DOMAIN ), 'type' => 'select', 'group' => __( 'Fundo', EOP_TEXT_DOMAIN ), 'default' => 'solid', 'choices' => array( 'solid' => __( 'Cor solida', EOP_TEXT_DOMAIN ), 'gradient' => __( 'Gradiente', EOP_TEXT_DOMAIN ) ) ),
                    'customer_experience_hero_background_color' => array( 'label' => __( 'Cor principal do hero', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Fundo', EOP_TEXT_DOMAIN ), 'default' => '#00034b' ),
                    'customer_experience_hero_background_secondary_color' => array( 'label' => __( 'Cor secundaria do hero', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Fundo', EOP_TEXT_DOMAIN ), 'default' => '#243553' ),
                    'customer_experience_hero_text_color' => array( 'label' => __( 'Cor do texto no hero', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Contraste', EOP_TEXT_DOMAIN ), 'default' => '#ffffff' ),
                    'customer_experience_hero_muted_color' => array( 'label' => __( 'Cor auxiliar no hero', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Contraste', EOP_TEXT_DOMAIN ), 'default' => '#d7deec' ),
                    'customer_experience_chip_font_size'  => array( 'label' => __( 'Tamanho dos selos do topo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => '12px' ),
                    'customer_experience_chip_font_weight' => array( 'label' => __( 'Peso dos selos do topo', EOP_TEXT_DOMAIN ), 'type' => 'select', 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => '800', 'choices' => $hero_weight_choices ),
                    'customer_experience_eyebrow_size'    => array( 'label' => __( 'Tamanho da etiqueta superior', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => '11px' ),
                    'customer_experience_title_size'      => array( 'label' => __( 'Tamanho do titulo principal', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => '46px' ),
                    'customer_experience_title_line_height' => array( 'label' => __( 'Line-height do titulo principal', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => '0.98' ),
                    'customer_experience_title_font_weight' => array( 'label' => __( 'Peso do titulo principal', EOP_TEXT_DOMAIN ), 'type' => 'select', 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => '800', 'choices' => $hero_weight_choices ),
                    'customer_experience_text_line_height' => array( 'label' => __( 'Line-height do texto base', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => '1.7' ),
                ),
            ),
            array(
                'label'       => __( 'Hero lateral', EOP_TEXT_DOMAIN ),
                'description' => __( 'Agrupe aqui o resumo em destaque, os metadados do pedido e as configuracoes visuais do painel lateral do topo.', EOP_TEXT_DOMAIN ),
                'expanded'    => false,
                'fields'      => array(
                    'customer_experience_total_label'     => array( 'label' => __( 'Label do resumo em destaque', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Conteudo', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_total_note'      => array( 'label' => __( 'Texto de apoio do resumo', EOP_TEXT_DOMAIN ), 'type' => 'textarea', 'full' => true, 'group' => __( 'Conteudo', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_order_label'     => array( 'label' => __( 'Label do pedido', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Metadados', EOP_TEXT_DOMAIN ), 'default' => __( 'Pedido', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_customer_label'  => array( 'label' => __( 'Label do cliente', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Metadados', EOP_TEXT_DOMAIN ), 'default' => __( 'Cliente', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_meta_status_label' => array( 'label' => __( 'Label de status', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Metadados', EOP_TEXT_DOMAIN ), 'default' => __( 'Status', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_meta_status_value_preview' => array( 'label' => __( 'Status demonstrativo do preview', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Metadados', EOP_TEXT_DOMAIN ), 'default' => __( 'Aguardando confirmacao', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_meta_deadline_label' => array( 'label' => __( 'Label de prazo', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Metadados', EOP_TEXT_DOMAIN ), 'default' => __( 'Prazo', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_meta_deadline_value_preview' => array( 'label' => __( 'Prazo demonstrativo do preview', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Metadados', EOP_TEXT_DOMAIN ), 'default' => __( 'Entrega em ate 3 dias uteis', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_hero_aside_background_color' => array( 'label' => __( 'Fundo do hero lateral', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Painel lateral', EOP_TEXT_DOMAIN ), 'default' => '#f6f8fc' ),
                    'customer_experience_hero_aside_border_color' => array( 'label' => __( 'Borda do hero lateral', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Painel lateral', EOP_TEXT_DOMAIN ), 'default' => '#dbe3f0' ),
                    'customer_experience_hero_aside_radius' => array( 'label' => __( 'Radius do hero lateral', EOP_TEXT_DOMAIN ), 'type' => 'size', 'group' => __( 'Painel lateral', EOP_TEXT_DOMAIN ), 'default' => '28px' ),
                    'customer_experience_hero_aside_shadow' => array( 'label' => __( 'Sombra do hero lateral', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'group' => __( 'Painel lateral', EOP_TEXT_DOMAIN ), 'default' => 'none' ),
                    'customer_experience_hero_meta_background_color' => array( 'label' => __( 'Fundo dos cards internos', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Cards internos', EOP_TEXT_DOMAIN ), 'default' => '#ffffff' ),
                    'customer_experience_hero_meta_border_color' => array( 'label' => __( 'Borda dos cards internos', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Cards internos', EOP_TEXT_DOMAIN ), 'default' => '#dbe3f0' ),
                    'customer_experience_hero_meta_radius' => array( 'label' => __( 'Radius dos cards internos', EOP_TEXT_DOMAIN ), 'type' => 'size', 'group' => __( 'Cards internos', EOP_TEXT_DOMAIN ), 'default' => '20px' ),
                ),
            ),
            array(
                'label'       => __( 'Lista de itens', EOP_TEXT_DOMAIN ),
                'description' => __( 'Edite o bloco central dos produtos com textos, titulo, fundo da secao e estilo individual de cada item.', EOP_TEXT_DOMAIN ),
                'expanded'    => false,
                'fields'      => array(
                    'customer_experience_items_eyebrow'   => array( 'label' => __( 'Etiqueta da lista de itens', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Conteudo', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_items_title'     => array( 'label' => __( 'Titulo da lista de itens', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Conteudo', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_section_title_size' => array( 'label' => __( 'Tamanho do titulo da secao', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => '26px' ),
                    'customer_experience_section_title_font_weight' => array( 'label' => __( 'Peso do titulo da secao', EOP_TEXT_DOMAIN ), 'type' => 'select', 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => '800', 'choices' => $hero_weight_choices ),
                    'customer_experience_item_title_size' => array( 'label' => __( 'Tamanho do titulo dos itens', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => '19px' ),
                    'customer_experience_item_title_font_weight' => array( 'label' => __( 'Peso do titulo dos itens', EOP_TEXT_DOMAIN ), 'type' => 'select', 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => '700', 'choices' => $hero_weight_choices ),
                    'customer_experience_items_section_background_color' => array( 'label' => __( 'Fundo da secao', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Bloco da secao', EOP_TEXT_DOMAIN ), 'default' => '#ffffff' ),
                    'customer_experience_items_section_border_color' => array( 'label' => __( 'Borda da secao', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Bloco da secao', EOP_TEXT_DOMAIN ), 'default' => '#dbe3f0' ),
                    'customer_experience_items_section_radius' => array( 'label' => __( 'Radius da secao', EOP_TEXT_DOMAIN ), 'type' => 'size', 'group' => __( 'Bloco da secao', EOP_TEXT_DOMAIN ), 'default' => '28px' ),
                    'customer_experience_items_section_shadow' => array( 'label' => __( 'Sombra da secao', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'group' => __( 'Bloco da secao', EOP_TEXT_DOMAIN ), 'default' => '0 18px 38px rgba(15, 27, 53, .08)' ),
                    'customer_experience_item_card_background_color' => array( 'label' => __( 'Fundo dos itens', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Cards dos itens', EOP_TEXT_DOMAIN ), 'default' => '#ffffff' ),
                    'customer_experience_item_card_border_color' => array( 'label' => __( 'Borda dos itens', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Cards dos itens', EOP_TEXT_DOMAIN ), 'default' => '#dbe3f0' ),
                    'customer_experience_item_card_radius' => array( 'label' => __( 'Radius dos itens', EOP_TEXT_DOMAIN ), 'type' => 'size', 'group' => __( 'Cards dos itens', EOP_TEXT_DOMAIN ), 'default' => '24px' ),
                    'customer_experience_item_card_shadow' => array( 'label' => __( 'Sombra dos itens', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'group' => __( 'Cards dos itens', EOP_TEXT_DOMAIN ), 'default' => 'none' ),
                    'customer_experience_item_pill_background_color' => array( 'label' => __( 'Fundo dos selos', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Selos dos itens', EOP_TEXT_DOMAIN ), 'default' => '#e9e8f4' ),
                    'customer_experience_item_pill_text_color' => array( 'label' => __( 'Texto dos selos', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Selos dos itens', EOP_TEXT_DOMAIN ), 'default' => '#16243a' ),
                ),
            ),
            array(
                'label'       => __( 'Cards laterais', EOP_TEXT_DOMAIN ),
                'description' => __( 'Organize os textos dos cards de contexto, financeiro e acoes junto com o fundo, borda e sombra desses blocos.', EOP_TEXT_DOMAIN ),
                'expanded'    => false,
                'fields'      => array(
                    'customer_experience_summary_enabled' => array( 'label' => __( 'Exibir card de contexto', EOP_TEXT_DOMAIN ), 'type' => 'select', 'group' => __( 'Visibilidade', EOP_TEXT_DOMAIN ), 'default' => 'yes', 'choices' => $yes_no_choices ),
                    'customer_experience_financial_enabled' => array( 'label' => __( 'Exibir card de resumo financeiro', EOP_TEXT_DOMAIN ), 'type' => 'select', 'group' => __( 'Visibilidade', EOP_TEXT_DOMAIN ), 'default' => 'yes', 'choices' => $yes_no_choices ),
                    'customer_experience_actions_enabled' => array( 'label' => __( 'Exibir card de acoes', EOP_TEXT_DOMAIN ), 'type' => 'select', 'group' => __( 'Visibilidade', EOP_TEXT_DOMAIN ), 'default' => 'yes', 'choices' => $yes_no_choices ),
                    'customer_experience_summary_eyebrow' => array( 'label' => __( 'Etiqueta do card de contexto', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Conteudo', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_summary_title'   => array( 'label' => __( 'Titulo do card de contexto', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Conteudo', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_financial_eyebrow' => array( 'label' => __( 'Etiqueta do card financeiro', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Conteudo', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_financial_title' => array( 'label' => __( 'Titulo do card financeiro', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Conteudo', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_actions_eyebrow' => array( 'label' => __( 'Etiqueta do card de acoes', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Conteudo', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_actions_title'   => array( 'label' => __( 'Titulo do card de acoes', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Conteudo', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_subtotal_label'  => array( 'label' => __( 'Label de subtotal', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Resumo financeiro', EOP_TEXT_DOMAIN ), 'default' => __( 'Subtotal', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_subtotal_note'   => array( 'label' => __( 'Texto auxiliar do subtotal', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Resumo financeiro', EOP_TEXT_DOMAIN ), 'default' => __( 'Valor base dos itens', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_discount_label'  => array( 'label' => __( 'Label de desconto', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Resumo financeiro', EOP_TEXT_DOMAIN ), 'default' => __( 'Desconto', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_discount_note'   => array( 'label' => __( 'Texto auxiliar do desconto', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Resumo financeiro', EOP_TEXT_DOMAIN ), 'default' => __( 'Campanha aplicada', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_total_row_label' => array( 'label' => __( 'Label do total final', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Resumo financeiro', EOP_TEXT_DOMAIN ), 'default' => __( 'Total', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_total_row_note'  => array( 'label' => __( 'Texto auxiliar do total final', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Resumo financeiro', EOP_TEXT_DOMAIN ), 'default' => __( 'Valor final exibido ao cliente', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_sidebar_card_background_color' => array( 'label' => __( 'Fundo dos cards laterais', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Cards laterais', EOP_TEXT_DOMAIN ), 'default' => '#f6f8fc' ),
                    'customer_experience_sidebar_card_border_color' => array( 'label' => __( 'Borda dos cards laterais', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Cards laterais', EOP_TEXT_DOMAIN ), 'default' => '#dbe3f0' ),
                    'customer_experience_sidebar_card_radius' => array( 'label' => __( 'Radius dos cards laterais', EOP_TEXT_DOMAIN ), 'type' => 'size', 'group' => __( 'Cards laterais', EOP_TEXT_DOMAIN ), 'default' => '28px' ),
                    'customer_experience_sidebar_card_shadow' => array( 'label' => __( 'Sombra dos cards laterais', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'group' => __( 'Cards laterais', EOP_TEXT_DOMAIN ), 'default' => '0 18px 38px rgba(15, 27, 53, .08)' ),
                    'customer_experience_sidebar_meta_background_color' => array( 'label' => __( 'Fundo das linhas internas', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Linhas internas', EOP_TEXT_DOMAIN ), 'default' => '#ffffff' ),
                    'customer_experience_sidebar_meta_border_color' => array( 'label' => __( 'Borda das linhas internas', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Linhas internas', EOP_TEXT_DOMAIN ), 'default' => '#dbe3f0' ),
                    'customer_experience_sidebar_meta_radius' => array( 'label' => __( 'Radius das linhas internas', EOP_TEXT_DOMAIN ), 'type' => 'size', 'group' => __( 'Linhas internas', EOP_TEXT_DOMAIN ), 'default' => '18px' ),
                ),
            ),
            array(
                'label'       => __( 'Botao confirmar proposta', EOP_TEXT_DOMAIN ),
                'description' => __( 'Mantenha no mesmo bloco o toggle, o texto e todo o visual do CTA principal da proposta.', EOP_TEXT_DOMAIN ),
                'expanded'    => false,
                'fields'      => array(
                    'proposal_button_enabled'             => array( 'label' => __( 'Exibir botao', EOP_TEXT_DOMAIN ), 'type' => 'select', 'group' => __( 'Comportamento', EOP_TEXT_DOMAIN ), 'default' => 'yes', 'choices' => $yes_no_choices ),
                    'proposal_button_label'               => array( 'label' => __( 'Texto do botao', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Comportamento', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_primary_button_background_color' => array( 'label' => __( 'Fundo do botao', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Fundo e borda', EOP_TEXT_DOMAIN ), 'default' => '#00ffff' ),
                    'customer_experience_primary_button_text_color'  => array( 'label' => __( 'Cor do texto', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Fundo e borda', EOP_TEXT_DOMAIN ), 'default' => '#ffffff' ),
                    'customer_experience_button_border_width' => array( 'label' => __( 'Largura da borda', EOP_TEXT_DOMAIN ), 'type' => 'size', 'group' => __( 'Fundo e borda', EOP_TEXT_DOMAIN ), 'default' => '0px' ),
                    'customer_experience_button_border_style' => array( 'label' => __( 'Estilo da borda', EOP_TEXT_DOMAIN ), 'type' => 'select', 'group' => __( 'Fundo e borda', EOP_TEXT_DOMAIN ), 'default' => 'solid', 'choices' => $border_style_choices ),
                    'customer_experience_button_border_color' => array( 'label' => __( 'Cor da borda', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Fundo e borda', EOP_TEXT_DOMAIN ), 'default' => '#00ffff' ),
                    'customer_experience_button_font_size'   => array( 'label' => __( 'Tamanho da fonte', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => '16px' ),
                    'customer_experience_button_line_height' => array( 'label' => __( 'Line-height', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => '1' ),
                    'customer_experience_button_font_weight' => array( 'label' => __( 'Peso da fonte', EOP_TEXT_DOMAIN ), 'type' => 'select', 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => '700', 'choices' => $font_weight_choices ),
                    'customer_experience_button_padding'     => array( 'label' => __( 'Padding', EOP_TEXT_DOMAIN ), 'type' => 'box', 'group' => __( 'Espacamento e sombra', EOP_TEXT_DOMAIN ), 'default' => '0 22px' ),
                    'customer_experience_button_radius'      => array( 'label' => __( 'Radius', EOP_TEXT_DOMAIN ), 'type' => 'size', 'group' => __( 'Espacamento e sombra', EOP_TEXT_DOMAIN ), 'default' => '18px' ),
                    'customer_experience_button_shadow'      => array( 'label' => __( 'Sombra', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'group' => __( 'Espacamento e sombra', EOP_TEXT_DOMAIN ), 'default' => '0 16px 30px rgba(0, 255, 255, .20)' ),
                ),
            ),
            array(
                'label'       => __( 'Botao ir para pagamento', EOP_TEXT_DOMAIN ),
                'description' => __( 'Ajuste o texto, visibilidade e o estilo completo do CTA secundario de pagamento.', EOP_TEXT_DOMAIN ),
                'expanded'    => false,
                'fields'      => array(
                    'proposal_pay_button_enabled'         => array( 'label' => __( 'Exibir botao', EOP_TEXT_DOMAIN ), 'type' => 'select', 'group' => __( 'Comportamento', EOP_TEXT_DOMAIN ), 'default' => 'yes', 'choices' => $yes_no_choices ),
                    'proposal_pay_button_label'           => array( 'label' => __( 'Texto do botao', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Comportamento', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_secondary_button_background_color' => array( 'label' => __( 'Fundo do botao', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Fundo e borda', EOP_TEXT_DOMAIN ), 'default' => '#f6f8fc' ),
                    'customer_experience_secondary_button_text_color' => array( 'label' => __( 'Cor do texto', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Fundo e borda', EOP_TEXT_DOMAIN ), 'default' => '#16243a' ),
                    'customer_experience_secondary_button_border_color' => array( 'label' => __( 'Cor da borda', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Fundo e borda', EOP_TEXT_DOMAIN ), 'default' => '#dbe3f0' ),
                    'customer_experience_secondary_button_border_width' => array( 'label' => __( 'Largura da borda', EOP_TEXT_DOMAIN ), 'type' => 'size', 'group' => __( 'Fundo e borda', EOP_TEXT_DOMAIN ), 'default' => '1px' ),
                    'customer_experience_secondary_button_border_style' => array( 'label' => __( 'Estilo da borda', EOP_TEXT_DOMAIN ), 'type' => 'select', 'group' => __( 'Fundo e borda', EOP_TEXT_DOMAIN ), 'default' => 'solid', 'choices' => $border_style_choices ),
                    'customer_experience_secondary_button_font_size' => array( 'label' => __( 'Tamanho da fonte', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => '16px' ),
                    'customer_experience_secondary_button_line_height' => array( 'label' => __( 'Line-height', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => '1' ),
                    'customer_experience_secondary_button_font_weight' => array( 'label' => __( 'Peso da fonte', EOP_TEXT_DOMAIN ), 'type' => 'select', 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => '700', 'choices' => $font_weight_choices ),
                    'customer_experience_secondary_button_padding'   => array( 'label' => __( 'Padding', EOP_TEXT_DOMAIN ), 'type' => 'box', 'group' => __( 'Espacamento e sombra', EOP_TEXT_DOMAIN ), 'default' => '0 22px' ),
                    'customer_experience_secondary_button_radius'    => array( 'label' => __( 'Radius', EOP_TEXT_DOMAIN ), 'type' => 'size', 'group' => __( 'Espacamento e sombra', EOP_TEXT_DOMAIN ), 'default' => '18px' ),
                    'customer_experience_secondary_button_shadow'    => array( 'label' => __( 'Sombra', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'group' => __( 'Espacamento e sombra', EOP_TEXT_DOMAIN ), 'default' => 'none' ),
                ),
            ),
            array(
                'label'       => __( 'Botao baixar PDF', EOP_TEXT_DOMAIN ),
                'description' => __( 'Concentre aqui a exibicao, o texto e os ajustes visuais do botao de download do PDF.', EOP_TEXT_DOMAIN ),
                'expanded'    => false,
                'fields'      => array(
                    'proposal_pdf_button_enabled'         => array( 'label' => __( 'Exibir botao', EOP_TEXT_DOMAIN ), 'type' => 'select', 'group' => __( 'Comportamento', EOP_TEXT_DOMAIN ), 'default' => 'yes', 'choices' => $yes_no_choices ),
                    'proposal_pdf_button_label'           => array( 'label' => __( 'Texto do botao', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Comportamento', EOP_TEXT_DOMAIN ), 'default' => __( 'Baixar PDF', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_pdf_button_background_color' => array( 'label' => __( 'Fundo do botao', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Fundo e borda', EOP_TEXT_DOMAIN ), 'default' => '#ffffff' ),
                    'customer_experience_pdf_button_text_color' => array( 'label' => __( 'Cor do texto', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Fundo e borda', EOP_TEXT_DOMAIN ), 'default' => '#16243a' ),
                    'customer_experience_pdf_button_border_color' => array( 'label' => __( 'Cor da borda', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Fundo e borda', EOP_TEXT_DOMAIN ), 'default' => '#dbe3f0' ),
                    'customer_experience_pdf_button_border_width' => array( 'label' => __( 'Largura da borda', EOP_TEXT_DOMAIN ), 'type' => 'size', 'group' => __( 'Fundo e borda', EOP_TEXT_DOMAIN ), 'default' => '1px' ),
                    'customer_experience_pdf_button_border_style' => array( 'label' => __( 'Estilo da borda', EOP_TEXT_DOMAIN ), 'type' => 'select', 'group' => __( 'Fundo e borda', EOP_TEXT_DOMAIN ), 'default' => 'solid', 'choices' => $border_style_choices ),
                    'customer_experience_pdf_button_font_size'       => array( 'label' => __( 'Tamanho da fonte', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => '16px' ),
                    'customer_experience_pdf_button_line_height'     => array( 'label' => __( 'Line-height', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => '1' ),
                    'customer_experience_pdf_button_font_weight'     => array( 'label' => __( 'Peso da fonte', EOP_TEXT_DOMAIN ), 'type' => 'select', 'group' => __( 'Tipografia', EOP_TEXT_DOMAIN ), 'default' => '700', 'choices' => $font_weight_choices ),
                    'customer_experience_pdf_button_padding'         => array( 'label' => __( 'Padding', EOP_TEXT_DOMAIN ), 'type' => 'box', 'group' => __( 'Espacamento e sombra', EOP_TEXT_DOMAIN ), 'default' => '0 22px' ),
                    'customer_experience_pdf_button_radius'          => array( 'label' => __( 'Radius', EOP_TEXT_DOMAIN ), 'type' => 'size', 'group' => __( 'Espacamento e sombra', EOP_TEXT_DOMAIN ), 'default' => '18px' ),
                    'customer_experience_pdf_button_shadow'          => array( 'label' => __( 'Sombra', EOP_TEXT_DOMAIN ), 'type' => 'shadow', 'group' => __( 'Espacamento e sombra', EOP_TEXT_DOMAIN ), 'default' => 'none' ),
                ),
            ),
            array(
                'label'       => __( 'Alertas e estados', EOP_TEXT_DOMAIN ),
                'description' => __( 'Centralize os textos demonstrativos do preview, mensagens de status e o visual dos alertas exibidos ao cliente.', EOP_TEXT_DOMAIN ),
                'expanded'    => false,
                'fields'      => array(
                    'customer_experience_alert_note'      => array( 'label' => __( 'Texto do alerta/observacao', EOP_TEXT_DOMAIN ), 'type' => 'textarea', 'full' => true, 'group' => __( 'Mensagens', EOP_TEXT_DOMAIN ), 'default' => '' ),
                    'customer_experience_success_message' => array( 'label' => __( 'Mensagem de sucesso', EOP_TEXT_DOMAIN ), 'type' => 'text', 'full' => true, 'group' => __( 'Mensagens', EOP_TEXT_DOMAIN ), 'default' => __( 'Proposta confirmada com sucesso.', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_notes_label'     => array( 'label' => __( 'Label das observacoes', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Mensagens', EOP_TEXT_DOMAIN ), 'default' => __( 'Observacoes', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_preview_status_label' => array( 'label' => __( 'Texto do selo 1 no preview', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Preview', EOP_TEXT_DOMAIN ), 'default' => __( 'Preview ao vivo', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_preview_stage_label' => array( 'label' => __( 'Texto do selo 2 no preview', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Preview', EOP_TEXT_DOMAIN ), 'default' => __( 'Layout real', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_pending_status_text' => array( 'label' => __( 'Status pendente', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Estados reais', EOP_TEXT_DOMAIN ), 'default' => __( 'Aguardando confirmacao', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_confirmed_status_text' => array( 'label' => __( 'Status confirmado', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Estados reais', EOP_TEXT_DOMAIN ), 'default' => __( 'Proposta confirmada', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_stage_prefix'    => array( 'label' => __( 'Prefixo da etapa atual', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Estados reais', EOP_TEXT_DOMAIN ), 'default' => __( 'Etapa atual:', EOP_TEXT_DOMAIN ) ),
                    'customer_experience_alert_background_color' => array( 'label' => __( 'Fundo do alerta', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Visual do alerta', EOP_TEXT_DOMAIN ), 'default' => '#ecfdf5' ),
                    'customer_experience_alert_border_color' => array( 'label' => __( 'Borda do alerta', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Visual do alerta', EOP_TEXT_DOMAIN ), 'default' => '#bbf7d0' ),
                    'customer_experience_alert_text_color' => array( 'label' => __( 'Texto do alerta', EOP_TEXT_DOMAIN ), 'type' => 'color', 'group' => __( 'Visual do alerta', EOP_TEXT_DOMAIN ), 'default' => '#166534' ),
                    'customer_experience_alert_font_size'  => array( 'label' => __( 'Tamanho da fonte do alerta', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Visual do alerta', EOP_TEXT_DOMAIN ), 'default' => '15px' ),
                    'customer_experience_alert_line_height' => array( 'label' => __( 'Line-height do alerta', EOP_TEXT_DOMAIN ), 'type' => 'text', 'group' => __( 'Visual do alerta', EOP_TEXT_DOMAIN ), 'default' => '1.6' ),
                    'customer_experience_alert_radius'     => array( 'label' => __( 'Radius do alerta', EOP_TEXT_DOMAIN ), 'type' => 'size', 'group' => __( 'Visual do alerta', EOP_TEXT_DOMAIN ), 'default' => '18px' ),
                    'customer_experience_alert_padding'    => array( 'label' => __( 'Padding do alerta', EOP_TEXT_DOMAIN ), 'type' => 'box', 'group' => __( 'Visual do alerta', EOP_TEXT_DOMAIN ), 'default' => '14px 16px' ),
                ),
            ),
        );
    }

    /**
     * Renderiza um accordion individual do editor visual do link.
     *
     * Recebe uma secao declarativa de get_order_link_visual_sections(),
     * quebra os campos em grupos internos e imprime o HTML do accordion.
     * Se o layout de um bloco inteiro precisar mudar, geralmente a alteracao
     * acontece aqui, e nao na definicao dos campos.
     *
     * @param array $section  Definicao declarativa da secao.
     * @param array $settings Configuracoes atuais do plugin.
     */
    private static function render_order_link_visual_section( $section, $settings ) {
        $field_groups = self::get_order_link_visual_field_groups( $section );
        $expanded     = ! empty( $section['expanded'] );
        ?>
        <div class="eop-accordion eop-visual-accordion">
            <button type="button" class="eop-accordion__toggle" aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>">
                <span>
                    <strong><?php echo esc_html( (string) $section['label'] ); ?></strong>
                    <small><?php echo esc_html( (string) $section['description'] ); ?></small>
                </span>
                <span class="eop-accordion__icon"><?php echo $expanded ? '-' : '+'; ?></span>
            </button>
            <div class="eop-accordion__body"<?php echo $expanded ? '' : ' hidden'; ?>>
                <div class="eop-visual-groups">
                    <?php foreach ( $field_groups as $group ) : ?>
                        <section class="eop-visual-group">
                            <?php if ( ! empty( $group['label'] ) ) : ?>
                                <header class="eop-visual-group__head">
                                    <strong><?php echo esc_html( (string) $group['label'] ); ?></strong>
                                </header>
                            <?php endif; ?>
                            <div class="eop-settings-grid">
                                <?php foreach ( $group['fields'] as $field_key => $field ) : ?>
                                    <?php self::render_order_link_visual_field( $field_key, $field, $settings ); ?>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Agrupa os campos de uma secao visual por subtitulo interno.
     *
     * Exemplo: dentro de uma mesma secao maior, podemos separar os campos em
     * grupos como "Titulo", "Descricao", "Botoes", "Tipografia" etc.
     * Isso deixa a manutencao mais simples porque a ordem visual pode ser
     * reorganizada sem reescrever o renderer principal.
     *
     * @param array $section Definicao declarativa da secao.
     * @return array[]
     */
    private static function get_order_link_visual_field_groups( $section ) {
        $groups = array();

        foreach ( (array) ( $section['fields'] ?? array() ) as $field_key => $field ) {
            $group_label = isset( $field['group'] ) ? (string) $field['group'] : self::infer_post_confirmation_visual_style_group( (string) ( $section['id'] ?? '' ), (string) $field_key );
            $group_key   = '' !== $group_label ? sanitize_title( $group_label ) : 'default';

            if ( ! isset( $groups[ $group_key ] ) ) {
                $groups[ $group_key ] = array(
                    'label'  => $group_label,
                    'fields' => array(),
                );
            }

            $groups[ $group_key ]['fields'][ $field_key ] = $field;
        }

        return array_values( $groups );
    }

    private static function infer_post_confirmation_visual_style_group( $section_id, $field_key ) {
        $map = array(
            'header' => array(
                'Container'         => array( 'header_background_', 'header_padding', 'header_margin', 'header_border_', 'header_box_shadow', 'header_min_height' ),
                'Cards internos'    => array( 'header_card_' ),
                'Titulo'            => array( 'header_title_' ),
                'Descricao'         => array( 'header_meta_' ),
                'Tipografia geral'  => array( 'header_font_family' ),
            ),
            'contract_header' => array(
                'Container'         => array( 'header_background_', 'header_padding', 'header_margin', 'header_border_', 'header_box_shadow', 'header_min_height' ),
                'Cards internos'    => array( 'header_card_' ),
                'Titulo'            => array( 'header_title_' ),
                'Descricao'         => array( 'header_meta_' ),
                'Tipografia geral'  => array( 'header_font_family' ),
            ),
            'breadcrumbs' => array(
                'Container'         => array( 'breadcrumb_gap' ),
                'Tipografia'        => array( 'breadcrumb_font_' ),
                'Item padrao'       => array( 'breadcrumb_padding', 'breadcrumb_border_', 'breadcrumb_background_', 'breadcrumb_text_' ),
                'Item atual'        => array( 'breadcrumb_current_background_', 'breadcrumb_current_text_' ),
                'Item concluido'    => array( 'breadcrumb_completed_background_', 'breadcrumb_completed_text_' ),
                'Numero'            => array( 'breadcrumb_index_', 'breadcrumb_current_index_' ),
            ),
            'contract_breadcrumbs' => array(
                'Container'         => array( 'breadcrumb_gap' ),
                'Tipografia'        => array( 'breadcrumb_font_' ),
                'Item padrao'       => array( 'breadcrumb_padding', 'breadcrumb_border_', 'breadcrumb_background_', 'breadcrumb_text_' ),
                'Item atual'        => array( 'breadcrumb_current_background_', 'breadcrumb_current_text_' ),
                'Item concluido'    => array( 'breadcrumb_completed_background_', 'breadcrumb_completed_text_' ),
                'Numero'            => array( 'breadcrumb_index_', 'breadcrumb_current_index_' ),
            ),
            'contract_reader' => array(
                'Container'         => array( 'reader_background_', 'reader_padding', 'reader_margin', 'reader_border_', 'reader_box_shadow' ),
                'Titulo'            => array( 'reader_title_' ),
                'Descricao'         => array( 'reader_description_' ),
                'Moldura'           => array( 'reader_frame_' ),
                'Conteudo HTML'     => array( 'reader_content_' ),
                'Tipografia geral'  => array( 'reader_font_family' ),
            ),
            'contract_cards' => array(
                'Cards adicionais'  => array( 'support_card_' ),
                'Aceite'            => array( 'acceptance_' ),
                'Texto de aceite'   => array( 'checkbox_' ),
                'Paineis laterais'  => array( 'sidebar_' ),
                'Resumo lateral'    => array( 'summary_' ),
            ),
            'contract_buttons' => array(
                'Botao principal'   => array( 'primary_button_' ),
                'Botao secundario'  => array( 'secondary_button_' ),
            ),
            'intro' => array(
                'Container'         => array( 'intro_background_', 'intro_padding', 'intro_margin', 'intro_border_', 'intro_box_shadow', 'intro_font_family' ),
                'Etiqueta superior' => array( 'intro_eyebrow_' ),
                'Titulo'            => array( 'intro_title_' ),
                'Descricao'         => array( 'intro_text_' ),
            ),
            'upload' => array(
                'Container'         => array( 'upload_background_', 'upload_padding', 'upload_margin', 'upload_border_', 'upload_box_shadow' ),
                'Label do arquivo'  => array( 'upload_label_' ),
                'Campo de upload'   => array( 'upload_input_' ),
                'Anexo salvo'       => array( 'upload_meta_' ),
            ),
            'products' => array(
                'Titulo'               => array( 'products_title_' ),
                'Descricao'            => array( 'products_description_' ),
                'Cabecalho da tabela'  => array( 'products_heading_' ),
                'Tipografia geral'     => array( 'products_font_family' ),
                'Linha do produto'     => array( 'products_row_' ),
                'Nome do produto'      => array( 'products_name_' ),
                'SKU'                  => array( 'products_sku_' ),
                'Input do novo nome'   => array( 'products_input_' ),
            ),
            'button' => array(
                'Botao'       => array( 'button_background_' ),
                'Tipografia'  => array( 'button_text_', 'button_font_' ),
                'Espacamento' => array( 'button_padding', 'button_margin' ),
                'Borda'       => array( 'button_border_' ),
                'Sombra'      => array( 'button_box_shadow' ),
            ),
        );

        if ( empty( $map[ $section_id ] ) ) {
            return '';
        }

        foreach ( $map[ $section_id ] as $label => $patterns ) {
            foreach ( $patterns as $pattern ) {
                if ( false !== strpos( $field_key, $pattern ) ) {
                    return __( $label, EOP_TEXT_DOMAIN );
                }
            }
        }

        return '';
    }

    private static function render_order_link_visual_field( $field_key, $field, $settings ) {
        $value      = $settings[ $field_key ] ?? ( $field['default'] ?? '' );
        $input_id   = 'eop_' . sanitize_html_class( $field_key ) . '_visual';
        $field_type = (string) ( $field['type'] ?? 'text' );
        $is_full    = ! empty( $field['full'] );
        $help       = isset( $field['help'] ) ? (string) $field['help'] : '';
        ?>
        <div class="eop-settings-field<?php echo $is_full ? ' is-full' : ''; ?>">
            <label for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( (string) $field['label'] ); ?></label>
            <?php if ( 'media' === $field_type ) : ?>
                <?php
                $buttons = wp_parse_args(
                    (array) ( $field['buttons'] ?? array() ),
                    array(
                        'select'  => __( 'Selecionar', EOP_TEXT_DOMAIN ),
                        'replace' => __( 'Trocar', EOP_TEXT_DOMAIN ),
                        'remove'  => __( 'Remover', EOP_TEXT_DOMAIN ),
                        'empty'   => __( 'Nenhum arquivo selecionado.', EOP_TEXT_DOMAIN ),
                    )
                );
                ?>
                <div class="eop-settings-media">
                    <div class="eop-settings-media__preview<?php echo ! empty( $value ) ? ' has-image' : ''; ?>" data-media-preview>
                        <?php if ( ! empty( $value ) ) : ?>
                            <img src="<?php echo esc_url( (string) $value ); ?>" alt="<?php esc_attr_e( 'Preview da logo', EOP_TEXT_DOMAIN ); ?>" />
                        <?php else : ?>
                            <span class="eop-settings-media__empty"><?php echo esc_html( $buttons['empty'] ); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="eop-settings-media__details">
                        <input
                            id="<?php echo esc_attr( $input_id ); ?>"
                            type="url"
                            class="eop-settings-media__url"
                            name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $field_key ); ?>]"
                            value="<?php echo esc_attr( (string) $value ); ?>"
                            readonly
                            data-media-url
                        />
                        <div class="eop-settings-media__actions">
                            <button type="button" class="button button-secondary eop-settings-media__select" data-media-select>
                                <?php echo ! empty( $value ) ? esc_html( $buttons['replace'] ) : esc_html( $buttons['select'] ); ?>
                            </button>
                            <button type="button" class="button-link-delete eop-settings-media__remove<?php echo ! empty( $value ) ? '' : ' is-hidden'; ?>" data-media-remove>
                                <?php echo esc_html( $buttons['remove'] ); ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php elseif ( 'color' === $field_type ) : ?>
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
            <?php if ( '' !== $help ) : ?>
                <small class="eop-settings-help"><?php echo esc_html( $help ); ?></small>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_post_confirmation_signature_document_item( $index, $document ) {
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
                        <div class="eop-signature-document__attachment-name" data-signature-document-attachment-name><?php echo esc_html( 'Nenhum arquivo anexado ainda.' ); ?></div>
                        <div class="eop-signature-document__attachment-actions">
                            <button type="button" class="button button-secondary" data-signature-document-attachment-select><?php echo esc_html( 'Selecionar arquivo' ); ?></button>
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
                                    <div class="eop-signature-document__attachment-name" data-signature-document-attachment-name><?php echo esc_html( 'Nenhum arquivo anexado ainda.' ); ?></div>
                                    <div class="eop-signature-document__attachment-actions">
                                        <button type="button" class="button button-secondary" data-signature-document-attachment-select><?php echo esc_html( 'Selecionar arquivo' ); ?></button>
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
            'post_confirmation_upload_saved_date_prefix' => self::build_help_tooltip_payload( __( 'Prefixo da data do anexo', EOP_TEXT_DOMAIN ), __( 'Texto usado antes da data de envio quando ja existe um arquivo salvo.', EOP_TEXT_DOMAIN ), __( 'Exemplo: Enviado em 14/05/2026.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_upload_saved_status_text' => self::build_help_tooltip_payload( __( 'Texto alternativo do anexo salvo', EOP_TEXT_DOMAIN ), __( 'Mensagem exibida quando existe arquivo salvo, mas sem data detalhada disponivel.', EOP_TEXT_DOMAIN ), __( 'Serve como fallback do status do anexo.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_upload_view_button_label' => self::build_help_tooltip_payload( __( 'Botao do anexo salvo', EOP_TEXT_DOMAIN ), __( 'Texto do botao secundario que abre o arquivo ja enviado pelo cliente.', EOP_TEXT_DOMAIN ), __( 'Aparece dentro do card do anexo salvo.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_products_title' => self::build_help_tooltip_payload( __( 'Titulo da personalizacao', EOP_TEXT_DOMAIN ), __( 'Titulo principal da etapa em que o cliente revisa ou personaliza informacoes dos produtos.', EOP_TEXT_DOMAIN ), __( 'Fica no topo da tela de personalizacao.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_products_description' => self::build_help_tooltip_payload( __( 'Descricao da personalizacao', EOP_TEXT_DOMAIN ), __( 'Texto auxiliar que explica como o cliente deve preencher ou revisar os dados dos produtos.', EOP_TEXT_DOMAIN ), __( 'Serve como instrucao curta dessa etapa.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_products_heading_sequence_label' => self::build_help_tooltip_payload( __( 'Coluna de sequencia', EOP_TEXT_DOMAIN ), __( 'Texto da primeira coluna da lista de produtos.', EOP_TEXT_DOMAIN ), __( 'Normalmente exibe a numeracao dos itens.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_products_heading_original_label' => self::build_help_tooltip_payload( __( 'Coluna do produto original', EOP_TEXT_DOMAIN ), __( 'Texto da coluna que mostra o nome atual do item.', EOP_TEXT_DOMAIN ), __( 'Ajuda o cliente a identificar qual produto esta editando.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_products_heading_custom_label' => self::build_help_tooltip_payload( __( 'Coluna do novo nome', EOP_TEXT_DOMAIN ), __( 'Texto da coluna do campo editavel de personalizacao.', EOP_TEXT_DOMAIN ), __( 'Define a chamada visual do novo nome do item.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_products_sku_prefix' => self::build_help_tooltip_payload( __( 'Prefixo do SKU', EOP_TEXT_DOMAIN ), __( 'Texto que aparece antes do codigo SKU do produto.', EOP_TEXT_DOMAIN ), __( 'Exemplo: SKU: ABC123.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_products_sku_empty_label' => self::build_help_tooltip_payload( __( 'Texto sem SKU', EOP_TEXT_DOMAIN ), __( 'Mensagem exibida quando o produto nao possui SKU cadastrado.', EOP_TEXT_DOMAIN ), __( 'Evita deixar essa area vazia na linha do item.', EOP_TEXT_DOMAIN ) ),
            'post_confirmation_products_locked_message' => self::build_help_tooltip_payload( __( 'Aviso do item bloqueado', EOP_TEXT_DOMAIN ), __( 'Mensagem exibida abaixo do campo quando o produto esta protegido contra alteracao.', EOP_TEXT_DOMAIN ), __( 'Explica ao cliente por que aquele item nao pode ser editado.', EOP_TEXT_DOMAIN ) ),
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
            'admin_rest_url'               => class_exists( 'EOP_Admin_SPA' ) ? esc_url_raw( rest_url( trailingslashit( EOP_Admin_SPA::REST_NAMESPACE ) ) ) : '',
            'rest_nonce'                   => wp_create_nonce( 'wp_rest' ),
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
            'new-order-style',
            'orders-list-style',
            'texts',
        );

        return in_array( $section, $allowed, true ) ? $section : 'all';
    }

    private static function should_render_admin_section( $active_section, $section_key ) {
        return self::normalize_admin_section( $active_section ) === sanitize_key( (string) $section_key ) || 'all' === self::normalize_admin_section( $active_section );
    }

    private static function render_admin_settings_template( $template, $vars = array() ) {
        $template = ltrim( (string) $template, '/\\' );
        $path     = trailingslashit( EOP_PLUGIN_DIR ) . 'templates/settings/' . $template;

        if ( ! file_exists( $path ) ) {
            return;
        }

        if ( ! empty( $vars ) ) {
            extract( $vars, EXTR_SKIP );
        }

        include $path;
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
        $experimental_admin            = $should_render_general_config && class_exists( 'EOP_Admin_SPA' )
            ? EOP_Admin_SPA::get_settings()
            : array(
                'enabled' => 'no',
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
                    <?php if ( $should_render_general_config ) : ?>
                        <?php self::render_admin_settings_template( 'embedded/general-config.php', compact( 'settings', 'pages', 'service_selector', 'service_category_selector', 'experimental_admin' ) ); ?>
                    <?php endif; ?>

                    <?php if ( $should_render_confirmation_general ) : ?>
                        <?php self::render_admin_settings_template( 'embedded/confirmation-general.php', compact( 'settings', 'locked_selector' ) ); ?>
                    <?php endif; ?>

                    <?php if ( $should_render_confirmation_documents ) : ?>
                        <?php self::render_admin_settings_template( 'embedded/confirmation-documents.php', compact( 'signature_documents' ) ); ?>
                    <?php endif; ?>

                    <?php if ( $should_render_confirmation_preview ) : ?>
                        <?php self::render_admin_settings_template( 'embedded/confirmation-preview.php', compact( 'settings' ) ); ?>
                    <?php endif; ?>

                    <?php if ( $should_render_confirmation_upload_products_preview ) : ?>
                        <?php self::render_admin_settings_template( 'embedded/confirmation-upload-products-preview.php', compact( 'settings' ) ); ?>
                    <?php endif; ?>

                    <?php if ( self::should_render_admin_section( $section, 'proposal-link-style' ) ) : ?>
                        <?php self::render_admin_settings_template( 'embedded/proposal-link-style.php', compact( 'settings' ) ); ?>
                    <?php endif; ?>

                    <?php if ( self::should_render_admin_section( $section, 'new-order-style' ) ) : ?>
                        <?php self::render_admin_settings_template( 'embedded/new-order-style.php', compact( 'settings' ) ); ?>
                    <?php endif; ?>

                    <?php if ( self::should_render_admin_section( $section, 'orders-list-style' ) ) : ?>
                        <?php self::render_admin_settings_template( 'embedded/orders-list-style.php', compact( 'settings' ) ); ?>
                    <?php endif; ?>

                    <?php if ( self::should_render_admin_section( $section, 'texts' ) ) : ?>
                        <?php self::render_admin_settings_template( 'embedded/texts.php', compact( 'settings' ) ); ?>
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
