<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

use AppleKlinika\Buyback\Application\Pricing\PriceBookPage;
use AppleKlinika\Buyback\Domain\Pricing\PriceBook;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookStatus;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookVersionNumber;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;

interface PriceBookRepository
{
    public function createDraft(PriceBook $priceBook): PriceBook;

    public function getById(PriceBookId $id): ?PriceBook;

    public function getByVersionNumber(PriceBookVersionNumber $number): ?PriceBook;

    public function list(int $page, int $perPage, ?PriceBookStatus $status = null): PriceBookPage;

    public function saveDraft(PriceBook $priceBook, AggregateVersion $expectedVersion): void;

    public function nextAvailableVersionNumber(): PriceBookVersionNumber;

    public function hasActiveBook(): bool;
}
