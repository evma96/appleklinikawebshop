<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Command;

/**
 * The matrix holds only Inventory-authorized iPhone model/storage pairs.
 * Empty values remove the matching draft base-price rule.
 *
 * @param array<string,array<string,mixed>> $basePrices
 */
final class SaveDraftBasePriceMatrix
{
    public function __construct(
        public readonly int $priceBookId,
        public readonly int $expectedBookVersion,
        public readonly array $basePrices
    ) {
    }
}
