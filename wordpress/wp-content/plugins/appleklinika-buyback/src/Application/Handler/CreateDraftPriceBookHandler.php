<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Command\CreateDraftPriceBook;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;
use AppleKlinika\Buyback\Domain\Pricing\MinimumOfferPolicy;
use AppleKlinika\Buyback\Domain\Pricing\PriceBook;
use AppleKlinika\Buyback\Domain\Pricing\PricingActorId;
use AppleKlinika\Buyback\Domain\Shared\Money;

final class CreateDraftPriceBookHandler
{
    public function __construct(
        private readonly PriceBookRepository $repository,
        private readonly TransactionManager $transactions,
        private readonly Clock $clock
    ) {
    }

    public function handle(CreateDraftPriceBook $command): PriceBook
    {
        return $this->transactions->transactional(function () use ($command): PriceBook {
            $book = PriceBook::createDraft(
                $this->repository->nextAvailableVersionNumber(),
                trim($command->label),
                new CurrencyCode('HUF'),
                new Money($command->minimumOfferMinor, 'HUF'),
                $command->roundingIncrementMinor,
                new MinimumOfferPolicy($command->minimumPolicy),
                new PricingActorId($command->actorId),
                $this->clock->now()
            );

            return $this->repository->createDraft($book);
        });
    }
}
