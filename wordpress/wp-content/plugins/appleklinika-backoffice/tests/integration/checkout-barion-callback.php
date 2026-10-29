<?php
declare(strict_types=1);
// Called only by checkout-payment-eligibility.php in a disposable offline Woo database.
if (PHP_SAPI !== 'cli' || getenv('AK_LIFECYCLE_FIXTURE') !== '1') { exit(1); }
$_SERVER['HTTP_HOST']='localhost:18882';require '/var/www/html/wp-load.php';
if (DB_NAME!=='lifecycle_fixture'||home_url()!=='http://localhost:18882'||wp_get_environment_type()!=='local'||!WP_HTTP_BLOCK_EXTERNAL) { exit(2); }
use Appleklinika\BackOffice\Application\OrderLifecycle;
use Appleklinika\BackOffice\Application\Port\InvoiceAutomation;
use Appleklinika\BackOffice\Infrastructure\{WooOrderLifecycleStore,WooOrderMutex,WooCustomerMailer};
use Appleklinika\BackOffice\Interfaces\LifecycleHooks;
$id=(int)($argv[1]??0);$status=$argv[2]??'';$order=wc_get_order($id);
if (!$order || !$order->get_meta('_ak_payment_fixture') || !in_array($status,['Failed','Succeeded'],true)) { exit(3); }
$network=0;$mail=[];$queries=0;
add_filter('pre_http_request',static function()use(&$network){++$network;return new WP_Error('offline');},999);
add_filter('pre_wp_mail',static function($prior,$args)use(&$mail){$mail[]=$args;return true;},999,2);
$invoice=new class implements InvoiceAutomation {
 public function ready():bool{return true;}
 public function run(int $id):void{
  $o=wc_get_order($id);$o->update_meta_data('_ak_fixture_invoice_calls',1+(int)$o->get_meta('_ak_fixture_invoice_calls'));
  $dir=wp_upload_dir()['basedir'].'/wc_szamlazz';wp_mkdir_p($dir);$file='/barion-fixture-'.$id.'.pdf';
  file_put_contents($dir.$file,"%PDF-1.4\n% LOCAL FIXTURE, NOT A PROVIDER INVOICE\n%%EOF\n");
  $o->update_meta_data('_wc_szamlazz_invoice','LOCAL-FIXTURE-'.$id);$o->update_meta_data('_wc_szamlazz_invoice_pdf',$file);$o->save_meta_data();
  do_action('wc_szamlazz_document_created',['order_id'=>$id,'document_type'=>'invoice']);
 }
};
foreach(($GLOBALS['wp_filter']['woocommerce_payment_complete']->callbacks??[]) as $priority=>$callbacks){foreach($callbacks as $entry){$cb=$entry['function'];if(is_array($cb)&&$cb[0] instanceof LifecycleHooks&&$cb[1]==='paymentComplete'){remove_action('woocommerce_payment_complete',$cb,$priority);}}}
$store=new WooOrderLifecycleStore();$hooks=new LifecycleHooks(new OrderLifecycle($store,new WooOrderMutex(),$invoice,new WooCustomerMailer()),$store);
add_action('woocommerce_payment_complete',[$hooks,'paymentComplete'],20);
$client=new class($id,$status,$queries){
 public function __construct(private int $id,private string $status,public int &$queries){}
 public function GetPaymentState(string $paymentId):object{
  ++$this->queries;
  return (object)['PaymentRequestId'=>(string)$this->id,'Status'=>$this->status,'Errors'=>[],
   'Transactions'=>[(object)['POSTransactionId'=>$this->id,'TransactionId'=>'LOCAL-TXN-'.$this->id]]];
 }
};
register_shutdown_function(static function()use($id,&$mail,&$network,&$queries){
 for($i=0;$i<3;++$i){do_action('appleklinika_lifecycle_dispatch',$id,'paid_invoice');}
 $customer=array_values(array_filter($mail,static fn($m)=>$m['to']==='checkout-fixture@example.invalid'));
 $o=wc_get_order($id);$count=(int)$o->get_meta('_ak_fixture_customer_emails');$o->update_meta_data('_ak_fixture_customer_emails',$count+count($customer));$o->save_meta_data();
 echo json_encode(['paid'=>$o->is_paid(),'date_paid'=>(bool)$o->get_date_paid(),'status'=>$o->get_status(),
  'invoices'=>(int)$o->get_meta('_ak_fixture_invoice_calls'),'emails'=>$count+count($customer),
  'subjects'=>array_column($customer,'subject'),'attachments'=>array_map(static fn($m)=>count($m['attachments']),$customer),
  'invoice_record'=>$o->get_meta(WooOrderLifecycleStore::INVOICE),'received_record'=>$o->get_meta(WooOrderLifecycleStore::EMAIL.'order_received'),'paid_record'=>$o->get_meta(WooOrderLifecycleStore::EMAIL.'paid_invoice'),'issue'=>$o->get_meta('_appleklinika_lifecycle_last_issue'),'network'=>$network,'provider_state_queries'=>$queries]);
});
$_GET=['paymentId'=>'LOCAL-PAYMENT-'.$id];
(new WC_Gateway_Barion_IPN_Handler($client,WC()->payment_gateways()->payment_gateways()['barion']))->check_barion_ipn();
