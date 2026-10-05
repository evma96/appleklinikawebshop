<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Load the actual small theme functions, without booting WordPress or a database.
$source = file_get_contents(dirname(__DIR__, 4) . '/themes/appleklinika-theme/functions.php');
foreach (['appleklinika_filter_order_confirmation_address_block', 'appleklinika_order_confirmation_tax_number_html'] as $name) {
    $start = strpos($source, 'function ' . $name . '(');
    $end = strpos($source, "\n}\n", $start) + 3;
    eval(substr($source, $start, $end - $start));
}
class WC_Order {
    public function __construct(public int $customer) {}
    public function get_customer_id() { return $this->customer; }
    public function key_is_valid($key) { return $key === 'fixture-order-key'; }
    public function get_meta($key, $single) { ++$GLOBALS['metadataReads']; return 'fixture-tax'; }
}
function absint($value) { return abs((int) $value); }
function get_query_var($name) { return 42; }
function wc_get_order($id) { return $GLOBALS['order']; }
function current_user_can($cap, ...$args) { return $GLOBALS['caps'][$cap] ?? false; }
function get_current_user_id() { return $GLOBALS['user']; }
function wc_clean($value) { return $value; }
function wp_unslash($value) { return $value; }
function esc_html($value) { return htmlspecialchars($value); }
function appleklinika_checkout_address_detail_fields() { return []; }
$n = 0;
$assert = static function($ok, $why) use (&$n) { ++$n; if (!$ok) { throw new RuntimeException($why); } };
foreach (['anonymous', 'wrong-customer', 'owner', 'staff', 'guest-authorized', 'guest-without-key', 'guest-invalid-key', 'missing-order'] as $case) {
    $GLOBALS['user'] = $case === 'owner' ? 7 : ($case === 'wrong-customer' ? 8 : 0);
    $GLOBALS['order'] = $case === 'missing-order' ? false : new WC_Order(str_starts_with($case, 'guest') ? 0 : 7);
    $GLOBALS['caps'] = ['view_order' => $case === 'owner', 'edit_shop_orders' => $case === 'staff', 'edit_shop_order' => $case === 'staff'];
    $_GET = $case === 'guest-without-key' ? [] : ['key' => $case === 'guest-invalid-key' ? 'wrong' : 'fixture-order-key'];
    $GLOBALS['metadataReads'] = 0;
    $allowed = in_array($case, ['owner','staff','guest-authorized'], true);
    $html = appleklinika_order_confirmation_tax_number_html(true);
    $assert(str_contains($html, 'fixture-tax') === $allowed, "$case tax authorization");
    $assert($GLOBALS['metadataReads'] === ($allowed ? 1 : 0), "$case denies before reading private metadata");
    $empty = appleklinika_filter_order_confirmation_address_block('', ['blockName' => 'woocommerce/order-confirmation-billing-address']);
    $assert($empty === '', "$case must never append data when Woo denied the address block");
    if (str_starts_with($case, 'guest')) {
        $assert(appleklinika_order_confirmation_tax_number_html() === '', 'Guest needs Woo verification, not just an order key');
    }
}
echo "Order tax authorization: $n assertions passed; no database or network.\n";
