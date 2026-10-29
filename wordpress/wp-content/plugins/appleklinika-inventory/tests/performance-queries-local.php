<?php
/** LOCAL regression: native Woo fixtures and legacy/optimized query equivalence. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__, 4) . '/wp-load.php';
if (wp_get_environment_type() !== 'local' || getenv('AK_LOCAL_VERIFICATION') !== '1'
    || !defined('WP_HTTP_BLOCK_EXTERNAL') || !WP_HTTP_BLOCK_EXTERNAL) throw new RuntimeException('Guarded LOCAL only.');
use Appleklinika\Inventory\Infrastructure\WordPress\{DeviceAvailability, WooDeviceQueries, WooProductMetadata, WooProtectionCatalog, ProductDescriptionImages, WooSimilarDevicesQuery, WooProductConditionRepository, DeviceCatalogRepository};
$count = $http = $mail = 0; $owned = [];
$check = static function(bool $ok, string $label) use (&$count): void { ++$count; if (!$ok) throw new RuntimeException($label); };
add_filter('pre_http_request', static function() use (&$http) { ++$http; return new WP_Error('local_blocked'); }, PHP_INT_MAX);
add_filter('pre_wp_mail', static function() use (&$mail) { ++$mail; return false; }, PHP_INT_MAX);
add_filter('woocommerce_should_send_no_stock_notification', '__return_false');
add_filter('woocommerce_should_send_low_stock_notification', '__return_false');
$snapshot = static function(): array { $r=[]; foreach(wc_get_products(['limit'=>-1,'status'=>['publish','draft','private','pending','trash']]) as $p) if($p->get_meta('_ak_performance_qa')!=='yes') $r[$p->get_id()]=hash('sha256',serialize($p->get_data())); ksort($r); return $r; };
$before = $snapshot();
$legacy = static function(array $q, array $v): array {
    if (!empty($v[WooDeviceQueries::AVAILABLE])) { unset($q[WooDeviceQueries::AVAILABLE]); $q['meta_query'][] = DeviceAvailability::constraint(); }
    return $q;
};
$ids = static fn(array $ps): array => array_map(static fn($p): int => $p->get_id(), $ps);
$query = static function(array $args, bool $old = false) use ($legacy): array {
    if($old) add_filter('woocommerce_product_data_store_cpt_get_products_query', $legacy, 99, 2);
    try { return wc_get_products($args + ['status'=>'publish','visibility'=>'visible','limit'=>-1,'return'=>'ids','orderby'=>['date'=>'DESC','ID'=>'DESC'],'cache_results'=>false]); }
    finally { if($old) remove_filter('woocommerce_product_data_store_cpt_get_products_query', $legacy, 99); }
};
$make = static function(array $meta = [], array $props = []) use (&$owned): WC_Product {
    $p = new WC_Product_Simple();
    $p->set_props($props + ['name'=>'LOCAL performance QA','status'=>'publish','catalog_visibility'=>'visible','regular_price'=>'100000','price'=>'100000','manage_stock'=>true,'stock_quantity'=>1,'stock_status'=>'instock','sold_individually'=>true]);
    $p->update_meta_data('_ak_performance_qa','yes');
    foreach($meta as $k=>$v) $p->update_meta_data('_appleklinika_'.$k,$v);
    $owned[]=$p->save(); return $p;
};
try {
    foreach (['catalog','visible','search'] as $visibility) {
        $check($query(['visibility'=>$visibility]) === $query(['visibility'=>$visibility],true), $visibility.': full existing catalogue IDs and order match legacy predicate');
    }
    $search = static function(bool $old, int $page) use ($legacy): array {
        $previous = [$GLOBALS['wp_query'] ?? null, $GLOBALS['wp_the_query'] ?? null];
        $legacyMain = static function($q): void { $q->set('meta_query', [DeviceAvailability::constraint()]); };
        // Remove only the optimized WHERE for the reference, preserving the exact
        // same deterministic relevance/date/ID ordering on both query strategies.
        $oldWhere = static function($where, $q): string {
            return str_replace((new WooDeviceQueries())->availableWhere('', $q), '', $where);
        };
        if ($old) { add_action('pre_get_posts', $legacyMain, 999); add_filter('posts_where', $oldWhere, 999, 2); }
        try {
            $q=new WP_Query(); $GLOBALS['wp_query']=$GLOBALS['wp_the_query']=$q;
            $q->query(['s'=>'iphone','post_type'=>'product','posts_per_page'=>16,'paged'=>$page,'fields'=>'ids','cache_results'=>false]);
            return ['ids'=>$q->posts,'found'=>$q->found_posts,'sql'=>$q->request];
        } finally {
            [$GLOBALS['wp_query'],$GLOBALS['wp_the_query']]=$previous;
            remove_action('pre_get_posts',$legacyMain,999); remove_filter('posts_where',$oldWhere,999);
        }
    };
    $pages=[];
    foreach ([1,2,3,4] as $page) {
        $old=$search(true,$page);$new=$search(false,$page);
        $check($old['ids']===$new['ids'] && $old['found']===$new['found'],'Search page '.$page.': identical legacy/optimized IDs under the same relevance/date/ID order');
        $check(str_contains($new['sql'], 'post_date DESC, '.$GLOBALS['wpdb']->posts.'.ID DESC'),'Stable ID only breaks existing ranking/date ties');
        $pages=array_merge($pages,$new['ids']);
    }
    $check(count($pages)===count(array_unique($pages)),'Search pages do not duplicate tied products');
    $fixtures=[];
    foreach ([null,'','iphone_13_pro'] as $model) foreach ([false,true] as $managed) foreach ([0,1] as $stock) {
        $meta=$model===null?[]:['device_model'=>$model,'device_type'=>'iphone'];
        $fixtures[]=$make($meta,['manage_stock'=>$managed,'stock_quantity'=>$stock]);
    }
    $missing=$make(['device_model'=>'iphone_13_pro']);
    // Metadata edge cases use supported WordPress APIs, never raw database writes.
    delete_post_meta($missing->get_id(),'_manage_stock');delete_post_meta($missing->get_id(),'_stock');$fixtures[]=$missing;
    foreach (['hidden','search','catalog'] as $visibility) $fixtures[]=$make(['device_model'=>'iphone_13_pro'],['catalog_visibility'=>$visibility]);
    $fixtures[]=$make(['device_model'=>'iphone_13_pro'],['status'=>'draft']);
    $fixtures[]=$make(['device_model'=>'iphone_13_pro'],['stock_quantity'=>0,'backorders'=>'notify','stock_status'=>'onbackorder']);
    foreach (['catalog','visible','search'] as $visibility) {
        $args=['include'=>$ids($fixtures),'visibility'=>$visibility];
        $check($query($args)===$query($args,true), $visibility.': missing/empty model, stock zero/one, unmanaged, hidden, draft and backorder semantics unchanged');
    }
    $live=$make(['device_type'=>'iphone','device_model'=>'iphone_13_pro','overall_grade'=>'a']);
    $args=['include'=>[$live->get_id()]];
    $check($query($args)===[$live->get_id()], 'New native Woo device appears immediately');
    wc_update_product_stock($live,0,'set');
    $check($query($args)===[], 'Native stock deduction removes device from a warm query cache');
    $check($query($args)===$query($args,true), 'Sold exclusion matches original');
    wc_update_product_stock(wc_get_product($live->get_id()),1,'set');
    $check($query($args)===[$live->get_id()], 'Native restock reappears without persistent availability cache');
    $live=wc_get_product($live->get_id());
    $same = $live->get_meta('_appleklinika_overall_grade');
    $fresh=wc_get_product($live->get_id());
    WooProductMetadata::prime([$fresh]);
    $check($fresh->get_meta('_appleklinika_overall_grade')===$same,'Priming preserves custom metadata');
    $check($fresh->get_price()===$live->get_price() && $fresh->get_stock_quantity()===$live->get_stock_quantity(),'Native properties still resolved by Woo');
    $fresh->update_meta_data('_appleklinika_overall_grade','b');$fresh->save();
    $next=wc_get_product($fresh->get_id());WooProductMetadata::prime([$next]);
    $check($next->get_meta('_appleklinika_overall_grade')==='b','Native metadata save invalidates primed cache');
    $next->update_meta_data('_appleklinika_overall_grade','c');WooProductMetadata::prime([$next]);
    $check($next->get_meta('_appleklinika_overall_grade')==='c','Priming never overwrites unsaved object data');
    $adapter=new WooDeviceQueries();
    $check($adapter->productQuery(['meta_query'=>[]],[WooDeviceQueries::FAMILY=>[]])['post__in']===[0],'Explicit empty recommendation scope cannot query every family');
    $repo=new WooSimilarDevicesQuery(new WooProductConditionRepository(),new DeviceCatalogRepository());
    foreach (['iphone'=>['iphone_13_pro','iphone'],'ipad'=>['ipad_air_5th_generation','ipad'],'mac'=>['macbook_air_13_inch_m2','macbook'],'watch'=>['apple_watch_series_9','apple_watch']] as $family=>[$model,$alias]) {
        $p=$make(['device_type'=>$alias,'device_model'=>$model]);
        $peer=$make(['device_type'=>$alias,'device_model'=>$model]);
        $scope=['types'=>[$alias],'models'=>[$model]];
        $check(in_array($peer->get_id(),$query([WooDeviceQueries::FAMILY=>$scope]),true),$family.': new uncategorized device found through real Woo CPT adapter');
        $check(!in_array($peer->get_id(),$query([WooDeviceQueries::FAMILY=>['types'=>['unrelated'],'models'=>['unrelated']]]),true),$family.': contradictory family excluded at SQL layer');
        $check($query([WooDeviceQueries::FAMILY=>$scope,'meta_key'=>'_appleklinika_device_model','meta_value'=>'unrelated'])===[],$family.': existing meta filters stay AND grouped');
        $found=$repo->get($p,[]);
        $check(in_array($peer->get_id(),$ids($found),true)&&count($found)<=6,$family.': fallback includes fresh peer, maximum six');
        foreach($found as $item) $check(DeviceAvailability::active($item),$family.': each recommended device is natively purchasable/in stock');
        wc_update_product_stock($peer,0,'set');
        $check(!in_array($peer->get_id(),$ids($repo->get($p,[])),true),$family.': sold recommendation immediately excluded');
    }
    $accessory=$make(['protection_group'=>'case','protection_types'=>['iphone'],'protection_models'=>[]],['catalog_visibility'=>'hidden','sold_individually'=>false,'regular_price'=>'1000','price'=>'1000']);
    $catalog=new WooProtectionCatalog();
    $a=$catalog->forDisplay($live);$b=$catalog->forDisplay($live);
    $check($a===$b&&isset($a[$accessory->get_id()]),'Display snapshot reuses identical accessory candidates');
    wc_update_product_stock($accessory,0,'set');
    $check(!$catalog->forDevice($live->get_id())[$accessory->get_id()]['available'],'Cart validation sees fresh stock despite earlier display snapshot');
    $accessory=wc_get_product($accessory->get_id());$accessory->set_regular_price('0');$accessory->save();
    $check(!$catalog->forDevice($live->get_id())[$accessory->get_id()]['available'],'Cart validation never accepts zero-price extra');
    $imageRenderer=new ProductDescriptionImages();
    $plain='<p>Unchanged content</p><img src="/ordinary.jpg" alt="photo">';
    $check($imageRenderer->render($plain)===$plain,'Ordinary description/photo markup unchanged');
    $check(!str_contains($imageRenderer->render('<img src="/unknown.heic" alt="device">'),'src='),'Unknown HEIC is not requested without an existing derivative');
    $attachments=get_posts(['post_type'=>'attachment','post_status'=>'inherit','post_mime_type'=>'image/heic','numberposts'=>-1,'fields'=>'ids']);
    foreach($attachments as $id){
        $metadata=wp_get_attachment_metadata($id);$url=wp_get_attachment_url($id);
        if(empty($metadata['original_image'])||!str_ends_with((string)$url,'.jpg'))continue;
        $heic=trailingslashit(dirname($url)).$metadata['original_image'];
        $html='<a href="'.esc_url($heic).'"><img class="wp-image-'.$id.' size-full" src="'.esc_url($heic).'" alt="Original photo"></a>';
        $rendered=$imageRenderer->render($html);
        $check(!str_contains($rendered,'.heic')&&str_contains($rendered,'srcset='),'Existing HEIC attachment resolves to responsive JPEG');
        $check(str_contains($rendered,'href="'.$url.'"')&&str_contains($rendered,'Original photo'),'Original browser-safe zoom and alt preserved');
    }
    $check($http===0&&$mail===0,'No provider HTTP or email calls');
} finally {
    foreach(array_reverse($owned) as $id){$p=wc_get_product($id);if($p&&$p->get_meta('_ak_performance_qa')==='yes')$p->delete(true);}
}
$check($snapshot()===$before,'Original products unchanged after supported cleanup');
foreach($owned as $id)$check(!wc_get_product($id),'Owned QA product removed');
echo "Performance query/image regression passed: {$count} assertions. No external calls; QA cleaned.\n";
