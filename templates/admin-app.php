<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap eop-admin-app-shell">
	<script>
		window.eopAdminSpaConfig = <?php echo wp_json_encode( EOP_Admin_SPA::get_client_config() ); ?>;
		window.eopAdminSpaBootstrap = <?php echo wp_json_encode( EOP_Admin_SPA::get_bootstrap_payload() ); ?>;
	</script>
	<?php // Ancora para o WordPress posicionar as notices admin no topo, fora do app React. ?>
	<h1 class="screen-reader-text"><?php esc_html_e( 'Pedido Expresso', EOP_TEXT_DOMAIN ); ?></h1>
	<hr class="wp-header-end" />
	<div id="eop-admin-app" class="eop-admin-app-root"></div>
	<noscript>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'O admin SPA requer JavaScript ativo. Sem JavaScript, use o fallback legado.', EOP_TEXT_DOMAIN ); ?></p>
		</div>
	</noscript>
</div>
