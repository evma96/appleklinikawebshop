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

(new Appleklinika\Inventory\Interfaces\Frontend\StoreReviews())->register();

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

    $saveHandler = new Appleklinika\Inventory\Application\ProductCondition\SaveProductConditionHandler($repository, $deviceCatalogRepository);
    $modelSpecifications = new Appleklinika\Inventory\Infrastructure\WordPress\WordPressModelSpecifications();
    $modelSpecifications->installMissingProfiles();
    $deviceStore = new Appleklinika\Inventory\Infrastructure\WordPress\WooDeviceProductStore();
    $deviceService = new Appleklinika\Inventory\Application\DeviceProduct\DeviceProductService($deviceStore, $saveHandler, $modelSpecifications);
    (new Appleklinika\Inventory\Interfaces\Admin\DeviceProductEditor($deviceService, $deviceStore))->register();
    (new Appleklinika\Inventory\Interfaces\DeviceProductHooks())->register();
    (new Appleklinika\Inventory\Interfaces\DeviceOrderHooks())->register();
    $adminFields = new Appleklinika\Inventory\Interfaces\Admin\ProductConditionFields($saveHandler, $repository, $deviceCatalogRepository);
    $adminFields->register();

    $photoGuidance = new Appleklinika\Inventory\Interfaces\Admin\ProductPhotoGuidance();
    $photoGuidance->register();

    $protectionCatalog = new Appleklinika\Inventory\Infrastructure\WordPress\WooProtectionCatalog();
    $protectionSelection = new Appleklinika\Inventory\Application\Protection\SelectProtection($protectionCatalog);
    $protectionCart = new Appleklinika\Inventory\Infrastructure\WordPress\WooProtectionCart($protectionSelection);
    (new Appleklinika\Inventory\Interfaces\Frontend\ProductProtection($protectionCatalog, $protectionCart, new Appleklinika\Inventory\Infrastructure\WordPress\WooProtectionAssociation()))->register();
    (new Appleklinika\Inventory\Interfaces\Admin\ProductProtectionFields())->register();

    $frontendDisplay = new Appleklinika\Inventory\Interfaces\Frontend\ProductFrontendDisplay($repository, $deviceCatalogRepository, $modelSpecifications);
    $frontendDisplay->register();
});
