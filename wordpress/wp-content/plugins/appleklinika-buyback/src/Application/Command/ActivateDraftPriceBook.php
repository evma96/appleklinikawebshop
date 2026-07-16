<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Command;

final class ActivateDraftPriceBook
{
    public const CONFIRMATION = 'AKTIVÁLOM';

    public function __construct(
        public readonly int $priceBookId,
        public readonly int $expectedVersion,
        public readonly int $actorId,
        public readonly string $confirmation
    ) {
    }
}
