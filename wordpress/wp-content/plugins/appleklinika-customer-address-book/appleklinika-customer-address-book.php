<?php
/**
 * Plugin Name: Apple Klinika Customer Address Book
 * Description: Canonical customer address book and WooCommerce account integration.
 * Version: 0.1.0
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Author: Apple Klinika
 * Text Domain: appleklinika-customer-address-book
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('APPLEKLINIKA_ADDRESS_BOOK_VERSION', '0.1.0');
define('APPLEKLINIKA_ADDRESS_BOOK_SCHEMA_VERSION', '1');
define('APPLEKLINIKA_ADDRESS_BOOK_FILE', __FILE__);
define('APPLEKLINIKA_ADDRESS_BOOK_PATH', __DIR__);
define('APPLEKLINIKA_ADDRESS_BOOK_URL', plugin_dir_url(__FILE__));

spl_autoload_register(static function (string $className): void {
    $prefix = 'AppleKlinika\\CustomerAddressBook\\';

    if (! str_starts_with($className, $prefix)) {
        return;
    }

    $file = APPLEKLINIKA_ADDRESS_BOOK_PATH . '/src/' . str_replace('\\', '/', substr($className, strlen($prefix))) . '.php';
    if (is_readable($file)) {
        require_once $file;
    }
});

register_activation_hook(
    APPLEKLINIKA_ADDRESS_BOOK_FILE,
    [AppleKlinika\CustomerAddressBook\Infrastructure\WordPress\Activator::class, 'activate']
);

add_action('before_woocommerce_init', static function (): void {
    if (class_exists(Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            APPLEKLINIKA_ADDRESS_BOOK_FILE,
            true
        );
    }
});

add_action('plugins_loaded', static function (): void {
    if (! AppleKlinika\CustomerAddressBook\Infrastructure\WordPress\Requirements::isSatisfied()) {
        add_action('admin_notices', [AppleKlinika\CustomerAddressBook\Infrastructure\WordPress\Requirements::class, 'renderNotice']);
        return;
    }

    AppleKlinika\CustomerAddressBook\Infrastructure\WordPress\Plugin::create()->register();
});
