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
                $this->cloneLabel($source),
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

    private function cloneLabel(PriceBook $source): string
    {
        $suffix = ' – Másolat v' . $source->versionNumber()->value();
        $sourceLabelBytes = PriceBook::MAX_LABEL_BYTES - strlen($suffix);

        if (strlen($source->label()) <= $sourceLabelBytes) {
            return $source->label() . $suffix;
        }

        return $this->utf8PrefixWithinByteLimit($source->label(), $sourceLabelBytes) . $suffix;
    }

    private function utf8PrefixWithinByteLimit(string $label, int $maximumBytes): string
    {
        if (function_exists('mb_strcut')) {
            return mb_strcut($label, 0, $maximumBytes, 'UTF-8');
        }

        $characters = preg_split('//u', $label, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            throw new \RuntimeException('Price-book label must be valid UTF-8.');
        }

        $prefix = '';
        foreach ($characters as $character) {
            if (strlen($prefix . $character) > $maximumBytes) {
                break;
            }
            $prefix .= $character;
        }

        return $prefix;
    }
}
