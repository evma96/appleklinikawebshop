<?php

declare(strict_types=1);

$wordpressRoot = dirname(__DIR__, 4);
require_once $wordpressRoot . '/wp-load.php';

use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\PricingRuleRepository;
use AppleKlinika\Buyback\Application\Pricing\PriceBookPage;
use AppleKlinika\Buyback\Application\Pricing\RepositoryActivePriceBookResolver;
use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoQuestionnaire;
use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;
use AppleKlinika\Buyback\Domain\Pricing\MinimumOfferPolicy;
use AppleKlinika\Buyback\Domain\Pricing\PriceBook;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookStatus;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookVersionNumber;
use AppleKlinika\Buyback\Domain\Pricing\PricingActorId;
use AppleKlinika\Buyback\Domain\Pricing\PricingRule;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleCode;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleDefinition;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleId;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind;
use AppleKlinika\Buyback\Domain\Pricing\RulePriority;
use AppleKlinika\Buyback\Domain\Pricing\StorageCapacity;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Domain\Shared\Money;
use AppleKlinika\Buyback\Infrastructure\Inventory\WordPressDeviceCatalogReader;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\Schema;
use AppleKlinika\Buyback\Infrastructure\WordPress\WordPressLocalDemoProductReader;
use AppleKlinika\Buyback\Interfaces\Frontend\LocalDemoCalculatorPage;
use AppleKlinika\Buyback\Domain\Pricing\PricingEngine;

final class PublicActiveBookTestRunner
{
    private int $assertions = 0;
    /** @var list<string> */
    private array $failures = [];

    public function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (! $condition) {
            $this->failures[] = $message;
        }
    }

    /** @param array<string, int> $before @param array<string, int> $after */
    public function finish(array $before, array $after): never
    {
        if ($this->failures !== []) {
            foreach ($this->failures as $failure) {
                fwrite(STDERR, "FAIL: {$failure}\n");
            }
            fwrite(STDERR, sprintf("%d assertion(s), %d failure(s).\n", $this->assertions, count($this->failures)));
            exit(1);
        }

        echo sprintf(
            "Buyback public active-price-book tests passed: %d assertions; rows before/after price_books %d/%d, price_rules %d/%d, requests %d/%d, snapshots %d/%d, events %d/%d.\n",
            $this->assertions,
            $before['price_books'], $after['price_books'],
            $before['price_rules'], $after['price_rules'],
            $before['requests'], $after['requests'],
            $before['snapshots'], $after['snapshots'],
            $before['events'], $after['events']
        );
        exit(0);
    }
}

/** @return array<string, int> */
function publicActiveBookCounts(wpdb $database): array
{
    $tables = Schema::tableNames($database);
    return [
        'price_books' => (int) $database->get_var("SELECT COUNT(*) FROM `{$tables[Schema::PRICE_BOOKS]}`"),
        'price_rules' => (int) $database->get_var("SELECT COUNT(*) FROM `{$tables[Schema::PRICE_RULES]}`"),
        'requests' => (int) $database->get_var("SELECT COUNT(*) FROM `{$tables[Schema::REQUESTS]}`"),
        'snapshots' => (int) $database->get_var("SELECT COUNT(*) FROM `{$tables[Schema::SNAPSHOTS]}`"),
        'events' => (int) $database->get_var("SELECT COUNT(*) FROM `{$tables[Schema::EVENTS]}`"),
    ];
}

final class InMemoryPublicPriceBooks implements PriceBookRepository
{
    /** @param list<PriceBook> $books */
    public function __construct(private array $books)
    {
    }

    /** @param list<PriceBook> $books */
    public function replace(array $books): void
    {
        $this->books = $books;
    }

    public function createDraft(PriceBook $priceBook): PriceBook { return $priceBook; }
    public function getById(PriceBookId $id): ?PriceBook { foreach ($this->books as $book) { if ($book->id()?->equals($id)) { return $book; } } return null; }
    public function getByIdForUpdate(PriceBookId $id): ?PriceBook { return $this->getById($id); }
    public function getByVersionNumber(PriceBookVersionNumber $number): ?PriceBook { foreach ($this->books as $book) { if ($book->versionNumber()->value() === $number->value()) { return $book; } } return null; }
    public function list(int $page, int $perPage, ?PriceBookStatus $status = null): PriceBookPage { return new PriceBookPage($this->books, count($this->books), $page, $perPage); }
    public function saveDraft(PriceBook $priceBook, AggregateVersion $expectedVersion): void {}
    public function saveActivated(PriceBook $priceBook, AggregateVersion $expectedVersion): void {}
    public function saveRetired(PriceBook $priceBook, AggregateVersion $expectedVersion): void {}
    public function nextAvailableVersionNumber(): PriceBookVersionNumber { return new PriceBookVersionNumber(999999); }
    public function hasActiveBook(): bool { return $this->findCurrentActiveForCurrencyAt(new CurrencyCode('HUF'), new DateTimeImmutable()) !== []; }

    public function findCurrentActiveForCurrencyAt(CurrencyCode $currency, DateTimeImmutable $at): array
    {
        return array_values(array_filter($this->books, static fn (PriceBook $book): bool => $book->status()->isActive() && $book->currency()->code() === $currency->code()));
    }

    public function findCurrentActiveForCurrencyAtForUpdate(CurrencyCode $currency, DateTimeImmutable $at): array { return $this->findCurrentActiveForCurrencyAt($currency, $at); }
    public function countCurrentActiveForCurrencyAt(CurrencyCode $currency, DateTimeImmutable $at): int { return count($this->findCurrentActiveForCurrencyAt($currency, $at)); }
}

final class InMemoryPublicPricingRules implements PricingRuleRepository
{
    /** @param array<int, list<PricingRule>> $rules */
    public function __construct(private array $rules)
    {
    }

    public function insert(PricingRule $rule): PricingRule { return $rule; }
    public function getById(PricingRuleId $id): ?PricingRule { return null; }
    public function listForPriceBook(PriceBookId $priceBookId): array { return $this->rules[$priceBookId->toInt()] ?? []; }
    public function update(PricingRule $rule, AggregateVersion $expectedVersion): void {}
    public function deleteDraftRule(PriceBookId $priceBookId, PricingRuleId $ruleId, AggregateVersion $expectedVersion): void {}
    public function isCodeUnique(PriceBookId $priceBookId, PricingRuleCode $code, ?PricingRuleId $except = null): bool { return true; }
    public function countForPriceBook(PriceBookId $priceBookId): int { return count($this->listForPriceBook($priceBookId)); }
}

function publicActiveBook(int $id, int $version, string $label, string $status): PriceBook
{
    $at = new DateTimeImmutable('2026-07-21T12:00:00+00:00');
    return PriceBook::reconstitute(
        new PriceBookId($id),
        new PriceBookVersionNumber($version),
        $label,
        new PriceBookStatus($status),
        new CurrencyCode('HUF'),
        new Money(10000, 'HUF'),
        1000,
        new MinimumOfferPolicy(MinimumOfferPolicy::MANUAL_REVIEW),
        new PricingActorId(1),
        new AggregateVersion(1),
        $at,
        $at,
        $status === PriceBookStatus::ACTIVE ? $at : null
    );
}

function publicBaseRule(int $id, PriceBookId $bookId, string $model, int $storage): PricingRule
{
    $at = new DateTimeImmutable('2026-07-21T12:00:00+00:00');
    return PricingRule::reconstitute(
        new PricingRuleId($id),
        $bookId,
        new PricingRuleDefinition(
            new PricingRuleCode('public-base-' . $model . '-' . $storage),
            new PricingRuleKind(PricingRuleKind::BASE_PRICE),
            'iphone',
            $model,
            new StorageCapacity($storage),
            null,
            null,
            null,
            null,
            new Money(80000, 'HUF'),
            null,
            new RulePriority(10),
            true,
            'Public base price',
            'In-memory public runtime fixture'
        ),
        new AggregateVersion(1),
        $at,
        $at
    );
}

global $wpdb;
$runner = new PublicActiveBookTestRunner();
$before = publicActiveBookCounts($wpdb);
$active = publicActiveBook(930001, 987654, 'July public prices', PriceBookStatus::ACTIVE);
$retired = publicActiveBook(930002, 987653, 'LOCAL DEMO legacy', PriceBookStatus::RETIRED);
$draft = publicActiveBook(930003, 987655, 'Draft copy', PriceBookStatus::DRAFT);
$rules = [
    publicBaseRule(940001, $active->id(), 'iphone_11', 64),
    publicBaseRule(940002, $active->id(), 'iphone_11', 128),
    publicBaseRule(940003, $active->id(), 'iphone_11', 256),
];
$books = new InMemoryPublicPriceBooks([$retired, $draft, $active]);
$ruleRepository = new InMemoryPublicPricingRules([$active->id()->toInt() => $rules]);
$resolver = new RepositoryActivePriceBookResolver($books, $ruleRepository);
$now = new DateTimeImmutable('2026-07-21T12:00:00+00:00');

$resolved = $resolver->resolveForCurrencyAt(new CurrencyCode('HUF'), $now);
$runner->assert($resolved->priceBook->id()?->toInt() === 930001, 'Arbitrary active HUF book ID is resolved');
$runner->assert($resolved->priceBook->label() === 'July public prices', 'Active book does not require LOCAL DEMO in its label');
$runner->assert($resolved->priceBook->versionNumber()->value() === 987654, 'Active book does not require the old seeded version');
$runner->assert(count($resolved->supportedConfigurations) === 3, 'Enabled Base-price rules define the supported public configurations');
$runner->assert(implode(',', array_map(static fn ($configuration): int => $configuration->storageGb, $resolved->supportedConfigurations)) === '128,256,64', 'Only the three iPhone 11 Base-price storages are exposed by the resolver');

$cloned = publicActiveBook(930004, 987656, 'Cloned active public prices', PriceBookStatus::ACTIVE);
$books->replace([$retired, $draft, $cloned]);
$ruleRepository = new InMemoryPublicPricingRules([$cloned->id()->toInt() => $rules]);
$resolver = new RepositoryActivePriceBookResolver($books, $ruleRepository);
$runner->assert($resolver->resolveForCurrencyAt(new CurrencyCode('HUF'), $now)->priceBook->id()?->toInt() === 930004, 'A cloned and activated HUF book is accepted without Seeder identity');

$books->replace([$retired, $draft, $active]);
$resolver = new RepositoryActivePriceBookResolver($books, new InMemoryPublicPricingRules([$active->id()->toInt() => $rules]));
$page = new LocalDemoCalculatorPage($resolver, new PricingEngine(), new WordPressDeviceCatalogReader(), new WordPressLocalDemoProductReader(), new LocalDemoQuestionnaire());
$html = $page->render();
$catalog = (new WordPressDeviceCatalogReader())->iPhoneCatalog();
$runner->assert(($catalog['iphone_11']['colors'] ?? null) === [
    'black' => 'Fekete (Black)',
    'green' => 'Zöld (Green)',
    'yellow' => 'Sárga (Yellow)',
    'purple' => 'Lila (Purple)',
    'product_red' => '(PRODUCT)RED',
    'white' => 'Fehér (White)',
], 'iPhone 11 colors come from the canonical inventory catalogue');
$runner->assert(str_contains($html, 'KÉSZÜLÉKFELVÁSÁRLÁS'), 'Public runtime renders the generic buyback heading');
$runner->assert(! str_contains($html, 'A helyi demó árkönyve nem aktív.'), 'Public runtime does not emit the demo-book availability error');
$runner->assert(str_contains($html, 'data-storages="64,128,256"'), 'Priced iPhone 11 exposes only 64/128/256 GB');
$runner->assert(! str_contains($html, 'value="iphone_13_pro"'), 'Inventory model without an active Base price remains hidden');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'ak_buyback_local_demo_nonce' => wp_create_nonce('ak_buyback_local_demo_calculate'),
    'model_key' => 'iphone_11',
    'storage_gb' => '64',
    'color_key' => 'midnight_green',
    'questionnaire' => (new LocalDemoQuestionnaire())->defaults(),
];
$craftedColorHtml = $page->render();
$runner->assert(str_contains($craftedColorHtml, 'Válassz az ehhez a modellhez elérhető színek közül.'), 'A crafted color from another inventory model is rejected server-side');

$_POST['color_key'] = 'black';
$validColorHtml = $page->render();
$runner->assert(! str_contains($validColorHtml, 'Válassz az ehhez a modellhez elérhető színek közül.'), 'A canonical iPhone 11 inventory color is accepted server-side');
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';

$books->replace([$retired, $draft]);
$unavailable = (new LocalDemoCalculatorPage($resolver = new RepositoryActivePriceBookResolver($books, new InMemoryPublicPricingRules([])), new PricingEngine(), new WordPressDeviceCatalogReader(), new WordPressLocalDemoProductReader(), new LocalDemoQuestionnaire()))->render();
$runner->assert(str_contains($unavailable, 'Jelenleg nincs aktív felvásárlási árkönyv.'), 'No active HUF book produces the generic public unavailable state');
$runner->assert(! str_contains($unavailable, 'A helyi demó árkönyve nem aktív.'), 'Generic unavailable state never exposes the legacy demo guard');

$after = publicActiveBookCounts($wpdb);
$runner->assert($before === $after, 'Loading and resolving public runtime fixtures creates no price book, rule, request, snapshot, or event');
$runner->finish($before, $after);
