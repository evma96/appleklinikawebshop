<?php
declare(strict_types=1);
require dirname(__DIR__, 4) . '/wp-load.php';

use Appleklinika\Inventory\Interfaces\Frontend\ProductFrontendDisplay;
use Appleklinika\Inventory\Infrastructure\WordPress\WooProductConditionRepository;
use Appleklinika\Inventory\Infrastructure\WordPress\DeviceCatalogRepository;

// Synthetic metadata and an unsaved WC product; never persist fixtures.
$id = 987654321;
$meta = ['device_type'=>'iphone','device_model'=>'iphone_13_pro','storage_capacity'=>'128_gb','color'=>'silver','overall_grade'=>'b','battery_health'=>'84','warranty_duration'=>'12_months','sim_config'=>'dual_esim','accessories'=>'Töltőkábel'];
$metaFilter = static function ($value, $objectId, $key, $single) use ($id, &$meta) {
    if ($objectId !== $id) return $value;
    $key = str_replace('_appleklinika_', '', $key);
    return $single ? ($meta[$key] ?? '') : [$meta[$key] ?? ''];
};
$catalogFilter = static fn () => [['key'=>'iphone_13_pro','name'=>'iPhone 13 Pro','colors'=>['silver'=>'Ezüst (Silver)','gold'=>'Arany (Gold)','graphite'=>'Grafit (Graphite)','sierra_blue'=>'Hegyi kék (Sierra Blue)','alpine_green'=>'Alpesi zöld (Alpine Green)']]];
$versionFilter = static fn () => 9;
add_filter('get_post_metadata', $metaFilter, 10, 4);
add_filter('pre_option_appleklinika_device_catalog', $catalogFilter);
add_filter('pre_option_appleklinika_device_catalog_version', $versionFilter);
$display = new ProductFrontendDisplay(new WooProductConditionRepository(), new DeviceCatalogRepository());
$reflection = new ReflectionClass($display);
$call = static fn ($name, ...$args) => $reflection->getMethod($name)->invoke($display, ...$args);
$html = static function ($name, ...$args) use ($call) { ob_start(); $call($name, ...$args); return (string) ob_get_clean(); };
$count = 0;
$check = static function ($ok, $message) use (&$count) { $count++; if (!$ok) throw new RuntimeException($message); };
try {
    $p = new WC_Product_Simple();
    $p->set_id($id);
    $p->set_name('Selector teszt - must not leak');
    $p->set_sku('ak-selector-demo-iphone-presentation-unsaved');
    $p->set_description('Helyi fejlesztési teszttermék, WooCommerce selector.');
    $p->set_short_description('Helyi selector teszt.');
    $p->set_regular_price('100000'); $p->set_sale_price('90000');
    $before = serialize($p->get_data());
    $check($call('isLocalSelectorDemo',$p), 'Only the recognized local iPhone fixture uses fallback.');
    foreach (['silver'=>'Ezüst','gold'=>'Arany','graphite'=>'Grafit','sierra_blue'=>'Hegyi kék','alpine_green'=>'Alpesi zöld'] as $key=>$label) {
        $check($call('colorDisplayLabel','iphone_13_pro',$key)===$label, 'Catalog Hungarian color label retained: '.$key);
    }
    $check($call('displayProductTitle',$p)==='iPhone 13 Pro · 128 GB · Ezüst · B', 'Factual demo title differentiates condition.');
    $description = $html('renderProductDescriptionPanel',$p);
    foreach (['iPhone 13 Pro','128 GB','Ezüst','84%','B – jó'] as $fact) $check(str_contains($description,$fact),'Description contains stored fact '.$fact);
    $check(!preg_match('/selector|teszt|WooCommerce/i',$description),'No QA prose in fallback.');
    $quick = $html('renderQuickFacts',$id);
    $check(str_contains($quick,'Két eSIM') && str_contains($quick,'Töltőkábel'),'Quick facts use actual equipment.');
    $check(!str_contains($quick,'Garancia') && !str_contains($quick,'Cikkszám') && !str_contains($quick,'Készlet'),'Repeated purchase/reference fields removed from quick facts.');
    $facts = $html('renderProductFactGroups',$p,$id);
    $check(substr_count($facts,'<dt>Garancia</dt>')===1 && substr_count($facts,'<dt>Tartozékok</dt>')===1,'Complete facts kept once in lower reference.');
    $check(str_contains($facts,'<details') && str_contains($facts,$p->get_sku()),'Canonical SKU preserved in expandable reference.');
    $check(str_contains($facts,'<dt>Akkumulátor állapota</dt><dd>84%</dd>'),'Battery value has no repeated label.');
    $trust = $html('renderTrustCards',$id);
    $check(str_contains($trust,'12 hónap garancia') && !str_contains($trust,'24 hónap'),'Warranty never strengthened.');
    $check(substr_count($trust,'<svg')===4 && !str_contains($trust,'>1</span>'),'Four coherent decorative icons.');
    $payload = $call('productSelectorPayload',[$p])[0];
    $check($payload['salePrice']===90000.0 && $payload['regularPrice']===100000.0,'Pricing payload unchanged.');
    $check($payload['values']===['color'=>'silver','storage'=>'128_gb','condition'=>'b'],'Canonical selector keys unchanged.');
    $check($payload['factsHtml']===$facts && $payload['descriptionHtml']===$description && $payload['quickFactsHtml']===$quick,'Selected product carries the same factual renderers.');
    $check($payload['batteryHealthLabel']==='84%','Battery display follows selected physical phone.');
    $check(serialize($p->get_data())===$before,'Rendering did not change product data.');
    $meta['battery_health']=''; $meta['warranty_duration']=''; $meta['accessories']=''; $meta['sim_config']='';
    $check(!str_contains($call('localDemoDescription',$p),'%'),'Unknown battery health omitted.');
    $check(!str_contains($html('renderTrustCards',$id),'hónap garancia'),'Unknown warranty omitted.');
    $check(!str_contains($html('renderQuickFacts',$id),'Tartozékok'),'Unknown accessories omitted.');
    $remoteHome = static fn () => 'https://example.invalid'; // No network request.
    add_filter('home_url',$remoteHome);
    $check(!$call('isLocalSelectorDemo',$p) && $call('displayProductTitle',$p)===$p->get_name(),'Non-local business title untouched.');
    remove_filter('home_url',$remoteHome);
    $p->set_sku('business-presentation-unsaved');
    $p->set_description('Egyedi üzleti leírás, változatlanul.');
    $check(!$call('isLocalSelectorDemo',$p) && str_contains($html('renderProductDescriptionPanel',$p),'Egyedi üzleti leírás, változatlanul.'),'Non-demo business copy untouched even on Local.');
    echo "Product information presentation passed: {$count} assertions; no data saved.\n";
} finally {
    remove_filter('get_post_metadata',$metaFilter,10);
    remove_filter('pre_option_appleklinika_device_catalog',$catalogFilter);
    remove_filter('pre_option_appleklinika_device_catalog_version',$versionFilter);
}
