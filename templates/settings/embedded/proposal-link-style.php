<?php
defined( 'ABSPATH' ) || exit;
$settings = isset( $settings ) && is_array( $settings ) ? $settings : self::get_all();
?>
<section class="eop-settings-card eop-proposal-preview-settings">
    <h2><?php esc_html_e( 'Visual da proposta do cliente', EOP_TEXT_DOMAIN ); ?></h2>
    <p><?php esc_html_e( 'Centralize aqui os textos, botoes, cores, fontes, fundos, estados da jornada e o preview da proposta publica enviada ao cliente.', EOP_TEXT_DOMAIN ); ?></p>
    <?php self::render_order_link_visual_editor( $settings ); ?>
</section>
<?php if ( class_exists( 'EOP_Public_Proposal' ) && method_exists( 'EOP_Public_Proposal', 'render_admin_preview_card' ) ) : ?>
    <?php echo EOP_Public_Proposal::render_admin_preview_card( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php endif; ?>
<?php if ( class_exists( 'EOP_Post_Confirmation_Flow' ) && method_exists( 'EOP_Post_Confirmation_Flow', 'render_admin_contract_preview_markup' ) ) : ?>
    <?php echo EOP_Post_Confirmation_Flow::render_admin_contract_preview_markup( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php endif; ?>
<?php if ( class_exists( 'EOP_Post_Confirmation_Flow' ) && method_exists( 'EOP_Post_Confirmation_Flow', 'render_admin_upload_products_preview_markup' ) ) : ?>
    <?php echo EOP_Post_Confirmation_Flow::render_admin_upload_products_preview_markup( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php endif; ?>
