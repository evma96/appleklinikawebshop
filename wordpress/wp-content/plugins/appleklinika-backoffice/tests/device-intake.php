<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/intake-extensions.php';
// Offline canonical contract: the real catalog defaults with in-memory WP options only.
$GLOBALS['options']=[];
function remove_accents(string $value): string { return iconv('UTF-8','ASCII//TRANSLIT',$value) ?: $value; }
function get_option($key,$default=false) { return $GLOBALS['options'][$key]??$default; }
function update_option($key,$value,$autoload=null) { $GLOBALS['options'][$key]=$value; }
spl_autoload_register(static function(string $class):void {
    foreach(['Appleklinika\\Inventory\\'=>dirname(__DIR__,2).'/appleklinika-inventory/src/','Appleklinika\\BackOffice\\'=>dirname(__DIR__).'/src/'] as $prefix=>$root){
        if(str_starts_with($class,$prefix)){ $file=$root.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require_once $file; }
    }
});
use Appleklinika\Inventory\Application\DeviceProduct\DeviceIntakeSchema;
use Appleklinika\Inventory\Domain\DeviceProduct\PhysicalDevice;
use Appleklinika\BackOffice\Application\{IntakeDevice,Port\DeviceIntakeGateway};
$n=0;
$check=static function(bool $ok,string $label)use(&$n):void{if(!$ok)throw new RuntimeException($label);$n++;};
$reject=static function(callable $call,string $label)use($check):void{try{$call();}catch(InvalidArgumentException){$check(true,$label);return;}$check(false,$label);};
$schema=new DeviceIntakeSchema();$description=$schema->describe();
foreach($description['models'] as $key=>$model){
    $input=['device_model'=>$key,'device_type'=>$model['type'],'sku'=>'STORE-1','imei'=>'','serial_number'=>'SERIAL-1','regular_price'=>'129990','overall_grade'=>'a'];
    foreach($model['fields'] as $field=>$definition)$input[$field]=array_key_first($definition['options']);
    if($model['colors_by_material'])$input['color']=$model['colors_by_material'][$input['case_material']][0];
    $valid=$schema->validate($input);
    $check($valid['stock']===1&&$valid['status']==='draft','Normal intake fixed native draft and one stock');
    $check(!array_intersect(['sku','imei','serial_number','regular_price','sale_price','battery_health','title','custom_name','overall_grade'],array_keys(DeviceIntakeSchema::similar($valid))),'Unique fields excluded from similar for every model');
    $check(($model['type']==='apple_watch')===!isset($model['fields']['storage_capacity']),'Watch has no irrelevant storage control');
    $check(($model['type']==='macbook')===isset($model['fields']['ram_size']),'Only Mac has RAM configuration');
    foreach($model['fields'] as $field=>$definition){$bad=$input;$bad[$field]='unsupported';$reject(fn()=>$schema->validate($bad),'Reject unsupported model option '.$field);}
}
$input=['device_type'=>'iphone','device_model'=>'iphone_12_pro','storage_capacity'=>'256_gb','color'=>'graphite','sim_config'=>'physical_esim','sku'=>'STORE-1','imei'=>'123','serial_number'=>'SERIAL-1','regular_price'=>'129990','overall_grade'=>'a'];
$check($schema->validate($input)['sale_price']==='', 'Omitted sale price keeps existing regular-only intake');
foreach ([''=>'', '   '=>'', '0'=>'0', '99990'=>'99990', '129989.99'=>'129989.99', '129989,99'=>'129989.99'] as $entered=>$expected) {
    $valid=$schema->validate($input+['sale_price'=>(string)$entered]);
    $check($valid['sale_price']===$expected && $valid['regular_price']===$input['regular_price'], 'Optional sale price accepted without changing either amount');
    $similar=DeviceIntakeSchema::similar($valid);
    $check(!isset($similar['regular_price'])&&!isset($similar['sale_price']), 'Neither price is reusable for similar devices');
}
foreach (['129990','129991','-1','not-a-price','1e3','INF','NaN','999.999','10000000000',['nested']] as $sale) {
    $reject(fn()=>$schema->validate($input+['sale_price'=>$sale]), 'Invalid or non-lower sale price rejected');
}
foreach(['sku'=>'','regular_price'=>'0','battery_health'=>'101','storage_capacity'=>'8_tb','device_type'=>'ipad','overall_grade'=>'z'] as $field=>$value){$bad=$input;$bad[$field]=$value;$reject(fn()=>$schema->validate($bad),'Reject invalid '.$field);}
$bad=$input;$bad['imei']=$bad['serial_number']='';$reject(fn()=>$schema->validate($bad),'Missing both private identities rejected');
$bad=$input;$bad['sku']=['nested'];$reject(fn()=>$schema->validate($bad),'Nested scalar injection rejected');
$valid=$schema->validate($input+['status'=>'publish','stock'=>50,'photos'=>[999],'attachment_path'=>'/etc/passwd','arbitrary_meta'=>'secret']);
$check($valid['stock']===1&&$valid['status']==='draft'&&!isset($valid['photos'],$valid['attachment_path'],$valid['arbitrary_meta']),'Technical and arbitrary keys cannot override intake contract');
$valid=$schema->validate($input+['title'=>'Stale override']);$check(!isset($valid['title'])&&!$valid['custom_name'],'Custom name OFF ignores stale override');
$valid=$schema->validate($input+['title'=>'Custom','custom_name'=>true]);$check($valid['title']==='Custom'&&$valid['custom_name'],'Custom name ON retains canonical model');
$store=new class implements DeviceIntakeGateway{public array $calls=[];public function create(array $input,array $files,string $operation,int $employee,string $saveMode='draft'):array{$this->calls[]=[$input,$files,$operation,$employee,$saveMode];return ['id'=>123];}};
$command=new IntakeDevice($store);
$reject(fn()=>$command->execute([],[],'invalid',1),'Bad replay key rejected');$reject(fn()=>$command->execute([],[],str_repeat('a',64),0),'Unauthenticated actor rejected');
$check($command->execute($input,[],str_repeat('a',64),1)['id']===123&&count($store->calls)===1,'Application forwards authorized command once');
// Explicit publication intent never comes from the untrusted device payload.
foreach(\Appleklinika\BackOffice\Domain\DeviceIntakeSaveMode::cases() as $mode){
    $result=$schema->validate($input+['status'=>'publish','stock'=>99,'manage_stock'=>false,'sold_individually'=>false],$mode->status());
    $check($result['stock']===1&&$result['status']===$mode->status(),'Server owns stock and publication for '.$mode->value);
    $command->execute($input,[],str_repeat('b',64),1,$mode->value);
    $check(end($store->calls)[4]===$mode->value,'Explicit mode forwarded independently of device data');
}
$check(\Appleklinika\BackOffice\Domain\DeviceIntakeSaveMode::Next->status()==='draft','Save and next can never publish');
$reject(fn()=>$command->execute($input,[],str_repeat('a',64),1,'invalid'),'Unknown save intent rejected');
$reject(fn()=>$schema->validate($input,'private'),'Unsupported intake publication state rejected');
\Appleklinika\BackOffice\Domain\DeviceIntakeInventory::assertNewUnit(true,1,true,'instock');
foreach([[false,1,true,'instock'],[true,0,true,'outofstock'],[true,2,true,'instock'],[true,null,true,'instock'],[true,1,false,'instock'],[true,1,true,'outofstock']] as $bad)$reject(fn()=>\Appleklinika\BackOffice\Domain\DeviceIntakeInventory::assertNewUnit(...$bad),'New unit inventory invariant enforced');
$unsafe=['imei'=>'other','serial_number'=>'other','sku'=>'other','photos'=>[1],'custom_name'=>true,'title'=>'other','internal_note'=>'other','internal_identifier'=>'other','product_id'=>42,'id'=>42,'regular_price'=>'1','sale_price'=>'0','overall_grade'=>'a','battery_health'=>'99'];
$check(DeviceIntakeSchema::similar($unsafe)===[],'Every physical identity/value/unsupported unique note fails the reusable allowlist');
echo "Device intake contract: $n assertions passed; no database or external calls.\n";
