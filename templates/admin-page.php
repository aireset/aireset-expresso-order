<?php
defined( 'ABSPATH' ) || exit;

$settings            = EOP_Settings::get_all();
$font_css            = method_exists( 'EOP_Settings', 'get_font_css_family' ) ? EOP_Settings::get_font_css_family( $settings['font_family'] ) : "'Segoe UI', sans-serif";
$initial_view        = EOP_Admin_Page::normalize_view( isset( $_GET['view'] ) ? wp_unslash( $_GET['view'] ) : '' );
$license_manager     = class_exists( 'EOP_License_Manager' ) ? EOP_License_Manager::get_instance() : null;
$can_manage_settings = current_user_can( 'manage_options' );
$pdf_tabs            = class_exists( 'EOP_PDF_Admin_Page' ) ? EOP_PDF_Admin_Page::get_spa_nav_tabs() : array();
$current_pdf_tab     = class_exists( 'EOP_PDF_Admin_Page' ) ? EOP_PDF_Admin_Page::get_current_tab() : 'display';
$pdf_preview_args    = array(
    'document'      => isset( $_GET['document'] ) && 'proposal' === sanitize_key( wp_unslash( $_GET['document'] ) ) ? 'proposal' : 'order',
    'preview_order' => absint( $_GET['preview_order'] ?? 0 ),
);
$general_views = array(
    'settings-store-info',
    'settings-general-config',
    'settings-order-link-style',
    'settings-proposal-link-style',
    'settings-customer-experience',
    'settings-texts',
);
$confirmation_views = array(
	'settings-confirmation-general',
	'settings-confirmation-documents',
	'settings-confirmation-preview',
);
$is_general_view = in_array( $initial_view, $general_views, true );
$is_confirmation_view = in_array( $initial_view, $confirmation_views, true );
$general_nav_items = array(
    'settings-store-info' => array(
        'label' => __( 'Informacoes sobre a loja', EOP_TEXT_DOMAIN ),
        'icon'  => 'dashicons-store',
    ),
    'settings-general-config' => array(
		'label' => __( 'Configurações Gerais', EOP_TEXT_DOMAIN ),
        'icon'  => 'dashicons-admin-settings',
    ),
    'settings-order-link-style' => array(
        'label' => __( 'Visual do Link do Pedido', EOP_TEXT_DOMAIN ),
        'icon'  => 'dashicons-art',
    ),
    'settings-proposal-link-style' => array(
        'label' => __( 'Visual do Link de Proposta', EOP_TEXT_DOMAIN ),
        'icon'  => 'dashicons-format-image',
    ),
    'settings-customer-experience' => array(
        'label' => __( 'Experiencia do Cliente', EOP_TEXT_DOMAIN ),
        'icon'  => 'dashicons-format-gallery',
    ),
    'settings-texts' => array(
        'label' => __( 'Textos', EOP_TEXT_DOMAIN ),
        'icon'  => 'dashicons-edit-large',
    ),
);
$confirmation_nav_items = array(
    'settings-confirmation-general' => array(
		'label' => __( 'Configurações Gerais', EOP_TEXT_DOMAIN ),
        'icon'  => 'dashicons-admin-settings',
    ),
    'settings-confirmation-documents' => array(
        'label' => __( 'Documentos', EOP_TEXT_DOMAIN ),
        'icon'  => 'dashicons-media-document',
    ),
    'settings-confirmation-preview' => array(
		'label' => __( 'Visual da página de confirmação', EOP_TEXT_DOMAIN ),
        'icon'  => 'dashicons-visibility',
    ),
);
$lazy_views = array(
    'new-order',
    'orders',
    'pdf',
    'settings-store-info',
    'settings-general-config',
    'settings-confirmation-general',
    'settings-confirmation-documents',
    'settings-confirmation-preview',
    'settings-order-link-style',
    'settings-proposal-link-style',
    'settings-customer-experience',
    'settings-texts',
    'documentation',
    'export-import',
    'license',
);
$render_lazy_placeholder = static function ( $title ) {
    ?>
    <div class="eop-admin-view-lazy" data-eop-lazy-placeholder="true">
        <div class="eop-card eop-admin-view-lazy__card">
            <div class="eop-admin-view-lazy__icon dashicons dashicons-update" aria-hidden="true"></div>
            <strong><?php echo esc_html( $title ); ?></strong>
            <p><?php esc_html_e( 'Esta area sera carregada sob demanda para deixar o plugin mais leve.', EOP_TEXT_DOMAIN ); ?></p>
        </div>
    </div>
    <?php
};

$performance_initial_metrics = class_exists( 'EOP_Performance_Audit' )
    ? EOP_Performance_Audit::get_request_metrics(
        'spa_bootstrap',
        array(
            'view' => $initial_view,
        )
    )
    : array();
?>
<style>
    .eop-admin-spa {
        --eop-primary: <?php echo esc_attr( $settings['primary_color'] ); ?>;
        --eop-surface: <?php echo esc_attr( $settings['surface_color'] ); ?>;
        --eop-border: <?php echo esc_attr( $settings['border_color'] ); ?>;
        --eop-radius: <?php echo esc_attr( absint( $settings['border_radius'] ) ); ?>px;
        --eop-font-family: <?php echo esc_attr( $font_css ); ?>;
        font-family: <?php echo esc_attr( $font_css ); ?>;
    }
</style>

<div class="wrap eop-admin-spa eop-pdv">
    <div class="eop-admin-spa__layout">
        <aside class="eop-admin-spa__sidebar">
            <div class="eop-admin-spa__brand">
                <div class="eop-admin-spa__brand-mark">
                    <img src="<?php echo esc_url( EOP_PLUGIN_URL . 'assets/images/logo-aireset.png' ); ?>" alt="Pedido Expresso - Aireset" />
                    <div class="eop-admin-spa__brand-actions">
                        <button type="button" class="eop-admin-spa__chrome-toggle eop-admin-spa__sidebar-toggle" id="eop-admin-sidebar-toggle" aria-pressed="false" title="<?php esc_attr_e( 'Recolher menu lateral', EOP_TEXT_DOMAIN ); ?>">
                            <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                            <span class="screen-reader-text eop-admin-spa__sidebar-toggle-label"><?php esc_html_e( 'Recolher menu lateral', EOP_TEXT_DOMAIN ); ?></span>
                        </button>
                        <button type="button" class="eop-admin-spa__chrome-toggle" id="eop-admin-chrome-toggle" aria-pressed="false" title="<?php esc_attr_e( 'Ocultar interface do WordPress', EOP_TEXT_DOMAIN ); ?>">
                            <span class="dashicons dashicons-fullscreen-alt" aria-hidden="true"></span>
                            <span class="screen-reader-text eop-admin-spa__chrome-toggle-label"><?php esc_html_e( 'Modo foco', EOP_TEXT_DOMAIN ); ?></span>
                        </button>
                    </div>
                </div>
                <div class="eop-admin-spa__brand-copy">
                    <div class="eop-admin-spa__brand-head">
                        <h1><?php echo esc_html( $settings['panel_title'] ); ?></h1>
                    </div>
                    <?php if ( ! empty( $settings['panel_subtitle'] ) ) : ?>
                        <p><?php echo esc_html( $settings['panel_subtitle'] ); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <nav class="eop-admin-spa__nav" aria-label="<?php esc_attr_e( 'Navegacao do admin do Pedido Expresso', EOP_TEXT_DOMAIN ); ?>">
                <button type="button" class="eop-pdv-nav__item eop-admin-spa-nav__item<?php echo 'new-order' === $initial_view ? ' is-active' : ''; ?>" data-eop-view-target="new-order" aria-selected="<?php echo 'new-order' === $initial_view ? 'true' : 'false'; ?>">
                    <span class="eop-admin-spa-nav__icon dashicons dashicons-cart" aria-hidden="true"></span>
                    <span class="eop-admin-spa-nav__name"><?php esc_html_e( 'Novo pedido', EOP_TEXT_DOMAIN ); ?></span>
                </button>
                <button type="button" class="eop-pdv-nav__item eop-admin-spa-nav__item<?php echo 'orders' === $initial_view ? ' is-active' : ''; ?>" data-eop-view-target="orders" aria-selected="<?php echo 'orders' === $initial_view ? 'true' : 'false'; ?>">
                    <span class="eop-admin-spa-nav__icon dashicons dashicons-list-view" aria-hidden="true"></span>
                    <span class="eop-admin-spa-nav__name"><?php esc_html_e( 'Pedidos', EOP_TEXT_DOMAIN ); ?></span>
                </button>
                <?php if ( $can_manage_settings ) : ?>
                    <div class="eop-admin-spa-nav__group eop-admin-spa-nav__group--general<?php echo $is_general_view ? ' is-open' : ''; ?>">
                        <button
                            type="button"
                            class="eop-pdv-nav__item eop-admin-spa-nav__item eop-admin-spa-nav__group-toggle<?php echo $is_general_view ? ' is-active' : ''; ?>"
                            data-eop-nav-toggle="general"
                            aria-selected="<?php echo $is_general_view ? 'true' : 'false'; ?>"
                            aria-expanded="<?php echo $is_general_view ? 'true' : 'false'; ?>"
                        >
                            <span class="eop-admin-spa-nav__icon dashicons dashicons-admin-generic" aria-hidden="true"></span>
                            <span class="eop-admin-spa-nav__name"><?php esc_html_e( 'Geral', EOP_TEXT_DOMAIN ); ?></span>
                            <span class="eop-admin-spa-nav__group-arrow dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                        </button>

                        <div class="eop-admin-spa-nav__submenu"<?php echo $is_general_view ? '' : ' hidden'; ?>>
                            <?php foreach ( $general_nav_items as $view_key => $nav_item ) : ?>
                                <button
                                    type="button"
                                    class="eop-admin-spa-nav__submenu-item<?php echo $initial_view === $view_key ? ' is-active' : ''; ?>"
                                    data-eop-view-target="<?php echo esc_attr( $view_key ); ?>"
                                >
                                    <span class="eop-admin-spa-nav__submenu-label"><?php echo esc_html( $nav_item['label'] ); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ( $can_manage_settings ) : ?>
                    <div class="eop-admin-spa-nav__group eop-admin-spa-nav__group--confirmation<?php echo $is_confirmation_view ? ' is-open' : ''; ?>">
                        <button
                            type="button"
                            class="eop-pdv-nav__item eop-admin-spa-nav__item eop-admin-spa-nav__group-toggle<?php echo $is_confirmation_view ? ' is-active' : ''; ?>"
                            data-eop-nav-toggle="confirmation"
                            aria-selected="<?php echo $is_confirmation_view ? 'true' : 'false'; ?>"
                            aria-expanded="<?php echo $is_confirmation_view ? 'true' : 'false'; ?>"
                        >
                            <span class="eop-admin-spa-nav__icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
                            <span class="eop-admin-spa-nav__name"><?php esc_html_e( 'Fluxo de Confirmação', EOP_TEXT_DOMAIN ); ?></span>
                            <span class="eop-admin-spa-nav__group-arrow dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                        </button>

                        <div class="eop-admin-spa-nav__submenu"<?php echo $is_confirmation_view ? '' : ' hidden'; ?>>
                            <?php foreach ( $confirmation_nav_items as $view_key => $nav_item ) : ?>
                                <button
                                    type="button"
                                    class="eop-admin-spa-nav__submenu-item<?php echo $initial_view === $view_key ? ' is-active' : ''; ?>"
                                    data-eop-view-target="<?php echo esc_attr( $view_key ); ?>"
                                >
                                    <span class="eop-admin-spa-nav__submenu-label"><?php echo esc_html( $nav_item['label'] ); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ( ! empty( $pdf_tabs ) ) : ?>
                    <div class="eop-admin-spa-nav__group eop-admin-spa-nav__group--pdf<?php echo 'pdf' === $initial_view ? ' is-open' : ''; ?>">
                        <button
                            type="button"
                            class="eop-pdv-nav__item eop-admin-spa-nav__item eop-admin-spa-nav__group-toggle<?php echo 'pdf' === $initial_view ? ' is-active' : ''; ?>"
                            data-eop-view-target="pdf"
                            data-eop-nav-toggle="pdf"
                            aria-selected="<?php echo 'pdf' === $initial_view ? 'true' : 'false'; ?>"
                            aria-expanded="<?php echo 'pdf' === $initial_view ? 'true' : 'false'; ?>"
                        >
                            <span class="eop-admin-spa-nav__icon dashicons dashicons-media-document" aria-hidden="true"></span>
                            <span class="eop-admin-spa-nav__name"><?php esc_html_e( 'PDF', EOP_TEXT_DOMAIN ); ?></span>
                            <span class="eop-admin-spa-nav__group-arrow dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                        </button>

                        <div class="eop-admin-spa-nav__submenu"<?php echo 'pdf' === $initial_view ? '' : ' hidden'; ?>>
                            <?php foreach ( $pdf_tabs as $pdf_tab_key => $pdf_tab_label ) : ?>
                                <a
                                    class="eop-admin-spa-nav__submenu-item<?php echo 'pdf' === $initial_view && $current_pdf_tab === $pdf_tab_key ? ' is-active' : ''; ?>"
                                    href="<?php echo esc_url( EOP_PDF_Admin_Page::get_tab_url( $pdf_tab_key, $pdf_preview_args ) ); ?>"
                                    data-eop-pdf-tab="<?php echo esc_attr( $pdf_tab_key ); ?>"
                                >
                                    <span class="eop-admin-spa-nav__submenu-label"><?php echo esc_html( $pdf_tab_label ); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else : ?>
                    <button type="button" class="eop-pdv-nav__item eop-admin-spa-nav__item<?php echo 'pdf' === $initial_view ? ' is-active' : ''; ?>" data-eop-view-target="pdf" aria-selected="<?php echo 'pdf' === $initial_view ? 'true' : 'false'; ?>">
                        <span class="eop-admin-spa-nav__icon dashicons dashicons-media-document" aria-hidden="true"></span>
                        <span class="eop-admin-spa-nav__name"><?php esc_html_e( 'PDF', EOP_TEXT_DOMAIN ); ?></span>
                    </button>
                <?php endif; ?>
                <?php if ( $can_manage_settings ) : ?>
                    <button type="button" class="eop-pdv-nav__item eop-admin-spa-nav__item<?php echo 'documentation' === $initial_view ? ' is-active' : ''; ?>" data-eop-view-target="documentation" aria-selected="<?php echo 'documentation' === $initial_view ? 'true' : 'false'; ?>">
                        <span class="eop-admin-spa-nav__icon dashicons dashicons-book-alt" aria-hidden="true"></span>
                        <span class="eop-admin-spa-nav__name"><?php esc_html_e( 'Documentação', EOP_TEXT_DOMAIN ); ?></span>
                    </button>
                    <button type="button" class="eop-pdv-nav__item eop-admin-spa-nav__item<?php echo 'export-import' === $initial_view ? ' is-active' : ''; ?>" data-eop-view-target="export-import" aria-selected="<?php echo 'export-import' === $initial_view ? 'true' : 'false'; ?>">
                        <span class="eop-admin-spa-nav__icon dashicons dashicons-migrate" aria-hidden="true"></span>
                        <span class="eop-admin-spa-nav__name"><?php esc_html_e( 'Exportar e Importar', EOP_TEXT_DOMAIN ); ?></span>
                    </button>
                    <button type="button" class="eop-pdv-nav__item eop-admin-spa-nav__item<?php echo 'license' === $initial_view ? ' is-active' : ''; ?>" data-eop-view-target="license" aria-selected="<?php echo 'license' === $initial_view ? 'true' : 'false'; ?>">
                        <span class="eop-admin-spa-nav__icon dashicons dashicons-admin-network" aria-hidden="true"></span>
                        <span class="eop-admin-spa-nav__name"><?php esc_html_e( 'Licença', EOP_TEXT_DOMAIN ); ?></span>
                    </button>
                <?php endif; ?>
            </nav>
        </aside>

        <div class="eop-admin-spa__content">
            <div id="eop-notices"></div>

            <?php if ( $can_manage_settings ) : ?>
                <section
                    class="eop-card eop-performance-audit is-collapsed"
                    id="eop-performance-audit"
                    data-eop-performance-initial="<?php echo esc_attr( wp_json_encode( $performance_initial_metrics ) ); ?>"
                >
                    <div class="eop-performance-audit__header">
                        <button type="button" class="eop-performance-audit__toggle" id="eop-performance-audit-toggle" aria-expanded="false" aria-controls="eop-performance-audit-body">
                            <span class="eop-performance-audit__header-copy">
                                <strong><?php esc_html_e( 'Baseline de performance', EOP_TEXT_DOMAIN ); ?></strong>
                                <span><?php esc_html_e( 'Auditoria inicial da sessao para medir shell, views lazy, PDF e pedidos antes das proximas fases de otimizacao.', EOP_TEXT_DOMAIN ); ?></span>
                            </span>
                        <span class="eop-performance-audit__chevron dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                        </button>
                    </div>

                    <div class="eop-performance-audit__body" id="eop-performance-audit-body" hidden>
                        <div class="eop-performance-audit__actions">
                            <button type="button" class="button button-secondary" id="eop-performance-clear-session"><?php esc_html_e( 'Limpar baseline da sessao', EOP_TEXT_DOMAIN ); ?></button>
                        </div>

                        <div class="eop-performance-audit__summary" id="eop-performance-summary"></div>

                        <div class="eop-performance-audit__table-wrap">
                            <table class="widefat striped eop-performance-audit__table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Fluxo', EOP_TEXT_DOMAIN ); ?></th>
                                        <th><?php esc_html_e( 'Origem', EOP_TEXT_DOMAIN ); ?></th>
                                        <th><?php esc_html_e( 'Tempo total', EOP_TEXT_DOMAIN ); ?></th>
                                        <th><?php esc_html_e( 'PHP', EOP_TEXT_DOMAIN ); ?></th>
                                        <th><?php esc_html_e( 'Resposta', EOP_TEXT_DOMAIN ); ?></th>
                                        <th><?php esc_html_e( 'Pico memoria', EOP_TEXT_DOMAIN ); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="eop-performance-table-body">
                                    <tr>
                                        <td colspan="6"><?php esc_html_e( 'Nenhuma medicao registrada ainda nesta sessao.', EOP_TEXT_DOMAIN ); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <section class="eop-pdv-view<?php echo 'new-order' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="new-order" data-eop-lazy="true" data-eop-lazy-loaded="false"<?php echo 'new-order' === $initial_view ? '' : ' hidden'; ?>>
                <?php $render_lazy_placeholder( __( 'Novo pedido', EOP_TEXT_DOMAIN ) ); ?>
            </section>

            <section class="eop-pdv-view<?php echo 'orders' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="orders" data-eop-lazy="true" data-eop-lazy-loaded="false"<?php echo 'orders' === $initial_view ? '' : ' hidden'; ?>>
                <?php $render_lazy_placeholder( __( 'Pedidos', EOP_TEXT_DOMAIN ) ); ?>
            </section>

            <section class="eop-pdv-view<?php echo 'pdf' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="pdf" data-eop-lazy="true" data-eop-lazy-loaded="<?php echo 'pdf' === $initial_view ? 'true' : 'false'; ?>"<?php echo 'pdf' === $initial_view ? '' : ' hidden'; ?>>
                <div class="eop-admin-panel-head">
                    <h2><?php esc_html_e( 'PDF', EOP_TEXT_DOMAIN ); ?></h2>
                    <p><?php esc_html_e( 'Configure documentos, preview e comportamento do modulo PDF sem sair do shell original do Pedido Expresso.', EOP_TEXT_DOMAIN ); ?></p>
                </div>
                <div class="eop-admin-view-main">
                <?php if ( 'pdf' === $initial_view ) : ?>
                    <?php
                    if ( class_exists( 'EOP_PDF_Admin_Page' ) ) {
                        EOP_PDF_Admin_Page::render_embedded_page();
                    }
                    ?>
                <?php else : ?>
                    <?php $render_lazy_placeholder( __( 'Modulo PDF', EOP_TEXT_DOMAIN ) ); ?>
                <?php endif; ?>
                </div>
            </section>

            <?php if ( $can_manage_settings ) : ?>
                <section class="eop-pdv-view<?php echo 'settings-store-info' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="settings-store-info" data-eop-lazy="true" data-eop-lazy-loaded="<?php echo 'settings-store-info' === $initial_view ? 'true' : 'false'; ?>"<?php echo 'settings-store-info' === $initial_view ? '' : ' hidden'; ?>>
                    <div class="eop-admin-panel-head">
                        <h2><?php esc_html_e( 'Informacoes sobre a loja', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Centralize logo, dados institucionais e informacoes exibidas nos documentos do Pedido Expresso.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-admin-view-main">
                    <?php if ( 'settings-store-info' === $initial_view ) : ?>
                        <?php EOP_PDF_Admin_Page::render_embedded_page( 'store' ); ?>
                    <?php else : ?>
                        <?php $render_lazy_placeholder( __( 'Informacoes sobre a loja', EOP_TEXT_DOMAIN ) ); ?>
                    <?php endif; ?>
                    </div>
                </section>

                <section class="eop-pdv-view<?php echo 'settings-general-config' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="settings-general-config" data-eop-lazy="true" data-eop-lazy-loaded="<?php echo 'settings-general-config' === $initial_view ? 'true' : 'false'; ?>"<?php echo 'settings-general-config' === $initial_view ? '' : ' hidden'; ?>>
                    <div class="eop-admin-panel-head">
						<h2><?php esc_html_e( 'Configurações Gerais', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Mantenha em um bloco proprio as regras operacionais do plugin, paginas publicas e comportamento comercial principal.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-admin-view-main">
                    <?php if ( 'settings-general-config' === $initial_view ) : ?>
                        <?php EOP_Settings::render_embedded_page( 'general-config' ); ?>
                    <?php else : ?>
						<?php $render_lazy_placeholder( __( 'Configurações Gerais', EOP_TEXT_DOMAIN ) ); ?>
                    <?php endif; ?>
                    </div>
                </section>

                <section class="eop-pdv-view<?php echo 'settings-confirmation-general' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="settings-confirmation-general" data-eop-lazy="true" data-eop-lazy-loaded="<?php echo 'settings-confirmation-general' === $initial_view ? 'true' : 'false'; ?>"<?php echo 'settings-confirmation-general' === $initial_view ? '' : ' hidden'; ?>>
                    <div class="eop-admin-panel-head">
						<h2><?php esc_html_e( 'Configurações Gerais', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Controle regras gerais, aceite, upload, personalizacao e conclusao do fluxo complementar.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-admin-view-main">
                    <?php if ( 'settings-confirmation-general' === $initial_view ) : ?>
                        <?php EOP_Settings::render_embedded_page( 'confirmation-flow-general' ); ?>
                    <?php else : ?>
						<?php $render_lazy_placeholder( __( 'Fluxo de Confirmação - Geral', EOP_TEXT_DOMAIN ) ); ?>
                    <?php endif; ?>
                    </div>
                </section>

                <section class="eop-pdv-view<?php echo 'settings-confirmation-documents' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="settings-confirmation-documents" data-eop-lazy="true" data-eop-lazy-loaded="<?php echo 'settings-confirmation-documents' === $initial_view ? 'true' : 'false'; ?>"<?php echo 'settings-confirmation-documents' === $initial_view ? '' : ' hidden'; ?>>
                    <div class="eop-admin-panel-head">
                        <h2><?php esc_html_e( 'Documentos', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Gerencie a listagem de documentos do contrato com cadastro, edicao e arquivos para conversao automatica.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-admin-view-main">
                    <?php if ( 'settings-confirmation-documents' === $initial_view ) : ?>
                        <?php EOP_Settings::render_embedded_page( 'confirmation-flow-documents' ); ?>
                    <?php else : ?>
						<?php $render_lazy_placeholder( __( 'Fluxo de Confirmação - Documentos', EOP_TEXT_DOMAIN ) ); ?>
                    <?php endif; ?>
                    </div>
                </section>

                <section class="eop-pdv-view<?php echo 'settings-confirmation-preview' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="settings-confirmation-preview" data-eop-lazy="true" data-eop-lazy-loaded="<?php echo 'settings-confirmation-preview' === $initial_view ? 'true' : 'false'; ?>"<?php echo 'settings-confirmation-preview' === $initial_view ? '' : ' hidden'; ?>>
                    <div class="eop-admin-panel-head">
						<h2><?php esc_html_e( 'Visual da página de confirmação', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Ajuste o visual da etapa contratual com uma leitura previa da pagina publica.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-admin-view-main">
                    <?php if ( 'settings-confirmation-preview' === $initial_view ) : ?>
                        <?php EOP_Settings::render_embedded_page( 'confirmation-flow-preview' ); ?>
                    <?php else : ?>
						<?php $render_lazy_placeholder( __( 'Fluxo de Confirmação - Visual da página de confirmação', EOP_TEXT_DOMAIN ) ); ?>
                    <?php endif; ?>
                    </div>
                </section>

                <section class="eop-pdv-view<?php echo 'settings-order-link-style' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="settings-order-link-style" data-eop-lazy="true" data-eop-lazy-loaded="<?php echo 'settings-order-link-style' === $initial_view ? 'true' : 'false'; ?>"<?php echo 'settings-order-link-style' === $initial_view ? '' : ' hidden'; ?>>
                    <div class="eop-admin-panel-head">
                        <h2><?php esc_html_e( 'Visual do Link do Pedido', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Separe a identidade visual principal do shell e do link do pedido para ajustes rapidos de marca.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-admin-view-main">
                    <?php if ( 'settings-order-link-style' === $initial_view ) : ?>
                        <?php EOP_Settings::render_embedded_page( 'order-link-style' ); ?>
                    <?php else : ?>
                        <?php $render_lazy_placeholder( __( 'Visual do Link do Pedido', EOP_TEXT_DOMAIN ) ); ?>
                    <?php endif; ?>
                    </div>
                </section>

                <section class="eop-pdv-view<?php echo 'settings-proposal-link-style' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="settings-proposal-link-style" data-eop-lazy="true" data-eop-lazy-loaded="<?php echo 'settings-proposal-link-style' === $initial_view ? 'true' : 'false'; ?>"<?php echo 'settings-proposal-link-style' === $initial_view ? '' : ' hidden'; ?>>
                    <div class="eop-admin-panel-head">
                        <h2><?php esc_html_e( 'Visual do Link de Proposta', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Ajuste o visual publico da proposta sem misturar essas opcoes com o restante do admin.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-admin-view-main">
                    <?php if ( 'settings-proposal-link-style' === $initial_view ) : ?>
                        <?php EOP_Settings::render_embedded_page( 'proposal-link-style' ); ?>
                    <?php else : ?>
                        <?php $render_lazy_placeholder( __( 'Visual do Link de Proposta', EOP_TEXT_DOMAIN ) ); ?>
                    <?php endif; ?>
                    </div>
                </section>

                <section class="eop-pdv-view<?php echo 'settings-customer-experience' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="settings-customer-experience" data-eop-lazy="true" data-eop-lazy-loaded="<?php echo 'settings-customer-experience' === $initial_view ? 'true' : 'false'; ?>"<?php echo 'settings-customer-experience' === $initial_view ? '' : ' hidden'; ?>>
                    <div class="eop-admin-panel-head">
                        <h2><?php esc_html_e( 'Experiencia do Cliente', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Separe o design da pagina confirmada e do fluxo complementar em uma view exclusiva dentro da SPA.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-admin-view-main">
                    <?php if ( 'settings-customer-experience' === $initial_view ) : ?>
                        <?php EOP_Settings::render_embedded_page( 'customer-experience' ); ?>
                    <?php else : ?>
                        <?php $render_lazy_placeholder( __( 'Experiencia do Cliente', EOP_TEXT_DOMAIN ) ); ?>
                    <?php endif; ?>
                    </div>
                </section>

                <section class="eop-pdv-view<?php echo 'settings-texts' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="settings-texts" data-eop-lazy="true" data-eop-lazy-loaded="<?php echo 'settings-texts' === $initial_view ? 'true' : 'false'; ?>"<?php echo 'settings-texts' === $initial_view ? '' : ' hidden'; ?>>
                    <div class="eop-admin-panel-head">
                        <h2><?php esc_html_e( 'Textos e mensagens', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Mantenha em uma pagina propria os titulos, descricoes e labels usados no painel e na proposta publica.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-admin-view-main">
                    <?php if ( 'settings-texts' === $initial_view ) : ?>
                        <?php EOP_Settings::render_embedded_page( 'texts' ); ?>
                    <?php else : ?>
                        <?php $render_lazy_placeholder( __( 'Textos e mensagens', EOP_TEXT_DOMAIN ) ); ?>
                    <?php endif; ?>
                    </div>
                </section>

                <section class="eop-pdv-view<?php echo 'documentation' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="documentation" data-eop-lazy="true" data-eop-lazy-loaded="<?php echo 'documentation' === $initial_view ? 'true' : 'false'; ?>"<?php echo 'documentation' === $initial_view ? '' : ' hidden'; ?>>
                    <div class="eop-admin-panel-head">
						<h2><?php esc_html_e( 'Documentação', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Consulte em uma area propria o efeito real de cada configuracao do modulo de documentos.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-admin-view-main">
                    <?php if ( 'documentation' === $initial_view ) : ?>
                        <?php EOP_PDF_Admin_Page::render_embedded_page( 'documentation' ); ?>
                    <?php else : ?>
						<?php $render_lazy_placeholder( __( 'Documentação', EOP_TEXT_DOMAIN ) ); ?>
                    <?php endif; ?>
                    </div>
                </section>

                <section class="eop-pdv-view<?php echo 'export-import' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="export-import" data-eop-lazy="true" data-eop-lazy-loaded="<?php echo 'export-import' === $initial_view ? 'true' : 'false'; ?>"<?php echo 'export-import' === $initial_view ? '' : ' hidden'; ?>>
                    <div class="eop-admin-panel-head">
                        <h2><?php esc_html_e( 'Exportar e Importar', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Centralize backup, restauracao e importacao de documentos do fluxo complementar em uma area propria da SPA.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-admin-view-main">
                    <?php if ( 'export-import' === $initial_view ) : ?>
                        <?php if ( class_exists( 'EOP_Settings_Portability' ) ) { EOP_Settings_Portability::render_page(); } ?>
                    <?php else : ?>
                        <?php $render_lazy_placeholder( __( 'Exportar e Importar', EOP_TEXT_DOMAIN ) ); ?>
                    <?php endif; ?>
                    </div>
                </section>

                <section class="eop-pdv-view<?php echo 'license' === $initial_view ? ' is-active' : ''; ?>" data-eop-view="license" data-eop-lazy="true" data-eop-lazy-loaded="<?php echo 'license' === $initial_view ? 'true' : 'false'; ?>"<?php echo 'license' === $initial_view ? '' : ' hidden'; ?>>
                    <div class="eop-admin-panel-head">
						<h2><?php esc_html_e( 'Licença', EOP_TEXT_DOMAIN ); ?></h2>
                        <p><?php esc_html_e( 'Consulte a validade da assinatura e administre a ativacao do plugin sem sair do painel.', EOP_TEXT_DOMAIN ); ?></p>
                    </div>
                    <div class="eop-admin-view-main">
                    <?php if ( 'license' === $initial_view ) : ?>
                        <div class="eop-admin-license-shell">
                            <?php
                            if ( $license_manager ) {
                                $license_manager->activated();
                            }
                            ?>
                        </div>
                    <?php else : ?>
						<?php $render_lazy_placeholder( __( 'Licença', EOP_TEXT_DOMAIN ) ); ?>
                    <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>

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
