<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

use AppleKlinika\Buyback\Application\Pricing\DeviceCatalogItem;

interface DeviceCatalogReader
{
    /** @return list<DeviceCatalogItem> */
    public function iPhoneModels(): array;
}
