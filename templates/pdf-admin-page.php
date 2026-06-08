<?php
defined( 'ABSPATH' ) || exit;

$plugin_settings = EOP_Settings::get_all();
$pdf_settings    = EOP_PDF_Settings::get_all();
$embedded        = ! empty( $embedded );
$default_tab     = isset( $default_tab ) ? (string) $default_tab : 'display';
$font_css        = method_exists( 'EOP_Settings', 'get_font_css_family' ) ? EOP_Settings::get_font_css_family( $plugin_settings['font_family'] ) : "'Segoe UI', sans-serif";
$tabs            = EOP_PDF_Admin_Page::get_accessible_tabs();

if ( isset( $tab ) ) {
    $tab = EOP_PDF_Admin_Page::normalize_tab( $tab, $default_tab );
} else {
    $tab = EOP_PDF_Admin_Page::normalize_tab( EOP_PDF_Admin_Page::get_current_tab(), $default_tab );
}

$document_tab_contexts = array(
    'order-settings' => array(
        'document' => 'order',
        'section'  => 'settings',
        'pair'     => 'proposal-settings',
    ),
    'order-columns' => array(
        'document' => 'order',
        'section'  => 'columns',
        'pair'     => 'proposal-columns',
    ),
    'order-texts' => array(
        'document' => 'order',
        'section'  => 'texts',
        'pair'     => 'proposal-texts',
    ),
    'order-style' => array(
        'document' => 'order',
        'section'  => 'style',
        'pair'     => 'proposal-style',
    ),
    'proposal-settings' => array(
        'document' => 'proposal',
        'section'  => 'settings',
        'pair'     => 'order-settings',
    ),
    'proposal-columns' => array(
        'document' => 'proposal',
        'section'  => 'columns',
        'pair'     => 'order-columns',
    ),
    'proposal-texts' => array(
        'document' => 'proposal',
        'section'  => 'texts',
        'pair'     => 'order-texts',
    ),
    'proposal-style' => array(
        'document' => 'proposal',
        'section'  => 'style',
        'pair'     => 'order-style',
    ),
);

if ( ! isset( $tabs[ $tab ] ) ) {
    $tab = key( $tabs );
}

$document        = isset( $_GET['document'] ) && 'proposal' === sanitize_key( wp_unslash( $_GET['document'] ) ) ? 'proposal' : 'order';
$document_tab_context = isset( $document_tab_contexts[ $tab ] ) ? $document_tab_contexts[ $tab ] : null;

if ( is_array( $document_tab_context ) ) {
    $document = $document_tab_context['document'];
}

$document_section = is_array( $document_tab_context ) ? $document_tab_context['section'] : '';
$document_pair_tab = is_array( $document_tab_context ) ? $document_tab_context['pair'] : '';
$is_document_tab = is_array( $document_tab_context );
$recent_orders   = EOP_Document_Manager::get_recent_orders( 20 );
$preview_order   = EOP_Document_Manager::get_preview_order( absint( $_GET['preview_order'] ?? 0 ) );
$preview_document = $preview_order instanceof WC_Order ? EOP_Document_Manager::get_document_type_for_order( $preview_order ) : $document;
$editing_label   = 'proposal' === $document ? __( 'Proposta', EOP_TEXT_DOMAIN ) : __( 'Pedido', EOP_TEXT_DOMAIN );
$preview_label   = 'proposal' === $preview_document ? __( 'Proposta', EOP_TEXT_DOMAIN ) : __( 'Pedido', EOP_TEXT_DOMAIN );
$preview_notice  = $preview_order instanceof WC_Order && $preview_document !== $document;
$preview_allowed = $preview_order instanceof WC_Order;
$preview_advanced_html_notice = $preview_order instanceof WC_Order && 'yes' === $pdf_settings['advanced_html_output'];
$preview_mode    = empty( $_GET['preview_order'] ) ? __( 'Atualmente mostrando o ultimo pedido', EOP_TEXT_DOMAIN ) : __( 'Atualmente mostrando o pedido selecionado', EOP_TEXT_DOMAIN );
$preview_pdf_url = $preview_order instanceof WC_Order ? EOP_Document_Manager::get_pdf_document_url( $preview_order, $preview_document ) : '';
$preview_xml     = $preview_order instanceof WC_Order && class_exists( 'EOP_Document_Manager' ) ? EOP_Document_Manager::get_edocument_xml_preview( $preview_order, $preview_document ) : '';
$preview_xml_url = $preview_order instanceof WC_Order ? wp_nonce_url( add_query_arg( array( 'action' => 'eop_download_edoc_xml', 'order_id' => $preview_order->get_id(), 'document' => $preview_document ), admin_url( 'admin-post.php' ) ), 'eop_download_edoc_xml' ) : '';
$danger_zone_enabled = 'yes' === ( $pdf_settings['advanced_danger_zone'] ?? 'no' );
$purge_cache_url = wp_nonce_url( add_query_arg( array( 'action' => 'eop_pdf_purge_cache' ), admin_url( 'admin-post.php' ) ), 'eop_pdf_purge_cache' );
$reset_counters_url = wp_nonce_url( add_query_arg( array( 'action' => 'eop_pdf_reset_counters' ), admin_url( 'admin-post.php' ) ), 'eop_pdf_reset_counters' );
$pdf_action_notice = isset( $_GET['eop_pdf_action'] ) ? sanitize_key( wp_unslash( $_GET['eop_pdf_action'] ) ) : '';
$order_page_url  = EOP_Page_Installer::get_page_url( 'order' );
$proposal_url    = EOP_Page_Installer::get_page_url( 'proposal' );
$current_tab_label = isset( $tabs[ $tab ] ) ? $tabs[ $tab ] : __( 'PDF', EOP_TEXT_DOMAIN );
$woo_general_url = admin_url( 'admin.php?page=wc-settings&tab=general' );
$documentation_sections = class_exists( 'EOP_PDF_Settings' ) ? EOP_PDF_Settings::get_documentation_sections() : array();
$current_view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'pdf';
$show_context_chrome = ! $embedded;
$pdf_form_view = in_array( $current_view, array( 'pdf', 'settings-store-info' ), true ) ? $current_view : 'pdf';
$pdf_form_args = array();

if ( 'pdf' === $pdf_form_view ) {
    $pdf_form_args['pdf_tab'] = $tab;

    if ( $is_document_tab ) {
        $pdf_form_args['document'] = $document;
    }

    if ( $preview_order instanceof WC_Order ) {
        $pdf_form_args['preview_order'] = $preview_order->get_id();
    }
}
?>
<style>
    .eop-pdf-admin {
        --eop-primary: <?php echo esc_attr( $plugin_settings['primary_color'] ); ?>;
        --eop-surface: <?php echo esc_attr( $plugin_settings['surface_color'] ); ?>;
        --eop-border: <?php echo esc_attr( $plugin_settings['border_color'] ); ?>;
        --eop-radius: <?php echo esc_attr( absint( $plugin_settings['border_radius'] ) ); ?>px;
        --eop-font-family: <?php echo esc_attr( $font_css ); ?>;
        font-family: <?php echo esc_attr( $font_css ); ?>;
    }
</style>

<div class="<?php echo esc_attr( $embedded ? 'eop-pdf-admin eop-pdf-admin--embedded' : 'wrap eop-pdf-admin' ); ?>">
    <?php if ( ! $embedded ) : ?>
        <div class="eop-pdf-admin__header">
            <div>
                <h1><?php esc_html_e( 'PDF', EOP_TEXT_DOMAIN ); ?></h1>
                <p><?php esc_html_e( 'Modulo nativo de documentos do Pedido Expresso, com configuracoes e preview no estilo da experiencia anterior.', EOP_TEXT_DOMAIN ); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <?php if ( ! $embedded ) : ?>
        <nav class="nav-tab-wrapper eop-pdf-admin__tabs">
            <?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
                <a class="nav-tab<?php echo $tab_key === $tab ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( EOP_PDF_Admin_Page::get_tab_url( $tab_key, array( 'document' => $document, 'preview_order' => $preview_order ? $preview_order->get_id() : 0 ) ) ); ?>">
                    <?php echo esc_html( $tab_label ); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if ( isset( $_GET['settings-updated'] ) && 'true' === wp_unslash( $_GET['settings-updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Configuracoes do PDF salvas com sucesso.', EOP_TEXT_DOMAIN ); ?></p></div>
    <?php endif; ?>

    <?php if ( 'cache_purged' === $pdf_action_notice ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cache do modulo PDF removido com sucesso.', EOP_TEXT_DOMAIN ); ?></p></div>
    <?php elseif ( 'counters_reset' === $pdf_action_notice ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Contadores de numeracao resetados para 1.', EOP_TEXT_DOMAIN ); ?></p></div>
    <?php endif; ?>

    <div class="eop-pdf-admin__shell" data-eop-pdf-shell data-eop-pdf-tab-current="<?php echo esc_attr( $tab ); ?>">
        <div class="eop-pdf-admin__sidebar">
            <?php if ( in_array( $tab, array( 'display', 'store' ), true ) ) : ?>
                <form method="post" action="options.php" class="eop-pdf-admin__form">
                    <?php if ( $embedded && class_exists( 'EOP_Admin_Page' ) ) : ?>
                        <?php EOP_Admin_Page::render_option_form_fields( 'eop_pdf_settings_group', $pdf_form_view, $pdf_form_args ); ?>
                    <?php else : ?>
                        <?php settings_fields( 'eop_pdf_settings_group' ); ?>
                    <?php endif; ?>

                    <?php if ( $show_context_chrome ) : ?>
                    <div class="eop-pdf-admin__notice">
                        <p>
                            <?php
                            echo 'store' === $tab
                                ? esc_html__( 'Centralize aqui os dados da loja usados nos documentos e no cabecalho institucional do modulo.', EOP_TEXT_DOMAIN )
                                : esc_html__( 'Configure como o PDF deve ser exibido e qual modelo o modulo usa ao gerar ou abrir o documento.', EOP_TEXT_DOMAIN );
                            ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if ( 'display' === $tab ) : ?>
                    <details class="eop-pdf-admin__section" open>
                        <summary><?php esc_html_e( 'Configuracoes de exibicao', EOP_TEXT_DOMAIN ); ?></summary>
                        <div class="eop-pdf-admin__grid">
                            <div class="eop-settings-field">
                                <label for="eop_pdf_display_mode"><?php esc_html_e( 'Como voce deseja visualizar o PDF?', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_pdf_display_mode" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[display_mode]">
                                    <option value="new_tab" <?php selected( $pdf_settings['display_mode'], 'new_tab' ); ?>><?php esc_html_e( 'Abrir o PDF em uma nova aba/janela', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="download" <?php selected( $pdf_settings['display_mode'], 'download' ); ?>><?php esc_html_e( 'Baixar automaticamente', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_pdf_paper_size"><?php esc_html_e( 'Tamanho do papel', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_pdf_paper_size" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[paper_size]">
                                    <option value="a4" <?php selected( $pdf_settings['paper_size'], 'a4' ); ?>>A4</option>
                                    <option value="letter" <?php selected( $pdf_settings['paper_size'], 'letter' ); ?>>Letter</option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_pdf_template_name"><?php esc_html_e( 'Escolha um modelo', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_pdf_template_name" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[template_name]">
                                    <option value="simple" <?php selected( $pdf_settings['template_name'], 'simple' ); ?>><?php esc_html_e( 'Simple', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="compact" <?php selected( $pdf_settings['template_name'], 'compact' ); ?>><?php esc_html_e( 'Compact', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="minimal" <?php selected( $pdf_settings['template_name'], 'minimal' ); ?>><?php esc_html_e( 'Minimal', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_pdf_ink_saving_mode"><?php esc_html_e( 'Modo de economia de tinta', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_pdf_ink_saving_mode" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[ink_saving_mode]">
                                    <option value="no" <?php selected( $pdf_settings['ink_saving_mode'], 'no' ); ?>><?php esc_html_e( 'Desativado', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings['ink_saving_mode'], 'yes' ); ?>><?php esc_html_e( 'Ativado', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_pdf_test_mode"><?php esc_html_e( 'Modo de teste', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_pdf_test_mode" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[test_mode]">
                                    <option value="no" <?php selected( $pdf_settings['test_mode'], 'no' ); ?>><?php esc_html_e( 'Desativado', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings['test_mode'], 'yes' ); ?>><?php esc_html_e( 'Ativado', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_pdf_font_subsetting"><?php esc_html_e( 'Font subsetting', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_pdf_font_subsetting" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[font_subsetting]">
                                    <option value="yes" <?php selected( $pdf_settings['font_subsetting'], 'yes' ); ?>><?php esc_html_e( 'Ativado', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings['font_subsetting'], 'no' ); ?>><?php esc_html_e( 'Desativado', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                        </div>
                    </details>
                    <?php endif; ?>

                    <?php if ( 'store' === $tab ) : ?>
                    <details class="eop-pdf-admin__section" open>
                        <summary><?php esc_html_e( 'Informacoes sobre a loja', EOP_TEXT_DOMAIN ); ?></summary>
                        <div class="eop-pdf-admin__section-note">
                            <p><?php printf( wp_kses_post( __( 'Nome da loja, endereco, telefone, e-mail e documento compartilham a mesma base do Aireset Default e do WooCommerce. Alterando aqui, o outro plugin e as configuracoes da loja tambem refletem os dados. Voce tambem pode conferir em <a href="%s">WooCommerce > Configuracoes > Geral</a>.', EOP_TEXT_DOMAIN ) ), esc_url( $woo_general_url ) ); ?></p>
                        </div>
                        <div class="eop-pdf-admin__grid">
                            <div class="eop-settings-field is-full">
                                <label><?php esc_html_e( 'Logo/Cabecalho da loja', EOP_TEXT_DOMAIN ); ?></label>
                                <div class="eop-settings-media" data-media-role="shop-logo">
                                    <input type="hidden" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_logo_url]" value="<?php echo esc_attr( $pdf_settings['shop_logo_url'] ); ?>" />
                                    <div class="eop-settings-media__preview<?php echo $pdf_settings['shop_logo_url'] ? ' has-image' : ''; ?>" data-media-preview>
                                        <?php if ( $pdf_settings['shop_logo_url'] ) : ?>
                                            <img src="<?php echo esc_url( $pdf_settings['shop_logo_url'] ); ?>" alt="">
                                        <?php else : ?>
                                            <span class="eop-settings-media__empty"><?php esc_html_e( 'Nenhum logo selecionado ainda.', EOP_TEXT_DOMAIN ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="eop-settings-media__details">
                                        <input type="url" class="eop-settings-media__url" value="<?php echo esc_attr( $pdf_settings['shop_logo_url'] ); ?>" readonly data-media-url />
                                        <div class="eop-settings-media__actions">
                                            <button type="button" class="button button-secondary eop-settings-media__select" data-media-select><?php echo $pdf_settings['shop_logo_url'] ? esc_html__( 'Trocar logo', EOP_TEXT_DOMAIN ) : esc_html__( 'Selecionar logo', EOP_TEXT_DOMAIN ); ?></button>
                                            <button type="button" class="button button-link-delete eop-settings-media__remove<?php echo $pdf_settings['shop_logo_url'] ? '' : ' is-hidden'; ?>" data-media-remove><?php esc_html_e( 'Remover logo', EOP_TEXT_DOMAIN ); ?></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_logo_height"><?php esc_html_e( 'Altura do logo', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_logo_height" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_logo_height]" value="<?php echo esc_attr( $pdf_settings['shop_logo_height'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_name"><?php esc_html_e( 'Nome da loja', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_name" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_name]" value="<?php echo esc_attr( $pdf_settings['shop_name'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_email"><?php esc_html_e( 'E-mail da loja', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_email" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_email]" value="<?php echo esc_attr( $pdf_settings['shop_email'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_postcode"><?php esc_html_e( 'Endereco (CEP)', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_postcode" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_postcode]" value="<?php echo esc_attr( $pdf_settings['shop_postcode'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_address_line_1"><?php esc_html_e( 'Endereco linha 1', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_address_line_1" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_address_line_1]" value="<?php echo esc_attr( $pdf_settings['shop_address_line_1'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_address_line_2"><?php esc_html_e( 'Endereco linha 2', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_address_line_2" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_address_line_2]" value="<?php echo esc_attr( $pdf_settings['shop_address_line_2'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_city"><?php esc_html_e( 'Cidade', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_city" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_city]" value="<?php echo esc_attr( $pdf_settings['shop_city'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_state"><?php esc_html_e( 'Estado (UF)', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_state" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_state]" value="<?php echo esc_attr( $pdf_settings['shop_state'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_country"><?php esc_html_e( 'Pais (ISO2)', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_country" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_country]" value="<?php echo esc_attr( $pdf_settings['shop_country'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_phone"><?php esc_html_e( 'Telefone da loja', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_phone" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_phone]" value="<?php echo esc_attr( $pdf_settings['shop_phone'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_vat_number"><?php esc_html_e( 'CNPJ / Documento', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_vat_number" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_vat_number]" value="<?php echo esc_attr( $pdf_settings['shop_vat_number'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_chamber_of_commerce"><?php esc_html_e( 'Camara de comercio / registro', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_chamber_of_commerce" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_chamber_of_commerce]" value="<?php echo esc_attr( $pdf_settings['shop_chamber_of_commerce'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_extra_1"><?php esc_html_e( 'Campo extra 1', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_extra_1" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_extra_1]" value="<?php echo esc_attr( $pdf_settings['shop_extra_1'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_extra_2"><?php esc_html_e( 'Campo extra 2', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_extra_2" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_extra_2]" value="<?php echo esc_attr( $pdf_settings['shop_extra_2'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_shop_extra_3"><?php esc_html_e( 'Campo extra 3', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_shop_extra_3" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_extra_3]" value="<?php echo esc_attr( $pdf_settings['shop_extra_3'] ); ?>" />
                            </div>
                            <div class="eop-settings-field is-full">
                                <label for="eop_shop_footer"><?php esc_html_e( 'Rodape / informacoes adicionais', EOP_TEXT_DOMAIN ); ?></label>
                                <textarea id="eop_shop_footer" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[shop_footer]"><?php echo esc_textarea( $pdf_settings['shop_footer'] ); ?></textarea>
                            </div>
                        </div>
                    </details>
                    <?php endif; ?>

                    <div class="eop-pdf-admin__actions eop-admin-submitbar"><?php submit_button( 'store' === $tab ? __( 'Salvar informacoes da loja', EOP_TEXT_DOMAIN ) : __( 'Salvar configuracoes de exibicao', EOP_TEXT_DOMAIN ), 'primary', 'submit', false ); ?></div>
                </form>
            <?php elseif ( $is_document_tab ) : ?>
                <form method="post" action="options.php" class="eop-pdf-admin__form">
                    <?php if ( $embedded && class_exists( 'EOP_Admin_Page' ) ) : ?>
                        <?php EOP_Admin_Page::render_option_form_fields( 'eop_pdf_settings_group', $pdf_form_view, $pdf_form_args ); ?>
                    <?php else : ?>
                        <?php settings_fields( 'eop_pdf_settings_group' ); ?>
                    <?php endif; ?>

                    <?php if ( $show_context_chrome ) : ?>
                    <div class="eop-pdf-admin__document-switcher" aria-label="<?php esc_attr_e( 'Documento em configuracao', EOP_TEXT_DOMAIN ); ?>">
                        <a class="eop-pdf-admin__document-pill<?php echo 'order' === $document ? ' is-active' : ''; ?>" href="<?php echo esc_url( EOP_PDF_Admin_Page::get_tab_url( 'order' === $document ? $tab : $document_pair_tab, array( 'preview_order' => $preview_order ? $preview_order->get_id() : 0 ) ) ); ?>"><?php esc_html_e( 'Pedido', EOP_TEXT_DOMAIN ); ?></a>
                        <a class="eop-pdf-admin__document-pill<?php echo 'proposal' === $document ? ' is-active' : ''; ?>" href="<?php echo esc_url( EOP_PDF_Admin_Page::get_tab_url( 'proposal' === $document ? $tab : $document_pair_tab, array( 'preview_order' => $preview_order ? $preview_order->get_id() : 0 ) ) ); ?>"><?php esc_html_e( 'Proposta', EOP_TEXT_DOMAIN ); ?></a>
                    </div>

                    <div class="eop-pdf-admin__notice eop-pdf-admin__notice--document-context">
                        <p><?php printf( esc_html__( 'Voce esta editando as configuracoes de %s. Pedido e proposta possuem textos e opcoes independentes.', EOP_TEXT_DOMAIN ), esc_html( $editing_label ) ); ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if ( 'settings' === $document_section ) : ?>
                    <details class="eop-pdf-admin__section" open>
                        <summary><?php echo esc_html( 'proposal' === $document ? __( 'Configuracoes da proposta', EOP_TEXT_DOMAIN ) : __( 'Configuracoes do pedido', EOP_TEXT_DOMAIN ) ); ?></summary>
                        <div class="eop-pdf-admin__grid">
                            <div class="eop-settings-field">
                                <label for="eop_doc_enabled"><?php esc_html_e( 'Documento habilitado', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_enabled" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_enabled]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_enabled' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_enabled' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_attach_email"><?php esc_html_e( 'Anexar em e-mails', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_attach_email" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_attach_email]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_attach_email' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_attach_email' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_shipping"><?php esc_html_e( 'Exibir endereco de entrega', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_shipping" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_shipping]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_shipping' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_shipping' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_billing"><?php esc_html_e( 'Exibir endereco de cobranca', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_billing" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_billing]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_billing' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_billing' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_email"><?php esc_html_e( 'Exibir e-mail do cliente', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_email" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_email]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_email' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_email' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_phone"><?php esc_html_e( 'Exibir telefone do cliente', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_phone" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_phone]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_phone' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_phone' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_notes"><?php esc_html_e( 'Exibir notas do cliente', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_notes" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_notes]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_notes' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_notes' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_mark_printed"><?php esc_html_e( 'Marcar como impresso', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_mark_printed" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_mark_printed]">
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_mark_printed' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_mark_printed' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_prefix"><?php esc_html_e( 'Prefixo do numero', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_prefix" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_prefix]" value="<?php echo esc_attr( $pdf_settings[ $document . '_prefix' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_suffix"><?php esc_html_e( 'Sufixo do numero', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_suffix" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_suffix]" value="<?php echo esc_attr( $pdf_settings[ $document . '_suffix' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_padding"><?php esc_html_e( 'Padding', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_padding" type="number" min="0" max="12" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_padding]" value="<?php echo esc_attr( $pdf_settings[ $document . '_padding' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_next_number"><?php esc_html_e( 'Proximo numero', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_next_number" type="number" min="1" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_next_number]" value="<?php echo esc_attr( $pdf_settings[ $document . '_next_number' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_reset_yearly"><?php esc_html_e( 'Reset anual', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_reset_yearly" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_reset_yearly]">
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_reset_yearly' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_reset_yearly' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <?php if ( 'order' === $document ) : ?>
                                <div class="eop-settings-field">
                                    <label for="eop_doc_myaccount"><?php esc_html_e( 'Download no Minha Conta', EOP_TEXT_DOMAIN ); ?></label>
                                    <select id="eop_doc_myaccount" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[order_myaccount_download]">
                                        <option value="no" <?php selected( $pdf_settings['order_myaccount_download'], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                        <option value="yes" <?php selected( $pdf_settings['order_myaccount_download'], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    </select>
                                </div>
                            <?php else : ?>
                                <div class="eop-settings-field">
                                    <label for="eop_doc_public_pdf"><?php esc_html_e( 'Permitir PDF publico da proposta', EOP_TEXT_DOMAIN ); ?></label>
                                    <select id="eop_doc_public_pdf" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[proposal_public_pdf]">
                                        <option value="yes" <?php selected( $pdf_settings['proposal_public_pdf'], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                        <option value="no" <?php selected( $pdf_settings['proposal_public_pdf'], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                    </details>
                    <details class="eop-pdf-admin__section" open>
                        <summary><?php esc_html_e( 'Totais exibidos', EOP_TEXT_DOMAIN ); ?></summary>
                        <div class="eop-pdf-admin__grid">
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_total_subtotal"><?php esc_html_e( 'Exibir subtotal', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_total_subtotal" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_total_subtotal]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_total_subtotal' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_total_subtotal' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_total_shipping"><?php esc_html_e( 'Exibir frete', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_total_shipping" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_total_shipping]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_total_shipping' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_total_shipping' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_total_discount"><?php esc_html_e( 'Exibir desconto total', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_total_discount" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_total_discount]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_total_discount' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_total_discount' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_total_total"><?php esc_html_e( 'Exibir total final', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_total_total" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_total_total]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_total_total' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_total_total' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                        </div>
                    </details>
                    <?php elseif ( 'columns' === $document_section ) : ?>
                    <details class="eop-pdf-admin__section" open>
                        <summary><?php esc_html_e( 'Colunas do detalhamento', EOP_TEXT_DOMAIN ); ?></summary>
                        <div class="eop-pdf-admin__grid">
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_item_index"><?php esc_html_e( 'Exibir coluna de item sequencial', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_item_index" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_item_index]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_item_index' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_item_index' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_sku"><?php esc_html_e( 'Exibir SKU do produto', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_sku" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_sku]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_sku' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_sku' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_quantity"><?php esc_html_e( 'Exibir coluna de quantidade', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_quantity" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_quantity]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_quantity' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_quantity' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_unit_price"><?php esc_html_e( 'Exibir coluna de valor unitario', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_unit_price" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_unit_price]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_unit_price' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_unit_price' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_discount"><?php esc_html_e( 'Exibir coluna de desconto', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_discount" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_discount]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_discount' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_discount' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_discounted_unit_price"><?php esc_html_e( 'Exibir coluna de valor unitario com desconto', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_discounted_unit_price" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_discounted_unit_price]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_discounted_unit_price' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_discounted_unit_price' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_show_line_total"><?php esc_html_e( 'Exibir coluna de total do item', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_show_line_total" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_show_line_total]">
                                    <option value="yes" <?php selected( $pdf_settings[ $document . '_show_line_total' ], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings[ $document . '_show_line_total' ], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                        </div>
                    </details>
                    <details class="eop-pdf-admin__section eop-pdf-admin__section--column-labels" open>
                        <summary><?php esc_html_e( 'Ordem das colunas', EOP_TEXT_DOMAIN ); ?></summary>
                        <div class="eop-pdf-admin__section-note">
                            <p><?php esc_html_e( 'Use numeros menores para trazer a coluna mais para a esquerda. A coluna de item sequencial fica sempre no inicio quando ativada.', EOP_TEXT_DOMAIN ); ?></p>
                        </div>
                        <div class="eop-pdf-admin__grid">
                            <div class="eop-settings-field">
                                <label for="eop_doc_quantity_position"><?php esc_html_e( 'Posicao da coluna de quantidade', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_quantity_position" type="number" min="1" max="99" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_quantity_position]" value="<?php echo esc_attr( $pdf_settings[ $document . '_quantity_position' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_unit_price_position"><?php esc_html_e( 'Posicao da coluna de valor unitario', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_unit_price_position" type="number" min="1" max="99" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_unit_price_position]" value="<?php echo esc_attr( $pdf_settings[ $document . '_unit_price_position' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_discount_position"><?php esc_html_e( 'Posicao da coluna de desconto', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_discount_position" type="number" min="1" max="99" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_discount_position]" value="<?php echo esc_attr( $pdf_settings[ $document . '_discount_position' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_discounted_unit_price_position"><?php esc_html_e( 'Posicao da coluna de valor unitario com desconto', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_discounted_unit_price_position" type="number" min="1" max="99" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_discounted_unit_price_position]" value="<?php echo esc_attr( $pdf_settings[ $document . '_discounted_unit_price_position' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_line_total_position"><?php esc_html_e( 'Posicao da coluna de total do item', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_line_total_position" type="number" min="1" max="99" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_line_total_position]" value="<?php echo esc_attr( $pdf_settings[ $document . '_line_total_position' ] ); ?>" />
                            </div>
                        </div>
                    </details>
                    <?php elseif ( 'texts' === $document_section ) : ?>
                    <details class="eop-pdf-admin__section eop-pdf-admin__section--column-labels" open>
                        <summary><?php esc_html_e( 'Textos das colunas', EOP_TEXT_DOMAIN ); ?></summary>
                        <div class="eop-pdf-admin__grid">
                            <div class="eop-settings-field">
                                <label for="eop_doc_item_index_label"><?php esc_html_e( 'Texto da coluna de item sequencial', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_item_index_label" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_item_index_label]" value="<?php echo esc_attr( $pdf_settings[ $document . '_item_index_label' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_product_label"><?php esc_html_e( 'Texto da coluna de produto', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_product_label" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_product_label]" value="<?php echo esc_attr( $pdf_settings[ $document . '_product_label' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_quantity_label"><?php esc_html_e( 'Texto da coluna de quantidade', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_quantity_label" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_quantity_label]" value="<?php echo esc_attr( $pdf_settings[ $document . '_quantity_label' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_unit_price_label"><?php esc_html_e( 'Texto da coluna de valor unitario', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_unit_price_label" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_unit_price_label]" value="<?php echo esc_attr( $pdf_settings[ $document . '_unit_price_label' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_discount_label"><?php esc_html_e( 'Texto da coluna de desconto', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_discount_label" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_discount_label]" value="<?php echo esc_attr( $pdf_settings[ $document . '_discount_label' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_discount_suffix"><?php esc_html_e( 'Texto complementar do desconto por unidade', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_discount_suffix" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_discount_suffix]" value="<?php echo esc_attr( $pdf_settings[ $document . '_discount_suffix' ] ); ?>" placeholder="/ un." />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_discount_display_mode"><?php esc_html_e( 'Formato da coluna de desconto', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_doc_discount_display_mode" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_discount_display_mode]">
                                    <option value="percent" <?php selected( $pdf_settings[ $document . '_discount_display_mode' ], 'percent' ); ?>><?php esc_html_e( 'Porcentagem', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="currency" <?php selected( $pdf_settings[ $document . '_discount_display_mode' ], 'currency' ); ?>><?php esc_html_e( 'Moeda', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="both" <?php selected( $pdf_settings[ $document . '_discount_display_mode' ], 'both' ); ?>><?php esc_html_e( 'Ambos', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_discounted_unit_price_label"><?php esc_html_e( 'Texto da coluna de valor unitario com desconto', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_discounted_unit_price_label" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_discounted_unit_price_label]" value="<?php echo esc_attr( $pdf_settings[ $document . '_discounted_unit_price_label' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_discounted_unit_price_suffix"><?php esc_html_e( 'Texto complementar do valor unitario com desconto', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_discounted_unit_price_suffix" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_discounted_unit_price_suffix]" value="<?php echo esc_attr( $pdf_settings[ $document . '_discounted_unit_price_suffix' ] ); ?>" placeholder="/ un." />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_line_total_label"><?php esc_html_e( 'Texto da coluna de total do item', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_line_total_label" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_line_total_label]" value="<?php echo esc_attr( $pdf_settings[ $document . '_line_total_label' ] ); ?>" />
                            </div>
                        </div>
                    </details>
                    <?php elseif ( 'style' === $document_section ) : ?>
                    <details class="eop-pdf-admin__section" open>
                        <summary><?php esc_html_e( 'Visual do documento', EOP_TEXT_DOMAIN ); ?></summary>
                        <div class="eop-pdf-admin__grid">
                            <div class="eop-settings-field">
                                <label for="eop_doc_header_background_color"><?php esc_html_e( 'Cor de fundo do cabecalho da tabela', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_header_background_color" type="text" class="eop-color-field" data-default-color="#111111" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_header_background_color]" value="<?php echo esc_attr( $pdf_settings[ $document . '_header_background_color' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_header_text_color"><?php esc_html_e( 'Cor do texto do cabecalho da tabela', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_header_text_color" type="text" class="eop-color-field" data-default-color="#ffffff" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_header_text_color]" value="<?php echo esc_attr( $pdf_settings[ $document . '_header_text_color' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_body_text_color"><?php esc_html_e( 'Cor principal do texto', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_body_text_color" type="text" class="eop-color-field" data-default-color="#172033" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_body_text_color]" value="<?php echo esc_attr( $pdf_settings[ $document . '_body_text_color' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_muted_text_color"><?php esc_html_e( 'Cor dos textos auxiliares', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_muted_text_color" type="text" class="eop-color-field" data-default-color="#5b6474" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_muted_text_color]" value="<?php echo esc_attr( $pdf_settings[ $document . '_muted_text_color' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_border_color"><?php esc_html_e( 'Cor das bordas e divisorias', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_border_color" type="text" class="eop-color-field" data-default-color="#e3e8f1" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_border_color]" value="<?php echo esc_attr( $pdf_settings[ $document . '_border_color' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_title_font_size"><?php esc_html_e( 'Tamanho do titulo do documento', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_title_font_size" type="number" min="8" max="72" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_title_font_size]" value="<?php echo esc_attr( $pdf_settings[ $document . '_title_font_size' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_meta_font_size"><?php esc_html_e( 'Tamanho dos textos de apoio', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_meta_font_size" type="number" min="8" max="72" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_meta_font_size]" value="<?php echo esc_attr( $pdf_settings[ $document . '_meta_font_size' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_table_header_font_size"><?php esc_html_e( 'Tamanho da fonte do cabecalho da tabela', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_table_header_font_size" type="number" min="8" max="72" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_table_header_font_size]" value="<?php echo esc_attr( $pdf_settings[ $document . '_table_header_font_size' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_table_body_font_size"><?php esc_html_e( 'Tamanho da fonte do corpo da tabela', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_table_body_font_size" type="number" min="8" max="72" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_table_body_font_size]" value="<?php echo esc_attr( $pdf_settings[ $document . '_table_body_font_size' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_table_body_line_height"><?php esc_html_e( 'Altura da linha do corpo da tabela', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_table_body_line_height" type="text" inputmode="decimal" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_table_body_line_height]" value="<?php echo esc_attr( $pdf_settings[ $document . '_table_body_line_height' ] ); ?>" placeholder="1.35" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_totals_font_size"><?php esc_html_e( 'Tamanho da fonte dos totais', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_totals_font_size" type="number" min="8" max="72" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_totals_font_size]" value="<?php echo esc_attr( $pdf_settings[ $document . '_totals_font_size' ] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_doc_note_font_size"><?php esc_html_e( 'Tamanho da fonte das observacoes e rodape', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_doc_note_font_size" type="number" min="8" max="72" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $document ); ?>_note_font_size]" value="<?php echo esc_attr( $pdf_settings[ $document . '_note_font_size' ] ); ?>" />
                            </div>
                        </div>
                    </details>
                    <?php endif; ?>
                    <div class="eop-pdf-admin__actions eop-admin-submitbar"><?php submit_button( __( 'Salvar configuracoes do documento', EOP_TEXT_DOMAIN ), 'primary', 'submit', false ); ?></div>
                </form>
            <?php elseif ( 'edocuments' === $tab ) : ?>
                <form method="post" action="options.php" class="eop-pdf-admin__form">
                    <?php if ( $embedded && class_exists( 'EOP_Admin_Page' ) ) : ?>
                        <?php EOP_Admin_Page::render_option_form_fields( 'eop_pdf_settings_group', $pdf_form_view, $pdf_form_args ); ?>
                    <?php else : ?>
                        <?php settings_fields( 'eop_pdf_settings_group' ); ?>
                    <?php endif; ?>
                    <details class="eop-pdf-admin__section" open>
                        <summary><?php esc_html_e( 'Documentos eletrônicos', EOP_TEXT_DOMAIN ); ?></summary>
                        <div class="eop-pdf-admin__grid">
                            <div class="eop-settings-field">
                                <label for="eop_edoc_enabled"><?php esc_html_e( 'Ativar documentos eletrônicos', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_edoc_enabled" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[edoc_enabled]">
                                    <option value="no" <?php selected( $pdf_settings['edoc_enabled'], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings['edoc_enabled'], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_edoc_format"><?php esc_html_e( 'Formato / sintaxe', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_edoc_format" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[edoc_format]">
                                    <option value="ubl" <?php selected( $pdf_settings['edoc_format'], 'ubl' ); ?>>UBL</option>
                                    <option value="cii" <?php selected( $pdf_settings['edoc_format'], 'cii' ); ?>>CII</option>
                                    <option value="peppol" <?php selected( $pdf_settings['edoc_format'], 'peppol' ); ?>>Peppol</option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_edoc_embed_pdf"><?php esc_html_e( 'Embutir PDF', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_edoc_embed_pdf" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[edoc_embed_pdf]">
                                    <option value="yes" <?php selected( $pdf_settings['edoc_embed_pdf'], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings['edoc_embed_pdf'], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_edoc_preview_xml"><?php esc_html_e( 'Habilitar preview XML', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_edoc_preview_xml" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[edoc_preview_xml]">
                                    <option value="yes" <?php selected( $pdf_settings['edoc_preview_xml'], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings['edoc_preview_xml'], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_edoc_logging"><?php esc_html_e( 'Habilitar logs', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_edoc_logging" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[edoc_logging]">
                                    <option value="no" <?php selected( $pdf_settings['edoc_logging'], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings['edoc_logging'], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_edoc_supplier_scheme"><?php esc_html_e( 'Identificador do fornecedor', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_edoc_supplier_scheme" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[edoc_supplier_scheme]" value="<?php echo esc_attr( $pdf_settings['edoc_supplier_scheme'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_edoc_customer_scheme"><?php esc_html_e( 'Identificador do cliente', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_edoc_customer_scheme" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[edoc_customer_scheme]" value="<?php echo esc_attr( $pdf_settings['edoc_customer_scheme'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_edoc_network_endpoint"><?php esc_html_e( 'Peppol Endpoint ID', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_edoc_network_endpoint" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[edoc_network_endpoint]" value="<?php echo esc_attr( $pdf_settings['edoc_network_endpoint'] ); ?>" />
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_edoc_network_eas"><?php esc_html_e( 'Peppol EAS', EOP_TEXT_DOMAIN ); ?></label>
                                <input id="eop_edoc_network_eas" type="text" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[edoc_network_eas]" value="<?php echo esc_attr( $pdf_settings['edoc_network_eas'] ); ?>" />
                            </div>
                        </div>
                    </details>

                    <?php if ( 'yes' === $pdf_settings['edoc_enabled'] && 'yes' === $pdf_settings['edoc_preview_xml'] && $preview_order instanceof WC_Order && '' !== $preview_xml ) : ?>
                        <details class="eop-pdf-admin__section" open>
                            <summary><?php esc_html_e( 'Preview do XML tecnico', EOP_TEXT_DOMAIN ); ?></summary>
                            <div class="eop-pdf-admin__section-note">
                                <p><?php printf( esc_html__( 'Preview gerado a partir do %1$s #%2$d.', EOP_TEXT_DOMAIN ), esc_html( strtolower( $preview_label ) ), esc_html( $preview_order->get_id() ) ); ?></p>
                            </div>
                            <div class="eop-pdf-admin__xml-panel">
                                <textarea readonly aria-readonly="true"><?php echo esc_textarea( $preview_xml ); ?></textarea>
                            </div>
                            <div class="eop-pdf-admin__actions">
                                <a class="button button-secondary" href="<?php echo esc_url( $preview_xml_url ); ?>"><?php esc_html_e( 'Baixar XML do preview', EOP_TEXT_DOMAIN ); ?></a>
                            </div>
                        </details>
                    <?php elseif ( 'yes' === $pdf_settings['edoc_enabled'] ) : ?>
                        <div class="eop-pdf-admin__notice">
                            <p><?php esc_html_e( 'Ative o preview XML e selecione um pedido valido para visualizar a exportacao tecnica.', EOP_TEXT_DOMAIN ); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="eop-pdf-admin__actions eop-admin-submitbar"><?php submit_button( __( 'Salvar configuracoes eletrônicas', EOP_TEXT_DOMAIN ), 'primary', 'submit', false ); ?></div>
                </form>
            <?php elseif ( 'advanced' === $tab ) : ?>
                <form method="post" action="options.php" class="eop-pdf-admin__form">
                    <?php if ( $embedded && class_exists( 'EOP_Admin_Page' ) ) : ?>
                        <?php EOP_Admin_Page::render_option_form_fields( 'eop_pdf_settings_group', $pdf_form_view, $pdf_form_args ); ?>
                    <?php else : ?>
                        <?php settings_fields( 'eop_pdf_settings_group' ); ?>
                    <?php endif; ?>
                    <details class="eop-pdf-admin__section" open>
                        <summary><?php esc_html_e( 'Configuracoes avancadas', EOP_TEXT_DOMAIN ); ?></summary>
                        <div class="eop-pdf-admin__grid">
                            <div class="eop-settings-field">
                                <label for="eop_adv_link_access"><?php esc_html_e( 'Politica de acesso ao link', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_adv_link_access" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[advanced_link_access]">
                                    <option value="private_nonce" <?php selected( $pdf_settings['advanced_link_access'], 'private_nonce' ); ?>><?php esc_html_e( 'Privado com nonce', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="public_token" <?php selected( $pdf_settings['advanced_link_access'], 'public_token' ); ?>><?php esc_html_e( 'Publico por token', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="order_owner" <?php selected( $pdf_settings['advanced_link_access'], 'order_owner' ); ?>><?php esc_html_e( 'Dono do pedido', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_adv_pretty_links"><?php esc_html_e( 'Pretty links', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_adv_pretty_links" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[advanced_pretty_links]">
                                    <option value="no" <?php selected( $pdf_settings['advanced_pretty_links'], 'no' ); ?>><?php esc_html_e( 'Desativado', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings['advanced_pretty_links'], 'yes' ); ?>><?php esc_html_e( 'Ativado', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_adv_html_output"><?php esc_html_e( 'Forcar output HTML', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_adv_html_output" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[advanced_html_output]">
                                    <option value="no" <?php selected( $pdf_settings['advanced_html_output'], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings['advanced_html_output'], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_adv_debug"><?php esc_html_e( 'Debug do modulo', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_adv_debug" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[advanced_debug]">
                                    <option value="no" <?php selected( $pdf_settings['advanced_debug'], 'no' ); ?>><?php esc_html_e( 'Desativado', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings['advanced_debug'], 'yes' ); ?>><?php esc_html_e( 'Ativado', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_adv_order_note_logs"><?php esc_html_e( 'Logar nas notas do pedido', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_adv_order_note_logs" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[advanced_order_note_logs]">
                                    <option value="no" <?php selected( $pdf_settings['advanced_order_note_logs'], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings['advanced_order_note_logs'], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_adv_auto_cleanup"><?php esc_html_e( 'Limpeza automatica', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_adv_auto_cleanup" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[advanced_auto_cleanup]">
                                    <option value="yes" <?php selected( $pdf_settings['advanced_auto_cleanup'], 'yes' ); ?>><?php esc_html_e( 'Sim', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="no" <?php selected( $pdf_settings['advanced_auto_cleanup'], 'no' ); ?>><?php esc_html_e( 'Nao', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                            <div class="eop-settings-field">
                                <label for="eop_adv_danger_zone"><?php esc_html_e( 'Danger zone / ferramentas destrutivas', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop_adv_danger_zone" name="<?php echo esc_attr( EOP_PDF_Settings::OPTION_KEY ); ?>[advanced_danger_zone]">
                                    <option value="no" <?php selected( $pdf_settings['advanced_danger_zone'], 'no' ); ?>><?php esc_html_e( 'Bloqueado', EOP_TEXT_DOMAIN ); ?></option>
                                    <option value="yes" <?php selected( $pdf_settings['advanced_danger_zone'], 'yes' ); ?>><?php esc_html_e( 'Liberado', EOP_TEXT_DOMAIN ); ?></option>
                                </select>
                            </div>
                        </div>
                    </details>

                    <details class="eop-pdf-admin__section" open>
                        <summary><?php esc_html_e( 'Status e links do modulo', EOP_TEXT_DOMAIN ); ?></summary>
                        <div class="eop-pdf-admin__status-grid">
                            <div class="eop-pdf-admin__status-card">
                                <span><?php esc_html_e( 'Pagina do pedido', EOP_TEXT_DOMAIN ); ?></span>
                                <?php if ( $order_page_url ) : ?>
                                    <a href="<?php echo esc_url( $order_page_url ); ?>" target="_blank"><?php echo esc_html( $order_page_url ); ?></a>
                                <?php else : ?>
                                    <strong><?php esc_html_e( 'Nao configurada', EOP_TEXT_DOMAIN ); ?></strong>
                                <?php endif; ?>
                            </div>
                            <div class="eop-pdf-admin__status-card">
                                <span><?php esc_html_e( 'Pagina da proposta', EOP_TEXT_DOMAIN ); ?></span>
                                <?php if ( $proposal_url ) : ?>
                                    <a href="<?php echo esc_url( $proposal_url ); ?>" target="_blank"><?php echo esc_html( $proposal_url ); ?></a>
                                <?php else : ?>
                                    <strong><?php esc_html_e( 'Nao configurada', EOP_TEXT_DOMAIN ); ?></strong>
                                <?php endif; ?>
                            </div>
                        </div>
                    </details>

                    <details class="eop-pdf-admin__section" open>
                        <summary><?php esc_html_e( 'Danger zone', EOP_TEXT_DOMAIN ); ?></summary>
                        <?php if ( $danger_zone_enabled ) : ?>
                            <div class="eop-pdf-admin__danger-grid">
                                <div class="eop-pdf-admin__danger-card">
                                    <h3><?php esc_html_e( 'Limpar cache do PDF', EOP_TEXT_DOMAIN ); ?></h3>
                                    <p><?php esc_html_e( 'Remove PDFs em cache e artefatos temporarios do modulo. O proximo download sera regenerado do zero.', EOP_TEXT_DOMAIN ); ?></p>
                                    <a class="button button-secondary" href="<?php echo esc_url( $purge_cache_url ); ?>"><?php esc_html_e( 'Limpar cache agora', EOP_TEXT_DOMAIN ); ?></a>
                                </div>
                                <div class="eop-pdf-admin__danger-card">
                                    <h3><?php esc_html_e( 'Resetar contadores', EOP_TEXT_DOMAIN ); ?></h3>
                                    <p><?php esc_html_e( 'Volta o proximo numero de pedido e proposta para 1. Nao altera documentos ja numerados.', EOP_TEXT_DOMAIN ); ?></p>
                                    <a class="button button-secondary" href="<?php echo esc_url( $reset_counters_url ); ?>"><?php esc_html_e( 'Resetar contadores', EOP_TEXT_DOMAIN ); ?></a>
                                </div>
                            </div>
                        <?php else : ?>
                            <div class="eop-pdf-admin__notice">
                                <p><?php esc_html_e( 'Libere a opcao Danger zone para exibir ferramentas destrutivas controladas por nonce.', EOP_TEXT_DOMAIN ); ?></p>
                            </div>
                        <?php endif; ?>
                    </details>

                    <div class="eop-pdf-admin__actions eop-admin-submitbar"><?php submit_button( __( 'Salvar configuracoes avancadas', EOP_TEXT_DOMAIN ), 'primary', 'submit', false ); ?></div>
                </form>
            <?php else : ?>
                <div class="eop-pdf-admin__documentation">
                    <div class="eop-pdf-admin__notice eop-pdf-admin__notice--documentation">
                        <p><?php esc_html_e( 'Esta documentacao fica disponivel dentro do modulo para explicar o que cada configuracao faz e qual efeito ela tem no PDF, nas URLs e no XML tecnico.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>

                    <?php foreach ( $documentation_sections as $section ) : ?>
                        <section class="eop-pdf-admin__doc-section">
                            <header class="eop-pdf-admin__doc-header">
                                <h2><?php echo esc_html( $section['title'] ?? '' ); ?></h2>
                                <?php if ( ! empty( $section['description'] ) ) : ?>
                                    <p><?php echo esc_html( $section['description'] ); ?></p>
                                <?php endif; ?>
                            </header>

                            <?php if ( ! empty( $section['fields'] ) ) : ?>
                                <div class="eop-pdf-admin__doc-grid">
                                    <?php foreach ( $section['fields'] as $field ) : ?>
                                        <article class="eop-pdf-admin__doc-card">
                                            <div class="eop-pdf-admin__doc-card-head">
                                                <h3><?php echo esc_html( $field['label'] ?? '' ); ?></h3>
                                                <?php if ( ! empty( $field['status'] ) ) : ?>
                                                    <span class="eop-pdf-admin__doc-badge eop-pdf-admin__doc-badge--<?php echo esc_attr( $field['status'] ); ?>">
                                                        <?php echo esc_html( 'experimental' === $field['status'] ? __( 'Experimental', EOP_TEXT_DOMAIN ) : __( 'Ativo', EOP_TEXT_DOMAIN ) ); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ( ! empty( $field['help'] ) ) : ?>
                                                <p class="eop-pdf-admin__doc-text"><?php echo esc_html( $field['help'] ); ?></p>
                                            <?php endif; ?>

                                            <?php if ( ! empty( $field['effect'] ) ) : ?>
                                                <p class="eop-pdf-admin__doc-effect"><strong><?php esc_html_e( 'Efeito real:', EOP_TEXT_DOMAIN ); ?></strong> <?php echo esc_html( $field['effect'] ); ?></p>
                                            <?php endif; ?>

                                            <?php if ( ! empty( $field['values'] ) && is_array( $field['values'] ) ) : ?>
                                                <p class="eop-pdf-admin__doc-values"><strong><?php esc_html_e( 'Valores aceitos:', EOP_TEXT_DOMAIN ); ?></strong> <?php echo esc_html( implode( ', ', array_map( 'strval', $field['values'] ) ) ); ?></p>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>

                    <div class="eop-pdf-admin__status-grid">
                        <div class="eop-pdf-admin__status-card"><span><?php esc_html_e( 'Leitura rapida', EOP_TEXT_DOMAIN ); ?></span><strong><?php esc_html_e( 'Use os tooltips ao lado dos labels para ajuda curta e esta aba para contexto completo.', EOP_TEXT_DOMAIN ); ?></strong></div>
                        <div class="eop-pdf-admin__status-card"><span><?php esc_html_e( 'Operacao', EOP_TEXT_DOMAIN ); ?></span><strong><?php esc_html_e( 'Sempre valide com um pedido real no preview depois de mudar numeracao, acesso, colunas ou dados da loja.', EOP_TEXT_DOMAIN ); ?></strong></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <button type="button" class="eop-pdf-admin__preview-toggle" data-eop-pdf-preview-toggle aria-expanded="false" aria-controls="eop-pdf-preview-drawer">
            <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
            <span><?php esc_html_e( 'Abrir preview', EOP_TEXT_DOMAIN ); ?></span>
        </button>

        <div class="eop-pdf-admin__preview-backdrop" data-eop-pdf-preview-close></div>

        <aside id="eop-pdf-preview-drawer" class="eop-pdf-admin__preview-drawer" aria-hidden="true">
            <div class="eop-pdf-admin__preview-header">
                <div>
                    <strong><?php esc_html_e( 'Preview do documento', EOP_TEXT_DOMAIN ); ?></strong>
                    <span><?php echo esc_html( $current_tab_label ); ?></span>
                </div>
                <button type="button" class="button button-secondary" data-eop-pdf-preview-close><?php esc_html_e( 'Fechar', EOP_TEXT_DOMAIN ); ?></button>
            </div>

            <div class="eop-pdf-admin__preview">
                <form method="get" class="eop-pdf-admin__preview-toolbar">
                    <?php if ( $embedded || EOP_PDF_Admin_Page::is_spa_request() ) : ?>
                        <input type="hidden" name="page" value="eop-pedido-expresso" />
                        <input type="hidden" name="view" value="<?php echo esc_attr( $current_view ); ?>" />
                        <?php if ( 'pdf' === $current_view ) : ?>
                            <input type="hidden" name="pdf_tab" value="<?php echo esc_attr( $tab ); ?>" />
                        <?php endif; ?>
                    <?php else : ?>
                        <input type="hidden" name="page" value="<?php echo esc_attr( EOP_PDF_Admin_Page::get_tab_page_slug( $tab ) ); ?>" />
                    <?php endif; ?>
                    <input type="hidden" name="document" value="<?php echo esc_attr( $document ); ?>" />
                    <button type="submit" class="button button-primary" formaction="<?php echo esc_url( $preview_pdf_url ); ?>" formmethod="get"<?php echo $preview_pdf_url ? '' : ' disabled'; ?>>PDF</button>
                    <div class="eop-pdf-admin__toolbar-selects">
                        <label>
                            <span><?php esc_html_e( 'Documento do preview', EOP_TEXT_DOMAIN ); ?></span>
                            <strong class="eop-pdf-admin__toolbar-value"><?php echo esc_html( $preview_label ); ?></strong>
                        </label>
                        <label>
                            <span><?php echo esc_html( $preview_mode ); ?></span>
                            <select name="preview_order">
                                <option value="0"><?php esc_html_e( 'Ultimo pedido', EOP_TEXT_DOMAIN ); ?></option>
                                <?php foreach ( $recent_orders as $order_option ) : ?>
                                    <option value="<?php echo esc_attr( $order_option->get_id() ); ?>" <?php selected( $preview_order && $preview_order->get_id() === $order_option->get_id() ); ?>>
                                        <?php echo esc_html( '#' . $order_option->get_id() . ' - ' . trim( $order_option->get_billing_first_name() . ' ' . $order_option->get_billing_last_name() ) ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </form>

                <div class="eop-pdf-admin__preview-body">
                    <?php if ( $preview_notice ) : ?>
                        <div class="eop-pdf-admin__notice eop-pdf-admin__notice--preview-mismatch">
                            <p><?php printf( esc_html__( 'O pedido selecionado para preview gera %1$s, mas voce esta editando as configuracoes de %2$s.', EOP_TEXT_DOMAIN ), esc_html( $preview_label ), esc_html( $editing_label ) ); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ( $preview_advanced_html_notice ) : ?>
                        <div class="eop-pdf-admin__notice">
                            <p><?php esc_html_e( 'Modo avancado de output HTML ativo. O preview lateral continua usando o renderer HTML interno para validacao visual.', EOP_TEXT_DOMAIN ); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ( $preview_allowed ) : ?>
                        <?php echo EOP_Document_Manager::get_preview_html( $preview_order, $preview_document ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php else : ?>
                        <div class="eop-pdf-admin__empty-preview">
                            <strong><?php esc_html_e( 'Nenhum pedido encontrado para preview.', EOP_TEXT_DOMAIN ); ?></strong>
                            <p><?php esc_html_e( 'Crie um pedido no Pedido Expresso para visualizar o PDF aqui.', EOP_TEXT_DOMAIN ); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>
</div>
