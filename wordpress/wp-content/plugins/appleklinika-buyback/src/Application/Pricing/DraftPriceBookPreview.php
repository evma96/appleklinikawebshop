<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Pricing;

use AppleKlinika\Buyback\Domain\Pricing\PriceBook;

final class DraftPriceBookPreview
{
    /** @param array<string, \AppleKlinika\Buyback\Domain\Pricing\PricingCalculationResult> $modeResults */
    public function __construct(
        public readonly PriceBook $priceBook,
        public readonly string $modelKey,
        public readonly int $storageGb,
        public readonly array $modeResults
    ) {
    }
}
