<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('AK_LIFECYCLE_FIXTURE') !== '1') { exit(1); }
$_SERVER['HTTP_HOST']='localhost:18882'; require '/var/www/html/wp-load.php';
if (DB_NAME !== 'lifecycle_fixture' || wp_get_environment_type() !== 'local' || !WP_HTTP_BLOCK_EXTERNAL) { exit(1); }
use Appleklinika\BackOffice\Infrastructure\{MplHomeShipping,MplPackage,MplCarrier,WooOrderBackOfficeRepository,WooFulfilmentStore,WooOrderMutex,WooOrderLifecycleStore,WooCustomerMailer,LifecycleEmailPresentation,OrderDocuments};
use Appleklinika\BackOffice\Application\{ChangeFulfilment,OrderLifecycle};
use Appleklinika\BackOffice\Domain\{CustomerNotification as N,DeliveryMode as M};
$n=0;$requests=0;$mail=[];
$check=static function($v,$m)use(&$n){++$n;if(!$v)throw new RuntimeException($m);};
add_filter('pre_http_request',static function()use(&$requests){++$requests;return new WP_Error('offline_fixture');},999);
add_filter('pre_wp_mail',static function($v,$a)use(&$mail){$mail[]=$a;return true;},999,2);
update_option('woocommerce_weight_unit','kg');update_option('woocommerce_dimension_unit','cm');
WC()->initialize_session();WC()->initialize_cart();WC()->customer->set_shipping_country('HU');
$p=new WC_Product_Simple();$p->set_name('MPL LOCAL fixture');$p->set_regular_price('1000');$p->set_weight('1');$p->set_length('20');$p->set_width('15');$p->set_height('5');$p->save();
$order=null;
try {
    $hooks=new \Appleklinika\BackOffice\Interfaces\MplHooks();
    $labels=$hooks->checkoutBlock(['blockName'=>'woocommerce/checkout-shipping-method-block']);
    $check($labels['attrs']['shippingText']==='Kiszállítás' && $labels['attrs']['localPickupText']==='Átvételi pont vagy üzlet','Native delivery choice uses clear Hungarian labels');
    $block=parse_blocks('<!-- wp:woocommerce/checkout-pickup-options-block --><div class="wp-block-woocommerce-checkout-pickup-options-block"></div><!-- /wp:woocommerce/checkout-pickup-options-block -->')[0];
    $mounted=$hooks->checkoutBlock($block);
    $check(count($mounted['innerBlocks'])===1 && str_contains(serialize_block($mounted),'vp-woo-pont/pont-picker-block'),'Supported vendor picker mounted without editing stored checkout');
    $check($hooks->checkoutBlock($mounted)===$mounted,'Picker mount is idempotent');
    $check($hooks->checkoutBlock(['blockName'=>'woocommerce/checkout-contact-information-block'])===['blockName'=>'woocommerce/checkout-contact-information-block'],'Other checkout fields untouched');
    WC()->cart->add_to_cart($p->get_id(),1);
    $package=['contents'=>WC()->cart->get_cart(),'destination'=>['country'=>'HU']];
    $shipping=new MplHomeShipping(9942);$rates=$shipping->get_rates_for_package($package);
    $check(count($rates)===1 && (float)current($rates)->get_cost()===2090.0,'Native home rate correct');
    $costs=VP_Woo_Pont_Helpers::calculate_shipping_costs();
    $check(count($costs)===3 && array_values(array_unique(array_map('floatval',array_column($costs,'gross'))))===[1040.0],'Three supported point types, correct price');
    $rate=new WC_Shipping_Rate('vp_pont:1','MPL pont',0,[],'vp_pont',1);
    $filtered=(new \Appleklinika\BackOffice\Interfaces\MplHooks())->rates(['vp_pont:1'=>$rate],$package);
    $check((float)$filtered['vp_pont:1']->get_cost()===1040.0,'No misleading zero price before point selection');
    $p->set_weight('');$contents=[['data'=>$p,'quantity'=>1]];
    $check(MplPackage::fromContents($contents)['kg']===0.0,'Unknown product weight fails closed');$p->set_weight('1');
    $order=wc_create_order();$order->set_payment_method('bacs');$order->set_created_via('store-api');$order->set_status('processing');
    $order->set_address(['first_name'=>'Ágnes','last_name'=>'Fixture','email'=>'mpl-fixture@example.invalid','country'=>'HU','city'=>'Szeged','postcode'=>'6722','address_1'=>'Fixture utca 1.'],'billing');
    $item=new WC_Order_Item_Shipping();$item->set_method_id('vp_pont');$item->set_method_title('MPL PostaPont');$item->set_total(1040);$order->add_item($item);$order->add_product($p);$order->calculate_totals();
    // Provider fixtures only; never submitted to any carrier.
    $order->update_meta_data('_vp_woo_pont_provider','postapont_automata');$order->update_meta_data('_vp_woo_pont_point_id','fixture-point');
    $order->update_meta_data('_appleklinika_lifecycle_submitted',['source'=>'validated_checkout']);
    $order->update_meta_data('_appleklinika_lifecycle_email_order_received',['state'=>'accepted']);
    $order->update_meta_data('_appleklinika_lifecycle_email_paid_invoice',['state'=>'accepted']);$order->save();$id=$order->get_id();$mail=[];
    $repo=new WooOrderBackOfficeRepository();$store=new WooFulfilmentStore($repo);$change=new ChangeFulfilment($store,new WooOrderMutex());
    $check($repo->deliveryMode($order)===M::MPL,'Point metadata selects MPL');
    $check($repo->deliveryModeLabel($order)==='MPL kézbesítés','MPL in Back Office');
    $check($repo->primaryAction($order)==='start','Shared workflow starts normally');
    $change->execute($id,'start',1,'new');$change->execute($id,'start_packing',1,'preparation');$change->execute($id,'packing_completed',1,'packing');
    $check($mail===[],'Intermediate states send no email');
    try{$change->execute($id,'handed_to_carrier',1,'ready_for_shipping');$rejected=false;}catch(InvalidArgumentException){$rejected=true;}
    $check($rejected,'Handoff without label/manifest rejected');
    try{$change->execute($id,'create_mpl_label',1,'ready_for_shipping');$rejected=false;}catch(InvalidArgumentException){$rejected=true;}
    $check($rejected && $requests===0,'Disabled/unconfigured API cannot call provider');
    $o=wc_get_order($id);foreach(['_vp_woo_pont_parcel_id'=>'fixture-label','_vp_woo_pont_parcel_pdf'=>'fixture.pdf','_vp_woo_pont_parcel_number'=>'PB123456789HU','_vp_woo_pont_mpl_closed'=>'no'] as $k=>$v){$o->update_meta_data($k,$v);}$o->save_meta_data();
    $check($repo->primaryAction(wc_get_order($id))==='handed_to_carrier','Label-ready exposes handoff');
    $check(count((new OrderDocuments())->trackingLinks(wc_get_order($id)))===1,'Stored MPL tracking exposed');
    try{$change->execute($id,'handed_to_carrier',1,'ready_for_shipping');$rejected=false;}catch(InvalidArgumentException){$rejected=true;}
    $check($rejected,'MPL manifest closure required');
    $o=wc_get_order($id);$o->update_meta_data('_vp_woo_pont_mpl_closed','yes');$o->save_meta_data();
    $change->execute($id,'handed_to_carrier',1,'ready_for_shipping');
    for($i=0;$i<3;$i++)do_action('appleklinika_lifecycle_dispatch',$id,N::SHIPPED);
    $customer=array_values(array_filter($mail,static fn($a)=>$a['to']==='mpl-fixture@example.invalid'));
    $check(count($customer)===1,'One handoff email despite retries');
    $check(str_contains($customer[0]['message'],'MPL csomagszám')&&!str_contains($customer[0]['message'],'GLS'),'Carrier-specific shipping copy');
    $check(str_contains($customer[0]['message'],'posta.hu/nyomkovetes/nyitooldal?searchvalue=PB123456789HU'),'Official MPL tracking URL');
    $check((new WooOrderLifecycleStore())->order($id)->handoffRecorded,'History confirms actual handoff');
    $check($repo->state(wc_get_order($id))==='handed_to_carrier'&&wc_get_order($id)->get_status()==='processing','Woo completion still separate');
    try{$change->execute($id,'handed_to_carrier',1,'ready_for_shipping');$rejected=false;}catch(InvalidArgumentException){$rejected=true;}
    $check($rejected,'Repeated old-state handoff rejected');
    $check(!apply_filters('woocommerce_email_enabled_vp_woo_pont_order_shipped',true,wc_get_order($id)),'No duplicate plugin shipping email');
    $check(count($repo->history(wc_get_order($id)))===4,'One shared history per real transition');
    $o=wc_get_order($id);$o->update_meta_data('_vp_woo_pont_provider','foxpost_foxpost');
    $check(!MplCarrier::matches($o),'Other pickup provider cannot be mistaken for MPL');
    $check($requests===0,'No external calls');
    echo "LOCAL MPL checkout/state fixture: $n assertions passed; provider is NOT proven.\n";
} finally {
    if($order){as_unschedule_all_actions('appleklinika_lifecycle_dispatch',null,'appleklinika-lifecycle');$order->delete(true);}
    WC()->cart->empty_cart();$p->delete(true);
}
