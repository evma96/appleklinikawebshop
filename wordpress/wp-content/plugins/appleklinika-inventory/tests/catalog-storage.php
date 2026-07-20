<?php

declare(strict_types=1);

$wordpressRoot = dirname(__DIR__, 4);
require_once $wordpressRoot . '/wp-load.php';

use Appleklinika\Inventory\Application\ProductCondition\SaveProductConditionCommand;
use Appleklinika\Inventory\Application\ProductCondition\SaveProductConditionHandler;
use Appleklinika\Inventory\Domain\ProductCondition\StorageCapacityCatalog;
use Appleklinika\Inventory\Infrastructure\WordPress\DeviceCatalogRepository;
use Appleklinika\Inventory\Infrastructure\WordPress\WooProductConditionRepository;

final class InventoryCatalogStorageTest
{
    private int $assertions = 0;

    /** @var list<string> */
    private array $failures = [];

    public function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (! $condition) {
            $this->failures[] = $message;
        }
    }

    public function finish(): never
    {
        if ($this->failures !== []) {
            foreach ($this->failures as $failure) {
                fwrite(STDERR, "FAIL: {$failure}\n");
            }
            exit(1);
        }

        echo "Inventory model/storage tests passed: {$this->assertions} assertions.\n";
        exit(0);
    }
}

$test = new InventoryCatalogStorageTest();
$repository = new DeviceCatalogRepository();
$catalog = $repository->all();
$iPhones = array_values(array_filter($catalog, static fn (array $device): bool => $device['type'] === 'iphone'));

$test->assert(count($iPhones) === 34, 'The inventory catalogue contains all canonical iPhone models.');
$configurationCount = 0;
foreach ($iPhones as $device) {
    $keys = $device['storage_capacity_keys'];
    $test->assert($keys !== [], $device['key'] . ' has a non-empty model-specific storage list.');
    $test->assert(count($keys) === count(array_unique($keys)), $device['key'] . ' has no duplicate storage key.');
    foreach ($keys as $key) {
        $test->assert(array_key_exists($key, StorageCapacityCatalog::options()), $device['key'] . ' uses an inventory vocabulary key.');
    }
    $configurationCount += count($keys);
}
$test->assert($configurationCount === 107, 'The canonical model-specific storage total is 107.');

$byKey = [];
foreach ($iPhones as $device) {
    $byKey[$device['key']] = $device['storage_capacity_keys'];
}
$test->assert($byKey['iphone_xr'] !== $byKey['iphone_17'] && $byKey['iphone_17_pro_max'] === ['256_gb', '512_gb', '1_tb', '2_tb'], 'Representative model storage sets differ and retain their exact canonical values.');

$productId = wp_insert_post([
    'post_type' => 'product',
    'post_status' => 'draft',
    'post_title' => 'Temporary inventory storage validation test',
]);

try {
    $test->assert($productId > 0, 'A temporary product fixture was created.');
    if ($productId > 0) {
        $conditions = new WooProductConditionRepository();
        $handler = new SaveProductConditionHandler($conditions, $repository);
        $handler->handle(new SaveProductConditionCommand($productId, [
            'device_type' => 'iphone',
            'device_model' => 'iphone_17',
            'storage_capacity' => '256_gb',
        ]));
        $test->assert($conditions->get($productId, 'storage_capacity') === '256_gb', 'A valid iPhone model/storage pair is saved.');

        $handler->handle(new SaveProductConditionCommand($productId, [
            'device_type' => 'iphone',
            'device_model' => 'iphone_17',
            'storage_capacity' => '128_gb',
        ]));
        $test->assert($conditions->get($productId, 'storage_capacity') === '', 'A storage key valid for another model is rejected server-side.');

        $handler->handle(new SaveProductConditionCommand($productId, [
            'device_type' => 'iphone',
            'device_model' => 'unknown_iphone',
            'storage_capacity' => '256_gb',
        ]));
        $test->assert($conditions->get($productId, 'storage_capacity') === '', 'An unknown iPhone model/storage pair is rejected server-side.');
        $test->assert($conditions->get($productId, 'device_model') === '', 'An unknown iPhone model key is rejected server-side.');

        $handler->handle(new SaveProductConditionCommand($productId, [
            'device_type' => 'ipad',
            'device_model' => 'ipad_7th_generation',
            'storage_capacity' => '256_gb',
        ]));
        $test->assert($conditions->get($productId, 'device_model') === 'ipad_7th_generation' && $conditions->get($productId, 'storage_capacity') === '256_gb', 'Non-iPhone storage handling remains unchanged.');
    }
} finally {
    if ($productId > 0) {
        wp_delete_post($productId, true);
    }
}

$test->finish();
