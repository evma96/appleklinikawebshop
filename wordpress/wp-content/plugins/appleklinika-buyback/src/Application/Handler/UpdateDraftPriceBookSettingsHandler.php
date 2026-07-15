<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Command\UpdateDraftPriceBookSettings;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Domain\Pricing\MinimumOfferPolicy;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Domain\Shared\Money;

final class UpdateDraftPriceBookSettingsHandler
{
    public function __construct(private readonly PriceBookRepository $repository, private readonly Clock $clock)
    {
    }

    public function handle(UpdateDraftPriceBookSettings $command): void
    {
        $id = new PriceBookId($command->priceBookId);
        $book = $this->repository->getById($id);

        if ($book === null) {
            throw PriceBookNotFoundException::forId($id);
        }

        $book->updateSettings(
            trim($command->label),
            new Money($command->minimumOfferMinor, 'HUF'),
            $command->roundingIncrementMinor,
            new MinimumOfferPolicy($command->minimumPolicy),
            $this->clock->now()
        );
        $this->repository->saveDraft($book, new AggregateVersion($command->expectedVersion));
    }
}
