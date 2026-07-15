<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Command;

final class UpdateDraftPriceBookSettings
{
    public function __construct(
        public readonly int $priceBookId,
        public readonly int $expectedVersion,
        public readonly string $label,
        public readonly int $minimumOfferMinor,
        public readonly int $roundingIncrementMinor,
        public readonly string $minimumPolicy
    ) {
    }
}
