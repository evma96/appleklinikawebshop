<?php
/**
 * Plugin Name: Appleklinika Inventory
 * Description: Custom WooCommerce business logic for unique used smartphone products.
 * Version: 0.1.0
 * Author: Appleklinika
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

spl_autoload_register(static function (string $className): void {
    $prefix = 'Appleklinika\\Inventory\\';

    if (strpos($className, $prefix) !== 0) {
        return;
    }

    $relativeClass = substr($className, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_readable($file)) {
        require_once $file;
    }
});

add_action('init', static function (): void {
    $deviceCatalogRepository = new Appleklinika\Inventory\Infrastructure\WordPress\DeviceCatalogRepository();
    $deviceCatalogPage = new Appleklinika\Inventory\Interfaces\Admin\DeviceCatalogPage($deviceCatalogRepository);
    $deviceCatalogPage->register();

    if (! did_action('woocommerce_loaded')) {
        return;
    }

    $repository = new Appleklinika\Inventory\Infrastructure\WordPress\WooProductConditionRepository();
    $selectorDemoProductsSeeder = new Appleklinika\Inventory\Infrastructure\WordPress\SelectorDemoProductsSeeder($repository);
    $selectorDemoProductsSeeder->register();

    $saveHandler = new Appleklinika\Inventory\Application\ProductCondition\SaveProductConditionHandler($repository);
    $adminFields = new Appleklinika\Inventory\Interfaces\Admin\ProductConditionFields($saveHandler, $repository, $deviceCatalogRepository);
    $adminFields->register();

    $photoGuidance = new Appleklinika\Inventory\Interfaces\Admin\ProductPhotoGuidance();
    $photoGuidance->register();

    $frontendDisplay = new Appleklinika\Inventory\Interfaces\Frontend\ProductFrontendDisplay($repository, $deviceCatalogRepository);
    $frontendDisplay->register();
});
