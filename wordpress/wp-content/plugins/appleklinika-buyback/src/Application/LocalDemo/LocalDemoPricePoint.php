<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\LocalDemo;

final class LocalDemoPricePoint
{
    public function __construct(
        public readonly string $modelKey,
        public readonly string $modelLabel,
        public readonly int $storageGb,
        public readonly int $representativePrice,
        public readonly int $basePrice
    ) {
    }
}
