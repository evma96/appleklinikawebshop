<?php
/** Read-only public selector integration: real LOCAL devices and memory-only privacy fixtures. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__, 4) . '/wp-load.php';
if (wp_get_environment_type() !== 'local' || getenv('AK_LOCAL_VERIFICATION') !== '1'
    || !defined('WP_HTTP_BLOCK_EXTERNAL') || !WP_HTTP_BLOCK_EXTERNAL) throw new RuntimeException('Guarded LOCAL only.');

use Appleklinika\Inventory\Domain\ModelSpecification\ModelIdentity;
use Appleklinika\Inventory\Infrastructure\WordPress\{DeviceCatalogRepository, WooProductConditionRepository, WooSelectorConfiguration, WordPressModelSpecifications};
use Appleklinika\Inventory\Interfaces\Frontend\ProductFrontendDisplay;

$count = $network = $mail = 0;
$check = static function (bool $ok, string $label) use (&$count): void { ++$count; if (!$ok) throw new RuntimeException($label); };
add_filter('pre_http_request', static function () use (&$network) { ++$network; return new WP_Error('local_blocked'); }, PHP_INT_MAX);
add_filter('pre_wp_mail', static function () use (&$mail) { ++$mail; return false; }, PHP_INT_MAX);
$repository = new WooProductConditionRepository();
$frontend = new ProductFrontendDisplay($repository, new DeviceCatalogRepository(), new WordPressModelSpecifications());
$call = static fn (string $method, ...$args) => (new ReflectionMethod($frontend, $method))->invoke($frontend, ...$args);
$ids = array_values(array_filter(array_map('absint', array_slice($argv, 1))));
if ($ids === []) throw new RuntimeException('Pass existing public LOCAL product IDs. No fixtures are created.');
foreach ($ids as $id) {
    $product = wc_get_product($id);
    $check($product instanceof WC_Product && $product->get_status() === 'publish', 'Existing public device');
    $before = hash('sha256', serialize($product->get_data()));
    $pool = $call('relatedProducts', $product);
    $payload = $call('productSelectorPayload', $pool);
    $check(count($payload) === count($pool), 'One projection per physical Woo identity');
    $check(count(array_unique(array_column($payload, 'id'))) === count($payload), 'No duplicate physical identity');
    $canonical = ModelIdentity::canonical($repository->get($id, 'device_model'));
    foreach ($payload as $row) {
        $actual = wc_get_product($row['id']);
        $check($row['modelKey'] === $canonical, 'Exact canonical family only');
        $check($row['url'] === get_permalink($actual->get_id()), 'Native physical product URL');
        $check((float)$row['salePrice'] === (float)$actual->get_price(), 'Actual native Woo price');
        $check($row['id'] === $id || $row['available'], 'Other candidates are purchasable');
        $check(str_contains($row['purchaseHtml'], 'value="' . $row['id'] . '"'), 'Cart fragment contains same device ID');
        $check(!preg_match('/serial_number|imei_1|imei_2|_appleklinika_imei/i', json_encode($row)), 'No private identity fields in public projection');
        $profile = $call('officialSpecsForProduct', $actual, $actual->get_id());
        $check(($profile['model_key'] ?? '') === $canonical, 'Every alternative uses the same correct canonical specification profile');
    }
    $check(hash('sha256', serialize(wc_get_product($id)->get_data())) === $before, 'Rendering leaves product unchanged');
}

// Memory-only product and filtered metadata: no private fixture ever enters the database.
$fake = new WC_Product_Simple();
$fake->set_id(987654321);
$meta = ['_appleklinika_connectivity' => 'wifi_cellular', '_appleklinika_ram_size' => '16_gb',
    '_appleklinika_processor_chip' => 'm2', '_appleklinika_serial_number' => 'PRIVATE-SENTINEL',
    '_appleklinika_imei_1' => 'PRIVATE-SENTINEL'];
$filter = static fn ($value, $id, $key, $single) => $id === 987654321 ? ($single ? ($meta[$key] ?? '') : [$meta[$key] ?? '']) : $value;
add_filter('get_post_metadata', $filter, 10, 4);
try {
    $attributes = [];
    foreach ([['RAM', '32 GB', true], ['CPU', 'PRIVATE-SENTINEL', false], ['IMEI', 'PRIVATE-SENTINEL', true]] as [$name, $value, $visible]) {
        $a = new WC_Product_Attribute(); $a->set_name($name); $a->set_options([$value]); $a->set_visible($visible); $attributes[] = $a;
    }
    $fake->set_attributes($attributes);
    $projection = (new WooSelectorConfiguration($repository))->forProduct($fake);
    $check($projection['connectivity']['display'] === 'Wi-Fi + Cellular', 'Connectivity human label');
    $check($projection['ram_size']['display'] === '16 GB', 'Canonical memory label');
    $check($projection['processor_chip']['display'] === 'M2', 'Chip label');
    $check($projection['attribute:RAM']['value'] === '32 GB', 'Visible native technical attribute included');
    $check(!str_contains(json_encode($projection), 'PRIVATE-SENTINEL'), 'Hidden attributes and serial/IMEI excluded even if wrongly marked visible');
} finally { remove_filter('get_post_metadata', $filter, 10); }
$check($network === 0 && $mail === 0, 'No provider, external HTTP or email calls');
echo "Smart selector LOCAL passed: {$count} assertions; no saved fixtures.\n";
