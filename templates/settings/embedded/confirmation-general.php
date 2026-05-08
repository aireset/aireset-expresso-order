<?php
defined( 'ABSPATH' ) || exit;
?>
<section class="eop-settings-card">
    <h2><?php esc_html_e( 'Fluxo complementar apos a proposta', EOP_TEXT_DOMAIN ); ?></h2>
    <p><?php esc_html_e( 'Centralize aqui as regras gerais, os textos principais e o comportamento do fluxo depois que a proposta ja foi aprovada.', EOP_TEXT_DOMAIN ); ?></p>
    <div class="eop-settings-grid">
        <div class="eop-settings-field is-full">
            <?php self::render_help_label( 'span', __( 'Ativar fluxo complementar', EOP_TEXT_DOMAIN ), 'enable_post_confirmation_flow' ); ?>
            <div class="eop-settings-switch-shell">
                <input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_post_confirmation_flow]" value="<?php echo esc_attr( $settings['enable_post_confirmation_flow'] ); ?>" />
                <button
                    type="button"
                    class="eop-settings-switcher<?php echo 'yes' === $settings['enable_post_confirmation_flow'] ? ' is-enabled' : ''; ?>"
                    role="switch"
                    aria-checked="<?php echo 'yes' === $settings['enable_post_confirmation_flow'] ? 'true' : 'false'; ?>"
                    data-target-name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_post_confirmation_flow]"
                    data-enabled-value="yes"
                    data-disabled-value="no"
                    aria-label="<?php esc_attr_e( 'Alternar fluxo complementar', EOP_TEXT_DOMAIN ); ?>"
                >
                    <span class="eop-settings-switcher__label eop-settings-switcher__label--off">Off</span>
                    <span class="eop-settings-switcher__thumb" aria-hidden="true"></span>
                    <span class="eop-settings-switcher__label eop-settings-switcher__label--on">On</span>
                </button>
                <span class="eop-settings-switcher__status" aria-live="polite">
                    <?php echo 'yes' === $settings['enable_post_confirmation_flow'] ? esc_html__( 'Ativado', EOP_TEXT_DOMAIN ) : esc_html__( 'Desativado', EOP_TEXT_DOMAIN ); ?>
                </span>
            </div>
        </div>
        <div class="eop-settings-field">
            <?php self::render_help_label( 'label', __( 'Produtos bloqueados', EOP_TEXT_DOMAIN ), 'post_confirmation_locked_products', array( 'for' => 'eop_post_confirmation_locked_products_selector' ) ); ?>
            <input id="eop_post_confirmation_locked_products" type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_locked_products]" value="<?php echo esc_attr( $locked_selector['serialized_value'] ); ?>" />
            <select id="eop_post_confirmation_locked_products_selector" class="eop-settings-product-selector" data-target-input="#eop_post_confirmation_locked_products" multiple>
                <?php foreach ( $locked_selector['options'] as $option ) : ?>
                    <option value="<?php echo esc_attr( $option['id'] ); ?>" selected="selected"><?php echo esc_html( $option['text'] ); ?></option>
                <?php endforeach; ?>
            </select>
            <small class="eop-settings-help"><?php esc_html_e( 'Busque produtos por nome ou SKU para bloquear a alteracao do nome na etapa final.', EOP_TEXT_DOMAIN ); ?></small>
            <?php if ( ! empty( $locked_selector['missing_tokens'] ) ) : ?>
                <small class="eop-settings-help"><?php echo esc_html( sprintf( __( 'Tokens antigos preservados ate a proxima atualizacao desta lista: %s', EOP_TEXT_DOMAIN ), implode( ', ', $locked_selector['missing_tokens'] ) ) ); ?></small>
            <?php endif; ?>
        </div>
        <div class="eop-settings-field is-full">
            <?php self::render_help_label( 'span', __( 'Exigir anexo', EOP_TEXT_DOMAIN ), 'post_confirmation_require_attachment' ); ?>
            <div class="eop-settings-switch-shell">
                <input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_require_attachment]" value="<?php echo esc_attr( $settings['post_confirmation_require_attachment'] ); ?>" />
                <button
                    type="button"
                    class="eop-settings-switcher<?php echo 'yes' === $settings['post_confirmation_require_attachment'] ? ' is-enabled' : ''; ?>"
                    role="switch"
                    aria-checked="<?php echo 'yes' === $settings['post_confirmation_require_attachment'] ? 'true' : 'false'; ?>"
                    data-target-name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_require_attachment]"
                    data-enabled-value="yes"
                    data-disabled-value="no"
                    aria-label="<?php esc_attr_e( 'Alternar anexo obrigatorio', EOP_TEXT_DOMAIN ); ?>"
                >
                    <span class="eop-settings-switcher__label eop-settings-switcher__label--off">Off</span>
                    <span class="eop-settings-switcher__thumb" aria-hidden="true"></span>
                    <span class="eop-settings-switcher__label eop-settings-switcher__label--on">On</span>
                </button>
                <span class="eop-settings-switcher__status" aria-live="polite">
                    <?php echo 'yes' === $settings['post_confirmation_require_attachment'] ? esc_html__( 'Ativado', EOP_TEXT_DOMAIN ) : esc_html__( 'Desativado', EOP_TEXT_DOMAIN ); ?>
                </span>
            </div>
        </div>
        <div class="eop-settings-field">
            <?php self::render_help_label( 'label', __( 'Titulo do upload', EOP_TEXT_DOMAIN ), 'post_confirmation_upload_title', array( 'for' => 'eop_post_confirmation_upload_title' ) ); ?>
            <input id="eop_post_confirmation_upload_title" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_upload_title]" value="<?php echo esc_attr( $settings['post_confirmation_upload_title'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <?php self::render_help_label( 'label', __( 'Label do anexo', EOP_TEXT_DOMAIN ), 'post_confirmation_upload_field_label', array( 'for' => 'eop_post_confirmation_upload_field_label' ) ); ?>
            <input id="eop_post_confirmation_upload_field_label" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_upload_field_label]" value="<?php echo esc_attr( $settings['post_confirmation_upload_field_label'] ); ?>" />
        </div>
        <div class="eop-settings-field is-full">
            <?php self::render_help_label( 'label', __( 'Descricao do upload', EOP_TEXT_DOMAIN ), 'post_confirmation_upload_description', array( 'for' => 'eop_post_confirmation_upload_description' ) ); ?>
            <textarea id="eop_post_confirmation_upload_description" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_upload_description]"><?php echo esc_textarea( $settings['post_confirmation_upload_description'] ); ?></textarea>
        </div>
        <div class="eop-settings-field">
            <?php self::render_help_label( 'label', __( 'Botao do upload', EOP_TEXT_DOMAIN ), 'post_confirmation_upload_button_label', array( 'for' => 'eop_post_confirmation_upload_button_label' ) ); ?>
            <input id="eop_post_confirmation_upload_button_label" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_upload_button_label]" value="<?php echo esc_attr( $settings['post_confirmation_upload_button_label'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <?php self::render_help_label( 'label', __( 'Titulo da personalizacao', EOP_TEXT_DOMAIN ), 'post_confirmation_products_title', array( 'for' => 'eop_post_confirmation_products_title' ) ); ?>
            <input id="eop_post_confirmation_products_title" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_products_title]" value="<?php echo esc_attr( $settings['post_confirmation_products_title'] ); ?>" />
        </div>
        <div class="eop-settings-field is-full">
            <?php self::render_help_label( 'label', __( 'Descricao da personalizacao', EOP_TEXT_DOMAIN ), 'post_confirmation_products_description', array( 'for' => 'eop_post_confirmation_products_description' ) ); ?>
            <textarea id="eop_post_confirmation_products_description" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_products_description]"><?php echo esc_textarea( $settings['post_confirmation_products_description'] ); ?></textarea>
        </div>
        <div class="eop-settings-field">
            <?php self::render_help_label( 'label', __( 'Botao da personalizacao', EOP_TEXT_DOMAIN ), 'post_confirmation_products_button_label', array( 'for' => 'eop_post_confirmation_products_button_label' ) ); ?>
            <input id="eop_post_confirmation_products_button_label" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_products_button_label]" value="<?php echo esc_attr( $settings['post_confirmation_products_button_label'] ); ?>" />
        </div>
        <div class="eop-settings-field">
            <?php self::render_help_label( 'label', __( 'Titulo da conclusao', EOP_TEXT_DOMAIN ), 'post_confirmation_completion_title', array( 'for' => 'eop_post_confirmation_completion_title' ) ); ?>
            <input id="eop_post_confirmation_completion_title" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_completion_title]" value="<?php echo esc_attr( $settings['post_confirmation_completion_title'] ); ?>" />
        </div>
        <div class="eop-settings-field is-full">
            <?php self::render_help_label( 'label', __( 'Descricao da conclusao', EOP_TEXT_DOMAIN ), 'post_confirmation_completion_description', array( 'for' => 'eop_post_confirmation_completion_description' ) ); ?>
            <textarea id="eop_post_confirmation_completion_description" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_confirmation_completion_description]"><?php echo esc_textarea( $settings['post_confirmation_completion_description'] ); ?></textarea>
        </div>
    </div>
</section>
