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
        $logo_url        = self::get_shared_store_logo_url( $settings );
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
        $hero_text_color     = self::get_contrast_text_color( $theme['hero_base_color'], '#16243a', '#ffffff' );
        $hero_muted_color    = self::with_alpha( $hero_text_color, '#ffffff' === strtolower( $hero_text_color ) ? '0.78' : '0.70' );
        $hero_chip_background = '#ffffff' === strtolower( $hero_text_color ) ? 'rgba(255, 255, 255, 0.16)' : 'rgba(15, 27, 53, 0.08)';
        $hero_chip_text      = $hero_text_color;
        $pill_text_color     = self::get_contrast_text_color( $experience_accent, '#16243a', '#ffffff' );
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

        return self::render_proposal_page_via_shared_renderer(
            compact(
                'order',
                'totals',
                'settings',
                'theme',
                'logo_url',
                'confirmed',
                'line_items',
                'button_label',
                'pay_label',
                'confirm_state',
                'payment_url',
                'pdf_url',
                'flow_enabled',
                'current_flow_label',
                'is_flow_focus',
                'item_columns',
                'item_labels',
                'total_rows',
                'show_sku',
                'show_email',
                'show_phone',
                'show_notes',
                'customer_name',
                'experience_font_url',
                'customer_note',
                'experience_eyebrow',
                'experience_title',
                'experience_desc',
                'total_label',
                'total_note',
                'items_eyebrow',
                'items_title',
                'summary_eyebrow',
                'summary_title',
                'financial_eyebrow',
                'financial_title',
                'actions_eyebrow',
                'actions_title',
                'show_line_total'
            )
        );

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
            .eop-proposal-brand__fallback{color:<?php echo esc_attr( $hero_text_color ); ?>;font-size:13px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;text-align:center}
            .eop-proposal-hero__copy{display:grid;gap:12px;max-width:640px}
            .eop-proposal-hero__top{display:flex;flex-wrap:wrap;gap:10px}
            .eop-proposal-status,.eop-proposal-stage{display:inline-flex;align-items:center;min-height:38px;padding:0 14px;border-radius:999px;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
            .eop-proposal-status{background:<?php echo esc_attr( $hero_chip_background ); ?>;color:<?php echo esc_attr( $hero_chip_text ); ?>;border:1px solid <?php echo esc_attr( self::with_alpha( $settings['border_color'], '0.18' ) ); ?>}
            .eop-proposal-stage{background:<?php echo esc_attr( self::with_alpha( $experience_accent, '0.16' ) ); ?>;color:<?php echo esc_attr( $hero_chip_text ); ?>;border:1px solid <?php echo esc_attr( self::with_alpha( $experience_accent, '0.28' ) ); ?>}
            .eop-proposal-eyebrow{display:block;color:<?php echo esc_attr( $hero_muted_color ); ?>;font-size:11px;font-weight:900;letter-spacing:.18em;text-transform:uppercase}
            .eop-proposal-title{margin:0;font-size:<?php echo esc_attr( $title_font_size ); ?>;line-height:.98;letter-spacing:-.05em;color:<?php echo esc_attr( $hero_text_color ); ?>}
            .eop-proposal-text{margin:0;max-width:58ch;color:<?php echo esc_attr( $hero_muted_color ); ?>;font-size:16px;line-height:1.7}
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
            .eop-proposal-item__pill{display:inline-flex;align-items:center;min-height:32px;padding:0 12px;border-radius:999px;background:<?php echo esc_attr( self::with_alpha( $experience_accent, '0.12' ) ); ?>;color:<?php echo esc_attr( $pill_text_color ); ?>;font-weight:700;line-height:1.2;white-space:nowrap;max-width:100%}
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
                            <div class="eop-proposal-brand<?php echo $has_logo ? '' : ' is-empty'; ?>">
                                <?php if ( $has_logo ) : ?>
                                    <img class="eop-proposal-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="">
                                <?php else : ?>
                                    <span class="eop-proposal-brand__fallback"><?php esc_html_e( 'Marca da loja', EOP_TEXT_DOMAIN ); ?></span>
                                <?php endif; ?>
                            </div>
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

    /**
     * Renderiza o card de preview usado nas telas visuais do admin.
     *
     * Esse metodo monta apenas o shell do preview: titulo, texto auxiliar,
     * controles Desktop/Mobile e o iframe isolado. O HTML interno exibido no
     * iframe vem de get_admin_preview_srcdoc(), que por sua vez usa o mesmo
     * renderer da pagina publica. Se o preview parecer "quebrado" no admin,
     * este costuma ser o primeiro ponto para investigar.
     *
     * @param array $settings Configuracoes atuais do plugin.
     * @return string
     */
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

    /**
     * Gera o HTML completo usado dentro do srcdoc do iframe de preview.
     *
     * Aqui acontece o isolamento de CSS: o admin nao injeta a pagina publica
     * diretamente no DOM da tela de configuracao, e sim dentro de um iframe
     * com markup e estilos proprios. Isso evita conflito com CSS do WordPress
     * e garante que o preview fique o mais proximo possivel da experiencia
     * real do cliente.
     *
     * @param array $settings Configuracoes atuais do plugin.
     * @return string
     */
    public static function get_admin_preview_srcdoc( $settings = array() ) {
        return self::build_admin_preview_srcdoc_from_shared_renderer( $settings );

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
            color: var(--eop-preview-hero-text, #fff);
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
            background: var(--eop-preview-hero-chip-bg, rgba(255, 255, 255, 0.16));
            color: var(--eop-preview-hero-chip-text, #fff);
            border: 1px solid var(--eop-preview-border-soft, rgba(255, 255, 255, 0.18));
        }

        .eop-proposal-stage {
            background: var(--eop-preview-accent-soft, rgba(215, 138, 47, 0.22));
            color: var(--eop-preview-hero-chip-text, #fff);
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

        .eop-proposal-eyebrow {
            color: var(--eop-preview-hero-muted, rgba(255, 255, 255, 0.78));
        }

        .eop-proposal-title {
            margin: 0;
            font-size: var(--eop-preview-title-size, 46px);
            line-height: 0.98;
            letter-spacing: -0.05em;
            color: var(--eop-preview-hero-text, #fff);
            text-wrap: balance;
        }

        .eop-proposal-text {
            margin: 0;
            max-width: 58ch;
            color: var(--eop-preview-hero-muted, rgba(255, 255, 255, 0.78));
            font-size: var(--eop-preview-text-size, 16px);
            line-height: 1.72;
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
            color: var(--eop-preview-pill-text, #1a2550);
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
            background: linear-gradient(180deg, rgba(54, 211, 153, 0.10), rgba(16, 185, 129, 0.08));
            border: 1px solid rgba(16, 185, 129, 0.18);
            color: #196647;
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
        $logo_url = self::get_shared_store_logo_url( $settings );
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
        $hero_text_color = self::get_contrast_text_color( $theme['hero_base_color'], '#16243a', '#ffffff' );
        $hero_muted_color = self::with_alpha( $hero_text_color, '#ffffff' === strtolower( $hero_text_color ) ? '0.78' : '0.70' );
        $hero_chip_background = '#ffffff' === strtolower( $hero_text_color ) ? 'rgba(255, 255, 255, 0.16)' : 'rgba(15, 27, 53, 0.08)';
        $hero_chip_text = $hero_text_color;
        $pill_text_color = self::get_contrast_text_color( $accent, '#16243a', '#ffffff' );
        $total_value = function_exists( 'wc_price' ) ? wc_price( 1499.9 ) : 'R$ 1.499,90';
        $discount_value = function_exists( 'wc_price' ) ? wc_price( 120 ) : 'R$ 120,00';

        return self::render_admin_preview_markup_via_shared_renderer(
            compact(
                'settings',
                'theme',
                'logo_url',
                'title',
                'description',
                'items_eyebrow',
                'items_title',
                'summary_eyebrow',
                'summary_title',
                'financial_eyebrow',
                'financial_title',
                'actions_eyebrow',
                'actions_title',
                'total_label',
                'total_note',
                'total_value',
                'discount_value'
            )
        );

        ob_start();
        ?>
        <div
            class="eop-proposal-wrap eop-proposal-wrap--preview"
            data-eop-proposal-preview-root
            style="<?php echo esc_attr( sprintf(
                '--eop-preview-page-bg:%1$s;--eop-preview-hero-bg:%2$s;--eop-preview-panel-bg:%3$s;--eop-preview-side-bg:%4$s;--eop-preview-accent:%5$s;--eop-preview-text:%6$s;--eop-preview-muted:%7$s;--eop-preview-radius:%8$dpx;--eop-preview-font-family:%9$s;--eop-preview-max-width:%10$dpx;--eop-preview-title-size:%11$s;--eop-preview-text-size:%12$s;--eop-preview-brand-bg:%13$s;--eop-preview-panel-soft:%14$s;--eop-preview-border-soft:%15$s;--eop-preview-accent-soft:%16$s;--eop-preview-accent-border:%17$s;--eop-preview-accent-glow:%18$s;--eop-preview-accent-shadow:%19$s;--eop-preview-hero-text:%20$s;--eop-preview-hero-muted:%21$s;--eop-preview-hero-chip-bg:%22$s;--eop-preview-hero-chip-text:%23$s;--eop-preview-pill-text:%24$s;',
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
                self::with_alpha( $accent, '0.20' ),
                $hero_text_color,
                $hero_muted_color,
                $hero_chip_background,
                $hero_chip_text,
                $pill_text_color
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
                                    <span class="eop-proposal-brand__fallback" data-preview-logo-fallback><?php esc_html_e( 'Marca da loja', EOP_TEXT_DOMAIN ); ?></span>
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

    private static function render_proposal_page_via_shared_renderer( $args ) {
        $style_vars = self::get_shared_proposal_style_vars( $args['settings'], $args['theme'] );

        $proposal_items = array();

        foreach ( $args['line_items'] as $line_item ) {
            $item      = $line_item['item'];
            $product   = $line_item['product'];
            $image_url = $product ? wp_get_attachment_image_url( $product->get_image_id(), 'medium' ) : '';

            if ( ! $image_url ) {
                $image_url = wc_placeholder_img_src( 'medium' );
            }

            $pills = array();

            foreach ( $args['item_columns'] as $column ) {
                if ( 'quantity' === $column['key'] ) {
                    $pills[] = array( 'text' => sprintf( '%1$s: %2$s', $column['label'], $line_item['quantity'] ) );
                } elseif ( 'unit_price' === $column['key'] ) {
                    $pills[] = array( 'text' => sprintf( '%1$s: %2$s', $column['label'], wp_strip_all_tags( wc_price( $line_item['unit_price'] ) ) ) );
                } elseif ( 'discount' === $column['key'] ) {
                    $pills[] = array(
                        'text'     => $column['label'] . ': ' . number_format_i18n( $line_item['discount_percent'], abs( $line_item['discount_percent'] - round( $line_item['discount_percent'] ) ) < 0.01 ? 0 : 2 ) . '%',
                        'sub_text' => wp_strip_all_tags( wc_price( $line_item['discount_per_unit'] ) ) . ' / ' . __( 'un.', EOP_TEXT_DOMAIN ),
                        'discount' => true,
                    );
                } elseif ( 'discounted_unit_price' === $column['key'] ) {
                    $pills[] = array( 'text' => sprintf( '%1$s: %2$s', $column['label'], wp_strip_all_tags( wc_price( $line_item['discounted_unit_price'] ) ) ) );
                }
            }

            if ( $args['show_sku'] && $product && $product->get_sku() ) {
                $pills[] = array( 'text' => sprintf( __( 'SKU: %s', EOP_TEXT_DOMAIN ), $product->get_sku() ) );
            }

            $proposal_items[] = array(
                'media_type'         => 'image',
                'media_value'        => $image_url,
                'media_alt'          => $item->get_name(),
                'name'               => $item->get_name(),
                'pills'              => $pills,
                'summary_label'      => $args['show_line_total'] ? ( $args['item_labels']['line_total'] ?? __( 'Total', EOP_TEXT_DOMAIN ) ) : '',
                'summary_value_html' => $args['show_line_total'] ? wc_price( $line_item['line_total'] ) : '',
            );
        }

        $summary_rows = array(
            array( 'label' => __( 'Pedido', EOP_TEXT_DOMAIN ), 'value' => '#' . $args['order']->get_id() ),
            array( 'label' => __( 'Cliente', EOP_TEXT_DOMAIN ), 'value' => $args['customer_name'] ),
        );

        if ( $args['show_email'] && $args['order']->get_billing_email() ) {
            $summary_rows[] = array( 'label' => __( 'E-mail', EOP_TEXT_DOMAIN ), 'value' => $args['order']->get_billing_email() );
        }

        if ( $args['show_phone'] && $args['order']->get_billing_phone() ) {
            $summary_rows[] = array( 'label' => __( 'Telefone', EOP_TEXT_DOMAIN ), 'value' => $args['order']->get_billing_phone() );
        }

        $sidebar_cards = array(
            array(
                'type'    => 'meta',
                'eyebrow' => '' !== $args['summary_eyebrow'] ? $args['summary_eyebrow'] : __( 'Contexto do pedido', EOP_TEXT_DOMAIN ),
                'title'   => $args['summary_title'] ?: __( 'Contexto do pedido', EOP_TEXT_DOMAIN ),
                'rows'    => $summary_rows,
            ),
        );

        if ( ! empty( $args['total_rows'] ) ) {
            $sidebar_cards[] = array(
                'type'    => 'totals',
                'eyebrow' => $args['financial_eyebrow'],
                'title'   => $args['financial_title'] ?: __( 'Resumo', EOP_TEXT_DOMAIN ),
                'rows'    => $args['total_rows'],
            );
        }

        if ( ! $args['confirmed'] ) {
            $actions = array(
                array(
                    'type'  => 'form_submit',
                    'label' => $args['button_label'],
                    'token' => $args['order']->get_meta( '_eop_public_token', true ),
                ),
            );

            if ( $args['pdf_url'] ) {
                $actions[] = array(
                    'type'     => 'link',
                    'label'    => __( 'Baixar PDF', EOP_TEXT_DOMAIN ),
                    'url'      => $args['pdf_url'],
                    'download' => $args['order']->get_id() . '.pdf',
                );
            }

            $sidebar_cards[] = array(
                'type'    => 'actions',
                'eyebrow' => '' !== $args['actions_eyebrow'] ? $args['actions_eyebrow'] : __( 'Confirmacao da proposta', EOP_TEXT_DOMAIN ),
                'title'   => $args['actions_title'] ?: __( 'Confirmacao da proposta', EOP_TEXT_DOMAIN ),
                'actions' => $actions,
            );
        } elseif ( ! $args['flow_enabled'] && ( $args['payment_url'] || $args['pdf_url'] ) ) {
            $actions = array();

            if ( $args['payment_url'] ) {
                $actions[] = array( 'type' => 'link', 'label' => $args['pay_label'], 'url' => $args['payment_url'] );
            }

            if ( $args['pdf_url'] ) {
                $actions[] = array(
                    'type'     => 'link',
                    'label'    => __( 'Baixar PDF', EOP_TEXT_DOMAIN ),
                    'url'      => $args['pdf_url'],
                    'download' => $args['order']->get_id() . '.pdf',
                );
            }

            $sidebar_cards[] = array(
                'type'    => 'actions',
                'eyebrow' => '' !== $args['actions_eyebrow'] ? $args['actions_eyebrow'] : __( 'Acoes rapidas', EOP_TEXT_DOMAIN ),
                'title'   => $args['actions_title'] ?: __( 'Acoes rapidas', EOP_TEXT_DOMAIN ),
                'actions' => $actions,
            );
        }

        $main_notices = array();

        if ( $args['show_notes'] && '' !== $args['customer_note'] ) {
            $main_notices[] = array( 'type' => 'notes', 'label' => __( 'Observacoes', EOP_TEXT_DOMAIN ), 'text' => $args['customer_note'] );
        }

        if ( '1' === $args['confirm_state'] ) {
            $main_notices[] = array( 'type' => 'success', 'text' => __( 'Proposta confirmada com sucesso.', EOP_TEXT_DOMAIN ) );
        }

        $layout_context = array(
            'wrap_classes'    => array_filter( array( $args['confirmed'] ? 'is-confirmed' : '', $args['is_flow_focus'] ? 'is-flow-focus' : '' ) ),
            'style_vars'      => $style_vars,
            'logo_url'        => $args['logo_url'],
            'hero_status'     => $args['confirmed'] ? __( 'Proposta confirmada', EOP_TEXT_DOMAIN ) : __( 'Aguardando confirmacao', EOP_TEXT_DOMAIN ),
            'hero_stage'      => $args['current_flow_label'] ? sprintf( __( 'Etapa atual: %s', EOP_TEXT_DOMAIN ), $args['current_flow_label'] ) : '',
            'eyebrow'         => $args['experience_eyebrow'],
            'title'           => $args['experience_title'],
            'description'     => $args['experience_desc'],
            'total_label'     => '' !== $args['total_label'] ? $args['total_label'] : __( 'Total aprovado', EOP_TEXT_DOMAIN ),
            'total_value_html'=> wc_price( $args['totals']['total'] ?? $args['order']->get_total() ),
            'total_note'      => '' !== $args['total_note'] ? $args['total_note'] : __( 'Revise os itens e conclua a etapa atual para liberar o restante da jornada.', EOP_TEXT_DOMAIN ),
            'hero_meta_items' => array(
                array( 'label' => __( 'Pedido', EOP_TEXT_DOMAIN ), 'value' => '#' . $args['order']->get_id() ),
                array( 'label' => __( 'Cliente', EOP_TEXT_DOMAIN ), 'value' => $args['customer_name'] ),
            ),
            'items_eyebrow'   => $args['items_eyebrow'],
            'items_title'     => $args['items_title'] ?: __( 'Itens', EOP_TEXT_DOMAIN ),
            'items'           => $proposal_items,
            'main_notices'    => $main_notices,
            'sidebar_cards'   => $sidebar_cards,
            'after_markup'    => ( $args['confirmed'] && $args['flow_enabled'] ) ? EOP_Post_Confirmation_Flow::render_frontend_stage( $args['order'], $args['line_items'], $args['pdf_url'] ) : '',
        );

        ob_start();
        if ( ! wp_style_is( 'eop-frontend', 'enqueued' ) && ! wp_style_is( 'eop-frontend', 'done' ) ) {
            echo '<link rel="stylesheet" href="' . esc_url( EOP_PLUGIN_URL . 'assets/css/frontend.css?ver=' . EOP_VERSION ) . '">';
        }
        if ( $args['experience_font_url'] ) {
            echo '<link rel="stylesheet" href="' . esc_url( $args['experience_font_url'] ) . '">';
        }
        echo '<style>body{background:' . esc_attr( $style_vars['page_bg'] ) . '}' . self::get_shared_proposal_stylesheet() . '</style>';

        if ( $args['is_flow_focus'] ) {
            echo self::render_shared_proposal_markup(
                array(
                    'wrap_classes' => array( 'is-flow-focus' ),
                    'style_vars'   => $style_vars,
                    'before_markup' => EOP_Post_Confirmation_Flow::render_frontend_stage( $args['order'], $args['line_items'], $args['pdf_url'] ),
                )
            ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            echo self::render_shared_proposal_markup( $layout_context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        return (string) ob_get_clean();
    }

    private static function render_admin_preview_markup_via_shared_renderer( $args ) {
        $style_vars = self::get_shared_proposal_style_vars( $args['settings'], $args['theme'] );

        return self::render_shared_proposal_markup(
            array(
                'wrap_classes'    => array( 'eop-proposal-wrap--preview' ),
                'style_vars'      => $style_vars,
                'logo_url'        => $args['logo_url'],
                'hero_status'     => __( 'Preview ao vivo', EOP_TEXT_DOMAIN ),
                'hero_stage'      => __( 'Layout real', EOP_TEXT_DOMAIN ),
                'eyebrow'         => '' !== $args['theme']['eyebrow'] ? $args['theme']['eyebrow'] : __( 'Experiencia do cliente', EOP_TEXT_DOMAIN ),
                'title'           => $args['title'],
                'description'     => $args['description'],
                'total_label'     => $args['total_label'],
                'total_value_html'=> $args['total_value'],
                'total_note'      => $args['total_note'],
                'hero_meta_items' => array(
                    array( 'label' => __( 'Pedido', EOP_TEXT_DOMAIN ), 'value' => '#2026-048' ),
                    array( 'label' => __( 'Cliente', EOP_TEXT_DOMAIN ), 'value' => __( 'Maria Oliveira', EOP_TEXT_DOMAIN ) ),
                ),
                'items_eyebrow'   => $args['items_eyebrow'],
                'items_title'     => $args['items_title'],
                'items'           => array(
                    array(
                        'media_type'         => 'text',
                        'media_value'        => '01',
                        'name'               => __( 'Kit Nutricao Intensa', EOP_TEXT_DOMAIN ),
                        'pills'              => array(
                            array( 'text' => __( 'SKU A-102', EOP_TEXT_DOMAIN ) ),
                            array( 'text' => __( '2 unidades', EOP_TEXT_DOMAIN ) ),
                        ),
                        'summary_label'      => __( 'Subtotal', EOP_TEXT_DOMAIN ),
                        'summary_value_html' => $args['total_value'],
                    ),
                    array(
                        'media_type'         => 'text',
                        'media_value'        => '02',
                        'name'               => __( 'Serum Reparador', EOP_TEXT_DOMAIN ),
                        'pills'              => array(
                            array( 'text' => __( 'Brinde de campanha', EOP_TEXT_DOMAIN ) ),
                            array( 'text' => __( 'Amostra', EOP_TEXT_DOMAIN ) ),
                        ),
                        'summary_label'      => __( 'Desconto', EOP_TEXT_DOMAIN ),
                        'summary_value_html' => '- ' . $args['discount_value'],
                    ),
                ),
                'sidebar_cards'   => array(
                    array(
                        'type'    => 'meta',
                        'eyebrow' => $args['summary_eyebrow'],
                        'title'   => $args['summary_title'],
                        'rows'    => array(
                            array( 'label' => __( 'Status', EOP_TEXT_DOMAIN ), 'value' => __( 'Aguardando confirmacao', EOP_TEXT_DOMAIN ) ),
                            array( 'label' => __( 'Prazo', EOP_TEXT_DOMAIN ), 'value' => __( 'Entrega em ate 3 dias uteis', EOP_TEXT_DOMAIN ) ),
                        ),
                    ),
                    array(
                        'type'    => 'totals',
                        'eyebrow' => $args['financial_eyebrow'],
                        'title'   => $args['financial_title'],
                        'rows'    => array(
                            array( 'label' => __( 'Subtotal', EOP_TEXT_DOMAIN ), 'main_value' => wp_strip_all_tags( $args['total_value'] ), 'sub_value' => __( 'Valor base dos itens', EOP_TEXT_DOMAIN ), 'class' => '' ),
                            array( 'label' => __( 'Desconto', EOP_TEXT_DOMAIN ), 'main_value' => '- ' . wp_strip_all_tags( $args['discount_value'] ), 'sub_value' => __( 'Campanha aplicada', EOP_TEXT_DOMAIN ), 'class' => '' ),
                            array( 'label' => __( 'Total', EOP_TEXT_DOMAIN ), 'main_value' => wp_strip_all_tags( $args['total_value'] ), 'sub_value' => __( 'Valor final exibido ao cliente', EOP_TEXT_DOMAIN ), 'class' => 'is-grand' ),
                        ),
                    ),
                    array(
                        'type'    => 'actions',
                        'eyebrow' => $args['actions_eyebrow'],
                        'title'   => $args['actions_title'],
                        'actions' => array(
                            array( 'type' => 'button', 'label' => $args['settings']['proposal_button_label'] ),
                            array( 'type' => 'button', 'label' => $args['settings']['proposal_pay_button_label'], 'secondary' => true ),
                        ),
                        'note'    => __( 'Este bloco simula a jornada publica com a mesma hierarquia visual usada pelo cliente.', EOP_TEXT_DOMAIN ),
                    ),
                ),
            )
        );
    }

    private static function build_admin_preview_srcdoc_from_shared_renderer( $settings ) {
        $markup = self::render_admin_preview_markup( $settings );
        ob_start();
        ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        html,body{margin:0;padding:0;background:#f4f7ff}
        body{font-family:'Segoe UI',sans-serif}
        .eop-proposal-preview-frame{padding:22px;background:#f4f7ff}
        <?php echo self::get_shared_proposal_stylesheet(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        @media (max-width:720px){.eop-proposal-preview-frame{padding:12px}}
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

    private static function get_shared_proposal_style_vars( $settings, $theme ) {
        $hero_text_color      = self::get_contrast_text_color( $theme['hero_base_color'], '#16243a', '#ffffff' );
        $hero_muted_color     = self::with_alpha( $hero_text_color, '#ffffff' === strtolower( $hero_text_color ) ? '0.78' : '0.70' );
        $hero_chip_background = '#ffffff' === strtolower( $hero_text_color ) ? 'rgba(255, 255, 255, 0.16)' : 'rgba(15, 27, 53, 0.08)';
        $hero_chip_text       = $hero_text_color;
        $pill_text_color      = self::get_contrast_text_color( $theme['accent_color'], '#16243a', '#ffffff' );

        return array(
            'page_bg'                 => $theme['background_css'],
            'hero_bg'                 => $theme['hero_background_css'],
            'panel_bg'                => $theme['panel_background_css'],
            'side_bg'                 => $theme['sidebar_background_css'],
            'accent'                  => $theme['accent_color'],
            'text'                    => $theme['text_color'],
            'muted'                   => $theme['muted_color'],
            'radius'                  => absint( $settings['border_radius'] ),
            'font_css'                => $theme['font_css'],
            'max_width'               => absint( $theme['max_width'] ),
            'title_font_size'         => $theme['title_font_size'],
            'base_font_size'          => $theme['base_font_size'],
            'brand_bg'                => self::with_alpha( $theme['panel_background_css'], '0.12' ),
            'panel_soft'              => self::with_alpha( $theme['panel_background_css'], '0.18' ),
            'border_soft'             => self::with_alpha( $settings['border_color'], '0.18' ),
            'accent_soft'             => self::with_alpha( $theme['accent_color'], '0.12' ),
            'accent_border'           => self::with_alpha( $theme['accent_color'], '0.28' ),
            'accent_glow'             => self::with_alpha( $theme['accent_color'], '0.28' ),
            'accent_shadow'           => self::with_alpha( $theme['accent_color'], '0.20' ),
            'hero_text'               => $hero_text_color,
            'hero_muted'              => $hero_muted_color,
            'hero_chip_bg'            => $hero_chip_background,
            'hero_chip_text'          => $hero_chip_text,
            'pill_text'               => $pill_text_color,
            'flow_focus_max_width'    => min( absint( $theme['max_width'] ), 1040 ),
            'title_font_size_mobile'  => self::responsive_preview_size( $theme['title_font_size'], '28px', -10 ),
        );
    }

    private static function build_shared_proposal_inline_style( $style_vars ) {
        return sprintf(
            '--eop-preview-page-bg:%1$s;--eop-preview-hero-bg:%2$s;--eop-preview-panel-bg:%3$s;--eop-preview-side-bg:%4$s;--eop-preview-accent:%5$s;--eop-preview-text:%6$s;--eop-preview-muted:%7$s;--eop-preview-radius:%8$dpx;--eop-preview-font-family:%9$s;--eop-preview-max-width:%10$dpx;--eop-preview-title-size:%11$s;--eop-preview-text-size:%12$s;--eop-preview-brand-bg:%13$s;--eop-preview-panel-soft:%14$s;--eop-preview-border-soft:%15$s;--eop-preview-accent-soft:%16$s;--eop-preview-accent-border:%17$s;--eop-preview-accent-glow:%18$s;--eop-preview-accent-shadow:%19$s;--eop-preview-hero-text:%20$s;--eop-preview-hero-muted:%21$s;--eop-preview-hero-chip-bg:%22$s;--eop-preview-hero-chip-text:%23$s;--eop-preview-pill-text:%24$s;--eop-preview-flow-focus-max-width:%25$dpx;--eop-preview-title-size-mobile:%26$s;',
            $style_vars['page_bg'],
            $style_vars['hero_bg'],
            $style_vars['panel_bg'],
            $style_vars['side_bg'],
            $style_vars['accent'],
            $style_vars['text'],
            $style_vars['muted'],
            $style_vars['radius'],
            $style_vars['font_css'],
            $style_vars['max_width'],
            $style_vars['title_font_size'],
            $style_vars['base_font_size'],
            $style_vars['brand_bg'],
            $style_vars['panel_soft'],
            $style_vars['border_soft'],
            $style_vars['accent_soft'],
            $style_vars['accent_border'],
            $style_vars['accent_glow'],
            $style_vars['accent_shadow'],
            $style_vars['hero_text'],
            $style_vars['hero_muted'],
            $style_vars['hero_chip_bg'],
            $style_vars['hero_chip_text'],
            $style_vars['pill_text'],
            $style_vars['flow_focus_max_width'],
            $style_vars['title_font_size_mobile']
        );
    }

    private static function get_shared_proposal_stylesheet() {
        return '.eop-proposal-wrap{max-width:var(--eop-preview-max-width,1120px);margin:32px auto;padding:0 16px 46px;font-family:var(--eop-preview-font-family,\'Segoe UI\',sans-serif);font-size:var(--eop-preview-text-size,16px);color:var(--eop-preview-text,#16243a)}.eop-proposal-card{display:grid;gap:24px}.eop-proposal-hero{position:relative;overflow:hidden;display:grid;grid-template-columns:minmax(0,1.15fr) minmax(280px,.85fr);gap:24px;padding:34px;border-radius:calc(var(--eop-preview-radius,18px) + 10px);background:var(--eop-preview-hero-bg,#0f1b35);box-shadow:0 28px 68px rgba(15,27,53,.24)}.eop-proposal-hero::before{content:\"\";position:absolute;inset:auto -10% -30% auto;width:340px;height:340px;border-radius:50%;background:radial-gradient(circle,var(--eop-preview-accent-glow,rgba(215,138,47,.28)),transparent 70%)}.eop-proposal-hero > *{position:relative;z-index:1}.eop-proposal-hero__main,.eop-proposal-hero__aside{display:grid;gap:18px;align-content:start}.eop-proposal-brandline{display:flex;align-items:flex-start;gap:18px}.eop-proposal-brand{display:flex;align-items:center;justify-content:center;min-width:110px;min-height:90px;padding:16px 18px;border-radius:26px;background:var(--eop-preview-brand-bg,rgba(255,255,255,.12));border:1px solid var(--eop-preview-border-soft,rgba(255,255,255,.18));backdrop-filter:blur(10px)}.eop-proposal-brand img,.eop-proposal-logo{display:block;max-width:210px;max-height:60px;object-fit:contain}.eop-proposal-brand__fallback{color:var(--eop-preview-hero-text,#fff);font-size:13px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;text-align:center}.eop-proposal-hero__copy{display:grid;gap:12px;max-width:640px}.eop-proposal-hero__top{display:flex;flex-wrap:wrap;gap:10px}.eop-proposal-status,.eop-proposal-stage{display:inline-flex;align-items:center;min-height:38px;padding:0 14px;border-radius:999px;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.eop-proposal-status{background:var(--eop-preview-hero-chip-bg,rgba(255,255,255,.16));color:var(--eop-preview-hero-chip-text,#fff);border:1px solid var(--eop-preview-border-soft,rgba(255,255,255,.18))}.eop-proposal-stage{background:var(--eop-preview-accent-soft,rgba(215,138,47,.22));color:var(--eop-preview-hero-chip-text,#fff);border:1px solid var(--eop-preview-accent-border,rgba(215,138,47,.28))}.eop-proposal-eyebrow{display:block;color:var(--eop-preview-hero-muted,rgba(255,255,255,.78));font-size:11px;font-weight:900;letter-spacing:.18em;text-transform:uppercase}.eop-proposal-title{margin:0;font-size:var(--eop-preview-title-size,46px);line-height:.98;letter-spacing:-.05em;color:var(--eop-preview-hero-text,#fff)}.eop-proposal-text{margin:0;max-width:58ch;color:var(--eop-preview-hero-muted,rgba(255,255,255,.78));font-size:var(--eop-preview-text-size,16px);line-height:1.7}.eop-proposal-hero__aside{padding:24px;border-radius:28px;background:var(--eop-preview-side-bg,#f6f8fc);border:1px solid var(--eop-preview-border-soft,rgba(255,255,255,.18));backdrop-filter:blur(10px)}.eop-proposal-hero__aside-label{color:var(--eop-preview-muted,#66768d);display:block;font-size:11px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}.eop-proposal-hero__aside strong{font-size:44px;line-height:.95;letter-spacing:-.06em;color:var(--eop-preview-text,#16243a)}.eop-proposal-hero__aside p{margin:0;color:var(--eop-preview-text,#16243a);line-height:1.65}.eop-proposal-hero__meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:4px}.eop-proposal-hero__meta-item{padding:14px 16px;border-radius:20px;background:var(--eop-preview-panel-bg,#fff);border:1px solid var(--eop-preview-border-soft,rgba(255,255,255,.18))}.eop-proposal-hero__meta-item span{display:block;color:var(--eop-preview-muted,#66768d);font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.eop-proposal-hero__meta-item strong{display:block;margin-top:7px;font-size:18px;line-height:1.2;color:var(--eop-preview-text,#16243a)}.eop-proposal-overview{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(300px,.85fr);gap:24px;align-items:start}.eop-proposal-overview__main,.eop-proposal-overview__side{display:grid;gap:18px}.eop-proposal-overview__side{position:sticky;top:18px}.eop-proposal-section,.eop-proposal-summary-card{padding:24px;border-radius:28px;border:1px solid var(--eop-preview-border-soft,rgba(255,255,255,.18));box-shadow:0 18px 38px rgba(15,27,53,.08)}.eop-proposal-section{background:var(--eop-preview-panel-bg,#fff)}.eop-proposal-summary-card{background:var(--eop-preview-side-bg,#f6f8fc)}.eop-proposal-section__head{display:flex;justify-content:space-between;gap:12px;align-items:flex-end;margin-bottom:18px}.eop-proposal-section__eyebrow,.eop-proposal-summary-card__eyebrow{display:block;margin-bottom:6px;color:var(--eop-preview-muted,#66768d);font-size:11px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.eop-proposal-section__head h2,.eop-proposal-summary-card h2{margin:0;font-size:26px;line-height:1.06;letter-spacing:-.04em;color:var(--eop-preview-text,#16243a)}.eop-proposal-meta{display:grid;gap:12px}.eop-proposal-meta p{display:grid;gap:6px;margin:0;padding:14px 16px;border:1px solid var(--eop-preview-border-soft,rgba(255,255,255,.18));border-radius:18px;background:var(--eop-preview-panel-bg,#fff)}.eop-proposal-meta strong{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--eop-preview-muted,#66768d)}.eop-proposal-items{display:grid;gap:14px}.eop-proposal-item{display:grid;grid-template-columns:108px minmax(0,1fr) auto;gap:18px;align-items:center;padding:18px;border:1px solid var(--eop-preview-border-soft,rgba(255,255,255,.18));border-radius:24px;background:var(--eop-preview-panel-bg,#fff)}.eop-proposal-item__media{width:108px;height:108px;border-radius:24px;overflow:hidden;background:var(--eop-preview-side-bg,#f6f8fc);border:1px solid var(--eop-preview-border-soft,rgba(255,255,255,.18));display:flex;align-items:center;justify-content:center;font-weight:800;font-size:20px;color:var(--eop-preview-muted,#66768d)}.eop-proposal-item__media img{display:block;width:100%;height:100%;object-fit:cover}.eop-proposal-item__body{min-width:0}.eop-proposal-item__name{margin:0 0 8px;font-size:23px;line-height:1.18;color:var(--eop-preview-text,#16243a)}.eop-proposal-item__meta{display:flex;flex-wrap:wrap;gap:8px 12px;color:var(--eop-preview-muted,#66768d);font-size:14px}.eop-proposal-item__pill{display:inline-flex;align-items:center;min-height:32px;padding:0 12px;border-radius:999px;background:var(--eop-preview-accent-soft,rgba(215,138,47,.12));color:var(--eop-preview-pill-text,#1a2550);font-weight:700;line-height:1.2;white-space:nowrap;max-width:100%}.eop-proposal-item__pill--discount{display:grid;gap:2px;align-items:flex-start;padding:9px 12px;white-space:normal;border-radius:18px}.eop-proposal-item__pill-main{font-size:13px;line-height:1.2}.eop-proposal-item__pill-sub{font-size:12px;line-height:1.25;color:var(--eop-preview-muted,#66768d)}.eop-proposal-item__summary{display:grid;gap:6px;min-width:150px;justify-items:end;text-align:right}.eop-proposal-item__summary span{font-size:13px;color:var(--eop-preview-muted,#66768d);text-transform:uppercase;letter-spacing:.08em;font-weight:700}.eop-proposal-item__summary strong{font-size:30px;line-height:1;color:var(--eop-preview-text,#16243a)}.eop-proposal-notes{padding:18px;border:1px solid var(--eop-preview-border-soft,rgba(255,255,255,.18));border-radius:22px;background:var(--eop-preview-panel-bg,#fff)}.eop-proposal-notes span{display:block;margin-bottom:8px;color:var(--eop-preview-muted,#66768d);font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}.eop-proposal-notes p{margin:0;color:var(--eop-preview-text,#16243a)}.eop-proposal-totals{display:grid;gap:2px}.eop-proposal-total{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:9px 0;color:var(--eop-preview-text,#16243a)}.eop-proposal-total.is-grand{font-size:20px;font-weight:800;border-top:2px solid var(--eop-preview-accent,#d78a2f);margin-top:8px;padding-top:12px}.eop-proposal-total__value{display:grid;justify-items:end;gap:2px;text-align:right}.eop-proposal-total__value strong{font-size:15px;line-height:1.1;color:var(--eop-preview-text,#16243a)}.eop-proposal-total__value small{font-size:12px;line-height:1.25;color:var(--eop-preview-muted,#66768d)}.eop-proposal-button{display:inline-flex;align-items:center;justify-content:center;min-height:50px;padding:0 22px;border:none;border-radius:var(--eop-preview-radius,18px);background:var(--eop-preview-accent,#d78a2f);color:#fff;text-decoration:none;font-weight:700;cursor:pointer;box-shadow:0 16px 30px var(--eop-preview-accent-shadow,rgba(215,138,47,.2))}.eop-proposal-button--secondary{background:var(--eop-preview-side-bg,#f6f8fc);color:var(--eop-preview-text,#16243a);box-shadow:none;border:1px solid var(--eop-preview-border-soft,rgba(255,255,255,.18))}.eop-proposal-actions{display:flex;flex-wrap:wrap;gap:12px}.eop-proposal-actions form{display:flex;width:100%}.eop-proposal-actions .eop-proposal-button{width:100%}.eop-proposal-note{margin:0;padding:14px 16px;border-radius:18px;background:#ecfdf5;border:1px solid #bbf7d0;color:#166534}.eop-proposal-wrap.is-flow-focus{max-width:var(--eop-preview-flow-focus-max-width,1040px);padding-bottom:34px}.eop-proposal-wrap.is-flow-focus .eop-proposal-card{gap:0}@media (max-width:980px){.eop-proposal-hero,.eop-proposal-overview{grid-template-columns:1fr}.eop-proposal-overview__side{position:static}.eop-proposal-actions .eop-proposal-button{width:auto}}@media (max-width:720px){.eop-proposal-wrap{font-size:15px;padding:0 10px 30px}.eop-proposal-hero,.eop-proposal-section,.eop-proposal-summary-card{padding:20px;border-radius:24px}.eop-proposal-brandline{flex-direction:column}.eop-proposal-hero__meta{grid-template-columns:1fr}.eop-proposal-title{font-size:var(--eop-preview-title-size-mobile,28px)}.eop-proposal-item{grid-template-columns:1fr}.eop-proposal-item__media{width:86px;height:86px}.eop-proposal-item__summary{justify-items:start;text-align:left}.eop-proposal-total{gap:10px}.eop-proposal-total__value strong{font-size:14px}}';
    }

    private static function render_shared_proposal_markup( $context ) {
        $wrap_classes = isset( $context['wrap_classes'] ) && is_array( $context['wrap_classes'] ) ? $context['wrap_classes'] : array();
        $style_attr   = self::build_shared_proposal_inline_style( $context['style_vars'] ?? array() );

        ob_start();
        ?>
        <div class="eop-proposal-wrap <?php echo esc_attr( trim( implode( ' ', $wrap_classes ) ) ); ?>" style="<?php echo esc_attr( $style_attr ); ?>">
            <div class="eop-proposal-card">
                <?php if ( ! empty( $context['before_markup'] ) ) : ?>
                    <?php echo $context['before_markup']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php else : ?>
                    <div class="eop-proposal-hero">
                        <div class="eop-proposal-hero__main">
                            <div class="eop-proposal-brandline">
                                <div class="eop-proposal-brand<?php echo empty( $context['logo_url'] ) ? ' is-empty' : ''; ?>">
                                    <?php if ( ! empty( $context['logo_url'] ) ) : ?>
                                        <img class="eop-proposal-logo" src="<?php echo esc_url( $context['logo_url'] ); ?>" alt="">
                                    <?php else : ?>
                                        <span class="eop-proposal-brand__fallback"><?php esc_html_e( 'Marca da loja', EOP_TEXT_DOMAIN ); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="eop-proposal-hero__copy">
                                    <div class="eop-proposal-hero__top">
                                        <?php if ( ! empty( $context['hero_status'] ) ) : ?><div class="eop-proposal-status"><?php echo esc_html( $context['hero_status'] ); ?></div><?php endif; ?>
                                        <?php if ( ! empty( $context['hero_stage'] ) ) : ?><div class="eop-proposal-stage"><?php echo esc_html( $context['hero_stage'] ); ?></div><?php endif; ?>
                                    </div>
                                    <?php if ( ! empty( $context['eyebrow'] ) ) : ?><span class="eop-proposal-eyebrow"><?php echo esc_html( $context['eyebrow'] ); ?></span><?php endif; ?>
                                    <h1 class="eop-proposal-title"><?php echo esc_html( $context['title'] ?? '' ); ?></h1>
                                    <p class="eop-proposal-text"><?php echo esc_html( $context['description'] ?? '' ); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="eop-proposal-hero__aside">
                            <span class="eop-proposal-hero__aside-label"><?php echo esc_html( $context['total_label'] ?? '' ); ?></span>
                            <strong><?php echo wp_kses_post( $context['total_value_html'] ?? '' ); ?></strong>
                            <p><?php echo esc_html( $context['total_note'] ?? '' ); ?></p>
                            <?php if ( ! empty( $context['hero_meta_items'] ) ) : ?><div class="eop-proposal-hero__meta"><?php foreach ( $context['hero_meta_items'] as $meta_item ) : ?><div class="eop-proposal-hero__meta-item"><span><?php echo esc_html( $meta_item['label'] ?? '' ); ?></span><strong><?php echo esc_html( $meta_item['value'] ?? '' ); ?></strong></div><?php endforeach; ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="eop-proposal-overview">
                        <div class="eop-proposal-overview__main">
                            <section class="eop-proposal-section">
                                <div class="eop-proposal-section__head"><div><?php if ( ! empty( $context['items_eyebrow'] ) ) : ?><span class="eop-proposal-section__eyebrow"><?php echo esc_html( $context['items_eyebrow'] ); ?></span><?php endif; ?><h2><?php echo esc_html( $context['items_title'] ?? '' ); ?></h2></div></div>
                                <div class="eop-proposal-items">
                                    <?php foreach ( ( $context['items'] ?? array() ) as $item ) : ?>
                                        <article class="eop-proposal-item">
                                            <div class="eop-proposal-item__media"><?php if ( 'image' === ( $item['media_type'] ?? '' ) ) : ?><img src="<?php echo esc_url( $item['media_value'] ?? '' ); ?>" alt="<?php echo esc_attr( $item['media_alt'] ?? '' ); ?>"><?php else : ?><?php echo esc_html( $item['media_value'] ?? '' ); ?><?php endif; ?></div>
                                            <div class="eop-proposal-item__body">
                                                <h3 class="eop-proposal-item__name"><?php echo esc_html( $item['name'] ?? '' ); ?></h3>
                                                <?php if ( ! empty( $item['pills'] ) ) : ?><div class="eop-proposal-item__meta"><?php foreach ( $item['pills'] as $pill ) : ?><?php if ( ! empty( $pill['discount'] ) ) : ?><div class="eop-proposal-item__pill eop-proposal-item__pill--discount"><strong class="eop-proposal-item__pill-main"><?php echo esc_html( $pill['text'] ?? '' ); ?></strong><?php if ( ! empty( $pill['sub_text'] ) ) : ?><small class="eop-proposal-item__pill-sub"><?php echo esc_html( $pill['sub_text'] ); ?></small><?php endif; ?></div><?php else : ?><span class="eop-proposal-item__pill"><?php echo esc_html( $pill['text'] ?? '' ); ?></span><?php endif; ?><?php endforeach; ?></div><?php endif; ?>
                                            </div>
                                            <?php if ( ! empty( $item['summary_label'] ) || ! empty( $item['summary_value_html'] ) ) : ?><div class="eop-proposal-item__summary"><span><?php echo esc_html( $item['summary_label'] ?? '' ); ?></span><strong><?php echo wp_kses_post( $item['summary_value_html'] ?? '' ); ?></strong></div><?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                            <?php foreach ( ( $context['main_notices'] ?? array() ) as $notice ) : ?><?php if ( 'notes' === ( $notice['type'] ?? '' ) ) : ?><section class="eop-proposal-section"><div class="eop-proposal-notes"><span><?php echo esc_html( $notice['label'] ?? '' ); ?></span><p><?php echo esc_html( $notice['text'] ?? '' ); ?></p></div></section><?php elseif ( 'success' === ( $notice['type'] ?? '' ) ) : ?><p class="eop-proposal-note"><?php echo esc_html( $notice['text'] ?? '' ); ?></p><?php endif; ?><?php endforeach; ?>
                        </div>
                        <aside class="eop-proposal-overview__side">
                            <?php foreach ( ( $context['sidebar_cards'] ?? array() ) as $card ) : ?><section class="eop-proposal-summary-card"><?php if ( ! empty( $card['eyebrow'] ) ) : ?><span class="eop-proposal-summary-card__eyebrow"><?php echo esc_html( $card['eyebrow'] ); ?></span><?php endif; ?><h2><?php echo esc_html( $card['title'] ?? '' ); ?></h2><?php if ( 'meta' === ( $card['type'] ?? '' ) ) : ?><div class="eop-proposal-meta" style="margin-top:16px"><?php foreach ( ( $card['rows'] ?? array() ) as $row ) : ?><p><strong><?php echo esc_html( $row['label'] ?? '' ); ?></strong><span><?php echo esc_html( $row['value'] ?? '' ); ?></span></p><?php endforeach; ?></div><?php elseif ( 'totals' === ( $card['type'] ?? '' ) ) : ?><div class="eop-proposal-totals" style="margin-top:16px"><?php foreach ( ( $card['rows'] ?? array() ) as $row ) : ?><div class="eop-proposal-total <?php echo esc_attr( $row['class'] ?? '' ); ?>"><span><?php echo esc_html( $row['label'] ?? '' ); ?></span><?php if ( ! empty( $row['main_value'] ) ) : ?><div class="eop-proposal-total__value"><strong><?php echo esc_html( $row['main_value'] ); ?></strong><?php if ( ! empty( $row['sub_value'] ) ) : ?><small><?php echo esc_html( $row['sub_value'] ); ?></small><?php endif; ?></div><?php else : ?><span><?php echo wp_kses_post( $row['value'] ?? '' ); ?></span><?php endif; ?></div><?php endforeach; ?></div><?php elseif ( 'actions' === ( $card['type'] ?? '' ) ) : ?><div class="eop-proposal-actions" style="margin-top:16px"><?php foreach ( ( $card['actions'] ?? array() ) as $action ) : ?><?php if ( 'form_submit' === ( $action['type'] ?? '' ) ) : ?><form method="post"><?php wp_nonce_field( 'eop_confirm_proposal', 'eop_confirm_proposal_nonce' ); ?><input type="hidden" name="eop_proposal_token" value="<?php echo esc_attr( $action['token'] ?? '' ); ?>" /><button type="submit" class="eop-proposal-button<?php echo ! empty( $action['secondary'] ) ? ' eop-proposal-button--secondary' : ''; ?>"><?php echo esc_html( $action['label'] ?? '' ); ?></button></form><?php elseif ( 'link' === ( $action['type'] ?? '' ) ) : ?><a class="eop-proposal-button<?php echo ! empty( $action['secondary'] ) ? ' eop-proposal-button--secondary' : ''; ?>" href="<?php echo esc_url( $action['url'] ?? '' ); ?>"<?php echo ! empty( $action['download'] ) ? ' download="' . esc_attr( $action['download'] ) . '"' : ''; ?>><?php echo esc_html( $action['label'] ?? '' ); ?></a><?php else : ?><button type="button" class="eop-proposal-button<?php echo ! empty( $action['secondary'] ) ? ' eop-proposal-button--secondary' : ''; ?>"><?php echo esc_html( $action['label'] ?? '' ); ?></button><?php endif; ?><?php endforeach; ?></div><?php if ( ! empty( $card['note'] ) ) : ?><p class="eop-proposal-note" style="margin-top:16px"><?php echo esc_html( $card['note'] ); ?></p><?php endif; ?><?php endif; ?></section><?php endforeach; ?>
                        </aside>
                    </div>
                <?php endif; ?>
                <?php if ( ! empty( $context['after_markup'] ) ) : ?><?php echo $context['after_markup']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
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
            'hero_base_color'        => $resolve( 'customer_experience_hero_background_color', 'primary_color', '#00034b' ),
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

    /**
     * Resolve a logo oficial da loja para a experiencia publica.
     *
     * Prioridade:
     * 1. logo configurada em "Informacoes sobre a loja" no modulo PDF
     * 2. campo legado `brand_logo_url`, mantido como fallback
     *
     * @param array $settings Configuracoes gerais do plugin.
     * @return string
     */
    private static function get_shared_store_logo_url( $settings ) {
        $settings = is_array( $settings ) ? $settings : EOP_Settings::get_all();
        $pdf_logo = '';

        if ( class_exists( 'EOP_PDF_Settings' ) && method_exists( 'EOP_PDF_Settings', 'get_all' ) ) {
            $pdf_settings = EOP_PDF_Settings::get_all();
            $pdf_logo     = trim( (string) ( $pdf_settings['shop_logo_url'] ?? '' ) );
        }

        if ( '' !== $pdf_logo ) {
            return $pdf_logo;
        }

        return trim( (string) ( $settings['brand_logo_url'] ?? '' ) );
    }

    /**
     * Retorna uma cor de contraste simples a partir de um fundo hexadecimal.
     *
     * O objetivo aqui e manter os previews legiveis mesmo quando a experiencia
     * publica usa fundos escuros ou muito saturados no hero principal.
     *
     * @param string $background   Cor base do fundo.
     * @param string $dark_choice  Cor retornada para fundos claros.
     * @param string $light_choice Cor retornada para fundos escuros.
     * @return string
     */
    private static function get_contrast_text_color( $background, $dark_choice = '#16243a', $light_choice = '#ffffff' ) {
        $background = trim( (string) $background );

        if ( '' === $background || '#' !== $background[0] ) {
            return $light_choice;
        }

        $hex = ltrim( $background, '#' );

        if ( 3 === strlen( $hex ) ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
            return $light_choice;
        }

        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        $luminance = ( ( 0.299 * $r ) + ( 0.587 * $g ) + ( 0.114 * $b ) ) / 255;

        return $luminance > 0.62 ? $dark_choice : $light_choice;
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
