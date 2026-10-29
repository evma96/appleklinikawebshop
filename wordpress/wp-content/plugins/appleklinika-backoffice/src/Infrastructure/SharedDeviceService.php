<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

use Appleklinika\Inventory\Application\DeviceProduct\DeviceProductService;
use Appleklinika\Inventory\Application\ProductCondition\SaveProductConditionHandler;
use Appleklinika\Inventory\Infrastructure\WordPress\{WooDeviceProductStore,WooProductConditionRepository,DeviceCatalogRepository,WordPressModelSpecifications};

/** Compose the installed shared Inventory service, including its model registry when available. */
final class SharedDeviceService
{
    public static function create(WooDeviceProductStore $store): DeviceProductService
    {
        $condition=new SaveProductConditionHandler(new WooProductConditionRepository(),new DeviceCatalogRepository());
        return class_exists(WordPressModelSpecifications::class)
            ? new DeviceProductService($store,$condition,new WordPressModelSpecifications())
            : new DeviceProductService($store,$condition);
    }
}
