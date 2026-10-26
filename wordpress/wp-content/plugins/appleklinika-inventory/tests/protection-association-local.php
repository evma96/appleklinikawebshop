<?php
/** LOCAL-only association persistence and native rendering, never provider transport. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__, 4) . '/wp-load.php';
$isLocal = wp_get_environment_type() === 'local' && getenv('AK_LOCAL_VERIFICATION') === '1';
$isTest = in_array('--target=test', $argv, true) && wp_get_environment_type() === 'staging'
    && parse_url(home_url(), PHP_URL_HOST) === 'teszt.appleklinika.com'
    && defined('AK_TEST_PROTECTION_VERIFICATION') && AK_TEST_PROTECTION_VERIFICATION
    && !function_exists('curl_exec') && !function_exists('curl_multi_exec') && !function_exists('fsockopen') && !function_exists('stream_socket_client');
if ((!$isLocal && !$isTest) || !defined('WP_HTTP_BLOCK_EXTERNAL') || !WP_HTTP_BLOCK_EXTERNAL) throw new RuntimeException('Guarded LOCAL or explicitly isolated TEST process required.');
use Appleklinika\Inventory\Infrastructure\WordPress\WooProtectionAssociation as Association;
use Appleklinika\BackOffice\Infrastructure\WooOrderBackOfficeRepository;
use Appleklinika\BackOffice\Interfaces\BackOfficeRouter;

$count = $http = $mail = 0;
$check = static function(bool $ok, string $message) use (&$count): void { ++$count; if (!$ok) throw new RuntimeException($message); };
$capture = static function(callable $f): string { ob_start(); try { $f(); return (string)ob_get_contents(); } finally { ob_end_clean(); } };
add_filter('pre_http_request', static function() use (&$http) { ++$http; return new WP_Error('local_blocked'); }, PHP_INT_MAX);
add_filter('pre_wp_mail', static function() use (&$mail) { ++$mail; return false; }, PHP_INT_MAX);
WC()->session = new class extends WC_Session {
    public function has_session() { return false; }
    public function set_customer_session_cookie($set) {}
    public function get_session_cookie() { return false; }
};
WC()->customer = new WC_Customer(0); WC()->customer->set_billing_country('HU');
WC()->cart = new WC_Cart(); $cart = WC()->cart;
$devices = []; $order = null; $orderId = 0;
$caseId = wc_get_product_id_by_sku('ak-protection-case-premium');
$case = wc_get_product($caseId);
$caseBefore = ['manage_stock'=>$case->get_manage_stock(), 'stock_quantity'=>$case->get_stock_quantity(), 'stock_status'=>$case->get_stock_status()];
$submit = static function(int $id, int $extra) use ($cart) {
    $_POST = ['ak_protection'=>['case'=>$extra], 'ak_protection_nonce'=>wp_create_nonce('ak_protection_' . $id)];
    wc_clear_notices();
    return apply_filters('woocommerce_add_to_cart_validation', true, $id, 1) ? $cart->add_to_cart($id, 1) : false;
};
$childKey = static function(string $parent) use ($cart): string {
    foreach ($cart->get_cart() as $key=>$line) if (($line[Association::PARENT] ?? '') === $parent) return $key;
    throw new RuntimeException('Missing associated extra');
};
try {
    foreach (['A'=>100000, 'B'=>200000] as $label=>$price) {
        $p = new WC_Product_Simple(); $p->set_name('LOCAL iPhone ' . $label);
        $p->set_status('publish'); $p->set_catalog_visibility('hidden');
        $p->set_regular_price((string)$price); $p->set_price((string)$price);
        $p->set_manage_stock(true); $p->set_stock_quantity(5);
        $p->update_meta_data('_appleklinika_device_type','iphone');
        $p->update_meta_data('_appleklinika_device_model','iphone_13_pro'); $p->save();
        $devices[$label] = $p;
    }
    $a = $submit($devices['A']->get_id(),$caseId);
    $b = $submit($devices['B']->get_id(),$caseId);
    $check(is_string($a) && is_string($b) && $a !== $b,'Two native parent device lines');
    $aCase = $childKey($a); $bCase = $childKey($b);
    $check($aCase !== $bCase && count($cart->get_cart()) === 4,'Same accessory has two distinct native cart keys');
    foreach ([$aCase=>$a,$bCase=>$b] as $key=>$parent) {
        $line=$cart->get_cart()[$key];
        $check($line['product_id']===$caseId && $line[Association::PARENT]===$parent && $line['quantity']===1,'Accessory bound to the correct parent');
        $data=wc_get_formatted_cart_item_data($line);
        $check(str_contains($data,$cart->get_cart()[$parent]['data']->get_name()),'Native cart label names its own parent');
        $cartRow=$capture(static fn()=>appleklinika_render_cart_item($key,$line));
        $summaryRow=$capture(static fn()=>appleklinika_render_checkout_summary_item($line,$cart));
        $check(str_contains($cartRow,'Készülék: '.$cart->get_cart()[$parent]['data']->get_name()),'Actual custom cart row displays the parent');
        $check(str_contains($summaryRow,'Készülék: '.$cart->get_cart()[$parent]['data']->get_name()),'Actual checkout summary row displays the parent');
    }
    $cart->calculate_totals();
    $check((float)$cart->get_cart_contents_total()===316000.0,'Native two-device total');
    // Native session serialization/restoration retains the association and distinct lines.
    $session = new WC_Cart_Session($cart); $session->set_session();
    $cart->set_cart_contents([]); $session->get_cart_from_session();
    $check($childKey($a)===$aCase && $childKey($b)===$bCase,'Native session reload preserves both links');
    $snapshot=$cart->get_cart();
    do_action('woocommerce_add_to_cart',$a,$devices['A']->get_id(),1,0,[],[]);
    $check($cart->get_cart()===$snapshot,'Replayed add handler cannot append accessories again');
    $check($submit($devices['A']->get_id(),$caseId)===$a,'Deliberate second add reuses native parent key');
    $check(count($cart->get_cart())===4 && $cart->get_cart()[$aCase]['quantity']===2 && $cart->get_cart()[$bCase]['quantity']===1,'Repeated explicit add increments only its native associated line');
    $cart->set_quantity($a,1); $cart->set_quantity($aCase,1);
    if ($isLocal) {
    $case->set_manage_stock(true); $case->set_stock_quantity(2); $case->save();
    $check(!$submit($devices['A']->get_id(),$caseId),'Native stock counts same product across different association keys');
    $check($cart->get_cart()[$a]['quantity']===1 && $cart->get_cart()[$aCase]['quantity']===1 && $cart->get_cart()[$bCase]['quantity']===1,'Stock failure restores both device groups');
    $case->set_props($caseBefore); $case->save();
    }

    $order = new WC_Order(); $order->set_status('checkout-draft'); $order->set_created_via('local-protection-association-qa');
    $order->set_currency('HUF'); $order->set_billing_country('HU');
    $cart->calculate_totals(); WC()->checkout()->create_order_line_items($order,$cart);
    $order->set_total($cart->get_cart_contents_total()); $orderId=$order->save();
    $order=wc_get_order($orderId);
    $check($orderId>0 && count($order->get_items())===4,'Real Woo draft order saves and reloads four separate items');
    $items=[]; foreach($order->get_items() as $item) $items[$item->get_meta(Association::LINE)]=$item;
    foreach ([$aCase=>$a,$bCase=>$b] as $key=>$parent) {
        $check(isset($items[$parent]) && $items[$key]->get_meta(Association::PARENT)===$parent,'Persisted order association resolves to its exact parent item');
        $check($items[$key]->get_meta(Association::LABEL)===$items[$parent]->get_name(),'Persisted public device label correct');
        $account=$capture(static fn()=>wc_get_template('order/order-details-item.php',['order'=>$order,'item_id'=>$items[$key]->get_id(),'item'=>$items[$key],'show_purchase_note'=>false,'purchase_note'=>'','product'=>$items[$key]->get_product()]));
        $check(str_contains($account,$items[$parent]->get_name()) && str_contains($account,'Készülék'),'My Account displays association on the accessory row');
        $check(!str_contains($account,Association::PARENT) && !str_contains($account,$parent),'Internal cart keys not exposed in customer item metadata');
    }
    $repo=new WooOrderBackOfficeRepository(); $bo=$repo->deviceItems($order);
    $extras=array_values(array_filter($bo,static fn($item)=>$item['product_id']===$caseId));
    $check(count($extras)===2 && $extras[0]['details']['Készülék']==='LOCAL iPhone A' && $extras[1]['details']['Készülék']==='LOCAL iPhone B','Back Office associates each premium case with its own device');
    $backoffice=$capture(static fn()=>(new ReflectionMethod(BackOfficeRouter::class,'renderDevices'))->invoke(new BackOfficeRouter($repo),$order));
    $check(substr_count($backoffice,'<dt>Készülék</dt>')===2,'Back Office actually renders the two association descriptions');
    WC()->mailer();
    $email = new \Appleklinika\BackOffice\Interfaces\PaidInvoiceEmail(); $email->set_object($order);
    $emailHtml = $email->get_content(); $emailText = $email->get_content_plain();
    $check(substr_count($emailHtml, 'Prémium tok') === 2 && substr_count($emailText, 'Prémium tok') === 2, 'Email rendering retains both ordinary accessory lines without sending');
    $cart->empty_cart(false);
    $glassId=wc_get_product_id_by_sku('ak-protection-glass-premium');
    $cameraId=wc_get_product_id_by_sku('ak-protection-camera');
    $_POST=['ak_protection'=>['glass'=>$glassId,'case'=>$caseId,'camera'=>$cameraId], 'ak_protection_nonce'=>wp_create_nonce('ak_protection_'.$devices['A']->get_id())];
    $combo=apply_filters('woocommerce_add_to_cart_validation',true,$devices['A']->get_id(),1) ? $cart->add_to_cart($devices['A']->get_id(),1) : false;
    $cart->calculate_totals();
    $check((bool)$combo && count($cart->get_cart())===4 && (float)$cart->get_cart_contents_total()===120000.0,'Premium glass/case/camera combination has four native lines and correct total');
    $cart->empty_cart(false); $a=$submit($devices['A']->get_id(),$caseId); $b=$submit($devices['B']->get_id(),$caseId);
    $aCase=$childKey($a); $bCase=$childKey($b);


    $vendor=file_get_contents(WP_PLUGIN_DIR.'/integration-for-szamlazzhu-woocommerce/index.php');
    $start=strpos($vendor,'//Product items',strpos($vendor,'//Order Items')); $end=strpos($vendor,'//Shipping',$start);
    if($start===false||$end===false)throw new RuntimeException('Vendor mapping boundaries changed.');
    $mapping=substr($vendor,$start,$end-$start);
    $xml=(function() use($order,$mapping){
        $szamla=new WCSzamlazzSimpleXMLElement('<szamla/>'); $tetelek=$szamla->addChild('tetelek');
        $vevo=new WCSzamlazzSimpleXMLElement('<vevo/>'); $order_items=$order->get_items(); $rounding=0; $invoice_line_items=[];
        eval($mapping); return $szamla;
    })->call(WC_Szamlazz());
    $check(count($xml->tetelek->tetel)===4,'Invoice preserves separate ordinary Woo lines');
    foreach(array_values($order->get_items()) as $i=>$item){
        $mapped=$xml->tetelek->tetel[$i];
        $check((string)$mapped->megnevezes===$item->get_name() && (int)(string)$mapped->mennyiseg===$item->get_quantity() && (float)(string)$mapped->bruttoErtek===(float)$item->get_total(),'Invoice names, quantities and native amounts preserved');
        if($item->get_meta(Association::PARENT)!=='') {
            $check(str_contains((string)$mapped->megjegyzes,'Készülék: '.$item->get_meta(Association::LABEL)),'Existing invoice note includes device association without pipeline change');
            $check(!str_contains((string)$mapped->megjegyzes,$item->get_meta(Association::PARENT)),'Invoice hides internal association key');
        }
    }
    $cart->remove_cart_item($aCase);
    $check(isset($cart->get_cart()[$a],$cart->get_cart()[$b],$cart->get_cart()[$bCase]) && count($cart->get_cart())===3,'Removing accessory A leaves both devices and accessory B');
    $cart->restore_cart_item($aCase);
    $check(isset($cart->get_cart()[$aCase]),'Accessory undo works while parent remains');
    $cart->remove_cart_item($a);
    $check(!isset($cart->get_cart()[$aCase]) && isset($cart->get_cart()[$b],$cart->get_cart()[$bCase]) && count($cart->get_cart())===2,'Parent removal deletes only its linked extras');
    $check((float)$cart->get_cart_contents_total()===208000.0,'Cascade removal recalculates native totals');
    $cart->restore_cart_item($aCase);
    $check(!isset($cart->get_cart()[$aCase]),'Undo cannot resurrect an orphan accessory');
    // Lost/unavailable parent during session restoration cannot leave its extras purchasable.
    $contents=$cart->get_cart(); unset($contents[$b]); $cart->set_cart_contents($contents);
    do_action('woocommerce_cart_loaded_from_session',$cart);
    $check($cart->is_empty(),'Session orphan cleanup removes only remaining linked extra');
    $item=new WC_Order_Item_Product();
    try {(new Association())->copyToOrder($item,$aCase,[Association::PARENT=>$a],$order);$check(false,'Orphan order copy blocked');}
    catch(RuntimeException $error){$check(true,'Orphan order copy blocked');}
    $adapter=wc_get_product(wc_get_product_id_by_sku('ak-protection-adapter-20w'));
    $check($adapter->get_price()==='' && !$adapter->is_purchasable(),'Adapter remains unpriced/unavailable');
    $check($http===0 && $mail===0,'No external provider or email attempt');
    $check(!$order->is_paid() && $order->get_status()==='checkout-draft','QA order remains an unpaid draft');
} finally {
    $_POST=[]; $cart->empty_cart(false);
    if($orderId>0){$cleanup=wc_get_order($orderId);if($cleanup)$cleanup->delete(true);}
    if ($isLocal) { $case->set_props($caseBefore); $case->save(); }
    foreach($devices as $device)$device->delete(true);
}
$check($orderId>0 && !wc_get_order($orderId),'QA draft and order items deleted through Woo CRUD');
foreach($devices as $device)$check(!wc_get_product($device->get_id()),'QA device deleted');
echo "Protection association guarded checks passed: {$count}; draft/device cleanup complete, no provider/email attempts.\n";
