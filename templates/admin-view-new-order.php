<?php
defined( 'ABSPATH' ) || exit;

$eop_new_order_labels = class_exists( 'EOP_Admin_Page' ) ? EOP_Admin_Page::get_new_order_view_labels() : array();
$eop_is_preview_frame = class_exists( 'EOP_Admin_Page' ) && EOP_Admin_Page::is_preview_frame_request( 'new-order' );
?>
<section class="eop-pdv-view is-active" data-eop-view="new-order" data-eop-lazy="true" data-eop-lazy-loaded="true">
    <input type="hidden" id="eop-edit-order-id" value="0" />

    <div class="eop-admin-view-header">
        <div class="eop-admin-view-copy">
            <span class="eop-admin-view-kicker"><?php echo esc_html( $eop_new_order_labels['kicker'] ?? __( 'Operacao comercial', EOP_TEXT_DOMAIN ) ); ?></span>
            <div class="eop-admin-view-title-row">
                <h2 class="eop-admin-view-title">
                    <span class="dashicons dashicons-cart" aria-hidden="true"></span>
                    <span><?php echo esc_html( $eop_new_order_labels['title'] ?? __( 'Novo pedido', EOP_TEXT_DOMAIN ) ); ?></span>
                </h2>
            </div>
            <p class="eop-admin-view-desc"><?php echo esc_html( $eop_new_order_labels['description'] ?? __( 'Monte o pedido, ajuste cliente, frete e descontos sem sair do fluxo principal do painel.', EOP_TEXT_DOMAIN ) ); ?></p>
        </div>
    </div>

    <div class="eop-admin-view-main">
        <div class="eop-editing-banner" id="eop-editing-banner"<?php echo $eop_is_preview_frame ? '' : ' hidden'; ?>>
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
                            <input type="number" id="eop-default-item-quantity" min="1" step="1" value="<?php echo esc_attr( $eop_is_preview_frame ? '2' : '1' ); ?>" inputmode="numeric" />
                        </div>
                        <div class="eop-field">
                            <label for="eop-default-item-discount"><?php esc_html_e( 'Desconto', EOP_TEXT_DOMAIN ); ?></label>
                            <div class="eop-item-discount-group eop-item-defaults__discount-group">
                                <input type="text" id="eop-default-item-discount" class="eop-discount-text-input" value="<?php echo esc_attr( $eop_is_preview_frame ? '10%' : '' ); ?>" placeholder="" inputmode="decimal" />
                                <span class="eop-item-discount-suffix" id="eop-default-item-discount-suffix"<?php echo $eop_is_preview_frame ? '' : ' hidden'; ?>>%</span>
                            </div>
                        </div>
                        <div class="eop-field eop-item-defaults__action">
                            <button type="button" id="eop-apply-item-defaults" class="eop-btn"><?php echo esc_html( $eop_new_order_labels['mass_apply_label'] ?? __( 'Aplicar', EOP_TEXT_DOMAIN ) ); ?></button>
                        </div>
                    </div>
                    <div class="eop-field">
                        <select id="eop-product-search" style="width:100%">
                            <?php if ( $eop_is_preview_frame ) : ?>
                                <option><?php esc_html_e( 'Kit skincare premium — SKU SK-203', EOP_TEXT_DOMAIN ); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="eop-items-list" id="eop-items-body">
                        <?php if ( $eop_is_preview_frame ) : ?>
                            <div class="eop-item-row">
                                <strong><?php esc_html_e( 'Kit skincare premium', EOP_TEXT_DOMAIN ); ?></strong>
                                <span><?php esc_html_e( '2 unidades • SKU SK-203', EOP_TEXT_DOMAIN ); ?></span>
                            </div>
                            <div class="eop-item-row">
                                <strong><?php esc_html_e( 'Bruma revitalizante', EOP_TEXT_DOMAIN ); ?></strong>
                                <span><?php esc_html_e( '1 unidade • SKU BR-018', EOP_TEXT_DOMAIN ); ?></span>
                            </div>
                        <?php else : ?>
                            <div class="eop-items-empty"><?php esc_html_e( 'Nenhum produto adicionado.', EOP_TEXT_DOMAIN ); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="eop-pdv-sidebar">
                <div class="eop-card eop-accordion">
                    <button type="button" class="eop-accordion__toggle" aria-expanded="<?php echo $eop_is_preview_frame ? 'true' : 'false'; ?>">
                        <h2><?php esc_html_e( 'Cliente (opcional)', EOP_TEXT_DOMAIN ); ?></h2>
                        <span class="eop-accordion__icon" aria-hidden="true">+</span>
                    </button>
                    <div class="eop-accordion__body"<?php echo $eop_is_preview_frame ? '' : ' hidden'; ?>>
                        <div class="eop-field">
                            <label for="eop-document"><?php esc_html_e( 'CPF / CNPJ', EOP_TEXT_DOMAIN ); ?></label>
                            <div class="eop-input-group">
                                <input type="text" id="eop-document" placeholder="000.000.000-00" autocomplete="off" value="<?php echo esc_attr( $eop_is_preview_frame ? '123.456.789-00' : '' ); ?>" />
                                <button type="button" id="eop-search-customer" class="eop-btn"><?php esc_html_e( 'Buscar', EOP_TEXT_DOMAIN ); ?></button>
                            </div>
                            <span id="eop-customer-status" class="eop-status"><?php echo esc_html( $eop_is_preview_frame ? __( 'Cliente encontrado e preenchido.', EOP_TEXT_DOMAIN ) : '' ); ?></span>
                        </div>

                        <input type="hidden" id="eop-user-id" value="<?php echo esc_attr( $eop_is_preview_frame ? '245' : '0' ); ?>" />

                        <div class="eop-field">
                            <label for="eop-name"><?php esc_html_e( 'Nome', EOP_TEXT_DOMAIN ); ?></label>
                            <input type="text" id="eop-name" value="<?php echo esc_attr( $eop_is_preview_frame ? 'Camila Santos' : '' ); ?>" />
                        </div>

                        <div class="eop-field">
                            <label for="eop-email"><?php esc_html_e( 'E-mail', EOP_TEXT_DOMAIN ); ?></label>
                            <input type="email" id="eop-email" value="<?php echo esc_attr( $eop_is_preview_frame ? 'camila@exemplo.com' : '' ); ?>" />
                        </div>

                        <div class="eop-field">
                            <label for="eop-phone"><?php esc_html_e( 'WhatsApp', EOP_TEXT_DOMAIN ); ?></label>
                            <input type="tel" id="eop-phone" value="<?php echo esc_attr( $eop_is_preview_frame ? '(11) 98888-7777' : '' ); ?>" />
                        </div>
                    </div>
                </div>

                <div class="eop-card">
                    <div class="eop-accordion eop-totals-accordion">
                        <button type="button" class="eop-accordion__toggle eop-totals-detail-toggle" aria-expanded="<?php echo $eop_is_preview_frame ? 'true' : 'false'; ?>">
                            <span><?php esc_html_e( 'Ver detalhes de pagamento', EOP_TEXT_DOMAIN ); ?></span>
                            <span class="eop-accordion__icon" aria-hidden="true">+</span>
                        </button>
                        <div class="eop-accordion__body"<?php echo $eop_is_preview_frame ? '' : ' hidden'; ?>>
                            <div class="eop-shipping-box">
                                <button type="button" id="eop-shipping-toggle" class="eop-shipping-toggle" aria-expanded="<?php echo $eop_is_preview_frame ? 'true' : 'false'; ?>" aria-controls="eop-shipping-panel">
                                    <span class="eop-shipping-toggle__copy">
                                        <strong><?php esc_html_e( 'Entrega e frete', EOP_TEXT_DOMAIN ); ?></strong>
                                        <small id="eop-shipping-summary"><?php echo esc_html( $eop_is_preview_frame ? __( 'PAC • 3 a 5 dias uteis • R$ 18,90', EOP_TEXT_DOMAIN ) : __( 'Clique para calcular com o endereco do cliente.', EOP_TEXT_DOMAIN ) ); ?></small>
                                    </span>
                                    <span class="eop-shipping-toggle__icon" aria-hidden="true">+</span>
                                </button>

                                <div class="eop-shipping-panel" id="eop-shipping-panel"<?php echo $eop_is_preview_frame ? '' : ' hidden'; ?>>
                                    <div class="eop-shipping-panel__intro">
                                        <strong><?php esc_html_e( 'Como funciona', EOP_TEXT_DOMAIN ); ?></strong>
                                        <p><?php esc_html_e( 'Preencha o CEP para buscar endereco automaticamente, complete o numero e calcule as opcoes de frete.', EOP_TEXT_DOMAIN ); ?></p>
                                    </div>

                                    <div class="eop-shipping-panel__status" id="eop-shipping-address-status"><?php echo esc_html( $eop_is_preview_frame ? __( 'Endereco validado com sucesso.', EOP_TEXT_DOMAIN ) : '' ); ?></div>

                                    <div class="eop-field-row">
                                        <div class="eop-field">
                                            <label for="eop-shipping-postcode"><?php esc_html_e( 'CEP', EOP_TEXT_DOMAIN ); ?></label>
                                            <input type="text" id="eop-shipping-postcode" placeholder="00000-000" inputmode="numeric" value="<?php echo esc_attr( $eop_is_preview_frame ? '01310-100' : '' ); ?>" />
                                        </div>
                                        <div class="eop-field">
                                            <label for="eop-shipping-state"><?php esc_html_e( 'Estado', EOP_TEXT_DOMAIN ); ?></label>
                                            <input type="text" id="eop-shipping-state" placeholder="SP" value="<?php echo esc_attr( $eop_is_preview_frame ? 'SP' : '' ); ?>" />
                                        </div>
                                    </div>

                                    <div class="eop-field-row">
                                        <div class="eop-field">
                                            <label for="eop-shipping-city"><?php esc_html_e( 'Cidade', EOP_TEXT_DOMAIN ); ?></label>
                                            <input type="text" id="eop-shipping-city" value="<?php echo esc_attr( $eop_is_preview_frame ? 'Sao Paulo' : '' ); ?>" />
                                        </div>
                                        <div class="eop-field">
                                            <label for="eop-shipping-number"><?php esc_html_e( 'Numero', EOP_TEXT_DOMAIN ); ?></label>
                                            <input type="text" id="eop-shipping-number" value="<?php echo esc_attr( $eop_is_preview_frame ? '245' : '' ); ?>" />
                                        </div>
                                    </div>

                                    <div class="eop-field">
                                        <label for="eop-shipping-address"><?php esc_html_e( 'Endereco', EOP_TEXT_DOMAIN ); ?></label>
                                        <input type="text" id="eop-shipping-address" value="<?php echo esc_attr( $eop_is_preview_frame ? 'Avenida Paulista' : '' ); ?>" />
                                    </div>

                                    <div class="eop-field-row">
                                        <div class="eop-field">
                                            <label for="eop-shipping-neighborhood"><?php esc_html_e( 'Bairro', EOP_TEXT_DOMAIN ); ?></label>
                                            <input type="text" id="eop-shipping-neighborhood" value="<?php echo esc_attr( $eop_is_preview_frame ? 'Bela Vista' : '' ); ?>" />
                                        </div>
                                        <div class="eop-field">
                                            <label for="eop-shipping-address-2"><?php esc_html_e( 'Complemento', EOP_TEXT_DOMAIN ); ?></label>
                                            <input type="text" id="eop-shipping-address-2" value="<?php echo esc_attr( $eop_is_preview_frame ? 'Conjunto 18' : '' ); ?>" />
                                        </div>
                                    </div>

                                    <div class="eop-field">
                                        <button type="button" id="eop-calc-shipping" class="eop-btn eop-btn-primary eop-btn-block"><?php echo esc_html( $eop_new_order_labels['shipping_button_label'] ?? __( 'Buscar opcoes de frete', EOP_TEXT_DOMAIN ) ); ?></button>
                                    </div>

                                    <div id="eop-shipping-rates" class="eop-shipping-rates"><?php echo $eop_is_preview_frame ? '<div class="eop-card"><strong>' . esc_html__( 'PAC', EOP_TEXT_DOMAIN ) . '</strong><span> R$ 18,90</span></div>' : ''; ?></div>
                                </div>
                            </div>

                            <input type="hidden" id="eop-shipping" value="<?php echo esc_attr( $eop_is_preview_frame ? '18.90' : '0' ); ?>" />

                            <div class="eop-field">
                                <label for="eop-discount"><?php esc_html_e( 'Desconto geral', EOP_TEXT_DOMAIN ); ?></label>
                                <input type="text" id="eop-discount" class="eop-discount-text-input" value="<?php echo esc_attr( $eop_is_preview_frame ? '15' : '' ); ?>" placeholder="10% ou 10" inputmode="decimal" />
                            </div>

                            <div class="eop-totals">
                                <div class="eop-total-row">
                                    <span><?php esc_html_e( 'Subtotal:', EOP_TEXT_DOMAIN ); ?></span>
                                    <span id="eop-subtotal"><?php echo esc_html( $eop_is_preview_frame ? 'R$ 389,80' : 'R$ 0,00' ); ?></span>
                                </div>
                                <div class="eop-total-row">
                                    <span><?php esc_html_e( 'Frete:', EOP_TEXT_DOMAIN ); ?></span>
                                    <span id="eop-shipping-total"><?php echo esc_html( $eop_is_preview_frame ? 'R$ 18,90' : 'R$ 0,00' ); ?></span>
                                </div>
                                <div class="eop-total-row">
                                    <span><?php esc_html_e( 'Desconto:', EOP_TEXT_DOMAIN ); ?></span>
                                    <span id="eop-discount-total"><?php echo esc_html( $eop_is_preview_frame ? '- R$ 15,00' : '- R$ 0,00' ); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="eop-totals-grand-always">
                        <div class="eop-total-row eop-total-grand">
                            <span><?php esc_html_e( 'Total:', EOP_TEXT_DOMAIN ); ?></span>
                            <span id="eop-grand-total"><?php echo esc_html( $eop_is_preview_frame ? 'R$ 393,70' : 'R$ 0,00' ); ?></span>
                        </div>
                    </div>
                </div>

                <div class="eop-card">
                    <div class="eop-field">
                        <label for="eop-status"><?php esc_html_e( 'Status', EOP_TEXT_DOMAIN ); ?></label>
                        <select id="eop-status">
                            <option value="completed"<?php selected( $eop_is_preview_frame, true ); ?>><?php esc_html_e( 'Concluido', EOP_TEXT_DOMAIN ); ?></option>
                            <option value="pending"><?php esc_html_e( 'Pendente', EOP_TEXT_DOMAIN ); ?></option>
                            <option value="processing"><?php esc_html_e( 'Processando', EOP_TEXT_DOMAIN ); ?></option>
                            <option value="on-hold"><?php esc_html_e( 'Aguardando', EOP_TEXT_DOMAIN ); ?></option>
                        </select>
                    </div>

                    <button type="button" id="eop-submit" class="eop-btn eop-btn-primary eop-btn-block">
                        <?php echo esc_html( $eop_new_order_labels['submit_label'] ?? __( 'Finalizar e Gerar PDF', EOP_TEXT_DOMAIN ) ); ?>
                    </button>
                </div>

                <div class="eop-card eop-post-flow-card" id="eop-post-flow-card">
                    <div class="eop-post-flow-card__head">
                        <div>
                            <h2><?php esc_html_e( 'Fluxo complementar do cliente', EOP_TEXT_DOMAIN ); ?></h2>
                            <p id="eop-post-flow-subtitle"><?php echo esc_html( $eop_is_preview_frame ? __( 'Exemplo preenchido para revisar visual, hierarquia e contraste da tela real.', EOP_TEXT_DOMAIN ) : __( 'O resumo complementar aparece quando um pedido existente entra em modo de edicao.', EOP_TEXT_DOMAIN ) ); ?></p>
                        </div>
                        <span class="eop-post-flow-badge <?php echo esc_attr( $eop_is_preview_frame ? 'is-active' : 'is-inactive' ); ?>" id="eop-post-flow-badge"><?php echo esc_html( $eop_is_preview_frame ? __( 'Ativo', EOP_TEXT_DOMAIN ) : __( 'Inativo', EOP_TEXT_DOMAIN ) ); ?></span>
                    </div>

                    <div class="eop-post-flow-card__toolbar" id="eop-post-flow-stage-toolbar"<?php echo $eop_is_preview_frame ? '' : ' hidden'; ?>>
                        <div class="eop-post-flow-stage-form eop-post-flow-stage-form--inline">
                            <div class="eop-post-flow-stage-field">
                                <label for="eop-post-flow-stage-select"><?php esc_html_e( 'Etapa do fluxo', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop-post-flow-stage-select"<?php echo $eop_is_preview_frame ? '' : ' disabled'; ?>>
                                    <?php if ( $eop_is_preview_frame ) : ?>
                                        <option><?php esc_html_e( 'Contrato aprovado', EOP_TEXT_DOMAIN ); ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <button type="button" class="eop-btn eop-btn-secondary" id="eop-post-flow-stage-apply"<?php echo $eop_is_preview_frame ? '' : ' disabled'; ?>><?php esc_html_e( 'Atualizar etapa', EOP_TEXT_DOMAIN ); ?></button>
                        </div>
                        <p class="eop-post-flow-stage-hint" id="eop-post-flow-stage-hint"><?php esc_html_e( 'Use Automatico para voltar ao fluxo calculado pelo sistema.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>

                    <div class="eop-post-flow-card__stats" id="eop-post-flow-stats"><?php echo $eop_is_preview_frame ? '<span class="eop-status">Contrato aceito</span><span class="eop-status">1 documento pendente</span>' : ''; ?></div>

                    <div class="eop-post-flow-card__section">
                        <h3><?php esc_html_e( 'Contrato', EOP_TEXT_DOMAIN ); ?></h3>
                        <p id="eop-post-flow-contract"><?php echo esc_html( $eop_is_preview_frame ? __( 'Aceite registrado em 09/05/2026 às 14:37.', EOP_TEXT_DOMAIN ) : __( 'Nenhum aceite registrado.', EOP_TEXT_DOMAIN ) ); ?></p>
                    </div>

                    <div class="eop-post-flow-card__section">
                        <h3><?php esc_html_e( 'Documentos para assinatura', EOP_TEXT_DOMAIN ); ?></h3>
                        <div class="eop-post-flow-list" id="eop-post-flow-signature-documents"><?php echo $eop_is_preview_frame ? '<div class="eop-status">Contrato principal</div><div class="eop-status">Termo de personalizacao</div>' : ''; ?></div>
                    </div>

                    <div class="eop-post-flow-card__section">
                        <h3><?php esc_html_e( 'Dados do pedido', EOP_TEXT_DOMAIN ); ?></h3>
                        <div class="eop-post-flow-list" id="eop-post-flow-order-data"><?php echo $eop_is_preview_frame ? '<div class="eop-status">Canal: WhatsApp</div><div class="eop-status">Vendedor: Equipe Aireset</div>' : ''; ?></div>
                    </div>

                    <div class="eop-post-flow-card__section">
                        <h3><?php esc_html_e( 'Anexo', EOP_TEXT_DOMAIN ); ?></h3>
                        <div class="eop-post-flow-list" id="eop-post-flow-attachment"><?php echo $eop_is_preview_frame ? '<div class="eop-status">Moodboard aprovado</div>' : ''; ?></div>
                    </div>

                    <div class="eop-post-flow-card__section">
                        <h3><?php esc_html_e( 'Produtos', EOP_TEXT_DOMAIN ); ?></h3>
                        <div class="eop-post-flow-list" id="eop-post-flow-products"><?php echo $eop_is_preview_frame ? '<div class="eop-status">2 de 3 produtos personalizados</div>' : ''; ?></div>
                    </div>

                    <div class="eop-post-flow-card__section">
                        <h3><?php esc_html_e( 'Downloads e links', EOP_TEXT_DOMAIN ); ?></h3>
                        <div class="eop-post-flow-list" id="eop-post-flow-downloads"><?php echo $eop_is_preview_frame ? '<div class="eop-status">PDF complementar pronto</div>' : ''; ?></div>
                    </div>

                    <div class="eop-post-flow-card__actions">
                        <a class="eop-btn" id="eop-post-flow-public-link" href="#" target="_blank" rel="noopener"<?php echo $eop_is_preview_frame ? '' : ' hidden'; ?>><?php esc_html_e( 'Abrir link publico', EOP_TEXT_DOMAIN ); ?></a>
                        <a class="eop-btn eop-btn-secondary" id="eop-post-flow-pdf-link" href="#" target="_blank" rel="noopener"<?php echo $eop_is_preview_frame ? '' : ' hidden'; ?>><?php esc_html_e( 'Baixar PDF complementar', EOP_TEXT_DOMAIN ); ?></a>
                        <a class="eop-btn eop-btn-secondary" id="eop-post-flow-final-pdf-link" href="#" target="_blank" rel="noopener"<?php echo $eop_is_preview_frame ? '' : ' hidden'; ?>><?php esc_html_e( 'Baixar PDF final da personalizacao', EOP_TEXT_DOMAIN ); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
