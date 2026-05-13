<?php
defined( 'ABSPATH' ) || exit;
$settings = isset( $settings ) && is_array( $settings ) ? $settings : self::get_all();
?>
<section class="eop-settings-card eop-proposal-preview-settings">
    <h2><?php esc_html_e( 'Visual de criar pedido', EOP_TEXT_DOMAIN ); ?></h2>
    <p><?php esc_html_e( 'Personalize os textos, botoes, cores, fontes e a base visual da tela interna de criacao de pedido.', EOP_TEXT_DOMAIN ); ?></p>
    <?php self::render_new_order_visual_editor( $settings ); ?>
</section>
<?php self::render_new_order_preview_card( $settings ); ?>
