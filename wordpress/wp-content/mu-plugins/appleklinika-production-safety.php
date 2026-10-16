<?php
/** Plugin Name: Apple Klinika Production Safety — mandatory fail-closed bootstrap */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }
// No TEST/LOCAL behavior change. This file must remain installed for production.
if (wp_get_environment_type() !== 'production') { return; }
$base = WP_PLUGIN_DIR . '/appleklinika-backoffice/src/';
$files = ['Domain/ProductionActivation.php','Infrastructure/ProductionReadiness.php',
    'Infrastructure/ProductionTransportGuard.php','Interfaces/ProductionHooks.php'];
foreach ($files as $file) {
    if (!is_readable($base . $file)) {
        add_filter('pre_wp_mail', static fn() => false, PHP_INT_MAX);
        add_filter('woocommerce_available_payment_gateways', static fn() => [], PHP_INT_MAX);
        add_filter('pre_http_request', static fn() => new WP_Error('appleklinika_guard_missing','Production safety module is missing.'),PHP_INT_MAX);
        add_action('woocommerce_pre_payment_complete', static function() { throw new RuntimeException('Production safety module is missing.'); },-9999);
        add_filter('woocommerce_can_reduce_order_stock', static fn() => false,PHP_INT_MAX);
        return;
    }
}
foreach ($files as $file) { require_once $base . $file; }
define('APPLEKLINIKA_PRODUCTION_SAFETY_LOADED',true);
(new Appleklinika\BackOffice\Interfaces\ProductionHooks())->register();
