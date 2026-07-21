<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Command;

/** @param list<array<string,mixed>> $modifiers */
final class SaveDraftOfferModeModifiers
{
    public function __construct(
        public readonly int $priceBookId,
        public readonly int $expectedBookVersion,
        public readonly array $modifiers
    ) {
    }
}
