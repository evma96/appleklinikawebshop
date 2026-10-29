<?php
declare(strict_types=1);
namespace Appleklinika\Inventory\Application\DeviceProduct;
use Appleklinika\Inventory\Application\ProductCondition\{SaveProductConditionCommand,SaveProductConditionHandler};
use Appleklinika\Inventory\Domain\DeviceProduct\PhysicalDevice;
use InvalidArgumentException;
use Appleklinika\Inventory\Application\ModelSpecification\ModelSpecifications;
final class DeviceProductService
{
    public function __construct(private readonly DeviceProductStore $store,private readonly SaveProductConditionHandler $condition, private readonly ModelSpecifications $specifications) {}
    /** Shared by every entry UI; no technical data is copied into a physical device. */
    public function technicalProfileStatus(string $modelKey): array
    {
        $profile=$this->specifications->resolve($modelKey);
        return ['model_key'=>$profile['model_key'],'status'=>$profile['status'],'warnings'=>$profile['warnings']];
    }
    public function save(SaveDeviceProductCommand $command): int
    {
        return $this->store->synchronized(fn()=> $this->store->persist($command->productId,$this->prepare($command)));
    }
    /** Validates a complete plan before the Woo adapter writes any product data. */
    public function prepare(SaveDeviceProductCommand $command): array
    {
        $prior=$command->productId>0?$this->store->load($command->productId):[];
        $data=array_replace($prior,$command->input);
        $key=PhysicalDevice::text($data['device_model']??'');$model=null;
        foreach($this->store->catalog() as $entry)if(($entry['key']??'')===$key){$model=$entry;break;}
        if(!$model)throw new InvalidArgumentException('Válassz létező Apple modellt.');
        $type=PhysicalDevice::type((string)$model['type']);
        if(!in_array($type,['iphone','ipad','macbook','apple_watch'],true))throw new InvalidArgumentException('Nem támogatott készüléktípus.');
        if(!empty($data['device_type'])&&PhysicalDevice::type(PhysicalDevice::text($data['device_type']))!==$type)throw new InvalidArgumentException('A készüléktípus és a modell nem egyezik.');
        PhysicalDevice::assertNotAccessory($key,PhysicalDevice::text($data['protection_group']??''));
        $data['device_type']=$type;
        $data['status']=$data['status']??'draft';
        if(!in_array($data['status'],['publish','draft','pending','private'],true))throw new InvalidArgumentException('Érvénytelen termékállapot.');
        $data['custom_name']=filter_var($data['custom_name']??false,FILTER_VALIDATE_BOOLEAN);
        $data['title']=PhysicalDevice::title($data,$model);
        foreach(['sku','imei','serial_number'] as $key)$data[$key]=PhysicalDevice::text($data[$key]??'');
        if(mb_strlen($data['sku'])>100)throw new InvalidArgumentException('A Cikkszám legfeljebb 100 karakter lehet.');
        $data['stock']=PhysicalDevice::quantity($data['stock']??1);
        $data['photos']=PhysicalDevice::photos((array)($data['photos']??[]));
        foreach(['regular_price','sale_price'] as $key){
            $value=PhysicalDevice::text($data[$key]??'');
            if($value!==''&&(!is_numeric($value)||!is_finite((float)$value)||(float)$value<0))throw new InvalidArgumentException('Érvénytelen készülékár.');
            $data[$key]=$value;
        }
        if($data['status']==='publish')PhysicalDevice::assertPublication($data);
        if($data['sale_price']!==''&&(float)$data['sale_price']>=(float)$data['regular_price'])throw new InvalidArgumentException('Az akciós árnak kisebbnek kell lennie a normál árnál.');
        $this->store->assertUnique($data['sku'],$data['imei'],$data['serial_number'],$command->productId);
        $this->store->validatePhotos($data['photos'],$command->productId);
        $inventory=$this->condition->prepare(new SaveProductConditionCommand($command->productId,$data));
        if(($data['storage_capacity']??'')!==''&&$inventory['storage_capacity']!==$data['storage_capacity'])throw new InvalidArgumentException('A kiválasztott tárhely nem tartozik ehhez a modellhez.');
        if (($data['color']??'')!=='' && ($inventory['color']!==$data['color'] || (!empty($model['colors']) && !array_key_exists($data['color'],$model['colors'])))) throw new InvalidArgumentException('A kiválasztott szín nem tartozik ehhez a modellhez.');
        return ['technical_profile'=>$this->technicalProfileStatus($inventory['device_model']),'inventory'=>$inventory,'title'=>$data['title'],'custom_name'=>$data['custom_name'],'sku'=>$data['sku'],'imei'=>$data['imei'],'serial_number'=>$data['serial_number'],
            'status'=>$data['status'],'regular_price'=>$data['regular_price'],'sale_price'=>$data['sale_price'],'stock'=>$data['stock'],'photos'=>$data['photos']];
    }
}
