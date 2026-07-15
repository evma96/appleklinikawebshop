<?php

declare(strict_types=1);

$wordpressRoot = dirname(__DIR__, 4);
require_once $wordpressRoot . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use AppleKlinika\Buyback\Application\Command\AddDraftPricingRule;
use AppleKlinika\Buyback\Application\Command\CreateDraftPriceBook;
use AppleKlinika\Buyback\Application\Command\DeleteDraftPricingRule;
use AppleKlinika\Buyback\Application\Command\ToggleDraftPricingRule;
use AppleKlinika\Buyback\Application\Command\UpdateDraftPriceBookSettings;
use AppleKlinika\Buyback\Application\Command\UpdateDraftPricingRule;
use AppleKlinika\Buyback\Application\Exception\DeviceCatalogUnavailableException;
use AppleKlinika\Buyback\Application\Exception\DuplicatePriceBookVersionException;
use AppleKlinika\Buyback\Application\Exception\DuplicatePricingRuleCodeException;
use AppleKlinika\Buyback\Application\Exception\PricingRuleNotFoundException;
use AppleKlinika\Buyback\Application\Handler\AddDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Handler\CreateDraftPriceBookHandler;
use AppleKlinika\Buyback\Application\Handler\DeleteDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Handler\ToggleDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Handler\UpdateDraftPriceBookSettingsHandler;
use AppleKlinika\Buyback\Application\Handler\UpdateDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Domain\Exception\InvalidAggregateOperationException;
use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;
use AppleKlinika\Buyback\Domain\Exception\StaleAggregateVersionException;
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
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPriceBookRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPricingRuleRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressTransactionManager;
use AppleKlinika\Buyback\Infrastructure\WordPress\CapabilityManager;
use AppleKlinika\Buyback\Infrastructure\WordPress\Deactivator;
use AppleKlinika\Buyback\Infrastructure\WordPress\Plugin;
use AppleKlinika\Buyback\Interfaces\Admin\AdminAuthorization;
use AppleKlinika\Buyback\Interfaces\Admin\AdminSubmissionGuard;
use AppleKlinika\Buyback\Interfaces\Admin\PriceBooksPage;
use AppleKlinika\Buyback\Interfaces\Admin\PricingRuleFormParser;

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
        PricingRuleKind::MULTIPLIER => new PricingRuleDefinition($ruleCode, $ruleKind, 'iphone', null, null, null, 'screen_condition', new ComparisonOperator(ComparisonOperator::EQUALS), 'scratched', null, new BasisPointsMultiplier(9000), $rulePriority, $enabled, null, 'QA multiplier'),
        PricingRuleKind::MODE_ADJUSTMENT => new PricingRuleDefinition($ruleCode, $ruleKind, 'iphone', null, null, 'fast_online', null, null, null, new Money(500000, 'HUF'), null, $rulePriority, $enabled, null, 'QA mode adjustment'),
        PricingRuleKind::HARD_REJECT => new PricingRuleDefinition($ruleCode, $ruleKind, 'iphone', null, null, null, 'liquid_damage', new ComparisonOperator(ComparisonOperator::EQUALS), true, null, null, $rulePriority, $enabled, 'Folyadékkár miatt nem adható ajánlat.', 'QA reject'),
        PricingRuleKind::MANUAL_REVIEW => new PricingRuleDefinition($ruleCode, $ruleKind, 'iphone', null, null, null, 'replacement_parts', new ComparisonOperator(ComparisonOperator::EQUALS), true, null, null, $rulePriority, $enabled, 'Szakértői ellenőrzés szükséges.', 'QA manual review'),
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
$books = new WordPressPriceBookRepository($wpdb);
$rules = new WordPressPricingRuleRepository($wpdb);
$createBook = new CreateDraftPriceBookHandler($books, $transactions, $clock);
$updateBook = new UpdateDraftPriceBookSettingsHandler($books, $clock);
$addRule = new AddDraftPricingRuleHandler($books, $rules, $transactions, $clock);
$updateRule = new UpdateDraftPricingRuleHandler($books, $rules, $transactions, $clock);
$toggleRule = new ToggleDraftPricingRuleHandler($books, $rules, $transactions, $clock);
$deleteRule = new DeleteDraftPricingRuleHandler($books, $rules, $transactions, $clock);

try {
    $test->assert(is_plugin_active(AK_BUYBACK_PRICING_PLUGIN), 'Buyback plugin is active');
    $test->assert(APPLEKLINIKA_BUYBACK_VERSION === '0.5.0', 'Plugin code version is 0.5.0');
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
    $test->throws(fn () => new PricingRuleDefinition(new PricingRuleCode('bad-target'), new PricingRuleKind(PricingRuleKind::FIXED_DEDUCTION), 'iphone', 'iphone-13-pro', null, null, 'battery_health', new ComparisonOperator(ComparisonOperator::LESS_THAN), 80, new Money(1000, 'HUF'), null, new RulePriority(1), true, null, null), InvalidValueObjectException::class, 'Conditional rule rejects conflicting model target');

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
    $wpdb->update($tables[Schema::PRICE_BOOKS], ['status' => PriceBookStatus::ACTIVE], ['id' => $activeBook->id()->toInt()], ['%s'], ['%d']);
    $wpdb->update($tables[Schema::PRICE_BOOKS], ['status' => PriceBookStatus::RETIRED], ['id' => $retiredBook->id()->toInt()], ['%s'], ['%d']);
    $test->assert($books->hasActiveBook(), 'Repository can detect active books read-only');
    $test->throws(fn () => $updateBook->handle(new UpdateDraftPriceBookSettings($activeBook->id()->toInt(), 0, 'Forbidden active edit', 0, 1, MinimumOfferPolicy::REJECT)), InvalidAggregateOperationException::class, 'Active book settings mutation is rejected');
    $test->throws(fn () => pricingAddRule($addRule, $books, $activeBook->id(), pricingDefinition(PricingRuleKind::BASE_PRICE, 'active-forbidden')), InvalidAggregateOperationException::class, 'Active book rule mutation is rejected');
    $test->throws(fn () => $updateBook->handle(new UpdateDraftPriceBookSettings($retiredBook->id()->toInt(), 0, 'Forbidden retired edit', 0, 1, MinimumOfferPolicy::REJECT)), InvalidAggregateOperationException::class, 'Retired book mutation is rejected');

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
    $test->throws(fn () => (new WordPressDeviceCatalogReader('qa_missing_catalog_' . $runToken, static fn (): bool => true))->iPhoneModels(), DeviceCatalogUnavailableException::class, 'Missing catalog produces a safe failure');
    $test->throws(fn () => (new WordPressDeviceCatalogReader('appleklinika_device_catalog', static fn (): bool => false))->iPhoneModels(), DeviceCatalogUnavailableException::class, 'Inactive inventory plugin produces a safe failure');

    $test->assert(! method_exists(PriceBook::class, 'activate') && ! method_exists(PriceBook::class, 'retire') && ! method_exists(PriceBook::class, 'setStatus'), 'No activation, retirement or arbitrary status mutation API exists');
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
    $test->assert((string) get_option(Schema::OPTION_PLUGIN_VERSION, '') === '0.5.0', 'Installed plugin version is 0.5.0');
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
$test->assert((string) get_option(Schema::OPTION_PLUGIN_VERSION) === '0.5.0', 'Installed plugin option ends at 0.5.0');
$test->assert((int) $wpdb->get_var('SELECT @@in_transaction') === 0, 'Cleanup leaves no database transaction open');
foreach ($phaseOneStructureBefore as $key => $signature) {
    $test->assert(pricingTableStructureHash($wpdb, $tables[$key]) === $signature, "Phase 1 table {$key} remains unchanged after cleanup");
}

$test->finish($countsBefore, $countsAfter, $marker, $legacyHashBefore, $legacyHashAfter);
