<?php
declare(strict_types=1);
namespace Appleklinika\Inventory\Infrastructure\WordPress;
use Appleklinika\Inventory\Domain\DeviceProduct\PhysicalDevice;
final class DeviceOrderIdentity
{
    public const CAPTURED='_appleklinika_device_identity_captured';
    public const KEYS=['_appleklinika_imei','_appleklinika_serial_number','_appleklinika_sku',PhysicalDevice::MARKER,self::CAPTURED];
    public function capture(\WC_Order_Item_Product $item): void
    {
        if($item->get_meta(self::CAPTURED)==='yes')return;
        $p=$item->get_product();if(!$p||$p->get_meta('_appleklinika_device_model')===''||$p->get_meta('_appleklinika_protection_group')!=='')return;
        foreach(['imei','serial_number'] as $key)$item->update_meta_data('_appleklinika_'.$key,(string)$p->get_meta('_appleklinika_'.$key));
        $item->update_meta_data('_appleklinika_sku',$p->get_sku());$item->update_meta_data(PhysicalDevice::MARKER,'yes');$item->update_meta_data(self::CAPTURED,'yes');
    }
    public function lineNote(\WC_Order_Item_Product $item): string
    {
        return $item->get_meta(self::CAPTURED)==='yes'?PhysicalDevice::invoiceIdentity((string)$item->get_meta('_appleklinika_imei'),(string)$item->get_meta('_appleklinika_serial_number')):'';
    }
}
