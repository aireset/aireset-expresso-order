<?php
/**
 * Aireset License SDK — Comunicação com Elite Licenser.
 *
 * AVISO: Este arquivo contém propriedade intelectual da Aireset Agência Web.
 * Qualquer modificação, descompilação, engenharia reversa ou redistribuição
 * não autorizada é estritamente proibida e sujeita a medidas legais.
 *
 * @package  Aireset\ExpressoOrder
 * @license  Proprietary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-eop-telemetry.php';
require_once __DIR__ . '/class-eop-integrity.php';

if ( ! class_exists( 'EOP_License_Core' ) ) {

	/**
	 * SDK base para comunicação com o servidor de licenças Elite Licenser.
	 *
	 * Classe sem namespace — precisa ser acessível globalmente pelo SDK.
	 */
	class EOP_License_Core {

		/* ───────── Credenciais do produto ───────── */
		public  $key          = 'C4293B5D4F9D3BAA';
		private $product_id   = '2';
		private $product_base = 'aireset-expresso-order';
		private $server_host  = 'https://aireset.com.br/wp-json/zhi-linc/';

		/* Chave pública Ed25519 (hex) do servidor de licenças. Apenas comandos
		 * remotos (rl/rc/dl) ASSINADOS com a chave privada correspondente — que
		 * existe somente no servidor aireset.com.br — são aceitos. A chave pública
		 * não é segredo; pode ficar embarcada. Gere o par uma única vez e cole a
		 * parte pública aqui (ver OPERATIONS.md). Vazia = comando remoto desativado. */
		const CMD_PUBKEY = '';

		/* ───────── Controle interno ───────── */
		private $has_check_update = true;
		private $plugin_file;
		private $version          = '';
		private $email_address    = '';
		private $telemetry_core   = null;
		private $integrity_core   = null;
		private static $selfobj   = null;
		private static $_on_delete_license = [];

		/* ───────── Fingerprint de integridade ───────── */
		private static $_fp_salt = 'eop_ar_2026';

		/* ══════════════════════════════════════════════
		 *  Construtor
		 * ══════════════════════════════════════════════ */
		public function __construct( $plugin_base_file = '' ) {
			if ( empty( $plugin_base_file ) ) {
				$plugin_base_file = defined( 'EOP_PLUGIN_FILE' ) ? EOP_PLUGIN_FILE : '';
			}

			$this->plugin_file = $plugin_base_file;

			if ( empty( $this->version ) ) {
				$this->version = $this->get_current_version();
			}

			$this->boot_auxiliary_cores();

			if ( $this->has_check_update && function_exists( 'add_action' ) ) {
				add_action( 'admin_post_' . $this->product_base . '_fupc', function () {
					update_option( '_site_transient_update_plugins', '' );
					delete_transient( $this->product_base . '_up' );
					wp_redirect( admin_url( 'plugins.php' ) );
					exit;
				} );
				add_action( 'init', [ $this, 'init_action_handler' ] );

				if ( function_exists( 'add_filter' ) ) {
					add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'plugin_update' ] );
					add_filter( 'plugins_api', [ $this, 'check_update_info' ], 10, 3 );
					add_filter( 'plugin_row_meta', function ( $links, $pf ) {
						if ( plugin_basename( $this->plugin_file ) === $pf ) {
							$links[] = '<a href="' . esc_url( admin_url( 'admin-post.php' ) . '?action=' . $this->product_base . '_fupc' ) . '">' . esc_html__( 'Update Check', 'aireset-expresso-order' ) . '</a>';
						}
						return $links;
					}, 10, 2 );
					add_action( 'in_plugin_update_message-' . plugin_basename( $this->plugin_file ), [ $this, 'update_message_cb' ], 20, 2 );
					add_action( 'upgrader_process_complete', function () {
						update_option( '_site_transient_update_plugins', '' );
					}, 10, 2 );
				}
			}
		}

		private function boot_auxiliary_cores() {
			$this->telemetry_core = new EOP_Telemetry_Core(
				array(
					'server_host'  => $this->server_host,
					'product_id'   => $this->product_id,
					'product_base' => $this->product_base,
					'version'      => $this->version,
					'domain'       => $this->get_domain(),
					'encoder'      => array( $this, 'encode_transport_payload' ),
				)
			);

			$this->integrity_core = new EOP_Integrity_Core(
				array(
					'plugin_dir'            => dirname( $this->plugin_file ),
					'min_license_base_size' => 8000,
				)
			);
		}

		/* ══════════════════════════════════════════════
		 *  Funções estáticas públicas
		 * ══════════════════════════════════════════════ */
		public static function add_on_delete( $func ) {
			self::$_on_delete_license[] = $func;
		}

		public static function &get_instance( $plugin_base_file = null ) {
			if ( empty( self::$selfobj ) && ! empty( $plugin_base_file ) ) {
				self::$selfobj = new self( $plugin_base_file );
			}
			return self::$selfobj;
		}

		public static function check_wp_plugin( $purchase_key, $email, &$error = '', &$response_obj = null, $plugin_base_file = '' ) {
			$obj = self::get_instance( $plugin_base_file );
			$obj->set_email_address( $email );
			return $obj->_check_wp_plugin( $purchase_key, $error, $response_obj );
		}

		public static function remove_license_key( $plugin_base_file, &$message = '' ) {
			$obj = self::get_instance( $plugin_base_file );
			$obj->clean_update_info();
			return $obj->_remove_wp_plugin_license( $message );
		}

		public static function get_lic_key_param( $key ) {
			$raw_url = self::get_raw_wp();
			return $key . '_s' . hash( 'crc32b', $raw_url );
		}

		public static function get_register_info() {
			if ( ! empty( self::$selfobj ) ) {
				return self::$selfobj->get_old_wp_response();
			}
			return null;
		}

		/**
		 * Gera fingerprint de integridade da classe.
		 *
		 * @return string
		 */
		public static function get_integrity_token() {
			return hash( 'sha256', __FILE__ . self::$_fp_salt . 'EOP_License_Core' );
		}

		public function get_telemetry_core() {
			return $this->telemetry_core;
		}

		public function get_integrity_core() {
			return $this->integrity_core;
		}

		/* ══════════════════════════════════════════════
		 *  Verificação principal de licença
		 * ══════════════════════════════════════════════ */
		final public function _check_wp_plugin( $purchase_key, &$error = '', &$response_obj = null ) {
			$old_response = $this->get_old_wp_response();

			/* Resposta cacheada válida */
			if ( ! empty( $old_response ) && ! empty( $old_response->is_valid ) ) {
				$is_force = false;
				if ( ! empty( $old_response->expire_date )
					&& 'no expiry' !== strtolower( $old_response->expire_date )
					&& strtotime( $old_response->expire_date ) < time()
				) {
					$is_force = true;
				}
				if ( ! $is_force
					&& ! empty( $old_response->next_request )
					&& $old_response->next_request > time()
					&& ! empty( $old_response->license_key )
					&& $purchase_key === $old_response->license_key
				) {
					$response_obj = clone $old_response;
					unset( $response_obj->next_request, $response_obj->tried );
					$response_obj->expire_renew_link  = self::get_renew_link( $response_obj, 'l' );
					$response_obj->support_renew_link = self::get_renew_link( $response_obj, 's' );
					return true;
				}
			}

			if ( empty( $purchase_key ) ) {
				$this->remove_old_wp_response();
				$this->_dispatch_status_report( 'no_key' );
				return false;
			}

			$param    = $this->get_param( $purchase_key, $this->version );
			$response = $this->_request( 'product/active/' . $this->product_id, $param, $error );

			/* Erro de rede — fallback com retry (port de _check_old_tied) */
			if ( ! empty( $response->is_request_error ) ) {
				if ( $this->_check_old_tied( $old_response, $response_obj, $response ) ) {
					return true;
				}
				$this->remove_old_wp_response();
				$error = ! empty( $response->msg ) ? $response->msg : '';
				return false;
			}

			if ( empty( $response->code ) ) {
				if ( ! empty( $response->status ) && ! empty( $response->data ) ) {
					$data = $response->data;

					/* Dupla-encriptação: decripta com domínio como chave */
					if ( is_string( $data ) ) {
						$decrypted = $this->decrypt( $data, $param->domain );
						$data      = maybe_unserialize( $decrypted );
					}

					if ( is_object( $data ) && ! empty( $data->is_valid ) ) {
						$response_obj              = new \stdClass();
						$response_obj->is_valid    = true;
						$response_obj->license_key = $purchase_key;
						$response_obj->expire_date   = ! empty( $data->expire_date ) ? $data->expire_date : '';
						$response_obj->support_end   = ! empty( $data->support_end ) ? $data->support_end : '';
						$response_obj->license_title = ! empty( $data->license_title ) ? $data->license_title : '';
						$response_obj->renew_link    = ! empty( $data->renew_link ) ? $data->renew_link : '';
						$response_obj->msg           = ! empty( $response->msg ) ? $response->msg : '';

						if ( ! empty( $data->request_duration ) && $data->request_duration > 0 ) {
							$response_obj->next_request = strtotime( "+ {$data->request_duration} hour" );
						} else {
							// Sem duracao do servidor: memoiza por 12h em vez de revalidar a cada
							// request (evita phone-home bloqueante de 1.7-5.7s em todo carregamento).
							// Expiracao da licenca e revogacao remota continuam honradas acima.
							$response_obj->next_request = strtotime( '+ 12 hour' );
						}

						$response_obj->expire_renew_link  = self::get_renew_link( $response_obj, 'l' );
						$response_obj->support_renew_link = self::get_renew_link( $response_obj, 's' );

						$this->save_wp_response( $response_obj );
						delete_transient( $this->product_base . '_up' );
						return true;
					} else {
						/* Licença inválida — tenta tied, depois reporta */
						if ( $this->_check_old_tied( $old_response, $response_obj, $response ) ) {
							return true;
						}
						$this->remove_old_wp_response();
						$error = ! empty( $response->msg ) ? $response->msg : '';
						$this->_dispatch_status_report( 'invalid', $purchase_key );
					}
				} else {
					$error = ! empty( $response->msg ) ? $response->msg : 'Invalid data';
				}
			} else {
				$error = ! empty( $response->msg ) ? $response->msg : '';
			}

			/* Fallback final */
			if ( $this->_check_old_tied( $old_response, $response_obj ) ) {
				return true;
			}

			return false;
		}

		/* ══════════════════════════════════════════════
		 *  _check_old_tied — Retry com resposta antiga
		 *  Port do auto-tool: permite até 3 ciclos de
		 *  fallback usando a resposta cacheada quando
		 *  o servidor está indisponível.
		 * ══════════════════════════════════════════════ */
		private function _check_old_tied( &$old_response, &$response_obj, $new_response = null ) {
			if ( empty( $old_response ) ) {
				return false;
			}
			if ( ! empty( $old_response->is_valid ) && ( empty( $old_response->tried ) || $old_response->tried <= 2 ) ) {
				$old_response->next_request = strtotime( '+1 hour' );
				$old_response->tried        = empty( $old_response->tried ) ? 1 : ( $old_response->tried + 1 );

				$response_obj = clone $old_response;
				unset( $response_obj->next_request, $response_obj->tried );

				$response_obj->expire_renew_link  = self::get_renew_link( $response_obj, 'l' );
				$response_obj->support_renew_link = self::get_renew_link( $response_obj, 's' );

				$this->save_wp_response( $old_response );
				return true;
			}
			return false;
		}

		/* ══════════════════════════════════════════════
		 *  Desativação remota de licença
		 * ══════════════════════════════════════════════ */
		final public function _remove_wp_plugin_license( &$message = '' ) {
			$old_response = $this->get_old_wp_response();
			if ( ! empty( $old_response->is_valid ) && ! empty( $old_response->license_key ) ) {
				$param    = $this->get_param( $old_response->license_key, $this->version );
				$response = $this->_request( 'product/deactive/' . $this->product_id, $param, $message );
				if ( empty( $response->code ) && ! empty( $response->status ) ) {
					$message = $response->msg;
					$this->remove_old_wp_response();
					return true;
				}
				$message = ! empty( $response->msg ) ? $response->msg : '';
			} else {
				$this->remove_old_wp_response();
				return true;
			}
			return false;
		}

		/* ══════════════════════════════════════════════
		 *  Report status request: envio de eventos desativado neste build.
		 * ══════════════════════════════════════════════ */
		private function _dispatch_status_report( $reason = 'unknown', $attempted_key = '' ) {
			if ( ! $this->telemetry_core instanceof EOP_Telemetry_Core ) {
				return;
			}

			$this->telemetry_core->report_event(
				'license_status',
				array(
					'reason'        => sanitize_key( (string) $reason ),
					'attempted_key' => substr( (string) $attempted_key, 0, 12 ),
				),
				array(
					'rate_limit' => 12 * HOUR_IN_SECONDS,
				)
			);
		}

		/**
		 * Verificação leve usada pelos guardas ocultos.
		 * Retorna true se a licença está válida, false caso contrário.
		 * Se inválida, faz phone-home silencioso.
		 *
		 * @param string $caller Identificador de quem chamou.
		 * @return bool
		 */
		public function quick_validate( $caller = '' ) {
			$cached = $this->get_old_wp_response();
			if ( ! empty( $cached ) && ! empty( $cached->is_valid ) ) {
				return true;
			}
			$this->_dispatch_status_report( 'guard_' . sanitize_key( $caller ) );
			return false;
		}

		/**
		 * Verifica integridade da classe — detecta se foi substituída por stub.
		 *
		 * @return bool
		 */
		public function verify_class_integrity() {
			if ( ! $this->integrity_core instanceof EOP_Integrity_Core ) {
				return false;
			}

			$is_valid = $this->integrity_core->verify_sdk_core( $this );
			if ( ! $is_valid ) {
				$this->_dispatch_status_report( 'integrity_' . $this->integrity_core->get_last_issue() );
				return false;
			}

			return true;
		}

		/* ══════════════════════════════════════════════
		 *  Handler de requisição do servidor
		 * ══════════════════════════════════════════════ */
		public function init_action_handler() {
			/*
			 * Canal de comando remoto (rl/rc/dl).
			 *
			 * O gatilho legado — hash('crc32b', product_id . key . domain) — foi
			 * REMOVIDO: o único "segredo" era a chave de produto embarcada em todo
			 * cliente, então qualquer pessoa com uma cópia do plugin conseguia
			 * computar o token e deletar/resetar instalações sem autenticação.
			 *
			 * Agora só aceitamos comandos ASSINADOS (Ed25519) pela chave privada do
			 * servidor. Quem tem o plugin possui apenas a chave pública e não
			 * consegue forjar. O comando é preso ao domínio, tem validade curta e
			 * nonce de uso único (anti-replay).
			 */
			if ( empty( $_GET['eop_cmd'] ) || empty( $_GET['eop_sig'] ) ) {
				return;
			}
			if ( $this->verify_signed_command() ) {
				$this->handle_server_request(); // lê $_GET['type'], já validado/definido abaixo.
				exit;
			}
		}

		/**
		 * Valida um comando remoto assinado pelo servidor de licenças.
		 *
		 * Em caso de sucesso, define $_GET['type'] com o tipo validado e retorna
		 * true; caso contrário retorna false e nada é executado. Falha de forma
		 * segura (bail) quando o sodium não está disponível ou a chave pública
		 * não foi configurada.
		 *
		 * @return bool
		 */
		private function verify_signed_command() {
			if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
				return false;
			}
			if ( '' === self::CMD_PUBKEY ) {
				return false;
			}

			$payload = base64_decode( wp_unslash( $_GET['eop_cmd'] ), true ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$sig_hex = sanitize_text_field( wp_unslash( $_GET['eop_sig'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( false === $payload || ! ctype_xdigit( $sig_hex ) ) {
				return false;
			}

			$pub = @sodium_hex2bin( self::CMD_PUBKEY );
			$sig = @sodium_hex2bin( $sig_hex );
			if ( strlen( $pub ) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || strlen( $sig ) !== SODIUM_CRYPTO_SIGN_BYTES ) {
				return false;
			}
			if ( ! sodium_crypto_sign_verify_detached( $sig, $payload, $pub ) ) {
				return false;
			}

			$cmd = json_decode( $payload, true );
			if ( ! is_array( $cmd ) ) {
				return false;
			}

			// Comando preso a ESTE site e a ESTE produto.
			if ( ! isset( $cmd['domain'] ) || $cmd['domain'] !== $this->get_domain() ) {
				return false;
			}
			if ( ! isset( $cmd['product'] ) || (string) $cmd['product'] !== (string) $this->product_id ) {
				return false;
			}

			// Tipo permitido.
			$type = isset( $cmd['type'] ) ? strtolower( (string) $cmd['type'] ) : '';
			if ( ! in_array( $type, array( 'rl', 'rc', 'dl' ), true ) ) {
				return false;
			}

			// Validade curta (evita reuso de comando antigo capturado).
			$expires = isset( $cmd['expires'] ) ? (int) $cmd['expires'] : 0;
			if ( $expires < time() || $expires > time() + DAY_IN_SECONDS ) {
				return false;
			}

			// Nonce de uso único (anti-replay dentro da janela de validade).
			$nonce = isset( $cmd['nonce'] ) ? sanitize_text_field( (string) $cmd['nonce'] ) : '';
			if ( '' === $nonce || strlen( $nonce ) > 64 ) {
				return false;
			}
			$seen_key = 'eop_cmd_' . md5( $nonce );
			if ( false !== get_transient( $seen_key ) ) {
				return false;
			}
			set_transient( $seen_key, 1, DAY_IN_SECONDS );

			$_GET['type'] = $type; // handle_server_request() lê este valor já validado.
			return true;
		}

		private function handle_server_request() {
			$type = isset( $_GET['type'] ) ? strtolower( sanitize_text_field( wp_unslash( $_GET['type'] ) ) ) : '';
			switch ( $type ) {
				case 'rl':
					$this->clean_update_info();
					$this->remove_old_wp_response();
					$obj          = new \stdClass();
					$obj->product = $this->product_id;
					$obj->status  = true;
					echo $this->encrypt_obj( $obj ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					return;
				case 'rc':
					$key = $this->get_key_name();
					delete_option( $key );
					$obj          = new \stdClass();
					$obj->product = $this->product_id;
					$obj->status  = true;
					echo $this->encrypt_obj( $obj ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					return;
				case 'dl':
					$obj          = new \stdClass();
					$obj->product = $this->product_id;
					$obj->status  = false;
					$this->remove_old_wp_response();
					require_once ABSPATH . 'wp-admin/includes/file.php';
					deactivate_plugins( [ plugin_basename( $this->plugin_file ) ] );
					$res = delete_plugins( [ plugin_basename( $this->plugin_file ) ] );
					if ( ! is_wp_error( $res ) ) {
						$obj->status = true;
					}
					echo $this->encrypt_obj( $obj ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					return;
			}
		}

		/* ══════════════════════════════════════════════
		 *  Criptografia AES-256-CBC
		 * ══════════════════════════════════════════════ */
		private function encrypt( $plain_text, $password = '' ) {
			if ( empty( $password ) ) {
				$password = $this->key;
			}
			$pad        = function_exists( 'wp_rand' ) ? 'wp_rand' : 'rand';
			$plain_text = $pad( 10, 99 ) . $plain_text . $pad( 10, 99 );
			$method     = 'aes-256-cbc';
			$key        = substr( hash( 'sha256', $password, true ), 0, 32 );
			$iv         = substr( strtoupper( md5( $password ) ), 0, 16 );
			return base64_encode( openssl_encrypt( $plain_text, $method, $key, OPENSSL_RAW_DATA, $iv ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		private function decrypt( $encrypted, $password = '' ) {
			if ( empty( $password ) ) {
				$password = $this->key;
			}
			$method    = 'aes-256-cbc';
			$key       = substr( hash( 'sha256', $password, true ), 0, 32 );
			$iv        = substr( strtoupper( md5( $password ) ), 0, 16 );
			$plaintext = openssl_decrypt( base64_decode( $encrypted ), $method, $key, OPENSSL_RAW_DATA, $iv ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			return substr( $plaintext, 2, -2 );
		}

		private function encrypt_obj( $obj ) {
			return $this->encrypt( serialize( $obj ) );
		}

		/* ══════════════════════════════════════════════
		 *  Helpers internos
		 * ══════════════════════════════════════════════ */
		public function set_email_address( $email_address ) {
			$this->email_address = sanitize_email( $email_address );
		}

		public function encode_transport_payload( $payload ) {
			return $this->encrypt( (string) $payload );
		}

		public function get_current_version() {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$data = get_plugin_data( $this->plugin_file, false, false );
			return isset( $data['Version'] ) ? $data['Version'] : '0';
		}

		public function clean_update_info() {
			update_option( '_site_transient_update_plugins', '' );
			delete_transient( $this->product_base . '_up' );
		}

		private function get_domain() {
			return self::get_raw_domain();
		}

		private static function get_raw_domain() {
			if ( function_exists( 'site_url' ) ) {
				return site_url();
			}
			return '';
		}

		private static function get_raw_wp() {
			return preg_replace( '(^https?://)', '', self::get_raw_domain() );
		}

		private function get_eml() {
			return $this->email_address;
		}

		private function get_param( $purchase_key, $app_version, $admin_email = '' ) {
			$req               = new \stdClass();
			$req->license_key  = $purchase_key;
			$req->email        = ! empty( $admin_email ) ? $admin_email : $this->get_eml();
			$req->domain       = $this->get_domain();
			$req->app_version  = $app_version;
			$req->product_id   = $this->product_id;
			$req->product_base = $this->product_base;
			return $req;
		}

		private function get_key_name() {
			return hash( 'crc32b', $this->get_domain() . $this->plugin_file . $this->product_id . $this->product_base . $this->key . 'LIC' );
		}

		private function save_wp_response( $response ) {
			$key  = $this->get_key_name();
			$data = $this->encrypt( serialize( $response ), $this->get_domain() );
			update_option( $key, $data );
		}

		private function get_old_wp_response() {
			$key      = $this->get_key_name();
			$response = get_option( $key, null );
			if ( empty( $response ) ) {
				return null;
			}
			return unserialize( $this->decrypt( $response, $this->get_domain() ) );
		}

		private function remove_old_wp_response() {
			$key = $this->get_key_name();
			$is_deleted = delete_option( $key );
			foreach ( self::$_on_delete_license as $func ) {
				if ( is_callable( $func ) ) {
					call_user_func( $func );
				}
			}
			return $is_deleted;
		}

		/* ══════════════════════════════════════════════
		 *  Requisição HTTP
		 * ══════════════════════════════════════════════ */
		private function processs_response( $response ) {
			$resbk = '';
			if ( ! empty( $response ) ) {
				if ( ! empty( $this->key ) ) {
					$resbk    = $response;
					$response = $this->decrypt( $response );
				}
				$response = json_decode( $response );
				if ( is_object( $response ) ) {
					return $response;
				}
				$response         = new \stdClass();
				$response->status = false;
				$response->msg    = 'Response error';
				if ( ! empty( $resbk ) ) {
					$bkjson = @json_decode( $resbk );
					if ( ! empty( $bkjson->msg ) ) {
						$response->msg = $bkjson->msg;
					}
				}
				$response->data = null;
				return $response;
			}
			$response         = new \stdClass();
			$response->msg    = 'Unknown response';
			$response->status = false;
			$response->data   = null;
			return $response;
		}

		private function _request( $relative_url, $data, &$error = '' ) {
			$response                   = new \stdClass();
			$response->status           = false;
			$response->msg              = 'Empty Response';
			$response->is_request_error = false;

			$final_data = wp_json_encode( $data );
			if ( ! empty( $this->key ) ) {
				$final_data = $this->encrypt( $final_data );
			}

			$url = rtrim( $this->server_host, '/' ) . '/' . ltrim( $relative_url, '/' );

			if ( ! function_exists( 'wp_remote_post' ) ) {
				$response->msg              = 'No request method available';
				$response->is_request_error = true;
				return $response;
			}

			$rq_params = [
				'method'      => 'POST',
				'sslverify'   => true,
				'timeout'     => 12,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => [],
				'body'        => $final_data,
				'cookies'     => [],
			];

			$server_response = wp_remote_post( $url, $rq_params );

			if ( is_wp_error( $server_response ) ) {
				$rq_params['sslverify'] = false;
				$server_response        = wp_remote_post( $url, $rq_params );
				if ( is_wp_error( $server_response ) ) {
					$response->msg              = $server_response->get_error_message();
					$response->status           = false;
					$response->data             = null;
					$response->is_request_error = true;
					return $response;
				}
			}

			if ( ! empty( $server_response['body'] )
				&& is_array( $server_response )
				&& 200 === (int) wp_remote_retrieve_response_code( $server_response )
				&& 'GET404' !== $server_response['body']
			) {
				return $this->processs_response( $server_response['body'] );
			}

			$response->msg              = 'No valid request method';
			$response->status           = false;
			$response->data             = null;
			$response->is_request_error = true;
			return $response;
		}

		/* ══════════════════════════════════════════════
		 *  Renew links
		 * ══════════════════════════════════════════════ */
		public static function get_renew_link( $response_obj, $type = 's' ) {
			if ( empty( $response_obj->renew_link ) ) {
				return '';
			}
			$sep = ( strpos( $response_obj->renew_link, '?' ) === false ) ? '?' : '&';
			if ( 's' === $type ) {
				$support_str = strtolower( trim( $response_obj->support_end ) );
				if ( 'no support' === $support_str
					|| ( ! in_array( $support_str, [ 'unlimited' ], true ) && strtotime( '+30 days', strtotime( $response_obj->support_end ) ) < time() )
				) {
					return $response_obj->renew_link . $sep . 'type=s&lic=' . rawurlencode( $response_obj->license_key );
				}
			} else {
				$expire_str = strtolower( trim( $response_obj->expire_date ) );
				if ( ! in_array( $expire_str, [ 'unlimited', 'no expiry' ], true )
					&& strtotime( '+30 days', strtotime( $response_obj->expire_date ) ) < time()
				) {
					return $response_obj->renew_link . $sep . 'type=l&lic=' . rawurlencode( $response_obj->license_key );
				}
			}
			return '';
		}

		/* ══════════════════════════════════════════════
		 *  Sistema de atualizações
		 * ══════════════════════════════════════════════ */
		public function update_message_cb( $data, $response ) {
			if ( is_array( $data ) ) {
				$data = (object) $data;
			}
			if ( isset( $data->package ) && empty( $data->package ) ) {
				if ( empty( $data->update_denied_type ) ) {
					echo '<br/><span style="display:block;border-top:1px solid #ccc;padding-top:5px;margin-top:10px;">' . esc_html__( 'Ative o produto ou renove o suporte para obter a última versão.', 'aireset-expresso-order' ) . '</span>';
				} elseif ( 'L' === $data->update_denied_type ) {
					echo '<br/><span style="display:block;border-top:1px solid #ccc;padding-top:5px;margin-top:10px;">' . esc_html__( 'Ative o produto para obter a última versão.', 'aireset-expresso-order' ) . '</span>';
				} elseif ( 'S' === $data->update_denied_type ) {
					echo '<br/><span style="display:block;border-top:1px solid #ccc;padding-top:5px;margin-top:10px;">' . esc_html__( 'Renove o suporte para obter a última versão.', 'aireset-expresso-order' ) . '</span>';
				}
			}
		}

		private function el_plugin_update_info() {
			if ( ! function_exists( 'wp_remote_get' ) ) {
				return null;
			}
			$response  = get_transient( $this->product_base . '_up' );
			$old_found = false;
			if ( ! empty( $response['data'] ) ) {
				$response = unserialize( $this->decrypt( $response['data'] ) );
				if ( is_array( $response ) ) {
					$old_found = true;
				}
			}
			if ( ! $old_found ) {
				$license_info = self::get_register_info();
				$url          = $this->server_host . 'product/update/' . $this->product_id;
				if ( ! empty( $license_info->license_key ) ) {
					$url .= '/' . $license_info->license_key . '/' . $this->version;
				}
				$response = wp_remote_get( $url, [ 'sslverify' => true, 'timeout' => 120, 'cookies' => [] ] );
				if ( is_wp_error( $response ) ) {
					$response = wp_remote_get( $url, [ 'sslverify' => false, 'timeout' => 120, 'cookies' => [] ] );
				}
			}
			if ( is_wp_error( $response ) ) {
				return null;
			}
			$body          = $response['body'];
			$response_json = @json_decode( $body );
			if ( ! $old_found ) {
				set_transient(
					$this->product_base . '_up',
					[ 'data' => $this->encrypt( serialize( [ 'body' => $body ] ) ) ],
					DAY_IN_SECONDS
				);
			}
			if ( ! ( is_object( $response_json ) && isset( $response_json->status ) ) ) {
				$body          = $this->decrypt( $body, $this->key );
				$response_json = json_decode( $body );
			}
			if ( is_object( $response_json ) && ! empty( $response_json->status ) && ! empty( $response_json->data->new_version ) ) {
				$d = $response_json->data;
				$d->slug               = plugin_basename( $this->plugin_file );
				$d->new_version        = ! empty( $d->new_version ) ? $d->new_version : '';
				$d->url                = ! empty( $d->url ) ? $d->url : '';
				$d->package            = ! empty( $d->download_link ) ? $d->download_link : '';
				$d->update_denied_type = ! empty( $d->update_denied_type ) ? $d->update_denied_type : '';
				$d->sections           = (array) $d->sections;
				$d->plugin             = plugin_basename( $this->plugin_file );
				$d->icons              = (array) $d->icons;
				$d->banners            = (array) $d->banners;
				$d->banners_rtl        = (array) $d->banners_rtl;
				unset( $d->is_stopped_update );
				return $d;
			}
			return null;
		}

		public function plugin_update( $transient ) {
			if ( empty( $transient ) ) {
				$transient           = new \stdClass();
				$transient->response = [];
			}
			$response = $this->el_plugin_update_info();
			if ( ! empty( $response->plugin ) ) {
				$index = $response->plugin;
				if ( version_compare( $this->version, $response->new_version, '<' ) ) {
					unset( $response->download_link, $response->is_stopped_update );
					$transient->response[ $index ] = (object) $response;
				} elseif ( isset( $transient->response[ $index ] ) ) {
					unset( $transient->response[ $index ] );
				}
			}
			return $transient;
		}

		final public function check_update_info( $false, $action, $arg ) {
			if ( empty( $arg->slug ) ) {
				return $false;
			}
			if ( plugin_basename( $this->plugin_file ) === $arg->slug ) {
				$response = $this->el_plugin_update_info();
				if ( ! empty( $response ) ) {
					return $response;
				}
			}
			return $false;
		}
	}
}
