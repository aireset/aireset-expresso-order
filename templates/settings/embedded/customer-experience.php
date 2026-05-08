<?php
defined( 'ABSPATH' ) || exit;
?>
<section class="eop-settings-card">
    <h2><?php esc_html_e( 'Experiencia publica confirmada', EOP_TEXT_DOMAIN ); ?></h2>
    <p><?php esc_html_e( 'Edite em um unico lugar o visual da pagina confirmada e do fluxo complementar visto pelo cliente.', EOP_TEXT_DOMAIN ); ?></p>
    <div class="eop-settings-grid">
        <div class="eop-settings-field is-full">
            <label for="eop_customer_experience_font_family"><?php esc_html_e( 'Fonte da experiencia publica', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_customer_experience_font_family" class="select_font eop-font-field" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_font_family]" value="<?php echo esc_attr( $settings['customer_experience_font_family'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <label for="eop_customer_experience_title_size"><?php esc_html_e( 'Tamanho do titulo principal', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_customer_experience_title_size" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_title_size]" value="<?php echo esc_attr( $settings['customer_experience_title_size'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <label for="eop_customer_experience_text_size"><?php esc_html_e( 'Tamanho do texto base', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_customer_experience_text_size" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_text_size]" value="<?php echo esc_attr( $settings['customer_experience_text_size'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <label for="eop_customer_experience_background_color"><?php esc_html_e( 'Fundo da pagina', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_customer_experience_background_color" class="eop-color-field" type="text" data-default-color="#edf2fb" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_background_color]" value="<?php echo esc_attr( $settings['customer_experience_background_color'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <label for="eop_customer_experience_hero_background_color"><?php esc_html_e( 'Fundo do hero', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_customer_experience_hero_background_color" class="eop-color-field" type="text" data-default-color="#0f1b35" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_hero_background_color]" value="<?php echo esc_attr( $settings['customer_experience_hero_background_color'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <label for="eop_customer_experience_panel_background_color"><?php esc_html_e( 'Fundo dos cards principais', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_customer_experience_panel_background_color" class="eop-color-field" type="text" data-default-color="#ffffff" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_panel_background_color]" value="<?php echo esc_attr( $settings['customer_experience_panel_background_color'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <label for="eop_customer_experience_sidebar_background_color"><?php esc_html_e( 'Fundo dos cards laterais', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_customer_experience_sidebar_background_color" class="eop-color-field" type="text" data-default-color="#f6f8fc" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_sidebar_background_color]" value="<?php echo esc_attr( $settings['customer_experience_sidebar_background_color'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <label for="eop_customer_experience_accent_color"><?php esc_html_e( 'Cor de destaque', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_customer_experience_accent_color" class="eop-color-field" type="text" data-default-color="#d78a2f" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_accent_color]" value="<?php echo esc_attr( $settings['customer_experience_accent_color'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <label for="eop_customer_experience_text_color"><?php esc_html_e( 'Texto principal', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_customer_experience_text_color" class="eop-color-field" type="text" data-default-color="#16243a" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_text_color]" value="<?php echo esc_attr( $settings['customer_experience_text_color'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <label for="eop_customer_experience_muted_color"><?php esc_html_e( 'Texto auxiliar', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_customer_experience_muted_color" class="eop-color-field" type="text" data-default-color="#66768d" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_muted_color]" value="<?php echo esc_attr( $settings['customer_experience_muted_color'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <label for="eop_customer_experience_eyebrow"><?php esc_html_e( 'Label superior', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_customer_experience_eyebrow" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_eyebrow]" value="<?php echo esc_attr( $settings['customer_experience_eyebrow'] ); ?>" />
        </div>
        <div class="eop-settings-field is-full">
            <label for="eop_customer_experience_title"><?php esc_html_e( 'Titulo principal', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_customer_experience_title" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_title]" value="<?php echo esc_attr( $settings['customer_experience_title'] ); ?>" />
        </div>
        <div class="eop-settings-field is-full">
            <label for="eop_customer_experience_description"><?php esc_html_e( 'Descricao principal', EOP_TEXT_DOMAIN ); ?></label>
            <textarea id="eop_customer_experience_description" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_description]"><?php echo esc_textarea( $settings['customer_experience_description'] ); ?></textarea>
        </div>
        <div class="eop-settings-field is-full">
            <label for="eop_customer_experience_total_note"><?php esc_html_e( 'Texto de apoio do total', EOP_TEXT_DOMAIN ); ?></label>
            <textarea id="eop_customer_experience_total_note" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_total_note]"><?php echo esc_textarea( $settings['customer_experience_total_note'] ); ?></textarea>
        </div>
        <div class="eop-settings-field">
            <label for="eop_customer_experience_items_eyebrow"><?php esc_html_e( 'Label da secao de itens', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_customer_experience_items_eyebrow" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_items_eyebrow]" value="<?php echo esc_attr( $settings['customer_experience_items_eyebrow'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <label for="eop_customer_experience_items_title"><?php esc_html_e( 'Titulo da secao de itens', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_customer_experience_items_title" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_items_title]" value="<?php echo esc_attr( $settings['customer_experience_items_title'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <label for="eop_customer_experience_summary_eyebrow"><?php esc_html_e( 'Label do resumo lateral', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_customer_experience_summary_eyebrow" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_summary_eyebrow]" value="<?php echo esc_attr( $settings['customer_experience_summary_eyebrow'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <label for="eop_customer_experience_summary_title"><?php esc_html_e( 'Titulo do resumo lateral', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_customer_experience_summary_title" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_summary_title]" value="<?php echo esc_attr( $settings['customer_experience_summary_title'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <label for="eop_customer_experience_financial_eyebrow"><?php esc_html_e( 'Label do resumo financeiro', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_customer_experience_financial_eyebrow" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_financial_eyebrow]" value="<?php echo esc_attr( $settings['customer_experience_financial_eyebrow'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <label for="eop_customer_experience_actions_eyebrow"><?php esc_html_e( 'Label do card de acao', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_customer_experience_actions_eyebrow" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_actions_eyebrow]" value="<?php echo esc_attr( $settings['customer_experience_actions_eyebrow'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <label for="eop_customer_experience_actions_title"><?php esc_html_e( 'Titulo do card de acao', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_customer_experience_actions_title" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_actions_title]" value="<?php echo esc_attr( $settings['customer_experience_actions_title'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <label for="eop_customer_experience_progress_label"><?php esc_html_e( 'Titulo do mapa de jornada', EOP_TEXT_DOMAIN ); ?></label>
            <input id="eop_customer_experience_progress_label" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_experience_progress_label]" value="<?php echo esc_attr( $settings['customer_experience_progress_label'] ); ?>" />
        </div>
    </div>
</section>
