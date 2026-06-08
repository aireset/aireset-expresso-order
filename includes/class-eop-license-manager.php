<?php
/**
 * License manager for Aireset Expresso Order via Elite Licenser.
 *
 * @package Aireset\ExpressoOrder
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-eop-license-base.php';

class EOP_License_Manager {

	/** @var string */
	public $plugin_file;

	/** @var object|null */
	public $response_obj;

	/** @var string */
	public $license_message = '';

	/** @var bool */
	public $show_message = false;

	/** @var string */
	public $slug = 'aireset';

	/** @var string */
	public $license_page_slug = 'eop-license';

	/** @var string */
	public $plugin_page_slug = 'eop-pedido-expresso';

	/** @var string */
	public $plugin_version = '';

	/** @var bool */
	private $is_valid = false;

	/** @var self|null */
	private static $self_obj;

	const OPT_PREFIX = 'Aireset-ExpressoOrder';

	public function __construct( $plugin_base_file = '' ) {
		if ( empty( $plugin_base_file ) ) {
			$plugin_base_file = defined( 'EOP_PLUGIN_FILE' ) ? EOP_PLUGIN_FILE : '';
		}

		$this->plugin_file = $plugin_base_file;

		add_action( 'admin_print_styles', array( $this, 'set_admin_style' ) );
		$this->set_plugin_data();

		$main_lic_key = self::OPT_PREFIX . '_lic_Key';
		$lic_key_name = EOP_License_Core::get_lic_key_param( $main_lic_key );
		$license_key  = get_option( $lic_key_name, '' );

		if ( empty( $license_key ) ) {
			$license_key = get_option( $main_lic_key, '' );
			if ( ! empty( $license_key ) ) {
				update_option( $lic_key_name, $license_key );
				update_option( $main_lic_key, '' );
			}
		}

		$lic_email = get_option( self::OPT_PREFIX . '_lic_email', '' );

		EOP_License_Core::add_on_delete(
			function () {
				update_option( self::OPT_PREFIX . '_lic_Key', '' );
			}
		);

		if ( EOP_License_Core::check_wp_plugin( $license_key, $lic_email, $this->license_message, $this->response_obj, $this->plugin_file ) ) {
			$this->is_valid = true;
			add_action( 'admin_menu', array( $this, 'active_admin_menu' ), 99999 );
			add_action( 'admin_post_' . self::OPT_PREFIX . '_el_deactivate_license', array( $this, 'action_deactivate_license' ) );
		} else {
			if ( ! empty( $license_key ) && ! empty( $this->license_message ) ) {
				$this->show_message = true;
			}

			update_option( $lic_key_name, '' );
			add_action( 'admin_post_' . self::OPT_PREFIX . '_el_activate_license', array( $this, 'action_activate_license' ) );
			add_action( 'admin_menu', array( $this, 'inactive_menu' ), 99999 );
		}
	}

	public static function is_valid() {
		$instance = self::get_instance();
		return $instance ? $instance->is_valid : false;
	}

	public static function &get_instance( $plugin_base_file = null ) {
		if ( empty( self::$self_obj ) && ! empty( $plugin_base_file ) ) {
			self::$self_obj = new self( $plugin_base_file );
		}

		return self::$self_obj;
	}

	public function set_plugin_data() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$data = get_plugin_data( $this->plugin_file, false, false );
		if ( isset( $data['Version'] ) ) {
			$this->plugin_version = $data['Version'];
		}
	}

	public function set_admin_style() {
		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( ! in_array( $current_page, array( $this->license_page_slug, 'eop-license' ), true ) ) {
			return;
		}

		wp_register_style(
			'eop-license-css',
			plugins_url( 'assets/css/eop-license.css', $this->plugin_file ),
			array(),
			$this->plugin_version
		);
		wp_enqueue_style( 'eop-license-css' );
	}

	public function active_admin_menu() {
		ensure_aireset_parent_menu();

		remove_submenu_page( $this->slug, $this->license_page_slug );

		// add_submenu_page(
		// 	$this->slug,
		// 	__( 'Licenca', 'aireset-expresso-order' ),
		// 	__( 'Licenca', 'aireset-expresso-order' ),
		// 	'manage_options',
		// 	$this->license_page_slug,
		// 	array( $this, 'render_active_page' )
		// );
	}

	public function inactive_menu() {
		ensure_aireset_parent_menu();

		remove_submenu_page( $this->slug, $this->plugin_page_slug );
		remove_submenu_page( $this->slug, $this->license_page_slug );

		add_submenu_page(
			$this->slug,
			__( 'Pedido Expresso', 'aireset-expresso-order' ),
			__( 'Pedido Expresso', 'aireset-expresso-order' ),
			'manage_options',
			$this->plugin_page_slug,
			array( $this, 'license_form' )
		);
	}

	public function action_activate_license() {
		check_admin_referer( 'el-license' );

		$license_key   = ! empty( $_POST['el_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['el_license_key'] ) ) : '';
		$license_email = ! empty( $_POST['el_license_email'] ) ? sanitize_email( wp_unslash( $_POST['el_license_email'] ) ) : '';

		$main_lic_key = self::OPT_PREFIX . '_lic_Key';
		$lic_key_name = EOP_License_Core::get_lic_key_param( $main_lic_key );

		update_option( $lic_key_name, $license_key );
		update_option( self::OPT_PREFIX . '_lic_email', $license_email );
		update_option( '_site_transient_update_plugins', '' );

		$redirect_url = admin_url( 'admin.php?page=' . $this->plugin_page_slug . '&view=license' );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function action_deactivate_license() {
		check_admin_referer( 'el-license' );

		$message      = '';
		$main_lic_key = self::OPT_PREFIX . '_lic_Key';
		$lic_key_name = EOP_License_Core::get_lic_key_param( $main_lic_key );

		if ( EOP_License_Core::remove_license_key( $this->plugin_file, $message ) ) {
			update_option( $lic_key_name, '' );
			update_option( '_site_transient_update_plugins', '' );
		}

		$redirect_url = admin_url( 'admin.php?page=' . $this->plugin_page_slug . '&view=license' );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function render_active_page() {
		if ( class_exists( 'EOP_Admin_Page' ) ) {
			wp_safe_redirect( EOP_Admin_Page::get_view_url( 'license' ) );
			exit;
		}

		$this->activated();
	}

	public function activated() {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::OPT_PREFIX . '_el_deactivate_license' ); ?>" />
			<div class="el-license-container">
				<h3 class="el-license-title">
					<i class="dashicons-before dashicons-star-filled"></i>
					<?php esc_html_e( 'Expresso Order - Informacoes da Licenca', 'aireset-expresso-order' ); ?>
				</h3>
				<hr>
				<ul class="el-license-info">
					<li><div>
						<span class="el-license-info-title"><?php esc_html_e( 'Status', 'aireset-expresso-order' ); ?></span>
						<?php if ( ! empty( $this->response_obj->is_valid ) ) : ?>
							<span class="el-license-valid"><?php esc_html_e( 'Valida', 'aireset-expresso-order' ); ?></span>
						<?php else : ?>
							<span class="el-license-invalid"><?php esc_html_e( 'Invalida', 'aireset-expresso-order' ); ?></span>
						<?php endif; ?>
					</div></li>
					<li><div>
						<span class="el-license-info-title"><?php esc_html_e( 'Tipo de Licenca', 'aireset-expresso-order' ); ?></span>
						<?php echo esc_html( $this->response_obj->license_title ?? '' ); ?>
					</div></li>
					<li><div>
						<span class="el-license-info-title"><?php esc_html_e( 'Expira em', 'aireset-expresso-order' ); ?></span>
						<?php echo esc_html( $this->response_obj->expire_date ?? '' ); ?>
						<?php if ( ! empty( $this->response_obj->expire_renew_link ) ) : ?>
							<a target="_blank" class="el-blue-btn" href="<?php echo esc_url( $this->response_obj->expire_renew_link ); ?>">
								<?php esc_html_e( 'Renovar', 'aireset-expresso-order' ); ?>
							</a>
						<?php endif; ?>
					</div></li>
					<li><div>
						<span class="el-license-info-title"><?php esc_html_e( 'Suporte ate', 'aireset-expresso-order' ); ?></span>
						<?php echo esc_html( $this->response_obj->support_end ?? '' ); ?>
						<?php if ( ! empty( $this->response_obj->support_renew_link ) ) : ?>
							<a target="_blank" class="el-blue-btn" href="<?php echo esc_url( $this->response_obj->support_renew_link ); ?>">
								<?php esc_html_e( 'Renovar', 'aireset-expresso-order' ); ?>
							</a>
						<?php endif; ?>
					</div></li>
					<li><div>
						<span class="el-license-info-title"><?php esc_html_e( 'Chave', 'aireset-expresso-order' ); ?></span>
						<span class="el-license-key">
							<?php
							$key = (string) ( $this->response_obj->license_key ?? '' );
							echo esc_html( substr( $key, 0, 9 ) . 'XXXXXXXX-XXXXXXXX' . substr( $key, -9 ) );
							?>
						</span>
					</div></li>
				</ul>
				<div class="el-license-active-btn">
					<?php wp_nonce_field( 'el-license' ); ?>
					<?php submit_button( __( 'Desativar Licenca', 'aireset-expresso-order' ) ); ?>
				</div>
			</div>
		</form>
		<?php
	}

	public function license_form() {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::OPT_PREFIX . '_el_activate_license' ); ?>" />
			<div class="eop-license-layout">
				<aside class="eop-license-aside">
					<span class="eop-license-aside__eyebrow"><?php esc_html_e( 'Aireset', 'aireset-expresso-order' ); ?></span>
					<h2><?php esc_html_e( 'Pedido Expresso', 'aireset-expresso-order' ); ?></h2>
					<p><?php esc_html_e( 'Ative sua licenca para liberar o fluxo comercial, o painel SPA e a geracao de pedidos no WooCommerce.', 'aireset-expresso-order' ); ?></p>
					<ul class="eop-license-aside__list">
						<li><?php esc_html_e( 'Ativacao simples com chave e e-mail', 'aireset-expresso-order' ); ?></li>
						<li><?php esc_html_e( 'Atualizacoes e renovacao centralizadas', 'aireset-expresso-order' ); ?></li>
						<li><?php esc_html_e( 'Mesmo padrao visual do painel do plugin', 'aireset-expresso-order' ); ?></li>
					</ul>
				</aside>
				<div class="el-license-container">
					<h3 class="el-license-title">
						<i class="dashicons-before dashicons-star-filled"></i>
						<?php esc_html_e( 'Expresso Order - Ativacao de Licenca', 'aireset-expresso-order' ); ?>
					</h3>
					<hr>
					<?php if ( ! empty( $this->show_message ) && ! empty( $this->license_message ) ) : ?>
						<div class="notice notice-error is-dismissible">
							<p><?php echo esc_html( $this->license_message ); ?></p>
						</div>
					<?php endif; ?>
					<p><?php esc_html_e( 'Insira sua chave de licenca e e-mail para ativar o plugin.', 'aireset-expresso-order' ); ?></p>
					<div class="el-license-field">
						<label for="el_license_key"><?php esc_html_e( 'Chave de Licenca', 'aireset-expresso-order' ); ?></label>
						<input type="text" class="regular-text" name="el_license_key" id="el_license_key" placeholder="<?php esc_attr_e( 'xxxxxxxx-xxxxxxxx-xxxxxxxx-xxxxxxxx', 'aireset-expresso-order' ); ?>">
					</div>
					<div class="el-license-field">
						<label for="el_license_email"><?php esc_html_e( 'E-mail', 'aireset-expresso-order' ); ?></label>
						<input type="email" class="regular-text" name="el_license_email" id="el_license_email" value="<?php echo esc_attr( get_option( self::OPT_PREFIX . '_lic_email', get_bloginfo( 'admin_email' ) ) ); ?>" placeholder="<?php esc_attr_e( 'seu@email.com', 'aireset-expresso-order' ); ?>">
					</div>
					<div class="el-license-active-btn">
						<?php wp_nonce_field( 'el-license' ); ?>
						<?php submit_button( __( 'Ativar Licenca', 'aireset-expresso-order' ) ); ?>
					</div>
				</div>
			</div>
		</form>
		<?php
	}
}
