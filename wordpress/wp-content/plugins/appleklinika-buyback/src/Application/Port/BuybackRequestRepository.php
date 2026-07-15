<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

use AppleKlinika\Buyback\Application\Command\NewBuybackRequest;
use AppleKlinika\Buyback\Application\Query\BuybackRequestPage;
use AppleKlinika\Buyback\Application\Query\PageRequest;
use AppleKlinika\Buyback\Domain\Buyback\BuybackRequest;
use AppleKlinika\Buyback\Domain\Buyback\BuybackRequestId;
use AppleKlinika\Buyback\Domain\Buyback\BuybackStatus;
use AppleKlinika\Buyback\Domain\Buyback\CustomerId;
use AppleKlinika\Buyback\Domain\Buyback\LegacyReference;
use AppleKlinika\Buyback\Domain\Buyback\RequestNumber;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;

interface BuybackRequestRepository
{
    public function insert(NewBuybackRequest $request): BuybackRequest;

    public function getById(BuybackRequestId $id): ?BuybackRequest;

    public function getByRequestNumber(RequestNumber $requestNumber): ?BuybackRequest;

    public function save(BuybackRequest $request, AggregateVersion $expectedVersion): void;

    public function findByCustomer(CustomerId $customerId, PageRequest $page): BuybackRequestPage;

    public function findByStatus(BuybackStatus $status, PageRequest $page): BuybackRequestPage;

    public function existsByRequestNumber(RequestNumber $requestNumber): bool;

    public function existsByLegacyReference(LegacyReference $legacyReference): bool;
}
