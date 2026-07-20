<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

use AppleKlinika\Buyback\Application\Command\ActivateDraftPriceBook;
use AppleKlinika\Buyback\Application\Command\AddDraftPricingRule;
use AppleKlinika\Buyback\Application\Command\CreateDraftPriceBook;
use AppleKlinika\Buyback\Application\Handler\ActivateDraftPriceBookHandler;
use AppleKlinika\Buyback\Application\Handler\AddDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Handler\CreateDraftPriceBookHandler;
use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoHostGuard;
use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoPriceMatrixBuilder;
use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoRuleFactory;
use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoSeedResult;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\PricingRuleRepository;
use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;
use AppleKlinika\Buyback\Domain\Pricing\MinimumOfferPolicy;
use AppleKlinika\Buyback\Domain\Pricing\PriceBook;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookStatus;
use AppleKlinika\Buyback\Infrastructure\Time\SystemClock;

final class LocalDemoSeeder
{
    public const LABEL = 'Apple Klinika LOCAL DEMO iPhone Buyback';
    private const ACTOR_ID = 1;

    public function __construct(
        private readonly PriceBookRepository $books,
        private readonly PricingRuleRepository $rules,
        private readonly CreateDraftPriceBookHandler $createBook,
        private readonly AddDraftPricingRuleHandler $addRule,
        private readonly ActivateDraftPriceBookHandler $activateBook,
        private readonly WordPressLocalDemoProductReader $products,
        private readonly WordPressLocalDemoPageGateway $pages,
        private readonly LocalDemoPriceMatrixBuilder $matrixBuilder = new LocalDemoPriceMatrixBuilder(),
        private readonly LocalDemoRuleFactory $ruleFactory = new LocalDemoRuleFactory(),
        private readonly LocalDemoHostGuard $hostGuard = new LocalDemoHostGuard(),
        private readonly SystemClock $clock = new SystemClock()
    ) {
    }

    public function seed(): LocalDemoSeedResult
    {
        $this->hostGuard->assertLocal(site_url(), home_url());
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $active = $this->books->findCurrentActiveForCurrencyAt(new CurrencyCode('HUF'), $now);
        if (count($active) > 1 || ($active !== [] && $active[0]->label() !== self::LABEL)) {
            throw new \RuntimeException('An unknown active HUF price book prevents local demo activation.');
        }

        $matrix = $this->matrixBuilder->build($this->products->publishedIphones());
        if ($matrix === []) {
            throw new \RuntimeException('No valid published iPhone model/storage price groups were found.');
        }
        $definitions = $this->ruleFactory->create($matrix);
        $book = $this->findDemoBook();
        $created = false;

        if ($book === null) {
            $book = $this->createBook->handle(new CreateDraftPriceBook(self::LABEL, 10000, 1000, MinimumOfferPolicy::MANUAL_REVIEW, self::ACTOR_ID));
            $created = true;
            $expectedVersion = $book->version()->value();
            foreach ($definitions as $definition) {
                $this->addRule->handle(new AddDraftPricingRule($book->id()->toInt(), $expectedVersion, $definition));
                ++$expectedVersion;
            }
            $book = $this->books->getById($book->id());
        }

        if (! $book instanceof PriceBook || $book->id() === null) {
            throw new \RuntimeException('The local demo price book could not be loaded.');
        }
        $this->assertExpectedRules($book, $definitions);

        if ($book->status()->code() === PriceBookStatus::DRAFT) {
            if ($active !== []) {
                throw new \RuntimeException('The local demo draft cannot replace an already active price book.');
            }
            $book = $this->activateBook->handle(new ActivateDraftPriceBook($book->id()->toInt(), $book->version()->value(), self::ACTOR_ID, ActivateDraftPriceBook::CONFIRMATION));
        } elseif ($book->status()->code() !== PriceBookStatus::ACTIVE) {
            throw new \RuntimeException('The existing local demo price book is not reusable.');
        }

        $active = $this->books->findCurrentActiveForCurrencyAt(new CurrencyCode('HUF'), $this->clock->now());
        if (count($active) !== 1 || $active[0]->id()?->toInt() !== $book->id()->toInt()) {
            throw new \RuntimeException('Local demo activation did not result in exactly one active HUF price book.');
        }

        $pageId = $this->pages->ensure(self::ACTOR_ID);
        $models = [];
        foreach ($matrix as $point) {
            $models[$point->modelKey] = true;
        }

        return new LocalDemoSeedResult($book->id()->toInt(), $pageId, count($models), count($matrix), count($definitions), $created);
    }

    private function findDemoBook(): ?PriceBook
    {
        $matches = [];
        $page = $this->books->list(1, 100);
        foreach ($page->items as $book) {
            if ($book->label() === self::LABEL && $book->currency()->code() === 'HUF') {
                $matches[] = $book;
            }
        }
        if (count($matches) > 1) {
            throw new \RuntimeException('Multiple local demo price books already exist.');
        }
        return $matches[0] ?? null;
    }

    /** @param list<\AppleKlinika\Buyback\Domain\Pricing\PricingRuleDefinition> $definitions */
    private function assertExpectedRules(PriceBook $book, array $definitions): void
    {
        $actual = array_map(static fn ($rule): string => $rule->definition()->code->code(), $this->rules->listForPriceBook($book->id()));
        $expected = array_map(static fn ($definition): string => $definition->code->code(), $definitions);
        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($actual !== $expected) {
            throw new \RuntimeException('The existing local demo rule set is incomplete or differs from the deterministic definition.');
        }
    }
}
