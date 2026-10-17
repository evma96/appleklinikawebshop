<?php
// Fixture provider boundaries only: not an installable plugin or production configuration.
if (PHP_SAPI !== 'cli' || !defined('AK_PRODUCTION_SIMULATION') || !AK_PRODUCTION_SIMULATION || DB_NAME !== 'ak_production_fixture') { exit(1); }
class WC_Gateway_Barion { static function log($message){} function get_barion_payment_id($o){return 'fixture-'.$o->get_id();} function get_successfull_status(){return 'processing';} function payment_complete($o,$id){$o->payment_complete($id);} }
class WC_Szamlazz_Pro{static function is_pro_enabled(){return get_option('_wc_szamlazz_pro_enabled',false);}}
class WC_Szamlazz_Xml_Generator{}
add_filter('option_active_plugins',static fn($plugins)=>array_values(array_unique(array_merge($plugins,['integration-for-szamlazzhu-woocommerce/index.php','gls-shipping-for-woocommerce/gls-shipping-for-woocommerce.php']))));
function WC_Szamlazz(){return new class{function generate_download_link($order,$type,$path){return wp_upload_dir()['basedir'].'/wc_szamlazz'.$order->get_meta('_wc_szamlazz_invoice_pdf',true); } function get_option($key,$default=''){return apply_filters('wc_szamlazz_get_option',['agent_key'=>'fixture-live-agent','auto_invoice_custom'=>'yes'][$key]??$default,$key);}};}
class WC_Szamlazz_Automations {
 static function on_payment_complete($id){
  \Appleklinika\BackOffice\Infrastructure\ProductionTransportGuard::invoice('<xmlszamla><beallitasok><szamlaagentkulcs>fixture-live-agent</szamlaagentkulcs></beallitasok></xmlszamla>');
  update_option('fixture_invoice_calls',(int)get_option('fixture_invoice_calls',0)+1);
  $o=wc_get_order($id);$u=wp_upload_dir();$dir=$u['basedir'].'/wc_szamlazz';wp_mkdir_p($dir);$file='/fixture-'.$id.'.pdf';
  file_put_contents($dir.$file,"%PDF-1.4\n% OFFLINE FIXTURE ONLY\n%%EOF\n");
  $o->update_meta_data('_wc_szamlazz_invoice','FIXTURE-'.$id);$o->update_meta_data('_wc_szamlazz_invoice_pdf',$file);$o->save_meta_data();
  do_action('wc_szamlazz_document_created',['order_id'=>$id,'document_type'=>'invoice']);
 }
}
define('GLS_SHIPPING_ABSPATH','/fixture-never-load-vendor/');
define('GLS_LABELS_DIR', WP_CONTENT_DIR.'/uploads/gls-labels');
class GLS_Shipping_For_Woo { static function get_instance(){return new self();} function setup_labels_directory(){wp_mkdir_p(GLS_LABELS_DIR);} }
class GLS_Shipping_Account_Helper{
 static function get_active_account(){return ['mode'=>'production','country'=>'HU','client_id'=>'fixture-id','username'=>'fixture-user','password'=>'fixture-pass'];}
 static function get_account_setting($key){return self::get_active_account()[$key]??'fixture';}
 static function get_order_tracking_links($order){return array_map(static fn($code)=>['code'=>(string)$code,'url'=>'https://gls-group.eu/HU/en/parcel-tracking/?match='.$code],(array)$order->get_meta('_gls_tracking_codes',true));}
}
class GLS_Shipping_Sender_Address_Helper{static function get_default_sender_address(){return [];}static function format_for_api_pickup($address,$phone){return array_fill_keys(['Name','Street','City','ZipCode','CountryIsoCode','ContactPhone'],'fixture');}}
class GLS_Shipping_API_Data{function __construct($id){}function generate_post_fields(...$args){return [];}}
require '/source/wordpress/wp-content/plugins/gls-shipping-for-woocommerce/includes/api/class-gls-shipping-api-service.php';
require '/source/wordpress/wp-content/plugins/gls-shipping-for-woocommerce/includes/admin/class-gls-shipping-order.php';
add_filter('pre_http_request',static function($previous,$args,$url){
 if(strtolower($url)!=='https://api.mygls.hu/parcelservice.svc/json/printlabels')return $previous;
 update_option('fixture_gls_calls',(int)get_option('fixture_gls_calls',0)+1);
 $pdf="%PDF-1.4\n% OFFLINE GLS FIXTURE\n%%EOF\n";
 return ['response'=>['code'=>200],'body'=>json_encode(['Labels'=>array_values(unpack('C*',$pdf)),'PrintLabelsInfoList'=>[['ParcelNumber'=>12345678,'ParcelId'=>42]]])];
},10,3);
function fluentMailGetSettings($defaults=[],$cached=true){return ['misc'=>['default_connection'=>'fixture'],'connections'=>['fixture'=>['provider_settings'=>[
 'provider'=>'smtp','host'=>'mail.example.invalid','port'=>'587','username'=>'sender@example.invalid','password'=>'fixture-pass','auth'=>'yes','encryption'=>'tls',
 'sender_email'=>'sender@example.invalid','sender_name'=>'Apple Klinika','force_from_email'=>'yes','force_from_name'=>'yes']]]];}
add_filter('pre_wp_mail',static function($previous,$mail){
 if($previous===false || !class_exists('Appleklinika\\BackOffice\\Infrastructure\\ProductionReadiness') || !\Appleklinika\BackOffice\Infrastructure\ProductionReadiness::allowed()) return false;
 $all=get_option('fixture_mail',[]);$all[]=$mail;update_option('fixture_mail',$all);return true;
},PHP_INT_MAX,2);
add_filter('pre_http_request',static function($previous){ if($previous!==false)return $previous; $GLOBALS['fixture_network_attempts']=($GLOBALS['fixture_network_attempts']??0)+1;return new WP_Error('offline','No network in simulation');},PHP_INT_MAX);
