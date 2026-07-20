<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Command;

final class ClonePriceBookToDraft
{
    public function __construct(
        public readonly int $sourcePriceBookId,
        public readonly int $actorId
    ) {
    }
}
