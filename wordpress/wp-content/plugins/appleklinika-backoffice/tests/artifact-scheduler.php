<?php
declare(strict_types=1);
namespace { if(PHP_SAPI!=='cli'){http_response_code(404);exit;} require_once dirname(__DIR__).'/src/Application/Port/OrderMutex.php'; }
namespace Appleklinika\BackOffice\Infrastructure {
 class OrderDocuments {function filePath($o,$k){return $o->meta[$k.'_file']??null;}}
 class LifecycleConfiguration {static function manages($o){return $o->managed;}}
 class SzamlazzInvoice {function generationBlock($o){return $GLOBALS['invoice_block']??null;}}
 class WooOrderBackOfficeRepository {
  function deliveryMode($o){return $o->mode;} function state($o){return $o->state;}
  function trackingCodes($o){return $o->meta['_gls_tracking_codes']??[];}
  function fulfilmentBlockReason($o){return $o->block;} function glsReadinessMessage(){return $GLOBALS['gls_block']??null;}
  function recordAutomation($id,$kind,$result,$key){$GLOBALS['audit'][$id.':'.$key]=$result;}
 }
 class WooOrderMutex implements \Appleklinika\BackOffice\Application\Port\OrderMutex {function synchronized(int $id,callable $op):mixed{return $op();}}
 class WooOrderLifecycleStore {} class SzamlazzAutomation {} class WooCustomerMailer {}
 class GlsAutomation {function __construct($r){} function runScheduled($id){if($_POST!==[])throw new \RuntimeException('Ambient provider override');$o=\wc_get_order($id);++$GLOBALS['gls_calls'];$o->meta['_gls_print_label']='label.pdf';$o->meta['_gls_tracking_codes']=['123'];$o->meta['gls_label_file']='/private/label.pdf';}}
}
namespace Appleklinika\BackOffice\Application {
 class OrderLifecycle {function __construct(...$args){}function paymentConfirmed($id){if($_POST!==[])throw new \RuntimeException('Ambient provider override');$o=\wc_get_order($id);++$GLOBALS['invoice_calls'];$o->meta['_wc_szamlazz_invoice']='TEST';$o->meta['_wc_szamlazz_invoice_pdf']='invoice.pdf';$o->meta['invoice_file']='/private/invoice.pdf';}}
}
namespace {
 if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
 // The interface is needed before the namespace fake is declared (PHP declarations are deferred for implements).
 function wp_get_environment_type(){return $GLOBALS['env'];} function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
 function update_option($k,$v,$autoload){$GLOBALS['options'][$k]=$v;}
 function doing_action($hook){return ($GLOBALS['hook']??'')===$hook;}
 function as_has_scheduled_action($h,$a,$g){return isset($GLOBALS['recurring'][$h]) || isset($GLOBALS['queue'][$h.':'.implode(',',$a)]);}
 function as_schedule_recurring_action($time,$interval,$hook,$args,$group,$unique){$GLOBALS['recurring'][$hook]=[$interval,$args,$group,$unique];}
 function as_enqueue_async_action($h,$a,$g,$unique){
  // Reproduce the real store's hook/group-wide unique constraint.
  if($unique){foreach($GLOBALS['queue']??[]as$q){if($q[0]===$h&&$q[2]===$g){return 0;}}}
  $GLOBALS['queue'][$h.':'.implode(',',$a)]=[$h,$a,$g,$unique];return count($GLOBALS['queue']);
 }
 function wc_get_orders($args){$GLOBALS['query']=$args;return (object)['orders'=>[1,2],'max_num_pages'=>2];}
 function wc_get_order($id){return $GLOBALS['orders'][$id]??false;}
 class WC_Order {
  public bool $paid=true,$managed=true,$dated=true;public string $status='processing',$mode='gls',$state='ready_for_shipping';public ?string $block=null;public array $meta=[];
  function get_meta($k,$single=true){return $this->meta[$k]??'';}function update_meta_data($k,$v){$this->meta[$k]=$v;}function save_meta_data(){}
  function is_paid(){return $this->paid;}function get_date_paid(){return $this->dated?new \stdClass():null;}function get_status(){return $this->status;}
 }
 spl_autoload_register(static function($c){$p='Appleklinika\\BackOffice\\';if(str_starts_with($c,$p)){require_once dirname(__DIR__).'/src/'.str_replace('\\','/',substr($c,strlen($p))).'.php';}});
 $GLOBALS['env']='staging';$GLOBALS['options']=['home'=>'https://teszt.appleklinika.com'];$GLOBALS['orders']=[1=>new WC_Order()];$GLOBALS['invoice_calls']=$GLOBALS['gls_calls']=0;
 $n=0;$ok=static function($v,$m)use(&$n){++$n;if(!$v)throw new \RuntimeException($m);};
 $adapter=new \Appleklinika\BackOffice\Infrastructure\WooArtifactReconciliation();
 $adapter->schedule();$adapter->schedule();$ok(count($GLOBALS['recurring'])===1&&reset($GLOBALS['recurring'])[0]===300,'One recurring Action Scheduler scan, not a parallel scheduler.');
 $adapter->scan();$ok($GLOBALS['query']['limit']===25&&$GLOBALS['query']['return']==='ids'&&$GLOBALS['query']['paginate']===true,'Discovery is bounded and does not hydrate full order lists.');
 $ok($GLOBALS['options']['appleklinika_artifacts_scan_page']===2,'Cursor advances rather than scanning only newest orders.');
 $adapter->scan();$ok($GLOBALS['options']['appleklinika_artifacts_scan_page']===1&&count($GLOBALS['queue'])===2,'Discovery wraps and every distinct order is queued despite AS hook/group uniqueness.');
 $adapter->enqueue(1);$adapter->enqueue(2);$ok(count($GLOBALS['queue'])===2 && reset($GLOBALS['queue'])[3]===false,'Exact-argument guard suppresses repeats without suppressing other orders.');
 $adapter->dispatch(1);$ok($GLOBALS['invoice_calls']===0&&$GLOBALS['gls_calls']===0,'Ordinary request cannot dispatch provider writes.');
 foreach(['production','development']as$env){$GLOBALS['env']=$env;$ok(!$adapter::enabled(),'No rollout outside verified local/staging.');}
 $GLOBALS['env']='staging';$GLOBALS['options']['home']='https://appleklinika.com';$ok(!$adapter::enabled(),'Production hostname cannot enter TEST worker.');$GLOBALS['options']['home']='https://teszt.appleklinika.com';
 $o=$GLOBALS['orders'][1];
 foreach(['paid'=>false,'dated'=>false,'managed'=>false,'status'=>'cancelled']as$k=>$v){$old=$o->$k;$o->$k=$v;$ok(!$adapter->facts(1,'invoice')['eligible'],'Invoice eligibility uses authoritative '.$k);$o->$k=$old;}
 foreach(['mode'=>'pickup','mode_unknown'=>'unknown','state'=>'packing','state_late'=>'handed_to_gls','paid'=>false,'dated'=>false,'block'=>'Review incomplete']as$k=>$v){$prop=explode('_',$k)[0];$old=$o->$prop;$o->$prop=$v;$ok(!$adapter->facts(1,'gls')['eligible'],'GLS eligibility uses '.$k);$o->$prop=$old;}
 $o->mode='unknown';$ok($adapter->facts(1,'invoice')['eligible'],'Invoice stays independent from shipping.');$o->mode='gls';
 $o->meta['_wc_szamlazz_invoice_pdf']='missing.pdf';$ok($adapter->facts(1,'invoice')['evidence']&&!$adapter->facts(1,'invoice')['complete'],'Partial invoice blocks duplication, does not claim PDF readiness.');$o->meta=[];
 $o->meta['_gls_parcel_ids']=[9];$ok($adapter->facts(1,'gls')['evidence']&&!$adapter->facts(1,'gls')['complete'],'Parcel without PDF is evidence, not ready.');$o->meta=[];
 $_POST=['forged_account'=>'must-not-reach-provider'];$GLOBALS['hook']=$adapter::WORK;$adapter->dispatch(1);$adapter->dispatch(1);
 $ok($_POST===['forged_account'=>'must-not-reach-provider'],'Ambient queue input is isolated and restored after provider calls.');
 $ok($GLOBALS['invoice_calls']===1&&$GLOBALS['gls_calls']===1,'Scheduled worker reuses both integrations exactly once.');
 $ok(count($GLOBALS['audit'])===2,'System successes reach the existing audit adapter once.');
 $ok($adapter->facts(1,'invoice')['complete']&&$adapter->facts(1,'gls')['complete'],'Ready means actual file resolution and identifiers.');
 echo "Artifact scheduler: $n assertions passed; isolated queue/provider adapters only.\n";
}
