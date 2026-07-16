<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

use AppleKlinika\Buyback\Application\Pricing\PriceBookPage;
use AppleKlinika\Buyback\Domain\Pricing\PriceBook;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookStatus;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookVersionNumber;
use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;

interface PriceBookRepository
{
    public function createDraft(PriceBook $priceBook): PriceBook;

    public function getById(PriceBookId $id): ?PriceBook;

    public function getByIdForUpdate(PriceBookId $id): ?PriceBook;

    public function getByVersionNumber(PriceBookVersionNumber $number): ?PriceBook;

    public function list(int $page, int $perPage, ?PriceBookStatus $status = null): PriceBookPage;

    public function saveDraft(PriceBook $priceBook, AggregateVersion $expectedVersion): void;

    public function saveActivated(PriceBook $priceBook, AggregateVersion $expectedVersion): void;

    public function saveRetired(PriceBook $priceBook, AggregateVersion $expectedVersion): void;

    public function nextAvailableVersionNumber(): PriceBookVersionNumber;

    public function hasActiveBook(): bool;

    /** @return list<PriceBook> */
    public function findCurrentActiveForCurrencyAt(CurrencyCode $currency, \DateTimeImmutable $at): array;

    /** @return list<PriceBook> */
    public function findCurrentActiveForCurrencyAtForUpdate(CurrencyCode $currency, \DateTimeImmutable $at): array;

    public function countCurrentActiveForCurrencyAt(CurrencyCode $currency, \DateTimeImmutable $at): int;
}
