<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Command;

/** @param list<array<string,mixed>> $bands */
final class SaveDraftBatteryBands
{
    public function __construct(
        public readonly int $priceBookId,
        public readonly int $expectedBookVersion,
        public readonly string $modelKey,
        public readonly array $bands
    ) {
    }
}
