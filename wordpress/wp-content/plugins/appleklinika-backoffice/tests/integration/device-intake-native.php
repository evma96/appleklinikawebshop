<?php
/** LOCAL integration boundary: Back Office command -> current shared Inventory -> native Woo. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__, 5).'/wp-load.php';
if (wp_get_environment_type() !== 'local' || getenv('AK_LOCAL_VERIFICATION') !== '1'
    || !defined('WP_HTTP_BLOCK_EXTERNAL') || !WP_HTTP_BLOCK_EXTERNAL) throw new RuntimeException('Isolated LOCAL only.');
use Appleklinika\BackOffice\Application\IntakeDevice;
use Appleklinika\BackOffice\Infrastructure\{SharedDeviceIntakeGateway, CanonicalRuntime};
use Appleklinika\Inventory\Application\DeviceProduct\DeviceIntakeSchema;
use Appleklinika\Inventory\Infrastructure\WordPress\WordPressModelSpecifications;
require_once ABSPATH.'wp-admin/includes/image.php';
$n=0;$products=[];$media=[];$http=$mail=0;
$check=static function(bool $ok,string $label)use(&$n):void{++$n;if(!$ok)throw new RuntimeException($label);};
add_filter('pre_http_request',static function()use(&$http){++$http;return new WP_Error('local_blocked');},PHP_INT_MAX);
add_filter('pre_wp_mail',static function()use(&$mail){++$mail;return false;},PHP_INT_MAX);
$before=wc_get_products(['limit'=>-1,'return'=>'ids','status'=>array_keys(get_post_stati())]);sort($before);
$schema=new DeviceIntakeSchema();$specs=new WordPressModelSpecifications();
$photos=new class($media) implements \Appleklinika\BackOffice\Application\Port\DevicePhotos {
    public function __construct(private array &$owned) {}
    public function ingest(array $files,string $operation):array {
        $path=wp_upload_dir()['basedir'].'/ak-native-intake-'.$operation.'.jpg';
        $image=new Imagick();$image->newImage(300,400,new ImagickPixel('#eeeeee'));$image->setImageFormat('jpeg');$image->writeImage($path);$image->clear();
        $id=wp_insert_attachment(['post_title'=>'LOCAL native intake fixture','post_mime_type'=>'image/jpeg','post_status'=>'inherit'],$path);
        $this->owned[]=$id;wp_update_attachment_metadata($id,wp_generate_attachment_metadata($id,$path));return [$id];
    }
    public function remove(array $ids):void{foreach($ids as $id)wp_delete_attachment($id,true);}
};
$gateway=new SharedDeviceIntakeGateway($photos);$command=new IntakeDevice($gateway);
$activityKey=(new ReflectionMethod(\Appleklinika\BackOffice\Infrastructure\WooOrderBackOfficeRepository::class,'dailyActivityTransientKey'))->invoke(new \Appleklinika\BackOffice\Infrastructure\WooOrderBackOfficeRepository());
$activity=get_transient($activityKey);
$activityExpiry=(int)get_option('_transient_timeout_'.$activityKey);
try {
    $check(CanonicalRuntime::productWritesAllowed(),'Explicit LOCAL product writes enabled');
    foreach(['iphone','ipad','macbook','apple_watch'] as $family) {
        $model=array_filter($schema->models(),static fn($model)=>$model['type']===$family);
        $key=array_key_first($model);$model=$model[$key];
        foreach(['draft','publish','next'] as $mode) {
            $operation=hash('sha256','native-integration-'.$family.'-'.$mode);
            $input=['device_type'=>$family,'device_model'=>$key,'sku'=>'AK-INTEGRATION-'.strtoupper($family.'-'.$mode),'serial_number'=>'QA-'.$operation,'regular_price'=>'139990','overall_grade'=>'a'];
            foreach($model['fields'] as $field=>$definition)$input[$field]=array_key_first($definition['options']);
            if($model['colors_by_material'])$input['color']=$model['colors_by_material'][$input['case_material']][0];
            $result=$command->execute($input,[],$operation,1,$mode);$id=$result['id'];$products[]=$id;$p=wc_get_product($id);
            $check($p->get_status()===($mode==='publish'?'publish':'draft'),'Explicit save mode preserved');
            $check($p->get_manage_stock()&&$p->get_stock_quantity()===1&&$p->is_sold_individually(),'One native physical unit');
            $check($p->get_sku()===$input['sku']&&$p->get_meta('_appleklinika_serial_number')===$input['serial_number'],'Native SKU and private identity');
            $check($p->get_meta('_ak_official_specs_status')==='ready'&&$specs->resolve($key)['status']==='ready','Current model registry is used');
            $check($result['image_count']===1&&$p->get_image_id()>0,'Current shared photo/store adapter');
            $check($result['price']==='139990'&&$result['name']===$p->get_name(),'Shared naming and price');
            $again=$command->execute($input,[],$operation,1,$mode);
            $check($again['id']===$id&&!empty($again['replayed']),'Retry reuses the same native product');
            $check(!isset($result['similar']['sku'],$result['similar']['serial_number']),'Save-next excludes unique identities');
            $check(get_post_meta($id,'_akbo_intake_employee',true)==1,'Staff attribution persisted');
        }
    }
    $check(count($media)===12,'Replay never uploads duplicate media');
    $check($http===0&&$mail===0,'No provider or email call');
} finally {
    foreach($products as $id)if($p=wc_get_product($id))$p->delete(true);
    foreach($media as $id)wp_delete_attachment($id,true);
    if($activity===false)delete_transient($activityKey);else set_transient($activityKey,$activity,max(1,$activityExpiry-time()));
}
$after=wc_get_products(['limit'=>-1,'return'=>'ids','status'=>array_keys(get_post_stati())]);sort($after);
$check($before===$after,'All temporary products removed, original product IDs preserved');
echo "Native intake integration: {$n} assertions passed; four families, three save modes, replay, cleanup; no providers/mail.\n";
