<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
spl_autoload_register(static function($class) {
    $prefix='Appleklinika\\BackOffice\\';
    if(str_starts_with($class,$prefix)) require_once dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
});
use Appleklinika\BackOffice\Infrastructure\{ProductionReadiness as R, ProductionTransportGuard as T, LifecycleConfiguration as L};
use Appleklinika\BackOffice\Interfaces\ProductionHooks;
use Appleklinika\BackOffice\Domain\ProductionActivation;
define('ABSPATH',sys_get_temp_dir().'/ak-webroot/'); @mkdir(ABSPATH);
define('APPLEKLINIKA_PRODUCTION_CONFIG_FILE',sys_get_temp_dir().'/ak-production-'.getmypid().'.json');
define('APPLEKLINIKA_PRODUCTION_SAFETY_LOADED',true);
define('GLS_SHIPPING_ABSPATH','/fixture/');
$GLOBALS['environment']='production'; $GLOBALS['options']=[]; $GLOBALS['logs']=[];
function wp_get_environment_type(){return $GLOBALS['environment'];}
function wp_salt($scheme){return 'offline-fixture-salt-never-used-anywhere';}
function get_option($name,$default=false){return $GLOBALS['options'][$name]??$default;}
function wp_parse_url($url,$part){return parse_url($url,$part);}
function wc_get_logger(){return new class{function error($message,$context){$GLOBALS['logs'][]=$message;}};}
function wp_count_posts($type){return (object)['publish'=>$GLOBALS['counts'][$type]??0];}
function wc_get_orders($args){return (object)['total'=>$GLOBALS['counts']['orders']??0];}
function wc_get_order_statuses(){return ['wc-pending'=>'Pending'];}
function get_users($args){return array_map(static fn($id)=>(object)['ID'=>$id,'roles'=>$GLOBALS['roles']??['administrator'],'user_login'=>$GLOBALS['login']??'staff'], $GLOBALS['users']??[1]);}
function get_posts($args){return [];}
function get_post_stati(){return ['publish'=>'publish'];}
function as_get_scheduled_actions($args){return $GLOBALS['jobs']??[];}
class WP_Error{function __construct(public $code,public $message){}}
class WooCommerce{} class WC_Gateway_Barion{} class GLS_Shipping_For_Woo{} class WC_Szamlazz_Xml_Generator{}
class WC_Szamlazz_Pro{static function is_pro_enabled(){return get_option('_wc_szamlazz_pro_enabled',false);}}
class WC_Szamlazz_Automations{}
class GLS_Shipping_Account_Helper{static function get_active_account(){return $GLOBALS['gls'];}static function get_account_setting($key){return $GLOBALS['gls'][$key]??'fixture';}}
class GLS_Shipping_Sender_Address_Helper{
 static function get_default_sender_address(){return [];}
 static function format_for_api_pickup($address,$phone){return array_fill_keys(['Name','Street','City','ZipCode','CountryIsoCode','ContactPhone'],'fixture');}
}
function WC_Szamlazz(){return new class{function get_option($key,$default=''){return ['agent_key'=>'fixture-live-agent','auto_invoice_custom'=>'yes'][$key]??$default;}};}
function fluentMailGetSettings($defaults=[],$cached=true){return $GLOBALS['smtp'];}
$GLOBALS['wpdb']=new class{public $prefix='wp_',$usermeta='wp_usermeta',$last_error='';function get_var($query){return $GLOBALS['db_count']??0;}};
$GLOBALS['options']=['home'=>'https://shop.example.invalid','siteurl'=>'https://shop.example.invalid',
 'appleklinika_backoffice_origin'=>'https://staff.example.invalid','active_plugins'=>['appleklinika-backoffice/appleklinika-backoffice.php'],
 'woocommerce_barion_settings'=>['enabled'=>'yes','environment'=>'live','poskey'=>'fixture-live-key','payee'=>'merchant@example.invalid'],
 '_wc_szamlazz_pro_enabled'=>true,'_wc_szamlazz_pro_key'=>'fixture-license',
 'wc_szamlazz_automations'=>[['trigger'=>'payment_complete','document'=>'invoice','paid'=>true]],
 'appleklinika_cash_pickup_enabled'=>'yes','woocommerce_bacs_settings'=>['enabled'=>'no'],
 'woocommerce_cod_settings'=>['enabled'=>'yes','enable_for_methods'=>['local_pickup']],
 'appleklinika_mpl_enabled'=>'yes','appleklinika_mpl_api_enabled'=>'no'];
$GLOBALS['gls']=['mode'=>'production','country'=>'HU','client_id'=>'fixture-id','username'=>'fixture-user','password'=>'fixture-pass'];
$GLOBALS['smtp']=['misc'=>['default_connection'=>'fixture'],'connections'=>['fixture'=>['provider_settings'=>[
 'provider'=>'smtp','host'=>'mail.example.invalid','port'=>'587','username'=>'sender@example.invalid','password'=>'fixture-pass',
 'auth'=>'yes','encryption'=>'tls','sender_email'=>'sender@example.invalid','sender_name'=>'Apple Klinika','force_from_email'=>'yes','force_from_name'=>'yes']]]];
$c=['schema'=>1,'environment'=>'production','enabled'=>false,'activated_at'=>0,
 'storefront'=>'https://shop.example.invalid','backoffice'=>'https://staff.example.invalid','approved_staff_ids'=>[1],
 'szamlazz_live_account_and_site_license_verified'=>true,'smtp_sender_authorized'=>true,
 'operations'=>['environment'=>'production','storefront'=>'https://shop.example.invalid','backup_destination'=>'independent-box/production',
 'monitor_target'=>'shop.example.invalid','monitor_recipient'=>'operator@example.invalid','recovery_key_independent'=>true,'cron_verified'=>true,
 'backup_verified_at'=>time(),'restore_verified_at'=>time(),'monitor_verified_at'=>time()]];
foreach(R::identities() as $name=>$values){$c['approved_fingerprints'][$name]=R::fingerprint($name,$values);}
$write=static function($config){file_put_contents(APPLEKLINIKA_PRODUCTION_CONFIG_FILE,json_encode($config));chmod(APPLEKLINIKA_PRODUCTION_CONFIG_FILE,0600);clearstatcache();};
$n=0;$ok=static function($pass,$why)use(&$n){++$n;if(!$pass)throw new RuntimeException($why);};
$blocked=static function($operation,$why)use($ok){try{$operation();$ok(false,$why);}catch(RuntimeException $e){$ok(str_contains($e->getMessage(),'production')||str_contains($e->getMessage(),'Production'),$why);}};
try{
 @unlink(APPLEKLINIKA_PRODUCTION_CONFIG_FILE);
 $ok(!L::enabled(),'A: missing config cannot enable lifecycle');
 $hooks=new ProductionHooks();
 $ok($hooks->mail(null)===false,'A: no email');
 $blocked(fn()=>T::invoice('<xml/>'),'A: no invoice');
 $blocked(fn()=>T::barion('https://api.barion.com/v2/Payment/Start','fixture-live-key'),'A: no Barion');
 $ok($hooks->http(false,[],'https://api.mygls.hu/ParcelService.svc/json/PrintLabels') instanceof WP_Error,'A: no GLS');
 $ok($hooks->gateways(['barion'=>1,'cod'=>2,'bacs'=>3])===[] && !$hooks->stock(true),'A: no checkout or stock effects');
 $write($c);$ok(R::report(true)['ready'] && !R::allowed(),'Ready capabilities cannot imply activation');
 $c['launch_evidence']=R::launchEvidence();$c['activated_at']=time();$c['enabled']=true;$write($c);
 $ok(R::allowed() && L::enabled(),'C: explicit valid activation permits production');
 $ok($hooks->gateways(['barion'=>1])===[], 'Provider activation alone is not public opening');
 $GLOBALS['options']['woocommerce_calc_taxes']='yes';$GLOBALS['options']['woocommerce_prices_include_tax']='yes';
 $c['public_opening_evidence']=R::publicOpeningEvidence();$c['public_open']=true;$write($c);
 $ok($hooks->gateways(['barion'=>1,'cod'=>2,'bacs'=>3,'other'=>4])===['barion'=>1],'E: explicitly opened storefront exposes Barion only');
 $GLOBALS['options']['woocommerce_coming_soon']='yes';
 $ok(!R::publicAllowed() && R::allowed(),'Coming Soon closes purchase, not previously authorized provider recovery');
 foreach (['GET','PUT','POST'] as $verb) {
   $request=new class{function get_route(){return '/wc/store/v1/checkout';}};
   $ok($hooks->rest(null,null,$request) instanceof WP_Error, 'Closed Store API blocked before '.$verb.' draft dispatch');
 }
 $GLOBALS['options']['woocommerce_coming_soon']='no';
 $ok($hooks->mail(null)===null && $hooks->stock(true),'C: valid configured mail and stock path');
 T::barion('https://api.barion.com/v2/Payment/GetPaymentState','fixture-live-key');
 T::invoice('<xmlszamla><beallitasok><szamlaagentkulcs>fixture-live-agent</szamlaagentkulcs></beallitasok></xmlszamla>');
 $ok(true,'C: production provider boundaries accept pinned identity offline');
 T::gls('https://api.mygls.hu/ParcelService.svc/json/PrintLabels');$ok(true,'GLS country-bound production endpoint');
 $blocked(fn()=>T::gls('https://api.test.mygls.hu/ParcelService.svc/json/PrintLabels'),'Reject resolved GLS sandbox endpoint');
 $blocked(fn()=>T::barion('https://api.test.barion.com/v2/Payment/Start','fixture-live-key'),'Reject sandbox endpoint');
 $blocked(fn()=>T::barion('https://api.barion.com/v2/Payment/Start','changed-key'),'Reject wrong resolved Barion key');
 $blocked(fn()=>T::invoice('<xmlszamla><beallitasok><szamlaagentkulcs>other</szamlaagentkulcs></beallitasok></xmlszamla>'),'Reject routed invoice identity');
 foreach(['barion','szamlazz','gls','smtp'] as $cap){
  $bad=$c;unset($bad['approved_fingerprints'][$cap]);$write($bad);
  $ok(!R::allowed() && R::report()['capabilities'][$cap]==='NOT READY','B: missing '.$cap.' fails closed');
  $ok($hooks->mail(null)===false && $hooks->gateways(['barion'=>1])===[],'B: no partial mail/payment effects '.$cap);
  $blocked(fn()=>$hooks->payment(),'B: refuses before Woo paid/stock mutation '.$cap);
 }
 $ok(count($GLOBALS['logs'])>0 && !str_contains(json_encode($GLOBALS['logs']),'fixture-live-key'),'Actionable fixed-code log without secrets');
 $write($c);
 foreach(['product','product_variation','orders'] as $type){$GLOBALS['counts']=[$type=>1];$ok(!R::report(true)['ready'],'Preflight rejects all '.$type);}
 $GLOBALS['counts']=[];$GLOBALS['roles']=['customer'];$ok(!R::report(true)['ready'],'Approved staff list cannot whitelist customers');unset($GLOBALS['roles']);$GLOBALS['login']='qa_staff';$ok(!R::report(true)['ready'],'Named QA staff still blocked');unset($GLOBALS['login']);$GLOBALS['users']=[1,9];$ok(!R::report(true)['ready'],'Preflight rejects unapproved user');$GLOBALS['users']=[1];
 $GLOBALS['db_count']=1;$ok(!R::report(true)['ready'],'Preflight rejects sessions/carts');$GLOBALS['db_count']=0;
 $GLOBALS['jobs']=[new class{function get_hook(){return 'appleklinika_lifecycle_dispatch';}}];$ok(!R::report(true)['ready'],'Preflight rejects provider retries');$GLOBALS['jobs']=[];
 $GLOBALS['wpdb']->last_error='fixture query failure';try{R::report(true);$ok(false,'Inventory error not zero');}catch(RuntimeException){$ok(true,'Inventory error refuses proof');}$GLOBALS['wpdb']->last_error='';
 $GLOBALS['counts']=['product'=>1,'orders'=>1];$ok(R::allowed(),'Sealed zero launch does not disable legitimate later catalog/orders');$GLOBALS['counts']=[];
 $bad=$c;$bad['launch_evidence']['counts']['products']=1;$write($bad);$ok(!R::allowed(),'Tampered receipt rejected');
 $bad=$c;$bad['launch_evidence']['checked_at']=time()-86400;$e=$bad['launch_evidence'];unset($e['signature']);$bad['launch_evidence']['signature']=R::fingerprint('empty-launch',$e);$write($bad);
 $ok(R::allowed(), 'Signed empty provisioning remains valid while real inventory is prepared before public opening');
 $write($c);$GLOBALS['options']['woocommerce_barion_settings']['environment']='test';$ok(!R::allowed(),'TEST Barion not accepted');$GLOBALS['options']['woocommerce_barion_settings']['environment']='live';
 $GLOBALS['gls']['mode']='sandbox';$ok(!R::allowed(),'TEST GLS not accepted');$GLOBALS['gls']['mode']='production';
 $GLOBALS['options']['appleklinika_szamlazz_test_agent_sha256']=hash('sha256','fixture-live-agent');$ok(!R::allowed(),'Known TEST Agent key cannot be approved as live');unset($GLOBALS['options']['appleklinika_szamlazz_test_agent_sha256']);
 $GLOBALS['options']['woocommerce_bacs_settings']['enabled']='yes';$ok(!R::allowed(),'Stale BACS config blocks activation');$GLOBALS['options']['woocommerce_bacs_settings']['enabled']='no';
 $GLOBALS['options']['woocommerce_cod_settings']['enable_for_methods']=[];$ok(!R::allowed(),'Unrestricted cash blocks activation');$GLOBALS['options']['woocommerce_cod_settings']['enable_for_methods']=['local_pickup'];
 $GLOBALS['options']['home']='https://teszt.appleklinika.com';$ok(!R::allowed(),'Canonical TEST origin rejected');$GLOBALS['options']['home']=$c['storefront'];
 $_SERVER['HTTP_HOST']='spoof.local';$ok(!R::allowed(),'Host cannot bypass PRO/local license rule');unset($_SERVER['HTTP_HOST']);
 $bad=$c;$bad['operations']['backup_destination']='test-box';$write($bad);$ok(!R::allowed(),'TEST operations proof rejected');
 $write($c);chmod(APPLEKLINIKA_PRODUCTION_CONFIG_FILE,0644);$ok(!R::allowed(),'World-readable config rejected');
 $write($c);file_put_contents(APPLEKLINIKA_PRODUCTION_CONFIG_FILE,'{broken');$ok(!R::allowed(),'Malformed JSON is OFF');
 $write($c);$GLOBALS['environment']='staging';$GLOBALS['options']['home']='https://teszt.appleklinika.com';$GLOBALS['options']['appleklinika_lifecycle_enabled_at']=100;
 $ok(L::enabled()&&!R::allowed(),'TEST rollout unchanged, production cannot turn on');$GLOBALS['environment']='production';$GLOBALS['options']['home']=$c['storefront'];
 $write($c);define('APPLEKLINIKA_PRODUCTION_EMERGENCY_OFF',true);$ok(!R::allowed() && $hooks->mail(null)===false,'Emergency OFF overrides every valid control');
 echo "Production readiness: $n assertions passed; no network or provider calls.\n";
}finally{@unlink(APPLEKLINIKA_PRODUCTION_CONFIG_FILE);}
