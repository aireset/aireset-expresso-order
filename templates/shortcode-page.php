<?php
defined( 'ABSPATH' ) || exit;

$settings             = EOP_Settings::get_all();
$font_css             = method_exists( 'EOP_Settings', 'get_font_css_family' ) ? EOP_Settings::get_font_css_family( $settings['font_family'] ) : "'Segoe UI', sans-serif";
$order_statuses       = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
$eop_is_preview_frame = class_exists( 'EOP_Admin_Page' ) && EOP_Admin_Page::is_preview_frame_request();
?>
<style>
    .eop-pdv {
        --eop-primary: <?php echo esc_attr( $settings['primary_color'] ); ?>;
        --eop-surface: <?php echo esc_attr( $settings['surface_color'] ); ?>;
        --eop-border: <?php echo esc_attr( $settings['border_color'] ); ?>;
        --eop-radius: <?php echo esc_attr( absint( $settings['border_radius'] ) ); ?>px;
        --eop-font-family: <?php echo esc_attr( $font_css ); ?>;
        font-family: <?php echo esc_attr( $font_css ); ?>;
    }
</style>

<?php if ( $eop_is_preview_frame && class_exists( 'EOP_Admin_Page' ) ) : ?>
    <?php EOP_Admin_Page::render_view_skin_css( isset( $_GET['preview_view'] ) ? wp_unslash( $_GET['preview_view'] ) : 'new-order' ); ?>
<?php endif; ?>

<div class="eop-pdv">
    <div class="eop-pdv-header">
        <div class="eop-pdv-header__content">
            <h1><?php echo esc_html( $settings['panel_title'] ); ?></h1>
            <?php if ( ! empty( $settings['panel_subtitle'] ) ) : ?>
                <p class="eop-pdv-subtitle"><?php echo esc_html( $settings['panel_subtitle'] ); ?></p>
            <?php endif; ?>

            <div class="eop-pdv-nav" role="tablist" aria-label="<?php esc_attr_e( 'Navegacao do pedido expresso', EOP_TEXT_DOMAIN ); ?>">
                <button type="button" class="eop-pdv-nav__item is-active" data-eop-view-target="new-order" aria-selected="true">
                    <?php esc_html_e( 'Novo pedido', EOP_TEXT_DOMAIN ); ?>
                </button>
                <button type="button" class="eop-pdv-nav__item" data-eop-view-target="orders" aria-selected="false">
                    <?php esc_html_e( 'Pedidos', EOP_TEXT_DOMAIN ); ?>
                </button>
            </div>
        </div>

        <!-- <span class="eop-pdv-user"><?php echo esc_html( wp_get_current_user()->display_name ); ?></span> -->
    </div>

    <div id="eop-notices"></div>

    <section class="eop-pdv-view is-active" data-eop-view="new-order">
        <input type="hidden" id="eop-edit-order-id" value="0" />

        <div class="eop-editing-banner" id="eop-editing-banner" hidden>
            <div>
                <strong id="eop-editing-title"><?php esc_html_e( 'Editando pedido', EOP_TEXT_DOMAIN ); ?></strong>
                <p><?php esc_html_e( 'Voce esta ajustando um pedido existente dentro do painel.', EOP_TEXT_DOMAIN ); ?></p>
            </div>
            <button type="button" class="eop-btn" id="eop-cancel-edit"><?php esc_html_e( 'Cancelar edicao', EOP_TEXT_DOMAIN ); ?></button>
        </div>

        <div class="eop-pdv-grid">
            <div class="eop-pdv-main">
                <div class="eop-card">
                    <h2><?php esc_html_e( 'Produtos', EOP_TEXT_DOMAIN ); ?></h2>
                    <div class="eop-item-defaults">
                        <div class="eop-item-defaults__title"><?php esc_html_e( 'Acoes em massa', EOP_TEXT_DOMAIN ); ?></div>
                        <div class="eop-field">
                            <label for="eop-default-item-quantity"><?php esc_html_e( 'Quantidade', EOP_TEXT_DOMAIN ); ?></label>
                            <input type="number" id="eop-default-item-quantity" min="1" step="1" value="1" inputmode="numeric" />
                        </div>
                        <div class="eop-field">
                            <label for="eop-default-item-discount"><?php esc_html_e( 'Desconto', EOP_TEXT_DOMAIN ); ?></label>
                            <div class="eop-item-discount-group eop-item-defaults__discount-group">
                                <input type="text" id="eop-default-item-discount" class="eop-discount-text-input" value="" placeholder="" inputmode="decimal" />
                                <span class="eop-item-discount-suffix" id="eop-default-item-discount-suffix" hidden></span>
                            </div>
                        </div>
                        <div class="eop-field eop-item-defaults__action">
                            <button type="button" id="eop-apply-item-defaults" class="eop-btn"><?php esc_html_e( 'Aplicar', EOP_TEXT_DOMAIN ); ?></button>
                        </div>
                    </div>
                    <div class="eop-field">
                        <select id="eop-product-search" style="width:100%"></select>
                    </div>

                    <div class="eop-items-list" id="eop-items-body">
                        <div class="eop-items-empty"><?php esc_html_e( 'Nenhum produto adicionado.', EOP_TEXT_DOMAIN ); ?></div>
                    </div>
                </div>
            </div>

            <div class="eop-pdv-sidebar">
                <div class="eop-card eop-accordion">
                    <button type="button" class="eop-accordion__toggle" aria-expanded="false">
                        <h2><?php esc_html_e( 'Cliente (opcional)', EOP_TEXT_DOMAIN ); ?></h2>
                        <span class="eop-accordion__icon" aria-hidden="true">+</span>
                    </button>
                    <div class="eop-accordion__body" hidden>
                        <div class="eop-field">
                            <label for="eop-document"><?php esc_html_e( 'CPF / CNPJ', EOP_TEXT_DOMAIN ); ?></label>
                            <div class="eop-input-group">
                                <input type="text" id="eop-document" placeholder="000.000.000-00" autocomplete="off" />
                                <button type="button" id="eop-search-customer" class="eop-btn"><?php esc_html_e( 'Buscar', EOP_TEXT_DOMAIN ); ?></button>
                            </div>
                            <span id="eop-customer-status" class="eop-status"></span>
                        </div>

                        <input type="hidden" id="eop-user-id" value="0" />

                        <div class="eop-field">
                            <label for="eop-name"><?php esc_html_e( 'Nome', EOP_TEXT_DOMAIN ); ?></label>
                            <input type="text" id="eop-name" />
                        </div>

                        <div class="eop-field">
                            <label for="eop-email"><?php esc_html_e( 'E-mail', EOP_TEXT_DOMAIN ); ?></label>
                            <input type="email" id="eop-email" />
                        </div>

                        <div class="eop-field">
                            <label for="eop-phone"><?php esc_html_e( 'WhatsApp', EOP_TEXT_DOMAIN ); ?></label>
                            <input type="tel" id="eop-phone" />
                        </div>
                    </div>
                </div>

                <div class="eop-card">
                    <div class="eop-accordion eop-totals-accordion">
                        <button type="button" class="eop-accordion__toggle eop-totals-detail-toggle" aria-expanded="false">
                            <span><?php esc_html_e( 'Ver detalhes de pagamento', EOP_TEXT_DOMAIN ); ?></span>
                            <span class="eop-accordion__icon" aria-hidden="true">+</span>
                        </button>
                        <div class="eop-accordion__body" hidden>
                            <div class="eop-shipping-box">
                                <button type="button" id="eop-shipping-toggle" class="eop-shipping-toggle" aria-expanded="false" aria-controls="eop-shipping-panel">
                                    <span class="eop-shipping-toggle__copy">
                                        <strong><?php esc_html_e( 'Entrega e frete', EOP_TEXT_DOMAIN ); ?></strong>
                                        <small id="eop-shipping-summary"><?php esc_html_e( 'Clique para calcular com o endereco do cliente.', EOP_TEXT_DOMAIN ); ?></small>
                                    </span>
                                    <span class="eop-shipping-toggle__icon" aria-hidden="true">+</span>
                                </button>

                                <div class="eop-shipping-panel" id="eop-shipping-panel" hidden>
                                    <div class="eop-shipping-panel__intro">
                                        <strong><?php esc_html_e( 'Como funciona', EOP_TEXT_DOMAIN ); ?></strong>
                                        <p><?php esc_html_e( 'Preencha o CEP para buscar endereco automaticamente, complete o numero e calcule as opcoes de frete.', EOP_TEXT_DOMAIN ); ?></p>
                                    </div>

                                    <div class="eop-shipping-panel__status" id="eop-shipping-address-status"></div>

                                    <div class="eop-field-row">
                                        <div class="eop-field">
                                            <label for="eop-shipping-postcode"><?php esc_html_e( 'CEP', EOP_TEXT_DOMAIN ); ?></label>
                                            <input type="text" id="eop-shipping-postcode" placeholder="00000-000" inputmode="numeric" />
                                        </div>
                                        <div class="eop-field">
                                            <label for="eop-shipping-state"><?php esc_html_e( 'Estado', EOP_TEXT_DOMAIN ); ?></label>
                                            <input type="text" id="eop-shipping-state" placeholder="SP" />
                                        </div>
                                    </div>

                                    <div class="eop-field-row">
                                        <div class="eop-field">
                                            <label for="eop-shipping-city"><?php esc_html_e( 'Cidade', EOP_TEXT_DOMAIN ); ?></label>
                                            <input type="text" id="eop-shipping-city" />
                                        </div>
                                        <div class="eop-field">
                                            <label for="eop-shipping-number"><?php esc_html_e( 'Numero', EOP_TEXT_DOMAIN ); ?></label>
                                            <input type="text" id="eop-shipping-number" />
                                        </div>
                                    </div>

                                    <div class="eop-field">
                                        <label for="eop-shipping-address"><?php esc_html_e( 'Endereco', EOP_TEXT_DOMAIN ); ?></label>
                                        <input type="text" id="eop-shipping-address" />
                                    </div>

                                    <div class="eop-field-row">
                                        <div class="eop-field">
                                            <label for="eop-shipping-neighborhood"><?php esc_html_e( 'Bairro', EOP_TEXT_DOMAIN ); ?></label>
                                            <input type="text" id="eop-shipping-neighborhood" />
                                        </div>
                                        <div class="eop-field">
                                            <label for="eop-shipping-address-2"><?php esc_html_e( 'Complemento', EOP_TEXT_DOMAIN ); ?></label>
                                            <input type="text" id="eop-shipping-address-2" />
                                        </div>
                                    </div>

                                    <div class="eop-field">
                                        <button type="button" id="eop-calc-shipping" class="eop-btn eop-btn-primary eop-btn-block"><?php esc_html_e( 'Buscar opcoes de frete', EOP_TEXT_DOMAIN ); ?></button>
                                    </div>

                                    <div id="eop-shipping-rates" class="eop-shipping-rates"></div>
                                </div>
                            </div>

                            <input type="hidden" id="eop-shipping" value="0" />

                            <div class="eop-field">
                                <label for="eop-discount"><?php esc_html_e( 'Desconto geral', EOP_TEXT_DOMAIN ); ?></label>
                                <input type="text" id="eop-discount" class="eop-discount-text-input" value="" placeholder="10% ou 10" inputmode="decimal" />
                            </div>

                            <div class="eop-totals">
                                <div class="eop-total-row">
                                    <span><?php esc_html_e( 'Subtotal:', EOP_TEXT_DOMAIN ); ?></span>
                                    <span id="eop-subtotal">R$ 0,00</span>
                                </div>
                                <div class="eop-total-row">
                                    <span><?php esc_html_e( 'Frete:', EOP_TEXT_DOMAIN ); ?></span>
                                    <span id="eop-shipping-total">R$ 0,00</span>
                                </div>
                                <div class="eop-total-row">
                                    <span><?php esc_html_e( 'Desconto:', EOP_TEXT_DOMAIN ); ?></span>
                                    <span id="eop-discount-total">- R$ 0,00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="eop-totals-grand-always">
                        <div class="eop-total-row eop-total-grand">
                            <span><?php esc_html_e( 'Total:', EOP_TEXT_DOMAIN ); ?></span>
                            <span id="eop-grand-total">R$ 0,00</span>
                        </div>
                    </div>
                </div>

                <div class="eop-card">
                    <div class="eop-field">
                        <label for="eop-status"><?php esc_html_e( 'Status', EOP_TEXT_DOMAIN ); ?></label>
                        <select id="eop-status">
                            <option value="completed"><?php esc_html_e( 'Concluído', EOP_TEXT_DOMAIN ); ?></option>
                            <option value="pending"><?php esc_html_e( 'Pendente', EOP_TEXT_DOMAIN ); ?></option>
                            <option value="processing"><?php esc_html_e( 'Processando', EOP_TEXT_DOMAIN ); ?></option>
                            <option value="on-hold"><?php esc_html_e( 'Aguardando', EOP_TEXT_DOMAIN ); ?></option>
                        </select>
                    </div>

                    <button type="button" id="eop-submit" class="eop-btn eop-btn-primary eop-btn-block">
                        <?php esc_html_e( 'Finalizar e Gerar PDF', EOP_TEXT_DOMAIN ); ?>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="eop-pdv-view" data-eop-view="orders" hidden>
        <div class="eop-orders-browser">
            <div class="eop-card eop-orders-browser__controls">
                <div class="eop-orders-browser__top">
                    <div>
                        <h2><?php esc_html_e( 'Pedidos criados', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Acompanhe propostas e pedidos sem sair da tela de vendas.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <button type="button" class="eop-btn" id="eop-orders-refresh"><?php esc_html_e( 'Atualizar', EOP_TEXT_DOMAIN ); ?></button>
                </div>

                <div class="eop-orders-browser__filters">
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
                </div>
            </div>

            <div class="eop-orders-browser__summary" id="eop-orders-summary"></div>
            <div class="eop-orders-browser__list" id="eop-orders-list"></div>
            <div class="eop-orders-browser__pagination" id="eop-orders-pagination"></div>
        </div>
    </section>

    <div id="eop-success-modal" class="eop-modal" style="display:none;">
        <div class="eop-modal-content">
            <h2><?php esc_html_e( 'Pedido Criado!', EOP_TEXT_DOMAIN ); ?></h2>
            <p id="eop-success-message"></p>
            <div class="eop-modal-actions">
                <a id="eop-pdf-link" href="#" target="_blank" class="eop-btn eop-btn-primary"><?php esc_html_e( 'Abrir PDF', EOP_TEXT_DOMAIN ); ?></a>
                <a id="eop-public-link" href="#" target="_blank" class="eop-btn" style="display:none;"><?php esc_html_e( 'Abrir link do cliente', EOP_TEXT_DOMAIN ); ?></a>
                <a id="eop-order-link" href="#" target="_blank" class="eop-btn"><?php esc_html_e( 'Ver Pedido', EOP_TEXT_DOMAIN ); ?></a>
                <button type="button" id="eop-new-order" class="eop-btn"><?php esc_html_e( 'Novo Pedido', EOP_TEXT_DOMAIN ); ?></button>
            </div>
        </div>
    </div>
</div>
