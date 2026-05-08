<?php
defined( 'ABSPATH' ) || exit;
?>
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
