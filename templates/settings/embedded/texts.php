<?php
defined( 'ABSPATH' ) || exit;
?>
<section class="eop-settings-card">
    <h2><?php esc_html_e( 'Textos', EOP_TEXT_DOMAIN ); ?></h2>
    <p><?php esc_html_e( 'Refine a narrativa do painel de vendedor e da proposta publica.', EOP_TEXT_DOMAIN ); ?></p>
    <div class="eop-settings-grid">
        <div class="eop-settings-field">
            <label for="eop_panel_title"><?php esc_html_e( 'Titulo do painel', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_panel_title" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[panel_title]" value="<?php echo esc_attr( $settings['panel_title'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <label for="eop_proposal_title"><?php esc_html_e( 'Titulo da proposta', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_proposal_title" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_title]" value="<?php echo esc_attr( $settings['proposal_title'] ); ?>" />
        </div>
        <div class="eop-settings-field is-full">
            <label for="eop_panel_subtitle"><?php esc_html_e( 'Subtitulo do painel', EOP_TEXT_DOMAIN ); ?></label>
            <textarea id="eop_panel_subtitle" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[panel_subtitle]"><?php echo esc_textarea( $settings['panel_subtitle'] ); ?></textarea>
        </div>
        <div class="eop-settings-field is-full">
            <label for="eop_proposal_description"><?php esc_html_e( 'Descricao da proposta', EOP_TEXT_DOMAIN ); ?></label>
            <textarea id="eop_proposal_description" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_description]"><?php echo esc_textarea( $settings['proposal_description'] ); ?></textarea>
        </div>
        <div class="eop-settings-field">
            <label for="eop_proposal_button_label"><?php esc_html_e( 'Texto do botao da proposta', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_proposal_button_label" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_button_label]" value="<?php echo esc_attr( $settings['proposal_button_label'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <label for="eop_proposal_pay_button_label"><?php esc_html_e( 'Texto do botao de pagamento', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_proposal_pay_button_label" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proposal_pay_button_label]" value="<?php echo esc_attr( $settings['proposal_pay_button_label'] ); ?>" />
        </div>
    </div>
</section>
