<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function get_option($name, $default = []) { return $GLOBALS['settings'] ?? $default; }
require_once dirname(__DIR__, 2) . '/gls-shipping-for-woocommerce/includes/helpers/class-gls-shipping-account-helper.php';
$order = new class {
    public array $meta = [];
    public function get_meta($key, $single = true) { return $this->meta[$key] ?? ''; }
};
$GLOBALS['settings'] = ['country' => 'HU'];
$n = 0;
$assert = static function ($ok, $why) use (&$n) { ++$n; if (!$ok) { throw new RuntimeException($why); } };
$order->meta = ['_gls_tracking_codes' => ['123', '456', '123', '', ['bad'], 'https://evil.example'], '_gls_tracking_code' => '999'];
$links = GLS_Shipping_Account_Helper::get_order_tracking_links($order);
$assert(array_column($links, 'code') === ['123', '456'], 'Current array is authoritative; malformed and duplicate values are removed.');
$assert($links[1]['url'] === 'https://gls-group.eu/HU/en/parcel-tracking/?match=456', 'Validated country and number produce a working GLS link.');
$order->meta['_gls_tracking_codes'] = [];
$assert(GLS_Shipping_Account_Helper::get_order_tracking_links($order)[0]['code'] === '999', 'Legacy fallback works for old orders.');
$GLOBALS['settings'] = ['country' => '../../evil'];
$assert(GLS_Shipping_Account_Helper::get_order_tracking_links($order) === [], 'Invalid country cannot produce a link.');
$GLOBALS['settings'] = ['account_mode' => 'multiple', 'country' => 'HR', 'gls_accounts_grid' => [['active' => true, 'country' => 'HU', 'client_id' => 'fixture', 'username' => 'fixture', 'password' => 'fixture']]];
$assert(str_contains(GLS_Shipping_Account_Helper::get_order_tracking_links($order)[0]['url'], '/HU/'), 'Multi-account country selection matches provider export.');
echo "GLS lifecycle tracking: $n assertions passed; no provider calls.\n";
