<?php
/** Model registry + ordinary device-entry regression; disposable LOCAL fixtures only. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__, 4).'/wp-load.php';
if (wp_get_environment_type() !== 'local' || getenv('AK_LOCAL_VERIFICATION') !== '1' || !defined('WP_HTTP_BLOCK_EXTERNAL') || !WP_HTTP_BLOCK_EXTERNAL) throw new RuntimeException('Isolated LOCAL only.');

use Appleklinika\Inventory\Application\DeviceProduct\{DeviceProductService,SaveDeviceProductCommand};
use Appleklinika\Inventory\Application\ProductCondition\SaveProductConditionHandler;
use Appleklinika\Inventory\Infrastructure\WordPress\{WordPressModelSpecifications,DeviceCatalogRepository,WooProductConditionRepository,WooDeviceProductStore};
use Appleklinika\Inventory\Interfaces\Frontend\ProductFrontendDisplay;
use Appleklinika\Inventory\Interfaces\Admin\DeviceProductEditor;
use Appleklinika\Inventory\Domain\ModelSpecification\ModelIdentity;

$manifest='/tmp/ak-model-specifications-qa.json';
$cleanup=static function(array $m):void {
    foreach($m['products']??[] as $id) { $p=wc_get_product($id); if($p && $p->get_meta('_ak_model_spec_qa')==='yes') $p->delete(true); }
    foreach($m['media']??[] as $id) if(get_post_meta($id,'_ak_model_spec_qa',true)==='yes') wp_delete_attachment($id,true);
};
if(in_array('--cleanup',$argv,true)) {
    if(!is_file($manifest)) throw new RuntimeException('No owned QA manifest.');
    $m=json_decode(file_get_contents($manifest),true,512,JSON_THROW_ON_ERROR); $cleanup($m);
    foreach($m['products'] as $id) if(wc_get_product($id)) throw new RuntimeException('QA product remains.');
    foreach($m['media'] as $id) if(get_post($id)) throw new RuntimeException('QA media remains.');
    unlink($manifest); echo "PASS QA cleanup: products/media removed through Woo/WordPress APIs\n";exit;
}
if(is_file($manifest)) throw new RuntimeException('Clean prior QA fixtures first.');
$count=$http=$mail=0;$ids=$media=[];$success=false;
$check=static function(bool $ok,string $message)use(&$count):void {++$count;if(!$ok)throw new RuntimeException('Check '.$count.': '.$message);};
add_filter('pre_http_request',static function()use(&$http){++$http;return new WP_Error('local_blocked');},PHP_INT_MAX);
add_filter('pre_wp_mail',static function()use(&$mail){++$mail;return false;},PHP_INT_MAX);
$repo=new WordPressModelSpecifications();$store=new WooDeviceProductStore();$catalog=new DeviceCatalogRepository();$conditions=new WooProductConditionRepository();
$service=new DeviceProductService($store,new SaveProductConditionHandler($conditions,$catalog),$repo);
$frontend=new ProductFrontendDisplay($conditions,$catalog,$repo);
$call=static fn(string $method,...$args)=>(new ReflectionMethod($frontend,$method))->invoke($frontend,...$args);
$render=static function(WC_Product $p)use($call):string {ob_start();try{$call('renderProductSpecsPanel',$p,$p->get_id());return (string)ob_get_contents();}finally{ob_end_clean();}};
$save=static fn(int $id,array $data):int=>$service->save(new SaveDeviceProductCommand($id,$data));
$snapshot=static function():array {$r=[];foreach(wc_get_products(['limit'=>-1,'status'=>['publish','draft','private','pending','trash']]) as $p)if($p->get_meta('_ak_model_spec_qa')!=='yes')$r[$p->get_id()]=hash('sha256',serialize($p->get_data()));ksort($r);return $r;};
$before=$snapshot();$registryBefore=get_option(WordPressModelSpecifications::OPTION);
$record=static function()use(&$ids,&$media,$manifest):void {file_put_contents($manifest,json_encode(['products'=>$ids,'media'=>$media],JSON_PRETTY_PRINT));chmod($manifest,0600);};

try {
    $repo->installMissingProfiles();$repo->installMissingProfiles();
    $check(get_option(WordPressModelSpecifications::OPTION)===$registryBefore,'Provisioning repeats leave existing registry unchanged');
    foreach(['ipad_air_5th_generation'=>'ipad_air_5','macbook_air_13_inch_m2'=>'macbook_air_m2','apple_watch_se_2nd_generation'=>'apple_watch_se_2','iphone_12_pro'=>'iphone_12_pro'] as $alias=>$canonical)
        $check(ModelIdentity::canonical($alias)===$canonical,'Exact known model identity: '.$alias);
    // In-memory option overlays exercise bad identities and centrally revised data without DB mutation.
    $overlay=$registryBefore;
    $filter=static function()use(&$overlay){return $overlay;};add_filter('pre_option_'.WordPressModelSpecifications::OPTION,$filter);
    try {
        unset($overlay['iphone_12'], $overlay['iphone_11']);
        $overlay['ipad_10']['source_url']='https://support.apple.com/specs/ipad';
        $check($repo->resolve('iphone_12')['rows']===[], 'No nearest generation/model fallback');
        $check($repo->resolve('iphone_11')['status']==='missing_profile', 'Missing configured model is explicit');
        $check($repo->resolve('ipad_10')['rows']!==[] && $repo->resolve('ipad_10')['status']==='missing_model_source', 'Generic source retains technical rows');
        $check($repo->resolve('ipad_10')['source_url']==='', 'Generic source never masquerades as model-specific');
        $overlay=$registryBefore;
        $overlay['iphone_12_pro']['model_key']='iphone_13_pro';
        $check($repo->resolve('iphone_12_pro')['rows']===[],'Mismatched embedded model identity rejected');
        $overlay=$registryBefore;$overlay['iphone_12_pro']['rows'][]=['label'=>'QA central update','value'=>'Shared, never copied'];
        $check($repo->resolve('iphone_12_pro')['rows'][count($repo->resolve('iphone_12_pro')['rows'])-1]['label']==='QA central update','Central profile change is immediately resolved');
    } finally {remove_filter('pre_option_'.WordPressModelSpecifications::OPTION,$filter);}

    // No product save: selected model wins over stale historical pointers/spec JSON and title.
    $fakeId=987654321;$metadata=['_appleklinika_device_model'=>'iphone_12_pro','_ak_official_specs_model_key'=>'iphone_13_pro','_ak_official_specs'=>'[{"label":"Chip","value":"WRONG"}]'];
    $metaFilter=static function($v,$id,$key,$single)use($fakeId,&$metadata){return $id===$fakeId?($single?($metadata[$key]??''):[$metadata[$key]??'']):$v;};
    add_filter('get_post_metadata',$metaFilter,10,4);
    try {
        $fake=new WC_Product_Simple();$fake->set_id($fakeId);$fake->set_name('iPhone 13 Pro');
        $resolved=$call('officialSpecsForProduct',$fake,$fakeId);
        $check($resolved['model_key']==='iphone_12_pro'&&str_contains(json_encode($resolved['rows']),'A14')&&!str_contains(json_encode($resolved['rows']),'WRONG'),'Selected model overrides stale pointer/title/product JSON');
        $metadata['_appleklinika_device_model']='unconfigured_missing_model';
        $check($call('officialSpecsForProduct',$fake,$fakeId)['rows']===[],'Missing selected model never falls back to old product profile');
        $metadata=[];
        $check($call('officialSpecsForProduct',$fake,$fakeId)['rows']===[],'Product title does not infer a model');
    } finally {remove_filter('get_post_metadata',$metaFilter,10);}

    $fixtures=[
        'iphone'=>['model'=>'iphone_xr','storage'=>'128_gb','color'=>'black','chip'=>'A12','url'=>'https://support.apple.com/en-gb/111868','extra'=>[]],
        'ipad'=>['model'=>'ipad_7th_generation','storage'=>'128_gb','color'=>'silver','chip'=>'A10','url'=>'https://support.apple.com/en-us/111911','extra'=>[]],
        'mac'=>['model'=>'macbook_pro_14_inch_m1_max','storage'=>'512_gb','color'=>'space_gray','chip'=>'M1 Max','url'=>'https://support.apple.com/en-gb/111902','extra'=>['ram_size'=>'32_gb','processor_chip'=>'m1_max']],
        'watch'=>['model'=>'apple_watch_series_10','storage'=>'64_gb','color'=>'silver','chip'=>'S10','url'=>'https://support.apple.com/en-us/121202','extra'=>['case_material'=>'aluminium','case_size'=>'42_mm','connectivity'=>'gps']],
    ];
    require_once ABSPATH.'wp-admin/includes/image.php';require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';require_once WC_ABSPATH.'includes/admin/wc-meta-box-functions.php';
    foreach($fixtures as $family=>$f) {
        $input=$f['extra']+['device_model'=>$f['model'],'storage_capacity'=>$f['storage'],'color'=>$f['color'],'sku'=>'AK-LOCAL-MODEL-SPEC-'.strtoupper($family),'serial_number'=>'QA-MODEL-SPEC-'.strtoupper($family),'overall_grade'=>'a','battery_health'=>'95','regular_price'=>'159990','warranty_duration'=>'12 hónap'];
        $plan=$service->prepare(new SaveDeviceProductCommand(0,$input));
        $check($plan['technical_profile']['status']==='ready'&&$plan['technical_profile']['warnings']===[],'Ordinary entry fields resolve profile: '.$family);
        $check(!isset($plan['technical_profile']['rows'])&&!isset($plan['technical_profile']['source_url']),'Save plan carries relationship only: '.$family);
        $id=$save(0,$input);$ids[$family]=$id;$p=wc_get_product($id);$p->update_meta_data('_ak_model_spec_qa','yes');$p->save();$record();
        $tmp=wp_tempnam('model-spec-qa.jpg');$image=new Imagick();$image->newImage(600,800,new ImagickPixel('#edf0f4'));$image->setImageFormat('jpg');$image->writeImage($tmp);$image->clear();
        $photo=media_handle_sideload(['name'=>'ak-model-spec-qa-'.$family.'.jpg','tmp_name'=>$tmp],$id,'Disposable LOCAL model-specification QA image');
        if(is_wp_error($photo))throw new RuntimeException($photo->get_error_message());$media[]=$photo;update_post_meta($photo,'_ak_model_spec_qa','yes');$record();
        $save($id,['photos'=>[$photo],'status'=>'publish']);$p=wc_get_product($id);$html=$render($p);$profile=$repo->resolve($f['model']);
        $check($p->get_meta('_ak_official_specs_model_key')===ModelIdentity::canonical($f['model']),'Automatic canonical relationship persisted: '.$family);
        $check($p->get_meta('_ak_official_specs')===''&&$p->get_meta('_ak_official_specs_source_url')==='','No per-device spec copy or manual URL: '.$family);
        $check($p->is_sold_individually()&&$p->get_stock_quantity()===1,'Native physical-device invariant unchanged: '.$family);
        $check(str_contains($html,'Műszaki adatok')&&str_contains($html,'Összes műszaki adat'),'Same technical component on new product: '.$family);
        $check(str_contains($html,'appleklinika-technical-group')&&str_contains($html,$f['chip']),'Correct grouped model data: '.$family);
        $check(str_contains($html,$f['url'])&&$profile['source_url']===$f['url'],'Exact official model link: '.$family);
        if($family!=='iphone')$check(!str_contains($html,'A14 Bionic')&&!str_contains($html,'iPhone 12 Pro'),'No iPhone profile leakage: '.$family);
        if($family==='watch')$check(!str_contains($html,'Hátlapi kamera'),'Watch has no phone rear camera group');
        $GLOBALS['product_object']=$p;ob_start();(new DeviceProductEditor($service,$store))->fields();$admin=(string)ob_get_clean();
        $check(!str_contains($admin,'data-model-spec-warning'),'Complete model has no false admin warning: '.$family);
    }
    // Ordinary model edit rebinds the profile, without touching private/spec metadata manually.
    $save($ids['iphone'],['device_model'=>'iphone_13_pro','color'=>'graphite']);$p=wc_get_product($ids['iphone']);$html=$render($p);
    $check($p->get_meta('_ak_official_specs_model_key')==='iphone_13_pro'&&str_contains($html,'A15')&&!str_contains($html,'A14'),'Model update rebinds selected profile');
    $save($ids['iphone'],['device_model'=>'iphone_12_pro']);
    foreach(['128_gb','256_gb','512_gb'] as $capacity) {
        $plan=$service->prepare(new SaveDeviceProductCommand($ids['iphone'],['storage_capacity'=>$capacity]));
        $check($plan['technical_profile']['model_key']==='iphone_12_pro','All physical capacities share one model profile: '.$capacity);
    }
    $overlay=$registryBefore; unset($overlay['iphone_11']);
    add_filter('pre_option_'.WordPressModelSpecifications::OPTION,$filter);
    try {
    // A missing catalog profile is safe and visible to the editor, not the customer.
    $missing=$save(0,['device_model'=>'iphone_11','storage_capacity'=>'128_gb','color'=>'black','sku'=>'AK-LOCAL-MODEL-SPEC-MISSING','serial_number'=>'QA-MODEL-SPEC-MISSING','overall_grade'=>'a','regular_price'=>'99990']);
    $ids['missing']=$missing;$p=wc_get_product($missing);$p->update_meta_data('_ak_model_spec_qa','yes');$p->save();$record();
    $check($p->get_status()==='draft'&&$p->get_meta('_ak_official_specs_status')==='missing_profile','Missing profile is explicit without preventing ordinary draft save');
    $GLOBALS['product_object']=$p;ob_start();(new DeviceProductEditor($service,$store))->fields();$admin=(string)ob_get_clean();
    $check(str_contains($admin,'data-model-spec-warning')&&str_contains($admin,'iphone_11'),'Admin identifies exact missing model');
    $html=$render($p);$check(!str_contains($html,'data-product-technical')&&!str_contains($html,'még nincs ellenőrzött'),'No wrong specs or developer error on storefront');
    unset($GLOBALS['product_object']);
    } finally {remove_filter('pre_option_'.WordPressModelSpecifications::OPTION,$filter);}

    $save($ids['iphone'],['device_model'=>'iphone_xr','color'=>'black']);
    $manual=wc_get_product(2531);$html=$render($manual);
    $check($manual&&$manual->get_meta('_appleklinika_device_model')==='iphone_12_pro','Existing manual device reused');
    $check(str_contains($html,'A14')&&str_contains($html,'111875')&&str_contains($html,'Összes műszaki adat'),'Existing manual device automatically gains correct technical data');
    $check($manual->get_meta('_ak_official_specs')===''&&$manual->get_meta('_ak_official_specs_model_key')==='','Existing manual device required no metadata edit or resave');
    // Many individually created devices share the same 13 Pro rows, never per-product copies.
    $shared=$repo->resolve('iphone_13_pro');
    for($i=0;$i<3;$i++) {
        $id=$save(0,['device_model'=>'iphone_13_pro','storage_capacity'=>['128_gb','256_gb','512_gb'][$i], 'color'=>'graphite','sku'=>'AK-LOCAL-MODEL-SPEC-SCALE-'.$i,'serial_number'=>'QA-MODEL-SPEC-SCALE-'.$i,'overall_grade'=>'a','regular_price'=>'159990']);
        $ids['scale_'.$i]=$id; $p=wc_get_product($id); $p->update_meta_data('_ak_model_spec_qa','yes');$p->save();$record();
        $check($p->get_meta('_ak_official_specs')===''&&$call('officialSpecsForProduct',$p,$id)['rows']===$shared['rows'],'Independent physical device shares canonical 13 Pro profile: '.$i);
    }
    global $wpdb;
    wp_cache_delete(WordPressModelSpecifications::OPTION,'options');
    $q=$wpdb->num_queries;$start=microtime(true);$stable=true;
    for($i=0;$i<1000;$i++) $stable=$stable&&$repo->resolve('iphone_13_pro')['rows']===$shared['rows'];
    $lookupQueries=$wpdb->num_queries-$q;
    $check($stable&&$lookupQueries<=1,'1000 same-model resolutions reuse WordPress option cache (at most one SQL read)');
    echo 'Lookup sanity: 1000 resolutions, '.$lookupQueries.' SQL reads, '.round((microtime(true)-$start)*1000,2)." ms\n";
    $check($snapshot()===$before,'Every original physical product unchanged');
    $check(get_option(WordPressModelSpecifications::OPTION)===$registryBefore,'QA does not modify canonical registry');
    $check($http===0&&$mail===0,'No provider, mail, or external source request');
    $success=true;echo "PASS {$count} model specification checks; HTTP={$http}; mail={$mail}\n";
    echo json_encode(['products'=>$ids,'urls'=>array_map('get_permalink',$ids),'media'=>$media],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
} finally {
    if(!$success||!in_array('--keep-for-browser',$argv,true)) {$cleanup(['products'=>$ids,'media'=>$media]);if(is_file($manifest))unlink($manifest);}
}
