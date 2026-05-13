<?php
defined( 'ABSPATH' ) || exit;
$settings = isset( $settings ) && is_array( $settings ) ? $settings : self::get_all();
?>
<section class="eop-settings-card eop-proposal-preview-settings">
    <h2><?php esc_html_e( 'Visual da listagem de pedidos', EOP_TEXT_DOMAIN ); ?></h2>
    <p><?php esc_html_e( 'Personalize a tela interna de pedidos com cabecalho, textos, botoes e identidade visual propria.', EOP_TEXT_DOMAIN ); ?></p>
    <?php self::render_orders_list_visual_editor( $settings ); ?>
</section>
<?php self::render_orders_list_preview_card( $settings ); ?>
