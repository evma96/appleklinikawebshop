<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Command;

final class DiscardDraftPriceBook
{
    public const CONFIRMATION = 'DISCARD_DRAFT_PRICE_BOOK';

    public function __construct(
        public readonly int $priceBookId,
        public readonly string $confirmation
    ) {
    }
}
