<?php
defined( 'ABSPATH' ) || exit;
?>
<section class="eop-pdv-view is-active" data-eop-view="new-order" data-eop-lazy="true" data-eop-lazy-loaded="true">
    <input type="hidden" id="eop-edit-order-id" value="0" />

    <div class="eop-admin-view-header">
        <div class="eop-admin-view-copy">
            <span class="eop-admin-view-kicker"><?php esc_html_e( 'Operacao comercial', EOP_TEXT_DOMAIN ); ?></span>
            <div class="eop-admin-view-title-row">
                <h2 class="eop-admin-view-title">
                    <span class="dashicons dashicons-cart" aria-hidden="true"></span>
                    <span><?php esc_html_e( 'Novo pedido', EOP_TEXT_DOMAIN ); ?></span>
                </h2>
            </div>
            <p class="eop-admin-view-desc"><?php esc_html_e( 'Monte o pedido, ajuste cliente, frete e descontos sem sair do fluxo principal do painel.', EOP_TEXT_DOMAIN ); ?></p>
        </div>
    </div>

    <div class="eop-admin-view-main">
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
                            <option value="completed"><?php esc_html_e( 'Concluido', EOP_TEXT_DOMAIN ); ?></option>
                            <option value="pending"><?php esc_html_e( 'Pendente', EOP_TEXT_DOMAIN ); ?></option>
                            <option value="processing"><?php esc_html_e( 'Processando', EOP_TEXT_DOMAIN ); ?></option>
                            <option value="on-hold"><?php esc_html_e( 'Aguardando', EOP_TEXT_DOMAIN ); ?></option>
                        </select>
                    </div>

                    <button type="button" id="eop-submit" class="eop-btn eop-btn-primary eop-btn-block">
                        <?php esc_html_e( 'Finalizar e Gerar PDF', EOP_TEXT_DOMAIN ); ?>
                    </button>
                </div>

                <div class="eop-card eop-post-flow-card" id="eop-post-flow-card">
                    <div class="eop-post-flow-card__head">
                        <div>
                            <h2><?php esc_html_e( 'Fluxo complementar do cliente', EOP_TEXT_DOMAIN ); ?></h2>
                            <p id="eop-post-flow-subtitle"><?php esc_html_e( 'O resumo complementar aparece quando um pedido existente entra em modo de edicao.', EOP_TEXT_DOMAIN ); ?></p>
                        </div>
                        <span class="eop-post-flow-badge is-inactive" id="eop-post-flow-badge"><?php esc_html_e( 'Inativo', EOP_TEXT_DOMAIN ); ?></span>
                    </div>

                    <div class="eop-post-flow-card__toolbar" id="eop-post-flow-stage-toolbar" hidden>
                        <div class="eop-post-flow-stage-form eop-post-flow-stage-form--inline">
                            <div class="eop-post-flow-stage-field">
                                <label for="eop-post-flow-stage-select"><?php esc_html_e( 'Etapa do fluxo', EOP_TEXT_DOMAIN ); ?></label>
                                <select id="eop-post-flow-stage-select" disabled></select>
                            </div>
                            <button type="button" class="eop-btn eop-btn-secondary" id="eop-post-flow-stage-apply" disabled><?php esc_html_e( 'Atualizar etapa', EOP_TEXT_DOMAIN ); ?></button>
                        </div>
                        <p class="eop-post-flow-stage-hint" id="eop-post-flow-stage-hint"><?php esc_html_e( 'Use Automatico para voltar ao fluxo calculado pelo sistema.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>

                    <div class="eop-post-flow-card__stats" id="eop-post-flow-stats"></div>

                    <div class="eop-post-flow-card__section">
                        <h3><?php esc_html_e( 'Contrato', EOP_TEXT_DOMAIN ); ?></h3>
                        <p id="eop-post-flow-contract"><?php esc_html_e( 'Nenhum aceite registrado.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>

                    <div class="eop-post-flow-card__section">
                        <h3><?php esc_html_e( 'Documentos para assinatura', EOP_TEXT_DOMAIN ); ?></h3>
                        <div class="eop-post-flow-list" id="eop-post-flow-signature-documents"></div>
                    </div>

                    <div class="eop-post-flow-card__section">
                        <h3><?php esc_html_e( 'Dados do pedido', EOP_TEXT_DOMAIN ); ?></h3>
                        <div class="eop-post-flow-list" id="eop-post-flow-order-data"></div>
                    </div>

                    <div class="eop-post-flow-card__section">
                        <h3><?php esc_html_e( 'Anexo', EOP_TEXT_DOMAIN ); ?></h3>
                        <div class="eop-post-flow-list" id="eop-post-flow-attachment"></div>
                    </div>

                    <div class="eop-post-flow-card__section">
                        <h3><?php esc_html_e( 'Produtos', EOP_TEXT_DOMAIN ); ?></h3>
                        <div class="eop-post-flow-list" id="eop-post-flow-products"></div>
                    </div>

                    <div class="eop-post-flow-card__section">
                        <h3><?php esc_html_e( 'Downloads e links', EOP_TEXT_DOMAIN ); ?></h3>
                        <div class="eop-post-flow-list" id="eop-post-flow-downloads"></div>
                    </div>

                    <div class="eop-post-flow-card__actions">
                        <a class="eop-btn" id="eop-post-flow-public-link" href="#" target="_blank" rel="noopener" hidden><?php esc_html_e( 'Abrir link publico', EOP_TEXT_DOMAIN ); ?></a>
                        <a class="eop-btn eop-btn-secondary" id="eop-post-flow-pdf-link" href="#" target="_blank" rel="noopener" hidden><?php esc_html_e( 'Baixar PDF complementar', EOP_TEXT_DOMAIN ); ?></a>
                        <a class="eop-btn eop-btn-secondary" id="eop-post-flow-final-pdf-link" href="#" target="_blank" rel="noopener" hidden><?php esc_html_e( 'Baixar PDF final da personalizacao', EOP_TEXT_DOMAIN ); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
