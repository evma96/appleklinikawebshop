<?php

declare(strict_types=1);

$wordpressRoot = dirname(__DIR__, 4);
require_once $wordpressRoot . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use AppleKlinika\Buyback\Application\Command\AddDraftPricingRule;
use AppleKlinika\Buyback\Application\Command\CreateDraftPriceBook;
use AppleKlinika\Buyback\Application\Command\ClonePriceBookToDraft;
use AppleKlinika\Buyback\Application\Command\DiscardDraftPriceBook;
use AppleKlinika\Buyback\Application\Command\DeleteDraftPricingRule;
use AppleKlinika\Buyback\Application\Command\SaveDraftBasePriceMatrix;
use AppleKlinika\Buyback\Application\Command\SaveDraftQuestionnaireConditions;
use AppleKlinika\Buyback\Application\Command\SaveDraftBatteryBands;
use AppleKlinika\Buyback\Application\Command\SaveDraftOfferModeModifiers;
use AppleKlinika\Buyback\Application\Command\ToggleDraftPricingRule;
use AppleKlinika\Buyback\Application\Command\UpdateDraftPriceBookSettings;
use AppleKlinika\Buyback\Application\Command\UpdateDraftPricingRule;
use AppleKlinika\Buyback\Application\Exception\DeviceCatalogUnavailableException;
use AppleKlinika\Buyback\Application\Exception\DuplicatePriceBookVersionException;
use AppleKlinika\Buyback\Application\Exception\DuplicatePricingRuleCodeException;
use AppleKlinika\Buyback\Application\Exception\PricingRuleNotFoundException;
use AppleKlinika\Buyback\Application\Handler\AddDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Handler\ActivateDraftPriceBookHandler;
use AppleKlinika\Buyback\Application\Handler\CreateDraftPriceBookHandler;
use AppleKlinika\Buyback\Application\Handler\ClonePriceBookToDraftHandler;
use AppleKlinika\Buyback\Application\Handler\DiscardDraftPriceBookHandler;
use AppleKlinika\Buyback\Application\Handler\DeleteDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Handler\PreviewDraftPriceBookCalculationHandler;
use AppleKlinika\Buyback\Application\Handler\SaveDraftBasePriceMatrixHandler;
use AppleKlinika\Buyback\Application\Handler\SaveDraftQuestionnaireConditionsHandler;
use AppleKlinika\Buyback\Application\Handler\SaveDraftBatteryBandsHandler;
use AppleKlinika\Buyback\Application\Handler\SaveDraftOfferModeModifiersHandler;
use AppleKlinika\Buyback\Application\Handler\ToggleDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Handler\UpdateDraftPriceBookSettingsHandler;
use AppleKlinika\Buyback\Application\Handler\UpdateDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoQuestionnaire;
use AppleKlinika\Buyback\Application\Pricing\OfferModeExampleCalculator;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\DraftPriceBookDiscardRepository;
use AppleKlinika\Buyback\Domain\Exception\InvalidAggregateOperationException;
use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;
use AppleKlinika\Buyback\Domain\Exception\StaleAggregateVersionException;
use AppleKlinika\Buyback\Domain\Buyback\OfferModeDefinition;
use AppleKlinika\Buyback\Domain\Pricing\BasisPointsMultiplier;
use AppleKlinika\Buyback\Domain\Pricing\ComparisonOperator;
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
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind;
use AppleKlinika\Buyback\Domain\Pricing\RulePriority;
use AppleKlinika\Buyback\Domain\Pricing\StorageCapacity;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Domain\Shared\Money;
use AppleKlinika\Buyback\Infrastructure\Inventory\WordPressDeviceCatalogReader;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\Schema;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\SchemaInspector;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\MySqlPriceBookActivationLock;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPriceBookRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressDraftPriceBookDiscardRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPricingRuleRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressTransactionManager;
use AppleKlinika\Buyback\Infrastructure\WordPress\CapabilityManager;
use AppleKlinika\Buyback\Infrastructure\WordPress\Deactivator;
use AppleKlinika\Buyback\Infrastructure\WordPress\Plugin;
use AppleKlinika\Buyback\Interfaces\Admin\AdminAuthorization;
use AppleKlinika\Buyback\Interfaces\Admin\AdminSubmissionGuard;
use AppleKlinika\Buyback\Interfaces\Admin\PriceBooksPage;
use AppleKlinika\Buyback\Interfaces\Admin\PreviewCalculationFormParser;
use AppleKlinika\Buyback\Interfaces\Admin\PricingRuleFormParser;
use AppleKlinika\Buyback\Application\Pricing\PriceBookActivationReadinessService;
use AppleKlinika\Buyback\Application\Pricing\RepositoryActivePriceBookResolver;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookActivationReadinessEvaluator;
use AppleKlinika\Buyback\Domain\Pricing\PricingEngine;

const AK_BUYBACK_PRICING_PLUGIN = 'appleklinika-buyback/appleklinika-buyback.php';
const AK_BUYBACK_PRICING_LEGACY_META = 'appleklinika_buyback_records';

final class PricingAdminTestRunner
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

    /** @param class-string<Throwable> $expected */
    public function throws(callable $operation, string $expected, string $message): ?Throwable
    {
        ++$this->assertions;
        try {
            $operation();
            $this->failures[] = $message . ' (no exception thrown)';
        } catch (Throwable $exception) {
            if (! $exception instanceof $expected) {
                $this->failures[] = sprintf('%s (expected %s, received %s: %s)', $message, $expected, $exception::class, $exception->getMessage());
            }
            return $exception;
        }
        return null;
    }

    public function fail(Throwable $exception): void
    {
        $this->failures[] = $exception::class . ': ' . $exception->getMessage();
    }

    /** @param array<string, int> $before @param array<string, int> $after */
    public function finish(array $before, array $after, string $marker, string $legacyHashBefore, string $legacyHashAfter): never
    {
        if ($this->failures !== []) {
            foreach ($this->failures as $failure) {
                fwrite(STDERR, "FAIL: {$failure}\n");
            }
            fwrite(STDERR, sprintf("%d assertion(s), %d failure(s); marker: %s.\n", $this->assertions, count($this->failures), $marker));
            exit(1);
        }

        echo sprintf(
            "Buyback pricing/admin integration tests passed: %d assertions; marker %s; rows before/after price_books %d/%d, price_rules %d/%d, requests %d/%d, snapshots %d/%d, events %d/%d; legacy hash %s.\n",
            $this->assertions,
            $marker,
            $before[Schema::PRICE_BOOKS],
            $after[Schema::PRICE_BOOKS],
            $before[Schema::PRICE_RULES],
            $after[Schema::PRICE_RULES],
            $before[Schema::REQUESTS],
            $after[Schema::REQUESTS],
            $before[Schema::SNAPSHOTS],
            $after[Schema::SNAPSHOTS],
            $before[Schema::EVENTS],
            $after[Schema::EVENTS],
            $legacyHashBefore === $legacyHashAfter ? 'unchanged' : 'changed'
        );
        exit(0);
    }
}

final class PricingAdminFixedClock implements Clock
{
    public function __construct(private readonly DateTimeImmutable $time)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }
}

final class ReferencedDraftPriceBookDiscardRepository implements DraftPriceBookDiscardRepository
{
    public function __construct(private readonly DraftPriceBookDiscardRepository $delegate)
    {
    }

    public function hasBusinessReferences(PriceBookId $priceBookId): bool
    {
        return true;
    }

    public function discardDraftWithRules(PriceBookId $priceBookId): void
    {
        $this->delegate->discardDraftWithRules($priceBookId);
    }
}

final class FailingDraftPriceBookDiscardRepository implements DraftPriceBookDiscardRepository
{
    public function __construct(private readonly DraftPriceBookDiscardRepository $delegate)
    {
    }

    public function hasBusinessReferences(PriceBookId $priceBookId): bool
    {
        return false;
    }

    public function discardDraftWithRules(PriceBookId $priceBookId): void
    {
        $this->delegate->discardDraftWithRules($priceBookId);
        throw new AppleKlinika\Buyback\Infrastructure\Persistence\Exception\PersistenceException('Forced discard failure.');
    }
}

/** @return array<string, int> */
function pricingRowCounts(wpdb $database, array $tables): array
{
    $counts = [];
    foreach ($tables as $key => $table) {
        $counts[$key] = (int) $database->get_var("SELECT COUNT(*) FROM `{$table}`");
    }
    return $counts;
}

function pricingLegacyHash(wpdb $database): string
{
    $rows = $database->get_results($database->prepare(
        "SELECT umeta_id, user_id, meta_key, meta_value FROM {$database->usermeta} WHERE meta_key = %s ORDER BY umeta_id ASC",
        AK_BUYBACK_PRICING_LEGACY_META
    ), ARRAY_A);
    return hash('sha256', serialize(is_array($rows) ? $rows : []));
}

function pricingTableStructureHash(wpdb $database, string $table): string
{
    $columns = $database->get_results("SHOW FULL COLUMNS FROM `{$table}`", ARRAY_A);
    $indexes = $database->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A);
    return hash('sha256', serialize([$columns, $indexes]));
}

/** @return list<string> */
function pricingColumnNames(wpdb $database, string $table): array
{
    $rows = $database->get_results("SHOW COLUMNS FROM `{$table}`", ARRAY_A);
    return array_map(static fn (array $row): string => (string) $row['Field'], is_array($rows) ? $rows : []);
}

/** @return list<string> */
function pricingIndexNames(wpdb $database, string $table): array
{
    $rows = $database->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A);
    return array_values(array_unique(array_map(static fn (array $row): string => (string) $row['Key_name'], is_array($rows) ? $rows : [])));
}

function pricingCleanup(wpdb $database, array $tables, string $marker): void
{
    $ids = $database->get_col($database->prepare(
        "SELECT id FROM `{$tables[Schema::PRICE_BOOKS]}` WHERE label LIKE %s",
        $database->esc_like($marker) . '%'
    ));
    $ids = array_map('intval', is_array($ids) ? $ids : []);
    if ($ids !== []) {
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $database->query($database->prepare("DELETE FROM `{$tables[Schema::PRICE_RULES]}` WHERE price_book_id IN ({$placeholders})", ...$ids));
        $database->query($database->prepare("DELETE FROM `{$tables[Schema::PRICE_BOOKS]}` WHERE id IN ({$placeholders})", ...$ids));
    }
}

function pricingDefinition(string $kind, string $code, int $priority = 100, bool $enabled = true): PricingRuleDefinition
{
    $ruleCode = new PricingRuleCode($code);
    $ruleKind = new PricingRuleKind($kind);
    $rulePriority = new RulePriority($priority);

    return match ($kind) {
        PricingRuleKind::BASE_PRICE => new PricingRuleDefinition($ruleCode, $ruleKind, 'iphone', 'iphone-13-pro', new StorageCapacity(128), null, null, null, null, new Money(21000000, 'HUF'), null, $rulePriority, $enabled, null, 'QA base price'),
        PricingRuleKind::FIXED_DEDUCTION => new PricingRuleDefinition($ruleCode, $ruleKind, 'iphone', null, null, null, 'battery_health', new ComparisonOperator(ComparisonOperator::LESS_THAN), 80, new Money(1500000, 'HUF'), null, $rulePriority, $enabled, null, 'QA fixed deduction'),
        PricingRuleKind::MULTIPLIER => new PricingRuleDefinition($ruleCode, $ruleKind, 'iphone', null, null, null, 'screen_condition', new ComparisonOperator(ComparisonOperator::EQUALS), 'good', null, new BasisPointsMultiplier(9000), $rulePriority, $enabled, null, 'QA multiplier'),
        PricingRuleKind::MODE_ADJUSTMENT => new PricingRuleDefinition($ruleCode, $ruleKind, 'iphone', null, null, 'fast_online', null, null, null, new Money(500000, 'HUF'), null, $rulePriority, $enabled, null, 'QA mode adjustment'),
        PricingRuleKind::HARD_REJECT => new PricingRuleDefinition($ruleCode, $ruleKind, 'iphone', null, null, null, 'liquid_damage', new ComparisonOperator(ComparisonOperator::EQUALS), true, null, null, $rulePriority, $enabled, 'Folyadékkár miatt nem adható ajánlat.', 'QA reject'),
        PricingRuleKind::MANUAL_REVIEW => new PricingRuleDefinition($ruleCode, $ruleKind, 'iphone', null, null, null, 'replacement_parts', new ComparisonOperator(ComparisonOperator::EQUALS), 'non_original', null, null, $rulePriority, $enabled, 'Szakértői ellenőrzés szükséges.', 'QA manual review'),
        default => throw new InvalidArgumentException('Unsupported QA pricing kind.'),
    };
}

function pricingCreateBook(CreateDraftPriceBookHandler $handler, string $label, int $actorId): PriceBook
{
    return $handler->handle(new CreateDraftPriceBook($label, 2500000, 100000, MinimumOfferPolicy::MANUAL_REVIEW, $actorId));
}

function pricingAddRule(AddDraftPricingRuleHandler $handler, WordPressPriceBookRepository $books, PriceBookId $bookId, PricingRuleDefinition $definition): PricingRule
{
    $book = $books->getById($bookId);
    if ($book === null) {
        throw new RuntimeException('QA price book disappeared before rule creation.');
    }
    return $handler->handle(new AddDraftPricingRule($bookId->toInt(), $book->version()->value(), $definition));
}

/** @return array<string,array<string,array{action:string,value:string}>> */
function pricingConditionSubmission(LocalDemoQuestionnaire $questionnaire): array
{
    $submission = [];
    foreach ($questionnaire->conditionEditorQuestions() as $question) {
        foreach ($question['options'] as $option) {
            if ($option['configurable']) {
                $submission[$question['question_key']][$option['answer_key']] = ['action' => SaveDraftQuestionnaireConditionsHandler::ACTION_NONE, 'value' => ''];
            }
        }
    }
    return $submission;
}

/** @return array<string,mixed> */
function pricingBatteryBand(?PricingRule $rule, int $minimum, int $maximum, string $action, string $value = '', bool $delete = false): array
{
    return [
        'rule_id' => $rule?->id()?->toInt() === null ? '' : (string) $rule->id()->toInt(),
        'minimum' => (string) $minimum,
        'maximum' => (string) $maximum,
        'action' => $action,
        'value' => $value,
        'delete' => $delete ? '1' : '',
    ];
}

/** @param list<PricingRule> $rules @return list<PricingRule> */
function pricingModelBatteryRules(array $rules, string $modelKey): array
{
    return array_values(array_filter($rules, static fn (PricingRule $rule): bool => $rule->definition()->modelKey === $modelKey && $rule->definition()->conditionKey === 'battery_health' && $rule->definition()->operator?->code() === ComparisonOperator::BETWEEN));
}

/** @return array{0:int,1:bool} */
function pricingUserForRole(string $role, string $token): array
{
    $existing = get_users(['role' => $role, 'number' => 1, 'fields' => 'ID']);
    if (is_array($existing) && $existing !== []) {
        return [(int) $existing[0], false];
    }
    $id = wp_insert_user([
        'user_login' => 'qa-pricing-' . $role . '-' . strtolower($token),
        'user_pass' => wp_generate_password(24, true, true),
        'user_email' => 'qa-pricing-' . $role . '-' . strtolower($token) . '@example.invalid',
        'role' => $role,
    ]);
    if (is_wp_error($id)) {
        throw new RuntimeException($id->get_error_message());
    }
    return [(int) $id, true];
}

global $wpdb, $submenu;

$test = new PricingAdminTestRunner();
$tables = Schema::tableNames($wpdb);
$runToken = gmdate('mdHis') . substr(bin2hex(random_bytes(3)), 0, 6);
$marker = 'QA-PRICEBOOK-' . $runToken;
$countsBefore = pricingRowCounts($wpdb, $tables);
$legacyHashBefore = pricingLegacyHash($wpdb);
$catalogHashBefore = hash('sha256', serialize(get_option('appleklinika_device_catalog', null)));
$schemaVersionBefore = (string) get_option(Schema::OPTION_SCHEMA_VERSION, '0.0.0');
$pluginVersionBefore = (string) get_option(Schema::OPTION_PLUGIN_VERSION, '');
$phaseOneStructureBefore = [];
foreach ([Schema::REQUESTS, Schema::SNAPSHOTS, Schema::EVENTS] as $key) {
    $phaseOneStructureBefore[$key] = pricingTableStructureHash($wpdb, $tables[$key]);
}
$activeBefore = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$tables[Schema::PRICE_BOOKS]}` WHERE status = %s", PriceBookStatus::ACTIVE));
$originalUserId = get_current_user_id();
$createdUserIds = [];
$guardTransientKeys = [];
$clock = new PricingAdminFixedClock(new DateTimeImmutable('2026-07-15T12:00:00+00:00'));
$transactions = new WordPressTransactionManager($wpdb);
$books = new WordPressPriceBookRepository($wpdb, $transactions);
$rules = new WordPressPricingRuleRepository($wpdb);
$createBook = new CreateDraftPriceBookHandler($books, $transactions, $clock);
$updateBook = new UpdateDraftPriceBookSettingsHandler($books, $clock);
$addRule = new AddDraftPricingRuleHandler($books, $rules, $transactions, $clock);
$updateRule = new UpdateDraftPricingRuleHandler($books, $rules, $transactions, $clock);
$toggleRule = new ToggleDraftPricingRuleHandler($books, $rules, $transactions, $clock);
$deleteRule = new DeleteDraftPricingRuleHandler($books, $rules, $transactions, $clock);

try {
    $test->assert(is_plugin_active(AK_BUYBACK_PRICING_PLUGIN), 'Buyback plugin is active');
    $test->assert(APPLEKLINIKA_BUYBACK_VERSION === '0.7.0', 'Plugin code version is 0.7.0');
    $test->assert(APPLEKLINIKA_BUYBACK_SCHEMA_VERSION === '1.1.0', 'Code schema version is 1.1.0');

    update_option(Schema::OPTION_SCHEMA_VERSION, '1.0.0', false);
    Plugin::migrationRunner()->run();
    $test->assert(get_option(Schema::OPTION_SCHEMA_VERSION) === '1.1.0', 'Migration advances schema 1.0.0 to 1.1.0');
    Plugin::migrationRunner()->run();
    $test->assert(get_option(Schema::OPTION_SCHEMA_VERSION) === '1.1.0', 'Migration rerun is idempotent');
    $test->assert(pricingRowCounts($wpdb, $tables) === $countsBefore, 'Migration creates no automatic business rows');

    $inspector = new SchemaInspector($wpdb, APPLEKLINIKA_BUYBACK_SCHEMA_VERSION);
    $inspector->assertRequiredSchema();
    foreach ([Schema::PRICE_BOOKS, Schema::PRICE_RULES] as $key) {
        $columns = pricingColumnNames($wpdb, $tables[$key]);
        $indexes = pricingIndexNames($wpdb, $tables[$key]);
        $expectedColumns = Schema::requiredColumns()[$key];
        $expectedIndexes = Schema::requiredIndexes()[$key];
        sort($columns);
        sort($indexes);
        sort($expectedColumns);
        sort($expectedIndexes);
        $test->assert($columns === $expectedColumns, "{$key} has exactly the required columns");
        $test->assert($indexes === $expectedIndexes, "{$key} has exactly the required indexes");
        $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $tables[$key]));
        $test->assert(strtoupper((string) $engine) === 'INNODB', "{$key} uses a transactional engine");
    }
    foreach ($phaseOneStructureBefore as $key => $signature) {
        $test->assert(pricingTableStructureHash($wpdb, $tables[$key]) === $signature, "Phase 1 table {$key} structure remains unchanged");
    }

    $test->assert((new PriceBookVersionNumber(7))->next()->value() === 8, 'Price-book version helper is deterministic');
    $test->assert((new CurrencyCode('HUF'))->code() === 'HUF', 'Currency code accepts uppercase ISO-style codes');
    $test->throws(fn () => new CurrencyCode('huf'), InvalidValueObjectException::class, 'Currency rejects lowercase code');
    $test->assert((new BasisPointsMultiplier(BasisPointsMultiplier::ONE))->value() === 10000, '10000 basis points represents 1.0000');
    $test->throws(fn () => new BasisPointsMultiplier(BasisPointsMultiplier::MAX + 1), InvalidValueObjectException::class, 'Multiplier enforces documented safe maximum');
    $test->assert((new StorageCapacity(1024))->gigabytes() === 1024, 'Storage capacity uses canonical integer GB');
    $test->throws(fn () => new StorageCapacity(0), InvalidValueObjectException::class, 'Storage capacity must be positive');
    $test->throws(fn () => new RulePriority(100001), InvalidValueObjectException::class, 'Rule priority is bounded');
    $test->throws(fn () => new PricingRuleKind('calculator'), InvalidValueObjectException::class, 'Unknown pricing-rule kind is rejected');
    $test->assert((new PricingRuleCode(' Battery Health '))->code() === 'battery-health', 'Rule code normalizes to a safe identifier');
    $test->assert((new MinimumOfferPolicy(MinimumOfferPolicy::REJECT))->code() === MinimumOfferPolicy::REJECT, 'Minimum-offer policy accepts reject');

    $domainBook = PriceBook::createDraft(new PriceBookVersionNumber(1), 'QA domain', new CurrencyCode('HUF'), new Money(1000, 'HUF'), 100, new MinimumOfferPolicy(MinimumOfferPolicy::MANUAL_REVIEW), new PricingActorId(1), $clock->now());
    $domainBook->updateSettings('QA domain updated', new Money(2000, 'HUF'), 500, new MinimumOfferPolicy(MinimumOfferPolicy::REJECT), $clock->now());
    $test->assert($domainBook->version()->value() === 1 && $domainBook->minimumOffer()->amount() === 2000 && $domainBook->roundingIncrementMinor() === 500, 'Draft settings mutation validates and increments aggregate version');
    $test->throws(fn () => PriceBook::createDraft(new PriceBookVersionNumber(2), 'QA bad rounding', new CurrencyCode('HUF'), new Money(0, 'HUF'), 0, new MinimumOfferPolicy(MinimumOfferPolicy::REJECT), new PricingActorId(1), $clock->now()), InvalidValueObjectException::class, 'Rounding increment must be positive');
    $activeDomainBook = PriceBook::reconstitute(new PriceBookId(999001), new PriceBookVersionNumber(999001), 'QA active', new PriceBookStatus(PriceBookStatus::ACTIVE), new CurrencyCode('HUF'), new Money(0, 'HUF'), 1, new MinimumOfferPolicy(MinimumOfferPolicy::REJECT), new PricingActorId(1), new AggregateVersion(0), $clock->now(), $clock->now());
    $test->throws(fn () => $activeDomainBook->updateSettings('Forbidden', new Money(0, 'HUF'), 1, new MinimumOfferPolicy(MinimumOfferPolicy::REJECT), $clock->now()), InvalidAggregateOperationException::class, 'Active price book is read-only');
    $retiredDomainBook = PriceBook::reconstitute(new PriceBookId(999002), new PriceBookVersionNumber(999002), 'QA retired', new PriceBookStatus(PriceBookStatus::RETIRED), new CurrencyCode('HUF'), new Money(0, 'HUF'), 1, new MinimumOfferPolicy(MinimumOfferPolicy::REJECT), new PricingActorId(1), new AggregateVersion(0), $clock->now(), $clock->now());
    $test->throws(fn () => $retiredDomainBook->recordRuleMutation($clock->now()), InvalidAggregateOperationException::class, 'Retired price book is read-only');

    foreach ([PricingRuleKind::BASE_PRICE, PricingRuleKind::FIXED_DEDUCTION, PricingRuleKind::MULTIPLIER, PricingRuleKind::MODE_ADJUSTMENT, PricingRuleKind::HARD_REJECT, PricingRuleKind::MANUAL_REVIEW] as $index => $kind) {
        $definition = pricingDefinition($kind, 'domain-' . $index);
        $test->assert($definition->kind->code() === $kind, "Valid {$kind} rule shape is accepted");
    }
    $test->throws(fn () => new PricingRuleDefinition(new PricingRuleCode('bad-base'), new PricingRuleKind(PricingRuleKind::BASE_PRICE), 'iphone', 'iphone-13-pro', new StorageCapacity(128), null, null, null, null, new Money(1000, 'HUF'), new BasisPointsMultiplier(9000), new RulePriority(1), true, null, null), InvalidValueObjectException::class, 'Base price rejects conflicting multiplier');
    $test->throws(fn () => new PricingRuleDefinition(new PricingRuleCode('bad-mode'), new PricingRuleKind(PricingRuleKind::MODE_ADJUSTMENT), 'iphone', null, null, 'fast_online', null, null, null, new Money(1000, 'HUF'), new BasisPointsMultiplier(9000), new RulePriority(1), true, null, null), InvalidValueObjectException::class, 'Mode adjustment rejects amount and multiplier together');
    $test->throws(fn () => new PricingRuleDefinition(new PricingRuleCode('bad-review'), new PricingRuleKind(PricingRuleKind::MANUAL_REVIEW), 'iphone', null, null, null, 'liquid_damage', new ComparisonOperator(ComparisonOperator::EQUALS), true, null, null, new RulePriority(1), true, null, null), InvalidValueObjectException::class, 'Manual review requires a public label');
    $test->throws(fn () => new PricingRuleDefinition(new PricingRuleCode('bad-minimum'), new PricingRuleKind(PricingRuleKind::MINIMUM_OFFER), 'iphone', null, null, null, null, null, null, new Money(1000, 'HUF'), null, new RulePriority(1), true, null, null), InvalidValueObjectException::class, 'Rule-level minimum is not editable in Phase 2A');
    $test->assert((new PricingRuleDefinition(new PricingRuleCode('model-scope'), new PricingRuleKind(PricingRuleKind::FIXED_DEDUCTION), 'iphone', 'iphone-13-pro', null, null, 'battery_health', new ComparisonOperator(ComparisonOperator::LESS_THAN), 80, new Money(1000, 'HUF'), null, new RulePriority(1), true, null, null))->modelKey === 'iphone-13-pro', 'Conditional rule may use the existing optional model scope');

    $parser = new PricingRuleFormParser();
    $test->throws(fn () => $parser->parse([
        'rule_code' => 'conflicting-mode', 'rule_kind' => PricingRuleKind::MODE_ADJUSTMENT, 'priority' => '100', 'is_enabled' => '1',
        'service_mode' => 'fast_online', 'adjustment_type' => 'amount', 'amount_minor' => '1000', 'multiplier_percent' => '90',
    ]), InvalidArgumentException::class, 'Admin parser does not silently discard conflicting mode-adjustment values');

    [$adminId, $adminCreated] = pricingUserForRole('administrator', $runToken);
    [$managerId, $managerCreated] = pricingUserForRole('shop_manager', $runToken);
    [$customerId, $customerCreated] = pricingUserForRole('customer', $runToken);
    [$subscriberId, $subscriberCreated] = pricingUserForRole('subscriber', $runToken);
    foreach ([[$adminId, $adminCreated], [$managerId, $managerCreated], [$customerId, $customerCreated], [$subscriberId, $subscriberCreated]] as [$id, $created]) {
        if ($created) {
            $createdUserIds[] = $id;
        }
    }
    (new CapabilityManager())->grant();
    $authorization = new AdminAuthorization();
    foreach ([[$adminId, true, 'administrator'], [$managerId, true, 'shop_manager'], [$customerId, false, 'customer'], [$subscriberId, false, 'subscriber']] as [$userId, $allowed, $role]) {
        wp_set_current_user($userId);
        $nonce = wp_create_nonce(AdminAuthorization::NONCE_ACTION);
        if ($allowed) {
            $authorization->assert(CapabilityManager::MANAGE_PRICE_BOOKS, $nonce);
            $test->assert(true, "{$role} is allowed to manage price books");
        } else {
            $test->throws(fn () => $authorization->assert(CapabilityManager::MANAGE_PRICE_BOOKS, $nonce), RuntimeException::class, "{$role} is denied price-book access");
        }
    }
    wp_set_current_user($adminId);
    $test->throws(fn () => $authorization->assert(CapabilityManager::MANAGE_PRICE_BOOKS, 'invalid'), RuntimeException::class, 'Invalid admin nonce is rejected');
    $test->throws(fn () => $authorization->assert(CapabilityManager::MANAGE_PRICE_BOOKS, ''), RuntimeException::class, 'Missing admin nonce is rejected');

    $test->assert(has_action('admin_menu') !== false, 'Price-books page is registered on the WordPress admin menu hook');
    $test->assert(PriceBooksPage::SLUG === 'appleklinika-buyback-price-books', 'Price-books page exposes the documented stable admin slug');

    $book = pricingCreateBook($createBook, $marker . '-MAIN', $adminId);
    if ($book->id() === null) {
        throw new RuntimeException('Created QA price book has no identity.');
    }
    $bookId = $book->id();
    $test->assert($book->status()->code() === PriceBookStatus::DRAFT && $book->version()->value() === 0, 'Create handler persists a versioned draft');
    $test->assert($books->getByVersionNumber($book->versionNumber())?->id()?->equals($bookId) === true, 'Draft reloads by version number');
    $test->assert($books->list(1, 20, new PriceBookStatus(PriceBookStatus::DRAFT))->total >= 1, 'Draft list supports status filter and pagination');

    $duplicateBook = PriceBook::createDraft($book->versionNumber(), $marker . '-DUPLICATE', new CurrencyCode('HUF'), new Money(0, 'HUF'), 1, new MinimumOfferPolicy(MinimumOfferPolicy::REJECT), new PricingActorId($adminId), $clock->now());
    $wasSuppressingErrors = $wpdb->suppress_errors(true);
    $test->throws(fn () => $books->createDraft($duplicateBook), DuplicatePriceBookVersionException::class, 'Database uniqueness is final authority for price-book version');
    $wpdb->suppress_errors($wasSuppressingErrors);

    $updateBook->handle(new UpdateDraftPriceBookSettings($bookId->toInt(), 0, $marker . '-MAIN-UPDATED', 3000000, 50000, MinimumOfferPolicy::REJECT));
    $updatedBook = $books->getById($bookId);
    $test->assert($updatedBook?->label() === $marker . '-MAIN-UPDATED' && $updatedBook->version()->value() === 1, 'Settings handler updates the draft with optimistic version');

    $firstBookCopy = $books->getById($bookId);
    $secondBookCopy = $books->getById($bookId);
    if ($firstBookCopy === null || $secondBookCopy === null) {
        throw new RuntimeException('Could not load optimistic-lock price-book copies.');
    }
    $firstBookCopy->updateSettings($marker . '-LOCK-WINNER', new Money(3000000, 'HUF'), 50000, new MinimumOfferPolicy(MinimumOfferPolicy::REJECT), $clock->now());
    $secondBookCopy->updateSettings($marker . '-LOCK-LOSER', new Money(3000000, 'HUF'), 50000, new MinimumOfferPolicy(MinimumOfferPolicy::REJECT), $clock->now());
    $books->saveDraft($firstBookCopy, new AggregateVersion(1));
    $test->throws(fn () => $books->saveDraft($secondBookCopy, new AggregateVersion(1)), StaleAggregateVersionException::class, 'Price-book optimistic lock rejects concurrent stale save');
    $test->assert($books->getById($bookId)?->label() === $marker . '-LOCK-WINNER', 'Stale save does not overwrite accepted price-book state');

    $createdRules = [];
    foreach ([PricingRuleKind::BASE_PRICE, PricingRuleKind::FIXED_DEDUCTION, PricingRuleKind::MULTIPLIER, PricingRuleKind::MODE_ADJUSTMENT, PricingRuleKind::HARD_REJECT, PricingRuleKind::MANUAL_REVIEW] as $index => $kind) {
        $createdRules[$kind] = pricingAddRule($addRule, $books, $bookId, pricingDefinition($kind, 'qa-' . $runToken . '-' . $index, 100 + $index));
        $test->assert($createdRules[$kind]->id() !== null && $createdRules[$kind]->definition()->kind->code() === $kind, "Admin handler persists {$kind}");
    }
    $test->assert($rules->countForPriceBook($bookId) === 6, 'All six exposed Phase 2A rule kinds are stored');
    $listedRules = $rules->listForPriceBook($bookId);
    $listedPriorities = array_map(static fn (PricingRule $rule): int => $rule->definition()->priority->value(), $listedRules);
    $sortedPriorities = $listedPriorities;
    sort($sortedPriorities);
    $test->assert($listedPriorities === $sortedPriorities, 'Rules use deterministic priority and ID ordering');

    $currentBook = $books->getById($bookId);
    $test->throws(fn () => $addRule->handle(new AddDraftPricingRule($bookId->toInt(), $currentBook?->version()->value() ?? -1, pricingDefinition(PricingRuleKind::BASE_PRICE, 'qa-' . $runToken . '-0'))), DuplicatePricingRuleCodeException::class, 'Rule code is unique inside one price book');

    $secondBook = pricingCreateBook($createBook, $marker . '-SECOND', $adminId);
    if ($secondBook->id() === null) {
        throw new RuntimeException('Second QA price book has no identity.');
    }
    $sameCodeOtherBook = pricingAddRule($addRule, $books, $secondBook->id(), pricingDefinition(PricingRuleKind::BASE_PRICE, 'qa-' . $runToken . '-0'));
    $test->assert($sameCodeOtherBook->id() !== null, 'Same rule code is allowed in a different price book');

    $baseRule = $createdRules[PricingRuleKind::BASE_PRICE];
    if ($baseRule->id() === null) {
        throw new RuntimeException('Base rule has no identity.');
    }
    $bookForUpdate = $books->getById($bookId);
    $updateRule->handle(new UpdateDraftPricingRule($bookId->toInt(), $bookForUpdate?->version()->value() ?? -1, $baseRule->id()->toInt(), 0, pricingDefinition(PricingRuleKind::BASE_PRICE, $baseRule->definition()->code->code(), 25)));
    $updatedRule = $rules->getById($baseRule->id());
    $test->assert($updatedRule?->version()->value() === 1 && $updatedRule->definition()->priority->value() === 25, 'Rule update persists with optimistic version');

    $firstRuleCopy = $rules->getById($baseRule->id());
    $secondRuleCopy = $rules->getById($baseRule->id());
    if ($firstRuleCopy === null || $secondRuleCopy === null) {
        throw new RuntimeException('Could not load optimistic-lock rule copies.');
    }
    $firstRuleCopy->update(pricingDefinition(PricingRuleKind::BASE_PRICE, $baseRule->definition()->code->code(), 26), $clock->now());
    $secondRuleCopy->update(pricingDefinition(PricingRuleKind::BASE_PRICE, $baseRule->definition()->code->code(), 27), $clock->now());
    $rules->update($firstRuleCopy, new AggregateVersion(1));
    $test->throws(fn () => $rules->update($secondRuleCopy, new AggregateVersion(1)), StaleAggregateVersionException::class, 'Pricing-rule optimistic lock rejects concurrent stale save');

    $bookForCrossUpdate = $books->getById($secondBook->id());
    $currentBaseRule = $rules->getById($baseRule->id());
    $test->throws(fn () => $updateRule->handle(new UpdateDraftPricingRule($secondBook->id()->toInt(), $bookForCrossUpdate?->version()->value() ?? -1, $baseRule->id()->toInt(), $currentBaseRule?->version()->value() ?? -1, pricingDefinition(PricingRuleKind::BASE_PRICE, 'cross-book'))), PricingRuleNotFoundException::class, 'Cross-price-book rule update is blocked');
    $test->throws(fn () => $deleteRule->handle(new DeleteDraftPricingRule($secondBook->id()->toInt(), $bookForCrossUpdate?->version()->value() ?? -1, $baseRule->id()->toInt(), $currentBaseRule?->version()->value() ?? -1)), PricingRuleNotFoundException::class, 'Cross-price-book rule delete is blocked');

    $multiplierRule = $createdRules[PricingRuleKind::MULTIPLIER];
    if ($multiplierRule->id() === null) {
        throw new RuntimeException('Multiplier rule has no identity.');
    }
    $bookForToggle = $books->getById($bookId);
    $toggleRule->handle(new ToggleDraftPricingRule($bookId->toInt(), $bookForToggle?->version()->value() ?? -1, $multiplierRule->id()->toInt(), 0, false));
    $disabledRule = $rules->getById($multiplierRule->id());
    $test->assert($disabledRule?->definition()->enabled === false && $disabledRule->version()->value() === 1, 'Toggle handler disables a draft rule');
    $bookBeforeNoOp = $books->getById($bookId);
    $toggleRule->handle(new ToggleDraftPricingRule($bookId->toInt(), $bookBeforeNoOp?->version()->value() ?? -1, $multiplierRule->id()->toInt(), 1, false));
    $test->assert($books->getById($bookId)?->version()->value() === $bookBeforeNoOp?->version()->value(), 'No-op toggle does not create a false aggregate mutation');

    $reviewRule = $createdRules[PricingRuleKind::MANUAL_REVIEW];
    if ($reviewRule->id() === null) {
        throw new RuntimeException('Manual-review rule has no identity.');
    }
    $bookForDelete = $books->getById($bookId);
    $deleteRule->handle(new DeleteDraftPricingRule($bookId->toInt(), $bookForDelete?->version()->value() ?? -1, $reviewRule->id()->toInt(), 0));
    $test->assert($rules->getById($reviewRule->id()) === null, 'Delete handler removes only the selected draft rule');

    $activeBook = pricingCreateBook($createBook, $marker . '-ACTIVE', $adminId);
    $retiredBook = pricingCreateBook($createBook, $marker . '-RETIRED', $adminId);
    if ($activeBook->id() === null || $retiredBook->id() === null) {
        throw new RuntimeException('Read-only QA books have no identity.');
    }
    pricingAddRule($addRule, $books, $activeBook->id(), pricingDefinition(PricingRuleKind::BASE_PRICE, 'active-clone-base-' . $runToken));
    $activeBook = $books->getById($activeBook->id());
    if ($activeBook === null) {
        throw new RuntimeException('Active clone source could not be reloaded.');
    }
    $wpdb->update($tables[Schema::PRICE_BOOKS], ['status' => PriceBookStatus::ACTIVE], ['id' => $activeBook->id()->toInt()], ['%s'], ['%d']);
    $activeBook = $books->getById($activeBook->id());
    if ($activeBook === null) {
        throw new RuntimeException('Active clone source status could not be reloaded.');
    }
    $wpdb->update($tables[Schema::PRICE_BOOKS], ['status' => PriceBookStatus::RETIRED], ['id' => $retiredBook->id()->toInt()], ['%s'], ['%d']);
    $test->assert($books->hasActiveBook(), 'Repository can detect active books read-only');
    $test->throws(fn () => $updateBook->handle(new UpdateDraftPriceBookSettings($activeBook->id()->toInt(), 0, 'Forbidden active edit', 0, 1, MinimumOfferPolicy::REJECT)), InvalidAggregateOperationException::class, 'Active book settings mutation is rejected');
    $test->throws(fn () => pricingAddRule($addRule, $books, $activeBook->id(), pricingDefinition(PricingRuleKind::BASE_PRICE, 'active-forbidden')), InvalidAggregateOperationException::class, 'Active book rule mutation is rejected');
    $clone = (new ClonePriceBookToDraftHandler($books, $rules, $transactions, $clock))->handle(new ClonePriceBookToDraft($activeBook->id()->toInt(), $adminId));
    $test->assert($clone->status()->isDraft() && $clone->label() === $activeBook->label() . ' – Másolat v' . $activeBook->versionNumber()->value(), 'Active price book clones to a separately named draft');
    $test->assert(count($rules->listForPriceBook($clone->id())) === count($rules->listForPriceBook($activeBook->id())), 'Clone preserves all source pricing rules');
    $test->assert($books->getById($activeBook->id())?->status()->isActive(), 'Clone leaves the active source immutable and active');
    $test->throws(fn () => $updateBook->handle(new UpdateDraftPriceBookSettings($retiredBook->id()->toInt(), 0, 'Forbidden retired edit', 0, 1, MinimumOfferPolicy::REJECT)), InvalidAggregateOperationException::class, 'Retired book mutation is rejected');

    $discardRepository = new WordPressDraftPriceBookDiscardRepository($wpdb);
    $discardDraft = new DiscardDraftPriceBookHandler($books, $discardRepository, $transactions);
    $discardable = pricingCreateBook($createBook, $marker . '-DISCARD', $adminId);
    pricingAddRule($addRule, $books, $discardable->id(), pricingDefinition(PricingRuleKind::BASE_PRICE, $marker . '-discard-base'));
    $activeRulesBeforeDiscard = count($rules->listForPriceBook($activeBook->id()));
    $discardDraft->handle(new DiscardDraftPriceBook($discardable->id()->toInt(), DiscardDraftPriceBook::CONFIRMATION));
    $test->assert($books->getById($discardable->id()) === null && $rules->listForPriceBook($discardable->id()) === [], 'Discarding an unreferenced draft removes only that draft and its rules');
    $test->assert($books->getById($activeBook->id())?->status()->isActive() && count($rules->listForPriceBook($activeBook->id())) === $activeRulesBeforeDiscard, 'Discarding a draft preserves other price books and their rules');
    $test->throws(fn () => $discardDraft->handle(new DiscardDraftPriceBook($activeBook->id()->toInt(), DiscardDraftPriceBook::CONFIRMATION)), InvalidAggregateOperationException::class, 'Discard handler rejects an active price book');
    $retiredForDiscard = $books->getById($retiredBook->id());
    $test->throws(fn () => $discardDraft->handle(new DiscardDraftPriceBook($retiredForDiscard?->id()->toInt() ?? 0, DiscardDraftPriceBook::CONFIRMATION)), InvalidAggregateOperationException::class, 'Discard handler rejects an archived price book');
    $test->throws(fn () => $discardDraft->handle(new DiscardDraftPriceBook(999999999, DiscardDraftPriceBook::CONFIRMATION)), AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException::class, 'Discard handler rejects an unknown price book');
    $test->throws(fn () => $discardDraft->handle(new DiscardDraftPriceBook($clone->id()->toInt(), '')), InvalidArgumentException::class, 'Discard handler requires explicit permanent-deletion confirmation');
    $referencedDraft = pricingCreateBook($createBook, $marker . '-REFERENCED', $adminId);
    pricingAddRule($addRule, $books, $referencedDraft->id(), pricingDefinition(PricingRuleKind::BASE_PRICE, $marker . '-referenced-base'));
    $referencedDiscard = new DiscardDraftPriceBookHandler($books, new ReferencedDraftPriceBookDiscardRepository($discardRepository), $transactions);
    $test->throws(fn () => $referencedDiscard->handle(new DiscardDraftPriceBook($referencedDraft->id()->toInt(), DiscardDraftPriceBook::CONFIRMATION)), AppleKlinika\Buyback\Application\Exception\PriceBookHasBusinessReferencesException::class, 'Discard handler blocks a draft with historical or business references');
    $test->assert($books->getById($referencedDraft->id()) !== null && count($rules->listForPriceBook($referencedDraft->id())) === 1, 'Reference-blocked discard leaves the draft and its rules intact');
    $rollbackDraft = pricingCreateBook($createBook, $marker . '-DISCARD-ROLLBACK', $adminId);
    pricingAddRule($addRule, $books, $rollbackDraft->id(), pricingDefinition(PricingRuleKind::BASE_PRICE, $marker . '-rollback-base'));
    $failingDiscard = new DiscardDraftPriceBookHandler($books, new FailingDraftPriceBookDiscardRepository($discardRepository), $transactions);
    $test->throws(fn () => $failingDiscard->handle(new DiscardDraftPriceBook($rollbackDraft->id()->toInt(), DiscardDraftPriceBook::CONFIRMATION)), AppleKlinika\Buyback\Infrastructure\Persistence\Exception\PersistenceException::class, 'A discard transaction failure rolls back both the draft and its rules');
    $test->assert($books->getById($rollbackDraft->id()) !== null && count($rules->listForPriceBook($rollbackDraft->id())) === 1, 'Discard rollback leaves both the draft and its rules intact');

    $guard = new AdminSubmissionGuard();
    $token = $guard->issue();
    $scope = 'create_price_book';
    $transientKey = 'ak_bb_submit_' . md5($scope . '|' . $adminId . '|' . $token);
    $guardTransientKeys[] = $transientKey;
    $test->assert($guard->consume($scope, $token, $adminId), 'First admin submission token is accepted');
    $guardBook = pricingCreateBook($createBook, $marker . '-REPLAY', $adminId);
    $test->assert(! $guard->consume($scope, $token, $adminId), 'Repeated admin submission token is rejected');
    $replayCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$tables[Schema::PRICE_BOOKS]}` WHERE label = %s", $marker . '-REPLAY'));
    $test->assert($guardBook->id() !== null && $replayCount === 1, 'POST replay guard prevents duplicate draft data');

    $catalog = new WordPressDeviceCatalogReader();
    $iPhones = $catalog->iPhoneModels();
    $test->assert($iPhones !== [], 'Read-only device catalog provides iPhone model keys');
    $test->assert(count(array_filter($iPhones, static fn ($item): bool => $item->modelKey !== $item->label)) > 0, 'Catalog label does not replace stable model identity');
    $rawCatalog = get_option('appleklinika_device_catalog', []);
    $nonIphoneKeys = [];
    foreach (is_array($rawCatalog) ? $rawCatalog : [] as $record) {
        if (is_array($record) && ($record['type'] ?? '') !== 'iphone' && isset($record['key'])) {
            $nonIphoneKeys[] = (string) $record['key'];
        }
    }
    $returnedKeys = array_map(static fn ($item): string => $item->modelKey, $iPhones);
    $test->assert(array_intersect($nonIphoneKeys, $returnedKeys) === [], 'Non-iPhone catalog models are excluded');
    $orderedLabels = array_map(static fn ($item): string => $item->label, $iPhones);
    $sortedLabels = $orderedLabels;
    usort($sortedLabels, 'strnatcasecmp');
    $test->assert($orderedLabels === $sortedLabels, 'Catalog model ordering is deterministic');
    $configurations = $catalog->iPhoneConfigurations();
    $test->assert($configurations !== [], 'Inventory-owned catalogue data provides valid iPhone model/storage configurations');
    $iphoneRecords = array_values(array_filter(is_array($rawCatalog) ? $rawCatalog : [], static fn ($record): bool => is_array($record) && (($record['type'] ?? '') === 'iphone')));
    $test->assert(count($iphoneRecords) === 34, 'Inventory contains all 34 canonical iPhone models');
    $expectedConfigurationCount = 0;
    foreach ($iphoneRecords as $record) {
        $storageKeys = $record['storage_capacity_keys'] ?? [];
        $test->assert(is_array($storageKeys) && $storageKeys !== [], 'Every canonical iPhone model has a non-empty model-specific storage list');
        $test->assert(count($storageKeys) === count(array_unique($storageKeys)), 'A model-specific storage list contains no duplicate key');
        foreach ($storageKeys as $storageKey) {
            $test->assert(is_string($storageKey) && array_key_exists($storageKey, \Appleklinika\Inventory\Domain\ProductCondition\StorageCapacityCatalog::options()), 'Every model-specific storage key belongs to the inventory vocabulary');
        }
        $expectedConfigurationCount += count($storageKeys);
    }
    $test->assert($expectedConfigurationCount === 107 && count($configurations) === $expectedConfigurationCount, 'Buyback receives exactly the 107 model-specific inventory configurations');
    $configurationModels = array_values(array_unique(array_map(static fn ($configuration): string => $configuration->modelKey, $configurations)));
    $test->assert(count($configurationModels) === count($iPhones) && in_array('iphone_12', $configurationModels, true), 'A runtime inventory model without a base-price rule is still present in the matrix source');
    $test->assert(count(array_filter($configurations, static fn ($configuration): bool => $configuration->storageGb === 2048)) === 1 && count(array_filter($configurations, static fn ($configuration): bool => $configuration->storageGb === 8192)) === 0, 'Storage sets differ by model and the global 8 TB vocabulary does not create configurations');
    $firstConfiguration = $configurations[0] ?? null;
    if ($firstConfiguration === null) {
        throw new RuntimeException('No inventory configuration is available for matrix QA.');
    }
    $matrixBook = pricingCreateBook($createBook, $marker . '-MATRIX', $adminId);
    if ($matrixBook->id() === null) {
        throw new RuntimeException('Matrix QA price book has no identity.');
    }
    pricingAddRule($addRule, $books, $matrixBook->id(), pricingDefinition(PricingRuleKind::FIXED_DEDUCTION, 'matrix-unrelated-' . $runToken));
    $saveMatrix = new SaveDraftBasePriceMatrixHandler($books, $rules, $catalog, $transactions, $clock);
    $matrixCurrent = $books->getById($matrixBook->id());
    $saveMatrix->handle(new SaveDraftBasePriceMatrix($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, [
        $firstConfiguration->modelKey => [(string) $firstConfiguration->storageGb => '120000'],
    ]));
    $matrixRules = $rules->listForPriceBook($matrixBook->id());
    $matrixBaseRules = array_values(array_filter($matrixRules, static fn (PricingRule $rule): bool => $rule->definition()->kind->code() === PricingRuleKind::BASE_PRICE));
    $matrixBaseRuleId = $matrixBaseRules[0]->id() ?? null;
    if ($matrixBaseRuleId === null) {
        throw new RuntimeException('Matrix QA base-price rule has no identity.');
    }
    $test->assert(count($matrixBaseRules) === 1 && $matrixBaseRules[0]->definition()->amount?->amount() === 120000, 'Matrix save creates one canonical draft base-price rule for an inventory configuration');
    $test->assert(count($matrixRules) === 2, 'Matrix save preserves unrelated pricing rules');
    $matrixCurrent = $books->getById($matrixBook->id());
    $saveMatrix->handle(new SaveDraftBasePriceMatrix($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, [
        $firstConfiguration->modelKey => [(string) $firstConfiguration->storageGb => '130000'],
    ]));
    $test->assert($rules->getById($matrixBaseRuleId)?->definition()->amount?->amount() === 130000, 'Matrix save updates the matching draft base-price rule without duplicating it');
    $matrixCurrent = $books->getById($matrixBook->id());
    $saveMatrix->handle(new SaveDraftBasePriceMatrix($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, [
        $firstConfiguration->modelKey => [(string) $firstConfiguration->storageGb => ''],
    ]));
    $test->assert(count(array_filter($rules->listForPriceBook($matrixBook->id()), static fn (PricingRule $rule): bool => $rule->definition()->kind->code() === PricingRuleKind::BASE_PRICE)) === 0, 'An empty matrix cell removes only its matching draft base-price rule');
    $matrixCurrent = $books->getById($matrixBook->id());
    $ruleCountBeforeInvalidPair = count($rules->listForPriceBook($matrixBook->id()));
    $test->throws(fn () => $saveMatrix->handle(new SaveDraftBasePriceMatrix($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, ['iphone_17' => ['128' => '1']])), InvalidArgumentException::class, 'Matrix rejects a crafted storage value that belongs to another iPhone model');
    $test->assert(count($rules->listForPriceBook($matrixBook->id())) === $ruleCountBeforeInvalidPair, 'Invalid matrix submissions do not mutate unrelated rules');
    $questionnaire = new LocalDemoQuestionnaire();
    $saveConditions = new SaveDraftQuestionnaireConditionsHandler($books, $rules, $transactions, $clock, $questionnaire, $catalog);
    $saveBatteryBands = new SaveDraftBatteryBandsHandler($books, $rules, $transactions, $clock, $questionnaire, $catalog);
    $saveOfferModes = new SaveDraftOfferModeModifiersHandler($books, $rules, $transactions, $clock);
    $publicQuestionKeys = [];
    foreach ($questionnaire->panelOrder() as $panel) {
        if (in_array($panel, ['model', 'battery', 'offers', 'review'], true)) {
            continue;
        }
        foreach ($questionnaire->questionsForPanel($panel) as $key => $question) {
            if (isset($question['options'])) {
                $publicQuestionKeys[] = $key;
            }
        }
    }
    $editorQuestions = $questionnaire->conditionEditorQuestions();
    $test->assert($publicQuestionKeys === array_column($editorQuestions, 'question_key'), 'Condition editor uses the public questionnaire question order directly');
    $screenQuestion = array_values(array_filter($editorQuestions, static fn (array $question): bool => $question['question_key'] === 'screen_condition'))[0] ?? null;
    $test->assert($screenQuestion !== null && ($screenQuestion['options'][4]['answer_key'] ?? null) === 'damaged' && ($screenQuestion['options'][4]['condition_key'] ?? null) === 'screen_condition', 'Condition editor uses the canonical public answer keys and condition mapping');
    $matrixCurrent = $books->getById($matrixBook->id());
    $saveMatrix->handle(new SaveDraftBasePriceMatrix($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, [
        $firstConfiguration->modelKey => [(string) $firstConfiguration->storageGb => '125000'],
    ]));
    pricingAddRule($addRule, $books, $matrixBook->id(), pricingDefinition(PricingRuleKind::MODE_ADJUSTMENT, 'matrix-mode-' . $runToken));
    $conditionSubmission = pricingConditionSubmission($questionnaire);
    $conditionSubmission['screen_condition']['damaged'] = ['action' => SaveDraftQuestionnaireConditionsHandler::ACTION_FIXED, 'value' => '35000'];
    $matrixCurrent = $books->getById($matrixBook->id());
    $saveConditions->handle(new SaveDraftQuestionnaireConditions($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, 'iphone_11_pro', $conditionSubmission));
    $damagedCode = SaveDraftQuestionnaireConditionsHandler::ruleCode($matrixBook->id()->toInt(), 'iphone_11_pro', 'screen_condition', 'damaged');
    $damagedRule = array_values(array_filter($rules->listForPriceBook($matrixBook->id()), static fn (PricingRule $rule): bool => $rule->definition()->code->code() === $damagedCode))[0] ?? null;
    $test->assert($damagedRule instanceof PricingRule && $damagedRule->definition()->amount?->amount() === 35000 && $damagedRule->definition()->modelKey === 'iphone_11_pro', 'Condition editor creates a model-specific deduction from a real questionnaire answer');
    $test->assert(count(array_filter($rules->listForPriceBook($matrixBook->id()), static fn (PricingRule $rule): bool => $rule->definition()->kind->code() === PricingRuleKind::BASE_PRICE && $rule->definition()->amount?->amount() === 125000)) === 1, 'Condition editor preserves existing base-price rules');
    $test->assert(count(array_filter($rules->listForPriceBook($matrixBook->id()), static fn (PricingRule $rule): bool => $rule->definition()->code->code() === 'matrix-unrelated-' . $runToken && $rule->definition()->amount?->amount() === 1500000)) === 1, 'Condition editor preserves existing battery rules');
    $test->assert(count(array_filter($rules->listForPriceBook($matrixBook->id()), static fn (PricingRule $rule): bool => $rule->definition()->kind->code() === PricingRuleKind::MODE_ADJUSTMENT)) === 1, 'Condition editor preserves unrelated offer-mode rules');
    $iphone16Submission = pricingConditionSubmission($questionnaire);
    $iphone16Submission['screen_condition']['damaged'] = ['action' => SaveDraftQuestionnaireConditionsHandler::ACTION_FIXED, 'value' => '75000'];
    $matrixCurrent = $books->getById($matrixBook->id());
    $saveConditions->handle(new SaveDraftQuestionnaireConditions($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, 'iphone_16_pro', $iphone16Submission));
    $iphone16Code = SaveDraftQuestionnaireConditionsHandler::ruleCode($matrixBook->id()->toInt(), 'iphone_16_pro', 'screen_condition', 'damaged');
    $test->assert(count(array_filter($rules->listForPriceBook($matrixBook->id()), static fn (PricingRule $rule): bool => $rule->definition()->code->code() === $damagedCode && $rule->definition()->amount?->amount() === 35000)) === 1 && count(array_filter($rules->listForPriceBook($matrixBook->id()), static fn (PricingRule $rule): bool => $rule->definition()->code->code() === $iphone16Code && $rule->definition()->amount?->amount() === 75000)) === 1, 'iPhone 11 Pro and iPhone 16 Pro keep independent fixed deductions for the same answer');
    $conditionSubmission['screen_condition']['damaged'] = ['action' => SaveDraftQuestionnaireConditionsHandler::ACTION_PERCENTAGE, 'value' => '10'];
    $conditionSubmission['other_defects']['face_id'] = ['action' => SaveDraftQuestionnaireConditionsHandler::ACTION_MANUAL_REVIEW, 'value' => ''];
    $conditionSubmission['other_defects']['rear_camera'] = ['action' => SaveDraftQuestionnaireConditionsHandler::ACTION_HARD_REJECT, 'value' => ''];
    $matrixCurrent = $books->getById($matrixBook->id());
    $saveConditions->handle(new SaveDraftQuestionnaireConditions($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, 'iphone_11_pro', $conditionSubmission));
    $conditionRules = array_values(array_filter($rules->listForPriceBook($matrixBook->id()), static fn (PricingRule $rule): bool => str_starts_with($rule->definition()->code->code(), 'questionnaire-condition-')));
    $updatedDamagedRule = array_values(array_filter($conditionRules, static fn (PricingRule $rule): bool => $rule->definition()->code->code() === $damagedCode))[0] ?? null;
    $test->assert(count(array_filter($conditionRules, static fn (PricingRule $rule): bool => $rule->definition()->code->code() === $damagedCode)) === 1 && $updatedDamagedRule?->definition()->multiplier?->value() === 9000, 'Condition editor updates the deterministic rule and uses existing percentage multiplier semantics');
    $test->assert(count(array_filter($conditionRules, static fn (PricingRule $rule): bool => $rule->definition()->kind->code() === PricingRuleKind::MANUAL_REVIEW)) === 1 && count(array_filter($conditionRules, static fn (PricingRule $rule): bool => $rule->definition()->kind->code() === PricingRuleKind::HARD_REJECT)) === 1, 'Condition editor maps manual review and rejection without financial values');
    $conditionSubmission['screen_condition']['damaged'] = ['action' => SaveDraftQuestionnaireConditionsHandler::ACTION_NONE, 'value' => ''];
    $matrixCurrent = $books->getById($matrixBook->id());
    $saveConditions->handle(new SaveDraftQuestionnaireConditions($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, 'iphone_11_pro', $conditionSubmission));
    $test->assert(count(array_filter($rules->listForPriceBook($matrixBook->id()), static fn (PricingRule $rule): bool => $rule->definition()->code->code() === $damagedCode)) === 0, 'Nincs változás removes only the matching draft questionnaire rule');
    $invalidConditionSubmission = $conditionSubmission;
    $invalidConditionSubmission['other_defects']['rear_camera'] = ['action' => SaveDraftQuestionnaireConditionsHandler::ACTION_HARD_REJECT, 'value' => '1'];
    $matrixCurrent = $books->getById($matrixBook->id());
    $ruleCountBeforeInvalidCondition = count($rules->listForPriceBook($matrixBook->id()));
    $test->throws(fn () => $saveConditions->handle(new SaveDraftQuestionnaireConditions($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, 'iphone_11_pro', $invalidConditionSubmission)), InvalidArgumentException::class, 'Condition editor rejects stale financial values for rejection');
    $test->assert(count($rules->listForPriceBook($matrixBook->id())) === $ruleCountBeforeInvalidCondition, 'Invalid condition submissions leave all rules unchanged');
    $invalidConditionSubmission = $conditionSubmission;
    $invalidConditionSubmission['network_status'] = ['locked' => ['action' => SaveDraftQuestionnaireConditionsHandler::ACTION_FIXED, 'value' => '1']];
    $matrixCurrent = $books->getById($matrixBook->id());
    $test->throws(fn () => $saveConditions->handle(new SaveDraftQuestionnaireConditions($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, 'iphone_11_pro', $invalidConditionSubmission)), InvalidArgumentException::class, 'Condition editor does not make immutable network rejection configurable');
    $test->throws(fn () => $saveConditions->handle(new SaveDraftQuestionnaireConditions($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, 'iphone_not_in_inventory', $conditionSubmission)), InvalidArgumentException::class, 'Condition editor rejects an unknown inventory model key');

    $batteryQuestion = $questionnaire->questions()['battery_health'] ?? [];
    $test->assert(($batteryQuestion['type'] ?? null) === 'range' && ($batteryQuestion['min'] ?? null) === 70 && ($batteryQuestion['max'] ?? null) === 100, 'Battery editor uses the public questionnaire battery key and integer 70–100 percentage range');
    $modelKeys = array_map(static fn ($item): string => $item->modelKey, $catalog->iPhoneModels());
    $test->assert(in_array('iphone_11_pro', $modelKeys, true) && in_array('iphone_16_pro', $modelKeys, true), 'Battery editor model selector source is the inventory-backed device catalog');
    $batteryRulesBefore = $rules->listForPriceBook($matrixBook->id());
    $nonBatteryBefore = array_values(array_filter($batteryRulesBefore, static fn (PricingRule $rule): bool => $rule->definition()->conditionKey !== 'battery_health' || $rule->definition()->modelKey === null));
    $matrixCurrent = $books->getById($matrixBook->id());
    $saveBatteryBands->handle(new SaveDraftBatteryBands($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, 'iphone_11_pro', [pricingBatteryBand(null, 70, 79, SaveDraftBatteryBandsHandler::ACTION_FIXED, '9000')]));
    $iphone11Bands = pricingModelBatteryRules($rules->listForPriceBook($matrixBook->id()), 'iphone_11_pro');
    $iphone11Band = $iphone11Bands[0] ?? null;
    $test->assert(count($iphone11Bands) === 1 && $iphone11Band?->definition()->amount?->amount() === 9000 && $iphone11Band?->definition()->comparisonValue === [70, 79], 'Battery editor creates a model-specific 70–79% fixed-deduction band');
    $matrixCurrent = $books->getById($matrixBook->id());
    $saveBatteryBands->handle(new SaveDraftBatteryBands($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, 'iphone_16_pro', [pricingBatteryBand(null, 70, 79, SaveDraftBatteryBandsHandler::ACTION_FIXED, '21000')]));
    $iphone16Bands = pricingModelBatteryRules($rules->listForPriceBook($matrixBook->id()), 'iphone_16_pro');
    $iphone16Band = $iphone16Bands[0] ?? null;
    $test->assert(count($iphone16Bands) === 1 && $iphone16Band?->definition()->amount?->amount() === 21000, 'Two models keep different battery deductions for the same 79% value');
    $matrixCurrent = $books->getById($matrixBook->id());
    $saveBatteryBands->handle(new SaveDraftBatteryBands($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, 'iphone_11_pro', [pricingBatteryBand($iphone11Band, 70, 79, SaveDraftBatteryBandsHandler::ACTION_FIXED, '10000')]));
    $iphone11Band = pricingModelBatteryRules($rules->listForPriceBook($matrixBook->id()), 'iphone_11_pro')[0] ?? null;
    $test->assert($iphone11Band?->definition()->amount?->amount() === 10000, 'Battery editor updates one selected model band in place');
    $test->assert((pricingModelBatteryRules($rules->listForPriceBook($matrixBook->id()), 'iphone_16_pro')[0] ?? null)?->definition()->amount?->amount() === 21000, 'Saving iPhone 11 Pro does not modify iPhone 16 Pro battery rules');
    $matrixCurrent = $books->getById($matrixBook->id());
    $test->throws(fn () => $saveBatteryBands->handle(new SaveDraftBatteryBands($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, 'iphone_11_pro', [pricingBatteryBand($iphone11Band, 70, 79, SaveDraftBatteryBandsHandler::ACTION_FIXED, '10000'), pricingBatteryBand(null, 79, 90, SaveDraftBatteryBandsHandler::ACTION_FIXED, '1')])), InvalidArgumentException::class, 'Overlapping battery bands are rejected including their shared boundary');
    $matrixCurrent = $books->getById($matrixBook->id());
    $test->throws(fn () => $saveBatteryBands->handle(new SaveDraftBatteryBands($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, 'iphone_11_pro', [pricingBatteryBand($iphone11Band, 70, 79, SaveDraftBatteryBandsHandler::ACTION_FIXED, '10000'), pricingBatteryBand(null, 70, 79, SaveDraftBatteryBandsHandler::ACTION_FIXED, '1')])), InvalidArgumentException::class, 'Duplicate identical battery bands are rejected');
    $matrixCurrent = $books->getById($matrixBook->id());
    $test->throws(fn () => $saveBatteryBands->handle(new SaveDraftBatteryBands($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, 'iphone_11_pro', [pricingBatteryBand($iphone11Band, 90, 80, SaveDraftBatteryBandsHandler::ACTION_FIXED, '1')])), InvalidArgumentException::class, 'Reversed battery boundaries are rejected');
    $matrixCurrent = $books->getById($matrixBook->id());
    $test->throws(fn () => $saveBatteryBands->handle(new SaveDraftBatteryBands($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, 'iphone_11_pro', [pricingBatteryBand($iphone11Band, 69, 79, SaveDraftBatteryBandsHandler::ACTION_FIXED, '1')])), InvalidArgumentException::class, 'Battery boundaries outside the public questionnaire range are rejected');
    $matrixCurrent = $books->getById($matrixBook->id());
    $test->throws(fn () => $saveBatteryBands->handle(new SaveDraftBatteryBands($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, 'iphone_11_pro', [pricingBatteryBand($iphone11Band, 70, 79, 'invalid', '1')])), InvalidArgumentException::class, 'Unknown battery action is rejected');
    $matrixCurrent = $books->getById($matrixBook->id());
    $saveBatteryBands->handle(new SaveDraftBatteryBands($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, 'iphone_11_pro', [pricingBatteryBand($iphone11Band, 70, 79, SaveDraftBatteryBandsHandler::ACTION_MANUAL_REVIEW, '999') ]));
    $iphone11Band = pricingModelBatteryRules($rules->listForPriceBook($matrixBook->id()), 'iphone_11_pro')[0] ?? null;
    $test->assert($iphone11Band?->definition()->amount === null && $iphone11Band?->definition()->multiplier === null && $iphone11Band?->definition()->kind->code() === PricingRuleKind::MANUAL_REVIEW, 'Changing action server-side clears stale incompatible financial values');
    $matrixCurrent = $books->getById($matrixBook->id());
    $saveBatteryBands->handle(new SaveDraftBatteryBands($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, 'iphone_11_pro', [pricingBatteryBand($iphone11Band, 70, 79, SaveDraftBatteryBandsHandler::ACTION_FIXED, '9000') ]));
    $iphone11Band = pricingModelBatteryRules($rules->listForPriceBook($matrixBook->id()), 'iphone_11_pro')[0] ?? null;
    $test->throws(fn () => $saveBatteryBands->handle(new SaveDraftBatteryBands($matrixBook->id()->toInt(), $books->getById($matrixBook->id())?->version()->value() ?? -1, 'not_inventory', [pricingBatteryBand(null, 70, 79, SaveDraftBatteryBandsHandler::ACTION_FIXED, '1')])), InvalidArgumentException::class, 'Unknown inventory model is rejected by the battery handler');
    $test->throws(fn () => $saveBatteryBands->handle(new SaveDraftBatteryBands($activeBook->id()->toInt(), $books->getById($activeBook->id())?->version()->value() ?? -1, 'iphone_11_pro', [])), InvalidAggregateOperationException::class, 'Battery handler rejects crafted edits to an active price book');
    $test->throws(fn () => $saveBatteryBands->handle(new SaveDraftBatteryBands($retiredBook->id()->toInt(), $books->getById($retiredBook->id())?->version()->value() ?? -1, 'iphone_11_pro', [])), InvalidAggregateOperationException::class, 'Battery handler rejects crafted edits to an archived price book');
    $nonBatteryAfter = array_values(array_filter($rules->listForPriceBook($matrixBook->id()), static fn (PricingRule $rule): bool => $rule->definition()->conditionKey !== 'battery_health' || $rule->definition()->modelKey === null));
    $test->assert(serialize(array_map(static fn (PricingRule $rule): string => $rule->definition()->code->code(), $nonBatteryAfter)) === serialize(array_map(static fn (PricingRule $rule): string => $rule->definition()->code->code(), $nonBatteryBefore)), 'Battery saves preserve base-price, Conditions, offer-mode, system and legacy global rules');

    $test->assert(OfferModeDefinition::keys() === ['in_store_instant', 'fast_online', 'higher_offer', 'trade_in'] && OfferModeDefinition::all()['higher_offer']['label'] === 'Magasabb ajánlat', 'Offer-mode editor uses the one canonical shared public definition source');
    $offerIsolationBefore = array_values(array_filter($rules->listForPriceBook($matrixBook->id()), static fn (PricingRule $rule): bool => $rule->definition()->kind->code() !== PricingRuleKind::MODE_ADJUSTMENT));
    $offerSubmission = [
        ['mode' => 'in_store_instant', 'type' => 'amount', 'value' => '-12000'],
        ['mode' => 'fast_online', 'type' => 'multiplier', 'value' => '-5'],
        ['mode' => 'higher_offer', 'type' => 'multiplier', 'value' => '+2.5'],
        ['mode' => 'trade_in', 'type' => 'multiplier', 'value' => '+5'],
    ];
    $matrixCurrent = $books->getById($matrixBook->id());
    $saveOfferModes->handle(new SaveDraftOfferModeModifiers($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, $offerSubmission));
    $offerRules = array_values(array_filter($rules->listForPriceBook($matrixBook->id()), static fn (PricingRule $rule): bool => $rule->definition()->kind->code() === PricingRuleKind::MODE_ADJUSTMENT));
    $test->assert(count($offerRules) === 4 && count(array_unique(array_map(static fn (PricingRule $rule): ?string => $rule->definition()->serviceMode, $offerRules))) === 4 && $offerRules[0]->definition()->modelKey === null, 'Offer-mode save creates exactly one price-book-wide rule per canonical mode');
    $inStore = array_values(array_filter($offerRules, static fn (PricingRule $rule): bool => $rule->definition()->serviceMode === 'in_store_instant'))[0] ?? null;
    $test->assert($inStore?->definition()->amount?->amount() === -12000, 'Offer-mode editor persists a signed fixed HUF modifier using the real rule shape');
    $fastOnline = array_values(array_filter($offerRules, static fn (PricingRule $rule): bool => $rule->definition()->serviceMode === 'fast_online'))[0] ?? null;
    $test->assert($fastOnline?->definition()->multiplier?->value() === 9500, 'Offer-mode editor maps -5% to the existing 95% engine multiplier representation');
    $offerSubmission[0]['value'] = '-15000';
    $matrixCurrent = $books->getById($matrixBook->id());
    $saveOfferModes->handle(new SaveDraftOfferModeModifiers($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, $offerSubmission));
    $offerRules = array_values(array_filter($rules->listForPriceBook($matrixBook->id()), static fn (PricingRule $rule): bool => $rule->definition()->kind->code() === PricingRuleKind::MODE_ADJUSTMENT));
    $updatedInStore = array_values(array_filter($offerRules, static fn (PricingRule $rule): bool => $rule->definition()->serviceMode === 'in_store_instant'))[0] ?? null;
    $test->assert(count($offerRules) === 4 && $updatedInStore?->definition()->amount?->amount() === -15000, 'Repeated offer-mode save updates the matching rule without creating duplicates');
    $offerSubmission[2]['value'] = '';
    $matrixCurrent = $books->getById($matrixBook->id());
    $saveOfferModes->handle(new SaveDraftOfferModeModifiers($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, $offerSubmission));
    $offerRules = array_values(array_filter($rules->listForPriceBook($matrixBook->id()), static fn (PricingRule $rule): bool => $rule->definition()->kind->code() === PricingRuleKind::MODE_ADJUSTMENT));
    $test->assert(count($offerRules) === 3 && ! in_array('higher_offer', array_map(static fn (PricingRule $rule): ?string => $rule->definition()->serviceMode, $offerRules), true), 'Empty offer-mode value removes only that mode rule because absence means zero adjustment');
    $offerSubmission[2]['value'] = '0';
    $matrixCurrent = $books->getById($matrixBook->id());
    $saveOfferModes->handle(new SaveDraftOfferModeModifiers($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, $offerSubmission));
    $offerRules = array_values(array_filter($rules->listForPriceBook($matrixBook->id()), static fn (PricingRule $rule): bool => $rule->definition()->kind->code() === PricingRuleKind::MODE_ADJUSTMENT));
    $test->assert(count($offerRules) === 3 && ! in_array('higher_offer', array_map(static fn (PricingRule $rule): ?string => $rule->definition()->serviceMode, $offerRules), true), 'A zero offer-mode correction is not persisted as a no-op rule');
    $offerSubmission[2]['value'] = '+2.5';
    $matrixCurrent = $books->getById($matrixBook->id());
    $saveOfferModes->handle(new SaveDraftOfferModeModifiers($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, $offerSubmission));
    $matrixCurrent = $books->getById($matrixBook->id());
    $invalidOfferRulesBefore = count($rules->listForPriceBook($matrixBook->id()));
    $invalidOfferSubmission = $offerSubmission; $invalidOfferSubmission[0]['mode'] = 'unknown_mode';
    $test->throws(fn () => $saveOfferModes->handle(new SaveDraftOfferModeModifiers($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, $invalidOfferSubmission)), InvalidArgumentException::class, 'Offer-mode editor rejects an unknown mode');
    $invalidOfferSubmission = $offerSubmission; $invalidOfferSubmission[0]['type'] = 'unsupported';
    $test->throws(fn () => $saveOfferModes->handle(new SaveDraftOfferModeModifiers($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, $invalidOfferSubmission)), InvalidArgumentException::class, 'Offer-mode editor rejects an unsupported modifier type');
    $invalidOfferSubmission = $offerSubmission; $invalidOfferSubmission[0]['value'] = '-1.5';
    $test->throws(fn () => $saveOfferModes->handle(new SaveDraftOfferModeModifiers($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, $invalidOfferSubmission)), InvalidArgumentException::class, 'Offer-mode editor rejects a non-integer fixed HUF modifier');
    $invalidOfferSubmission = $offerSubmission; $invalidOfferSubmission[1]['value'] = '-100.01';
    $test->throws(fn () => $saveOfferModes->handle(new SaveDraftOfferModeModifiers($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, $invalidOfferSubmission)), InvalidArgumentException::class, 'Offer-mode editor rejects a percentage below the engine-safe signed range');
    $test->assert(count($rules->listForPriceBook($matrixBook->id())) === $invalidOfferRulesBefore, 'Invalid offer-mode submissions leave every rule untouched');
    $test->throws(fn () => $saveOfferModes->handle(new SaveDraftOfferModeModifiers($activeBook->id()->toInt(), $books->getById($activeBook->id())?->version()->value() ?? -1, $offerSubmission)), InvalidAggregateOperationException::class, 'Offer-mode handler rejects active price-book edits');
    $test->throws(fn () => $saveOfferModes->handle(new SaveDraftOfferModeModifiers($retiredBook->id()->toInt(), $books->getById($retiredBook->id())?->version()->value() ?? -1, $offerSubmission)), InvalidAggregateOperationException::class, 'Offer-mode handler rejects archived price-book edits');
    $offerIsolationAfter = array_values(array_filter($rules->listForPriceBook($matrixBook->id()), static fn (PricingRule $rule): bool => $rule->definition()->kind->code() !== PricingRuleKind::MODE_ADJUSTMENT));
    $test->assert(serialize(array_map(static fn (PricingRule $rule): string => $rule->definition()->code->code(), $offerIsolationAfter)) === serialize(array_map(static fn (PricingRule $rule): string => $rule->definition()->code->code(), $offerIsolationBefore)), 'Offer-mode saves preserve Base-price, Conditions, Battery and system rules');
    $uiPage = new PriceBooksPage(
        $books,
        $rules,
        $catalog,
        $createBook,
        new ClonePriceBookToDraftHandler($books, $rules, $transactions, $clock),
        new DiscardDraftPriceBookHandler($books, new WordPressDraftPriceBookDiscardRepository($wpdb), $transactions),
        $saveMatrix,
        $saveConditions,
        $saveBatteryBands,
        $saveOfferModes,
        new OfferModeExampleCalculator(new PricingEngine()),
        $updateBook,
        $addRule,
        $updateRule,
        $toggleRule,
        $deleteRule,
        $parser,
        new PreviewDraftPriceBookCalculationHandler($books, $rules, $catalog, new PricingEngine(), $questionnaire),
        new PreviewCalculationFormParser($questionnaire),
        new PriceBookActivationReadinessService($catalog, new PriceBookActivationReadinessEvaluator()),
        new ActivateDraftPriceBookHandler($books, $rules, new PriceBookActivationReadinessService($catalog, new PriceBookActivationReadinessEvaluator()), new MySqlPriceBookActivationLock($wpdb), $transactions, $clock),
        new RepositoryActivePriceBookResolver($books, $rules),
        $clock,
        $authorization,
        new AdminSubmissionGuard(),
        $questionnaire
    );
    $_GET = [];
    ob_start();
    $uiPage->render();
    $priceBookIndexHtml = (string) ob_get_clean();
    $test->assert(str_contains($priceBookIndexHtml, 'Apple Klinika Felvásárlás – Árkönyvek') && str_contains($priceBookIndexHtml, 'Itt kezelheted a nyilvános felvásárlási kalkulátor árait és szabályait.') && str_contains($priceBookIndexHtml, 'Élő árkönyv') && str_contains($priceBookIndexHtml, 'Szerkesztés alatt') && str_contains($priceBookIndexHtml, 'Korábbi verziók') && str_contains($priceBookIndexHtml, 'Technikai részletek') && str_contains($priceBookIndexHtml, 'Új módosítás indítása') && str_contains($priceBookIndexHtml, 'Módosítás elvetése') && str_contains($priceBookIndexHtml, 'data-ak-discard-form') && str_contains($priceBookIndexHtml, 'Haladó beállítások'), 'Price-book index presents owner-facing sections, safe clone guidance and a POST-only draft discard action');
    $_GET = ['book_id' => (string) $matrixBook->id()->toInt(), 'tab' => 'base-prices'];
    ob_start();
    $uiPage->render();
    $matrixHtml = (string) ob_get_clean();
    $test->assert(! str_contains($matrixHtml, 'name="base_prices[iphone_17][128]"') && str_contains($matrixHtml, 'ak-matrix-na'), 'Invalid model/storage intersections render as non-submittable matrix dashes');
    $originalGet = $_GET;
    $_GET = ['book_id' => (string) $matrixBook->id()->toInt(), 'tab' => 'conditions'];
    ob_start();
    $uiPage->render();
    $conditionsHtml = (string) ob_get_clean();
    $test->assert(str_contains($conditionsHtml, 'Hálózatfüggetlen a készülék?') && str_contains($conditionsHtml, 'Művelet') && str_contains($conditionsHtml, 'Rendszerszabály') && ! str_contains($conditionsHtml, 'Az állapotlevonások felhasználóbarát szerkesztője még nem készült el.') && ! str_contains($conditionsHtml, 'Szabálykód') && ! str_contains($conditionsHtml, 'Összehasonlítás értéke') && ! str_contains($conditionsHtml, 'Diagnosztikai azonosító'), 'Normal Conditions tab renders the business questionnaire editor without raw technical fields');
    $_GET = ['book_id' => (string) $matrixBook->id()->toInt(), 'tab' => 'battery', 'model' => 'iphone_11_pro'];
    ob_start();
    $uiPage->render();
    $batteryHtml = (string) ob_get_clean();
    $test->assert(str_contains($batteryHtml, 'Az akkumulátor szabályai ehhez a modellhez') && str_contains($batteryHtml, 'Akkumulátorszabályok mentése – iPhone 11 Pro') && str_contains($batteryHtml, 'name="model"') && str_contains($batteryHtml, 'iphone_11_pro') && str_contains($batteryHtml, 'selected'), 'Battery tab server-renders the selected inventory model and a model-labelled save action');
    $test->assert(str_contains($batteryHtml, 'Új százaléksáv hozzáadása') && str_contains($batteryHtml, 'Minimum (%)') && str_contains($batteryHtml, 'Maximum (%)') && str_contains($batteryHtml, 'Üzleti következmény') && ! str_contains($batteryHtml, 'Szabálykód') && ! str_contains($batteryHtml, 'Prioritás') && ! str_contains($batteryHtml, 'Összehasonlítás értéke'), 'Battery UI exposes business band fields without raw technical pricing fields');
    $_GET = ['book_id' => (string) $activeBook->id()->toInt(), 'tab' => 'battery', 'model' => 'iphone_11_pro'];
    ob_start();
    $uiPage->render();
    $activeBatteryHtml = (string) ob_get_clean();
    $test->assert(str_contains($activeBatteryHtml, 'name="model"') && ! str_contains($activeBatteryHtml, 'data-ak-battery-form') && ! str_contains($activeBatteryHtml, 'battery_bands[') && ! str_contains($activeBatteryHtml, 'Akkumulátorszabályok mentése'), 'Active price-book Battery tab is inspection-only with no editable inputs or save action');
    $_GET = ['book_id' => (string) $matrixBook->id()->toInt(), 'tab' => 'offer-modes'];
    ob_start();
    $uiPage->render();
    $offerModesHtml = (string) ob_get_clean();
    $test->assert(substr_count($offerModesHtml, 'data-ak-offer-mode-row') === 4 && str_contains($offerModesHtml, 'Ajánlattípusok mentése') && str_contains($offerModesHtml, 'Azonnali személyes felvásárlás') && str_contains($offerModesHtml, 'Gyors felvásárlás') && str_contains($offerModesHtml, 'Magasabb ajánlat') && str_contains($offerModesHtml, 'Azonnali beszámítás'), 'Offer-mode tab renders exactly four shared public offer modes and one clear save action');
    $test->assert(! str_contains($offerModesHtml, 'Szabálykód') && ! str_contains($offerModesHtml, 'Prioritás') && ! str_contains($offerModesHtml, 'Összehasonlítás értéke') && ! str_contains($offerModesHtml, 'model_key'), 'Offer-mode UI omits raw technical pricing-rule fields and any model selector');
    $offerModeScript = file_get_contents(APPLEKLINIKA_BUYBACK_PATH . '/assets/admin/price-books.js');
    $test->assert(is_string($offerModeScript) && str_contains($offerModeScript, "if (value) value.value = '';") && str_contains($offerModeScript, "if (remove.checked) value.value = '';") && str_contains($offerModeScript, "'missing|' + type.value"), 'Offer-mode client contract clears incompatible type-switch values and keeps missing-row change tracking type-aware');
    $_GET = ['book_id' => (string) $activeBook->id()->toInt(), 'tab' => 'offer-modes'];
    ob_start();
    $uiPage->render();
    $activeOfferModesHtml = (string) ob_get_clean();
    $test->assert(substr_count($activeOfferModesHtml, 'data-ak-offer-mode-row') === 4 && ! str_contains($activeOfferModesHtml, 'data-ak-offer-mode-form') && ! str_contains($activeOfferModesHtml, 'Ajánlattípusok mentése') && ! str_contains($activeOfferModesHtml, 'offer_mode_modifiers['), 'Active price-book Offer-mode tab is completely read-only');
    $matrixCurrent = $books->getById($matrixBook->id());
    $saveBatteryBands->handle(new SaveDraftBatteryBands($matrixBook->id()->toInt(), $matrixCurrent?->version()->value() ?? -1, 'iphone_11_pro', [pricingBatteryBand($iphone11Band, 70, 79, SaveDraftBatteryBandsHandler::ACTION_NONE, '', true)]));
    $test->assert(pricingModelBatteryRules($rules->listForPriceBook($matrixBook->id()), 'iphone_11_pro') === [] && count(pricingModelBatteryRules($rules->listForPriceBook($matrixBook->id()), 'iphone_16_pro')) === 1, 'Explicit deletion removes only the selected model battery band and preserves other models');
    $_GET = ['book_id' => (string) $matrixBook->id()->toInt(), 'tab' => 'preview'];
    ob_start();
    $uiPage->render();
    $previewHtml = (string) ob_get_clean();
    $test->assert(str_contains($previewHtml, 'Tesztkalkuláció futtatása'), 'Preview tab renders the non-persistent calculation action');
    $test->assert(str_contains($previewHtml, 'Hálózatfüggetlen a készülék?'), 'Preview tab renders the shared public questionnaire');
    $test->assert(! str_contains($previewHtml, 'name="rule_code"'), 'Preview tab omits raw rule-programming fields');
    $previewPageSource = file_get_contents(APPLEKLINIKA_BUYBACK_PATH . '/src/Interfaces/Admin/PriceBooksPage.php');
    $test->assert(is_string($previewPageSource) && str_contains($previewPageSource, 'ak-preview-breakdown') && str_contains($previewPageSource, 'ak-preview-offer-grid') && str_contains($previewPageSource, 'Elnyomott örökölt globális szabályok') && ! str_contains($previewPageSource, '<table class="widefat striped"><thead><tr><th>Lépés</th>'), 'Preview results render one shared breakdown and a separate offer-mode card grid without a clipped table');
    $_GET = $originalGet;
    $test->assert((new WordPressDeviceCatalogReader('qa_missing_catalog_' . $runToken, static fn (): bool => true))->iPhoneModels() !== [], 'Inventory runtime catalogue takes priority over the legacy option fallback');
    $test->throws(fn () => (new WordPressDeviceCatalogReader('appleklinika_device_catalog', static fn (): bool => false))->iPhoneModels(), DeviceCatalogUnavailableException::class, 'Inactive inventory plugin produces a safe failure');

    $test->assert(method_exists(PriceBook::class, 'activate') && method_exists(PriceBook::class, 'retire') && ! method_exists(PriceBook::class, 'setStatus'), 'Only controlled activation and retirement lifecycle APIs exist');
    $test->assert(! class_exists('AppleKlinika\\Buyback\\Application\\Pricing\\PricingCalculator'), 'No pricing calculator service exists');
    $test->assert(! in_array('price_book_id', Schema::requiredColumns()[Schema::REQUESTS], true), 'Buyback requests have no price-book reference');
    $sourceIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APPLEKLINIKA_BUYBACK_PATH . '/src'));
    $publicRegistrationFound = false;
    foreach ($sourceIterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        if (is_string($contents) && (str_contains($contents, 'register_rest_route') || str_contains($contents, 'wp_ajax_nopriv_'))) {
            $publicRegistrationFound = true;
            break;
        }
    }
    $test->assert(! $publicRegistrationFound, 'No public REST or unauthenticated AJAX endpoint exists');

    Deactivator::deactivate();
    foreach ([Schema::PRICE_BOOKS, Schema::PRICE_RULES] as $key) {
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tables[$key])));
        $test->assert($exists === $tables[$key], "Deactivation retains {$key}");
    }
    (new CapabilityManager())->grant();
    $test->assert(get_role('administrator')?->has_cap(CapabilityManager::MANAGE_PRICE_BOOKS) === true, 'Capability grant is restored for administrator');
    $test->assert(get_role('shop_manager')?->has_cap(CapabilityManager::MANAGE_PRICE_BOOKS) === true, 'Capability grant is restored for shop_manager');
    $test->assert(get_role('customer')?->has_cap(CapabilityManager::MANAGE_PRICE_BOOKS) !== true, 'Customer never receives pricing capability');
    $test->assert(get_role('subscriber')?->has_cap(CapabilityManager::MANAGE_PRICE_BOOKS) !== true, 'Subscriber never receives pricing capability');

    $test->assert(hash('sha256', serialize(get_option('appleklinika_device_catalog', null))) === $catalogHashBefore, 'Device catalog adapter performs no inventory writes');
    $test->assert((string) get_option(Schema::OPTION_PLUGIN_VERSION, '') === '0.7.0', 'Installed plugin version is 0.7.0');
} catch (Throwable $exception) {
    $test->fail($exception);
} finally {
    try {
        pricingCleanup($wpdb, $tables, $marker);
        foreach ($guardTransientKeys as $key) {
            delete_transient($key);
        }
        foreach ($createdUserIds as $userId) {
            wp_delete_user($userId);
        }
        wp_set_current_user($originalUserId);
        (new CapabilityManager())->grant();
        update_option(Schema::OPTION_SCHEMA_VERSION, APPLEKLINIKA_BUYBACK_SCHEMA_VERSION, false);
        update_option(Schema::OPTION_PLUGIN_VERSION, APPLEKLINIKA_BUYBACK_VERSION, false);
    } catch (Throwable $cleanupException) {
        $test->fail($cleanupException);
    }
}

$countsAfter = pricingRowCounts($wpdb, $tables);
$legacyHashAfter = pricingLegacyHash($wpdb);
$activeAfter = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$tables[Schema::PRICE_BOOKS]}` WHERE status = %s", PriceBookStatus::ACTIVE));
$test->assert($countsAfter === $countsBefore, 'All five Buyback table counts return to pre-test values');
$test->assert($legacyHashAfter === $legacyHashBefore, 'Legacy user-meta hash remains unchanged');
$test->assert($activeAfter === $activeBefore, 'Active price-book count returns to pre-test value');
$test->assert(hash('sha256', serialize(get_option('appleklinika_device_catalog', null))) === $catalogHashBefore, 'Inventory catalog hash remains unchanged after cleanup');
$test->assert((string) get_option(Schema::OPTION_SCHEMA_VERSION) === '1.1.0', 'Installed schema ends at 1.1.0');
$test->assert((string) get_option(Schema::OPTION_PLUGIN_VERSION) === '0.7.0', 'Installed plugin option ends at 0.7.0');
$test->assert((int) $wpdb->get_var('SELECT @@in_transaction') === 0, 'Cleanup leaves no database transaction open');
foreach ($phaseOneStructureBefore as $key => $signature) {
    $test->assert(pricingTableStructureHash($wpdb, $tables[$key]) === $signature, "Phase 1 table {$key} remains unchanged after cleanup");
}

$test->finish($countsBefore, $countsAfter, $marker, $legacyHashBefore, $legacyHashAfter);
