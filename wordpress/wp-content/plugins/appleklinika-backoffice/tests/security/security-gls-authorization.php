<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('ABSPATH', '/unused/');
function add_action(...$args) {}
function add_filter(...$args) {}
class JsonResult extends Error {
    public function __construct(public bool $success, public int $status) { parent::__construct('HTTP result'); }
}
function wp_send_json_error($data, $status = 200) { throw new JsonResult(false, $status); }
function wp_send_json_success($data, $status = 200) { throw new JsonResult(true, $status); }
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid-fixture-nonce' && $action === 'import-nonce'; }
function sanitize_text_field($value) { return trim($value); }
function wp_unslash($value) { return $value; }
function absint($value) { return abs((int) $value); }
function current_user_can($cap, ...$args) { return $GLOBALS['caps'][$cap] ?? false; }
function __($text, $domain = '') { return $text; }
class GLS_Shipping_API_Service {
    public function get_parcel_status($code) { ++$GLOBALS['providerCalls']; return []; }
}
class FixtureOrder {
    public function update_meta_data(...$args) { ++$GLOBALS['writes']; }
    public function save() { ++$GLOBALS['writes']; }
    public function add_order_note($note) { ++$GLOBALS['writes']; }
}
function wc_get_order($id) { ++$GLOBALS['lookups']; return new FixtureOrder(); }
require dirname(__DIR__, 3) . '/gls-shipping-for-woocommerce/includes/admin/class-gls-shipping-order.php';
class FixtureGLS extends GLS_Shipping_Order {
    public function __construct() {}
    public function generate_single_order_label($id, $count = null, $position = null, $reference = null, $services = null) {
        ++$GLOBALS['providerCalls']; return ['success' => true];
    }
}
$assertions = 0;
$assert = static function($ok, $why) use (&$assertions) { ++$assertions; if (!$ok) { throw new RuntimeException($why); } };
foreach (['generate_label_and_tracking_number', 'get_parcel_status', 'update_pickup_location'] as $method) {
    foreach (['customer', 'staff-without-order', 'invalid-nonce', 'missing-order', 'valid-staff'] as $scenario) {
        $GLOBALS['providerCalls'] = $GLOBALS['writes'] = $GLOBALS['lookups'] = 0;
        $GLOBALS['caps'] = ['edit_shop_orders' => $scenario !== 'customer', 'edit_shop_order' => !in_array($scenario, ['customer', 'staff-without-order'], true)];
        $_POST = ['postNonce' => $scenario === 'invalid-nonce' ? 'invalid' : 'valid-fixture-nonce', 'orderId' => $scenario === 'missing-order' ? 0 : 42, 'parcelNumber' => '123456789', 'pickupInfo' => '{"name":"Fixture","id":"fixture"}'];
        try { (new FixtureGLS())->$method(); throw new RuntimeException('No HTTP result'); }
        catch (JsonResult $result) {
            $allowed = $scenario === 'valid-staff';
            $assert($result->success === $allowed, "$method/$scenario authorization");
            $assert($result->status === ($allowed ? 200 : 403), "$method/$scenario status");
            if (!$allowed) {
                $assert($GLOBALS['providerCalls'] === 0 && $GLOBALS['writes'] === 0 && $GLOBALS['lookups'] === 0, "$method/$scenario must deny before provider/order access");
            } else {
                $assert($method === 'update_pickup_location' ? $GLOBALS['writes'] > 0 : $GLOBALS['providerCalls'] === 1, "$method remains operational with stubbed provider");
            }
        }
    }
}
echo "GLS authorization: $assertions assertions passed; providers stubbed.\n";
