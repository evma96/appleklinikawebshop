<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

use AppleKlinika\Buyback\Application\Pricing\DeviceCatalogItem;
use AppleKlinika\Buyback\Application\Pricing\DeviceCatalogConfiguration;

interface DeviceCatalogReader
{
    /** @return list<DeviceCatalogItem> */
    public function iPhoneModels(): array;

    /** @return list<DeviceCatalogConfiguration> */
    public function iPhoneConfigurations(): array;
}
