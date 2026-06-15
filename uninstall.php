<?php
/**
 * Limpeza ao desinstalar o plugin.
 *
 * Remove opcoes, transients, role, paginas gerenciadas e o diretorio de PDFs.
 * NAO remove meta dos pedidos (dado de negocio do WooCommerce).
 *
 * @package Aireset_Expresso_Order
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Faz a limpeza para um unico site.
 */
function eop_uninstall_cleanup_site() {
	global $wpdb;

	// Opcoes e transients com prefixo do plugin, incluindo as chaves/transients
	// de licenca (prefixo 'Aireset-ExpressoOrder' e product_base 'aireset-expresso-order').
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE 'eop\\_%'
		    OR option_name LIKE '\\_transient\\_eop\\_%'
		    OR option_name LIKE '\\_transient\\_timeout\\_eop\\_%'
		    OR option_name LIKE '\\_site\\_transient\\_eop\\_%'
		    OR option_name LIKE 'Aireset-ExpressoOrder%'
		    OR option_name LIKE '\\_transient\\_aireset-expresso-order\\_up'
		    OR option_name LIKE '\\_transient\\_timeout\\_aireset-expresso-order\\_up'"
	);

	// Role do vendedor.
	if ( function_exists( 'remove_role' ) ) {
		remove_role( 'vendedor_expresso' );
	}

	// Paginas gerenciadas (proposta, pedido, etc.).
	$managed = $wpdb->get_col(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_eop_managed_shortcode'"
	);

	if ( ! empty( $managed ) ) {
		foreach ( $managed as $page_id ) {
			wp_delete_post( (int) $page_id, true );
		}
	}

	// Diretorio de PDFs gerados.
	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['basedir'] ) ) {
		eop_uninstall_rrmdir( trailingslashit( $uploads['basedir'] ) . 'eop-pdf' );
	}
}

/**
 * Remove um diretorio recursivamente (uso restrito a uninstall).
 *
 * @param string $dir Caminho do diretorio.
 */
function eop_uninstall_rrmdir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	$items = scandir( $dir );
	if ( false === $items ) {
		return;
	}

	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . DIRECTORY_SEPARATOR . $item;
		if ( is_dir( $path ) ) {
			eop_uninstall_rrmdir( $path );
		} else {
			@unlink( $path );
		}
	}

	@rmdir( $dir );
}

if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		eop_uninstall_cleanup_site();
		restore_current_blog();
	}
} else {
	eop_uninstall_cleanup_site();
}
