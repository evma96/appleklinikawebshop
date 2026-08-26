<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Command;

/** A null amount removes the model-specific override and restores inheritance. */
final class SaveDraftModelMinimumOffer
{
    public function __construct(
        public readonly int $priceBookId,
        public readonly int $expectedBookVersion,
        public readonly string $modelKey,
        public readonly ?int $amountMinor
    ) {
    }
}
