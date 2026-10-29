<?php
declare(strict_types=1);
// Mutates only an EMPTY disposable local fixture. Provider ports are doubles, never real API calls.
if(PHP_SAPI!=='cli'||getenv('AK_LIFECYCLE_FIXTURE')!=='1')exit(1);
$_SERVER['HTTP_HOST']='localhost:18882';require '/var/www/html/wp-load.php';
if(DB_NAME!=='lifecycle_fixture'||home_url()!=='http://localhost:18882'||wp_get_environment_type()!=='local'||!WP_HTTP_BLOCK_EXTERNAL)throw new RuntimeException('Wrong fixture.');
use Appleklinika\BackOffice\Application\ChangeFulfilment;
use Appleklinika\BackOffice\Infrastructure\{WooOrderBackOfficeRepository,WooFulfilmentStore,WooOrderMutex};
$n=0;$assert=static function($ok,$why)use(&$n){++$n;if(!$ok)throw new RuntimeException($why);};
$mail=[];$network=0;$paymentStarts=0;
add_filter('pre_wp_mail',static function($previous,$args)use(&$mail){$mail[]=$args;return true;},999,2);
add_filter('pre_http_request',static function()use(&$network){++$network;return new WP_Error('offline');},999);
// Only the external redirect/start-payment boundary is replaced; native checkout validation and order creation run.
add_action('woocommerce_rest_checkout_process_payment_with_context',static function($context,$result)use(&$paymentStarts){
 if($context->payment_method!=='barion')throw new RuntimeException('Disallowed payment reached processor.');
 ++$paymentStarts;$result->set_status('success');$result->set_redirect_url(home_url('/?local-payment-fixture=1'));
},10,2);
WC()->initialize_session();WC()->initialize_cart();
$before=wc_get_orders(['limit'=>-1,'return'=>'ids','status'=>array_merge(array_keys(wc_get_order_statuses()),['wc-checkout-draft'])]);
$p=new WC_Product_Simple();$p->set_name('LOCAL checkout payment matrix');$p->set_regular_price('1000');$p->set_manage_stock(true);$p->set_stock_quantity(10);$p->save();
$address=['first_name'=>'Anna','last_name'=>'Fixture','country'=>'HU','postcode'=>'6722','city'=>'Szeged','address_1'=>'Fixture utca 1.','email'=>'checkout-fixture@example.invalid','phone'=>'+36301234567'];
WC()->customer->set_billing_country('HU');WC()->customer->set_shipping_country('HU');WC()->customer->set_shipping_postcode('6722');
WC()->cart->empty_cart();WC()->cart->add_to_cart($p->get_id(),1);WC()->cart->calculate_totals();
$all=WC()->shipping()->get_packages();$rates=$all[0]['rates'];$ids=array_keys($rates);
$pickup=current(array_filter($ids,static fn($id)=>str_starts_with($id,'local_pickup:')));
$gls=current(array_filter($ids,static fn($id)=>str_starts_with($id,'gls_shipping_method:')));
$mpl=current(array_filter($ids,static fn($id)=>str_starts_with($id,'ak_mpl_home:')));
$request=static function($shipping,$pay,$verb='POST')use($address){
 WC()->session->set('chosen_shipping_methods',[$shipping]);WC()->cart->calculate_totals();
 $r=new WP_REST_Request($verb,'/wc/store/v1/checkout');$r->set_header('Nonce',wp_create_nonce('wc_store_api'));
 $r->set_body_params(['billing_address'=>$address,'shipping_address'=>$address,'payment_method'=>$pay,
  'additional_fields'=>['appleklinika/company_purchase'=>false],'create_account'=>false]);return rest_do_request($r);
};
$callback=static function(int $id,string $state):array{
 $command='php '.escapeshellarg(__DIR__.'/checkout-barion-callback.php').' '.$id.' '.escapeshellarg($state);
 $result=json_decode((string)shell_exec($command),true,512,JSON_THROW_ON_ERROR);wp_cache_flush();return $result;
};
try{
 $assert($pickup&&$gls&&$mpl,'All three delivery families exist.');
 $assert(count($rates)===5,'No generic free courier method.');
 foreach(['local_pickup'=>0,'gls_shipping_method'=>1990,'gls_shipping_method_parcel_shop'=>1490,'ak_mpl_home'=>2090,'vp_pont'=>1040] as $prefix=>$cost){
  $matches=array_values(array_filter($rates,static fn($r)=>explode(':',$r->get_id())[0]===$prefix));
  $assert(count($matches)===1&&(float)$matches[0]->get_cost()===(float)$cost,'Shipping tariff unchanged: '.$prefix);
 }
 foreach(['bacs'] as $id){$settings=get_option('woocommerce_'.$id.'_settings');$assert($settings['enabled']==='no'&&$settings['instructions']===''&&$settings['description']==='','Effective '.$id.' settings disabled, stale instructions removed.');}
 $assert((get_option('woocommerce_cod_settings')['enabled']??'')==='yes','Legacy staff cash configuration remains intact; checkout availability below still denies COD.');
 foreach($ids as $ship){
  WC()->session->set('chosen_shipping_methods',[$ship]);
  foreach(['cod','bacs','unknown'] as $old){
   WC()->session->set('chosen_payment_method',$old);
   $assert(array_keys(WC()->payment_gateways()->get_available_payment_gateways())===['barion']&&WC()->session->get('chosen_payment_method')==='barion','Barion only; stale '.$old.' cleared for '.$ship);
  }
  foreach(['cod','bacs','unknown'] as $pay){
   $res=$request($ship,$pay);$data=$res->get_data();
   $assert($res->get_status()>=400,'Tampered '.$ship.' + '.$pay.' rejected.');
   $assert(in_array($data['code']??'', ['rest_invalid_param','woocommerce_rest_checkout_payment_method_disabled'],true),'Native gateway rejection before processor.');
  }
  $res=$request($ship,'barion','PUT');$assert($res->get_status()===200,'Barion remains eligible: '.$ship);
 }
 $assert($paymentStarts===0&&$network===0,'Invalid requests never initiate a payment/provider.');
 $assert(wc_get_product($p->get_id())->get_stock_quantity()===10&&$mail===[],'Rejected requests have no stock/email side effect.');
 $paidIds=[];
 foreach([$gls,$mpl,$pickup] as $index=>$ship){
  WC()->session->set('store_api_draft_order',null);WC()->session->set('order_awaiting_payment',null);
  WC()->cart->empty_cart();WC()->cart->add_to_cart($p->get_id(),1);WC()->cart->calculate_totals();$mail=[];
  $res=$request($ship,'barion');$data=$res->get_data();
  $assert($res->get_status()===200,'Real Store API submission accepted: '.json_encode($data));
  $o=wc_get_order($data['order_id']??0);$id=$o->get_id();$paidIds[]=$id;
  $assert(!$o->is_paid()&&!$o->get_date_paid(),'Redirect/submission never marks paid.');
  $ack=array_values(array_filter($mail,static fn($m)=>$m['to']==='checkout-fixture@example.invalid'));
  $assert(count($ack)===1&&$ack[0]['subject']==='Rendelésed beérkezett'&&$ack[0]['attachments']===[],'One acknowledgement, no invoice.');
  $o->update_meta_data('_ak_payment_fixture',true);$o->save_meta_data();
  WC()->payment_gateways()->payment_gateways()['barion']->set_barion_payment_id($o,'LOCAL-PAYMENT-'.$id);
  $stock=wc_get_product($p->get_id())->get_stock_quantity();
  $failed=$callback($id,'Failed');
  $assert(!$failed['paid']&&!$failed['date_paid']&&$failed['status']==='failed','Failed verified Barion state remains unpaid.');
  $assert($failed['invoices']===0&&$failed['provider_state_queries']===1&&$failed['network']===0,'Failure has no invoice/network; original callback reads fixture state.');
  $assert(wc_get_product($p->get_id())->get_stock_quantity()===$stock,'Failure does not deduct stock.');
  // Ignore native failed-payment notice when checking successful primary-email idempotency.
  $priorEmails=$failed['emails'];$success=$callback($id,'Succeeded');
  $assert($success['paid']&&$success['date_paid']&&$success['status']==='processing','Verified success alone marks paid/processing.');
  $assert($success['invoices']===1&&$success['provider_state_queries']===1&&$success['network']===0,'One invoice port invocation after verified success.');
  $assert($success['emails']===$priorEmails+1&&$success['subjects']===['Rendelésed visszaigazoltuk']&&$success['attachments']===[1],'Exactly one accepted email with invoice fixture: '.json_encode($success));
  $assert(wc_get_product($p->get_id())->get_stock_quantity()===$stock-1,'One stock deduction.');
  for($i=0;$i<2;++$i){$repeat=$callback($id,'Succeeded');$assert($repeat['invoices']===1&&$repeat['emails']===$success['emails']&&$repeat['network']===0,'Repeated original callback produces no duplicate side effects.');}
  $assert(wc_get_product($p->get_id())->get_stock_quantity()===$stock-1,'Repeated callback cannot deduct stock again.');
  do_action('woocommerce_store_api_checkout_order_processed',wc_get_order($id));do_action('woocommerce_checkout_order_processed',$id,[],wc_get_order($id));
  $assert(count(array_filter($mail,static fn($m)=>$m['to']==='checkout-fixture@example.invalid'))===1,'Repeated submission hook cannot duplicate acknowledgement.');
  $repo=new WooOrderBackOfficeRepository();$assert($repo->state(wc_get_order($id))==='new','Payment does not mean shipment or pickup.');
  if($ship===$pickup){
   $change=new ChangeFulfilment(new WooFulfilmentStore($repo),new WooOrderMutex());
   $change->execute($id,'start',1,'new');$change->execute($id,'prepare_pickup',1,'preparation');
   $assert($repo->state(wc_get_order($id))==='ready_for_pickup'&&wc_get_order($id)->is_paid(),'Paid and ready for pickup are distinct facts.');
   $change->execute($id,'picked_up',1,'ready_for_pickup');
   $assert($repo->state(wc_get_order($id))==='picked_up'&&wc_get_order($id)->has_status('processing'),'Actual pickup recorded separately; completed remains separate.');
   $assert(count(array_filter($mail,static fn($m)=>$m['to']==='checkout-fixture@example.invalid'))===1,'Pickup progression sends no shipping email.');
  }
 }
 $assert(count(array_unique($paidIds))===3&&$paymentStarts===3,'One accepted order/payment start per shipping scenario.');
 $assert($network===0,'No external/provider HTTP requests.');
 echo "Native Woo Barion-only checkout: $n assertions passed. Provider responses/invoice are LOCAL doubles.\n";
} finally {
 WC()->cart->empty_cart();
 foreach(array_diff(wc_get_orders(['limit'=>-1,'return'=>'ids','status'=>array_merge(array_keys(wc_get_order_statuses()),['wc-checkout-draft'])]),$before) as $id){
  as_unschedule_all_actions('appleklinika_lifecycle_dispatch',null,'appleklinika-lifecycle');wc_release_stock_for_order($id);$o=wc_get_order($id);if($o)$o->delete(true);
  $file=wp_upload_dir()['basedir'].'/wc_szamlazz/barion-fixture-'.$id.'.pdf';if(is_file($file))unlink($file);
 }
 $p->delete(true);WC()->session->destroy_session();
}
