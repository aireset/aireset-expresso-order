<?php
defined( 'ABSPATH' ) || exit;

$order_statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
?>
<section class="eop-pdv-view is-active" data-eop-view="orders" data-eop-lazy="true" data-eop-lazy-loaded="true">
    <div class="eop-admin-view-header">
        <div class="eop-admin-view-copy">
            <span class="eop-admin-view-kicker"><?php esc_html_e( 'Gestao comercial', EOP_TEXT_DOMAIN ); ?></span>
            <div class="eop-admin-view-title-row">
                <h2 class="eop-admin-view-title">
                    <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                    <span><?php esc_html_e( 'Pedidos', EOP_TEXT_DOMAIN ); ?></span>
                </h2>
            </div>
            <p class="eop-admin-view-desc"><?php esc_html_e( 'Acompanhe pedidos e propostas da equipe comercial com os atalhos principais do fluxo.', EOP_TEXT_DOMAIN ); ?></p>
        </div>
    </div>

    <div class="eop-admin-view-main">
        <div class="eop-orders-browser">
            <div class="eop-card eop-orders-browser__controls">
                <div class="eop-orders-browser__top">
                    <div>
                        <h2><?php esc_html_e( 'Pedidos criados', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Acompanhe propostas e pedidos sem sair da tela de vendas.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <button type="button" class="eop-btn" id="eop-orders-refresh"><?php esc_html_e( 'Atualizar', EOP_TEXT_DOMAIN ); ?></button>
                </div>

                <form class="eop-orders-browser__filters" id="eop-orders-filters-form">
                    <div class="eop-field">
                        <label for="eop-orders-search"><?php esc_html_e( 'Buscar', EOP_TEXT_DOMAIN ); ?></label>
                        <input type="search" id="eop-orders-search" placeholder="<?php esc_attr_e( 'Pedido, cliente ou e-mail', EOP_TEXT_DOMAIN ); ?>" />
                    </div>
                    <div class="eop-field">
                        <label for="eop-orders-status-filter"><?php esc_html_e( 'Status', EOP_TEXT_DOMAIN ); ?></label>
                        <select id="eop-orders-status-filter">
                            <option value="any"><?php esc_html_e( 'Todos', EOP_TEXT_DOMAIN ); ?></option>
                            <?php foreach ( $order_statuses as $status_key => $status_label ) : ?>
                                <option value="<?php echo esc_attr( str_replace( 'wc-', '', $status_key ) ); ?>"><?php echo esc_html( $status_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="eop-field">
                        <label for="eop-orders-flow-filter"><?php esc_html_e( 'Fluxo complementar', EOP_TEXT_DOMAIN ); ?></label>
                        <select id="eop-orders-flow-filter">
                            <option value="any"><?php esc_html_e( 'Todos', EOP_TEXT_DOMAIN ); ?></option>
                            <option value="active"><?php esc_html_e( 'Com fluxo ativo', EOP_TEXT_DOMAIN ); ?></option>
                            <option value="pending"><?php esc_html_e( 'Pendentes', EOP_TEXT_DOMAIN ); ?></option>
                            <option value="completed"><?php esc_html_e( 'Concluidos', EOP_TEXT_DOMAIN ); ?></option>
                        </select>
                    </div>
                    <button type="submit" class="screen-reader-text" hidden><?php esc_html_e( 'Filtrar pedidos', EOP_TEXT_DOMAIN ); ?></button>
                </form>
            </div>

            <div class="eop-orders-browser__summary" id="eop-orders-summary"></div>
            <div class="eop-orders-browser__list" id="eop-orders-list"></div>
            <div class="eop-orders-browser__pagination" id="eop-orders-pagination"></div>
        </div>
    </div>
</section>
