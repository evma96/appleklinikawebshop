<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;

interface DraftPriceBookDiscardRepository
{
    public function hasBusinessReferences(PriceBookId $priceBookId): bool;

    /** Returns the number of exclusively owned rules removed with the draft. */
    public function discardDraftWithRules(PriceBookId $priceBookId): int;
}
