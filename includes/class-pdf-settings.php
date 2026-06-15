<?php
defined( 'ABSPATH' ) || exit;

class EOP_PDF_Settings {

    const OPTION_KEY = 'eop_pdf_settings';

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
    }

    public static function register_settings() {
        register_setting(
            'eop_pdf_settings_group',
            self::OPTION_KEY,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
                'default'           => self::get_defaults(),
            )
        );
    }

    public static function get_all() {
        return self::apply_document_label_fallbacks(
            self::apply_legacy_pdf_fallbacks(
                self::apply_shared_store_defaults( wp_parse_args( get_option( self::OPTION_KEY, array() ), self::get_defaults() ) )
            )
        );
    }

    public static function get( $key, $default = null ) {
        $settings = self::get_all();

        if ( array_key_exists( $key, $settings ) ) {
            return $settings[ $key ];
        }

        return null === $default ? '' : $default;
    }

    public static function get_document_item_label_defaults( $document_type = 'order' ) {
        $document_type = 'proposal' === sanitize_key( (string) $document_type ) ? 'proposal' : 'order';
        $defaults      = self::get_defaults();

        return array(
            'item_index'            => (string) $defaults[ $document_type . '_item_index_label' ],
            'product'               => (string) $defaults[ $document_type . '_product_label' ],
            'quantity'              => (string) $defaults[ $document_type . '_quantity_label' ],
            'unit_price'            => (string) $defaults[ $document_type . '_unit_price_label' ],
            'discount'              => (string) $defaults[ $document_type . '_discount_label' ],
            'discounted_unit_price' => (string) $defaults[ $document_type . '_discounted_unit_price_label' ],
            'line_total'            => (string) $defaults[ $document_type . '_line_total_label' ],
        );
    }

    public static function get_document_item_position_defaults( $document_type = 'order' ) {
        $document_type = 'proposal' === sanitize_key( (string) $document_type ) ? 'proposal' : 'order';
        $defaults      = self::get_defaults();

        return array(
            'quantity'              => (string) $defaults[ $document_type . '_quantity_position' ],
            'unit_price'            => (string) $defaults[ $document_type . '_unit_price_position' ],
            'discount'              => (string) $defaults[ $document_type . '_discount_position' ],
            'discounted_unit_price' => (string) $defaults[ $document_type . '_discounted_unit_price_position' ],
            'line_total'            => (string) $defaults[ $document_type . '_line_total_position' ],
        );
    }

    public static function get_document_visual_defaults( $document_type = 'order' ) {
        $document_type = 'proposal' === sanitize_key( (string) $document_type ) ? 'proposal' : 'order';
        $defaults      = self::get_defaults();

        return array(
            'header_background_color' => (string) $defaults[ $document_type . '_header_background_color' ],
            'header_text_color'       => (string) $defaults[ $document_type . '_header_text_color' ],
            'body_text_color'         => (string) $defaults[ $document_type . '_body_text_color' ],
            'muted_text_color'        => (string) $defaults[ $document_type . '_muted_text_color' ],
            'border_color'            => (string) $defaults[ $document_type . '_border_color' ],
            'title_font_size'         => (string) $defaults[ $document_type . '_title_font_size' ],
            'meta_font_size'          => (string) $defaults[ $document_type . '_meta_font_size' ],
            'table_header_font_size'  => (string) $defaults[ $document_type . '_table_header_font_size' ],
            'table_body_font_size'    => (string) $defaults[ $document_type . '_table_body_font_size' ],
            'table_body_line_height'   => (string) $defaults[ $document_type . '_table_body_line_height' ],
            'totals_font_size'        => (string) $defaults[ $document_type . '_totals_font_size' ],
            'note_font_size'          => (string) $defaults[ $document_type . '_note_font_size' ],
        );
    }

    public static function get_legacy_document_item_label_defaults( $document_type = 'order' ) {
        $document_type = 'proposal' === sanitize_key( (string) $document_type ) ? 'proposal' : 'order';

        return array(
            'product'               => __( 'Produto', EOP_TEXT_DOMAIN ),
            'quantity'              => __( 'Quantidade', EOP_TEXT_DOMAIN ),
            'unit_price'            => __( 'Valor unitario', EOP_TEXT_DOMAIN ),
            'discount'              => __( 'Desconto aplicado', EOP_TEXT_DOMAIN ),
            'discounted_unit_price' => __( 'Valor unitario com desconto', EOP_TEXT_DOMAIN ),
            'line_total'            => __( 'Total', EOP_TEXT_DOMAIN ),
        );
    }

    public static function resolve_document_item_label( $document_type, $label_key, $configured, $default ) {
        $configured = sanitize_text_field( (string) $configured );
        $default    = sanitize_text_field( (string) $default );

        if ( '' === $configured ) {
            return $default;
        }

        $legacy_defaults = self::get_legacy_document_item_label_defaults( $document_type );
        $legacy_default  = sanitize_text_field( (string) ( $legacy_defaults[ $label_key ] ?? '' ) );

        if ( '' !== $legacy_default && $configured === $legacy_default && $default !== $legacy_default ) {
            return $default;
        }

        return $configured;
    }

    public static function get_output_signature( $settings = null ) {
        $settings = self::apply_document_label_fallbacks(
            self::apply_legacy_pdf_fallbacks(
                self::apply_shared_store_defaults( wp_parse_args( is_array( $settings ) ? $settings : array(), self::get_defaults() ) )
            )
        );

        $signature_settings = array(
            'display_mode'             => (string) $settings['display_mode'],
            'paper_size'               => (string) $settings['paper_size'],
            'template_name'            => (string) $settings['template_name'],
            'ink_saving_mode'          => (string) $settings['ink_saving_mode'],
            'font_subsetting'          => (string) $settings['font_subsetting'],
            'extended_currency_symbol' => (string) $settings['extended_currency_symbol'],
            'shop_logo_url'            => (string) $settings['shop_logo_url'],
            'shop_logo_height'         => (string) $settings['shop_logo_height'],
            'shop_name'                => (string) $settings['shop_name'],
            'shop_address_line_1'      => (string) $settings['shop_address_line_1'],
            'shop_address_line_2'      => (string) $settings['shop_address_line_2'],
            'shop_city'                => (string) $settings['shop_city'],
            'shop_state'               => (string) $settings['shop_state'],
            'shop_postcode'            => (string) $settings['shop_postcode'],
            'shop_country'             => (string) $settings['shop_country'],
            'shop_phone'               => (string) $settings['shop_phone'],
            'shop_email'               => (string) $settings['shop_email'],
            'shop_vat_number'          => (string) $settings['shop_vat_number'],
            'shop_chamber_of_commerce' => (string) $settings['shop_chamber_of_commerce'],
            'shop_extra_1'             => (string) $settings['shop_extra_1'],
            'shop_extra_2'             => (string) $settings['shop_extra_2'],
            'shop_extra_3'             => (string) $settings['shop_extra_3'],
            'shop_footer'              => (string) $settings['shop_footer'],
        );

        foreach ( array( 'order', 'proposal' ) as $document_type ) {
            foreach (
                array(
                    'enabled',
                    'show_shipping',
                    'show_billing',
                    'show_email',
                    'show_phone',
                    'show_notes',
                    'show_sku',
                    'show_item_index',
                    'show_quantity',
                    'show_unit_price',
                    'show_discount',
                    'show_discounted_unit_price',
                    'show_line_total',
                    'item_index_label',
                    'product_label',
                    'quantity_label',
                    'unit_price_label',
                    'discount_label',
                    'discount_suffix',
                    'discount_display_mode',
                    'discounted_unit_price_label',
                    'discounted_unit_price_suffix',
                    'line_total_label',
                    'quantity_position',
                    'unit_price_position',
                    'discount_position',
                    'discounted_unit_price_position',
                    'line_total_position',
                    'header_background_color',
                    'header_text_color',
                    'body_text_color',
                    'muted_text_color',
                    'border_color',
                    'title_font_size',
                    'meta_font_size',
                    'table_header_font_size',
                    'table_body_font_size',
                    'table_body_line_height',
                    'totals_font_size',
                    'note_font_size',
                    'show_total_subtotal',
                    'show_total_shipping',
                    'show_total_discount',
                    'show_total_total',
                    'prefix',
                    'suffix',
                    'padding',
                ) as $key
            ) {
                $signature_settings[ $document_type . '_' . $key ] = (string) $settings[ $document_type . '_' . $key ];
            }
        }

        return md5( (string) wp_json_encode( $signature_settings ) );
    }

    public static function get_admin_tooltip_map() {
        $fields = self::get_field_definitions();

        return array(
            'eop_pdf_display_mode'             => self::build_tooltip_payload( $fields['display_mode'] ),
            'eop_pdf_paper_size'               => self::build_tooltip_payload( $fields['paper_size'] ),
            'eop_pdf_template_name'            => self::build_tooltip_payload( $fields['template_name'] ),
            'eop_pdf_ink_saving_mode'          => self::build_tooltip_payload( $fields['ink_saving_mode'] ),
            'eop_pdf_test_mode'                => self::build_tooltip_payload( $fields['test_mode'] ),
            'eop_pdf_font_subsetting'          => self::build_tooltip_payload( $fields['font_subsetting'] ),
            'eop_shop_logo_height'             => self::build_tooltip_payload( $fields['shop_logo_height'] ),
            'eop_shop_name'                    => self::build_tooltip_payload( $fields['shop_name'] ),
            'eop_shop_address_line_1'          => self::build_tooltip_payload( $fields['shop_address_line_1'] ),
            'eop_shop_address_line_2'          => self::build_tooltip_payload( $fields['shop_address_line_2'] ),
            'eop_shop_city'                    => self::build_tooltip_payload( $fields['shop_city'] ),
            'eop_shop_state'                   => self::build_tooltip_payload( $fields['shop_state'] ),
            'eop_shop_postcode'                => self::build_tooltip_payload( $fields['shop_postcode'] ),
            'eop_shop_country'                 => self::build_tooltip_payload( $fields['shop_country'] ),
            'eop_shop_phone'                   => self::build_tooltip_payload( $fields['shop_phone'] ),
            'eop_shop_email'                   => self::build_tooltip_payload( $fields['shop_email'] ),
            'eop_shop_vat_number'              => self::build_tooltip_payload( $fields['shop_vat_number'] ),
            'eop_shop_chamber_of_commerce'     => self::build_tooltip_payload( $fields['shop_chamber_of_commerce'] ),
            'eop_shop_extra_1'                 => self::build_tooltip_payload( $fields['shop_extra_1'] ),
            'eop_shop_extra_2'                 => self::build_tooltip_payload( $fields['shop_extra_2'] ),
            'eop_shop_extra_3'                 => self::build_tooltip_payload( $fields['shop_extra_3'] ),
            'eop_shop_footer'                  => self::build_tooltip_payload( $fields['shop_footer'] ),
            'eop_doc_enabled'                  => self::build_tooltip_payload( $fields['document_enabled'] ),
            'eop_doc_attach_email'             => self::build_tooltip_payload( $fields['document_attach_email'] ),
            'eop_doc_show_shipping'            => self::build_tooltip_payload( $fields['document_show_shipping'] ),
            'eop_doc_show_billing'             => self::build_tooltip_payload( $fields['document_show_billing'] ),
            'eop_doc_show_email'               => self::build_tooltip_payload( $fields['document_show_email'] ),
            'eop_doc_show_phone'               => self::build_tooltip_payload( $fields['document_show_phone'] ),
            'eop_doc_show_notes'               => self::build_tooltip_payload( $fields['document_show_notes'] ),
            'eop_doc_mark_printed'             => self::build_tooltip_payload( $fields['document_mark_printed'] ),
            'eop_doc_prefix'                   => self::build_tooltip_payload( $fields['document_prefix'] ),
            'eop_doc_suffix'                   => self::build_tooltip_payload( $fields['document_suffix'] ),
            'eop_doc_padding'                  => self::build_tooltip_payload( $fields['document_padding'] ),
            'eop_doc_next_number'              => self::build_tooltip_payload( $fields['document_next_number'] ),
            'eop_doc_reset_yearly'             => self::build_tooltip_payload( $fields['document_reset_yearly'] ),
            'eop_doc_myaccount'                => self::build_tooltip_payload( $fields['order_myaccount_download'] ),
            'eop_doc_public_pdf'               => self::build_tooltip_payload( $fields['proposal_public_pdf'] ),
            'eop_doc_show_sku'                 => self::build_tooltip_payload( $fields['document_show_sku'] ),
            'eop_doc_show_quantity'            => self::build_tooltip_payload( $fields['document_show_quantity'] ),
            'eop_doc_show_unit_price'          => self::build_tooltip_payload( $fields['document_show_unit_price'] ),
            'eop_doc_show_discount'            => self::build_tooltip_payload( $fields['document_show_discount'] ),
            'eop_doc_discount_display_mode'     => self::build_tooltip_payload( $fields['document_discount_display_mode'] ),
            'eop_doc_show_discounted_unit_price' => self::build_tooltip_payload( $fields['document_show_discounted_unit_price'] ),
            'eop_doc_show_line_total'          => self::build_tooltip_payload( $fields['document_show_line_total'] ),
            'eop_doc_product_label'            => self::build_tooltip_payload( $fields['document_product_label'] ),
            'eop_doc_quantity_label'           => self::build_tooltip_payload( $fields['document_quantity_label'] ),
            'eop_doc_unit_price_label'         => self::build_tooltip_payload( $fields['document_unit_price_label'] ),
            'eop_doc_discount_label'           => self::build_tooltip_payload( $fields['document_discount_label'] ),
            'eop_doc_discount_suffix'          => self::build_tooltip_payload( $fields['document_discount_suffix'] ),
            'eop_doc_discounted_unit_price_label' => self::build_tooltip_payload( $fields['document_discounted_unit_price_label'] ),
            'eop_doc_discounted_unit_price_suffix' => self::build_tooltip_payload( $fields['document_discounted_unit_price_suffix'] ),
            'eop_doc_line_total_label'         => self::build_tooltip_payload( $fields['document_line_total_label'] ),
            'eop_doc_show_total_subtotal'      => self::build_tooltip_payload( $fields['document_show_total_subtotal'] ),
            'eop_doc_show_total_shipping'      => self::build_tooltip_payload( $fields['document_show_total_shipping'] ),
            'eop_doc_show_total_discount'      => self::build_tooltip_payload( $fields['document_show_total_discount'] ),
            'eop_doc_show_total_total'         => self::build_tooltip_payload( $fields['document_show_total_total'] ),
            'eop_edoc_enabled'                 => self::build_tooltip_payload( $fields['edoc_enabled'] ),
            'eop_edoc_format'                  => self::build_tooltip_payload( $fields['edoc_format'] ),
            'eop_edoc_embed_pdf'               => self::build_tooltip_payload( $fields['edoc_embed_pdf'] ),
            'eop_edoc_preview_xml'             => self::build_tooltip_payload( $fields['edoc_preview_xml'] ),
            'eop_edoc_logging'                 => self::build_tooltip_payload( $fields['edoc_logging'] ),
            'eop_edoc_supplier_scheme'         => self::build_tooltip_payload( $fields['edoc_supplier_scheme'] ),
            'eop_edoc_customer_scheme'         => self::build_tooltip_payload( $fields['edoc_customer_scheme'] ),
            'eop_edoc_network_endpoint'        => self::build_tooltip_payload( $fields['edoc_network_endpoint'] ),
            'eop_edoc_network_eas'             => self::build_tooltip_payload( $fields['edoc_network_eas'] ),
            'eop_adv_link_access'              => self::build_tooltip_payload( $fields['advanced_link_access'] ),
            'eop_adv_pretty_links'             => self::build_tooltip_payload( $fields['advanced_pretty_links'] ),
            'eop_adv_html_output'              => self::build_tooltip_payload( $fields['advanced_html_output'] ),
            'eop_adv_debug'                    => self::build_tooltip_payload( $fields['advanced_debug'] ),
            'eop_adv_order_note_logs'          => self::build_tooltip_payload( $fields['advanced_order_note_logs'] ),
            'eop_adv_auto_cleanup'             => self::build_tooltip_payload( $fields['advanced_auto_cleanup'] ),
            'eop_adv_danger_zone'              => self::build_tooltip_payload( $fields['advanced_danger_zone'] ),
        );
    }

    public static function get_documentation_sections() {
        $fields = self::get_field_definitions();

        return array(
            array(
                'title'       => __( 'Visao geral', EOP_TEXT_DOMAIN ),
                'description' => __( 'Estas configuracoes controlam geracao, acesso, layout e distribuicao dos documentos PDF e XML do modulo.', EOP_TEXT_DOMAIN ),
                'fields'      => array(),
            ),
            array(
                'title'       => __( 'Configuracoes de exibicao', EOP_TEXT_DOMAIN ),
                'description' => __( 'Afetam como o PDF final e o preview sao montados.', EOP_TEXT_DOMAIN ),
                'fields'      => self::collect_documentation_fields( $fields, array( 'display_mode', 'paper_size', 'template_name', 'ink_saving_mode', 'test_mode', 'font_subsetting' ) ),
            ),
            array(
                'title'       => __( 'Informacoes da loja', EOP_TEXT_DOMAIN ),
                'description' => __( 'Montam o cabeçalho, os dados institucionais e o rodape do documento.', EOP_TEXT_DOMAIN ),
                'fields'      => self::collect_documentation_fields( $fields, array( 'shop_logo_url', 'shop_logo_height', 'shop_name', 'shop_address_line_1', 'shop_address_line_2', 'shop_city', 'shop_state', 'shop_postcode', 'shop_country', 'shop_phone', 'shop_email', 'shop_vat_number', 'shop_chamber_of_commerce', 'shop_extra_1', 'shop_extra_2', 'shop_extra_3', 'shop_footer' ) ),
            ),
            array(
                'title'       => __( 'Configuracoes por documento', EOP_TEXT_DOMAIN ),
                'description' => __( 'Valem para Pedido e Proposta, cada um com valores independentes.', EOP_TEXT_DOMAIN ),
                'fields'      => self::collect_documentation_fields( $fields, array( 'document_enabled', 'document_attach_email', 'document_show_shipping', 'document_show_billing', 'document_show_email', 'document_show_phone', 'document_show_notes', 'document_mark_printed', 'document_prefix', 'document_suffix', 'document_padding', 'document_next_number', 'document_reset_yearly', 'order_myaccount_download', 'proposal_public_pdf' ) ),
            ),
            array(
                'title'       => __( 'Colunas e totais', EOP_TEXT_DOMAIN ),
                'description' => __( 'Definem quais colunas aparecem no detalhamento, como sao nomeadas e quais totais vao ao final do documento.', EOP_TEXT_DOMAIN ),
                'fields'      => self::collect_documentation_fields( $fields, array( 'document_show_sku', 'document_show_quantity', 'document_show_unit_price', 'document_show_discount', 'document_discount_display_mode', 'document_show_discounted_unit_price', 'document_show_line_total', 'document_product_label', 'document_quantity_label', 'document_unit_price_label', 'document_discount_label', 'document_discount_suffix', 'document_discounted_unit_price_label', 'document_discounted_unit_price_suffix', 'document_line_total_label', 'document_show_total_subtotal', 'document_show_total_shipping', 'document_show_total_discount', 'document_show_total_total' ) ),
            ),
            array(
                'title'       => __( 'Visual do documento', EOP_TEXT_DOMAIN ),
                'description' => __( 'Ajusta ritmo visual das linhas, tamanho de texto e escolhas de exibicao na tabela.', EOP_TEXT_DOMAIN ),
                'fields'      => self::collect_documentation_fields( $fields, array( 'document_table_body_line_height', 'document_discount_display_mode', 'document_discount_suffix', 'document_discounted_unit_price_suffix' ) ),
            ),
            array(
                'title'       => __( 'Documentos eletronicos', EOP_TEXT_DOMAIN ),
                'description' => __( 'Camada experimental para estruturar XML tecnico do documento a partir do pedido selecionado no preview.', EOP_TEXT_DOMAIN ),
                'fields'      => self::collect_documentation_fields( $fields, array( 'edoc_enabled', 'edoc_format', 'edoc_embed_pdf', 'edoc_preview_xml', 'edoc_logging', 'edoc_supplier_scheme', 'edoc_customer_scheme', 'edoc_network_endpoint', 'edoc_network_eas' ) ),
            ),
            array(
                'title'       => __( 'Avancado', EOP_TEXT_DOMAIN ),
                'description' => __( 'Controla politica de acesso, fallback de preview, logging e ferramentas operacionais do modulo.', EOP_TEXT_DOMAIN ),
                'fields'      => self::collect_documentation_fields( $fields, array( 'advanced_link_access', 'advanced_pretty_links', 'advanced_html_output', 'advanced_debug', 'advanced_order_note_logs', 'advanced_auto_cleanup', 'advanced_danger_zone' ) ),
            ),
        );
    }

    private static function collect_documentation_fields( $definitions, $keys ) {
        $items = array();

        foreach ( $keys as $key ) {
            if ( isset( $definitions[ $key ] ) ) {
                $items[] = self::build_documentation_field( $definitions[ $key ] );
            }
        }

        return $items;
    }

    private static function build_tooltip_payload( $definition ) {
        return array(
            'label'  => (string) $definition['label'],
            'help'   => (string) $definition['help'],
            'effect' => (string) $definition['effect'],
            'status' => (string) $definition['status'],
        );
    }

    private static function build_documentation_field( $definition ) {
        return array(
            'label'   => (string) $definition['label'],
            'help'    => (string) $definition['help'],
            'effect'  => (string) $definition['effect'],
            'status'  => (string) $definition['status'],
            'values'  => isset( $definition['values'] ) && is_array( $definition['values'] ) ? $definition['values'] : array(),
        );
    }

    private static function get_field_definitions() {
        return array(
            'display_mode' => array(
                'label'  => __( 'Como voce deseja visualizar o PDF?', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Define se o navegador abre o arquivo em uma nova aba ou inicia download imediato.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Altera o header Content-Disposition da resposta PDF.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
                'values' => array( 'new_tab', 'download' ),
            ),
            'paper_size' => array(
                'label'  => __( 'Tamanho do papel', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Escolhe a area fisica do documento entre A4 e Letter.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Muda o tamanho do preview, do Dompdf e do PDF nativo.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
                'values' => array( 'a4', 'letter' ),
            ),
            'template_name' => array(
                'label'  => __( 'Escolha um modelo', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Seleciona a variacao visual usada para montar o HTML do documento.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Impacta preview, cache e a renderizacao quando o PDF sai pelo HTML/Dompdf.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
                'values' => array( 'simple', 'compact', 'minimal' ),
            ),
            'ink_saving_mode' => array(
                'label'  => __( 'Modo de economia de tinta', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Aplica uma versao mais enxuta do layout, com menos peso visual.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Afeta o HTML do preview e do PDF baseado em navegador.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
                'values' => array( 'no', 'yes' ),
            ),
            'test_mode' => array(
                'label'  => __( 'Modo de teste', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Marca visualmente o documento como ambiente de teste para evitar uso indevido.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Exibe selo de homologacao no preview e no PDF gerado.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
                'values' => array( 'no', 'yes' ),
            ),
            'font_subsetting' => array(
                'label'  => __( 'Font subsetting', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Controla se o Dompdf embute apenas os glifos usados ou a fonte inteira.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Pode reduzir o tamanho final do PDF quando a geracao usa Dompdf.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
                'values' => array( 'yes', 'no' ),
            ),
            'shop_logo_url' => array(
                'label'  => __( 'Logo/Cabecalho da loja', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Imagem principal mostrada no topo do documento.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Aparece no preview e no PDF HTML; no fallback nativo a identidade textual da loja continua sendo usada.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'shop_logo_height' => array(
                'label'  => __( 'Altura do logo', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Aceita medidas como 3cm, 72px ou 4rem para limitar a altura do logo.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Controla a altura maxima do logo renderizado no preview e no PDF HTML.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'shop_name' => array(
                'label'  => __( 'Nome da loja', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Nome institucional exibido no cabecalho do documento.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Aparece sempre no topo do documento.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'shop_address_line_1' => array(
                'label'  => __( 'Endereco da loja, linha 1', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Sincronizado automaticamente com o endereco da loja no WooCommerce.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Compoe o bloco institucional do documento.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'shop_address_line_2' => array(
                'label'  => __( 'Endereco da loja, linha 2', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Complemento sincronizado com o WooCommerce.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Complementa o endereco institucional quando preenchido.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'shop_city' => array(
                'label'  => __( 'Cidade', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Cidade da loja sincronizada com o WooCommerce.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Aparece no bloco institucional.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'shop_state' => array(
                'label'  => __( 'Estado', EOP_TEXT_DOMAIN ),
                'help'   => __( 'UF ou estado da loja sincronizado com o WooCommerce.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Aparece no bloco institucional.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'shop_postcode' => array(
                'label'  => __( 'CEP', EOP_TEXT_DOMAIN ),
                'help'   => __( 'CEP da loja sincronizado com o WooCommerce.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Aparece no bloco institucional.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'shop_country' => array(
                'label'  => __( 'Pais', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Pais padrao da loja sincronizado com o WooCommerce.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Aparece no bloco institucional.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'shop_phone' => array(
                'label'  => __( 'Telefone da loja', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Canal comercial principal que pode ser mostrado abaixo do endereco.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Quando preenchido, entra no bloco de identidade da loja.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'shop_email' => array(
                'label'  => __( 'E-mail da loja', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Contato de retorno exibido junto aos dados institucionais.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Quando preenchido, entra no bloco de identidade da loja.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'shop_vat_number' => array(
                'label'  => __( 'Documento / VAT', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Documento fiscal ou VAT number da empresa.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Pode ser exibido abaixo do endereco no documento.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'shop_chamber_of_commerce' => array(
                'label'  => __( 'Camara de comercio / registro', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Registro comercial adicional para cenarios B2B.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Pode ser exibido no bloco institucional.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'shop_extra_1' => array(
                'label'  => __( 'Campo extra 1', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Linha livre para informacao institucional complementar.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Aparece no bloco da loja quando preenchido.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'shop_extra_2' => array(
                'label'  => __( 'Campo extra 2', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Linha livre para informacao institucional complementar.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Aparece no bloco da loja quando preenchido.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'shop_extra_3' => array(
                'label'  => __( 'Campo extra 3', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Linha livre para informacao institucional complementar.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Aparece no bloco da loja quando preenchido.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'shop_footer' => array(
                'label'  => __( 'Rodape / informacoes adicionais', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Mensagem final do documento, observacoes legais ou instrucoes comerciais.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Vai ao final do PDF e do preview.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_enabled' => array(
                'label'  => __( 'Documento habilitado', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Liga ou desliga a geracao do documento para o tipo em edicao.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Esconde links, bloqueia download e impede geracao.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
                'values' => array( 'yes', 'no' ),
            ),
            'document_attach_email' => array(
                'label'  => __( 'Anexar em e-mails', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Inclui o PDF como anexo nos e-mails compatíveis do WooCommerce.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Adiciona o arquivo gerado na lista de anexos enviada pelo WooCommerce.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
                'values' => array( 'yes', 'no' ),
            ),
            'document_show_shipping' => array(
                'label'  => __( 'Exibir endereco de entrega', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Mostra o bloco com dados de entrega do cliente.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Afeta preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_show_billing' => array(
                'label'  => __( 'Exibir endereco de cobranca', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Mostra o bloco com endereco de cobranca do cliente.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Afeta preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_show_email' => array(
                'label'  => __( 'Exibir e-mail do cliente', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Mostra o e-mail do cliente no resumo do documento.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Afeta preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_show_phone' => array(
                'label'  => __( 'Exibir telefone do cliente', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Mostra o telefone do cliente no resumo do documento.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Afeta preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_show_notes' => array(
                'label'  => __( 'Exibir notas do cliente', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Mostra as observacoes do pedido na parte final do documento.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Afeta preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_mark_printed' => array(
                'label'  => __( 'Marcar como impresso', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Registra metadados de impressao sempre que o documento e aberto ou baixado.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Atualiza data, usuario e historico operacional do documento.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_prefix' => array(
                'label'  => __( 'Prefixo do numero', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Texto colocado antes do numero sequencial do documento.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Muda o numero exibido no preview, PDF e buscas no admin.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_suffix' => array(
                'label'  => __( 'Sufixo do numero', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Texto colocado depois do numero sequencial.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Complementa o numero exibido no documento.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_padding' => array(
                'label'  => __( 'Padding', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Quantidade de zeros a esquerda do numero sequencial.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Exemplo: padding 4 gera 0001.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_next_number' => array(
                'label'  => __( 'Proximo numero', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Numero que sera usado no proximo documento ainda sem sequencial persistido.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Avanca automaticamente quando um novo numero e gravado.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_reset_yearly' => array(
                'label'  => __( 'Reset anual', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Reinicia a sequencia em 1 quando o ano corrente mudar.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Mantem o mesmo numero salvo em documentos antigos e reseta apenas a sequencia futura.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'order_myaccount_download' => array(
                'label'  => __( 'Download no Minha Conta', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Mostra o link do PDF do pedido para o cliente logado em Minha Conta.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Clientes so conseguem baixar quando esta opcao esta ativa.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'proposal_public_pdf' => array(
                'label'  => __( 'Permitir PDF publico da proposta', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Libera o link publico de PDF da proposta usando token compartilhavel.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Quando desativado, o link publico deixa de funcionar.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_show_sku' => array(
                'label'  => __( 'Exibir SKU do produto', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Mostra a linha de SKU abaixo do nome do item.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Nao cria coluna extra; adiciona uma linha auxiliar abaixo do produto.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_show_quantity' => array(
                'label'  => __( 'Exibir coluna de quantidade', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Mostra a coluna com quantidade do item.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Afeta preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_show_unit_price' => array(
                'label'  => __( 'Exibir coluna de valor unitario', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Mostra o valor original por unidade.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Afeta preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_show_discount' => array(
                'label'  => __( 'Exibir coluna de desconto', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Mostra percentual e valor unitario descontado.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Afeta preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_discount_display_mode' => array(
                'label'  => __( 'Formato da coluna de desconto', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Escolhe se a coluna de desconto mostra porcentagem, valor monetario ou ambos.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Afeta preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
                'values' => array( 'percent', 'currency', 'both' ),
            ),
            'document_show_discounted_unit_price' => array(
                'label'  => __( 'Exibir coluna de valor unitario com desconto', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Mostra o valor unitario final apos desconto.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Afeta preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_table_body_line_height' => array(
                'label'  => __( 'Altura da linha do corpo da tabela', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Controla o espacamento vertical das linhas de itens na tabela do PDF.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Aumenta ou reduz a altura visual das linhas do PDF e do preview.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_show_line_total' => array(
                'label'  => __( 'Exibir coluna de total do item', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Mostra o total final por linha de item.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Afeta preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_product_label' => array(
                'label'  => __( 'Texto da coluna de produto', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Define o texto do cabecalho da coluna principal.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Aparece no preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_quantity_label' => array(
                'label'  => __( 'Texto da coluna de quantidade', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Personaliza o nome da coluna de quantidade.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Aparece no preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_unit_price_label' => array(
                'label'  => __( 'Texto da coluna de valor unitario', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Personaliza o nome da coluna de valor unitario.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Aparece no preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_discount_label' => array(
                'label'  => __( 'Texto da coluna de desconto', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Personaliza o nome da coluna de desconto.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Aparece no preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_discount_suffix' => array(
                'label'  => __( 'Texto complementar da coluna de desconto', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Texto opcional mostrado depois do valor monetario do desconto por unidade.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Aparece quando a coluna de desconto mostra o valor monetario.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_discounted_unit_price_label' => array(
                'label'  => __( 'Texto da coluna de valor unitario com desconto', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Personaliza o nome da coluna de valor final por unidade.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Aparece no preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_discounted_unit_price_suffix' => array(
                'label'  => __( 'Texto complementar do valor unitario com desconto', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Texto opcional mostrado depois do valor unitario ja com desconto, como / un..', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Aparece na coluna de valor unitario com desconto quando ela estiver ativa.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_line_total_label' => array(
                'label'  => __( 'Texto da coluna de total do item', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Personaliza o nome da coluna de total do item.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Aparece no preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_show_total_subtotal' => array(
                'label'  => __( 'Exibir subtotal', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Mostra a linha de subtotal antes de frete, desconto e total final.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Afeta preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_show_total_shipping' => array(
                'label'  => __( 'Exibir frete', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Mostra a linha de frete no bloco de totais.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Afeta preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_show_total_discount' => array(
                'label'  => __( 'Exibir desconto total', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Mostra a linha com o desconto total do pedido.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Afeta preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'document_show_total_total' => array(
                'label'  => __( 'Exibir total final', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Mostra a linha final com o total consolidado.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Afeta preview, PDF e proposta publica.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'edoc_enabled' => array(
                'label'  => __( 'Ativar documentos eletronicos', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Liga a montagem do XML tecnico experimental do documento.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Habilita preview e download do XML no modulo administrativo.', EOP_TEXT_DOMAIN ),
                'status' => 'experimental',
            ),
            'edoc_format' => array(
                'label'  => __( 'Formato / sintaxe', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Escolhe a estrutura-base do XML tecnico.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Troca o namespace, o nome raiz e alguns nos especificos da exportacao.', EOP_TEXT_DOMAIN ),
                'status' => 'experimental',
                'values' => array( 'ubl', 'cii', 'peppol' ),
            ),
            'edoc_embed_pdf' => array(
                'label'  => __( 'Embutir PDF', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Inclui referencia ao PDF no XML tecnico quando disponivel.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Adiciona um bloco de referencia ao PDF no XML exportado.', EOP_TEXT_DOMAIN ),
                'status' => 'experimental',
            ),
            'edoc_preview_xml' => array(
                'label'  => __( 'Habilitar preview XML', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Mostra o XML tecnico gerado para o pedido selecionado no preview.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Exibe painel de visualizacao do XML no admin.', EOP_TEXT_DOMAIN ),
                'status' => 'experimental',
            ),
            'edoc_logging' => array(
                'label'  => __( 'Habilitar logs', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Registra a geracao e falhas dos documentos eletronicos.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Escreve logs tecnicos do fluxo XML.', EOP_TEXT_DOMAIN ),
                'status' => 'experimental',
            ),
            'edoc_supplier_scheme' => array(
                'label'  => __( 'Identificador do fornecedor', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Esquema usado para identificar o fornecedor no XML, como CNPJ ou GLN.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Preenche atributos tecnicos do emissor no XML.', EOP_TEXT_DOMAIN ),
                'status' => 'experimental',
            ),
            'edoc_customer_scheme' => array(
                'label'  => __( 'Identificador do cliente', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Esquema usado para identificar o cliente no XML.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Preenche atributos tecnicos do destinatario no XML.', EOP_TEXT_DOMAIN ),
                'status' => 'experimental',
            ),
            'edoc_network_endpoint' => array(
                'label'  => __( 'Peppol Endpoint ID', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Endpoint tecnico usado em cenarios Peppol.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Entra no XML quando o formato escolhido usa rede Peppol.', EOP_TEXT_DOMAIN ),
                'status' => 'experimental',
            ),
            'edoc_network_eas' => array(
                'label'  => __( 'Peppol EAS', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Electronic Address Scheme usado pelo endpoint de rede.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Entra no XML quando o formato escolhido usa rede Peppol.', EOP_TEXT_DOMAIN ),
                'status' => 'experimental',
            ),
            'advanced_link_access' => array(
                'label'  => __( 'Politica de acesso ao link', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Define se o link privado exige nonce, sessao do dono do pedido ou token compartilhavel.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Muda os parametros gerados na URL e a validacao de acesso no download.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
                'values' => array( 'private_nonce', 'public_token', 'order_owner' ),
            ),
            'advanced_pretty_links' => array(
                'label'  => __( 'Pretty links', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Usa rota frontal amigavel em vez de admin-post.php para o download.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Troca apenas a forma da URL; o documento continua o mesmo.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'advanced_html_output' => array(
                'label'  => __( 'Forcar output HTML', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Desliga o preview HTML lateral e mantem apenas a geracao final do documento.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Bloqueia o preview lateral do admin.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'advanced_debug' => array(
                'label'  => __( 'Debug do modulo', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Liga logs tecnicos de geracao, fallback e acesso do modulo PDF.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Escreve eventos tecnicos no log PHP quando o modulo processa documentos.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'advanced_order_note_logs' => array(
                'label'  => __( 'Logar nas notas do pedido', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Replica eventos do modulo PDF nas notas internas do pedido.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Adiciona trilha operacional visivel no pedido.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'advanced_auto_cleanup' => array(
                'label'  => __( 'Limpeza automatica', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Remove cache antigo e arquivos temporarios do modulo automaticamente.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Reduz acúmulo de arquivos em uploads/eop-pdf e runtime do Dompdf.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
            'advanced_danger_zone' => array(
                'label'  => __( 'Danger zone / ferramentas destrutivas', EOP_TEXT_DOMAIN ),
                'help'   => __( 'Desbloqueia operacoes administrativas como limpeza manual de cache e reset de contadores.', EOP_TEXT_DOMAIN ),
                'effect' => __( 'Exibe ferramentas destrutivas adicionais no admin do modulo.', EOP_TEXT_DOMAIN ),
                'status' => 'active',
            ),
        );
    }

    public static function get_defaults() {
        $shared_store = self::get_shared_store_defaults();

        return array(
            'display_mode'              => 'new_tab',
            'paper_size'                => 'a4',
            'template_name'             => 'simple',
            'ink_saving_mode'           => 'no',
            'test_mode'                 => 'no',
            'font_subsetting'           => 'yes',
            'extended_currency_symbol'  => 'no',
            'shop_logo_url'             => '',
            'shop_logo_height'          => '3cm',
            'shop_name'                 => $shared_store['shop_name'],
            'shop_address_line_1'       => $shared_store['shop_address_line_1'],
            'shop_address_line_2'       => $shared_store['shop_address_line_2'],
            'shop_city'                 => $shared_store['shop_city'],
            'shop_state'                => $shared_store['shop_state'],
            'shop_postcode'             => $shared_store['shop_postcode'],
            'shop_country'              => $shared_store['shop_country'],
            'shop_phone'                => $shared_store['shop_phone'],
            'shop_email'                => $shared_store['shop_email'],
            'shop_vat_number'           => $shared_store['shop_vat_number'],
            'shop_chamber_of_commerce'  => '',
            'shop_extra_1'              => '',
            'shop_extra_2'              => '',
            'shop_extra_3'              => '',
            'shop_footer'               => __( 'Documento gerado pelo Aireset Expresso Order.', EOP_TEXT_DOMAIN ),
            'order_enabled'             => 'yes',
            'order_attach_email'        => 'no',
            'order_show_shipping'       => 'yes',
            'order_show_billing'        => 'no',
            'order_show_email'          => 'yes',
            'order_show_phone'          => 'yes',
            'order_show_notes'          => 'yes',
            'order_show_sku'            => 'yes',
            'order_show_item_index'     => 'yes',
            'order_show_quantity'       => 'yes',
            'order_show_unit_price'     => 'yes',
            'order_show_discount'       => 'yes',
            'order_show_discounted_unit_price' => 'yes',
            'order_show_line_total'     => 'yes',
            'order_item_index_label'    => __( 'Item', EOP_TEXT_DOMAIN ),
            'order_product_label'       => __( 'Produto', EOP_TEXT_DOMAIN ),
            'order_quantity_label'      => __( 'Quantidade', EOP_TEXT_DOMAIN ),
            'order_unit_price_label'    => __( 'Valor unitario', EOP_TEXT_DOMAIN ),
            'order_discount_label'      => __( 'Desconto aplicado', EOP_TEXT_DOMAIN ),
            'order_discount_suffix'     => '/ un.',
            'order_discount_display_mode' => 'both',
            'order_discounted_unit_price_label' => __( 'Valor unitario com desconto', EOP_TEXT_DOMAIN ),
            'order_discounted_unit_price_suffix' => '/ un.',
            'order_line_total_label'    => __( 'Total', EOP_TEXT_DOMAIN ),
            'order_quantity_position'   => '10',
            'order_unit_price_position' => '20',
            'order_discount_position'   => '30',
            'order_discounted_unit_price_position' => '40',
            'order_line_total_position' => '50',
            'order_header_background_color' => '#00034b',
            'order_header_text_color'   => '#ffffff',
            'order_body_text_color'     => '#172033',
            'order_muted_text_color'    => '#5b6474',
            'order_border_color'        => '#e3e8f1',
            'order_title_font_size'     => '34',
            'order_meta_font_size'      => '13',
            'order_table_header_font_size' => '12',
            'order_table_body_font_size' => '14',
            'order_table_body_line_height' => '1.35',
            'order_totals_font_size'    => '16',
            'order_note_font_size'      => '13',
            'order_show_total_subtotal' => 'yes',
            'order_show_total_shipping' => 'yes',
            'order_show_total_discount' => 'yes',
            'order_show_total_total'    => 'yes',
            'order_prefix'              => 'PED-',
            'order_suffix'              => '',
            'order_padding'             => '4',
            'order_next_number'         => '1',
            'order_last_reset_year'     => '0',
            'order_reset_yearly'        => 'no',
            'order_myaccount_download'  => 'no',
            'order_mark_printed'        => 'no',
            'proposal_enabled'          => 'yes',
            'proposal_attach_email'     => 'yes',
            'proposal_show_shipping'    => 'yes',
            'proposal_show_billing'     => 'no',
            'proposal_show_email'       => 'yes',
            'proposal_show_phone'       => 'yes',
            'proposal_show_notes'       => 'yes',
            'proposal_show_sku'         => 'yes',
            'proposal_show_item_index'  => 'yes',
            'proposal_show_quantity'    => 'yes',
            'proposal_show_unit_price'  => 'yes',
            'proposal_show_discount'    => 'yes',
            'proposal_show_discounted_unit_price' => 'yes',
            'proposal_show_line_total'  => 'yes',
            'proposal_item_index_label' => __( 'Item', EOP_TEXT_DOMAIN ),
            'proposal_product_label'    => __( 'Produto', EOP_TEXT_DOMAIN ),
            'proposal_quantity_label'   => __( 'Quantidade', EOP_TEXT_DOMAIN ),
            'proposal_unit_price_label' => __( 'Valor unitario', EOP_TEXT_DOMAIN ),
            'proposal_discount_label'   => __( 'Desconto aplicado', EOP_TEXT_DOMAIN ),
            'proposal_discount_suffix'  => '/ un.',
            'proposal_discount_display_mode' => 'both',
            'proposal_discounted_unit_price_label' => __( 'Valor unitario com desconto', EOP_TEXT_DOMAIN ),
            'proposal_discounted_unit_price_suffix' => '/ un.',
            'proposal_line_total_label' => __( 'Total', EOP_TEXT_DOMAIN ),
            'proposal_quantity_position' => '10',
            'proposal_unit_price_position' => '20',
            'proposal_discount_position' => '30',
            'proposal_discounted_unit_price_position' => '40',
            'proposal_line_total_position' => '50',
            'proposal_header_background_color' => '#00034b',
            'proposal_header_text_color' => '#ffffff',
            'proposal_body_text_color'   => '#172033',
            'proposal_muted_text_color'  => '#5b6474',
            'proposal_border_color'      => '#e3e8f1',
            'proposal_title_font_size'   => '34',
            'proposal_meta_font_size'    => '13',
            'proposal_table_header_font_size' => '12',
            'proposal_table_body_font_size' => '14',
            'proposal_table_body_line_height' => '1.35',
            'proposal_totals_font_size'  => '16',
            'proposal_note_font_size'    => '13',
            'proposal_show_total_subtotal' => 'yes',
            'proposal_show_total_shipping' => 'yes',
            'proposal_show_total_discount' => 'yes',
            'proposal_show_total_total'    => 'yes',
            'proposal_prefix'           => 'PROP-',
            'proposal_suffix'           => '',
            'proposal_padding'          => '4',
            'proposal_next_number'      => '1',
            'proposal_last_reset_year'  => '0',
            'proposal_reset_yearly'     => 'no',
            'proposal_public_pdf'       => 'yes',
            'proposal_mark_printed'     => 'no',
            'edoc_enabled'              => 'no',
            'edoc_format'               => 'ubl',
            'edoc_embed_pdf'            => 'yes',
            'edoc_preview_xml'          => 'yes',
            'edoc_logging'              => 'no',
            'edoc_supplier_scheme'      => '',
            'edoc_customer_scheme'      => '',
            'edoc_network_endpoint'     => '',
            'edoc_network_eas'          => '',
            'advanced_link_access'      => 'private_nonce',
            'advanced_pretty_links'     => 'no',
            'advanced_html_output'      => 'no',
            'advanced_debug'            => 'no',
            'advanced_order_note_logs'  => 'no',
            'advanced_auto_cleanup'     => 'yes',
            'advanced_danger_zone'      => 'no',
        );
    }

    public static function sanitize_settings( $input ) {
        $defaults = self::get_defaults();
        $saved    = get_option( self::OPTION_KEY, array() );
        $raw_input = is_array( $input ) ? $input : array();
        $input     = wp_parse_args( $raw_input, is_array( $saved ) ? $saved : array() );

        $sanitized = array(
            'display_mode'              => in_array( $input['display_mode'] ?? '', array( 'new_tab', 'download' ), true ) ? $input['display_mode'] : $defaults['display_mode'],
            'paper_size'                => in_array( $input['paper_size'] ?? '', array( 'a4', 'letter' ), true ) ? $input['paper_size'] : $defaults['paper_size'],
            'template_name'             => in_array( $input['template_name'] ?? '', array( 'simple', 'compact', 'minimal' ), true ) ? $input['template_name'] : $defaults['template_name'],
            'ink_saving_mode'           => self::sanitize_toggle( $input['ink_saving_mode'] ?? $defaults['ink_saving_mode'] ),
            'test_mode'                 => self::sanitize_toggle( $input['test_mode'] ?? $defaults['test_mode'] ),
            'font_subsetting'           => self::sanitize_toggle( $input['font_subsetting'] ?? $defaults['font_subsetting'] ),
            'extended_currency_symbol'  => self::sanitize_toggle( $input['extended_currency_symbol'] ?? $defaults['extended_currency_symbol'] ),
            'shop_logo_url'             => esc_url_raw( $input['shop_logo_url'] ?? '' ),
            'shop_logo_height'          => sanitize_text_field( $input['shop_logo_height'] ?? $defaults['shop_logo_height'] ),
            'shop_name'                 => sanitize_text_field( $input['shop_name'] ?? $defaults['shop_name'] ),
            'shop_address_line_1'       => sanitize_text_field( $input['shop_address_line_1'] ?? '' ),
            'shop_address_line_2'       => sanitize_text_field( $input['shop_address_line_2'] ?? '' ),
            'shop_city'                 => sanitize_text_field( $input['shop_city'] ?? '' ),
            'shop_state'                => sanitize_text_field( $input['shop_state'] ?? '' ),
            'shop_postcode'             => sanitize_text_field( $input['shop_postcode'] ?? '' ),
            'shop_country'              => sanitize_text_field( $input['shop_country'] ?? $defaults['shop_country'] ),
            'shop_phone'                => sanitize_text_field( $input['shop_phone'] ?? '' ),
            'shop_email'                => sanitize_email( $input['shop_email'] ?? '' ),
            'shop_vat_number'           => sanitize_text_field( $input['shop_vat_number'] ?? '' ),
            'shop_chamber_of_commerce'  => sanitize_text_field( $input['shop_chamber_of_commerce'] ?? '' ),
            'shop_extra_1'              => sanitize_text_field( $input['shop_extra_1'] ?? '' ),
            'shop_extra_2'              => sanitize_text_field( $input['shop_extra_2'] ?? '' ),
            'shop_extra_3'              => sanitize_text_field( $input['shop_extra_3'] ?? '' ),
            'shop_footer'               => sanitize_textarea_field( $input['shop_footer'] ?? '' ),
            'order_enabled'             => self::sanitize_toggle( $input['order_enabled'] ?? $defaults['order_enabled'] ),
            'order_attach_email'        => self::sanitize_toggle( $input['order_attach_email'] ?? $defaults['order_attach_email'] ),
            'order_show_shipping'       => self::sanitize_toggle( $input['order_show_shipping'] ?? $defaults['order_show_shipping'] ),
            'order_show_billing'        => self::sanitize_toggle( $input['order_show_billing'] ?? $defaults['order_show_billing'] ),
            'order_show_email'          => self::sanitize_toggle( $input['order_show_email'] ?? $defaults['order_show_email'] ),
            'order_show_phone'          => self::sanitize_toggle( $input['order_show_phone'] ?? $defaults['order_show_phone'] ),
            'order_show_notes'          => self::sanitize_toggle( $input['order_show_notes'] ?? $defaults['order_show_notes'] ),
            'order_show_sku'            => self::sanitize_toggle( $input['order_show_sku'] ?? $defaults['order_show_sku'] ),
            'order_show_item_index'     => self::sanitize_toggle( $input['order_show_item_index'] ?? $defaults['order_show_item_index'] ),
            'order_show_quantity'       => self::sanitize_toggle( $input['order_show_quantity'] ?? $defaults['order_show_quantity'] ),
            'order_show_unit_price'     => self::sanitize_toggle( $input['order_show_unit_price'] ?? $defaults['order_show_unit_price'] ),
            'order_show_discount'       => self::sanitize_toggle( $input['order_show_discount'] ?? $defaults['order_show_discount'] ),
            'order_show_discounted_unit_price' => self::sanitize_toggle( $input['order_show_discounted_unit_price'] ?? $defaults['order_show_discounted_unit_price'] ),
            'order_show_line_total'     => self::sanitize_toggle( $input['order_show_line_total'] ?? $defaults['order_show_line_total'] ),
            'order_item_index_label'    => self::sanitize_label( $input['order_item_index_label'] ?? $defaults['order_item_index_label'], $defaults['order_item_index_label'] ),
            'order_product_label'       => self::sanitize_label( $input['order_product_label'] ?? $defaults['order_product_label'], $defaults['order_product_label'] ),
            'order_quantity_label'      => self::sanitize_label( $input['order_quantity_label'] ?? $defaults['order_quantity_label'], $defaults['order_quantity_label'] ),
            'order_unit_price_label'    => self::sanitize_label( $input['order_unit_price_label'] ?? $defaults['order_unit_price_label'], $defaults['order_unit_price_label'] ),
            'order_discount_label'      => self::sanitize_label( $input['order_discount_label'] ?? $defaults['order_discount_label'], $defaults['order_discount_label'] ),
            'order_discount_suffix'     => sanitize_text_field( $input['order_discount_suffix'] ?? $defaults['order_discount_suffix'] ),
            'order_discount_display_mode' => in_array( $input['order_discount_display_mode'] ?? '', array( 'percent', 'currency', 'both' ), true ) ? $input['order_discount_display_mode'] : 'both',
            'order_discounted_unit_price_label' => self::sanitize_label( $input['order_discounted_unit_price_label'] ?? $defaults['order_discounted_unit_price_label'], $defaults['order_discounted_unit_price_label'] ),
            'order_discounted_unit_price_suffix' => sanitize_text_field( $input['order_discounted_unit_price_suffix'] ?? $defaults['order_discounted_unit_price_suffix'] ),
            'order_line_total_label'    => self::sanitize_label( $input['order_line_total_label'] ?? $defaults['order_line_total_label'], $defaults['order_line_total_label'] ),
            'order_quantity_position'   => self::sanitize_column_position_value( $input['order_quantity_position'] ?? $defaults['order_quantity_position'], $defaults['order_quantity_position'] ),
            'order_unit_price_position' => self::sanitize_column_position_value( $input['order_unit_price_position'] ?? $defaults['order_unit_price_position'], $defaults['order_unit_price_position'] ),
            'order_discount_position'   => self::sanitize_column_position_value( $input['order_discount_position'] ?? $defaults['order_discount_position'], $defaults['order_discount_position'] ),
            'order_discounted_unit_price_position' => self::sanitize_column_position_value( $input['order_discounted_unit_price_position'] ?? $defaults['order_discounted_unit_price_position'], $defaults['order_discounted_unit_price_position'] ),
            'order_line_total_position' => self::sanitize_column_position_value( $input['order_line_total_position'] ?? $defaults['order_line_total_position'], $defaults['order_line_total_position'] ),
            'order_header_background_color' => self::sanitize_hex_color_value( $input['order_header_background_color'] ?? $defaults['order_header_background_color'], $defaults['order_header_background_color'] ),
            'order_header_text_color'   => self::sanitize_hex_color_value( $input['order_header_text_color'] ?? $defaults['order_header_text_color'], $defaults['order_header_text_color'] ),
            'order_body_text_color'     => self::sanitize_hex_color_value( $input['order_body_text_color'] ?? $defaults['order_body_text_color'], $defaults['order_body_text_color'] ),
            'order_muted_text_color'    => self::sanitize_hex_color_value( $input['order_muted_text_color'] ?? $defaults['order_muted_text_color'], $defaults['order_muted_text_color'] ),
            'order_border_color'        => self::sanitize_hex_color_value( $input['order_border_color'] ?? $defaults['order_border_color'], $defaults['order_border_color'] ),
            'order_title_font_size'     => self::sanitize_font_size_value( $input['order_title_font_size'] ?? $defaults['order_title_font_size'], $defaults['order_title_font_size'] ),
            'order_meta_font_size'      => self::sanitize_font_size_value( $input['order_meta_font_size'] ?? $defaults['order_meta_font_size'], $defaults['order_meta_font_size'] ),
            'order_table_header_font_size' => self::sanitize_font_size_value( $input['order_table_header_font_size'] ?? $defaults['order_table_header_font_size'], $defaults['order_table_header_font_size'] ),
            'order_table_body_font_size' => self::sanitize_font_size_value( $input['order_table_body_font_size'] ?? $defaults['order_table_body_font_size'], $defaults['order_table_body_font_size'] ),
            'order_table_body_line_height' => self::sanitize_line_height_value( $input['order_table_body_line_height'] ?? $defaults['order_table_body_line_height'], $defaults['order_table_body_line_height'] ),
            'order_totals_font_size'    => self::sanitize_font_size_value( $input['order_totals_font_size'] ?? $defaults['order_totals_font_size'], $defaults['order_totals_font_size'] ),
            'order_note_font_size'      => self::sanitize_font_size_value( $input['order_note_font_size'] ?? $defaults['order_note_font_size'], $defaults['order_note_font_size'] ),
            'order_show_total_subtotal' => self::sanitize_toggle( $input['order_show_total_subtotal'] ?? $defaults['order_show_total_subtotal'] ),
            'order_show_total_shipping' => self::sanitize_toggle( $input['order_show_total_shipping'] ?? $defaults['order_show_total_shipping'] ),
            'order_show_total_discount' => self::sanitize_toggle( $input['order_show_total_discount'] ?? $defaults['order_show_total_discount'] ),
            'order_show_total_total'    => self::sanitize_toggle( $input['order_show_total_total'] ?? $defaults['order_show_total_total'] ),
            'order_prefix'              => sanitize_text_field( $input['order_prefix'] ?? '' ),
            'order_suffix'              => sanitize_text_field( $input['order_suffix'] ?? '' ),
            'order_padding'             => (string) max( 0, min( 12, absint( $input['order_padding'] ?? $defaults['order_padding'] ) ) ),
            'order_next_number'         => (string) max( 1, absint( $input['order_next_number'] ?? $defaults['order_next_number'] ) ),
            'order_last_reset_year'     => (string) max( 0, absint( $input['order_last_reset_year'] ?? $defaults['order_last_reset_year'] ) ),
            'order_reset_yearly'        => self::sanitize_toggle( $input['order_reset_yearly'] ?? $defaults['order_reset_yearly'] ),
            'order_myaccount_download'  => self::sanitize_toggle( $input['order_myaccount_download'] ?? $defaults['order_myaccount_download'] ),
            'order_mark_printed'        => self::sanitize_toggle( $input['order_mark_printed'] ?? $defaults['order_mark_printed'] ),
            'proposal_enabled'          => self::sanitize_toggle( $input['proposal_enabled'] ?? $defaults['proposal_enabled'] ),
            'proposal_attach_email'     => self::sanitize_toggle( $input['proposal_attach_email'] ?? $defaults['proposal_attach_email'] ),
            'proposal_show_shipping'    => self::sanitize_toggle( $input['proposal_show_shipping'] ?? $defaults['proposal_show_shipping'] ),
            'proposal_show_billing'     => self::sanitize_toggle( $input['proposal_show_billing'] ?? $defaults['proposal_show_billing'] ),
            'proposal_show_email'       => self::sanitize_toggle( $input['proposal_show_email'] ?? $defaults['proposal_show_email'] ),
            'proposal_show_phone'       => self::sanitize_toggle( $input['proposal_show_phone'] ?? $defaults['proposal_show_phone'] ),
            'proposal_show_notes'       => self::sanitize_toggle( $input['proposal_show_notes'] ?? $defaults['proposal_show_notes'] ),
            'proposal_show_sku'         => self::sanitize_toggle( $input['proposal_show_sku'] ?? $defaults['proposal_show_sku'] ),
            'proposal_show_item_index'  => self::sanitize_toggle( $input['proposal_show_item_index'] ?? $defaults['proposal_show_item_index'] ),
            'proposal_show_quantity'    => self::sanitize_toggle( $input['proposal_show_quantity'] ?? $defaults['proposal_show_quantity'] ),
            'proposal_show_unit_price'  => self::sanitize_toggle( $input['proposal_show_unit_price'] ?? $defaults['proposal_show_unit_price'] ),
            'proposal_show_discount'    => self::sanitize_toggle( $input['proposal_show_discount'] ?? $defaults['proposal_show_discount'] ),
            'proposal_show_discounted_unit_price' => self::sanitize_toggle( $input['proposal_show_discounted_unit_price'] ?? $defaults['proposal_show_discounted_unit_price'] ),
            'proposal_show_line_total'  => self::sanitize_toggle( $input['proposal_show_line_total'] ?? $defaults['proposal_show_line_total'] ),
            'proposal_item_index_label' => self::sanitize_label( $input['proposal_item_index_label'] ?? $defaults['proposal_item_index_label'], $defaults['proposal_item_index_label'] ),
            'proposal_product_label'    => self::sanitize_label( $input['proposal_product_label'] ?? $defaults['proposal_product_label'], $defaults['proposal_product_label'] ),
            'proposal_quantity_label'   => self::sanitize_label( $input['proposal_quantity_label'] ?? $defaults['proposal_quantity_label'], $defaults['proposal_quantity_label'] ),
            'proposal_unit_price_label' => self::sanitize_label( $input['proposal_unit_price_label'] ?? $defaults['proposal_unit_price_label'], $defaults['proposal_unit_price_label'] ),
            'proposal_discount_label'   => self::sanitize_label( $input['proposal_discount_label'] ?? $defaults['proposal_discount_label'], $defaults['proposal_discount_label'] ),
            'proposal_discount_suffix'  => sanitize_text_field( $input['proposal_discount_suffix'] ?? $defaults['proposal_discount_suffix'] ),
            'proposal_discount_display_mode' => in_array( $input['proposal_discount_display_mode'] ?? '', array( 'percent', 'currency', 'both' ), true ) ? $input['proposal_discount_display_mode'] : 'both',
            'proposal_discounted_unit_price_label' => self::sanitize_label( $input['proposal_discounted_unit_price_label'] ?? $defaults['proposal_discounted_unit_price_label'], $defaults['proposal_discounted_unit_price_label'] ),
            'proposal_discounted_unit_price_suffix' => sanitize_text_field( $input['proposal_discounted_unit_price_suffix'] ?? $defaults['proposal_discounted_unit_price_suffix'] ),
            'proposal_line_total_label' => self::sanitize_label( $input['proposal_line_total_label'] ?? $defaults['proposal_line_total_label'], $defaults['proposal_line_total_label'] ),
            'proposal_quantity_position' => self::sanitize_column_position_value( $input['proposal_quantity_position'] ?? $defaults['proposal_quantity_position'], $defaults['proposal_quantity_position'] ),
            'proposal_unit_price_position' => self::sanitize_column_position_value( $input['proposal_unit_price_position'] ?? $defaults['proposal_unit_price_position'], $defaults['proposal_unit_price_position'] ),
            'proposal_discount_position' => self::sanitize_column_position_value( $input['proposal_discount_position'] ?? $defaults['proposal_discount_position'], $defaults['proposal_discount_position'] ),
            'proposal_discounted_unit_price_position' => self::sanitize_column_position_value( $input['proposal_discounted_unit_price_position'] ?? $defaults['proposal_discounted_unit_price_position'], $defaults['proposal_discounted_unit_price_position'] ),
            'proposal_line_total_position' => self::sanitize_column_position_value( $input['proposal_line_total_position'] ?? $defaults['proposal_line_total_position'], $defaults['proposal_line_total_position'] ),
            'proposal_header_background_color' => self::sanitize_hex_color_value( $input['proposal_header_background_color'] ?? $defaults['proposal_header_background_color'], $defaults['proposal_header_background_color'] ),
            'proposal_header_text_color' => self::sanitize_hex_color_value( $input['proposal_header_text_color'] ?? $defaults['proposal_header_text_color'], $defaults['proposal_header_text_color'] ),
            'proposal_body_text_color'   => self::sanitize_hex_color_value( $input['proposal_body_text_color'] ?? $defaults['proposal_body_text_color'], $defaults['proposal_body_text_color'] ),
            'proposal_muted_text_color'  => self::sanitize_hex_color_value( $input['proposal_muted_text_color'] ?? $defaults['proposal_muted_text_color'], $defaults['proposal_muted_text_color'] ),
            'proposal_border_color'      => self::sanitize_hex_color_value( $input['proposal_border_color'] ?? $defaults['proposal_border_color'], $defaults['proposal_border_color'] ),
            'proposal_title_font_size'   => self::sanitize_font_size_value( $input['proposal_title_font_size'] ?? $defaults['proposal_title_font_size'], $defaults['proposal_title_font_size'] ),
            'proposal_meta_font_size'    => self::sanitize_font_size_value( $input['proposal_meta_font_size'] ?? $defaults['proposal_meta_font_size'], $defaults['proposal_meta_font_size'] ),
            'proposal_table_header_font_size' => self::sanitize_font_size_value( $input['proposal_table_header_font_size'] ?? $defaults['proposal_table_header_font_size'], $defaults['proposal_table_header_font_size'] ),
            'proposal_table_body_font_size' => self::sanitize_font_size_value( $input['proposal_table_body_font_size'] ?? $defaults['proposal_table_body_font_size'], $defaults['proposal_table_body_font_size'] ),
            'proposal_table_body_line_height' => self::sanitize_line_height_value( $input['proposal_table_body_line_height'] ?? $defaults['proposal_table_body_line_height'], $defaults['proposal_table_body_line_height'] ),
            'proposal_totals_font_size'  => self::sanitize_font_size_value( $input['proposal_totals_font_size'] ?? $defaults['proposal_totals_font_size'], $defaults['proposal_totals_font_size'] ),
            'proposal_note_font_size'    => self::sanitize_font_size_value( $input['proposal_note_font_size'] ?? $defaults['proposal_note_font_size'], $defaults['proposal_note_font_size'] ),
            'proposal_show_total_subtotal' => self::sanitize_toggle( $input['proposal_show_total_subtotal'] ?? $defaults['proposal_show_total_subtotal'] ),
            'proposal_show_total_shipping' => self::sanitize_toggle( $input['proposal_show_total_shipping'] ?? $defaults['proposal_show_total_shipping'] ),
            'proposal_show_total_discount' => self::sanitize_toggle( $input['proposal_show_total_discount'] ?? $defaults['proposal_show_total_discount'] ),
            'proposal_show_total_total'    => self::sanitize_toggle( $input['proposal_show_total_total'] ?? $defaults['proposal_show_total_total'] ),
            'proposal_prefix'           => sanitize_text_field( $input['proposal_prefix'] ?? '' ),
            'proposal_suffix'           => sanitize_text_field( $input['proposal_suffix'] ?? '' ),
            'proposal_padding'          => (string) max( 0, min( 12, absint( $input['proposal_padding'] ?? $defaults['proposal_padding'] ) ) ),
            'proposal_next_number'      => (string) max( 1, absint( $input['proposal_next_number'] ?? $defaults['proposal_next_number'] ) ),
            'proposal_last_reset_year'  => (string) max( 0, absint( $input['proposal_last_reset_year'] ?? $defaults['proposal_last_reset_year'] ) ),
            'proposal_reset_yearly'     => self::sanitize_toggle( $input['proposal_reset_yearly'] ?? $defaults['proposal_reset_yearly'] ),
            'proposal_public_pdf'       => self::sanitize_toggle( $input['proposal_public_pdf'] ?? $defaults['proposal_public_pdf'] ),
            'proposal_mark_printed'     => self::sanitize_toggle( $input['proposal_mark_printed'] ?? $defaults['proposal_mark_printed'] ),
            'edoc_enabled'              => self::sanitize_toggle( $input['edoc_enabled'] ?? $defaults['edoc_enabled'] ),
            'edoc_format'               => in_array( $input['edoc_format'] ?? '', array( 'ubl', 'cii', 'peppol' ), true ) ? $input['edoc_format'] : $defaults['edoc_format'],
            'edoc_embed_pdf'            => self::sanitize_toggle( $input['edoc_embed_pdf'] ?? $defaults['edoc_embed_pdf'] ),
            'edoc_preview_xml'          => self::sanitize_toggle( $input['edoc_preview_xml'] ?? $defaults['edoc_preview_xml'] ),
            'edoc_logging'              => self::sanitize_toggle( $input['edoc_logging'] ?? $defaults['edoc_logging'] ),
            'edoc_supplier_scheme'      => sanitize_text_field( $input['edoc_supplier_scheme'] ?? '' ),
            'edoc_customer_scheme'      => sanitize_text_field( $input['edoc_customer_scheme'] ?? '' ),
            'edoc_network_endpoint'     => sanitize_text_field( $input['edoc_network_endpoint'] ?? '' ),
            'edoc_network_eas'          => sanitize_text_field( $input['edoc_network_eas'] ?? '' ),
            'advanced_link_access'      => in_array( $input['advanced_link_access'] ?? '', array( 'private_nonce', 'public_token', 'order_owner' ), true ) ? $input['advanced_link_access'] : $defaults['advanced_link_access'],
            'advanced_pretty_links'     => self::sanitize_toggle( $input['advanced_pretty_links'] ?? $defaults['advanced_pretty_links'] ),
            'advanced_html_output'      => self::sanitize_toggle( $input['advanced_html_output'] ?? $defaults['advanced_html_output'] ),
            'advanced_debug'            => self::sanitize_toggle( $input['advanced_debug'] ?? $defaults['advanced_debug'] ),
            'advanced_order_note_logs'  => self::sanitize_toggle( $input['advanced_order_note_logs'] ?? $defaults['advanced_order_note_logs'] ),
            'advanced_auto_cleanup'     => self::sanitize_toggle( $input['advanced_auto_cleanup'] ?? $defaults['advanced_auto_cleanup'] ),
            'advanced_danger_zone'      => self::sanitize_toggle( $input['advanced_danger_zone'] ?? $defaults['advanced_danger_zone'] ),
        );

        if ( self::has_shared_store_input( $raw_input ) ) {
            self::sync_shared_store_settings( $sanitized );
        }

        return self::apply_shared_store_defaults( $sanitized );
    }

    private static function get_woocommerce_store_defaults() {
        $default_country = (string) get_option( 'woocommerce_default_country', 'BR' );
        $country_parts   = explode( ':', $default_country );
        $country         = strtoupper( sanitize_text_field( $country_parts[0] ?? 'BR' ) );
        $state           = strtoupper( sanitize_text_field( $country_parts[1] ?? '' ) );

        if ( strlen( $country ) !== 2 ) {
            $country = 'BR';
        }

        $state = preg_replace( '/[^A-Z]/', '', $state );

        if ( 'BR' === $country ) {
            $state = substr( $state, 0, 2 );
        }

        return array(
            'shop_address_line_1' => sanitize_text_field( (string) get_option( 'woocommerce_store_address', '' ) ),
            'shop_address_line_2' => sanitize_text_field( (string) get_option( 'woocommerce_store_address_2', '' ) ),
            'shop_city'           => sanitize_text_field( (string) get_option( 'woocommerce_store_city', '' ) ),
            'shop_state'          => $state,
            'shop_postcode'       => sanitize_text_field( (string) get_option( 'woocommerce_store_postcode', '' ) ),
            'shop_country'        => $country,
        );
    }

    private static function get_shared_store_defaults() {
        $defaults = self::get_woocommerce_store_defaults();

        $defaults['shop_name']       = sanitize_text_field( (string) get_option( 'blogname', get_bloginfo( 'name' ) ) );
        $defaults['shop_phone']      = sanitize_text_field( (string) get_option( 'telefone', '' ) );
        $defaults['shop_email']      = sanitize_email( (string) get_option( 'email_contato', get_option( 'admin_email', get_bloginfo( 'admin_email' ) ) ) );
        $defaults['shop_vat_number'] = sanitize_text_field( (string) get_option( 'cnpj', '' ) );

        return $defaults;
    }

    private static function apply_shared_store_defaults( $settings ) {
        $settings = is_array( $settings ) ? $settings : array();

        foreach ( self::get_shared_store_defaults() as $key => $value ) {
            $settings[ $key ] = $value;
        }

        return $settings;
    }

    private static function has_shared_store_input( $input ) {
        if ( ! is_array( $input ) ) {
            return false;
        }

        foreach ( array( 'shop_name', 'shop_address_line_1', 'shop_address_line_2', 'shop_city', 'shop_state', 'shop_postcode', 'shop_country', 'shop_phone', 'shop_email', 'shop_vat_number' ) as $shared_key ) {
            if ( array_key_exists( $shared_key, $input ) ) {
                return true;
            }
        }

        return false;
    }

    private static function sync_shared_store_settings( $settings ) {
        $settings = is_array( $settings ) ? $settings : array();

        update_option( 'blogname', sanitize_text_field( (string) ( $settings['shop_name'] ?? get_option( 'blogname', '' ) ) ) );
        update_option( 'telefone', sanitize_text_field( (string) ( $settings['shop_phone'] ?? '' ) ) );
        update_option( 'email_contato', sanitize_email( (string) ( $settings['shop_email'] ?? '' ) ) );
        update_option( 'cnpj', sanitize_text_field( (string) ( $settings['shop_vat_number'] ?? '' ) ) );

        self::sync_store_address_settings( $settings );
    }

    private static function sync_store_address_settings( $settings ) {
        $address_1 = sanitize_text_field( (string) ( $settings['shop_address_line_1'] ?? '' ) );
        $address_2 = sanitize_text_field( (string) ( $settings['shop_address_line_2'] ?? '' ) );
        $city      = sanitize_text_field( (string) ( $settings['shop_city'] ?? '' ) );
        $postcode  = sanitize_text_field( (string) ( $settings['shop_postcode'] ?? '' ) );
        $country   = strtoupper( sanitize_text_field( (string) ( $settings['shop_country'] ?? 'BR' ) ) );
        $state     = strtoupper( sanitize_text_field( (string) ( $settings['shop_state'] ?? '' ) ) );

        $country = preg_replace( '/[^A-Z]/', '', $country );
        if ( strlen( $country ) !== 2 ) {
            $country = 'BR';
        }

        $state = preg_replace( '/[^A-Z]/', '', $state );
        if ( 'BR' === $country ) {
            $state = substr( $state, 0, 2 );
        }

        update_option( 'endereco', $address_1 );
        update_option( 'endereco_linha_2', $address_2 );
        update_option( 'cidade', $city );
        update_option( 'cep', $postcode );
        update_option( 'pais', $country );
        update_option( 'estado', $state );

        update_option( 'woocommerce_store_address', $address_1 );
        update_option( 'woocommerce_store_address_2', $address_2 );
        update_option( 'woocommerce_store_city', $city );
        update_option( 'woocommerce_store_postcode', $postcode );
        update_option( 'woocommerce_default_country', '' === $state ? $country : $country . ':' . $state );
    }

    private static function apply_legacy_pdf_fallbacks( $settings ) {
        $settings = is_array( $settings ) ? $settings : array();
        $legacy   = self::get_legacy_pdf_settings();
        $defaults = self::get_defaults();
        $woo      = self::get_woocommerce_store_defaults();

        $legacy_logo = esc_url_raw( $legacy['brand_logo_url'] ?? '' );
        if ( '' === trim( (string) ( $settings['shop_logo_url'] ?? '' ) ) && '' !== $legacy_logo ) {
            $settings['shop_logo_url'] = $legacy_logo;
        }

        $legacy_name = sanitize_text_field( (string) ( $legacy['pdf_company_name'] ?? '' ) );
        if ( '' !== $legacy_name ) {
            $current_name = sanitize_text_field( (string) ( $settings['shop_name'] ?? '' ) );
            $default_name = sanitize_text_field( (string) $defaults['shop_name'] );

            if ( '' === $current_name || $current_name === $default_name ) {
                $settings['shop_name'] = $legacy_name;
            }
        }

        $legacy_document = sanitize_text_field( (string) ( $legacy['pdf_company_document'] ?? '' ) );
        if ( '' === trim( (string) ( $settings['shop_vat_number'] ?? '' ) ) && '' !== $legacy_document ) {
            $settings['shop_vat_number'] = $legacy_document;
        }

        $legacy_address = sanitize_textarea_field( (string) ( $legacy['pdf_company_address'] ?? '' ) );
        if ( '' !== $legacy_address && self::address_is_using_woocommerce_defaults( $settings, $woo ) ) {
            $address_lines = preg_split( '/\r\n|\r|\n/', $legacy_address );
            $address_lines = array_values( array_filter( array_map( 'trim', (array) $address_lines ) ) );

            if ( empty( $address_lines ) ) {
                $address_lines = array( trim( preg_replace( '/\s+/', ' ', $legacy_address ) ) );
            }

            $settings['shop_address_line_1'] = $address_lines[0] ?? '';
            $settings['shop_address_line_2'] = isset( $address_lines[1] ) ? implode( ', ', array_slice( $address_lines, 1 ) ) : '';
            $settings['shop_city']           = '';
            $settings['shop_state']          = '';
            $settings['shop_postcode']       = '';
            $settings['shop_country']        = '';
        }

        $legacy_footer = sanitize_textarea_field( (string) ( $legacy['pdf_footer_note'] ?? '' ) );
        if ( '' !== $legacy_footer ) {
            $current_footer = sanitize_textarea_field( (string) ( $settings['shop_footer'] ?? '' ) );
            $default_footer = sanitize_textarea_field( (string) $defaults['shop_footer'] );

            if ( '' === $current_footer || $current_footer === $default_footer ) {
                $settings['shop_footer'] = $legacy_footer;
            }
        }

        return $settings;
    }

    private static function get_legacy_pdf_settings() {
        $legacy = get_option( 'eop_settings', array() );

        if ( class_exists( 'EOP_Settings' ) ) {
            return wp_parse_args( is_array( $legacy ) ? $legacy : array(), EOP_Settings::get_defaults() );
        }

        return is_array( $legacy ) ? $legacy : array();
    }

    private static function address_is_using_woocommerce_defaults( $settings, $woo_defaults ) {
        $keys = array( 'shop_address_line_1', 'shop_address_line_2', 'shop_city', 'shop_state', 'shop_postcode', 'shop_country' );

        foreach ( $keys as $key ) {
            $current = trim( (string) ( $settings[ $key ] ?? '' ) );
            $default = trim( (string) ( $woo_defaults[ $key ] ?? '' ) );

            if ( $current !== $default ) {
                return false;
            }
        }

        return true;
    }

    private static function apply_document_label_fallbacks( $settings ) {
        $settings = is_array( $settings ) ? $settings : array();
        $label_keys = array(
            'item_index_label',
            'product_label',
            'quantity_label',
            'unit_price_label',
            'discount_label',
            'discounted_unit_price_label',
            'line_total_label',
        );

        foreach ( $label_keys as $label_key ) {
            $proposal_key      = 'proposal_' . $label_key;
            $order_key         = 'order_' . $label_key;
            $proposal_value    = sanitize_text_field( (string) ( $settings[ $proposal_key ] ?? '' ) );
            $order_value       = sanitize_text_field( (string) ( $settings[ $order_key ] ?? '' ) );
            $proposal_legacy   = sanitize_text_field( (string) self::get_legacy_setting_default( $proposal_key ) );
            $order_legacy      = sanitize_text_field( (string) self::get_legacy_setting_default( $order_key ) );

            if ( '' === $proposal_value && '' !== $order_value ) {
                $settings[ $proposal_key ] = $order_value;
                continue;
            }

            if ( '' !== $proposal_legacy && $proposal_value === $proposal_legacy && '' !== $order_value && $order_value !== $order_legacy ) {
                $settings[ $proposal_key ] = $order_value;
            }
        }

        return $settings;
    }

    private static function sanitize_toggle( $value ) {
        return 'yes' === sanitize_key( (string) $value ) ? 'yes' : 'no';
    }

    private static function get_legacy_setting_default( $setting_key ) {
        $setting_key = sanitize_key( (string) $setting_key );
        $document_type = 0 === strpos( $setting_key, 'proposal_' ) ? 'proposal' : 'order';
        $label_key = str_replace( array( 'proposal_', 'order_' ), '', $setting_key );
        $legacy_defaults = self::get_legacy_document_item_label_defaults( $document_type );
        $legacy_map = array(
            'product_label' => 'product',
            'quantity_label' => 'quantity',
            'unit_price_label' => 'unit_price',
            'discount_label' => 'discount',
            'discounted_unit_price_label' => 'discounted_unit_price',
            'line_total_label' => 'line_total',
        );

        if ( ! isset( $legacy_map[ $label_key ] ) ) {
            return '';
        }

        return (string) ( $legacy_defaults[ $legacy_map[ $label_key ] ] ?? '' );
    }

    private static function sanitize_label( $value, $default ) {
        $label = sanitize_text_field( (string) $value );

        if ( '' === $label ) {
            return sanitize_text_field( (string) $default );
        }

        return $label;
    }

    private static function sanitize_hex_color_value( $value, $default ) {
        $color    = sanitize_hex_color( (string) $value );
        $fallback = sanitize_hex_color( (string) $default );

        return $color ? $color : ( $fallback ? $fallback : '#111111' );
    }

    private static function sanitize_font_size_value( $value, $default ) {
        $size     = absint( $value );
        $fallback = max( 8, min( 72, absint( $default ) ) );

        if ( $size < 8 || $size > 72 ) {
            $size = $fallback;
        }

        return (string) $size;
    }

    private static function sanitize_line_height_value( $value, $default ) {
        $height   = floatval( str_replace( ',', '.', (string) $value ) );
        $fallback = max( 1.0, min( 3.0, floatval( str_replace( ',', '.', (string) $default ) ) ) );

        if ( $height < 0.8 || $height > 3.0 ) {
            $height = $fallback;
        }

        $formatted = rtrim( rtrim( number_format( $height, 2, '.', '' ), '0' ), '.' );

        return '' === $formatted ? (string) $fallback : $formatted;
    }

    private static function sanitize_column_position_value( $value, $default ) {
        $position = absint( $value );
        $fallback = max( 1, min( 99, absint( $default ) ) );

        if ( $position < 1 || $position > 99 ) {
            $position = $fallback;
        }

        return (string) $position;
    }
}
