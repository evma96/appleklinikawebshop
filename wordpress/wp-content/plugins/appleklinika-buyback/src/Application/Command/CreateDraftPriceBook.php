<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Command;

final class CreateDraftPriceBook
{
    public function __construct(
        public readonly string $label,
        public readonly int $minimumOfferMinor,
        public readonly int $roundingIncrementMinor,
        public readonly string $minimumPolicy,
        public readonly int $actorId
    ) {
    }
}
