<?php
/** Native Woo Store API interleaving reproduction and draft repair; LOCAL, offline. */
declare(strict_types=1);
require dirname(__DIR__, 4) . '/wp-load.php';
if (PHP_SAPI !== 'cli' || wp_get_environment_type() !== 'local' || getenv('AK_LOCAL_VERIFICATION') !== '1') throw new RuntimeException('LOCAL only');
use Appleklinika\Inventory\Infrastructure\WordPress\WooProtectionAssociation as A;
use Appleklinika\Inventory\Application\Checkout\ReconcileProtectionDraft;
use Appleklinika\BackOffice\Infrastructure\WooOrderBackOfficeRepository;
$count = $http = $mail = 0;
$check = static function(bool $ok, string $label) use (&$count) { ++$count; if (!$ok) throw new RuntimeException($label); };
add_filter('pre_http_request', static function() use (&$http) { ++$http; return new WP_Error('blocked'); }, PHP_INT_MAX);
add_filter('pre_wp_mail', static function() use (&$mail) { ++$mail; return false; }, PHP_INT_MAX);
WC()->session = new class extends WC_Session { public function has_session(){return false;} public function set_customer_session_cookie($s){} public function get_session_cookie(){return false;} };
WC()->customer = new WC_Customer(0); WC()->customer->set_billing_country('HU'); WC()->customer->set_shipping_country('HU');
WC()->cart = new WC_Cart(); $cart = WC()->cart; $products = []; $orders = [];
$controller = new Automattic\WooCommerce\StoreApi\Utilities\OrderController(); $repair = new ReconcileProtectionDraft();
$extra = wc_get_product_id_by_sku('ak-protection-case-premium');
$fixture = static function(int $price) use (&$products): WC_Product {
    $p = new WC_Product_Simple(); $p->set_name('LOCAL draft device ' . $price); $p->set_regular_price((string)$price);
    $p->set_status('publish'); $p->set_catalog_visibility('hidden'); $p->set_manage_stock(true); $p->set_stock_quantity(3); $p->save();
    $products[] = $p; return $p;
};
$assert = static function(WC_Order $o) use ($cart,$check) {
    $o = wc_get_order($o->get_id());
    $check(count($o->get_items()) === count($cart->get_cart()), 'Cart and persisted draft line count');
    $expected=[]; foreach($cart->get_cart() as $key=>$line) $expected[$key]=$line;
    foreach($o->get_items() as $i) {
        $key=$i->get_meta(A::LINE);$line=$expected[$key]??null;
        $check($line && $i->get_product_id()===$line['product_id'] && $i->get_quantity()===$line['quantity'] && $i->get_meta(A::PARENT)===($line[A::PARENT]??''),'Exact parent/key/product/quantity');
    }
    $check(abs((float)$o->get_total()-(float)$cart->get_total('edit'))<0.011,'Native cart and order grand total');
    $check(count((new WooOrderBackOfficeRepository())->deviceItems($o))===count($cart->get_cart()),'Back Office line count');
    $check(!$o->get_date_paid() && !$o->get_transaction_id() && !$o->get_meta('_order_stock_reduced'),'No payment or stock side effect');
};
try {
    $p=$fixture(100000); $parent=$cart->add_to_cart($p->get_id());
    $o=$controller->create_order_from_cart();$orders[]=$o->get_id();
    $child=$cart->add_to_cart($extra,1,0,[],[A::PARENT=>$parent]);$cart->calculate_totals();
    $a=wc_get_order($o->get_id());$b=wc_get_order($o->get_id());$ran=false;
    // A pauses after its first saved line, B rebuilds the same draft, A saves its remaining case.
    // The real Store API request mutex prevents this interleaving; direct controller calls reproduce core behavior.
    $hook=static function($id,$item,$orderId)use(&$ran,$p,$o,$controller,$b){
        if($ran||$orderId!==$o->get_id()||!$item instanceof WC_Order_Item_Product||$item->get_product_id()!==$p->get_id())return;
        $ran=true;$controller->update_order_from_cart($b);
    };
    add_action('woocommerce_new_order_item',$hook,99,3);$controller->update_order_from_cart($a);remove_action('woocommerce_new_order_item',$hook,99);
    $bad=wc_get_order($o->get_id());$check(count($bad->get_items())===3,'Native interleaved rebuild reproduces one device plus two identical cases');
    $controller->update_order_from_cart($bad);$bad=wc_get_order($o->get_id());
    $check(count($bad->get_items())===3,'Matching cart hash skips native repair');
    do_action('woocommerce_store_api_checkout_update_order_meta',$bad);$assert($bad);
    $check(!$repair->execute(wc_get_order($o->get_id()),$cart),'Repair repeat is no-op');
    $ids=array_keys(wc_get_order($o->get_id())->get_items());
    foreach(['local_pickup:2','ak_mpl_home:3','gls_shipping_method:0','local_pickup:2']as$shipping){
        WC()->session->set('chosen_shipping_methods',[$shipping]);WC()->session->set('chosen_payment_method','barion');
        $fresh=wc_get_order($o->get_id());$controller->update_order_from_cart($fresh);do_action('woocommerce_store_api_checkout_update_order_meta',$fresh);$assert($fresh);
    }
    $session=new WC_Cart_Session($cart);$session->set_session();$cart->set_cart_contents([]);$session->get_cart_from_session();
    $controller->update_order_from_cart($fresh);do_action('woocommerce_store_api_checkout_update_order_meta',$fresh);$assert($fresh);
    $second=$fixture(200000);$parentB=$cart->add_to_cart($second->get_id());$childB=$cart->add_to_cart($extra,2,0,[],[A::PARENT=>$parentB]);
    $fresh=wc_get_order($o->get_id());$controller->update_order_from_cart($fresh);do_action('woocommerce_store_api_checkout_update_order_meta',$fresh);$assert($fresh);
    $check($child!==$childB && count($fresh->get_items())===4,'Two devices with same case remain separate; intentional quantity 2 retained');
    $cart->remove_cart_item($child);$fresh=wc_get_order($o->get_id());$controller->update_order_from_cart($fresh);do_action('woocommerce_store_api_checkout_update_order_meta',$fresh);
    $check(count($fresh->get_items())===3 && isset($cart->get_cart()[$parent]),'Accessory removal does not remove parent');
    $cart->restore_cart_item($child);$fresh=wc_get_order($o->get_id());$controller->update_order_from_cart($fresh);do_action('woocommerce_store_api_checkout_update_order_meta',$fresh);$assert($fresh);
    $cart->remove_cart_item($parent);$fresh=wc_get_order($o->get_id());$controller->update_order_from_cart($fresh);do_action('woocommerce_store_api_checkout_update_order_meta',$fresh);$assert($fresh);
    $check(count($fresh->get_items())===2 && isset($cart->get_cart()[$parentB],$cart->get_cart()[$childB]),'Only removed parent extras cascade');
    $cart->restore_cart_item($child);$check(!isset($cart->get_cart()[$child]),'No orphan via undo');
    $freshOrder=$controller->create_order_from_cart();$orders[]=$freshOrder->get_id();$assert($freshOrder);
    $originalItems=array_keys($freshOrder->get_items());
    // Read-only protection checks: an in-memory historical status must never be reconciled.
    foreach(['pending','processing','completed','refunded']as$status){$freshOrder->set_status($status);$check(!$repair->execute($freshOrder,$cart),'Historical status untouched: '.$status);}
    $freshOrder->set_status('checkout-draft');$freshOrder->set_transaction_id('local-never-paid');$check(!$repair->execute($freshOrder,$cart),'Transaction-bearing draft untouched');
    foreach($products as$p)$check(wc_get_product($p->get_id())->get_stock_quantity()===3,'Stock unchanged');
    $check($http===0&&$mail===0,'No provider/mail attempt');
} finally {
    if(isset($hook))remove_action('woocommerce_new_order_item',$hook,99);
    $cart->empty_cart(false);foreach($orders as$id){$o=wc_get_order($id);if($o)$o->delete(true);}foreach($products as$p)$p->delete(true);
}
$check(!array_filter($orders,'wc_get_order'),'Native QA cleanup');
echo "Protection draft checks passed: {$count}; interleaving reproduced, repaired, repeat-safe; QA cleaned.\n";
