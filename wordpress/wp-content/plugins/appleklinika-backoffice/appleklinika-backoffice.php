<?php
/**
 * Plugin Name: Appleklinika Back Office
 * Description: Private WooCommerce order-fulfilment workspace for Apple Klinika employees.
 * Version: 0.1.0
 * Author: Appleklinika
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

spl_autoload_register(static function (string $className): void {
    $prefix = 'Appleklinika\\BackOffice\\';

    if (strpos($className, $prefix) !== 0) {
        return;
    }

    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($className, strlen($prefix))) . '.php';

    if (is_readable($file)) {
        require_once $file;
    }
});

register_activation_hook(__FILE__, static function (): void {
    $administrator = get_role('administrator');
    if ($administrator !== null) {
        $administrator->add_cap(Appleklinika\BackOffice\Interfaces\BackOfficeRouter::CAPABILITY);
    }

    Appleklinika\BackOffice\Interfaces\BackOfficeRouter::registerRewriteRules();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, static function (): void {
    flush_rewrite_rules();
});

add_action('plugins_loaded', static function (): void {
    Appleklinika\BackOffice\Infrastructure\DedicatedHostUrls::register();

    if (! class_exists('WooCommerce')) {
        return;
    }

    $orders = new Appleklinika\BackOffice\Infrastructure\WooOrderBackOfficeRepository();
    $mutex = new Appleklinika\BackOffice\Infrastructure\WooOrderMutex();
    $change = new Appleklinika\BackOffice\Application\ChangeFulfilment(new Appleklinika\BackOffice\Infrastructure\WooFulfilmentStore($orders), $mutex);
    $router = new Appleklinika\BackOffice\Interfaces\BackOfficeRouter($orders, $change);
    $router->register();
    if (Appleklinika\BackOffice\Infrastructure\LifecycleConfiguration::isTestEnvironment()) {
        (new Appleklinika\BackOffice\Interfaces\FulfilmentAdmin($change, $orders))->register();
        (new Appleklinika\BackOffice\Interfaces\CashPickupHooks())->register();
        (new Appleklinika\BackOffice\Interfaces\CustomerInvoiceHooks())->register();
        $store = new Appleklinika\BackOffice\Infrastructure\WooOrderLifecycleStore();
        $lifecycle = new Appleklinika\BackOffice\Application\OrderLifecycle($store, $mutex,
            new Appleklinika\BackOffice\Infrastructure\SzamlazzAutomation(), new Appleklinika\BackOffice\Infrastructure\WooCustomerMailer());
        (new Appleklinika\BackOffice\Interfaces\LifecycleHooks($lifecycle, $store))->register();
    }
});
