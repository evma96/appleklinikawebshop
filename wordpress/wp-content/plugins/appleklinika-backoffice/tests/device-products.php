<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/intake-extensions.php';
$GLOBALS['options']=[];
function remove_accents(string $v):string{return iconv('UTF-8','ASCII//TRANSLIT',$v)?:$v;}
function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
function update_option($k,$v,$a=null){$GLOBALS['options'][$k]=$v;}
function get_post_meta($id,$key,$single=true){return '';}
spl_autoload_register(static function(string $class):void{
    foreach(['Appleklinika\\Inventory\\'=>dirname(__DIR__,2).'/appleklinika-inventory/src/','Appleklinika\\BackOffice\\'=>dirname(__DIR__).'/src/'] as $prefix=>$root){if(str_starts_with($class,$prefix)){$file=$root.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require_once $file;}}
});
use Appleklinika\Inventory\Application\DeviceProduct\{DeviceOfferInput,DeviceProductStore,DeviceProductService,SaveDeviceProductCommand,DeviceIntakeSchema};
use Appleklinika\Inventory\Application\ProductCondition\SaveProductConditionHandler;
use Appleklinika\Inventory\Infrastructure\WordPress\{WooProductConditionRepository,DeviceCatalogRepository};
use Appleklinika\BackOffice\Application\{UpdateDeviceOffer,ListDeviceProducts,Port\DeviceProducts};
$n=0;$check=static function(bool $ok,string $label)use(&$n):void{if(!$ok)throw new RuntimeException($label);$n++;};
$reject=static function(callable $f,string $label)use($check):void{try{$f();}catch(InvalidArgumentException){$check(true,$label);return;}$check(false,$label);};
$store=new class implements DeviceProductStore{
 public array $data=[];public array $plans=[];
 public function load(int $id):array{return $this->data;}
 public function catalog():array{return(new DeviceCatalogRepository())->all();}
 public function assertUnique(string $sku,string $imei,string $serial,int $except):void{}
 public function validatePhotos(array $ids,int $id):void{}
 public function persist(int $id,array $plan):int{$this->plans[]=$plan;return $id;}
 public function synchronized(callable $operation):mixed{return $operation();}
};
$store->data=(new DeviceIntakeSchema())->validate(['device_type'=>'iphone','device_model'=>'iphone_12_pro','storage_capacity'=>'256_gb','color'=>'graphite','sim_config'=>'physical_esim','sku'=>'TEST-SERVICE','imei'=>'000000000000099','serial_number'=>'TEST-SERIAL','regular_price'=>'129990','sale_price'=>'99990','overall_grade'=>'a'])+['photos'=>[11,12,13]];
$condition=new SaveProductConditionHandler(new WooProductConditionRepository(),new DeviceCatalogRepository());
$service=class_exists(\Appleklinika\Inventory\Infrastructure\WordPress\WordPressModelSpecifications::class)
 ?new DeviceProductService($store,$condition,new \Appleklinika\Inventory\Infrastructure\WordPress\WordPressModelSpecifications())
 :new DeviceProductService($store,$condition);
foreach(['','0','99990'] as $sale){
 $plan=$service->prepare(new SaveDeviceProductCommand(123,DeviceOfferInput::validate(['regular_price'=>'129990','sale_price'=>$sale,'overall_grade'=>'b'])));
 foreach(['sku','imei','serial_number','status','stock','photos','custom_name'] as $key)$check($plan[$key]===$store->data[$key],'Offer patch preserves '.$key);
 $check($plan['regular_price']==='129990'&&$plan['sale_price']===$sale,'Canonical optional sale contract');
 $check($plan['inventory']['overall_grade']==='b'&&$plan['inventory']['device_model']==='iphone_12_pro','Condition changes without changing model');
}
foreach(['129990','130000','-1','invalid'] as $sale){$reject(fn()=>$service->save(new SaveDeviceProductCommand(123,['sale_price'=>$sale])),'Shared service rejects invalid sale before persist');}
$check($store->plans===[],'Rejected plans never reach store');
foreach(['129990','130000','-1','NaN','1e3',[], '1.234'] as $sale)$reject(fn()=>DeviceOfferInput::validate(['regular_price'=>'129990','sale_price'=>$sale,'overall_grade'=>'a']),'Staff validation rejects malformed/non-lower sale');
$store->data['custom_name']=true;$store->data['title']='TEST custom title';
$plan=$service->prepare(new SaveDeviceProductCommand(123,['sale_price'=>'99000']));$check($plan['title']==='TEST custom title','Price edits preserve custom title');
$port=new class implements DeviceProducts{public array $calls=[];public function listing(int $p,string $s,string $state):array{$this->calls[]=[$p,$s,$state];return ['items'=>[]];}public function detail(int $id):array{return [];}public function publish(int $id,string $expected):void{$this->calls[]=[$id,$expected];}public function updateOffer(int $id,array $input,string $expected):void{$this->calls[]=[$id,$input,$expected];}};
$command=new UpdateDeviceOffer($port);$token=str_repeat('a',64);
$command->execute(123,['regular_price'=>'100','sale_price'=>'90','overall_grade'=>'a','stock'=>99,'status'=>'publish','sku'=>'INJECTED','photos'=>[999]],$token);
$check(array_keys($port->calls[0][1])===['regular_price','sale_price','overall_grade'],'Edit command rejects identity/stock/photo/status injection');
$reject(fn()=>$command->execute(0,[],$token),'Reject missing product');$reject(fn()=>$command->execute(123,[],'bad'),'Reject invalid version');
(new ListDeviceProducts($port))->execute(-8,'  TEST  ','draft');$check($port->calls[1]===[1,'TEST','draft'],'List command normalizes filters and pagination');
$publish=new \Appleklinika\BackOffice\Application\PublishDevice($port);
$reject(fn()=>$publish->execute(0,$token),'Publication requires an existing product');
$reject(fn()=>$publish->execute(123,'bad'),'Publication requires a valid version');
$publish->execute(123,$token);$check(end($port->calls)===[123,$token],'Publication command preserves product ID and concurrency guard');
echo "Device product service/list/edit contract: $n assertions passed; no database or external calls.\n";
