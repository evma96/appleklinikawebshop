<?php
/** Read-only LOCAL presentation verification against the current catalog. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__,4) . '/wp-load.php';
if (wp_get_environment_type() !== 'local' || getenv('AK_LOCAL_VERIFICATION') !== '1') throw new RuntimeException('LOCAL only.');
use Appleklinika\Inventory\Interfaces\Frontend\ProductFrontendDisplay;
use Appleklinika\Inventory\Infrastructure\WordPress\{WooProductConditionRepository,DeviceCatalogRepository};
$count=0;
$check=static function(bool $ok,string $message) use (&$count) { ++$count; if(!$ok)throw new RuntimeException($message); };
$capture=static function(callable $f):string { ob_start(); try{$f();return (string)ob_get_contents();}finally{ob_end_clean();} };
$display=new ProductFrontendDisplay(new WooProductConditionRepository(),new DeviceCatalogRepository());
$check(appleklinika_storage_label('32_gb')==='32 GB','Card/cart 32 GB presentation');
$check(appleklinika_storage_label('unknown_value')==='unknown_value','No blind underscore replacement');
$check((new ReflectionMethod($display,'storageLabel'))->invoke($display,'32_gb')==='32 GB','Detail storage presentation');
$ids=get_posts(['post_type'=>'product','post_status'=>'publish','numberposts'=>-1,'fields'=>'ids','meta_query'=>[['key'=>'_appleklinika_device_type','value'=>'apple_watch'],['key'=>'_appleklinika_storage_capacity','value'=>'32_gb']]]);
$check($ids!==[],'Actual affected LOCAL Watch products found');
foreach($ids as $id){
    $p=wc_get_product($id); $before=serialize($p->get_data());
    $card=$capture(static fn()=>appleklinika_render_product_card($p));
    $detail=$capture(static fn()=>(new ReflectionMethod($display,'renderProductSpecsPanel'))->invoke($display,$p,$id));
    $check(str_contains($card,'32 GB')&&!str_contains(strip_tags($card),'32_gb'),'Actual Watch card clean');
    $check(str_contains($detail,'32 GB')&&!str_contains(strip_tags($detail),'32_gb'),'Actual Watch specification clean');
    $check(serialize(wc_get_product($id)->get_data())===$before&&get_post_meta($id,'_appleklinika_storage_capacity',true)==='32_gb','Stored product remains unchanged');
}
$home=$capture('appleklinika_render_homepage');
$d=new DOMDocument(); @$d->loadHTML('<?xml encoding="UTF-8">'.$home); $x=new DOMXPath($d);
$strip=$x->query('//ul[@class="ak-home-benefit-strip"]');
$check($strip->length===1,'One strip only');
$check($x->query('//section[contains(@class,"ak-home-hero")]/following-sibling::*[1][self::ul[@class="ak-home-benefit-strip"]]')->length===1,'Strip directly follows hero');
$check($x->query('//ul[@class="ak-home-benefit-strip"]/li')->length===4,'Four benefits');
foreach(['12 hónap garancia','Teljes körű diagnosztika','20 napos ingyenes visszaküldés','Gyors szállítás'] as $text) $check(str_contains($strip->item(0)->textContent,$text),'Exact supplied claim: '.$text);
$check($x->query('//ul[@class="ak-home-benefit-strip"]/li/*[local-name()="svg"]')->length===4,'Existing vector icons used');
echo "Trust/Watch LOCAL checks passed: {$count} assertions; ".count($ids)." affected Watch products, no data writes.\n";
