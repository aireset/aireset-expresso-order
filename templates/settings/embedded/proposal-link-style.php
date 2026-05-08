<?php
defined( 'ABSPATH' ) || exit;
?>
<section class="eop-settings-card eop-proposal-preview-settings">
    <h2><?php esc_html_e( 'Visual da pagina do cliente', EOP_TEXT_DOMAIN ); ?></h2>
    <p><?php esc_html_e( 'Use a mesma central visual da proposta para ajustar a experiencia publica do cliente com preview isolado no final.', EOP_TEXT_DOMAIN ); ?></p>
    <?php self::render_order_link_visual_editor( $settings ); ?>
</section>
<?php if ( class_exists( 'EOP_Public_Proposal' ) && method_exists( 'EOP_Public_Proposal', 'render_admin_preview_card' ) ) : ?>
    <?php echo EOP_Public_Proposal::render_admin_preview_card( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php endif; ?>
