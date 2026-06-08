<?php
defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( "Execute via WP-CLI: wp eval-file scripts/smoke-admin-rest.php\n" );
}

$admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);

if ( empty( $admins ) ) {
	WP_CLI::error( 'Nenhum administrador encontrado para executar o smoke test REST.' );
}

wp_set_current_user( (int) $admins[0] );
do_action( 'rest_api_init' );

$checks = array(
	'bootstrap'                     => '/aireset-expresso-order/v1/admin/bootstrap',
	'settings-store'                => '/aireset-expresso-order/v1/admin/settings/store',
	'settings-general'              => '/aireset-expresso-order/v1/admin/settings/general',
	'settings-proposal'             => '/aireset-expresso-order/v1/admin/settings/proposal',
	'settings-confirmation-upload'  => '/aireset-expresso-order/v1/admin/settings/confirmation-upload-products',
	'preview-new-order'             => '/aireset-expresso-order/v1/admin/previews/new-order',
	'preview-proposal'              => '/aireset-expresso-order/v1/admin/previews/proposal',
	'preview-confirmation-contract' => '/aireset-expresso-order/v1/admin/previews/confirmation-contract',
	'preview-upload-products'       => '/aireset-expresso-order/v1/admin/previews/confirmation-upload-products',
	'view-orders'                   => '/aireset-expresso-order/v1/admin/views/orders',
	'pdf-tab-display'               => '/aireset-expresso-order/v1/admin/pdf-tabs/display',
	'product-categories'            => '/aireset-expresso-order/v1/admin/product-categories',
	'orders-list'                   => '/aireset-expresso-order/v1/admin/orders',
);

$results = array();
$failed  = array();
$settings = class_exists( 'EOP_Settings' ) ? EOP_Settings::get_all() : array();

foreach ( $checks as $label => $route ) {
	$request  = new WP_REST_Request( 'GET', $route );
	$response = rest_do_request( $request );
	$status   = (int) $response->get_status();
	$data     = $response->get_data();
	$ok       = $status >= 200 && $status < 300;

	$results[ $label ] = array(
		'route'     => $route,
		'status'    => $status,
		'ok'        => $ok,
		'data_type' => gettype( $data ),
		'keys'      => is_array( $data ) ? array_slice( array_keys( $data ), 0, 8 ) : array(),
	);

	if ( ! $ok ) {
		$failed[] = $label;
	}
}

$assertions = array();

$assertions['settings-store-source'] = isset( $results['settings-store'] ) && 'pdf' === ( rest_do_request( new WP_REST_Request( 'GET', '/aireset-expresso-order/v1/admin/settings/store' ) )->get_data()['source'] ?? '' );
$assertions['preview-new-order-public-source'] = isset( $results['preview-new-order'] ) && 'public-shortcode' === ( rest_do_request( new WP_REST_Request( 'GET', '/aireset-expresso-order/v1/admin/previews/new-order' ) )->get_data()['source'] ?? '' );

$upload_preview_response = rest_do_request( new WP_REST_Request( 'GET', '/aireset-expresso-order/v1/admin/previews/confirmation-upload-products' ) );
$upload_preview_data     = $upload_preview_response->get_data();
$upload_preview_html     = html_entity_decode( (string) ( is_array( $upload_preview_data ) ? ( $upload_preview_data['html'] ?? '' ) : '' ), ENT_QUOTES, get_bloginfo( 'charset' ) );
$upload_button_label     = (string) ( $settings['post_confirmation_upload_button_label'] ?? '' );
$products_button_label   = (string) ( $settings['post_confirmation_products_button_label'] ?? '' );

$assertions['preview-upload-button-label'] = '' !== $upload_button_label && false !== strpos( $upload_preview_html, $upload_button_label );
$assertions['preview-products-button-label'] = '' !== $products_button_label && false !== strpos( $upload_preview_html, $products_button_label );
$assertions['preview-products-stage-card'] = false !== strpos( $upload_preview_html, 'eop-post-flow--stage-products' );

$lazy_view_data = rest_do_request( new WP_REST_Request( 'GET', '/aireset-expresso-order/v1/admin/views/orders' ) )->get_data();
$pdf_tab_data   = rest_do_request( new WP_REST_Request( 'GET', '/aireset-expresso-order/v1/admin/pdf-tabs/display' ) )->get_data();

$assertions['view-orders-html'] = is_array( $lazy_view_data ) && false !== strpos( (string) ( $lazy_view_data['html'] ?? '' ), 'data-eop-view="orders"' );
$assertions['pdf-tab-html'] = is_array( $pdf_tab_data ) && false !== strpos( (string) ( $pdf_tab_data['html'] ?? '' ), 'eop-pdf-admin' );

$results['_assertions'] = $assertions;

foreach ( $assertions as $assertion => $passed ) {
	if ( ! $passed ) {
		$failed[] = $assertion;
	}
}

WP_CLI::line( wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

if ( ! empty( $failed ) ) {
	WP_CLI::error( 'Smoke REST falhou: ' . implode( ', ', $failed ) );
}

WP_CLI::success( 'Smoke REST administrativo concluido sem falhas.' );
