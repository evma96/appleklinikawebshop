<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
spl_autoload_register(static function($c) { $prefix='Appleklinika\\BackOffice\\'; if(str_starts_with($c,$prefix))require dirname(__DIR__).'/src/'.str_replace('\\','/',substr($c,strlen($prefix))).'.php'; });
use Appleklinika\BackOffice\Application\CreateGlsLabel;
use Appleklinika\BackOffice\Application\Port\{GlsLabelStore,OrderMutex};
final class Labels implements GlsLabelStore {
 public array $order=['state'=>'ready_for_shipping','mode'=>'gls','paid'=>true,'blocked'=>null,'recorded'=>false,'attempt'=>''];
 public array $journal=[];
 public function snapshot(int $id):array{return $this->order;}
 public function recordAttempt(int $id,string $state):void{$this->order['attempt']=$state;$this->journal[]=$state;}
}
final class LabelMutex implements OrderMutex {
 public bool $held=false;
 public function synchronized(int $id,callable $operation):mixed{if($this->held)throw new RuntimeException('busy');$this->held=true;try{return $operation();}finally{$this->held=false;}}
}
$n=0;$ok=static function($yes,$why)use(&$n){++$n;if(!$yes)throw new RuntimeException($why);};
$new=static function(){ $s=new Labels();$m=new LabelMutex();return [$s,$m,new CreateGlsLabel($s,$m)];};
[$s,$m,$app]=$new();$calls=0;$provider=static function()use(&$calls,&$m){if(!$m->held)throw new RuntimeException('unlocked');++$calls;return ['success'=>true];};
for($i=0;$i<5;$i++)$app->execute(1,$provider);
$ok($calls===1&&$s->journal===['attempting','ready'],'Successful repeat uses durable journal and lock.');
foreach(['new','preparation','packing','handed_to_gls','delivered']as$state){[$s,$m,$app]=$new();$s->order['state']=$state;$calls=0;$app->execute(1,$provider);$ok($calls===0,'No label in '.$state);}
foreach(['paid'=>false,'mode'=>'pickup','blocked'=>'Unpaid','recorded'=>true,'attempt'=>'uncertain']as$key=>$value){[$s,$m,$app]=$new();$s->order[$key]=$value;$calls=0;$app->execute(1,$provider);$ok($calls===0,'Ineligible '.$key.' never invokes provider.');}
[$s,$m,$app]=$new();$calls=0;
$failure=static function()use(&$calls){++$calls;return ['success'=>false,'retry_safe'=>true,'error'=>'private detail'];};
$result=$app->execute(1,$failure);$ok($s->order['attempt']==='retryable'&&!str_contains($result['error'],'private'),'Known rejection is visible, redacted and retryable.');
$app->execute(1,$provider);$app->execute(1,$provider);$ok($calls===2&&$s->order['attempt']==='ready','Retry after clear rejection creates one successful label.');
foreach([static fn()=>['success'=>false],static function(){throw new RuntimeException('timeout private data');}]as$failure){[$s,$m,$app]=$new();$app->execute(1,$failure);$calls=0;$app->execute(1,$provider);$ok($s->order['attempt']==='uncertain'&&$calls===0,'Unknown outcome never blindly retries.');}
[$s,$m,$app]=$new();$s->order['attempt']='attempting';$calls=0;$app->execute(1,$provider);$ok($calls===0,'Crash marker prevents duplicate dispatch.');
[$s,$m,$app]=$new();$calls=0;
$app->execute(1,static function()use($app,$provider,&$calls,$ok){++$calls;try{$app->execute(1,$provider);$ok(false,'Concurrent request');}catch(RuntimeException){$ok(true,'Concurrent request excluded.');}return ['success'=>true];});
$ok($calls===1,'Only one concurrent provider attempt.');
// Actual vendor API response classification with an in-memory HTTP adapter, no network.
function get_option($k){return [];}
function wp_get_environment_type(){return 'local';}
function wp_json_encode($v){return json_encode($v);}
function esc_html($v){return htmlspecialchars((string)$v);}
class WP_Error{function get_error_message(){return 'fixture timeout';}}
function is_wp_error($v){return $v instanceof WP_Error;}
function wp_remote_post($url,$params){return $GLOBALS['response'];}
function wp_remote_retrieve_response_code($r){return $r['code'];}
function wp_remote_retrieve_body($r){return $r['body'];}
class GLS_Shipping_Account_Helper{static function get_account_setting($k){return ['country'=>'hu','mode'=>'sandbox','password'=>'fixture','username'=>'fixture'][$k]??'';}}
require dirname(__DIR__,2).'/gls-shipping-for-woocommerce/includes/api/class-gls-shipping-api-service.php';
foreach([
 [new WP_Error(),false,'timeout'],
 [['code'=>401,'body'=>'{}'],true,'authentication rejection'],
 [['code'=>503,'body'=>'{}'],false,'HTTP failure unknown'],
 [['code'=>200,'body'=>'broken'],false,'invalid JSON unknown'],
 [['code'=>200,'body'=>'{"ErrorCode":7}'],true,'explicit rejection'],
 [['code'=>200,'body'=>'{"PrintLabelsErrorList":[{"ErrorDescription":"invalid address","ClientReferenceList":["Order:1"]}]}'],true,'validation rejection'],
 [['code'=>200,'body'=>'{"ErrorCode":7,"PrintLabelsInfoList":[{"ParcelId":42}]}'],false,'partial result'],
 [['code'=>200,'body'=>'{"Labels":[1],"PrintLabelsInfoList":[{"ParcelId":42}]}'],false,'success not replayable'],
]as[$response,$safe,$why]){$GLOBALS['response']=$response;$api=new GLS_Shipping_API_Service();try{$api->send_order([]);}catch(Exception){}$ok($api->safe_to_retry()===$safe,$why);}
// Legacy bulk print must not bypass the per-order idempotency journal.
define('ABSPATH','/unused/');
function add_action(...$args){} function add_filter(...$args){} function __($s,...$args){return $s;}
function wp_die($message,...$args){throw new RuntimeException($message);}
class GLS_Shipping_Order{}
require dirname(__DIR__,2).'/gls-shipping-for-woocommerce/includes/admin/class-gls-shipping-bulk.php';
$bulk=new GLS_Shipping_Bulk();$actions=$bulk->register_gls_bulk_actions([]);
$ok(isset($actions['generate_gls_labels'])&&!isset($actions['print_gls_labels']),'Only guarded bulk generation is offered.');
try{$bulk->process_bulk_gls_label_generation('fixture','print_gls_labels',[1]);$ok(false,'Bulk bypass');}catch(RuntimeException $e){$ok(str_contains($e->getMessage(),'nem engedélyezett'),'Direct legacy bulk request is blocked before HTTP.');}
echo "GLS automatic label: $n assertions passed; offline provider/HTTP fixtures only.\n";
