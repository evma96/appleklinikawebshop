<?php
/** Explicit, idempotent LOCAL/TEST provisioning. Never run on activation or normal deployment. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require '/var/www/html/wp-load.php';
$target = in_array('--target=local', $argv, true) ? 'local' : (in_array('--target=test', $argv, true) ? 'test' : '');
$apply = in_array('--apply', $argv, true);
$local = $target === 'local' && wp_get_environment_type() === 'local' && getenv('AK_LOCAL_VERIFICATION') === '1' && defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL;
$test = $target === 'test' && wp_get_environment_type() === 'staging' && parse_url(home_url(), PHP_URL_HOST) === 'teszt.appleklinika.com';
if (!$local && !$test) throw new RuntimeException('Explicit guarded LOCAL or named TEST target required; production denied.');
use Appleklinika\Inventory\Infrastructure\WordPress\WooProtectionCatalog as Catalog;
$definitions = [
    ['glass-standard', 'glass', 'Közepes üvegfólia', '4000'],
    ['glass-premium', 'glass', 'Prémium üvegfólia', '7000'],
    ['case-standard', 'case', 'Egyszerű tok', '3000'],
    ['case-premium', 'case', 'Prémium tok', '8000'],
    ['camera', 'camera', 'Kamera védő', '5000'],
    ['adapter-20w', 'adapter', '20 W gyorstöltő adapter', ''],
];
$existing = [];
// Validate all existing identities before making any write.
foreach ($definitions as [$sku, $group, $name, $price]) {
    $sku = 'ak-protection-' . $sku;
    $ids = get_posts(['post_type'=>'product', 'post_status'=>['publish','draft','pending','private','trash'], 'numberposts'=>-1, 'fields'=>'ids', 'meta_key'=>'_sku', 'meta_value'=>$sku]);
    if (count($ids) > 1) throw new RuntimeException('Duplicate SKU requires review: ' . $sku);
    if ($ids !== []) {
        $product = wc_get_product((int)$ids[0]);
        if (!$product || !$product->is_type('simple') || $product->get_meta(Catalog::GROUP) !== $group || $product->get_catalog_visibility() !== 'hidden' || $product->get_status() !== 'publish') {
            throw new RuntimeException('Existing SKU is not the expected hidden published accessory: ' . $sku);
        }
        $existing[$sku] = $product->get_id();
    }
}
foreach ($definitions as [$suffix, $group, $name, $price]) {
    $sku = 'ak-protection-' . $suffix;
    if (isset($existing[$sku])) {
        echo json_encode(['action'=>'reused', 'sku'=>$sku, 'id'=>$existing[$sku], 'administrator_configuration_preserved'=>true]) . "\n";
        continue;
    }
    if (!$apply) {
        echo json_encode(['action'=>'would_create', 'sku'=>$sku, 'price'=>$price]) . "\n";
        continue;
    }
    $product = new WC_Product_Simple();
    $product->set_name($name);
    $product->set_sku($sku);
    $product->set_status('publish');
    $product->set_catalog_visibility('hidden');
    $product->set_regular_price($price);
    $product->set_price($price);
    $product->set_manage_stock(false);
    $product->set_stock_status('instock');
    $product->update_meta_data(Catalog::GROUP, $group);
    // Requested first-stage device-type mapping. No invented per-model compatibility table.
    $product->update_meta_data(Catalog::TYPES, ['iphone']);
    $product->update_meta_data(Catalog::MODELS, []);
    echo json_encode(['action'=>'created', 'sku'=>$sku, 'id'=>$product->save(), 'price'=>$price]) . "\n";
}
