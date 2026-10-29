<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__, 4) . '/wp-load.php';

use Appleklinika\Inventory\Application\ProductSelector\ModelSelectorOptions;
use Appleklinika\Inventory\Domain\ProductCondition\Grade;
use Appleklinika\Inventory\Infrastructure\WordPress\DeviceCatalogRepository;
use Appleklinika\Inventory\Infrastructure\WordPress\WooProductConditionRepository;
use Appleklinika\Inventory\Interfaces\Frontend\ProductFrontendDisplay;

$count = 0;
$check = static function (bool $ok, string $message) use (&$count): void {
    ++$count;
    if (!$ok) throw new RuntimeException($message);
};
$query = new ModelSelectorOptions();
$check($query->resolve([], []) === ['colors'=>[], 'storage'=>[]], 'Missing matrix never invents alternatives.');
$check(array_keys($query->resolve(['storage_capacity_keys'=>['128_gb','256_gb']], [['label'=>'Kapacitások','value'=>'64 GB, 1 TB']])['storage']) === ['128_gb','256_gb'], 'Explicit model storage has priority.');
$check(array_keys($query->resolve([], [['label'=>'Kapacitások','value'=>'64 GB, 256 GB']])['storage']) === ['64_gb','256_gb'], 'Existing iPad capacities remain model-specific.');
$check(array_keys($query->resolve([], [['label'=>'Memória','value'=>'8 GB, 16 GB, 24 GB'],['label'=>'Tárhely','value'=>'256 GB SSD, konfigurálható 512 GB, 1 TB vagy 2 TB SSD-vel']])['storage']) === ['256_gb','512_gb','1_tb','2_tb'], 'Mac disk choices exclude RAM.');
$check($query->resolve([], [['label'=>'Kijelző','value'=>'128 GB'],['label'=>'Tárhely','value'=>'256 GB, 999 GB, 1.5 TB']])['storage'] === ['256_gb'=>'256 GB'], 'Unsupported / fractional or unrelated numbers are not capacities.');

$fallback = $query->resolve([], [['label'=>'Színek','value'=>'Space Black, Silver']], ['space_black'=>'Asztrofekete (Space Black)','silver'=>'Ezüst (Silver)','pink'=>'Rózsaszín (Pink)']);
$check(array_keys($fallback['colors']) === ['space_black','silver'], 'Exact-model specification can name valid colors without leaking other palette colors.');
$check($query->resolve([], [['label'=>'Színek','value'=>'Unverified Finish']], ['silver'=>'Ezüst'])['colors'] === [], 'Unknown color descriptions are not invented.');

$id = 987654321;
$metadata = [];
$models = [];
$specs = [];
$metaFilter = static function ($value, $objectId, $key, $single) use ($id, &$metadata) {
    if ($objectId !== $id) return $value;
    $field = str_starts_with($key, '_appleklinika_') ? substr($key, strlen('_appleklinika_')) : $key;
    return $single ? ($metadata[$field] ?? '') : [$metadata[$field] ?? ''];
};
$catalogFilter = static function () use (&$models) { return $models; };
$specsFilter = static function () use (&$specs) { return $specs; };
$versionFilter = static fn () => 9;
add_filter('get_post_metadata', $metaFilter, 10, 4);
add_filter('pre_option_appleklinika_device_catalog', $catalogFilter);
add_filter('pre_option_appleklinika_device_catalog_version', $versionFilter);
add_filter('pre_option_appleklinika_official_specs_by_model', $specsFilter);
$display = new ProductFrontendDisplay(new WooProductConditionRepository(), new DeviceCatalogRepository());
$reflect = new ReflectionClass($display);
$call = static fn ($name, ...$args) => $reflect->getMethod($name)->invoke($display, ...$args);
$html = static function ($name, ...$args) use ($call) { ob_start(); try { $call($name,...$args); return (string) ob_get_contents(); } finally { ob_end_clean(); } };
$parse = static function ($markup) { $dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">'.$markup); return new DOMXPath($dom); };
$p = new WC_Product_Simple();
$p->set_id($id); $p->set_status('publish'); $p->set_name('Unsaved selector fixture');
$p->set_price('100000'); $p->set_regular_price('100000'); $p->set_stock_status('instock');
$baseline = serialize($p->get_data());
try {
    foreach ([
        ['iphone','iphone_13_pro','iphone_13_pro','128_gb',['128_gb','256_gb','512_gb','1_tb'],[]],
        ['ipad','ipad_air_5','ipad_air_5th_generation','256_gb',[],[['label'=>'Kapacitások','value'=>'64 GB, 256 GB']]],
        ['macbook','macbook_air_m2','macbook_air_13_inch_m2','256_gb',[],[['label'=>'Tárhely','value'=>'256 GB, 512 GB, 1 TB, 2 TB']]],
        ['apple_watch','apple_watch_series_9','apple_watch_series_9','64_gb',[],[['label'=>'Tárhely','value'=>'64 GB']]],
    ] as [$type,$key,$catalogKey,$storage,$capacities,$rows]) {
        $metadata = ['device_type'=>$type,'device_model'=>$key,'color'=>'silver','storage_capacity'=>$storage,'overall_grade'=>'a','accessories'=>'Töltőkábel'];
        $models = [['key'=>$catalogKey,'type'=>$type,'name'=>'Exact model','colors'=>['silver'=>'Ezüst (Silver)','gold'=>'Arany (Gold)'],'storage_capacity_keys'=>$capacities]];
        $specs = [$key=>['rows'=>$rows]];
        $rendered = $html('renderBelowHeroProductArea',$p,$id,['url'=>'','html'=>'','srcset'=>''],[$p]);
        $xp=$parse($rendered);
        $check(!str_contains($rendered,'A kiválasztott készülékhez')&&!str_contains($rendered,'Minden termékadat')&&!str_contains($rendered,'appleklinika-quick-facts'),$type.': redundant summary and divider removed.');
        $check($xp->query('//section[@data-selector-group="color"]//button')->length===2,$type.': model colors survive sparse inventory.');
        $check($xp->query('//button[@data-option-value="gold" and @disabled and @aria-disabled="true"]')->length===1,$type.': no-product option is disabled before JavaScript.');
        $check($xp->query('//button[@data-option-value="silver" and @aria-pressed="true"]')->length===1,$type.': existing current color remains selected.');
        $check($xp->query('//section[@data-selector-group="condition"]')->length===1,$type.': condition preserved.');
        $gradeButtons=$xp->query('//section[@data-selector-group="condition"]//button');
        $gradeKeys=array_map(static fn ($button)=>$button->getAttribute('data-option-value'),iterator_to_array($gradeButtons));
        $check($gradeKeys===array_keys(Grade::options()),$type.': full configured condition scale/order survives sparse inventory.');
        $check($xp->query('//section[@data-selector-group="condition"]//button[@data-option-value="a" and @aria-pressed="true" and not(@disabled)]')->length===1,$type.': current physical condition stays selected and enabled.');
        $check($xp->query('//section[@data-selector-group="condition"]//button[@disabled and @aria-disabled="true" and @tabindex="-1"]')->length===3,$type.': valid missing grades remain visible and disabled before JavaScript.');
        $check($xp->query('//section[@data-selector-group="condition"]//button[@disabled]//span[contains(@class,"__meta")]')->length===0,$type.': missing grades have no invented price delta.');
        $check(str_contains($rendered,'appleklinika-product-below-hero--compact-options')===($type!=='iphone'),$type.': approved iPhone dimensions preserved.');
        if($type==='apple_watch'){
            $check(!str_contains($rendered,'appleklinika-below-panel--technical')&&!str_contains($rendered,'data-selector-group="storage"'), 'Watch has no storage card, controls or gap wrapper.');
        } else {
            $expected=$type==='ipad'?2:4;
            $check($xp->query('//section[@data-selector-group="storage"]//button')->length===$expected,$type.': complete known capacities rendered.');
            $check($xp->query('//section[@data-selector-group="storage"]//button[@disabled]')->length===$expected-1,$type.': unavailable storage alternatives stay visible disabled.');
            $check($xp->query('//section[@data-selector-group="storage"]//button[@aria-pressed="true"]')->length===1,$type.': current storage selected.');
        }
        $payload=$call('productSelectorPayload',[$p])[0];
        $check($payload['available']===true && $payload['values']['storage']===$storage,$type.': native availability and internal storage preserved.');
        $check(serialize($p->get_data())===$baseline,$type.': rendering does not mutate product.');
    }
    $p->set_stock_status('outofstock');
    $check($call('productSelectorPayload',[$p])[0]['available']===false,'Out-of-stock product cannot enable an option.');
    $p->set_stock_status('instock');$p->set_price('');
    $check($call('productSelectorPayload',[$p])[0]['available']===false,'Product without a purchasable price cannot enable an option.');
    $check($call('priceDifferenceLabel',5000.0)==='+5 000 Ft'&&$call('priceDifferenceLabel',-80000.0)==='-80 000 Ft','Existing price deltas retained.');
    $models=[['key'=>'unmapped_model','name'=>'Unknown','type'=>'macbook','colors'=>[],'storage_capacity_keys'=>[]]];
    $metadata['device_model']='unmapped_model';$specs=[];
    $rendered=$html('renderStorageSelector',$p,[$p],100000.0,'64 GB');
    $check($parse($rendered)->query('//button')->length===1,'Missing matrix retains known exact-model product without invented alternatives.');
} finally {
    remove_filter('get_post_metadata',$metaFilter,10);
    remove_filter('pre_option_appleklinika_device_catalog',$catalogFilter);
    remove_filter('pre_option_appleklinika_device_catalog_version',$versionFilter);
    remove_filter('pre_option_appleklinika_official_specs_by_model',$specsFilter);
}
echo "Model selector options passed: {$count} assertions; no product/option/QA data saved.\n";
