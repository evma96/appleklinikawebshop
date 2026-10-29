<?php
/** Offline catalog/provisioning regressions: no WordPress, database or HTTP. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
spl_autoload_register(static function(string $class):void {
    $prefix='Appleklinika\\Inventory\\';
    if(str_starts_with($class,$prefix))require dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
});
function remove_accents(string $value):string {return iconv('UTF-8','ASCII//TRANSLIT',$value);}
$options=[];$writes=0;
function get_option(string $key,mixed $default=false):mixed {global $options;return $options[$key]??$default;}
function update_option(string $key,mixed $value,bool $autoload=false):bool {global $options,$writes;$options[$key]=$value;++$writes;return true;}
use Appleklinika\Inventory\Infrastructure\WordPress\{WordPressModelSpecifications,DeviceCatalogRepository};
use Appleklinika\Inventory\Domain\ModelSpecification\ModelIdentity;
$count=0;$check=static function(bool $ok,string $message)use(&$count):void {++$count;if(!$ok)throw new RuntimeException($message);};
$repo=new WordPressModelSpecifications();$repo->installMissingProfiles();$all=get_option($repo::OPTION);$catalog=(new DeviceCatalogRepository())->all();
$check(count($catalog)===102,'Only the current 102 catalog models');
$check(count($all)===103,'102 mapped profiles plus one preserved historical profile');
$mapped=[];
foreach($catalog as $model) {
    $key=ModelIdentity::canonical($model['key']);$mapped[]=$key;$p=$repo->resolve($model['key']);
    $check($p['status']==='ready'&&count($p['rows'])>=8,'Covered exact model: '.$model['key']);
    $check($p['model_key']===$key&&$p['rows']===$all[$key]['rows'],'Catalog identity maps to its canonical profile: '.$key);
}
$check(array_values(array_diff(array_keys($all),$mapped))===['macbook_pro_16_m3'],'Historical combined M3 Pro/Max profile retained without fabricating a base-M3 16-inch model');
foreach($all as $key=>$entry) {
    $check((bool)preg_match('~^https://support\.apple\.com/[a-z-]+/[0-9]{6}$~',$entry['source_url']),'Specific Apple archive URL: '.$key);
    $check(count(array_unique(array_column($entry['rows'],'label')))===count($entry['rows'])&&!str_contains(json_encode($entry),'source_product_id'),'No duplicate labels or physical-product data: '.$key);
}
$values=static fn(string $key):array=>array_column($all[$key]['rows'],'value','label');
$check(str_contains($values('iphone_xr')['Chip'],'A12')&&str_contains($values('iphone_12_pro')['Chip'],'A14')&&str_contains($values('iphone_13_pro')['Chip'],'A15'),'Three different phone generations retain distinct chips');
$check(str_contains($values('ipad_7th_generation')['Chip'],'A10')&&str_contains($values('ipad_8th_generation')['Chip'],'A12')&&str_contains($values('ipad_10')['Chip'],'A14'),'Numbered iPad generations do not borrow Air data');
$check(str_contains($values('ipad_pro_11_inch_2nd_generation')['Kijelző'],'11 hüvelykes')&&str_contains($values('ipad_pro_12_9_inch_4th_generation')['Kijelző'],'12,9'),'A12Z sizes remain separate');
$check(str_contains($values('macbook_pro_14_inch_m1_pro')['Chip'],'M1 Pro')&&str_contains($values('macbook_pro_14_inch_m1_max')['Chip'],'M1 Max'),'Shared Apple source separates Pro and Max chips');
$check(!str_contains($values('macbook_pro_14_m4_pro')['Memória'],'64')&&!str_contains($values('macbook_pro_16_m4_pro')['Tárhely'],'8 TB'),'M4 Pro does not inherit Max-only memory/storage');
$check($values('macbook_pro_14_m5')['Wi‑Fi']==='Wi‑Fi 6E (802.11ax)'&&$values('macbook_pro_14_m5')['Bluetooth']==='Bluetooth 5.3','Base M5 does not inherit M5 Pro/Max radio');
$check(str_contains($values('macbook_pro_16_m4_max')['Akkumulátor'],'21 óra')&&str_contains($values('macbook_pro_14_inch_m5_max')['Akkumulátor'],'20 óra'),'Battery ratings follow size and chip');
$check($values('apple_watch_series_10')['Felbontás']==='42 mm: 374 × 446 pixel; 46 mm: 416 × 496 pixel','Watch Series 10 size-specific resolutions replace placeholder');
foreach($all as $key=>$p)if(str_starts_with($key,'apple_watch'))$check(!isset($values($key)['Hátlapi kamera']),'Watch retains family-specific fields: '.$key);
$before=$options;$n=$writes;$repo->installMissingProfiles();
$check($options===$before&&$n===$writes,'Versioned repeat provisioning performs zero writes');
// Reconstruct the known reviewed old Watch 10 data, not arbitrary current editor content.
$old=$all['apple_watch_series_10'];$old['source_url']='https://support.apple.com/specs/apple-watch';
foreach($old['rows'] as &$row)if($row['label']==='Felbontás')$row['value']='42 mm és 46 mm Series 10 felbontások';unset($row);
$old['editor_note']='retained non-row metadata';
$options=[$repo::OPTION=>['apple_watch_series_10'=>$old]];$repo->installMissingProfiles();
$check($options[$repo::OPTION]['apple_watch_series_10']['rows']===$all['apple_watch_series_10']['rows'],'Known old revision receives reviewed correction');
$check($options[$repo::OPTION]['apple_watch_series_10']['editor_note']==='retained non-row metadata','Unrelated existing provenance preserved');
$custom=$old;$custom['rows'][0]['value']='Reviewed custom value';
$options=[$repo::OPTION=>['apple_watch_series_10'=>$custom,'iphone_11'=>[]]];$repo->installMissingProfiles();
$check($options[$repo::OPTION]['apple_watch_series_10']===$custom&&$options[$repo::OPTION]['iphone_11']===[],'Unknown manual changes and explicit empty entries are never overwritten');
$options=[$repo::OPTION=>'malformed retained value'];$repo->installMissingProfiles();
$check($options[$repo::OPTION]==='malformed retained value','Malformed existing registry is not destroyed');
echo "PASS {$count} offline model catalog/provisioning checks\n";
