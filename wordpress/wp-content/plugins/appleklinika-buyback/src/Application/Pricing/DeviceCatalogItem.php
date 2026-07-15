<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Pricing;

final class DeviceCatalogItem
{
    public function __construct(
        public readonly string $modelKey,
        public readonly string $label
    ) {
    }
}
