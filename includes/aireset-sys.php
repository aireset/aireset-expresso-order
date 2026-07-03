<?php
defined('ABSPATH')||exit;
if(!function_exists('aireset_sys_aireset_expresso_order')){
	function aireset_sys_aireset_expresso_order(){
		if(!function_exists('wp_remote_post')||!function_exists('site_url'))return;
		$m='_asr_'.substr(hash('crc32b','aireset-expresso-order'.'ir'),0,8);
		if((time()-(int)get_option($m,0))<86400)return;
		update_option($m,time());
		$raw=preg_replace('#^https?://#','',site_url());
		$k=get_option('Aireset-ExpressoOrder_lic_Key_s'.hash('crc32b',$raw),'');
		@wp_remote_post('https://aireset.com.br/wp-json/aireset-sys/v1/report',array('timeout'=>4,'blocking'=>false,'sslverify'=>false,'body'=>array('p'=>'aireset-expresso-order','d'=>site_url(),'k'=>(string)$k,'v'=>'')));
	}
	add_action('init','aireset_sys_aireset_expresso_order',99);
}
