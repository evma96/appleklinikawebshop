<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Pricing;

use AppleKlinika\Buyback\Domain\Pricing\PriceBook;

final class PriceBookPage
{
    /** @param list<PriceBook> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $total
    ) {
    }
}
