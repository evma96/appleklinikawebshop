<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Pricing;

/** A sellable iPhone model/storage pair owned by the Inventory catalogue. */
final class DeviceCatalogConfiguration
{
    public function __construct(
        public readonly string $modelKey,
        public readonly string $modelLabel,
        public readonly int $storageGb
    ) {
    }
}
