<?php
/** Explicit disposable device-entry acceptance; CLI only, no provider/mail calls. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$mode=$argv[1]??'';
$target=$argv[2]??'';
if(!in_array($mode,['create','verify','sold','restock','cleanup'],true)||!in_array($target,['local','test'],true))exit(2);
require dirname(__DIR__,4).'/wp-load.php';
if(!defined('DISABLE_WP_CRON'))define('DISABLE_WP_CRON',true);
if(!defined('WP_HTTP_BLOCK_EXTERNAL'))define('WP_HTTP_BLOCK_EXTERNAL',true);
if(($target==='test'&&(wp_get_environment_type()!=='staging'||home_url()!=='https://teszt.appleklinika.com'))
 ||($target==='local'&&(wp_get_environment_type()!=='local'||getenv('AK_LOCAL_VERIFICATION')!=='1')))throw new RuntimeException('Wrong environment');
use Appleklinika\Inventory\Application\DeviceProduct\{DeviceProductService,SaveDeviceProductCommand};
use Appleklinika\Inventory\Application\ProductCondition\SaveProductConditionHandler;
use Appleklinika\Inventory\Infrastructure\WordPress\{WooDeviceProductStore,WooProductConditionRepository,DeviceCatalogRepository,DeviceOrderIdentity,DeviceInvoiceMapping};
use Appleklinika\Inventory\Interfaces\Frontend\ProductFrontendDisplay;
use Appleklinika\Inventory\Domain\DeviceProduct\PhysicalDevice;
require_once ABSPATH.'wp-admin/includes/image.php';
require_once ABSPATH.'wp-admin/includes/file.php';
require_once ABSPATH.'wp-admin/includes/media.php';
$http=$mail=$checks=0;
add_filter('pre_http_request',static function()use(&$http){$http++;return new WP_Error('acceptance_blocked');},PHP_INT_MAX);
add_filter('pre_wp_mail',static function()use(&$mail){$mail++;return false;},PHP_INT_MAX);
add_filter('woocommerce_should_send_no_stock_notification','__return_false');
add_filter('woocommerce_should_send_low_stock_notification','__return_false');
$check=static function(bool $value,string $label)use(&$checks):void {if(!$value)throw new RuntimeException($label);$checks++;};
$manifest='/tmp/ak-device-entry-acceptance-'.$target.'.json';
$m=is_file($manifest)?json_decode(file_get_contents($manifest),true,512,JSON_THROW_ON_ERROR):['product'=>0,'media'=>[],'order'=>0];
$record=static function()use(&$m,$manifest):void {file_put_contents($manifest,json_encode($m));chmod($manifest,0600);};
$snapshot=static function(int $exclude):array {
 $result=[];foreach(wc_get_products(['limit'=>-1,'status'=>['publish','draft','pending','private','trash']])as$p)if($p->get_id()!==$exclude)$result[$p->get_id()]=hash('sha256',serialize($p->get_data()));ksort($result);return$result;
};
$condition=new WooProductConditionRepository();$catalog=new DeviceCatalogRepository();$store=new WooDeviceProductStore();
$service=new DeviceProductService($store,new SaveProductConditionHandler($condition,$catalog),new \Appleklinika\Inventory\Infrastructure\WordPress\WordPressModelSpecifications());
$save=static fn(int $id,array $data):int=>$service->save(new SaveDeviceProductCommand($id,$data));
$sku='AK-QA-DEVICE-ENTRY-20261027';
$imei='QA-ENTRY-IMEI-20261027';$serial='QA-ENTRY-SERIAL-20261027';
if($mode==='cleanup'){
 if(!$m['product'])throw new RuntimeException('No owned fixture manifest');
 if($m['order']){$o=wc_get_order($m['order']);if($o){$check($o->get_created_via()==='device-entry-acceptance','Own QA order');$o->delete(true);}}
 foreach($m['media']as$id){if(get_post($id)){$check(in_array((int)wp_get_post_parent_id($id),[0,$m['product']],true)&&str_starts_with(basename(get_attached_file($id)),'ak-device-entry-qa-'.$m['product'].'-'),'Own QA photo');wp_delete_attachment($id,true);}}
 $p=wc_get_product($m['product']);if($p){$check($p->get_sku()===$sku,'Own QA product');$p->delete(true);}
 $check($snapshot(0)===$m['baseline_products'],'All normal products unchanged');
 $check(!wc_get_product($m['product'])&&!wc_get_order($m['order']),'Own product/order removed');
 foreach($m['media']as$id)$check(!get_post($id),'Own attachment removed');
 unlink($manifest);
}elseif($mode==='create'){
 if(is_file($manifest)||wc_get_product_id_by_sku($sku))throw new RuntimeException('Existing fixture must be cleaned first');
 $files=array_slice($argv,3);if(count($files)!==2)throw new RuntimeException('Exactly two HEIC inputs required');
 foreach($files as$f)if(!is_file($f)||!in_array(strtolower(pathinfo($f,PATHINFO_EXTENSION)),['heic','heif'],true))throw new RuntimeException('Invalid HEIC input');
 $m['baseline_products']=$snapshot(0);$record();
 $m['product']=$save(0,['device_model'=>'iphone_13_pro','storage_capacity'=>'128_gb','color'=>'sierra_blue','overall_grade'=>'a','regular_price'=>'139990','sku'=>$sku,'imei'=>$imei,'serial_number'=>$serial,'battery_health'=>'96','warranty_duration'=>'12 hónap']);$record();
 foreach($files as$i=>$f){
  $temp=wp_tempnam('qa.heic');copy($f,$temp);
  $id=media_handle_sideload(['name'=>'ak-device-entry-qa-'.$m['product'].'-'.$i.'.heic','tmp_name'=>$temp],$m['product'],'Disposable device-entry QA photo');
  if(is_wp_error($id))throw new RuntimeException($id->get_error_message());
  $m['media'][]=$id;$record();
 }
 $save($m['product'],['photos'=>$m['media'],'status'=>'publish']);
 $check(wc_get_product($m['product'])->get_name()==='iPhone 13 Pro 128 GB','Automatic title');
}else{
 $p=wc_get_product($m['product']);if(!$p||$p->get_sku()!==$sku)throw new RuntimeException('Wrong fixture');
 if($mode==='sold'||$mode==='restock'){
  if(!$m['order'])throw new RuntimeException('Verify the order simulation first');
  if($mode==='sold'){wc_reduce_stock_levels($m['order']);wc_reduce_stock_levels($m['order']);}else wc_increase_stock_levels($m['order']);
  $p=wc_get_product($m['product']);$expected=$mode==='sold'?0:1;
  $check($p->get_stock_quantity()===$expected,'Native stock/retry');
  $check($p->get_status()==='publish','Sold record retained');
 }else{
  $check($p->get_name()==='iPhone 13 Pro 128 GB','Automatic name');
  $check(str_starts_with($p->get_slug(),'iphone-13-pro-128-gb'),'Normal slug');
  $check($p->is_type('simple')&&$p->get_manage_stock()&&$p->is_sold_individually()&&$p->get_backorders()==='no','Native one-unit settings');
  $check($p->get_stock_quantity()===1&&$p->get_catalog_visibility()==='visible','Available visible unit');
  $check($p->get_meta('_appleklinika_protection_group')==='','Not accessory');
  $cat=get_term_by('slug','iphone','product_cat');$check(in_array((int)$cat->term_id,$p->get_category_ids(),true),'Derived iPhone category');
  $check($p->get_meta('_appleklinika_imei')===$imei&&$p->get_meta('_appleklinika_serial_number')===$serial,'Authorized private identity');
  $check(!wc_product_has_unique_sku(0,$sku),'Native SKU uniqueness');
  $check(in_array($p->get_id(),WC_Data_Store::load('product')->search_products($sku),true),'Woo admin SKU search');
  $check((int)$p->get_image_id()===$m['media'][0]&&$p->get_gallery_image_ids()===[$m['media'][1]],'Ordered native photos');
  foreach($m['media']as$id){
   $meta=wp_get_attachment_metadata($id);$f=get_attached_file($id);$check(str_ends_with($f,'.jpg')&&!empty($meta['sizes']),'Browser-safe HEIC conversion and sizes');
   $check(($meta['image_meta']??[])===[],'No public image metadata');
   $paths=[$f];foreach($meta['sizes']as$s)$paths[]=dirname($f).'/'.$s['file'];if(!empty($meta['original_image']))$paths[]=dirname($f).'/'.$meta['original_image'];
   foreach(array_unique($paths)as$file){
    $pipes=[];$proc=proc_open(['/usr/bin/exiftool','-j','-GPS:all','-EXIF:DateTimeOriginal','-EXIF:CreateDate','-MakerNotes:all','-SerialNumber','-ImageUniqueID',$file],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]);$tags=json_decode(stream_get_contents($pipes[1]),true)[0]??[];fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);$exit=proc_close($proc);unset($tags['SourceFile']);
    $check($exit===0&&$tags===[],'No private metadata in original/derivative');
   }
  }
  if(!$m['order']){$o=new WC_Order();$o->set_status('checkout-draft');$o->set_created_via('device-entry-acceptance');$o->set_currency('HUF');$o->set_billing_country('HU');$m['order']=$o->save();$record();$o->add_product($p,1);$o->calculate_totals();$o->save();}
  $o=wc_get_order($m['order']);$item=array_values($o->get_items())[0];
  $check($item->get_meta(DeviceOrderIdentity::CAPTURED)==='yes'&&$item->get_meta('_appleklinika_imei')===$imei&&$item->get_meta('_appleklinika_serial_number')===$serial&&$item->get_meta('_appleklinika_sku')===$sku,'Order snapshot');
  $check(!str_contains(serialize($item->get_formatted_meta_data('')),$imei)&&!str_contains(serialize($item->get_formatted_meta_data('')),$serial),'Public order metadata private');
  $bo=(new Appleklinika\BackOffice\Infrastructure\WooOrderBackOfficeRepository())->deviceItems($o);
  $check(($bo[0]['details']['IMEI']??'')===$imei&&($bo[0]['details']['Cikkszám']??'')===$sku,'Authorized Back Office snapshot');
  $vendor=file_get_contents(WP_PLUGIN_DIR.'/integration-for-szamlazzhu-woocommerce/index.php');$start=strpos($vendor,'//Product items',strpos($vendor,'//Order Items'));$end=strpos($vendor,'//Shipping',$start);if(!$start||!$end)throw new RuntimeException('Vendor mapping boundary changed');$mapping=substr($vendor,$start,$end-$start);
  $xml=(function()use($o,$mapping){$order=$o;$szamla=new WCSzamlazzSimpleXMLElement('<szamla/>');$szamla->addChild('fejlec')->addChild('megjegyzes','');$tetelek=$szamla->addChild('tetelek');$vevo=new WCSzamlazzSimpleXMLElement('<vevo/>');$order_items=$order->get_items();$rounding=0;$invoice_line_items=[];eval($mapping);return$szamla;})->call(WC_Szamlazz());
  $mapper=new DeviceInvoiceMapping();$xml=$mapper->document($xml,$o,'invoice');$line=$xml->tetelek->tetel[0];$note=(string)$line->megjegyzes;
  $check($note==='IMEI: '.$imei."\nSorozatszám: ".$serial,'Exact supported invoice line mapping');
  $check((string)$xml->fejlec->megjegyzes===PhysicalDevice::NOTE,'Exact used-goods note');
  $check((int)(string)$line->mennyiseg===1&&(float)(string)$line->bruttoErtek===(float)$item->get_total(),'Native invoice quantity/amount unchanged');
  $mapper->line($line,$item);$check((string)$line->megjegyzes===$note,'Mapping replay unchanged');
  $check(!$o->is_paid()&&$o->get_status()==='checkout-draft','No real payment/invoice lifecycle');
 }
 $p=wc_get_product($m['product']);$available=$p->get_stock_quantity()===1;
 foreach(['catalog','search']as$v)$check(in_array($p->get_id(),wc_get_products(['status'=>'publish','visibility'=>$v,'include'=>[$p->get_id()],'return'=>'ids']),true)===$available,'Visibility '.$v);
 $check(in_array($p->get_id(),wc_get_products(['status'=>'publish','visibility'=>'catalog','category'=>['iphone'],'include'=>[$p->get_id()],'return'=>'ids']),true)===$available,'Category stock state');
 $frontend=new ProductFrontendDisplay($condition,$catalog);$related=(new ReflectionMethod($frontend,'relatedProducts'))->invoke($frontend,$p);$check(in_array($p->get_id(),array_map(static fn($x)=>$x->get_id(),$related),true)===$available,'Selector stock state');
 $GLOBALS['product']=$p;ob_start();$frontend->renderDynamicProductPage();$html=(string)ob_get_clean();
 $check(!str_contains($html,$imei)&&!str_contains($html,$serial),'Public HTML identity privacy');
 $check($snapshot($m['product'])===$m['baseline_products'],'Normal products unchanged');
}
$check($http===0&&$mail===0,'No provider/mail attempts');
echo json_encode(['result'=>'PASS','mode'=>$mode,'checks'=>$checks,'product'=>$m['product'],'media'=>$m['media'],'order'=>$m['order'],'url'=>$mode==='cleanup'?null:get_permalink($m['product']),'http'=>$http,'mail'=>$mail],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
