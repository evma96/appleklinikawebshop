<?php
/** Read-only completeness report against the current configured model catalog. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__, 4).'/wp-load.php';
use Appleklinika\Inventory\Infrastructure\WordPress\{WordPressModelSpecifications,DeviceCatalogRepository};
$repo=new WordPressModelSpecifications();$profiles=get_option(WordPressModelSpecifications::OPTION,[]);
$catalog=(new DeviceCatalogRepository())->all();$mapped=[];$missing=[];$missingSources=[];$models=[];
foreach($catalog as $model) {
    $p=$repo->resolve($model['key']);$mapped[]=$p['model_key'];
    if($p['rows']===[])$missing[]=$model['key'];
    elseif($p['status']!=='ready')$missingSources[]=$model['key'];
    $models[]=['catalog_key'=>$model['key'],'name'=>$model['name'],'family'=>$model['type'],'profile_key'=>$p['model_key'],'status'=>$p['status'],'source_url'=>$p['source_url']];
}
$allMissingSources=[];$exact=0;
foreach(array_keys($profiles) as $key) {if($repo->resolve($key)['status']==='ready')++$exact;else $allMissingSources[]=$key;}
$report=['current_catalog_models'=>count($catalog),'models_with_profile'=>count($catalog)-count($missing),'models_missing_profile'=>$missing,'stored_profiles'=>count($profiles),'profiles_with_exact_apple_url'=>$exact,'profiles_missing_exact_apple_url'=>$allMissingSources,'catalog_models_missing_exact_apple_url'=>$missingSources,'orphan_profiles'=>array_values(array_diff(array_keys($profiles),$mapped)),'models'=>$models];
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
exit($missing!==[]||$missingSources!==[]||$allMissingSources!==[]?1:0);
