<?php
/** Disposable LOCAL Woo rehearsal. No payment, mail or provider requests. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__, 4).'/wp-load.php';
if (wp_get_environment_type() !== 'local' || getenv('AK_LOCAL_VERIFICATION') !== '1' || !defined('WP_HTTP_BLOCK_EXTERNAL') || !WP_HTTP_BLOCK_EXTERNAL) throw new RuntimeException('Isolated LOCAL only.');
use Appleklinika\Inventory\Application\DeviceProduct\{DeviceProductService,SaveDeviceProductCommand};
use Appleklinika\Inventory\Application\ProductCondition\SaveProductConditionHandler;
use Appleklinika\Inventory\Infrastructure\WordPress\{WooDeviceProductStore,WooProductConditionRepository,DeviceCatalogRepository,DeviceOrderIdentity,DeviceAvailability,ProductPhotoProcessor};
use Appleklinika\Inventory\Interfaces\Frontend\ProductFrontendDisplay;
use Appleklinika\Inventory\Interfaces\{DeviceProductHooks,DeviceOrderHooks};
use Appleklinika\Inventory\Domain\DeviceProduct\PhysicalDevice;
use Appleklinika\BackOffice\Infrastructure\WooOrderBackOfficeRepository;
require_once ABSPATH.'wp-admin/includes/image.php';
require_once ABSPATH.'wp-admin/includes/file.php';
require_once ABSPATH.'wp-admin/includes/media.php';
$manifest='/tmp/ak-device-foundation-qa.json';
if (in_array('--cleanup',$argv,true)) {
    $m=json_decode(file_get_contents($manifest),true,512,JSON_THROW_ON_ERROR);
    foreach($m['orders'] as $id) { $o=wc_get_order($id); if($o&&$o->get_created_via()==='local-device-foundation-qa') $o->delete(true); }
    foreach($m['products'] as $id) { $p=wc_get_product($id); if($p&&$p->get_meta('_ak_device_foundation_qa')==='yes') $p->delete(true); }
    foreach($m['media'] as $id) if(get_post_meta($id,'_ak_device_foundation_qa',true)==='yes') wp_delete_attachment($id,true);
    unlink($manifest); echo "QA cleanup PASS\n"; exit;
}
if (is_file($manifest)) throw new RuntimeException('Clean previous LOCAL QA fixture first.');
$count=$http=$mail=0; $ids=[]; $media=[]; $orders=[]; $success=false;
$check=static function(bool $ok,string $message) use (&$count):void { ++$count;if(!$ok)throw new RuntimeException('Check '.$count.': '.$message); };
$reject=static function(callable $operation,string $message) use ($check):void { try{$operation();}catch(InvalidArgumentException $e){$check(true,$message);return;} $check(false,$message); };
add_filter('pre_http_request',static function() use (&$http){++$http;return new WP_Error('local_blocked');},PHP_INT_MAX);
add_filter('pre_wp_mail',static function() use (&$mail){++$mail;return false;},PHP_INT_MAX);
add_filter('woocommerce_should_send_no_stock_notification','__return_false');
add_filter('woocommerce_should_send_low_stock_notification','__return_false');
$store=new WooDeviceProductStore();$condition=new WooProductConditionRepository();$catalog=new DeviceCatalogRepository();
$service=new DeviceProductService($store,new SaveProductConditionHandler($condition,$catalog));
$frontend=new ProductFrontendDisplay($condition,$catalog);
$save=static fn(int $id,array $input):int=>$service->save(new SaveDeviceProductCommand($id,$input));
$record=static function()use(&$ids,&$media,&$orders,$manifest):void {file_put_contents($manifest,json_encode(['products'=>$ids,'media'=>$media,'orders'=>$orders],JSON_PRETTY_PRINT));chmod($manifest,0600);};
$snapshotProducts=static function():array {
    $result=[];foreach(wc_get_products(['limit'=>-1,'status'=>['publish','draft','pending','private','trash']]) as $p)if($p->get_meta('_ak_device_foundation_qa')!=='yes')$result[$p->get_id()]=hash('sha256',serialize($p->get_data()));ksort($result);return $result;
};
$before=$snapshotProducts();
$fixtures=[
    'iphone'=>['device_model'=>'iphone_13_pro','storage_capacity'=>'128_gb','color'=>'sierra_blue','imei'=>'QA-IMEI-PHONE-001','serial_number'=>'QA-SERIAL-PHONE-001','sim_config'=>'physical_esim'],
    'ipad'=>['device_model'=>'ipad_air_5th_generation','storage_capacity'=>'256_gb','color'=>'blue','serial_number'=>'QA-SERIAL-IPAD-001'],
    'mac'=>['device_model'=>'macbook_air_13_inch_m2','storage_capacity'=>'256_gb','color'=>'midnight','serial_number'=>'QA-SERIAL-MAC-001','ram_size'=>'8_gb','processor_chip'=>'m2'],
    'watch'=>['device_model'=>'apple_watch_series_9','storage_capacity'=>'64_gb','color'=>'pink','serial_number'=>'QA-SERIAL-WATCH-001','case_material'=>'aluminium','case_size'=>'41_mm','connectivity'=>'gps'],
];
$photo=static function(int $parent,string $extension='jpg')use(&$media,$record,$check):int {
    $tmp=wp_tempnam('ak-device-qa.'.$extension);
    if(in_array($extension,['heic','heif'],true)) {
        $source=wp_upload_dir()['basedir'].'/2026/10/IMG_9350.heic';if(!is_file($source))throw new RuntimeException('LOCAL HEIC source missing.');copy($source,$tmp);
    } else {
        $img=new Imagick();$img->newImage(900,1200,new ImagickPixel('#e8edf3'));$img->setImageFormat($extension);$img->writeImage($tmp);$img->clear();
        $pipes=[];$proc=proc_open(['/usr/bin/exiftool','-overwrite_original','-GPSLatitude=47.1','-GPSLongitude=19.1','-DateTimeOriginal=2020:01:02 03:04:05','-BodySerialNumber=QA-CAMERA-ID',$tmp],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fclose($pipes[0]);stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);proc_close($proc);
    }
    $originalPixels=new Imagick($tmp);$originalPixels->setIteratorIndex(0);$originalSignature=$originalPixels->getImageSignature();$originalProfiles=$originalPixels->getImageProfiles('icc',true);$originalPixels->clear();
    $id=media_handle_sideload(['name'=>'ak-device-foundation-qa-'.$parent.'.'.$extension,'tmp_name'=>$tmp],$parent,'LOCAL QA device photo');
    if(is_wp_error($id))throw new RuntimeException('Media upload: '.$id->get_error_message());
    $media[]=$id;update_post_meta($id,'_ak_device_foundation_qa','yes');$record();
    $meta=wp_get_attachment_metadata($id);
    $check(!empty($meta['width'])&&!empty($meta['sizes']),'Native upload generates web image and sizes: '.$extension);
    $check(($meta['image_meta']??[])===[],'Private EXIF absent from public attachment metadata');
    $file=get_attached_file($id);$files=[$file];foreach($meta['sizes'] as $size)$files[]=dirname($file).'/'.$size['file'];if(!empty($meta['original_image']))$files[]=dirname($file).'/'.$meta['original_image'];
    foreach(array_unique($files) as $path) {
        $pipes=[];$proc=proc_open(['/usr/bin/exiftool','-j','-GPS:all','-EXIF:DateTimeOriginal','-EXIF:CreateDate','-MakerNotes:all','-SerialNumber','-ImageUniqueID',$path],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fclose($pipes[0]);$json=stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);proc_close($proc);
        $tags=json_decode($json,true)[0]??[];unset($tags['SourceFile']);$check($tags===[],'Served original/derivative contains no private metadata');
    }
    if(in_array($extension,['heic','heif'],true)) {
        $check(str_ends_with(strtolower($file),'.jpg'),'HEIC automatically becomes browser-safe JPEG');
        $original=dirname($file).'/'.$meta['original_image'];
        $a=new Imagick($source);$b=new Imagick($original);$a->setIteratorIndex(0);$b->setIteratorIndex(0);
        $check($a->getImageSignature()===$b->getImageSignature(),'Metadata cleanup preserves HEIC decoded pixels');
        $check($a->getImageOrientation()===$b->getImageOrientation(),'HEIC orientation preserved');
        $check($a->getImageProfiles('icc',true)===$b->getImageProfiles('icc',true),'HEIC ICC preserved');$a->clear();$b->clear();
    }
    if(!in_array($extension,['heic','heif'],true)){ $decoded=new Imagick($file);$check($decoded->getImageSignature()===$originalSignature,'Metadata stripping preserves original web-image pixels');$check($decoded->getImageProfiles('icc',true)===$originalProfiles,'Web image ICC preserved');$decoded->clear(); }
    return $id;
};
try {
    foreach($fixtures as $family=>$data) {
        $id=$save(0,$data+['sku'=>'AK-LOCAL-FOUNDATION-'.strtoupper($family),'regular_price'=>'139990','overall_grade'=>'a','battery_health'=>'96','warranty_duration'=>'12 hónap']);
        $ids[$family]=$id;$p=wc_get_product($id);$p->update_meta_data('_ak_device_foundation_qa','yes');$p->save();$record();
        $check($p->get_status()==='draft'&&$p->get_stock_quantity()===1&&$p->get_manage_stock()&&$p->is_sold_individually(),'Default one physical device / one native draft');
        $photos=[$photo($id,$family==='iphone'?'heic':'jpg')];if($family==='iphone')$photos[]=$photo($id,'png');
        if($family==='ipad')$photos[]=$photo($id,'heif');
        if($family==='mac')$photos[]=$photo($id,'webp');
        $save($id,['photos'=>$photos,'status'=>'publish']);$p=wc_get_product($id);
        $check($p->get_catalog_visibility()==='visible'&&$p->get_status()==='publish'&&$p->is_purchasable(),'Visible purchasable published device');
        $check($p->get_category_ids()!==[]&&$p->get_sku()==='AK-LOCAL-FOUNDATION-'.strtoupper($family),'Native category and manual SKU');
        $check($p->get_meta('_appleklinika_serial_number')===$data['serial_number'],'Private serial saved');
        $check(count($p->get_gallery_image_ids())===count($photos)-1&&(int)$p->get_image_id()===$photos[0],'Native ordered featured/gallery storage');
        $check(str_contains($p->get_name(),$family==='watch'?'Apple Watch':'GB'),'Automatic title meaningful');
        if($family==='watch')$check(!str_contains($p->get_name(),'64 GB'),'Watch internal storage omitted from title');
        $check($p->get_slug()!==''&&$p->get_meta('_appleklinika_custom_name')==='no','Automatic slug/default custom title OFF');
        $list=wc_get_products(['visibility'=>'catalog','status'=>'publish','include'=>[$id],'return'=>'ids']);$check(in_array($id,$list,true),'Native catalog includes physical device');
        $related=(new ReflectionMethod($frontend,'relatedProducts'))->invoke($frontend,$p);
        $check(in_array($id,array_map(static fn($x)=>$x->get_id(),$related),true),'Selector model group includes physical device');
        $images=(new ReflectionMethod($frontend,'productImages'))->invoke($frontend,$p);$check(count($images)===count($photos),'Gallery images rendered once');
        $previousProduct=$GLOBALS['product']??null;$GLOBALS['product']=$p;ob_start();(new ProductFrontendDisplay($condition,$catalog))->renderDynamicProductPage();$publicHtml=(string)ob_get_clean();$GLOBALS['product']=$previousProduct;
        $check(!str_contains($publicHtml,'QA-IMEI')&&!str_contains($publicHtml,'QA-SERIAL'),'Public page and selector JSON omit private identifiers');
    }
    $iphone=$ids['iphone'];$p=wc_get_product($iphone);$photos=$store->load($iphone)['photos'];
    $save($iphone,['photos'=>[$photos[1],$photos[0],$photos[1]]]);$p=wc_get_product($iphone);
    $check((int)$p->get_image_id()===$photos[1]&&$p->get_gallery_image_ids()===[$photos[0]],'Photo reordering deduplicates and changes featured');
    $save($iphone,['photos'=>$photos]);
    $save($ids['watch'],['custom_name'=>true,'title'=>'Apple Watch Series 9 – egyedi QA konfiguráció']);
    $save($ids['watch'],['regular_price'=>'149990']);
    $check(wc_get_product($ids['watch'])->get_name()==='Apple Watch Series 9 – egyedi QA konfiguráció','Custom title retained on unrelated update');
    $save($ids['watch'],['custom_name'=>false]);$check(wc_get_product($ids['watch'])->get_name()==='Apple Watch Series 9','Returning to auto title works');
    $reject(fn()=>$save($ids['ipad'],['sku'=>wc_get_product($iphone)->get_sku()]),'Duplicate native SKU rejected');
    $reject(fn()=>$save($ids['ipad'],['imei'=>'QA-IMEI-PHONE-001']),'Duplicate IMEI rejected');
    $reject(fn()=>$save($ids['ipad'],['serial_number'=>'QA-SERIAL-PHONE-001']),'Duplicate serial rejected');
    $reject(fn()=>$save($iphone,['sku'=>str_repeat('A',101)]),'Oversized native SKU rejected before lookup truncation');
    $save($ids['mac'],['imei'=>'QA-IMEI-MAC-OPTIONAL']);
    $check(wc_get_product($ids['mac'])->get_meta('_appleklinika_imei')==='QA-IMEI-MAC-OPTIONAL','No incorrect family restriction on legitimate identity fields');
    $reject(fn()=>$save($iphone,['stock'=>2]),'Quantity over one rejected');
    $reject(fn()=>$save($iphone,['protection_group'=>'case']),'Device/accessory conflict rejected');
    $reject(fn()=>$save($ids['ipad'],['photos'=>$photos]),'Another physical device photo cannot be reused');
    $reject(fn()=>$save($iphone,['storage_capacity'=>'64_gb']),'Impossible iPhone storage rejected');
    $check(wc_get_product($iphone)->get_catalog_visibility()==='visible','Rejected conflict does not hide original device');
    $accessory=wc_get_product(wc_get_product_id_by_sku('ak-protection-case-premium'));
    $check($accessory&&$accessory->get_catalog_visibility()==='hidden'&&$accessory->get_meta('_appleklinika_protection_group')==='case','Legitimate hidden accessory remains intact');
    require_once WC_ABSPATH.'includes/admin/class-wc-admin-duplicate-product.php';
    $duplicate=(new WC_Admin_Duplicate_Product())->product_duplicate(wc_get_product($iphone));
    $ids['duplicate']=$duplicate->get_id();$duplicate->update_meta_data('_ak_device_foundation_qa','yes');$duplicate->save();$record();
    $check($duplicate->get_status()==='draft'&&$duplicate->get_sku()===''&&!(int)$duplicate->get_image_id()&&$duplicate->get_gallery_image_ids()===[],'Native duplicate clears SKU/photos and remains draft: '.json_encode([$duplicate->get_status(),$duplicate->get_sku(),$duplicate->get_image_id(),$duplicate->get_gallery_image_ids()]));
    $check($duplicate->get_meta('_appleklinika_imei')===''&&$duplicate->get_meta('_appleklinika_serial_number')===''&&$duplicate->get_meta('_appleklinika_short_device_description')==='','Native duplicate clears unique identities/notes');
    $reject(static function()use($duplicate){$duplicate->set_status('publish');$duplicate->save();},'Incomplete duplicated physical device cannot be quick-published');
    $duplicate->set_status('draft');
    $order=new WC_Order();$order->set_status('checkout-draft');$order->set_created_via('local-device-foundation-qa');$order->set_currency('HUF');$order->set_billing_country('HU');
    $orders[]=$order->save();$record();
    foreach($fixtures as $family=>$data)$order->add_product(wc_get_product($ids[$family]),1);
    $order->calculate_totals();$order->save();$record();$order=wc_get_order($order->get_id());
    $items=array_values($order->get_items());
    foreach($items as $item) {
        $check($item->get_meta(DeviceOrderIdentity::CAPTURED)==='yes'&&$item->get_meta('_appleklinika_sku')!==''&&$item->get_meta('_appleklinika_serial_number')!=='','Order item preserves private device/SKU snapshot');
        $formatted=serialize($item->get_formatted_meta_data(''));$check(!str_contains($formatted,'QA-SERIAL')&&!str_contains($formatted,'QA-IMEI'),'Public order/email metadata does not leak private identifiers');
    }
    $save($iphone,['imei'=>'QA-IMEI-PHONE-CHANGED']);
    (new DeviceOrderIdentity())->capture($items[0]);
    $check($items[0]->get_meta('_appleklinika_imei')==='QA-IMEI-PHONE-001','Sold order snapshot remains immutable after product edit/retry');
    $bo=(new WooOrderBackOfficeRepository())->deviceItems($order);
    $check(($bo[0]['details']['IMEI']??'')==='QA-IMEI-PHONE-001'&&($bo[0]['details']['Cikkszám']??'')==='AK-LOCAL-FOUNDATION-IPHONE','Back Office exposes exact authorized order identity');
    $vendor=file_get_contents(WP_PLUGIN_DIR.'/integration-for-szamlazzhu-woocommerce/index.php');
    $start=strpos($vendor,'//Product items',strpos($vendor,'//Order Items'));$end=strpos($vendor,'//Shipping',$start);$mapping=substr($vendor,$start,$end-$start);
    $xml=(function()use($order,$mapping){$szamla=new WCSzamlazzSimpleXMLElement('<szamla/>');$szamla->addChild('fejlec')->addChild('megjegyzes','old note');$tetelek=$szamla->addChild('tetelek');$vevo=new WCSzamlazzSimpleXMLElement('<vevo/>');$order_items=$order->get_items();$rounding=0;$invoice_line_items=[];eval($mapping);return $szamla;})->call(WC_Szamlazz());
    $hook=new \Appleklinika\Inventory\Infrastructure\WordPress\DeviceInvoiceMapping();$xml=$hook->document($xml,$order,'invoice');
    $check((string)$xml->fejlec->megjegyzes===PhysicalDevice::NOTE,'Exact used-goods invoice note via supported hook');
    foreach($items as $i=>$item) {
        $line=$xml->tetelek->tetel[$i];$note=(string)$line->megjegyzes;
        $check(str_contains($note,'Sorozatszám: '.$item->get_meta('_appleklinika_serial_number')),'Real vendor mapping carries exact per-line serial');
        if($i===0)$check(str_contains($note,'IMEI: QA-IMEI-PHONE-001')&&!str_contains($note,'CHANGED'),'Invoice uses sold snapshot, not mutable product');
        $check((string)$line->megnevezes===$item->get_name()&&(int)(string)$line->mennyiseg===1&&(float)(string)$line->bruttoErtek===(float)$item->get_total(),'Invoice native name/quantity/amount unchanged');
        $hook->line($line,$item);$check((string)$line->megjegyzes===$note,'Invoice mapping repeat cannot duplicate identifiers');
    }
    wc_reduce_stock_levels($order->get_id());$p=wc_get_product($iphone);
    $check($p->get_stock_quantity()===0&&!$p->is_in_stock(),'Native stock reduction reaches zero');
    $check(!in_array($iphone,wc_get_products(['visibility'=>'catalog','status'=>'publish','include'=>[$iphone],'return'=>'ids']),true),'Sold device removed from catalog');
    $related=(new ReflectionMethod($frontend,'relatedProducts'))->invoke($frontend,$p);
    $check(!in_array($iphone,array_map(static fn($x)=>$x->get_id(),$related),true),'Sold device removed from available selector');
    $check(get_post_status($iphone)==='publish'&&wc_get_order($order->get_id())->get_item_count()===4,'Sold product/order retained');
    wc_reduce_stock_levels($order->get_id());$check(wc_get_product($iphone)->get_stock_quantity()===0,'Stock reduction retry idempotent');
    wc_increase_stock_levels($order->get_id());$p=wc_get_product($iphone);
    $check($p->get_stock_quantity()===1&&DeviceAvailability::active($p),'Native cancellation/restock returns one available device');
    $check(in_array($iphone,wc_get_products(['visibility'=>'search','status'=>'publish','include'=>[$iphone],'return'=>'ids']),true),'Restocked device available to search');
    $save($iphone,['imei'=>'QA-IMEI-PHONE-001']);
    $nativeEditor=require __DIR__.'/device-product-editor-rehearsal.php';
    $nativeEditor($iphone,$check);
    $protection=(new \Appleklinika\Inventory\Infrastructure\WordPress\WooProtectionCatalog())->forDevice($iphone);
    $check(!isset($protection[2513]),'Legacy device/accessory conflict excluded from accessory list');
    // Inspect the installed GLS COD mapping offline. No provider client is called.
    $gls=file_get_contents(WP_PLUGIN_DIR.'/gls-shipping-for-woocommerce/includes/api/class-gls-shipping-api-data.php');
    $codStart=strrpos($gls,"        if (\$order->get_payment_method() === 'cod') {");
    $codEnd=strpos($gls,"\n        }",$codStart)+10;
    $codMapping=substr($gls,$codStart,$codEnd-$codStart);
    $codResult=(static function()use($codMapping){$order=new WC_Order();$order->set_payment_method('cod');$order->set_status('on-hold');$order->set_total('123450');$orderId=123;$cod_reference=null;$parcel=[];eval($codMapping);return [$parcel,$order];})();
    $check((float)$codResult[0]['CODAmount']===123450.0&&$codResult[0]['CODReference']===123,'Installed GLS supports native COD amount/reference mapping');
    $check(!$codResult[1]->is_paid(),'Native COD order remains unpaid');
    $check(!\Appleklinika\BackOffice\Infrastructure\LifecycleConfiguration::manages($codResult[1]),'Current accepted lifecycle does not enrol COD');
    $check((new WooOrderBackOfficeRepository())->fulfilmentBlockReason($codResult[1])!==null,'Current paid-only fulfilment guard blocks COD; defer instead of bypassing');
    $check($snapshotProducts()===$before,'All original non-QA products unchanged');
    $check($http===0&&$mail===0,'No external provider or email request: HTTP='.$http.' mail='.$mail);
    $success=true;echo "PASS {$count} device foundation checks; HTTP={$http}; mail={$mail}\n";
    echo json_encode(['products'=>$ids,'urls'=>array_map('get_permalink',$ids),'media'=>$media,'orders'=>$orders],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
} finally {
    if(!$success||!in_array('--keep-for-browser',$argv,true)) {
        foreach($orders as $id)if($o=wc_get_order($id))$o->delete(true);
        foreach($ids as $id)if($p=wc_get_product($id))$p->delete(true);
        foreach($media as $id)wp_delete_attachment($id,true);
        if(is_file($manifest))unlink($manifest);
    }
}
