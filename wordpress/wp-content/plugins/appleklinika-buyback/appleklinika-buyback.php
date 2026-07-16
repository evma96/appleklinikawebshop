<?php
/**
 * Plugin Name: Apple Klinika Buyback
 * Description: Persistence and diagnostics foundation for the Apple Klinika buyback system.
 * Version: 0.7.0
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Author: Apple Klinika
 * Text Domain: appleklinika-buyback
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('APPLEKLINIKA_BUYBACK_VERSION', '0.7.0');
define('APPLEKLINIKA_BUYBACK_SCHEMA_VERSION', '1.1.0');
define('APPLEKLINIKA_BUYBACK_MINIMUM_PHP_VERSION', '8.1');
define('APPLEKLINIKA_BUYBACK_FILE', __FILE__);
define('APPLEKLINIKA_BUYBACK_PATH', __DIR__);
define('APPLEKLINIKA_BUYBACK_URL', plugin_dir_url(__FILE__));

if (version_compare(PHP_VERSION, APPLEKLINIKA_BUYBACK_MINIMUM_PHP_VERSION, '<')) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>';
        echo esc_html(sprintf(
            'Apple Klinika Buyback requires PHP %s or newer.',
            APPLEKLINIKA_BUYBACK_MINIMUM_PHP_VERSION
        ));
        echo '</p></div>';
    });

    return;
}

spl_autoload_register(static function (string $className): void {
    $prefix = 'AppleKlinika\\Buyback\\';

    if (strpos($className, $prefix) !== 0) {
        return;
    }

    $relativeClass = substr($className, strlen($prefix));
    $file = APPLEKLINIKA_BUYBACK_PATH . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_readable($file)) {
        require_once $file;
    }
});

register_activation_hook(
    APPLEKLINIKA_BUYBACK_FILE,
    [AppleKlinika\Buyback\Infrastructure\WordPress\Activator::class, 'activate']
);

register_deactivation_hook(
    APPLEKLINIKA_BUYBACK_FILE,
    [AppleKlinika\Buyback\Infrastructure\WordPress\Deactivator::class, 'deactivate']
);

add_action('plugins_loaded', static function (): void {
    if (! AppleKlinika\Buyback\Infrastructure\WordPress\Requirements::isWooCommerceAvailable()) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>';
            echo esc_html('Apple Klinika Buyback requires WooCommerce to be active.');
            echo '</p></div>';
        });

        return;
    }

    AppleKlinika\Buyback\Infrastructure\WordPress\Plugin::create()->register();
});
