<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
$GLOBALS['wp_filter']['pre_http_request'][PHP_INT_MAX][] = ['function' => static fn() => new WP_Error('offline_warranty_check'), 'accepted_args' => 3];
$GLOBALS['wp_filter']['pre_wp_mail'][PHP_INT_MAX][] = ['function' => static fn() => false, 'accepted_args' => 2];
require dirname(__DIR__, 4) . '/wp-load.php';
if (!in_array(wp_get_environment_type(), ['local', 'staging'], true)) { throw new RuntimeException('LOCAL/TEST only'); }

use Appleklinika\Inventory\Application\ProductCondition\{SaveProductConditionCommand, SaveProductConditionHandler};
use Appleklinika\Inventory\Infrastructure\WordPress\{DeviceCatalogRepository, SelectorDemoProductsSeeder, WooProductConditionRepository};
use Appleklinika\Inventory\Interfaces\Frontend\ProductFrontendDisplay;

$count = 0;
$check = static function (bool $ok, string $label) use (&$count): void {
    ++$count;
    if (!$ok) { throw new RuntimeException($label); }
};
$repository = new WooProductConditionRepository();
$catalog = new DeviceCatalogRepository();
$handler = new SaveProductConditionHandler($repository, $catalog);
foreach (['iphone', 'ipad', 'macbook', 'apple_watch'] as $type) {
    foreach (['', '  ', '12_months', '24_months', '36_months', 'Egyedi gyártói garancia'] as $term) {
        $prepared = $handler->prepare(new SaveProductConditionCommand(0, ['device_type' => $type, 'warranty_duration' => $term]));
        $check($prepared['warranty_duration'] === (trim($term) ?: '12_months'), $type . ': default only missing terms, preserve explicit terms');
    }
}
$check($handler->prepare(new SaveProductConditionCommand(0, []))['warranty_duration'] === '', 'No invented warranty for other product types');
$check($handler->prepare(new SaveProductConditionCommand(0, ['warranty_duration' => '24_months']))['warranty_duration'] === '24_months', 'Other product explicit terms preserved');
$seeder = new SelectorDemoProductsSeeder($repository);
foreach ((new ReflectionMethod($seeder, 'demoProducts'))->invoke($seeder) as $row) {
    $check(($row['warranty_duration'] ?? '12_months') === '12_months', 'Demo grade does not imply extended warranty');
}

// Unsaved metadata fixtures: exercise the same warranty source used by both sections.
$id = 987654321;
$term = '12_months';
$filter = static function ($value, $objectId, $key, $single) use ($id, &$term) {
    if ($objectId !== $id || $key !== '_appleklinika_warranty_duration') { return $value; }
    return $single ? $term : [$term];
};
add_filter('get_post_metadata', $filter, 10, 4);
try {
    $display = new ProductFrontendDisplay($repository, $catalog);
    $label = new ReflectionMethod($display, 'warrantyLabel');
    foreach (['12_months' => '12 hónap', '24_months' => '24 hónap', '36_months' => '36 hónap'] as $term => $expected) {
        $check($label->invoke($display, $repository->get($id, 'warranty_duration')) === $expected, 'Explicit warranty rendering remains supported');
        ob_start();
        (new ReflectionMethod($display, 'renderTrustCards'))->invoke($display, $id);
        $markup = (string) ob_get_clean();
        $check(str_contains($markup, $expected . ' garancia'), 'Trust card uses product warranty, not a fixed string');
    }
} finally { remove_filter('get_post_metadata', $filter, 10); }
echo "Warranty consistency: {$count} assertions PASS; no database writes, email or provider calls.\n";
