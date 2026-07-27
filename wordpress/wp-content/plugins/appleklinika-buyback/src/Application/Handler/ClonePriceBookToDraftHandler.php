<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Command\ClonePriceBookToDraft;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\PriceBookLifecycleRepository;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\PricingRuleRepository;
use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Domain\Exception\StaleAggregateVersionException;
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
        private readonly Clock $clock,
        private readonly ?PriceBookLifecycleRepository $lifecycle = null
    ) {
    }

    public function handle(ClonePriceBookToDraft $command): PriceBook
    {
        return $this->transactions->transactional(function () use ($command): PriceBook {
            $sourceId = new PriceBookId($command->sourcePriceBookId);
            $source = $this->books->getByIdForUpdate($sourceId);
            if ($source === null) {
                throw PriceBookNotFoundException::forId($sourceId);
            }
            if ($source->version()->value() !== $command->expectedSourceVersion) {
                throw new StaleAggregateVersionException($command->expectedSourceVersion, $source->version()->value());
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

            $this->lifecycle?->record('draft_cloned', $draftId, $command->actorId, [
                'source_price_book_id' => $sourceId->toInt(),
                'source_version' => $source->version()->value(),
                'source_status' => $source->status()->code(),
            ], $at);

            return $draft;
        });
    }
}
