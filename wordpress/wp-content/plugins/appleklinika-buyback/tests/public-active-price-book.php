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
use AppleKlinika\Buyback\Application\LocalDemo\VisualStateCatalogue;

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
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = [];
$_POST = [];
$html = $page->render();
$runner->assert(! str_contains($html, 'A munkamenet lejárt.') && ! str_contains($html, 'A biztonsági ellenőrzés sikertelen.'), 'A fresh public GET never renders a security-failure message');
$storageLabel = new ReflectionMethod(LocalDemoCalculatorPage::class, 'storageLabel');
$runner->assert($storageLabel->invoke($page, 1024) === '1 TB' && $storageLabel->invoke($page, 2048) === '2 TB' && $storageLabel->invoke($page, 512) === '512 GB', 'Customer-facing storage formatting uses TB only for the supported 1 TB and 2 TB capacities');
$catalog = (new WordPressDeviceCatalogReader())->iPhoneCatalog();
$runner->assert(($catalog['iphone_11']['colors'] ?? null) === [
    'black' => 'Fekete (Black)',
    'green' => 'Zöld (Green)',
    'yellow' => 'Sárga (Yellow)',
    'purple' => 'Lila (Purple)',
    'product_red' => '(PRODUCT)RED',
    'white' => 'Fehér (White)',
], 'iPhone 11 colors come from the canonical inventory catalogue');
$runner->assert(str_contains($html, 'Milyen Apple készüléket adnál el?') && str_contains($html, 'Válaszd ki a készülék típusát, és néhány lépésben megmutatjuk az előzetes felvásárlási ajánlatot.'), 'Public runtime renders the dedicated Buyback entry introduction');
$runner->assert(str_contains($html, 'Jelenleg iPhone készülék felvásárlásában tudunk segíteni.') && ! str_contains($html, 'További Apple készüléktípusok támogatása később érkezik.'), 'Entry page uses the approved iPhone-only availability copy');
$runner->assert(substr_count($html, 'data-entry-family="iphone"') === 1 && ! str_contains($html, 'data-entry-family="ipad"') && ! str_contains($html, 'data-entry-family="macbook"'), 'Only the genuinely supported iPhone family is interactive on entry');
$runner->assert(str_contains($html, 'Gyors előzetes ajánlat') && str_contains($html, 'Átlátható állapotfelmérés') && str_contains($html, 'Személyes bevizsgálás'), 'Entry page explains only the currently supported Buyback process');
$runner->assert(! str_contains($html, 'A helyi demó árkönyve nem aktív.'), 'Public runtime does not emit the demo-book availability error');
$runner->assert(str_contains($html, 'data-storages="64,128,256"'), 'Priced iPhone 11 exposes only 64/128/256 GB');
$runner->assert(! str_contains($html, 'value="iphone_13_pro"'), 'Inventory model without an active Base price remains hidden');
$runner->assert(str_contains($html, 'data-visual-catalogue='), 'Public runtime receives the server-generated visual-state catalogue');
$runner->assert(str_contains($html, 'ak-buyback-demo__wizard-shell') && str_contains($html, 'data-demo-wizard-shell'), 'Public questionnaire panels expose one stable desktop wizard shell');
$configurationPanel = substr($html, (int) strpos($html, 'data-demo-panel="configuration"'), (int) strpos($html, 'data-demo-panel="liquid_contact"') - (int) strpos($html, 'data-demo-panel="configuration"'));
$runner->assert(str_contains($configurationPanel, 'data-selected-model-configuration') && str_contains($configurationPanel, 'data-selected-model-image') && str_contains($configurationPanel, 'data-change-model') && str_contains($configurationPanel, 'Másik modellt választok'), 'Configuration panel provides one selected-device context and an in-flow return to the model catalogue');
$runner->assert(str_contains($html, 'ak-buyback-demo__visual-image') && str_contains($html, 'data-demo-device-image'), 'Public questionnaire panels retain a stable visual image container');
$runner->assert(! str_contains($html, 'Add el vagy számíttasd be Apple készüléked'), 'Entry page removes the obsolete duplicate generic title');
$modelPanel = substr($html, (int) strpos($html, 'data-demo-panel="model"'), (int) strpos($html, 'data-demo-panel="configuration"') - (int) strpos($html, 'data-demo-panel="model"'));
$runner->assert(str_contains($modelPanel, 'data-model-content') && str_contains($modelPanel, 'data-wizard-action-bar') && str_contains($modelPanel, 'Tovább a tárhelyhez'), 'Model selection keeps its action bar outside the scrollable model content');
$runner->assert(str_contains($modelPanel, 'Keress a modell nevére, vagy válassz a kártyák közül.') && str_contains($modelPanel, 'data-demo-target="entry"'), 'Model catalogue keeps the requested help text and Back route to the entry page');
$runner->assert(str_contains($modelPanel, 'Akár 80 000 Ft'), 'Model catalogue maximum amount comes from the active model Base-price rules');
$runner->assert(str_contains($modelPanel, 'data-model-key="iphone_11"') && str_contains($modelPanel, 'data-model-media') && str_contains($modelPanel, 'ak-buyback-demo__model-media-image'), 'Every rendered public model retains its canonical key and keeps its image inside a dedicated media wrapper');
$runner->assert(str_contains($modelPanel, 'data-model-no-results') && str_contains($modelPanel, 'data-model-search-clear') && ! str_contains($modelPanel, 'ak-buyback-demo__device-image'), 'Model filtering has a compact public no-result state, a clear action, and no legacy detached media wrapper');
$runner->assert(str_contains($html, 'ak-buyback-demo__choice-description') && str_contains($html, 'aria-expanded="true"') && str_contains($html, 'aria-expanded="false"'), 'Single-select answers render selected-description accessibility state');
$liquidPanelStart = (int) strpos($html, 'data-demo-panel="liquid_contact"');
$screenPanelStart = (int) strpos($html, 'data-demo-panel="screen_cosmetic"');
$liquidPanel = substr($html, $liquidPanelStart, $screenPanelStart - $liquidPanelStart);
$runner->assert(str_contains($liquidPanel, 'ak-buyback-demo__choice-grid--liquid-contact'), 'Liquid-contact keeps its dedicated vertical answer layout hook');
$runner->assert(str_contains($html, 'assets/images/buyback-states/screen/flawless.webp'), 'Public payload contains the stable final visual asset paths');
$runner->assert(str_contains($html, 'assets/images/buyback-states/_demo/flawless.svg'), 'Missing final visual files keep the safe temporary fallback in the public payload');
$runner->assert(! str_contains($html, 'data-visual-assets-base'), 'Public rendering does not expose the old JavaScript asset-path builder');
$runner->assert(! str_contains($html, 'demo asset') && ! str_contains($html, 'Current demo asset'), 'Public rendering contains no customer-facing demo asset wording');
$runner->assert(! str_contains($html, 'HELYI DEMÓ') && ! str_contains($html, 'tesztelési célú') && ! str_contains(mb_strtolower($html), 'helyi demó'), 'Public rendering contains no internal demo wording');
$runner->assert(count((new VisualStateCatalogue(new LocalDemoQuestionnaire()))->entries()) === 15, 'All public visual states remain available without changing pricing');
$defaultSummary = (new LocalDemoQuestionnaire())->summary((new LocalDemoQuestionnaire())->defaults(), 'iPhone 11', '64 GB', 'Fekete');
$runner->assert(! isset($defaultSummary['Alkatrész- és szervizelési előzmények']['Érintett alkatrészek']), 'Hidden affected-part values are omitted when service history does not require them');
$noColorSummary = (new LocalDemoQuestionnaire())->summary((new LocalDemoQuestionnaire())->defaults(), 'iPhone 11', '64 GB');
$runner->assert(($noColorSummary['Konfiguráció']['Szín'] ?? '') === 'Nincs megadva' && isset($noColorSummary['Állapot']['Folyadékérintkezés']) && ! isset($noColorSummary['Állapot']['Folyadék / pára']), 'Optional color and liquid-contact summary labels are customer-readable');
$serviceSummaryState = (new LocalDemoQuestionnaire())->defaults();
$serviceSummaryState['service_history'] = 'used_original';
$serviceSummaryState['affected_parts'] = ['battery', 'display'];
$serviceSummary = (new LocalDemoQuestionnaire())->summary($serviceSummaryState, 'iPhone 11', '64 GB', 'Fekete');
$runner->assert(($serviceSummary['Alkatrész- és szervizelési előzmények']['Érintett alkatrészek'] ?? '') === 'Akkumulátor, Kijelző', 'Relevant affected parts render as clean public multi-select labels');

$failureCounts = publicActiveBookCounts($wpdb);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'ak_demo_action' => 'calculate',
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
$runner->assert(str_contains($validColorHtml, 'ELŐZETES AJÁNLAT') && ! str_contains($validColorHtml, 'HELYI DEMÓ') && ! str_contains($validColorHtml, 'tesztelési célú'), 'Public offer result uses customer-facing wording without internal demo text');
$_POST['color_key'] = '';
$noColorHtml = $page->render();
$runner->assert(! str_contains($noColorHtml, 'Válassz az ehhez a modellhez elérhető színek közül.') && str_contains($noColorHtml, 'Nincs megadva'), 'An omitted optional color is accepted and summarized naturally');
$invalidNoncePost = $_POST;
$invalidNoncePost['ak_buyback_local_demo_nonce'] = 'stale-or-invalid-nonce';
$_POST = $invalidNoncePost;
$invalidNonceHtml = $page->render();
$runner->assert(str_contains($invalidNonceHtml, 'A biztonsági ellenőrzés sikertelen. Frissítsd az oldalt.'), 'An invalid or stale calculation nonce remains rejected safely');
$runner->assert(publicActiveBookCounts($wpdb) === $failureCounts, 'A rejected calculation nonce creates no price book, rule, request, snapshot, or event');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = [];
$_GET = [];
$reloadedHtml = $page->render();
$runner->assert(! str_contains($reloadedHtml, 'A biztonsági ellenőrzés sikertelen.'), 'A fresh reload clears the old calculation security message');
$submissionErrorToken = wp_generate_uuid4();
set_transient('ak_buyback_submission_error_' . $submissionErrorToken, 'A munkamenet lejárt. Frissítsd az oldalt, majd próbáld újra.', MINUTE_IN_SECONDS);
$_GET = ['ak_buyback_submission_error' => $submissionErrorToken];
$expiredSubmissionHtml = $page->render();
$runner->assert(str_contains($expiredSubmissionHtml, 'A munkamenet lejárt. Frissítsd az oldalt, majd próbáld újra.') && str_contains($expiredSubmissionHtml, 'Újraindítás'), 'A stale submission nonce has accurate recovery copy and a restart action');
$reloadedSubmissionHtml = $page->render();
$runner->assert(! str_contains($reloadedSubmissionHtml, 'A munkamenet lejárt.'), 'The one-time submission error cannot persist after a refresh or history revisit');
$_GET = ['ak_buyback_submission_error' => 'A biztonsági ellenőrzés sikertelen. Frissítsd az oldalt.'];
$legacyErrorHtml = $page->render();
$runner->assert(! str_contains($legacyErrorHtml, 'A biztonsági ellenőrzés sikertelen.'), 'A legacy error message in browser history is never rendered as trusted page state');
$_GET = [];
$runner->assert(publicActiveBookCounts($wpdb) === $failureCounts, 'Recovering from a rejected or expired nonce creates no public request data');
$offersPanelStart = (int) strpos($validColorHtml, '<section class="ak-buyback-demo__panel ak-buyback-demo__panel--offers"');
$reviewPanelStart = (int) strpos($validColorHtml, '<section class="ak-buyback-demo__panel ak-buyback-demo__panel--review"');
$offersPanel = substr($validColorHtml, $offersPanelStart, $reviewPanelStart - $offersPanelStart);
$runner->assert(substr_count($offersPanel, 'data-customer-summary') === 1 && str_contains($offersPanel, 'A készüléked összefoglalója') && str_contains($offersPanel, 'Ellenőrizd, hogy minden megadott adat helyes-e.'), 'Offer page renders one customer-readable review summary from the public state');
$runner->assert(str_contains($offersPanel, 'ak-buyback-demo__customer-summary-device') && str_contains($offersPanel, 'ak-buyback-demo__customer-summary-section') && str_contains($offersPanel, 'ak-buyback-demo__customer-summary-offer') && ! str_contains($offersPanel, 'ak-buyback-demo__customer-summary-groups'), 'Offer summary uses one ordered top-to-bottom review layout');
$runner->assert(str_contains($offersPanel, 'Folyadékérintkezés') && str_contains($offersPanel, 'Hálózatfüggetlen') && ! str_contains($offersPanel, 'visual_key'), 'Offer summary presents public labels without technical visual metadata');
$runner->assert(str_contains($offersPanel, 'data-offer-summary-selection') && str_contains($offersPanel, 'Még nincs kiválasztva.'), 'Offer summary has one selected-offer row before a choice is made');
$runner->assert(str_contains($offersPanel, 'Tovább az adatok megadásához') && ! str_contains($offersPanel, 'Tovább az összefoglalóhoz'), 'Offer CTA names the existing contact-data next step');
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';

$books->replace([$retired, $draft]);
$unavailable = (new LocalDemoCalculatorPage($resolver = new RepositoryActivePriceBookResolver($books, new InMemoryPublicPricingRules([])), new PricingEngine(), new WordPressDeviceCatalogReader(), new WordPressLocalDemoProductReader(), new LocalDemoQuestionnaire()))->render();
$runner->assert(str_contains($unavailable, 'Jelenleg nincs aktív felvásárlási árkönyv.'), 'No active HUF book produces the generic public unavailable state');
$runner->assert(! str_contains($unavailable, 'A helyi demó árkönyve nem aktív.'), 'Generic unavailable state never exposes the legacy demo guard');

$after = publicActiveBookCounts($wpdb);
$runner->assert($before === $after, 'Loading and resolving public runtime fixtures creates no price book, rule, request, snapshot, or event');
$runner->finish($before, $after);
