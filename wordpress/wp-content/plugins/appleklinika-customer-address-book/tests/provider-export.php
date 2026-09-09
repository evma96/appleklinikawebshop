<?php
/** Local integration regression: provider previews only, no payment/parcel/invoice request. */
require '/var/www/html/wp-load.php';
if (parse_url(get_option('siteurl'), PHP_URL_HOST) !== 'localhost') throw new RuntimeException('LOCAL fixture only');
add_filter('pre_http_request', static fn () => new WP_Error('test_no_network', 'External requests disabled'), PHP_INT_MAX);
require_once dirname(__DIR__, 2).'/gls-shipping-for-woocommerce/includes/api/class-gls-shipping-api-data.php';
$count = 0;
$failed = [];
$check = static function (bool $ok, string $message) use (&$count, &$failed): void {
    ++$count;
    if (!$ok) $failed[] = $message;
};
$order = wc_create_order();
$orderId = $order->get_id();
$product = new WC_Product_Simple();
try {
    $product->set_name('QA provider mapping fixture');
    $product->set_regular_price('1000');
    $product->set_status('private');
    $product->save();
    $order->add_product($product, 1);
    $order->calculate_totals(false);
    $order->set_address(['company'=>'Mapping QA Kft.', 'first_name'=>'', 'last_name'=>'', 'address_1'=>'Tisza Lajos körút', 'address_2'=>'A épület', 'city'=>'Szeged', 'postcode'=>'6720', 'country'=>'HU', 'email'=>'qa-provider-mapping@example.test', 'phone'=>'+36301234567'], 'billing');
    $order->set_address(['first_name'=>'Elek', 'last_name'=>'Teszt', 'address_1'=>'Másik utca', 'address_2'=>'B épület', 'city'=>'Szeged', 'postcode'=>'6720', 'country'=>'HU', 'phone'=>'+36301234567'], 'shipping');
    $order->update_meta_data('appleklinika_tax_number', '12345676-2-42');
    $order->update_meta_data('appleklinika_company_purchase', '1');
    $order->update_meta_data('_wc_billing/appleklinika/house_number', '10');
    $order->update_meta_data('_wc_shipping/appleklinika/house_number', '12');
    $order->save();
    $before = serialize(wc_get_order($order->get_id())->get_data());
    $result = WC_Szamlazz()->generate_invoice($order->get_id(), 'invoice', ['preview'=>true]);
    if (empty($result['xml'])) throw new RuntimeException('Preview unavailable: '.json_encode($result['messages'] ?? $result['error'] ?? null));
    $xml = simplexml_load_string($result['xml']);
    $check((string)$xml->vevo->adoszam === '12345676-2-42', 'Szamlazz company tax number');
    $check((string)$xml->vevo->cim === 'Tisza Lajos körút 10 A épület', 'Szamlazz full billing address');
    $check((string)$xml->vevo->nev === 'Mapping QA Kft.', 'Szamlazz company retained');
    $gls = new GLS_Shipping_API_Data($order->get_id());
    $delivery = $gls->get_delivery_address($order);
    $check($delivery['Street'] === 'Másik utca 12 B épület', 'GLS full shipping address, not billing');
    $check($delivery['ContactPhone'] === '+36301234567' && $delivery['ZipCode'] === '6720', 'GLS contact/postcode retained');
    $check($delivery === $gls->get_delivery_address($order), 'Repeated mapping does not duplicate house number');
    $check(serialize(wc_get_order($order->get_id())->get_data()) === $before, 'Stored order data unchanged');
    $order->delete_meta_data('_wc_billing/appleklinika/house_number');
    $order->delete_meta_data('_wc_shipping/appleklinika/house_number');
    $order->delete_meta_data('appleklinika_tax_number');
    $order->save();
    $result = WC_Szamlazz()->generate_invoice($order->get_id(), 'invoice', ['preview'=>true]);
    $xml = simplexml_load_string($result['xml']);
    $check((string)$xml->vevo->cim === 'Tisza Lajos körút A épület', 'Legacy address remains unchanged');
    $check((string)$xml->vevo->adoszam === '', 'Absent tax is not invented');
    $check($gls->get_delivery_address($order)['Street'] === 'Másik utca B épület', 'Legacy GLS address remains unchanged');
} finally {
    $order->delete(true);
    if ($product->get_id()) $product->delete(true);
}
echo json_encode(['assertions'=>$count, 'failed'=>$failed, 'fixture_removed'=>!wc_get_order($orderId)], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), PHP_EOL;
exit($failed ? 1 : 0);
