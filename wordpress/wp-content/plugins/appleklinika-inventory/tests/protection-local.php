<?php
/** Guarded LOCAL Woo integration: memory-only cart/order; disposable QA device only. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__, 4) . '/wp-load.php';
if (wp_get_environment_type() !== 'local' || getenv('AK_LOCAL_VERIFICATION') !== '1' || !defined('WP_HTTP_BLOCK_EXTERNAL') || !WP_HTTP_BLOCK_EXTERNAL) throw new RuntimeException('Guarded LOCAL only.');
use Appleklinika\Inventory\Domain\Protection\ProtectionSelection;
use Appleklinika\Inventory\Application\Protection\SelectProtection;
use Appleklinika\Inventory\Infrastructure\WordPress\{WooProtectionCatalog, WooProtectionCart};
use Appleklinika\BackOffice\Infrastructure\{WooOrderBackOfficeRepository, LifecycleEmailPresentation};
use Appleklinika\BackOffice\Interfaces\{BackOfficeRouter, PaidInvoiceEmail};
use Appleklinika\BackOffice\Domain\CustomerNotification;

$count = 0; $network = $mail = 0;
$check = static function(bool $ok, string $name) use (&$count): void { ++$count; if (!$ok) throw new RuntimeException($name); };
$capture = static function(callable $f): string { ob_start(); try { $f(); return (string) ob_get_contents(); } finally { ob_end_clean(); } };
add_filter('pre_http_request', static function() use (&$network) { ++$network; return new WP_Error('local_blocked'); }, PHP_INT_MAX);
add_filter('pre_wp_mail', static function() use (&$mail) { ++$mail; return false; }, PHP_INT_MAX);
WC()->session = new class extends WC_Session {
    public function has_session() { return false; }
    public function set_customer_session_cookie($set) {}
    public function get_session_cookie() { return false; }
};
WC()->customer = new WC_Customer(0);
WC()->customer->set_billing_country('HU');
WC()->cart = new WC_Cart();
$cart = WC()->cart;
$catalog = new WooProtectionCatalog();
$selection = new SelectProtection($catalog);
$fixture = new WC_Product_Simple();
$fixture->set_name('LOCAL protection verification device');
$fixture->set_status('publish'); $fixture->set_catalog_visibility('hidden');
$fixture->set_regular_price('100000'); $fixture->set_price('100000');
$fixture->set_manage_stock(true); $fixture->set_stock_quantity(5);
$fixture->update_meta_data('_appleklinika_device_type', 'iphone');
$fixture->update_meta_data('_appleklinika_device_model', 'iphone_13_pro');
$id = $fixture->save();
$ids = [];
foreach (['glass-standard','glass-premium','case-standard','case-premium','camera','adapter-20w'] as $sku) {
    $ids[$sku] = wc_get_product_id_by_sku('ak-protection-' . $sku);
    $check($ids[$sku] > 0, 'Explicit LOCAL setup exists: ' . $sku);
}
$case = wc_get_product($ids['case-premium']);
$originalCase = ['manage_stock'=>$case->get_manage_stock(), 'stock_quantity'=>$case->get_stock_quantity(), 'stock_status'=>$case->get_stock_status()];
$glass = wc_get_product($ids['glass-standard']);
$original = ['regular_price'=>$glass->get_regular_price(), 'sale_price'=>$glass->get_sale_price(), 'price'=>$glass->get_price(), 'manage_stock'=>$glass->get_manage_stock(), 'stock_quantity'=>$glass->get_stock_quantity(), 'stock_status'=>$glass->get_stock_status()];
$submit = static function(array $input, int $qty = 1) use ($id, $cart) {
    $_POST = ['ak_protection'=>$input, 'ak_protection_nonce'=>wp_create_nonce('ak_protection_' . $id)];
    wc_clear_notices();
    return apply_filters('woocommerce_add_to_cart_validation', true, $id, $qty) ? $cart->add_to_cart($id, $qty) : false;
};
try {
    foreach ([['glass'=>[$ids['glass-standard'],$ids['glass-premium']]], ['unknown'=>1], ['glass'=>'-1'], ['glass'=>'1.5'], ['glass'=>1,'case'=>1]] as $bad) {
        try { ProtectionSelection::parse($bad); $check(false, 'Reject malformed/conflicting choice'); } catch (InvalidArgumentException $e) { $check(true, 'Rejected malformed choice'); }
    }
    $check(ProtectionSelection::parse(['glass'=>'0','case'=>'0']) === [], 'Nothing preselected');
    $check(ProtectionSelection::eligible('iphone','iphone_13_pro',['iphone'],['iphone_13_pro']), 'Explicit model match');
    $check(!ProtectionSelection::eligible('iphone','iphone_15',['iphone'],['iphone_13_pro']), 'Different model blocked');
    $check(!ProtectionSelection::eligible('macbook','m1',['iphone'],[]), 'Wrong device type blocked');
    $options = $catalog->forDevice($id);
    $check(count($options) === 6, 'Six configured iPhone options');
    $check(!$options[$ids['adapter-20w']]['available'] && $options[$ids['adapter-20w']]['price_html'] === '', 'Adapter unavailable without invented price');
    foreach (['ipad','macbook','apple_watch'] as $type) {
        $fixture->update_meta_data('_appleklinika_device_type',$type); $fixture->save();
        $check($catalog->forDevice($id) === [], 'No irrelevant extras on ' . $type);
    }
    $fixture->update_meta_data('_appleklinika_device_type','iphone'); $fixture->save();
    foreach ([ [[],100000,1], [['glass'=>$ids['glass-standard']],104000,2], [['case'=>$ids['case-standard']],103000,2], [['glass'=>$ids['glass-premium'],'case'=>$ids['case-premium'],'camera'=>$ids['camera']],120000,4] ] as [$input,$total,$lines]) {
        $cart->empty_cart(false);
        $check((bool) $submit($input), 'Native add-to-cart succeeds'); $cart->calculate_totals();
        $check(count($cart->get_cart()) === $lines, 'Correct independent line count');
        $check((float) $cart->get_cart_contents_total() === (float) $total, 'Native line totals include only selected extras');
        foreach ($cart->get_cart() as $line) $check($line['quantity'] === 1, 'Each selected item quantity one');
    }
    $before = $cart->get_cart();
    $check(!$submit(['adapter'=>$ids['adapter-20w']]), 'Forged unavailable adapter rejected server-side');
    $check(!$submit(['case'=>$ids['glass-standard']]), 'Forged group mismatch rejected');
    $_POST['ak_protection_nonce'] = 'invalid';
    $check(!apply_filters('woocommerce_add_to_cart_validation', true, $id, 1), 'Invalid nonce rejected');
    $check(array_keys($cart->get_cart()) === array_keys($before), 'Invalid selections leave cart unchanged');
    $cart->empty_cart(false);
    $mainKey = $submit(['glass'=>$ids['glass-standard']],2); $cart->calculate_totals();
    $check($cart->get_cart()[$mainKey]['quantity'] === 2 && count($cart->get_cart()) === 2, 'Device quantity independent');
    $extraKey = array_values(array_filter(array_keys($cart->get_cart()), static fn($key) => $key !== $mainKey))[0];
    $check($cart->get_cart()[$extraKey]['quantity'] === 1, 'Extra does not multiply with device');
    $cart->set_quantity($extraKey,3); $cart->calculate_totals();
    $check((float) $cart->get_cart_contents_total() === 212000.0, 'Independent quantity uses native recalculation');
    $cart->remove_cart_item($extraKey); $cart->calculate_totals();
    $check(count($cart->get_cart()) === 1 && (float) $cart->get_cart_contents_total() === 200000.0, 'Accessory removable without device or total corruption');
    // Real Woo-editable price is the single price source (restore in finally).
    $glass->set_regular_price('4500'); $glass->set_price('4500'); $glass->save();
    $cart->empty_cart(false); $submit(['glass'=>$glass->get_id()]); $cart->calculate_totals();
    $check((float) $cart->get_cart_contents_total() === 104500.0, 'Woo price edit reaches cart without code change');
    $glass->set_regular_price('4000'); $glass->set_price('4000'); $glass->set_manage_stock(true); $glass->set_stock_quantity(1); $glass->save();
    $cart->empty_cart(false); $check((bool)$submit(['glass'=>$glass->get_id()]), 'Native managed stock permits first unit');
    $prior = $cart->get_cart();
    $check(!$submit(['glass'=>$glass->get_id()]), 'Native stock rejects excess extra');
    $check(array_map(static fn($x)=>$x['quantity'],$cart->get_cart()) === array_map(static fn($x)=>$x['quantity'],$prior), 'Failed extra atomically restores existing main and extra quantities');
    $check(wc_get_product($id)->get_stock_quantity() === 5 && wc_get_product($glass->get_id())->get_stock_quantity() === 1, 'Cart does not deduct stock');
    $glass->set_props($original); $glass->save();
    $cart->empty_cart(false);
    $case->set_manage_stock(true); $case->set_stock_quantity(1); $case->save();
    $cart->add_to_cart($case->get_id(),1);
    $prior = $cart->get_cart();
    $check(!$submit(['glass'=>$ids['glass-standard'],'case'=>$ids['case-premium']]), 'Second accessory failure is reported');
    $check(array_keys($cart->get_cart()) === array_keys($prior), 'Late failure rolls back newly added device and first extra, retains existing case');
    // Exercise Woo's logged-in persistent-cart writer without creating or changing any user.
    $savedUser = $GLOBALS['current_user'];
    $memoryUser = new WP_User(); $memoryUser->ID = 987654321;
    $persistentWrites = [];
    $interceptUserMeta = static function($result, $userId, $key, $value) use (&$persistentWrites, $memoryUser) {
        if ((int)$userId !== $memoryUser->ID) return $result;
        if ($key === '_woocommerce_persistent_cart_' . get_current_blog_id()) $persistentWrites[] = $value['cart'];
        return true; // Short-circuit every metadata write for the memory-only identity.
    };
    add_filter('update_user_metadata', $interceptUserMeta, PHP_INT_MAX, 4);
    $GLOBALS['current_user'] = $memoryUser;
    try {
        $check(!$submit(['glass'=>$ids['glass-standard'],'case'=>$ids['case-premium']]), 'Logged-in late failure is reported');
        $check(count($persistentWrites) >= 2, 'Native persistent-cart hooks actually ran for partial additions');
        $check(end($persistentWrites) === (new WC_Cart_Session($cart))->get_cart_for_session(), 'Failed request restores the logged-in persistent cart as well');
        $check(array_keys(WC()->session->get('cart', [])) === array_keys($prior), 'Failed request restores the Woo session cart');
    } finally {
        $GLOBALS['current_user'] = $savedUser;
        remove_filter('update_user_metadata', $interceptUserMeta, PHP_INT_MAX);
    }
    $case->set_props($originalCase); $case->save(); $cart->empty_cart(false);
    $chosen = ['glass'=>$ids['glass-standard'],'case'=>$ids['case-premium'],'camera'=>$ids['camera']];
    $check((bool)$submit($chosen), 'Combined selection succeeds after stock restoration'); $cart->calculate_totals();
    $snapshot = $cart->get_cart();
    do_action('woocommerce_add_to_cart', array_key_first($snapshot), $id, 1, 0, [], []);
    $check(array_map(static fn($x)=>$x['quantity'],$cart->get_cart()) === array_map(static fn($x)=>$x['quantity'],$snapshot), 'Consumed cart operation cannot append twice');
    foreach ($cart->get_cart() as $key=>$line) if ($line['product_id'] === $ids['glass-standard']) $cart->set_quantity($key,2);
    $cart->calculate_totals();
    $order = new WC_Order(); $order->set_currency('HUF'); $order->set_date_created('2026-10-26 10:00:00');
    $order->set_billing_first_name('LOCAL'); $order->set_billing_last_name('QA'); $order->set_billing_country('HU');
    $order->set_payment_method('barion'); $order->set_payment_method_title('Barion');
    WC()->checkout()->create_order_line_items($order, $cart);
    $order->set_total($cart->get_cart_contents_total());
    $check($order->get_id() === 0 && count($order->get_items()) === 4 && (float)$order->get_total() === 121000.0, 'Unsaved real Woo order retains four lines and total');
    $checkout = $capture(static fn()=>wc_get_template('checkout/review-order.php'));
    $account = $capture(static function() use($order) { foreach($order->get_items() as $itemId=>$item) wc_get_template('order/order-details-item.php',['order'=>$order,'item_id'=>$itemId,'item'=>$item,'show_purchase_note'=>false,'purchase_note'=>'','product'=>$item->get_product()]); });
    $repo = new WooOrderBackOfficeRepository(); $boItems = $repo->deviceItems($order);
    $router = new BackOfficeRouter($repo);
    $backoffice = $capture(static fn()=>(new ReflectionMethod($router,'renderDevices'))->invoke($router,$order));
    WC()->mailer();
    $email = new PaidInvoiceEmail(); $email->set_object($order);
    $emailHtml = $email->get_content(); $emailPlain = $email->get_content_plain();
    foreach ($chosen as $extraId) {
        $name = wc_get_product($extraId)->get_name();
        foreach (['checkout'=>$checkout,'My Account native detail'=>$account,'Back Office'=>$backoffice,'email HTML'=>$emailHtml,'email plain'=>$emailPlain] as $surface=>$html) $check(str_contains($html,$name), $surface . ' retains ' . $name);
    }
    $check(count($boItems)===4 && !empty($boItems[1]['total_html']), 'Back Office has item quantity and price');
    $view = (new LifecycleEmailPresentation())->forOrder($order,CustomerNotification::PAID);
    $check(count($view['items'])===4 && $view['items'][1]['quantity']===2, 'Email uses ordinary Woo item quantities');
    // Execute ONLY the installed vendor's product-line XML mapping, in memory.
    // No invoice entrypoint/authentication/transport/license override is invoked.
    $vendor = file_get_contents(WP_PLUGIN_DIR . '/integration-for-szamlazzhu-woocommerce/index.php');
    $start = strpos($vendor, '//Product items', strpos($vendor,'//Order Items'));
    $end = strpos($vendor, '//Shipping', $start);
    if ($start === false || $end === false) throw new RuntimeException('Vendor mapping boundaries changed.');
    $mapping = substr($vendor,$start,$end-$start);
    $check(!str_contains($mapping,'->generate(') && !str_contains($mapping,'wp_remote_'), 'Invoice test is bounded to item mapping only');
    $xml = (function() use($order,$mapping) {
        $szamla = new WCSzamlazzSimpleXMLElement('<szamla/>'); $tetelek = $szamla->addChild('tetelek');
        $vevo = new WCSzamlazzSimpleXMLElement('<vevo/>'); $order_items = $order->get_items(); $rounding = 0; $invoice_line_items = [];
        eval($mapping);
        return $szamla;
    })->call(WC_Szamlazz());
    $check(count($xml->tetelek->tetel)===4, 'Actual Szamlazz product mapping includes every native line');
    foreach (array_values($order->get_items()) as $i=>$item) {
        $mapped = $xml->tetelek->tetel[$i];
        $check((string)$mapped->megnevezes === $item->get_name(), 'Invoice name preserved');
        $check((int)(string)$mapped->mennyiseg === $item->get_quantity(), 'Invoice quantity preserved');
        $check((float)(string)$mapped->bruttoErtek === (float)$item->get_total(), 'Invoice amount preserved under current LOCAL no-tax configuration');
    }
    foreach (['appleklinika_homepage_latest_products','appleklinika_homepage_featured_products','appleklinika_homepage_sale_products','appleklinika_homepage_selected_products'] as $fn) {
        $shown = array_map(static fn($p)=>$p->get_id(),$fn(100));
        $check(array_intersect($ids,$shown)===[], 'Hidden extras excluded from '.$fn);
    }
    $dir = '/tmp/appleklinika-protection-preview'; if(!is_dir($dir)) mkdir($dir,0700,true);
    foreach (['checkout'=>$checkout,'account'=>$account,'backoffice'=>$backoffice,'email'=>$emailHtml,'invoice-items'=>$xml->asXML()] as $name=>$body) file_put_contents($dir.'/'.$name.($name==='invoice-items'?'.xml':'.html'),$body);
    $fixture->update_meta_data(WooProtectionCatalog::GROUP,'camera');
    $fixture->update_meta_data(WooProtectionCatalog::TYPES,['iphone']);
    $fixture->update_meta_data(WooProtectionCatalog::MODELS,[]);
    try { $fixture->save(); $check(false, 'Device cannot be silently converted into an accessory'); }
    catch (InvalidArgumentException $error) { $check(true, 'Device/accessory classification guard rejects conflicting fixture'); }
    // Reuse this disposable fixture as a genuine accessory for the existing
    // duplicate-camera-slot test; it must no longer contain device metadata.
    $fixture->delete_meta_data('_appleklinika_device_model');
    $fixture->delete_meta_data('_appleklinika_device_type');
    $fixture->save();
    $deviceIds = get_posts(['post_type'=>'product','post_status'=>'publish','numberposts'=>1,'fields'=>'ids','meta_query'=>[['key'=>'_appleklinika_device_type','value'=>'iphone'],['key'=>WooProtectionCatalog::GROUP,'compare'=>'NOT EXISTS']]]);
    $ambiguous = $catalog->forDevice((int)$deviceIds[0]);
    $check(!$ambiguous[$ids['camera']]['available'] && !$ambiguous[$id]['available'],'Ambiguous independent slot fails closed rather than dropping a selected item');
    $check($order->get_id()===0,'No QA order persisted');
    $check($network===0 && $mail===0, 'No external HTTP or email attempt');
} finally {
    $_POST = []; $cart->empty_cart(false);
    $glass->set_props($original); $glass->save();
    $case->set_props($originalCase); $case->save();
    $fixture->delete(true);
}
echo "Protection LOCAL checks passed: {$count} assertions; memory-only order/session, QA device deleted; no provider/email calls.\n";
