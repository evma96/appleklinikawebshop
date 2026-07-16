<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Benchmark;

use AppleKlinika\Buyback\Application\Exception\BenchmarkSeedConflictException;
use AppleKlinika\Buyback\Application\Exception\BenchmarkSeedReservationExistsException;
use AppleKlinika\Buyback\Application\Port\BenchmarkSeedRegistry;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\DeviceCatalogReader;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\PricingRuleRepository;
use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Domain\Benchmark\BenchmarkManifest;
use AppleKlinika\Buyback\Domain\Benchmark\BenchmarkRuleCanonicalizer;
use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;
use AppleKlinika\Buyback\Domain\Pricing\MinimumOfferPolicy;
use AppleKlinika\Buyback\Domain\Pricing\PriceBook;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PricingActorId;
use AppleKlinika\Buyback\Domain\Pricing\PricingRule;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Domain\Shared\Money;

final class BenchmarkPriceBookSeedService
{
    public function __construct(
        private readonly PriceBookRepository $books,
        private readonly PricingRuleRepository $rules,
        private readonly DeviceCatalogReader $catalog,
        private readonly BenchmarkSeedRegistry $registry,
        private readonly TransactionManager $transactions,
        private readonly Clock $clock
    ) {
    }

    public function plan(BenchmarkManifest $manifest): BenchmarkSeedPlan
    {
        if ($manifest->countKind(PricingRuleKind::BASE_PRICE) < 1) {
            throw new BenchmarkSeedConflictException('Benchmark manifest has no evidence-qualified base prices.');
        }

        $catalogKeys = [];
        foreach ($this->catalog->iPhoneModels() as $item) {
            $catalogKeys[$item->modelKey] = true;
        }
        foreach ($manifest->modelKeys() as $modelKey) {
            if (! isset($catalogKeys[$modelKey])) {
                throw new BenchmarkSeedConflictException("Benchmark model is absent from inventory catalog: {$modelKey}.");
            }
        }

        $registration = $this->registry->find($manifest->seedKey);
        $existingId = null;
        if ($registration !== null) {
            if (! hash_equals($registration->manifestHash, $manifest->hash())) {
                throw new BenchmarkSeedConflictException('The benchmark seed key is already registered to a different manifest.');
            }
            $existingId = $this->assertRegisteredDraftMatches($manifest, $registration);
        } elseif ($this->findBooksByLabel($manifest->label) !== []) {
            throw new BenchmarkSeedConflictException('A price book already uses this benchmark label without the matching seed registration.');
        }

        return new BenchmarkSeedPlan(
            $manifest->hash(),
            $manifest->manifestVersion,
            $manifest->seedKey,
            $manifest->label,
            count($manifest->modelKeys()),
            count($manifest->configurationKeys()),
            $manifest->countKind(PricingRuleKind::BASE_PRICE),
            $manifest->countKind(PricingRuleKind::FIXED_DEDUCTION) + $manifest->countKind(PricingRuleKind::MULTIPLIER),
            $manifest->countKind(PricingRuleKind::MODE_ADJUSTMENT),
            $manifest->countKind(PricingRuleKind::MANUAL_REVIEW),
            $manifest->countKind(PricingRuleKind::HARD_REJECT),
            count($manifest->rules),
            $existingId
        );
    }

    public function createDraft(BenchmarkManifest $manifest, int $actorId): BenchmarkSeedResult
    {
        if ($actorId < 1) {
            throw new BenchmarkSeedConflictException('Benchmark draft creation requires an authorized WordPress actor.');
        }

        $plan = $this->plan($manifest);
        if ($plan->existingPriceBookId !== null) {
            $book = $this->books->getById(new PriceBookId($plan->existingPriceBookId));
            if ($book === null) {
                throw new BenchmarkSeedConflictException('Registered benchmark draft cannot be reloaded.');
            }
            return new BenchmarkSeedResult($plan, $plan->existingPriceBookId, $book->versionNumber()->value(), false);
        }

        try {
            return $this->transactions->transactional(function () use ($manifest, $actorId, $plan): BenchmarkSeedResult {
                $this->registry->reserve($manifest->hash(), $manifest->seedKey, $manifest->label);
                if ($this->findBooksByLabel($manifest->label) !== []) {
                    throw new BenchmarkSeedConflictException('Benchmark label became occupied before draft creation.');
                }

                $at = $this->clock->now();
                $book = PriceBook::createDraft(
                    $this->books->nextAvailableVersionNumber(),
                    $manifest->label,
                    new CurrencyCode('HUF'),
                    new Money($manifest->minimumOfferMinor, 'HUF'),
                    $manifest->roundingIncrementMinor,
                    new MinimumOfferPolicy($manifest->minimumPolicy),
                    new PricingActorId($actorId),
                    $at
                );
                $book = $this->books->createDraft($book);
                if ($book->id() === null) {
                    throw new BenchmarkSeedConflictException('Benchmark draft did not receive an identity.');
                }

                foreach ($manifest->rules as $definition) {
                    $this->rules->insert(PricingRule::create($book->id(), $definition, $at));
                }
                $book->recordRuleMutation($at);
                $this->books->saveDraft($book, new AggregateVersion(0));
                $this->registry->complete($manifest->seedKey, $book->id()->toInt(), 1, $manifest->rulesHash());

                return new BenchmarkSeedResult($plan, $book->id()->toInt(), $book->versionNumber()->value(), true);
            });
        } catch (BenchmarkSeedReservationExistsException $exception) {
            $repeatPlan = $this->plan($manifest);
            if ($repeatPlan->existingPriceBookId === null) {
                throw $exception;
            }
            $book = $this->books->getById(new PriceBookId($repeatPlan->existingPriceBookId));
            if ($book === null) {
                throw new BenchmarkSeedConflictException('Concurrent benchmark seed registration has no draft.');
            }
            return new BenchmarkSeedResult($repeatPlan, $book->id()?->toInt() ?? 0, $book->versionNumber()->value(), false);
        }
    }

    private function assertRegisteredDraftMatches(BenchmarkManifest $manifest, BenchmarkSeedRegistration $registration): int
    {
        if ($registration->state !== 'complete' || $registration->priceBookId === null || $registration->aggregateVersion !== 1 || $registration->rulesHash !== $manifest->rulesHash()) {
            throw new BenchmarkSeedConflictException('Benchmark seed registration is incomplete or differs from the manifest.');
        }
        $book = $this->books->getById(new PriceBookId($registration->priceBookId));
        if ($book === null || ! $book->status()->isDraft() || $book->label() !== $manifest->label
            || $book->currency()->code() !== 'HUF' || $book->minimumOffer()->amount() !== $manifest->minimumOfferMinor
            || $book->roundingIncrementMinor() !== $manifest->roundingIncrementMinor
            || $book->minimumPolicy()->code() !== $manifest->minimumPolicy || $book->version()->value() !== 1) {
            throw new BenchmarkSeedConflictException('Existing benchmark draft settings or aggregate version were edited.');
        }
        $rules = $this->rules->listForPriceBook($book->id());
        foreach ($rules as $rule) {
            if ($rule->version()->value() !== 0) {
                throw new BenchmarkSeedConflictException('An existing benchmark rule was edited.');
            }
        }
        if (BenchmarkRuleCanonicalizer::persistedRulesHash($rules) !== $manifest->rulesHash()) {
            throw new BenchmarkSeedConflictException('Existing benchmark draft rules differ from the manifest.');
        }
        return $registration->priceBookId;
    }

    /** @return list<PriceBook> */
    private function findBooksByLabel(string $label): array
    {
        $matches = [];
        $pageNumber = 1;
        do {
            $page = $this->books->list($pageNumber, 100);
            foreach ($page->items as $book) {
                if ($book->label() === $label) {
                    $matches[] = $book;
                }
            }
            $pageNumber++;
        } while (($pageNumber - 1) * 100 < $page->total);
        return $matches;
    }
}
