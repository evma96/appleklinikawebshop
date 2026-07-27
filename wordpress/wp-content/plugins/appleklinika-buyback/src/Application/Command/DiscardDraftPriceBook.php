<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Command;

final class DiscardDraftPriceBook
{
    public function __construct(
        public readonly int $priceBookId,
        public readonly string $confirmationName,
        public readonly int $actorId = 0
    ) {
    }
}
