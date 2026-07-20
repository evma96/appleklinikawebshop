<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Command\ClonePriceBookToDraft;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\PricingRuleRepository;
use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Domain\Exception\InvalidAggregateOperationException;
use AppleKlinika\Buyback\Domain\Pricing\PriceBook;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PricingActorId;
use AppleKlinika\Buyback\Domain\Pricing\PricingRule;

/** Clones an immutable active book into a fully independent editable draft. */
final class ClonePriceBookToDraftHandler
{
    public function __construct(
        private readonly PriceBookRepository $books,
        private readonly PricingRuleRepository $rules,
        private readonly TransactionManager $transactions,
        private readonly Clock $clock
    ) {
    }

    public function handle(ClonePriceBookToDraft $command): PriceBook
    {
        return $this->transactions->transactional(function () use ($command): PriceBook {
            $sourceId = new PriceBookId($command->sourcePriceBookId);
            // The surrounding transaction makes creation and rule copying atomic.  A regular
            // repository read keeps the handler portable to existing repository instances
            // that do not expose row-locking support.
            $source = $this->books->getById($sourceId);
            if ($source === null) {
                throw PriceBookNotFoundException::forId($sourceId);
            }
            if (! $source->status()->isActive()) {
                throw new InvalidAggregateOperationException('Only the active price book may be copied into a new draft.');
            }

            $at = $this->clock->now();
            $draft = $this->books->createDraft(PriceBook::createDraft(
                $this->books->nextAvailableVersionNumber(),
                $source->label() . ' – Másolat v' . $source->versionNumber()->value(),
                $source->currency(),
                $source->minimumOffer(),
                $source->roundingIncrementMinor(),
                $source->minimumPolicy(),
                new PricingActorId($command->actorId),
                $at
            ));
            $draftId = $draft->id();
            if ($draftId === null) {
                throw new \RuntimeException('The cloned draft did not receive an identifier.');
            }

            foreach ($this->rules->listForPriceBook($sourceId) as $sourceRule) {
                $this->rules->insert(PricingRule::create($draftId, $sourceRule->definition(), $at));
            }

            return $draft;
        });
    }
}
