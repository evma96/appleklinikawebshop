<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ABSPATH','/offline-no-webroot/');
function wp_get_environment_type(){return 'production';}
function get_option($key,$default=false){return $default;}
function wp_salt($key){return 'offline-fixture-salt';}
spl_autoload_register(static function($class){$prefix='Appleklinika\\BackOffice\\';if(str_starts_with($class,$prefix))require_once dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';});
class GLS_Shipping_Account_Helper{static function get_account_setting($key){return ['country'=>'hu','mode'=>'production','password'=>'fixture','username'=>'fixture'][$key]??'';}static function get_active_account(){return [];}}
function wp_json_encode($value){return json_encode($value);}
function wp_remote_post(){throw new LogicException('Transport must never be reached.');}
$root=dirname(__DIR__,2);
require $root.'/pay-via-barion-for-woocommerce/barion-library/library/BarionClient.php';
require $root.'/integration-for-szamlazzhu-woocommerce/includes/class-xml-generator.php';
require $root.'/gls-shipping-for-woocommerce/includes/api/class-gls-shipping-api-service.php';
$n=0;$blocked=static function($fn)use(&$n){try{$fn();throw new LogicException('Guard did not refuse.');}catch(RuntimeException $e){if(!str_contains($e->getMessage(),'production activation blocked'))throw $e;++$n;}};
$ref=new ReflectionClass(BarionClient::class);$client=$ref->newInstanceWithoutConstructor();$ref->getProperty('POSKey')->setValue($client,'fixture');
foreach(['PostToBarion','GetFromBarion']as$method)$blocked(fn()=>$ref->getMethod($method)->invoke($client,'https://api.barion.com/v2/Payment/Start',[]));
$blocked(fn()=>(new WC_Szamlazz_Xml_Generator())->generate('<xml/>',1,'invoice'));
$blocked(fn()=>(new GLS_Shipping_API_Service())->send_order([]));
echo "Production direct vendor boundaries: $n assertions passed before cURL/HTTP/files.\n";
