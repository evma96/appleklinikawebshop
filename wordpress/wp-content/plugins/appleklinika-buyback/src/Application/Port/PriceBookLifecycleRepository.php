<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;

interface PriceBookLifecycleRepository
{
    public function protectedReferenceFor(CurrencyCode $currency): ?PriceBookId;
    public function isProtected(PriceBookId $priceBookId): bool;
    public function moveProtectedReference(CurrencyCode $currency, PriceBookId $priceBookId, int $actorId, \DateTimeImmutable $at): ?PriceBookId;
    public function hasEverBeenActive(PriceBookId $priceBookId): bool;
    public function hasLifecycleDependencies(PriceBookId $priceBookId): bool;
    /** @param array<string,mixed> $payload */
    public function record(string $eventType, PriceBookId $priceBookId, int $actorId, array $payload, \DateTimeImmutable $at): void;
}
