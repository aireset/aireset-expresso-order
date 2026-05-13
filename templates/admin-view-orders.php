<?php
defined( 'ABSPATH' ) || exit;

$order_statuses         = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
$eop_orders_labels      = class_exists( 'EOP_Admin_Page' ) ? EOP_Admin_Page::get_orders_list_view_labels() : array();
$eop_is_preview_frame   = class_exists( 'EOP_Admin_Page' ) && EOP_Admin_Page::is_preview_frame_request( 'orders' );
?>
<section class="eop-pdv-view is-active" data-eop-view="orders" data-eop-lazy="true" data-eop-lazy-loaded="true">
    <div class="eop-admin-view-header">
        <div class="eop-admin-view-copy">
            <span class="eop-admin-view-kicker"><?php echo esc_html( $eop_orders_labels['kicker'] ?? __( 'Gestao comercial', EOP_TEXT_DOMAIN ) ); ?></span>
            <div class="eop-admin-view-title-row">
                <h2 class="eop-admin-view-title">
                    <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                    <span><?php echo esc_html( $eop_orders_labels['title'] ?? __( 'Pedidos', EOP_TEXT_DOMAIN ) ); ?></span>
                </h2>
            </div>
            <p class="eop-admin-view-desc"><?php echo esc_html( $eop_orders_labels['description'] ?? __( 'Acompanhe pedidos e propostas da equipe comercial com os atalhos principais do fluxo.', EOP_TEXT_DOMAIN ) ); ?></p>
        </div>
    </div>

    <div class="eop-admin-view-main">
        <div class="eop-orders-browser">
            <div class="eop-card eop-orders-browser__controls">
                <div class="eop-orders-browser__top">
                    <div>
                        <h2><?php echo esc_html( $eop_orders_labels['panel_title'] ?? __( 'Pedidos criados', EOP_TEXT_DOMAIN ) ); ?></h2>
                        <p><?php echo esc_html( $eop_orders_labels['panel_description'] ?? __( 'Acompanhe propostas e pedidos sem sair da tela de vendas.', EOP_TEXT_DOMAIN ) ); ?></p>
                    </div>
                    <button type="button" class="eop-btn" id="eop-orders-refresh"><?php echo esc_html( $eop_orders_labels['refresh_label'] ?? __( 'Atualizar', EOP_TEXT_DOMAIN ) ); ?></button>
                </div>

                <form class="eop-orders-browser__filters" id="eop-orders-filters-form">
                    <div class="eop-field">
                        <label for="eop-orders-search"><?php esc_html_e( 'Buscar', EOP_TEXT_DOMAIN ); ?></label>
                        <input type="search" id="eop-orders-search" placeholder="<?php esc_attr_e( 'Pedido, cliente ou e-mail', EOP_TEXT_DOMAIN ); ?>" value="<?php echo esc_attr( $eop_is_preview_frame ? 'Camila' : '' ); ?>" />
                    </div>
                    <div class="eop-field">
                        <label for="eop-orders-status-filter"><?php esc_html_e( 'Status', EOP_TEXT_DOMAIN ); ?></label>
                        <select id="eop-orders-status-filter">
                            <option value="any"><?php esc_html_e( 'Todos', EOP_TEXT_DOMAIN ); ?></option>
                            <?php foreach ( $order_statuses as $status_key => $status_label ) : ?>
                                <option value="<?php echo esc_attr( str_replace( 'wc-', '', $status_key ) ); ?>"<?php selected( $eop_is_preview_frame && 'wc-processing' === $status_key ); ?>><?php echo esc_html( $status_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="eop-field">
                        <label for="eop-orders-flow-filter"><?php esc_html_e( 'Fluxo complementar', EOP_TEXT_DOMAIN ); ?></label>
                        <select id="eop-orders-flow-filter">
                            <option value="any"><?php esc_html_e( 'Todos', EOP_TEXT_DOMAIN ); ?></option>
                            <option value="active"<?php selected( $eop_is_preview_frame, true ); ?>><?php esc_html_e( 'Com fluxo ativo', EOP_TEXT_DOMAIN ); ?></option>
                            <option value="pending"><?php esc_html_e( 'Pendentes', EOP_TEXT_DOMAIN ); ?></option>
                            <option value="completed"><?php esc_html_e( 'Concluidos', EOP_TEXT_DOMAIN ); ?></option>
                        </select>
                    </div>
                    <button type="submit" class="screen-reader-text" hidden><?php esc_html_e( 'Filtrar pedidos', EOP_TEXT_DOMAIN ); ?></button>
                </form>
            </div>

            <div class="eop-orders-browser__summary" id="eop-orders-summary">
                <?php if ( $eop_is_preview_frame ) : ?>
                    <div class="eop-orders-summary__card">
                        <strong><?php esc_html_e( 'Todos os pedidos expresso', EOP_TEXT_DOMAIN ); ?></strong>
                        <span><?php esc_html_e( '2 pedido(s) encontrado(s)', EOP_TEXT_DOMAIN ); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="eop-orders-browser__list" id="eop-orders-list">
                <?php if ( $eop_is_preview_frame ) : ?>
                    <article class="eop-card eop-order-card">
                        <div class="eop-order-card__header">
                            <div>
                                <div class="eop-order-card__number">#1024</div>
                                <h3 class="eop-order-card__customer-name"><?php esc_html_e( 'Camila Santos', EOP_TEXT_DOMAIN ); ?></h3>
                                <p class="eop-order-card__email">camila@exemplo.com</p>
                            </div>
                            <span class="eop-order-card__status status-processing"><?php esc_html_e( 'Processando', EOP_TEXT_DOMAIN ); ?></span>
                        </div>
                        <div class="eop-order-card__meta">
                            <div><span><?php esc_html_e( 'Data', EOP_TEXT_DOMAIN ); ?></span><strong>09/05/2026</strong></div>
                            <div><span><?php esc_html_e( 'Total', EOP_TEXT_DOMAIN ); ?></span><strong>R$ 393,70</strong></div>
                            <div><span><?php esc_html_e( 'Vendedor', EOP_TEXT_DOMAIN ); ?></span><strong><?php esc_html_e( 'Equipe Aireset', EOP_TEXT_DOMAIN ); ?></strong></div>
                        </div>
                        <div class="eop-order-card__flow">
                            <div class="eop-order-card__flow-head">
                                <span><?php esc_html_e( 'Fluxo complementar', EOP_TEXT_DOMAIN ); ?></span>
                                <strong class="eop-order-card__flow-stage"><?php esc_html_e( 'Contrato aprovado', EOP_TEXT_DOMAIN ); ?></strong>
                            </div>
                            <div class="eop-order-card__flow-list">
                                <span class="eop-order-card__flow-pill eop-order-card__flow-pill--success"><span class="eop-order-card__flow-pill-label"><?php esc_html_e( 'Contrato', EOP_TEXT_DOMAIN ); ?></span> <strong class="eop-order-card__flow-pill-value"><?php esc_html_e( 'Aceito', EOP_TEXT_DOMAIN ); ?></strong></span>
                                <span class="eop-order-card__flow-pill eop-order-card__flow-pill--info"><span class="eop-order-card__flow-pill-label"><?php esc_html_e( 'Campos', EOP_TEXT_DOMAIN ); ?></span> <strong class="eop-order-card__flow-pill-value">2/3</strong></span>
                                <span class="eop-order-card__flow-pill eop-order-card__flow-pill--success"><span class="eop-order-card__flow-pill-label"><?php esc_html_e( 'Anexo', EOP_TEXT_DOMAIN ); ?></span> <strong class="eop-order-card__flow-pill-value"><?php esc_html_e( 'Enviado', EOP_TEXT_DOMAIN ); ?></strong></span>
                                <span class="eop-order-card__flow-pill eop-order-card__flow-pill--warning"><span class="eop-order-card__flow-pill-label"><?php esc_html_e( 'PDF final', EOP_TEXT_DOMAIN ); ?></span> <strong class="eop-order-card__flow-pill-value"><?php esc_html_e( 'Pendente', EOP_TEXT_DOMAIN ); ?></strong></span>
                                <span class="eop-order-card__flow-pill eop-order-card__flow-pill--info"><span class="eop-order-card__flow-pill-label"><?php esc_html_e( 'Produtos', EOP_TEXT_DOMAIN ); ?></span> <strong class="eop-order-card__flow-pill-value">2/3</strong></span>
                            </div>
                        </div>
                        <div class="eop-order-card__actions">
                            <a class="eop-btn eop-btn-primary" href="#" target="_blank" rel="noopener"><?php esc_html_e( 'Link do cliente', EOP_TEXT_DOMAIN ); ?></a>
                            <a class="eop-btn eop-btn-primary" href="#" target="_blank" rel="noopener"><?php esc_html_e( 'PDF', EOP_TEXT_DOMAIN ); ?></a>
                            <button type="button" class="eop-btn eop-btn-primary eop-order-edit-spa" data-order-id="1024"><?php esc_html_e( 'Editar aqui', EOP_TEXT_DOMAIN ); ?></button>
                        </div>
                    </article>
                    <article class="eop-card eop-order-card">
                        <div class="eop-order-card__header">
                            <div>
                                <div class="eop-order-card__number">#1021</div>
                                <h3 class="eop-order-card__customer-name"><?php esc_html_e( 'Luciana Prado', EOP_TEXT_DOMAIN ); ?></h3>
                                <p class="eop-order-card__email">luciana@exemplo.com</p>
                            </div>
                            <span class="eop-order-card__status status-completed"><?php esc_html_e( 'Concluido', EOP_TEXT_DOMAIN ); ?></span>
                        </div>
                        <div class="eop-order-card__meta">
                            <div><span><?php esc_html_e( 'Data', EOP_TEXT_DOMAIN ); ?></span><strong>08/05/2026</strong></div>
                            <div><span><?php esc_html_e( 'Total', EOP_TEXT_DOMAIN ); ?></span><strong>R$ 218,00</strong></div>
                            <div><span><?php esc_html_e( 'Vendedor', EOP_TEXT_DOMAIN ); ?></span><strong><?php esc_html_e( 'Equipe Aireset', EOP_TEXT_DOMAIN ); ?></strong></div>
                        </div>
                        <div class="eop-order-card__actions">
                            <a class="eop-btn eop-btn-primary" href="#" target="_blank" rel="noopener"><?php esc_html_e( 'Link do cliente', EOP_TEXT_DOMAIN ); ?></a>
                            <a class="eop-btn eop-btn-primary" href="#" target="_blank" rel="noopener"><?php esc_html_e( 'PDF', EOP_TEXT_DOMAIN ); ?></a>
                            <button type="button" class="eop-btn eop-btn-primary eop-order-edit-spa" data-order-id="1021"><?php esc_html_e( 'Editar aqui', EOP_TEXT_DOMAIN ); ?></button>
                        </div>
                    </article>
                <?php endif; ?>
            </div>
            <div class="eop-orders-browser__pagination" id="eop-orders-pagination">
                <?php if ( $eop_is_preview_frame ) : ?>
                    <button type="button" class="eop-btn eop-orders-page-btn" disabled><?php esc_html_e( 'Anterior', EOP_TEXT_DOMAIN ); ?></button>
                    <span class="eop-orders-page-indicator"><?php esc_html_e( 'Página 1 de 1', EOP_TEXT_DOMAIN ); ?></span>
                    <button type="button" class="eop-btn eop-orders-page-btn" disabled><?php esc_html_e( 'Proxima', EOP_TEXT_DOMAIN ); ?></button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
