<?php
declare(strict_types=1);
// Disposable, network-isolated LOCAL database only. Invoice port is a fixture.
if (PHP_SAPI !== 'cli' || getenv('AK_LIFECYCLE_FIXTURE') !== '1') { exit(1); }
$_SERVER['HTTP_HOST']='localhost:18882';
require '/var/www/html/wp-load.php';
if (home_url() !== 'http://localhost:18882' || wp_get_environment_type() !== 'local' || DB_NAME !== 'lifecycle_fixture'
    || !defined('WP_HTTP_BLOCK_EXTERNAL') || !WP_HTTP_BLOCK_EXTERNAL) { throw new RuntimeException('Wrong fixture environment.'); }
use Appleklinika\BackOffice\Application\{ChangeFulfilment,OrderLifecycle};
use Appleklinika\BackOffice\Application\Port\InvoiceAutomation;
use Appleklinika\BackOffice\Infrastructure\{CashPickup,WooOrderMutex,WooFulfilmentStore,WooOrderBackOfficeRepository,WooOrderLifecycleStore,WooCustomerMailer};
use Appleklinika\BackOffice\Interfaces\{LifecycleHooks,BackOfficeRouter,FulfilmentAdmin};

$mail=[]; $requests=0; $n=0;
add_filter('pre_wp_mail',static function($value,$args)use(&$mail){$mail[]=$args;return true;},999,2);
add_filter('pre_http_request',static function()use(&$requests){++$requests;return new WP_Error('fixture_network_blocked');},999);
$assert=static function($ok,$why)use(&$n){++$n;if(!$ok){throw new RuntimeException($why);}};
$prior=get_option('appleklinika_cash_pickup_enabled','no');update_option('appleklinika_cash_pickup_enabled','yes');
update_option('woocommerce_manage_stock','yes');
$repo=new WooOrderBackOfficeRepository();$mutex=new WooOrderMutex();$store=new WooOrderLifecycleStore();
$invoice=new class implements InvoiceAutomation {
    public int $calls=0; public array $files=[];
    public function ready():bool{return true;}
    public function run(int $id):void{
        ++$this->calls;$o=wc_get_order($id);$upload=wp_upload_dir();$dir=$upload['basedir'].'/wc_szamlazz';wp_mkdir_p($dir);
        $name='/cash-fixture-'.$id.'.pdf';file_put_contents($dir.$name,"%PDF-1.4\n% LOCAL FIXTURE - NOT A PROVIDER INVOICE\n%%EOF\n");$this->files[]=$dir.$name;
        $o->update_meta_data('_wc_szamlazz_invoice','CASH-FIXTURE-'.$id);$o->update_meta_data('_wc_szamlazz_invoice_pdf',$name);$o->save_meta_data();
        do_action('wc_szamlazz_document_created',['order_id'=>$id,'document_type'=>'invoice']);
    }
};
// Replace only the provider port in this isolated test; licence checks are not patched.
foreach(($GLOBALS['wp_filter']['woocommerce_payment_complete']->callbacks??[]) as $priority=>$callbacks){foreach($callbacks as $entry){$cb=$entry['function'];if(is_array($cb)&&$cb[0] instanceof LifecycleHooks&&$cb[1]==='paymentComplete'){remove_action('woocommerce_payment_complete',$cb,$priority);}}}
$hooks=new LifecycleHooks(new OrderLifecycle($store,$mutex,$invoice,new WooCustomerMailer()),$store);
add_action('woocommerce_payment_complete',[$hooks,'paymentComplete'],20);
$buyer=wp_insert_user(['user_login'=>'cash_fixture_'.wp_generate_password(8,false),'user_pass'=>wp_generate_password(48),'user_email'=>'cash-fixture@example.invalid','role'=>'customer']);
$product=new WC_Product_Simple();$product->set_name('Készpénzes átvétel - LOCAL fixture');$product->set_regular_price('1000');$product->set_manage_stock(true);$product->set_stock_quantity(3);$product->save();
$order=wc_create_order(['customer_id'=>$buyer]);$order->set_created_via('store-api');$order->set_payment_method('cod');$order->set_payment_method_title('Készpénz személyes átvételkor');
$order->set_address(['first_name'=>'Ágnes','last_name'=>'Fixture','email'=>'cash-fixture@example.invalid','country'=>'HU','city'=>'Szeged','postcode'=>'6722','address_1'=>'Fixture utca 1.'],'billing');
$order->add_product($product,1);$ship=new WC_Order_Item_Shipping();$ship->set_method_id('local_pickup');$ship->set_method_title('Személyes átvétel');$ship->set_total(0);$order->add_item($ship);$order->calculate_totals();$order->save();$id=$order->get_id();
$customer=static function()use(&$mail){return array_values(array_filter($mail,static fn($m)=>$m['to']==='cash-fixture@example.invalid'));};
try {
    WC()->initialize_session();WC()->initialize_cart();
    $gateway = WC()->payment_gateways()->payment_gateways()['cod'];
    $gatewayHooks = new \Appleklinika\BackOffice\Interfaces\CashPickupHooks();
    WC()->session->set('chosen_shipping_methods',['gls_shipping_method:1']);
    $assert(!isset($gatewayHooks->gateways(['cod'=>$gateway])['cod']),'Cash cannot be selected for courier shipping.');
    WC()->session->set('chosen_shipping_methods',['local_pickup:2']);
    $assert(isset($gatewayHooks->gateways(['cod'=>$gateway])['cod']),'Cash is selectable for personal pickup.');
    do_action('woocommerce_store_api_checkout_order_processed',$order);
    do_action('woocommerce_checkout_order_processed',$id,[],$order);
    $assert(count($customer())===1&&$customer()[0]['subject']==='Rendelésed beérkezett','Exactly one acknowledgement before payment.');
    $assert($customer()[0]['attachments']===[]&&!str_contains($customer()[0]['message'],'Fizetve'),'Acknowledgement has no invoice or payment-success claim.');
    $gateway=WC()->payment_gateways()->payment_gateways()['cod'];$gateway->process_payment($id);
    $fresh=wc_get_order($id);
    $assert($fresh->has_status('on-hold')&&!$fresh->is_paid()&&!$fresh->get_date_paid(),'Native cash submission remains genuinely unpaid.');
    $assert(wc_get_product($product->get_id())->get_stock_quantity()===2,'Native Woo on-hold reserves one unit.');
    $assert(count($customer())===1&&$invoice->calls===0,'No native duplicate or premature invoice.');
    $change=new ChangeFulfilment(new WooFulfilmentStore($repo),$mutex);
    $assert($repo->primaryAction($fresh)==='accept_cash_pickup','Staff sees explicit stock-check/accept action.');
    $change->execute($id,'accept_cash_pickup',1,'new');
    for($i=0;$i<3;++$i){do_action('appleklinika_lifecycle_dispatch',$id,'paid_invoice');}
    $fresh=wc_get_order($id);
    $assert(count($customer())===2&&$customer()[1]['subject']==='Rendelésed visszaigazoltuk','Exactly one staff acceptance email.');
    $accepted=$customer()[1];
    $assert($accepted['attachments']===[]&&!str_contains($accepted['message'],'Fizetve')&&!str_contains($accepted['message'],'fizetésed sikeresen')&&!str_contains($accepted['message'],'Számlád a mellékletben'),'Cash acceptance contains no payment/invoice fiction.');
    $assert(str_contains($accepted['message'],'személyes átvételkor')&&str_contains($accepted['message'],'előkészítés alatt')&&str_contains($accepted['message'],'Jósika utca'),'Acceptance states cash-at-pickup and actual preparation.');
    $assert(!$fresh->is_paid()&&$invoice->calls===0,'Staff acceptance does not mark paid or invoice.');
    $assert($repo->state($fresh)==='preparation'&&CashPickup::accepted($fresh),'One shared state and acceptance history.');
    $change->execute($id,'prepare_pickup',1,'preparation');
    do_action('appleklinika_lifecycle_dispatch',$id,'paid_invoice');
    $assert(count($customer())===2,'Ready-for-pickup is silent.');
    wp_set_current_user((int)$buyer);add_filter('woocommerce_is_account_page','__return_true');
    ob_start();(new BackOfficeRouter($repo,$change))->renderCustomerProgress(wc_get_order($id));$account=ob_get_clean();
    $assert(str_contains($account,'Átvételre előkészítve')&&!str_contains($account,'GLS'),'My Account uses pickup progress.');
    wp_set_current_user(1);ob_start();(new FulfilmentAdmin($change,$repo))->render(wc_get_order($id));$admin=ob_get_clean();
    $assert(str_contains($admin,'record_cash_pickup'),'Woo admin offers the same payment/handoff action.');
    $change->execute($id,'record_cash_pickup',1,'ready_for_pickup');
    $fresh=wc_get_order($id);
    $assert($fresh->is_paid()&&$fresh->get_date_paid()&&CashPickup::paymentRecorded($fresh),'Actual cash receipt is recorded with date and staff actor.');
    $assert($repo->state($fresh)==='picked_up'&&$fresh->has_status('processing'),'Pickup is shared; Woo completed is a separate closure.');
    $assert($invoice->calls===1&&(string)$fresh->get_meta('_wc_szamlazz_invoice',true)!=='','Payment generates exactly one fixture invoice.');
    $assert((new Appleklinika\BackOffice\Infrastructure\OrderDocuments())->filePath($fresh,'invoice')!==null,'Invoice is accessible through the existing document resolver.');
    for($i=0;$i<3;++$i){do_action('woocommerce_payment_complete',$id);do_action('appleklinika_lifecycle_dispatch',$id,'paid_invoice');do_action('appleklinika_lifecycle_dispatch',$id,'carrier_handoff');}
    try{$change->execute($id,'record_cash_pickup',1,'ready_for_pickup');$assert(false,'Duplicate handoff must reject.');}catch(InvalidArgumentException){$assert(true,'Duplicate handoff rejected.');}
    $assert($invoice->calls===1&&count($customer())===2&&wc_get_product($product->get_id())->get_stock_quantity()===2,'Retries duplicate neither invoice, email nor stock.');
    $history=$repo->history(wc_get_order($id));
    $assert(array_column($history,'action')===['accept_cash_pickup','prepare_pickup','record_cash_pickup'],'Audit history has exactly the three real actions.');
    $access=new \Appleklinika\BackOffice\Infrastructure\CustomerInvoice();
    $assert($access->path(wc_get_order($id),(int)$buyer)!==null,'Paid cash buyer can resolve their private invoice.');
    $assert($access->path(wc_get_order($id),0)===null,'Anonymous invoice access denied.');
    $assert($access->path(wc_get_order($id),1)===null,'Another user cannot access the invoice.');
    wp_set_current_user((int)$buyer);ob_start();(new \Appleklinika\BackOffice\Interfaces\CustomerInvoiceHooks())->render(wc_get_order($id));$invoiceUi=ob_get_clean();
    $assert(str_contains($invoiceUi,'ak_customer_invoice=')&&str_contains($invoiceUi,'_wpnonce=')&&!str_contains($invoiceUi,'/uploads/'),'Account invoice uses owner-bound authenticated route, never public upload URL.');
    $unpaid=wc_get_order($id);$unpaid->delete_meta_data(CashPickup::PAYMENT);
    $assert($access->path($unpaid,(int)$buyer)===null,'Cash receipt is required for customer invoice access.');
    $assert($requests===0,'No provider/network calls in the local fixture.');
    file_put_contents('/fixtures/cash-accepted-email.html',$accepted['message']);
    file_put_contents('/fixtures/cash-account.html',$account);
    echo "LOCAL cash/pickup: $n assertions passed; invoice port fixture, no provider/delivery claim.\n";
} finally {
    as_unschedule_all_actions('appleklinika_lifecycle_dispatch',null,'appleklinika-lifecycle');
    wc_increase_stock_levels($id);wc_release_stock_for_order($id);wc_get_order($id)->delete(true);$product->delete(true);
    require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user((int)$buyer);
    foreach($invoice->files as $file){if(is_file($file)){unlink($file);}}
    update_option('appleklinika_cash_pickup_enabled',$prior);
}
