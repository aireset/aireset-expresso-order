<?php
/**
 * Plugin Name: Aireset — Expresso Order
 * Description: Pedido expresso para vendedores com busca de cliente, inclusao rapida de produtos e geracao de pedido no WooCommerce.
 * Version: 1.1.91
 * Author: Aireset Agencia Web
 * Author URI: https://aireset.com.br
 * Requires Plugins: woocommerce
 * Text Domain: aireset-expresso-order
 * Domain Path: /languages
 * License: Proprietary
 * License URI: https://aireset.com.br/termos-de-uso
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'EOP_REQUEST_START' ) ) {
	define( 'EOP_REQUEST_START', microtime( true ) );
}

define( 'EOP_VERSION', '1.1.91' );
define( 'EOP_PLUGIN_FILE', __FILE__ );
define( 'EOP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EOP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EOP_TEXT_DOMAIN', 'aireset-expresso-order' );

/**
 * Activation: create role and required pages.
 */
function eop_activate() {
	if ( ! get_role( 'vendedor_expresso' ) ) {
		add_role(
			'vendedor_expresso',
			__( 'Vendedor Expresso', EOP_TEXT_DOMAIN ),
			array(
				'read'                => true,
				'edit_shop_orders'    => true,
				'publish_shop_orders' => true,
				'read_shop_orders'    => true,
				'read_product'        => true,
				'edit_shop_order'     => true,
				'upload_files'        => false,
			)
		);
	}

	require_once EOP_PLUGIN_DIR . 'includes/trait-eop-license-guard.php';
	require_once EOP_PLUGIN_DIR . 'includes/class-page-installer.php';

	EOP_Page_Installer::activate();
}
register_activation_hook( __FILE__, 'eop_activate' );

/**
 * Deactivation: remove role.
 */
function eop_deactivate() {
	remove_role( 'vendedor_expresso' );
}
register_deactivation_hook( __FILE__, 'eop_deactivate' );

require_once EOP_PLUGIN_DIR . 'includes/trait-eop-license-guard.php';
require_once EOP_PLUGIN_DIR . 'includes/class-page-installer.php';
require_once EOP_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once EOP_PLUGIN_DIR . 'includes/class-performance-audit.php';


/* License gate */
require_once EOP_PLUGIN_DIR . 'includes/class-eop-license-manager.php';
EOP_License_Manager::get_instance( __FILE__ );

if ( ! EOP_License_Manager::is_valid() ) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Aireset Expresso Order: ative sua licenca para usar o plugin.', 'aireset-expresso-order' )
			);
		}
	);
	return;
}
/* /License gate */

require_once EOP_PLUGIN_DIR . 'includes/class-role.php';
require_once EOP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
require_once EOP_PLUGIN_DIR . 'includes/class-document-manager.php';
require_once EOP_PLUGIN_DIR . 'includes/class-order-creator.php';
require_once EOP_PLUGIN_DIR . 'includes/class-pdf-admin-page.php';
require_once EOP_PLUGIN_DIR . 'includes/class-pdf-settings.php';
require_once EOP_PLUGIN_DIR . 'includes/class-settings.php';
require_once EOP_PLUGIN_DIR . 'includes/class-settings-portability.php';
require_once EOP_PLUGIN_DIR . 'includes/class-shipping-calculator.php';
require_once EOP_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once EOP_PLUGIN_DIR . 'includes/class-public-proposal.php';
require_once EOP_PLUGIN_DIR . 'includes/class-orders-page.php';
require_once EOP_PLUGIN_DIR . 'includes/class-post-confirmation-flow.php';
require_once EOP_PLUGIN_DIR . 'includes/class-wc-pdf-integration.php';

/**
 * Load plugin translations.
 */
function eop_load_textdomain() {
	load_plugin_textdomain( EOP_TEXT_DOMAIN, false, dirname( plugin_basename( EOP_PLUGIN_FILE ) ) . '/languages' );
}
add_action( 'init', 'eop_load_textdomain' );

/**
 * Boot.
 */
function eop_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Aireset Expresso Order requer WooCommerce ativo.', EOP_TEXT_DOMAIN );
				echo '</p></div>';
			}
		);
		return;
	}

	EOP_Page_Installer::init();
	EOP_Admin_Page::init();
	EOP_Ajax_Handlers::init();
	EOP_Document_Manager::init();
	EOP_PDF_Admin_Page::init();
	EOP_PDF_Settings::init();
	EOP_WC_PDF_Integration::init();
	EOP_Shipping_Calculator::init();
	EOP_Settings::init();
	EOP_Settings_Portability::init();
	EOP_Shortcode::init();
	EOP_Public_Proposal::init();
	EOP_Orders_Page::init();
	EOP_Post_Confirmation_Flow::init();
	EOP_Role::restrict_menus();
}
add_action( 'plugins_loaded', 'eop_init' );
