<?php
defined( 'ABSPATH' ) || exit;

class EOP_Public_Proposal {

    public static function init() {
        add_shortcode( 'expresso_order_proposal', array( __CLASS__, 'render_shortcode' ) );
        add_action( 'init', array( __CLASS__, 'handle_confirmation' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_public_assets' ) );
    }

    public static function maybe_enqueue_public_assets() {
        $token = '';

        if ( isset( $_GET['eop_proposal'] ) ) {
            $token = sanitize_text_field( wp_unslash( $_GET['eop_proposal'] ) );
        } elseif ( isset( $_GET['eop_token'] ) ) {
            $token = sanitize_text_field( wp_unslash( $_GET['eop_token'] ) );
        }

        if ( '' === $token ) {
            return;
        }

        $order = self::get_order_by_token( $token );

        if ( ! $order ) {
            return;
        }

        $settings = EOP_Settings::get_all();
        $theme    = self::get_public_proposal_theme( $settings );
        $font_url = $theme['font_url'];

        if ( $font_url ) {
            wp_enqueue_style( 'eop-selected-font', $font_url, array(), null );
        }

        wp_enqueue_style( 'eop-frontend', EOP_PLUGIN_URL . 'assets/css/frontend.css', array(), EOP_VERSION );
    }

    public static function create_public_token( WC_Order $order ) {
        $token = wp_generate_password( 24, false, false );

        $order->update_meta_data( '_eop_public_token', $token );
        $order->update_meta_data( '_eop_is_proposal', 'yes' );
        $order->update_meta_data( '_eop_proposal_confirmed', 'no' );

        return $token;
    }

    public static function get_public_link( WC_Order $order ) {
        $page_id = class_exists( 'EOP_Page_Installer' ) ? EOP_Page_Installer::get_page_id( 'proposal' ) : absint( EOP_Settings::get( 'proposal_page_id', 0 ) );
        $token   = (string) $order->get_meta( '_eop_public_token', true );

        if ( ! $page_id || '' === $token ) {
            return '';
        }

        return add_query_arg(
            array(
                'eop_proposal' => rawurlencode( $token ),
            ),
            get_permalink( $page_id )
        );
    }

    public static function render_shortcode() {
        $token = isset( $_GET['eop_proposal'] ) ? sanitize_text_field( wp_unslash( $_GET['eop_proposal'] ) ) : '';

        if ( '' === $token ) {
            return '<div class="eop-notice eop-notice-error">' . esc_html__( 'Proposta nao encontrada.', EOP_TEXT_DOMAIN ) . '</div>';
        }

        $order = self::get_order_by_token( $token );

        if ( ! $order ) {
            return '<div class="eop-notice eop-notice-error">' . esc_html__( 'Link de proposta invalido ou expirado.', EOP_TEXT_DOMAIN ) . '</div>';
        }

        return self::render_proposal_page( $order );
    }

    public static function handle_confirmation() {
        if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
            return;
        }

        if ( empty( $_POST['eop_confirm_proposal_nonce'] ) || empty( $_POST['eop_proposal_token'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eop_confirm_proposal_nonce'] ) ), 'eop_confirm_proposal' ) ) {
            return;
        }

        $token = sanitize_text_field( wp_unslash( $_POST['eop_proposal_token'] ) );
        $order = self::get_order_by_token( $token );

        if ( ! $order ) {
            return;
        }

        $order->update_meta_data( '_eop_proposal_confirmed', 'yes' );
        $order->add_order_note( __( 'Proposta confirmada pelo cliente.', EOP_TEXT_DOMAIN ) );
        $order->save();

        $redirect = add_query_arg( 'eop_confirmed', '1', self::get_public_link( $order ) );

        wp_safe_redirect( $redirect );
        exit;
    }

    public static function get_order_by_token( $token ) {
        $orders = wc_get_orders(
            array(
                'limit'      => 1,
                'type'       => 'shop_order',
                'meta_key'   => '_eop_public_token',
                'meta_value' => $token,
                'status'     => array_keys( wc_get_order_statuses() ),
            )
        );

        if ( empty( $orders ) ) {
            return false;
        }

        return $orders[0];
    }

    private static function render_proposal_page( WC_Order $order ) {
        $totals          = EOP_Order_Creator::sync_order_totals( $order );
        $settings        = EOP_Settings::get_all();
        $theme           = self::get_public_proposal_theme( $settings );
        $logo_url        = $settings['brand_logo_url'];
        $confirmed       = 'yes' === $order->get_meta( '_eop_proposal_confirmed', true );
        $line_items      = class_exists( 'EOP_Document_Manager' ) ? EOP_Document_Manager::get_order_line_items_display_data( $order ) : array();
        $button_label    = $settings['proposal_button_label'];
        $pay_label       = ! empty( $settings['proposal_pay_button_label'] ) ? $settings['proposal_pay_button_label'] : __( 'Ir para pagamento', EOP_TEXT_DOMAIN );
        $confirm_state   = isset( $_GET['eop_confirmed'] ) ? sanitize_text_field( wp_unslash( $_GET['eop_confirmed'] ) ) : '';
        $can_pay         = 'yes' === EOP_Settings::get( 'enable_checkout_confirmation', 'no' ) && method_exists( $order, 'needs_payment' ) && $order->needs_payment();
        $payment_url     = $can_pay ? $order->get_checkout_payment_url() : '';
        $pdf_url         = class_exists( 'EOP_Document_Manager' ) ? EOP_Document_Manager::get_public_document_url( $order, true ) : '';
        $flow_enabled    = class_exists( 'EOP_Post_Confirmation_Flow' ) && EOP_Post_Confirmation_Flow::is_enabled_for_order( $order );
        $current_flow_stage = $confirmed && $flow_enabled ? EOP_Post_Confirmation_Flow::get_current_stage( $order ) : '';
        $current_flow_label = $current_flow_stage ? EOP_Post_Confirmation_Flow::get_stage_label( $current_flow_stage ) : '';
        $is_flow_focus = $confirmed && $flow_enabled && in_array( $current_flow_stage, array( 'payment', 'contract', 'upload', 'products', 'completed' ), true );
        $document_config = class_exists( 'EOP_Document_Manager' ) ? EOP_Document_Manager::get_document_display_settings( 'proposal' ) : array();
        $item_columns    = class_exists( 'EOP_Document_Manager' ) ? EOP_Document_Manager::get_document_item_columns( 'proposal' ) : array();
        $item_labels     = class_exists( 'EOP_Document_Manager' ) ? EOP_Document_Manager::get_document_item_labels( 'proposal' ) : array();
        $total_rows      = class_exists( 'EOP_Document_Manager' ) ? EOP_Document_Manager::get_document_total_rows( $totals, 'proposal' ) : array();
        $total_rows      = self::prepare_total_rows_for_display( $order, $total_rows );
        $show_sku        = 'yes' === ( $document_config['show_sku'] ?? 'yes' );
        $show_email      = 'yes' === ( $document_config['show_email'] ?? 'yes' );
        $show_phone      = 'yes' === ( $document_config['show_phone'] ?? 'yes' );
        $show_notes      = 'yes' === ( $document_config['show_notes'] ?? 'yes' );
        $has_logo        = ! empty( $logo_url );
        $customer_name   = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ?: __( 'Nao informado', EOP_TEXT_DOMAIN );
        $experience_font_css   = $theme['font_css'];
        $experience_font_url   = $theme['font_url'];
        $base_font_size  = $theme['base_font_size'];
        $title_font_size = $theme['title_font_size'];
        $max_width       = $theme['max_width'];
        $customer_note   = trim( (string) $order->get_customer_note() );
        $experience_bg       = $theme['background_css'];
        $experience_hero_bg  = $theme['hero_background_css'];
        $experience_panel_bg = $theme['panel_background_css'];
        $experience_side_bg  = $theme['sidebar_background_css'];
        $experience_accent   = $theme['accent_color'];
        $experience_text     = $theme['text_color'];
        $experience_muted    = $theme['muted_color'];
        $experience_eyebrow  = $theme['eyebrow'];
        $experience_title    = $theme['title'];
        $experience_desc     = $theme['description'];
        $total_label         = $theme['total_label'];
        $total_note          = $theme['total_note'];
        $items_eyebrow       = $theme['items_eyebrow'];
        $items_title         = $theme['items_title'];
        $summary_eyebrow     = $theme['summary_eyebrow'];
        $summary_title       = $theme['summary_title'];
        $financial_eyebrow   = $theme['financial_eyebrow'];
        $financial_title     = $theme['financial_title'];
        $actions_eyebrow     = $theme['actions_eyebrow'];
        $actions_title       = $theme['actions_title'];
        $show_line_total = (bool) array_filter(
            $item_columns,
            function ( $column ) {
                return isset( $column['key'] ) && 'line_total' === $column['key'];
            }
        );

        $items_eyebrow     = self::normalize_customer_experience_copy( $items_eyebrow, 'Resumo visual', '' );
        $items_title       = self::normalize_customer_experience_copy( $items_title, 'O que esta incluso', 'Itens' );
        $financial_eyebrow = self::normalize_customer_experience_copy( $financial_eyebrow, 'Fechamento', '' );
        $financial_title   = self::normalize_customer_experience_copy( $financial_title, 'Resumo financeiro', 'Resumo' );

        ob_start();
        ?>
        <?php if ( ! wp_style_is( 'eop-frontend', 'enqueued' ) && ! wp_style_is( 'eop-frontend', 'done' ) ) : ?>
            <link rel="stylesheet" href="<?php echo esc_url( EOP_PLUGIN_URL . 'assets/css/frontend.css?ver=' . EOP_VERSION ); ?>">
        <?php endif; ?>
        <?php if ( $experience_font_url ) : ?>
            <link rel="stylesheet" href="<?php echo esc_url( $experience_font_url ); ?>">
        <?php endif; ?>
        <style>
            body{background:<?php echo esc_attr( $experience_bg ); ?>}
            .eop-proposal-wrap{max-width:<?php echo esc_attr( $max_width ); ?>px;margin:32px auto;padding:0 16px 46px;font-family:<?php echo esc_attr( $experience_font_css ); ?>;font-size:<?php echo esc_attr( $base_font_size ); ?>;color:<?php echo esc_attr( $experience_text ); ?>}
            .eop-proposal-card{display:grid;gap:24px}
            .eop-proposal-hero{position:relative;overflow:hidden;display:grid;grid-template-columns:minmax(0,1.15fr) minmax(280px,.85fr);gap:24px;padding:34px;border-radius:<?php echo esc_attr( max( 28, (int) $settings['border_radius'] + 8 ) ); ?>px;background:<?php echo esc_attr( $experience_hero_bg ); ?>;box-shadow:0 28px 68px rgba(15,27,53,.24)}
            .eop-proposal-hero::before{content:"";position:absolute;inset:auto -10% -25% auto;width:340px;height:340px;border-radius:50%;background:radial-gradient(circle,<?php echo esc_attr( self::with_alpha( $experience_accent, '0.28' ) ); ?>,transparent 70%)}
            .eop-proposal-hero > *{position:relative;z-index:1}
            .eop-proposal-hero__main,.eop-proposal-hero__aside{display:grid;gap:18px;align-content:start}
            .eop-proposal-brandline{display:flex;align-items:flex-start;gap:18px}
            .eop-proposal-brand{display:flex;align-items:center;justify-content:center;min-width:110px;min-height:90px;padding:16px 18px;border-radius:26px;background:<?php echo esc_attr( self::with_alpha( $experience_panel_bg, '0.12' ) ); ?>;border:1px solid <?php echo esc_attr( self::with_alpha( $settings['border_color'], '0.18' ) ); ?>;backdrop-filter:blur(10px)}
            .eop-proposal-logo{max-height:60px;max-width:210px}
            .eop-proposal-hero__copy{display:grid;gap:12px;max-width:640px}
            .eop-proposal-hero__top{display:flex;flex-wrap:wrap;gap:10px}
            .eop-proposal-status,.eop-proposal-stage{display:inline-flex;align-items:center;min-height:38px;padding:0 14px;border-radius:999px;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
            .eop-proposal-status{background:<?php echo esc_attr( self::with_alpha( $experience_panel_bg, '0.18' ) ); ?>;color:<?php echo esc_attr( $experience_text ); ?>;border:1px solid <?php echo esc_attr( self::with_alpha( $settings['border_color'], '0.18' ) ); ?>}
            .eop-proposal-stage{background:<?php echo esc_attr( self::with_alpha( $experience_accent, '0.16' ) ); ?>;color:<?php echo esc_attr( $experience_text ); ?>;border:1px solid <?php echo esc_attr( self::with_alpha( $experience_accent, '0.28' ) ); ?>}
            .eop-proposal-eyebrow{display:block;color:<?php echo esc_attr( $experience_muted ); ?>;font-size:11px;font-weight:900;letter-spacing:.18em;text-transform:uppercase}
            .eop-proposal-title{margin:0;font-size:<?php echo esc_attr( $title_font_size ); ?>;line-height:.98;letter-spacing:-.05em;color:<?php echo esc_attr( $experience_text ); ?>}
            .eop-proposal-text{margin:0;max-width:58ch;color:<?php echo esc_attr( $experience_muted ); ?>;font-size:16px;line-height:1.7}
            .eop-proposal-hero__aside{padding:24px;border-radius:28px;background:<?php echo esc_attr( self::with_alpha( $experience_side_bg, '0.9' ) ); ?>;border:1px solid <?php echo esc_attr( self::with_alpha( $settings['border_color'], '0.18' ) ); ?>;backdrop-filter:blur(10px)}
            .eop-proposal-hero__aside-label{color:<?php echo esc_attr( $experience_muted ); ?>;font-size:11px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}
            .eop-proposal-hero__aside strong{font-size:44px;line-height:.95;letter-spacing:-.06em;color:<?php echo esc_attr( $experience_text ); ?>}
            .eop-proposal-hero__aside p{margin:0;color:<?php echo esc_attr( $experience_text ); ?>;line-height:1.65}
            .eop-proposal-hero__meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:4px}
            .eop-proposal-hero__meta-item{padding:14px 16px;border-radius:20px;background:<?php echo esc_attr( self::with_alpha( $experience_panel_bg, '0.84' ) ); ?>;border:1px solid <?php echo esc_attr( self::with_alpha( $settings['border_color'], '0.18' ) ); ?>}
            .eop-proposal-hero__meta-item span{display:block;color:<?php echo esc_attr( $experience_muted ); ?>;font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
            .eop-proposal-hero__meta-item strong{display:block;margin-top:7px;font-size:18px;line-height:1.2;color:<?php echo esc_attr( $experience_text ); ?>}
            .eop-proposal-overview{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(300px,.85fr);gap:24px;align-items:start}
            .eop-proposal-overview__main,.eop-proposal-overview__side{display:grid;gap:18px}
            .eop-proposal-overview__side{position:sticky;top:18px}
            .eop-proposal-section,.eop-proposal-summary-card{padding:24px;border-radius:28px;border:1px solid rgba(15,27,53,.08);box-shadow:0 18px 38px rgba(15,27,53,.08)}
            .eop-proposal-section{background:<?php echo esc_attr( $experience_panel_bg ); ?>;border-color:<?php echo esc_attr( self::with_alpha( $settings['border_color'], '0.22' ) ); ?>}
            .eop-proposal-summary-card{background:<?php echo esc_attr( $experience_side_bg ); ?>;border-color:<?php echo esc_attr( self::with_alpha( $settings['border_color'], '0.22' ) ); ?>}
            .eop-proposal-section__head{display:flex;justify-content:space-between;gap:12px;align-items:flex-end;margin-bottom:18px}
            .eop-proposal-section__eyebrow{display:block;margin-bottom:6px;color:<?php echo esc_attr( $experience_muted ); ?>;font-size:11px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
            .eop-proposal-section__head h2,.eop-proposal-summary-card h2{margin:0;font-size:26px;line-height:1.06;letter-spacing:-.04em;color:<?php echo esc_attr( $experience_text ); ?>}
            .eop-proposal-summary-card__eyebrow{display:block;margin-bottom:8px;color:<?php echo esc_attr( $experience_muted ); ?>;font-size:11px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
            .eop-proposal-meta{display:grid;gap:12px}
            .eop-proposal-meta p{display:grid;gap:6px;margin:0;padding:14px 16px;border:1px solid <?php echo esc_attr( self::with_alpha( $settings['border_color'], '0.18' ) ); ?>;border-radius:18px;background:<?php echo esc_attr( self::with_alpha( $experience_panel_bg, '0.82' ) ); ?>}
            .eop-proposal-meta strong{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:<?php echo esc_attr( $experience_muted ); ?>}
            .eop-proposal-items{display:grid;gap:14px}
            .eop-proposal-item{display:grid;grid-template-columns:108px minmax(0,1fr) auto;gap:18px;align-items:center;padding:18px;border:1px solid <?php echo esc_attr( self::with_alpha( $settings['border_color'], '0.18' ) ); ?>;border-radius:24px;background:<?php echo esc_attr( self::with_alpha( $experience_panel_bg, '0.96' ) ); ?>}
            .eop-proposal-item__media{width:108px;height:108px;border-radius:24px;overflow:hidden;background:<?php echo esc_attr( self::with_alpha( $experience_side_bg, '0.96' ) ); ?>;border:1px solid <?php echo esc_attr( self::with_alpha( $settings['border_color'], '0.18' ) ); ?>;display:flex;align-items:center;justify-content:center}
            .eop-proposal-item__media img{display:block;width:100%;height:100%;object-fit:cover}
            .eop-proposal-item__body{min-width:0}
            .eop-proposal-item__name{margin:0 0 8px;font-size:23px;line-height:1.18;color:<?php echo esc_attr( $experience_text ); ?>}
            .eop-proposal-item__meta{display:flex;flex-wrap:wrap;gap:8px 12px;color:<?php echo esc_attr( $experience_muted ); ?>;font-size:14px}
            .eop-proposal-item__pill{display:inline-flex;align-items:center;min-height:32px;padding:0 12px;border-radius:999px;background:<?php echo esc_attr( self::with_alpha( $experience_accent, '0.12' ) ); ?>;color:<?php echo esc_attr( $experience_accent ); ?>;font-weight:700;line-height:1.2;white-space:nowrap;max-width:100%}
            .eop-proposal-item__pill--discount{display:grid;gap:2px;align-items:flex-start;padding:9px 12px;white-space:normal;border-radius:18px}
            .eop-proposal-item__pill-main{font-size:13px;line-height:1.2}
            .eop-proposal-item__pill-sub{font-size:12px;line-height:1.25;color:<?php echo esc_attr( $experience_muted ); ?>}
            .eop-proposal-item__summary{display:grid;gap:6px;min-width:150px;justify-items:end;text-align:right}
            .eop-proposal-item__summary span{font-size:13px;color:<?php echo esc_attr( $experience_muted ); ?>;text-transform:uppercase;letter-spacing:.08em;font-weight:700}
            .eop-proposal-item__summary strong{font-size:30px;line-height:1;color:<?php echo esc_attr( $experience_text ); ?>}
            .eop-proposal-notes{padding:18px;border:1px solid <?php echo esc_attr( self::with_alpha( $settings['border_color'], '0.18' ) ); ?>;border-radius:22px;background:<?php echo esc_attr( self::with_alpha( $experience_panel_bg, '0.82' ) ); ?>}
            .eop-proposal-notes span{display:block;margin-bottom:8px;color:<?php echo esc_attr( $experience_muted ); ?>;font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
            .eop-proposal-notes p{margin:0;color:<?php echo esc_attr( $experience_text ); ?>}
            .eop-proposal-totals{display:grid;gap:2px}
            .eop-proposal-total{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:9px 0;color:<?php echo esc_attr( $experience_text ); ?>}
            .eop-proposal-total.is-grand{font-size:20px;font-weight:800;border-top:2px solid <?php echo esc_attr( $experience_accent ); ?>;margin-top:8px;padding-top:12px}
            .eop-proposal-total__value{display:grid;justify-items:end;gap:2px;text-align:right}
            .eop-proposal-total__value strong{font-size:15px;line-height:1.1;color:<?php echo esc_attr( $experience_text ); ?>}
            .eop-proposal-total__value small{font-size:12px;line-height:1.25;color:<?php echo esc_attr( $experience_muted ); ?>}
            .eop-proposal-button{display:inline-flex;align-items:center;justify-content:center;min-height:50px;padding:0 22px;border:none;border-radius:<?php echo esc_attr( $settings['border_radius'] ); ?>px;background:<?php echo esc_attr( $experience_accent ); ?>;color:#fff;text-decoration:none;font-weight:700;cursor:pointer;box-shadow:0 16px 30px <?php echo esc_attr( self::with_alpha( $experience_accent, '0.2' ) ); ?>}
            .eop-proposal-button--secondary{background:<?php echo esc_attr( $experience_side_bg ); ?>;color:<?php echo esc_attr( $experience_text ); ?>;box-shadow:none;border:1px solid <?php echo esc_attr( self::with_alpha( $settings['border_color'], '0.18' ) ); ?>}
            .eop-proposal-actions{display:flex;flex-wrap:wrap;gap:12px}
            .eop-proposal-actions form{display:flex;width:100%}
            .eop-proposal-actions .eop-proposal-button{width:100%}
            .eop-proposal-note{margin:0;padding:14px 16px;border-radius:18px;background:#ecfdf5;border:1px solid #bbf7d0;color:#166534}
            .eop-proposal-wrap.is-flow-focus{max-width:<?php echo esc_attr( min( $max_width, 1040 ) ); ?>px;padding-bottom:34px}
            .eop-proposal-wrap.is-flow-focus .eop-proposal-card{gap:0}
            @media (max-width: 980px){.eop-proposal-hero,.eop-proposal-overview{grid-template-columns:1fr}.eop-proposal-overview__side{position:static}.eop-proposal-actions .eop-proposal-button{width:auto}}
            @media (max-width: 720px){.eop-proposal-wrap{font-size:15px;padding:0 10px 30px}.eop-proposal-hero,.eop-proposal-section,.eop-proposal-summary-card{padding:20px;border-radius:24px}.eop-proposal-brandline{flex-direction:column}.eop-proposal-hero__meta{grid-template-columns:1fr}.eop-proposal-title{font-size:<?php echo esc_attr( self::responsive_preview_size( $title_font_size, '28px', -10 ) ); ?>}.eop-proposal-item{grid-template-columns:1fr}.eop-proposal-item__media{width:88px;height:88px}.eop-proposal-item__summary{justify-items:start;text-align:left}.eop-proposal-item__meta{gap:8px}.eop-proposal-total{gap:10px}.eop-proposal-total__value strong{font-size:14px}}
        </style>
        <div class="eop-proposal-wrap<?php echo $confirmed ? ' is-confirmed' : ''; ?><?php echo $is_flow_focus ? ' is-flow-focus' : ''; ?>">
            <div class="eop-proposal-card">
                <?php if ( $is_flow_focus ) : ?>
                    <?php echo EOP_Post_Confirmation_Flow::render_frontend_stage( $order, $line_items, $pdf_url ); ?>
                <?php else : ?>
                <div class="eop-proposal-hero">
                    <div class="eop-proposal-hero__main">
                        <div class="eop-proposal-brandline">
                            <?php if ( $has_logo ) : ?>
                                <div class="eop-proposal-brand">
                                    <img class="eop-proposal-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="">
                                </div>
                            <?php endif; ?>
                            <div class="eop-proposal-hero__copy">
                                <div class="eop-proposal-hero__top">
                                    <div class="eop-proposal-status">
                                        <?php echo esc_html( $confirmed ? __( 'Proposta confirmada', EOP_TEXT_DOMAIN ) : __( 'Aguardando confirmacao', EOP_TEXT_DOMAIN ) ); ?>
                                    </div>
                                    <?php if ( $current_flow_label ) : ?>
                                        <div class="eop-proposal-stage"><?php echo esc_html( sprintf( __( 'Etapa atual: %s', EOP_TEXT_DOMAIN ), $current_flow_label ) ); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ( '' !== $experience_eyebrow ) : ?>
                                    <span class="eop-proposal-eyebrow"><?php echo esc_html( $experience_eyebrow ); ?></span>
                                <?php endif; ?>
                                    <h1 class="eop-proposal-title"><?php echo esc_html( $experience_title ); ?></h1>
                                <p class="eop-proposal-text"><?php echo esc_html( $experience_desc ); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="eop-proposal-hero__aside">
                        <span class="eop-proposal-hero__aside-label"><?php echo esc_html( '' !== $total_label ? $total_label : __( 'Total aprovado', EOP_TEXT_DOMAIN ) ); ?></span>
                        <strong><?php echo wp_kses_post( wc_price( $totals['total'] ?? $order->get_total() ) ); ?></strong>
                        <p><?php echo esc_html( '' !== $total_note ? $total_note : __( 'Revise os itens e conclua a etapa atual para liberar o restante da jornada.', EOP_TEXT_DOMAIN ) ); ?></p>
                        <div class="eop-proposal-hero__meta">
                            <div class="eop-proposal-hero__meta-item">
                                <span><?php esc_html_e( 'Pedido', EOP_TEXT_DOMAIN ); ?></span>
                                <strong>#<?php echo esc_html( $order->get_id() ); ?></strong>
                            </div>
                            <div class="eop-proposal-hero__meta-item">
                                <span><?php esc_html_e( 'Cliente', EOP_TEXT_DOMAIN ); ?></span>
                                <strong><?php echo esc_html( $customer_name ); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="eop-proposal-overview">
                    <div class="eop-proposal-overview__main">
                        <section class="eop-proposal-section">
                            <div class="eop-proposal-section__head">
                                <div>
                                    <?php if ( '' !== $items_eyebrow ) : ?>
                                        <span class="eop-proposal-section__eyebrow"><?php echo esc_html( $items_eyebrow ); ?></span>
                                    <?php endif; ?>
                            <h2><?php echo esc_html( $items_title ?: __( 'Itens', EOP_TEXT_DOMAIN ) ); ?></h2>
                                </div>
                            </div>

                            <div class="eop-proposal-items">
                                <?php foreach ( $line_items as $line_item ) : ?>
                                    <?php
                                    $item      = $line_item['item'];
                                    $product   = $line_item['product'];
                                    $image_url = $product ? wp_get_attachment_image_url( $product->get_image_id(), 'medium' ) : '';

                                    if ( ! $image_url ) {
                                        $image_url = wc_placeholder_img_src( 'medium' );
                                    }
                                    ?>
                                    <article class="eop-proposal-item">
                                        <div class="eop-proposal-item__media">
                                            <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $item->get_name() ); ?>">
                                        </div>
                                        <div class="eop-proposal-item__body">
                                            <h3 class="eop-proposal-item__name"><?php echo esc_html( $item->get_name() ); ?></h3>
                                            <?php if ( ! empty( $item_columns ) || ( $show_sku && $product && $product->get_sku() ) ) : ?>
                                                <div class="eop-proposal-item__meta">
                                                    <?php foreach ( $item_columns as $column ) : ?>
                                                        <?php if ( 'quantity' === $column['key'] ) : ?>
                                                            <span class="eop-proposal-item__pill">
                                                                <?php
                                                                printf(
                                                                    '%1$s: %2$s',
                                                                    esc_html( $column['label'] ),
                                                                    esc_html( $line_item['quantity'] )
                                                                );
                                                                ?>
                                                            </span>
                                                        <?php elseif ( 'unit_price' === $column['key'] ) : ?>
                                                            <span class="eop-proposal-item__pill">
                                                                <?php
                                                                printf(
                                                                    '%1$s: %2$s',
                                                                    esc_html( $column['label'] ),
                                                                    esc_html( wp_strip_all_tags( wc_price( $line_item['unit_price'] ) ) )
                                                                );
                                                                ?>
                                                            </span>
                                                        <?php elseif ( 'discount' === $column['key'] ) : ?>
                                                            <div class="eop-proposal-item__pill eop-proposal-item__pill--discount">
                                                                <strong class="eop-proposal-item__pill-main"><?php echo esc_html( $column['label'] . ': ' . number_format_i18n( $line_item['discount_percent'], abs( $line_item['discount_percent'] - round( $line_item['discount_percent'] ) ) < 0.01 ? 0 : 2 ) . '%' ); ?></strong>
                                                                <small class="eop-proposal-item__pill-sub"><?php echo esc_html( wp_strip_all_tags( wc_price( $line_item['discount_per_unit'] ) ) . ' / ' . __( 'un.', EOP_TEXT_DOMAIN ) ); ?></small>
                                                            </div>
                                                        <?php elseif ( 'discounted_unit_price' === $column['key'] ) : ?>
                                                            <span class="eop-proposal-item__pill">
                                                                <?php
                                                                printf(
                                                                    '%1$s: %2$s',
                                                                    esc_html( $column['label'] ),
                                                                    esc_html( wp_strip_all_tags( wc_price( $line_item['discounted_unit_price'] ) ) )
                                                                );
                                                                ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                    <?php if ( $show_sku && $product && $product->get_sku() ) : ?>
                                                        <span class="eop-proposal-item__pill">
                                                            <?php
                                                            printf(
                                                                /* translators: %s: product sku */
                                                                esc_html__( 'SKU: %s', EOP_TEXT_DOMAIN ),
                                                                esc_html( $product->get_sku() )
                                                            );
                                                            ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ( $show_line_total ) : ?>
                                            <div class="eop-proposal-item__summary">
                                                <span><?php echo esc_html( $item_labels['line_total'] ?? __( 'Total', EOP_TEXT_DOMAIN ) ); ?></span>
                                                <strong><?php echo wp_kses_post( wc_price( $line_item['line_total'] ) ); ?></strong>
                                            </div>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <?php if ( $show_notes && '' !== $customer_note ) : ?>
                            <section class="eop-proposal-section">
                                <div class="eop-proposal-notes">
                                    <span><?php esc_html_e( 'Observacoes', EOP_TEXT_DOMAIN ); ?></span>
                                    <p><?php echo esc_html( $customer_note ); ?></p>
                                </div>
                            </section>
                        <?php endif; ?>

                        <?php if ( '1' === $confirm_state ) : ?>
                            <p class="eop-proposal-note"><?php esc_html_e( 'Proposta confirmada com sucesso.', EOP_TEXT_DOMAIN ); ?></p>
                        <?php endif; ?>
                    </div>

                    <aside class="eop-proposal-overview__side">
                        <section class="eop-proposal-summary-card">
                            <span class="eop-proposal-summary-card__eyebrow"><?php echo esc_html( '' !== $summary_eyebrow ? $summary_eyebrow : __( 'Contexto do pedido', EOP_TEXT_DOMAIN ) ); ?></span>
                            <h2><?php echo esc_html( $summary_title ?: __( 'Contexto do pedido', EOP_TEXT_DOMAIN ) ); ?></h2>
                            <div class="eop-proposal-meta">
                                <p><strong><?php esc_html_e( 'Pedido', EOP_TEXT_DOMAIN ); ?></strong><span>#<?php echo esc_html( $order->get_id() ); ?></span></p>
                                <p><strong><?php esc_html_e( 'Cliente', EOP_TEXT_DOMAIN ); ?></strong><span><?php echo esc_html( $customer_name ); ?></span></p>
                                <?php if ( $show_email && $order->get_billing_email() ) : ?>
                                    <p><strong><?php esc_html_e( 'E-mail', EOP_TEXT_DOMAIN ); ?></strong><span><?php echo esc_html( $order->get_billing_email() ); ?></span></p>
                                <?php endif; ?>
                                <?php if ( $show_phone && $order->get_billing_phone() ) : ?>
                                    <p><strong><?php esc_html_e( 'Telefone', EOP_TEXT_DOMAIN ); ?></strong><span><?php echo esc_html( $order->get_billing_phone() ); ?></span></p>
                                <?php endif; ?>
                            </div>
                        </section>

                        <?php if ( ! empty( $total_rows ) ) : ?>
                            <section class="eop-proposal-summary-card">
                                <?php if ( '' !== $financial_eyebrow ) : ?>
                                    <span class="eop-proposal-summary-card__eyebrow"><?php echo esc_html( $financial_eyebrow ); ?></span>
                                <?php endif; ?>
                                <h2><?php echo esc_html( $financial_title ?: __( 'Resumo', EOP_TEXT_DOMAIN ) ); ?></h2>
                                <div class="eop-proposal-totals">
                                    <?php foreach ( $total_rows as $row ) : ?>
                                        <div class="eop-proposal-total <?php echo esc_attr( $row['class'] ); ?>">
                                            <span><?php echo esc_html( $row['label'] ); ?></span>
                                            <?php if ( ! empty( $row['main_value'] ) ) : ?>
                                                <div class="eop-proposal-total__value">
                                                    <strong><?php echo esc_html( $row['main_value'] ); ?></strong>
                                                    <?php if ( ! empty( $row['sub_value'] ) ) : ?>
                                                        <small><?php echo esc_html( $row['sub_value'] ); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else : ?>
                                                <span><?php echo wp_kses_post( $row['value'] ); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <?php if ( ! $confirmed ) : ?>
                            <section class="eop-proposal-summary-card">
                                <span class="eop-proposal-summary-card__eyebrow"><?php echo esc_html( '' !== $actions_eyebrow ? $actions_eyebrow : __( 'Confirmacao da proposta', EOP_TEXT_DOMAIN ) ); ?></span>
                                <h2><?php echo esc_html( $actions_title ?: __( 'Confirmacao da proposta', EOP_TEXT_DOMAIN ) ); ?></h2>
                                <div class="eop-proposal-actions">
                                    <form method="post">
                                        <?php wp_nonce_field( 'eop_confirm_proposal', 'eop_confirm_proposal_nonce' ); ?>
                                        <input type="hidden" name="eop_proposal_token" value="<?php echo esc_attr( $order->get_meta( '_eop_public_token', true ) ); ?>" />
                                        <button type="submit" class="eop-proposal-button"><?php echo esc_html( $button_label ); ?></button>
                                    </form>
                                    <?php if ( $pdf_url ) : ?>
                                        <a class="eop-proposal-button" href="<?php echo esc_url( $pdf_url ); ?>" download="<?php echo esc_attr( $order->get_id() . '.pdf' ); ?>">
                                            <?php esc_html_e( 'Baixar PDF', EOP_TEXT_DOMAIN ); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </section>
                        <?php elseif ( ! $flow_enabled && ( $payment_url || $pdf_url ) ) : ?>
                            <section class="eop-proposal-summary-card">
                                <span class="eop-proposal-summary-card__eyebrow"><?php echo esc_html( '' !== $actions_eyebrow ? $actions_eyebrow : __( 'Acoes rapidas', EOP_TEXT_DOMAIN ) ); ?></span>
                                <h2><?php echo esc_html( $actions_title ?: __( 'Acoes rapidas', EOP_TEXT_DOMAIN ) ); ?></h2>
                                <div class="eop-proposal-actions">
                                    <?php if ( $payment_url ) : ?>
                                        <a class="eop-proposal-button" href="<?php echo esc_url( $payment_url ); ?>">
                                            <?php echo esc_html( $pay_label ); ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ( $pdf_url ) : ?>
                                        <a class="eop-proposal-button" href="<?php echo esc_url( $pdf_url ); ?>" download="<?php echo esc_attr( $order->get_id() . '.pdf' ); ?>">
                                            <?php esc_html_e( 'Baixar PDF', EOP_TEXT_DOMAIN ); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </section>
                        <?php endif; ?>
                    </aside>
                </div>

                <?php if ( $confirmed && $flow_enabled ) : ?>
                    <?php echo EOP_Post_Confirmation_Flow::render_frontend_stage( $order, $line_items, $pdf_url ); ?>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    private static function normalize_customer_experience_copy( $value, $legacy, $replacement ) {
        $value = trim( (string) $value );

        return $legacy === $value ? $replacement : $value;
    }

    public static function render_admin_preview_card( $settings = array() ) {
        $settings = is_array( $settings ) ? wp_parse_args( $settings, EOP_Settings::get_defaults() ) : EOP_Settings::get_all();
        $srcdoc   = self::get_admin_preview_srcdoc( $settings );

        ob_start();
        ?>
        <div class="eop-proposal-preview-card" data-eop-proposal-preview-card data-preview-viewport="desktop">
            <div class="eop-proposal-preview-card__copy">
                <div class="eop-proposal-preview-card__eyebrow"><?php esc_html_e( 'Preview ao vivo', EOP_TEXT_DOMAIN ); ?></div>
                <h3><?php esc_html_e( 'Veja a pagina publica antes de salvar', EOP_TEXT_DOMAIN ); ?></h3>
                <p><?php esc_html_e( 'Este preview usa a mesma identidade visual da pagina do cliente e reage aos campos editados nesta tela. As mudancas de cor, fonte e logo aparecem aqui primeiro.', EOP_TEXT_DOMAIN ); ?></p>
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
                    <iframe class="eop-proposal-preview-render" title="<?php esc_attr_e( 'Preview da pagina do cliente', EOP_TEXT_DOMAIN ); ?>" srcdoc="<?php echo esc_attr( $srcdoc ); ?>"></iframe>
                </div>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private static function responsive_preview_size( $value, $fallback = '28px', $px_delta = 0 ) {
        $value = is_string( $value ) ? trim( $value ) : '';

        if ( preg_match( '/^\s*(\d+(?:\.\d+)?)px\s*$/i', $value, $matches ) ) {
            return max( 0, (float) $matches[1] + (float) $px_delta ) . 'px';
        }

        return $fallback;
    }

    public static function get_admin_preview_srcdoc( $settings = array() ) {
        $markup = self::render_admin_preview_markup( $settings );

        ob_start();
        ?>
<!doctype html>
<html>
<head>
    <meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            background: transparent;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            color: var(--eop-preview-text, #16243a);
            font-family: var(--eop-preview-font-family, 'Segoe UI', sans-serif);
            font-size: var(--eop-preview-text-size, 16px);
        }

        img {
            max-width: 100%;
            height: auto;
        }

        .eop-proposal-preview-frame {
            min-height: 940px;
            padding: 18px;
            background: var(--eop-preview-page-bg, #f5f7ff);
        }

        .eop-proposal-wrap--preview {
            max-width: var(--eop-preview-max-width, 1120px);
            margin: 0 auto;
            padding: 6px 0 30px;
        }

        .eop-proposal-card {
            display: grid;
            gap: 20px;
        }

        .eop-proposal-hero {
            position: relative;
            overflow: hidden;
            display: grid;
            grid-template-columns: minmax(0, 1.12fr) minmax(280px, 0.88fr);
            gap: 22px;
            padding: 30px;
            border-radius: calc(var(--eop-preview-radius, 18px) + 10px);
            background: var(--eop-preview-hero-bg, #0f1b35);
            box-shadow: 0 28px 68px rgba(15, 27, 53, 0.24);
        }

        .eop-proposal-hero::before {
            content: "";
            position: absolute;
            inset: auto -10% -30% auto;
            width: 340px;
            height: 340px;
            border-radius: 50%;
            background: radial-gradient(circle, var(--eop-preview-accent-glow, rgba(215, 138, 47, 0.28)), transparent 70%);
        }

        .eop-proposal-hero > * {
            position: relative;
            z-index: 1;
        }

        .eop-proposal-hero__main,
        .eop-proposal-hero__aside {
            display: grid;
            gap: 18px;
            align-content: start;
        }

        .eop-proposal-brandline {
            display: flex;
            align-items: flex-start;
            gap: 18px;
        }

        .eop-proposal-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 110px;
            min-height: 90px;
            padding: 16px 18px;
            border-radius: 26px;
            background: var(--eop-preview-brand-bg, rgba(255, 255, 255, 0.12));
            border: 1px solid var(--eop-preview-border-soft, rgba(255, 255, 255, 0.18));
            backdrop-filter: blur(10px);
        }

        .eop-proposal-brand img {
            display: block;
            max-width: 210px;
            max-height: 60px;
            object-fit: contain;
        }

        .eop-proposal-brand__fallback {
            color: var(--eop-preview-text, #fff);
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            text-align: center;
        }

        .eop-proposal-brand__fallback.is-hidden {
            display: none;
        }

        .eop-proposal-hero__copy {
            display: grid;
            gap: 12px;
            max-width: 640px;
        }

        .eop-proposal-hero__top {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .eop-proposal-status,
        .eop-proposal-stage {
            display: inline-flex;
            align-items: center;
            min-height: 38px;
            padding: 0 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .eop-proposal-status {
            background: var(--eop-preview-panel-soft, rgba(255, 255, 255, 0.18));
            color: var(--eop-preview-text, #fff);
            border: 1px solid var(--eop-preview-border-soft, rgba(255, 255, 255, 0.18));
        }

        .eop-proposal-stage {
            background: var(--eop-preview-accent-soft, rgba(215, 138, 47, 0.16));
            color: var(--eop-preview-text, #fff);
            border: 1px solid var(--eop-preview-accent-border, rgba(215, 138, 47, 0.28));
        }

        .eop-proposal-eyebrow,
        .eop-proposal-section__eyebrow,
        .eop-proposal-summary-card__eyebrow,
        .eop-proposal-notes span,
        .eop-proposal-hero__aside-label,
        .eop-proposal-item__summary span,
        .eop-proposal-meta strong {
            display: block;
            color: var(--eop-preview-muted, #66768d);
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .eop-proposal-title {
            margin: 0;
            font-size: var(--eop-preview-title-size, 46px);
            line-height: 0.98;
            letter-spacing: -0.05em;
            color: var(--eop-preview-text, #16243a);
        }

        .eop-proposal-text {
            margin: 0;
            max-width: 58ch;
            color: var(--eop-preview-muted, #66768d);
            font-size: var(--eop-preview-text-size, 16px);
            line-height: 1.7;
        }

        .eop-proposal-hero__aside {
            padding: 24px;
            border-radius: 28px;
            background: var(--eop-preview-side-bg, #f6f8fc);
            border: 1px solid var(--eop-preview-border-soft, rgba(255, 255, 255, 0.18));
            backdrop-filter: blur(10px);
        }

        .eop-proposal-hero__aside strong {
            font-size: 44px;
            line-height: 0.95;
            letter-spacing: -0.06em;
            color: var(--eop-preview-text, #16243a);
        }

        .eop-proposal-hero__aside p {
            margin: 0;
            color: var(--eop-preview-text, #16243a);
            line-height: 1.65;
        }

        .eop-proposal-hero__meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 4px;
        }

        .eop-proposal-hero__meta-item {
            padding: 14px 16px;
            border-radius: 20px;
            background: var(--eop-preview-panel-bg, #fff);
            border: 1px solid var(--eop-preview-border-soft, rgba(255, 255, 255, 0.18));
        }

        .eop-proposal-hero__meta-item strong {
            display: block;
            margin-top: 7px;
            font-size: 18px;
            line-height: 1.2;
            color: var(--eop-preview-text, #16243a);
        }

        .eop-proposal-overview {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(300px, 0.85fr);
            gap: 24px;
            align-items: start;
        }

        .eop-proposal-overview__main,
        .eop-proposal-overview__side {
            display: grid;
            gap: 18px;
        }

        .eop-proposal-overview__side {
            position: sticky;
            top: 18px;
        }

        .eop-proposal-section,
        .eop-proposal-summary-card {
            padding: 24px;
            border-radius: 28px;
            border: 1px solid rgba(15, 27, 53, 0.08);
            box-shadow: 0 18px 38px rgba(15, 27, 53, 0.08);
        }

        .eop-proposal-section {
            background: var(--eop-preview-panel-bg, #fff);
            border-color: var(--eop-preview-border-soft, rgba(255, 255, 255, 0.18));
        }

        .eop-proposal-summary-card {
            background: var(--eop-preview-side-bg, #f6f8fc);
            border-color: var(--eop-preview-border-soft, rgba(255, 255, 255, 0.18));
        }

        .eop-proposal-section__head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-end;
            margin-bottom: 18px;
        }

        .eop-proposal-section__head h2,
        .eop-proposal-summary-card h2 {
            margin: 0;
            font-size: 26px;
            line-height: 1.06;
            letter-spacing: -0.04em;
            color: var(--eop-preview-text, #16243a);
        }

        .eop-proposal-items {
            display: grid;
            gap: 14px;
        }

        .eop-proposal-item {
            display: grid;
            grid-template-columns: 96px minmax(0, 1fr) auto;
            gap: 18px;
            align-items: center;
            padding: 18px;
            border: 1px solid var(--eop-preview-border-soft, rgba(255, 255, 255, 0.18));
            border-radius: 24px;
            background: var(--eop-preview-panel-bg, #fff);
        }

        .eop-proposal-item__media {
            width: 96px;
            height: 96px;
            border-radius: 22px;
            overflow: hidden;
            background: var(--eop-preview-side-bg, #f6f8fc);
            border: 1px solid var(--eop-preview-border-soft, rgba(255, 255, 255, 0.18));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--eop-preview-muted, #66768d);
            font-size: 20px;
            font-weight: 900;
            letter-spacing: -0.04em;
        }

        .eop-proposal-item__body {
            min-width: 0;
        }

        .eop-proposal-item__name {
            margin: 0 0 8px;
            font-size: 22px;
            line-height: 1.18;
            color: var(--eop-preview-text, #16243a);
        }

        .eop-proposal-item__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 12px;
            color: var(--eop-preview-muted, #66768d);
            font-size: 14px;
        }

        .eop-proposal-item__pill {
            display: inline-flex;
            align-items: center;
            min-height: 32px;
            padding: 0 12px;
            border-radius: 999px;
            background: var(--eop-preview-accent-soft, rgba(215, 138, 47, 0.12));
            color: var(--eop-preview-accent, #d78a2f);
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
            max-width: 100%;
        }

        .eop-proposal-item__summary {
            display: grid;
            gap: 6px;
            min-width: 150px;
            justify-items: end;
            text-align: right;
        }

        .eop-proposal-item__summary strong {
            font-size: 30px;
            line-height: 1;
            color: var(--eop-preview-text, #16243a);
        }

        .eop-proposal-notes {
            padding: 18px;
            border: 1px solid var(--eop-preview-border-soft, rgba(255, 255, 255, 0.18));
            border-radius: 22px;
            background: var(--eop-preview-panel-bg, #fff);
        }

        .eop-proposal-notes p {
            margin: 0;
            color: var(--eop-preview-text, #16243a);
        }

        .eop-proposal-totals {
            display: grid;
            gap: 2px;
        }

        .eop-proposal-total {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            padding: 9px 0;
            color: var(--eop-preview-text, #16243a);
        }

        .eop-proposal-total.is-grand {
            font-size: 20px;
            font-weight: 800;
            border-top: 2px solid var(--eop-preview-accent, #d78a2f);
            margin-top: 8px;
            padding-top: 12px;
        }

        .eop-proposal-total__value {
            display: grid;
            justify-items: end;
            gap: 2px;
            text-align: right;
        }

        .eop-proposal-total__value strong {
            font-size: 15px;
            line-height: 1.1;
            color: var(--eop-preview-text, #16243a);
        }

        .eop-proposal-total__value small {
            font-size: 12px;
            line-height: 1.25;
            color: var(--eop-preview-muted, #66768d);
        }

        .eop-proposal-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 50px;
            padding: 0 22px;
            border: none;
            border-radius: var(--eop-preview-radius, 18px);
            background: var(--eop-preview-accent, #d78a2f);
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 16px 30px var(--eop-preview-accent-shadow, rgba(215, 138, 47, 0.2));
        }

        .eop-proposal-button--secondary {
            background: var(--eop-preview-side-bg, #f6f8fc);
            color: var(--eop-preview-text, #16243a);
            box-shadow: none;
            border: 1px solid var(--eop-preview-border-soft, rgba(255, 255, 255, 0.18));
        }

        .eop-proposal-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .eop-proposal-actions .eop-proposal-button {
            width: 100%;
        }

        .eop-proposal-note {
            margin: 0;
            padding: 14px 16px;
            border-radius: 18px;
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        @media (max-width: 980px) {
            .eop-proposal-hero,
            .eop-proposal-overview {
                grid-template-columns: 1fr;
            }

            .eop-proposal-overview__side {
                position: static;
            }

            .eop-proposal-actions .eop-proposal-button {
                width: auto;
            }
        }

        @media (max-width: 720px) {
            .eop-proposal-preview-frame {
                padding: 12px;
            }

            .eop-proposal-hero,
            .eop-proposal-section,
            .eop-proposal-summary-card {
                padding: 20px;
                border-radius: 24px;
            }

            .eop-proposal-brandline {
                flex-direction: column;
            }

            .eop-proposal-hero__meta {
                grid-template-columns: 1fr;
            }

            .eop-proposal-title {
                font-size: calc(var(--eop-preview-title-size, 46px) - 10px);
            }

            .eop-proposal-item {
                grid-template-columns: 1fr;
            }

            .eop-proposal-item__media {
                width: 86px;
                height: 86px;
            }

            .eop-proposal-item__summary {
                justify-items: start;
                text-align: left;
            }

            .eop-proposal-total {
                gap: 10px;
            }

            .eop-proposal-total__value strong {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="eop-proposal-preview-frame">
        <?php echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
</body>
</html>
        <?php

        return (string) ob_get_clean();
    }

    public static function render_admin_preview_markup( $settings = array() ) {
        $settings = is_array( $settings ) ? wp_parse_args( $settings, EOP_Settings::get_defaults() ) : EOP_Settings::get_all();
        $theme    = self::get_public_proposal_theme( $settings );
        $logo_url = trim( (string) ( $settings['brand_logo_url'] ?? '' ) );
        $title    = trim( (string) ( $settings['proposal_title'] ?? $theme['title'] ) );
        $description = trim( (string) ( $settings['proposal_description'] ?? $theme['description'] ) );
        $items_eyebrow = trim( (string) ( $theme['items_eyebrow'] ?: __( 'Resumo visual', EOP_TEXT_DOMAIN ) ) );
        $items_title = trim( (string) ( $theme['items_title'] ?: __( 'Itens', EOP_TEXT_DOMAIN ) ) );
        $summary_eyebrow = trim( (string) ( $theme['summary_eyebrow'] ?: __( 'Contexto rapido', EOP_TEXT_DOMAIN ) ) );
        $summary_title = trim( (string) ( $theme['summary_title'] ?: __( 'Visao do pedido', EOP_TEXT_DOMAIN ) ) );
        $financial_eyebrow = trim( (string) ( $theme['financial_eyebrow'] ?: __( 'Fechamento', EOP_TEXT_DOMAIN ) ) );
        $financial_title = trim( (string) ( $theme['financial_title'] ?: __( 'Resumo', EOP_TEXT_DOMAIN ) ) );
        $actions_eyebrow = trim( (string) ( $theme['actions_eyebrow'] ?: __( 'Proxima acao', EOP_TEXT_DOMAIN ) ) );
        $actions_title = trim( (string) ( $theme['actions_title'] ?: __( 'Como seguir agora', EOP_TEXT_DOMAIN ) ) );
        $total_label = trim( (string) ( $theme['total_label'] ?: __( 'Investimento aprovado', EOP_TEXT_DOMAIN ) ) );
        $total_note = trim( (string) ( $theme['total_note'] ?: __( 'Assim que a etapa atual for concluida, o pedido segue para o time responsavel.', EOP_TEXT_DOMAIN ) ) );
        $accent = $theme['accent_color'];
        $text = $theme['text_color'];
        $muted = $theme['muted_color'];
        $page_bg = $theme['background_css'];
        $hero_bg = $theme['hero_background_css'];
        $panel_bg = $theme['panel_background_css'];
        $side_bg = $theme['sidebar_background_css'];
        $font_css = $theme['font_css'];
        $max_width = $theme['max_width'];
        $base_font_size = $theme['base_font_size'];
        $title_font_size = $theme['title_font_size'];
        $radius = absint( $settings['border_radius'] );
        $total_value = function_exists( 'wc_price' ) ? wc_price( 1499.9 ) : 'R$ 1.499,90';
        $discount_value = function_exists( 'wc_price' ) ? wc_price( 120 ) : 'R$ 120,00';

        ob_start();
        ?>
        <div
            class="eop-proposal-wrap eop-proposal-wrap--preview"
            data-eop-proposal-preview-root
            style="<?php echo esc_attr( sprintf(
                '--eop-preview-page-bg:%1$s;--eop-preview-hero-bg:%2$s;--eop-preview-panel-bg:%3$s;--eop-preview-side-bg:%4$s;--eop-preview-accent:%5$s;--eop-preview-text:%6$s;--eop-preview-muted:%7$s;--eop-preview-radius:%8$dpx;--eop-preview-font-family:%9$s;--eop-preview-max-width:%10$dpx;--eop-preview-title-size:%11$s;--eop-preview-text-size:%12$s;--eop-preview-brand-bg:%13$s;--eop-preview-panel-soft:%14$s;--eop-preview-border-soft:%15$s;--eop-preview-accent-soft:%16$s;--eop-preview-accent-border:%17$s;--eop-preview-accent-glow:%18$s;--eop-preview-accent-shadow:%19$s;',
                $page_bg,
                $hero_bg,
                $panel_bg,
                $side_bg,
                $accent,
                $text,
                $muted,
                $radius,
                $font_css,
                absint( $max_width ),
                $title_font_size,
                $base_font_size,
                self::with_alpha( $panel_bg, '0.12' ),
                self::with_alpha( $panel_bg, '0.18' ),
                self::with_alpha( $settings['border_color'], '0.18' ),
                self::with_alpha( $accent, '0.12' ),
                self::with_alpha( $accent, '0.28' ),
                self::with_alpha( $accent, '0.28' ),
                self::with_alpha( $accent, '0.20' )
            ) ); ?>"
        >
            <div class="eop-proposal-card">
                <div class="eop-proposal-hero">
                    <div class="eop-proposal-hero__main">
                        <div class="eop-proposal-brandline">
                            <div class="eop-proposal-brand<?php echo $logo_url ? '' : ' is-empty'; ?>" data-preview-logo-wrap>
                                <?php if ( $logo_url ) : ?>
                                    <img data-preview-logo src="<?php echo esc_url( $logo_url ); ?>" alt="">
                                <?php else : ?>
                                    <span class="eop-proposal-brand__fallback" data-preview-logo-fallback><?php esc_html_e( 'Logo opcional', EOP_TEXT_DOMAIN ); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="eop-proposal-hero__copy">
                                <div class="eop-proposal-hero__top">
                                    <div class="eop-proposal-status" data-preview-status><?php esc_html_e( 'Preview ao vivo', EOP_TEXT_DOMAIN ); ?></div>
                                    <div class="eop-proposal-stage" data-preview-stage><?php esc_html_e( 'Layout real', EOP_TEXT_DOMAIN ); ?></div>
                                </div>
                                <?php if ( '' !== $theme['eyebrow'] ) : ?>
                                    <span class="eop-proposal-eyebrow" data-preview-eyebrow><?php echo esc_html( $theme['eyebrow'] ); ?></span>
                                <?php else : ?>
                                    <span class="eop-proposal-eyebrow" data-preview-eyebrow><?php esc_html_e( 'Experiencia do cliente', EOP_TEXT_DOMAIN ); ?></span>
                                <?php endif; ?>
                                <h1 class="eop-proposal-title" data-preview-title><?php echo esc_html( $title ); ?></h1>
                                <p class="eop-proposal-text" data-preview-description><?php echo esc_html( $description ); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="eop-proposal-hero__aside">
                        <span class="eop-proposal-hero__aside-label" data-preview-total-label><?php echo esc_html( $total_label ); ?></span>
                        <strong data-preview-total-value><?php echo wp_kses_post( $total_value ); ?></strong>
                        <p data-preview-total-note><?php echo esc_html( $total_note ); ?></p>
                        <div class="eop-proposal-hero__meta">
                            <div class="eop-proposal-hero__meta-item">
                                <span><?php esc_html_e( 'Pedido', EOP_TEXT_DOMAIN ); ?></span>
                                <strong>#2026-048</strong>
                            </div>
                            <div class="eop-proposal-hero__meta-item">
                                <span><?php esc_html_e( 'Cliente', EOP_TEXT_DOMAIN ); ?></span>
                                <strong><?php esc_html_e( 'Maria Oliveira', EOP_TEXT_DOMAIN ); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="eop-proposal-overview">
                    <div class="eop-proposal-overview__main">
                        <section class="eop-proposal-section">
                            <div class="eop-proposal-section__head">
                                <div>
                                    <?php if ( '' !== $items_eyebrow ) : ?>
                                        <span class="eop-proposal-section__eyebrow" data-preview-items-eyebrow><?php echo esc_html( $items_eyebrow ); ?></span>
                                    <?php endif; ?>
                                    <h2 data-preview-items-title><?php echo esc_html( $items_title ); ?></h2>
                                </div>
                            </div>

                            <div class="eop-proposal-items">
                                <article class="eop-proposal-item">
                                    <div class="eop-proposal-item__media">01</div>
                                    <div class="eop-proposal-item__body">
                                        <h3 class="eop-proposal-item__name"><?php esc_html_e( 'Kit Nutrição Intensa', EOP_TEXT_DOMAIN ); ?></h3>
                                        <div class="eop-proposal-item__meta">
                                            <span class="eop-proposal-item__pill"><?php esc_html_e( 'SKU A-102', EOP_TEXT_DOMAIN ); ?></span>
                                            <span class="eop-proposal-item__pill"><?php esc_html_e( '2 unidades', EOP_TEXT_DOMAIN ); ?></span>
                                        </div>
                                    </div>
                                    <div class="eop-proposal-item__summary">
                                        <span><?php esc_html_e( 'Subtotal', EOP_TEXT_DOMAIN ); ?></span>
                                        <strong><?php echo wp_kses_post( $total_value ); ?></strong>
                                    </div>
                                </article>

                                <article class="eop-proposal-item">
                                    <div class="eop-proposal-item__media">02</div>
                                    <div class="eop-proposal-item__body">
                                        <h3 class="eop-proposal-item__name"><?php esc_html_e( 'Sérum Reparador', EOP_TEXT_DOMAIN ); ?></h3>
                                        <div class="eop-proposal-item__meta">
                                            <span class="eop-proposal-item__pill"><?php esc_html_e( 'Brinde de campanha', EOP_TEXT_DOMAIN ); ?></span>
                                            <span class="eop-proposal-item__pill"><?php esc_html_e( 'Amostra', EOP_TEXT_DOMAIN ); ?></span>
                                        </div>
                                    </div>
                                    <div class="eop-proposal-item__summary">
                                        <span><?php esc_html_e( 'Desconto', EOP_TEXT_DOMAIN ); ?></span>
                                        <strong>- <?php echo wp_kses_post( $discount_value ); ?></strong>
                                    </div>
                                </article>
                            </div>
                        </section>
                    </div>

                    <div class="eop-proposal-overview__side">
                        <aside class="eop-proposal-summary-card">
                            <div class="eop-proposal-summary-card__eyebrow" data-preview-summary-eyebrow><?php echo esc_html( $summary_eyebrow ); ?></div>
                            <h2 data-preview-summary-title><?php echo esc_html( $summary_title ); ?></h2>
                            <div class="eop-proposal-meta" style="display:grid;gap:12px;margin-top:16px">
                                <p>
                                    <strong><?php esc_html_e( 'Status', EOP_TEXT_DOMAIN ); ?></strong>
                                    <span><?php esc_html_e( 'Aguardando confirmação', EOP_TEXT_DOMAIN ); ?></span>
                                </p>
                                <p>
                                    <strong><?php esc_html_e( 'Prazo', EOP_TEXT_DOMAIN ); ?></strong>
                                    <span><?php esc_html_e( 'Entrega em até 3 dias úteis', EOP_TEXT_DOMAIN ); ?></span>
                                </p>
                            </div>
                        </aside>

                        <aside class="eop-proposal-summary-card">
                            <div class="eop-proposal-summary-card__eyebrow" data-preview-financial-eyebrow><?php echo esc_html( $financial_eyebrow ); ?></div>
                            <h2 data-preview-financial-title><?php echo esc_html( $financial_title ); ?></h2>
                            <div class="eop-proposal-totals" style="margin-top:16px">
                                <div class="eop-proposal-total">
                                    <span><?php esc_html_e( 'Subtotal', EOP_TEXT_DOMAIN ); ?></span>
                                    <div class="eop-proposal-total__value">
                                        <strong><?php echo wp_kses_post( $total_value ); ?></strong>
                                        <small><?php esc_html_e( 'Valor base dos itens', EOP_TEXT_DOMAIN ); ?></small>
                                    </div>
                                </div>
                                <div class="eop-proposal-total">
                                    <span><?php esc_html_e( 'Desconto', EOP_TEXT_DOMAIN ); ?></span>
                                    <div class="eop-proposal-total__value">
                                        <strong>- <?php echo wp_kses_post( $discount_value ); ?></strong>
                                        <small><?php esc_html_e( 'Campanha aplicada', EOP_TEXT_DOMAIN ); ?></small>
                                    </div>
                                </div>
                                <div class="eop-proposal-total is-grand">
                                    <span><?php esc_html_e( 'Total', EOP_TEXT_DOMAIN ); ?></span>
                                    <div class="eop-proposal-total__value">
                                        <strong><?php echo wp_kses_post( $total_value ); ?></strong>
                                        <small><?php esc_html_e( 'Valor final exibido ao cliente', EOP_TEXT_DOMAIN ); ?></small>
                                    </div>
                                </div>
                            </div>
                        </aside>

                        <aside class="eop-proposal-summary-card">
                            <div class="eop-proposal-summary-card__eyebrow" data-preview-actions-eyebrow><?php echo esc_html( $actions_eyebrow ); ?></div>
                            <h2 data-preview-actions-title><?php echo esc_html( $actions_title ); ?></h2>
                            <div class="eop-proposal-actions" style="margin-top:16px">
                                <button type="button" class="eop-proposal-button" data-preview-confirm-button><?php echo esc_html( $settings['proposal_button_label'] ); ?></button>
                                <button type="button" class="eop-proposal-button eop-proposal-button--secondary" data-preview-pay-button><?php echo esc_html( $settings['proposal_pay_button_label'] ); ?></button>
                            </div>
                            <p class="eop-proposal-note" style="margin-top:16px"><?php esc_html_e( 'Este bloco simula a jornada publica com a mesma hierarquia visual usada pelo cliente.', EOP_TEXT_DOMAIN ); ?></p>
                        </aside>
                    </div>
                </div>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private static function get_public_proposal_theme( $settings ) {
        $settings = is_array( $settings ) ? $settings : EOP_Settings::get_all();
        $defaults = method_exists( 'EOP_Settings', 'get_defaults' ) ? EOP_Settings::get_defaults() : array();

        $resolve = static function( $custom_key, $fallback_key = '', $fallback_default = '' ) use ( $settings, $defaults ) {
            $custom_value = isset( $settings[ $custom_key ] ) ? trim( (string) $settings[ $custom_key ] ) : '';
            $default_value = isset( $defaults[ $custom_key ] ) ? trim( (string) $defaults[ $custom_key ] ) : '';

            if ( '' !== $custom_value && $custom_value !== $default_value ) {
                return $custom_value;
            }

            if ( '' !== $fallback_key && isset( $settings[ $fallback_key ] ) ) {
                $fallback_value = trim( (string) $settings[ $fallback_key ] );

                if ( '' !== $fallback_value ) {
                    return $fallback_value;
                }
            }

            return $fallback_default;
        };

        $font_value = $resolve( 'customer_experience_font_family', 'font_family', 'Montserrat:400,700' );
        $font_css   = method_exists( 'EOP_Settings', 'get_font_css_family' ) ? EOP_Settings::get_font_css_family( $font_value ) : "'Segoe UI', sans-serif";
        $font_url   = method_exists( 'EOP_Settings', 'get_font_stylesheet_url' ) ? EOP_Settings::get_font_stylesheet_url( $font_value ) : '';

        $normalize_size = static function ( $value, $fallback ) {
            $value = is_string( $value ) ? trim( $value ) : '';

            if ( '' === $value ) {
                return (string) $fallback;
            }

            if ( preg_match( '/^\d+(?:\.\d+)?$/', $value ) ) {
                return $value . 'px';
            }

            $normalized = preg_replace( '/[^0-9a-zA-Z#.%\s,\-()\/]/', '', $value );

            return '' !== trim( (string) $normalized ) ? trim( (string) $normalized ) : (string) $fallback;
        };

        $base_font_size  = $normalize_size( $resolve( 'customer_experience_text_size', 'proposal_text_size', '16px' ), '16px' );
        $title_font_size = $normalize_size( $resolve( 'customer_experience_title_size', 'proposal_title_size', '40px' ), '40px' );

        return array(
            'font_css'               => $font_css,
            'font_url'               => $font_url,
            'base_font_size'         => $base_font_size,
            'title_font_size'        => $title_font_size,
            'max_width'              => max( 720, absint( $settings['proposal_max_width'] ?? 1120 ) ),
            'background_css'         => self::build_background_css(
                $resolve( 'customer_experience_background_mode', '', 'solid' ) === 'gradient' ? 'gradient' : 'solid',
                $resolve( 'customer_experience_background_color', 'proposal_background_color', '#f5f7ff' ),
                $resolve( 'customer_experience_background_secondary_color', '', '#f7f9fc' )
            ),
            'hero_background_css'    => self::build_background_css(
                $resolve( 'customer_experience_hero_background_mode', '', 'solid' ) === 'gradient' ? 'gradient' : 'solid',
                $resolve( 'customer_experience_hero_background_color', 'primary_color', '#00034b' ),
                $resolve( 'customer_experience_hero_background_secondary_color', '', '#243553' )
            ),
            'panel_background_css'   => self::build_background_css(
                $resolve( 'customer_experience_panel_background_mode', '', 'solid' ) === 'gradient' ? 'gradient' : 'solid',
                $resolve( 'customer_experience_panel_background_color', 'proposal_card_color', '#ffffff' ),
                $resolve( 'customer_experience_panel_background_secondary_color', '', '#f7f9fc' )
            ),
            'sidebar_background_css' => self::build_background_css(
                $resolve( 'customer_experience_sidebar_background_mode', '', 'solid' ) === 'gradient' ? 'gradient' : 'solid',
                $resolve( 'customer_experience_sidebar_background_color', 'surface_color', '#f6f8fc' ),
                $resolve( 'customer_experience_sidebar_background_secondary_color', '', '#ffffff' )
            ),
            'accent_color'           => $resolve( 'customer_experience_accent_color', 'primary_color', '#d78a2f' ),
            'text_color'             => $resolve( 'customer_experience_text_color', 'proposal_text_color', '#16243a' ),
            'muted_color'            => $resolve( 'customer_experience_muted_color', 'proposal_muted_color', '#66768d' ),
            'eyebrow'                => trim( (string) $resolve( 'customer_experience_eyebrow', '', '' ) ),
            'title'                  => trim( (string) $resolve( 'customer_experience_title', 'proposal_title', 'Sua proposta esta pronta para seguir' ) ),
            'description'            => trim( (string) $resolve( 'customer_experience_description', 'proposal_description', 'Confira os detalhes finais, valide os documentos e conclua a etapa atual em uma unica jornada.' ) ),
            'total_label'            => trim( (string) $resolve( 'customer_experience_total_label', '', 'Investimento aprovado' ) ),
            'total_note'             => trim( (string) $resolve( 'customer_experience_total_note', '', 'Assim que a etapa atual for concluida, o pedido segue para o time responsavel.' ) ),
            'items_eyebrow'          => self::normalize_customer_experience_copy( trim( (string) $resolve( 'customer_experience_items_eyebrow', '', '' ) ), 'Resumo visual', '' ),
            'items_title'            => self::normalize_customer_experience_copy( trim( (string) $resolve( 'customer_experience_items_title', '', 'Itens' ) ), 'O que esta incluso', 'Itens' ),
            'summary_eyebrow'        => trim( (string) $resolve( 'customer_experience_summary_eyebrow', '', 'Contexto rapido' ) ),
            'summary_title'          => trim( (string) $resolve( 'customer_experience_summary_title', '', 'Visao do pedido' ) ),
            'financial_eyebrow'      => self::normalize_customer_experience_copy( trim( (string) $resolve( 'customer_experience_financial_eyebrow', '', '' ) ), 'Fechamento', '' ),
            'financial_title'        => self::normalize_customer_experience_copy( trim( (string) $resolve( 'customer_experience_financial_title', '', 'Resumo' ) ), 'Resumo financeiro', 'Resumo' ),
            'actions_eyebrow'        => trim( (string) $resolve( 'customer_experience_actions_eyebrow', '', 'Proxima acao' ) ),
            'actions_title'          => trim( (string) $resolve( 'customer_experience_actions_title', '', 'Como seguir agora' ) ),
        );
    }

    private static function build_background_css( $mode, $primary_color, $secondary_color = '' ) {
        $mode = 'gradient' === $mode ? 'gradient' : 'solid';
        $primary_color = trim( (string) $primary_color );
        $secondary_color = trim( (string) $secondary_color );

        if ( 'gradient' === $mode && '' !== $secondary_color ) {
            return "linear-gradient(135deg, {$primary_color} 0%, {$secondary_color} 100%)";
        }

        return $primary_color;
    }

    private static function with_alpha( $color, $alpha ) {
        $color = trim( (string) $color );
        $alpha = trim( (string) $alpha );

        if ( '' === $color ) {
            return 'rgba(0, 0, 0, ' . $alpha . ')';
        }

        if ( 0 === strpos( $color, 'rgb(' ) || 0 === strpos( $color, 'rgba(' ) || 0 === strpos( $color, 'linear-gradient(' ) ) {
            return $color;
        }

        if ( '#' === $color[0] ) {
            $hex = ltrim( $color, '#' );

            if ( 3 === strlen( $hex ) ) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }

            if ( 6 === strlen( $hex ) && ctype_xdigit( $hex ) ) {
                $r = hexdec( substr( $hex, 0, 2 ) );
                $g = hexdec( substr( $hex, 2, 2 ) );
                $b = hexdec( substr( $hex, 4, 2 ) );

                return sprintf( 'rgba(%d, %d, %d, %s)', $r, $g, $b, $alpha );
            }
        }

        return $color;
    }

    private static function prepare_total_rows_for_display( WC_Order $order, $rows ) {
        $rows           = is_array( $rows ) ? $rows : array();
        $subtotal_value = (float) $order->get_subtotal();

        foreach ( $rows as $index => $row ) {
            if ( ! is_array( $row ) || 'discount' !== ( $row['key'] ?? '' ) ) {
                continue;
            }

            $discount_amount = abs( (float) ( $row['raw'] ?? 0 ) );
            $discount_percent = $subtotal_value > 0 ? ( $discount_amount / $subtotal_value ) * 100 : 0;
            $rows[ $index ]['main_value'] = number_format_i18n( $discount_percent, abs( $discount_percent - round( $discount_percent ) ) < 0.01 ? 0 : 2 ) . '%';
            $rows[ $index ]['sub_value']  = wp_strip_all_tags( wc_price( (float) ( $row['raw'] ?? 0 ) ) );
        }

        return $rows;
    }
}
