<?php

declare(strict_types=1);

$wordpressRoot = dirname(__DIR__, 4);
require_once $wordpressRoot . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use AppleKlinika\Buyback\Application\Command\ActivateDraftPriceBook;
use AppleKlinika\Buyback\Application\Command\AddDraftPricingRule;
use AppleKlinika\Buyback\Application\Command\CreateDraftPriceBook;
use AppleKlinika\Buyback\Application\Diagnostics\GetDiagnosticsHandler;
use AppleKlinika\Buyback\Application\Exception\InvalidActivationConfirmationException;
use AppleKlinika\Buyback\Application\Exception\MultipleActivePriceBooksException;
use AppleKlinika\Buyback\Application\Exception\NoActivePriceBookException;
use AppleKlinika\Buyback\Application\Exception\PriceBookActivationBusyException;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotReadyForActivationException;
use AppleKlinika\Buyback\Application\Handler\ActivateDraftPriceBookHandler;
use AppleKlinika\Buyback\Application\Handler\AddDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Handler\CreateDraftPriceBookHandler;
use AppleKlinika\Buyback\Application\Handler\DeleteDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Handler\PreviewDraftPriceBookCalculationHandler;
use AppleKlinika\Buyback\Application\Handler\ToggleDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Handler\UpdateDraftPriceBookSettingsHandler;
use AppleKlinika\Buyback\Application\Handler\UpdateDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\DeviceCatalogReader;
use AppleKlinika\Buyback\Application\Port\PriceBookActivationLock;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Pricing\DeviceCatalogItem;
use AppleKlinika\Buyback\Application\Pricing\PriceBookActivationReadinessService;
use AppleKlinika\Buyback\Application\Pricing\PriceBookPage;
use AppleKlinika\Buyback\Application\Pricing\RepositoryActivePriceBookResolver;
use AppleKlinika\Buyback\Domain\Exception\InvalidAggregateOperationException;
use AppleKlinika\Buyback\Domain\Exception\StaleAggregateVersionException;
use AppleKlinika\Buyback\Domain\Pricing\BasisPointsMultiplier;
use AppleKlinika\Buyback\Domain\Pricing\ComparisonOperator;
use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;
use AppleKlinika\Buyback\Domain\Pricing\MinimumOfferPolicy;
use AppleKlinika\Buyback\Domain\Pricing\PriceBook;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookActivationReadinessEvaluator;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookStatus;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookVersionNumber;
use AppleKlinika\Buyback\Domain\Pricing\PricingActorId;
use AppleKlinika\Buyback\Domain\Pricing\PricingEngine;
use AppleKlinika\Buyback\Domain\Pricing\PricingRule;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleCode;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleDefinition;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleId;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind;
use AppleKlinika\Buyback\Domain\Pricing\RulePriority;
use AppleKlinika\Buyback\Domain\Pricing\StorageCapacity;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Domain\Shared\Money;
use AppleKlinika\Buyback\Infrastructure\Persistence\Exception\PersistenceException;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\MySqlPriceBookActivationLock;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\Schema;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\SchemaInspector;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPriceBookRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPricingRuleRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressTransactionManager;
use AppleKlinika\Buyback\Infrastructure\WordPress\CapabilityManager;
use AppleKlinika\Buyback\Infrastructure\WordPress\LegacyBuybackDetector;
use AppleKlinika\Buyback\Infrastructure\WordPress\WordPressEnvironmentDiagnosticsReader;
use AppleKlinika\Buyback\Interfaces\Admin\AdminAuthorization;
use AppleKlinika\Buyback\Interfaces\Admin\AdminSubmissionGuard;
use AppleKlinika\Buyback\Interfaces\Admin\DiagnosticsPage;
use AppleKlinika\Buyback\Interfaces\Admin\PreviewCalculationFormParser;
use AppleKlinika\Buyback\Interfaces\Admin\PriceBooksPage;
use AppleKlinika\Buyback\Interfaces\Admin\PricingRuleFormParser;

const AK_ACTIVATION_LEGACY_META = 'appleklinika_buyback_records';

final class ActivationTestRunner
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

    /** @param array<string,int> $before @param array<string,int> $after */
    public function finish(array $before, array $after, int $activeBefore, int $activeAfter, string $legacyBefore, string $legacyAfter, string $marker, bool $lockFree): never
    {
        if ($this->failures !== []) {
            foreach ($this->failures as $failure) {
                fwrite(STDERR, "FAIL: {$failure}\n");
            }
            fwrite(STDERR, sprintf("%d assertion(s), %d failure(s); marker %s.\n", $this->assertions, count($this->failures), $marker));
            exit(1);
        }

        echo sprintf(
            "Buyback price-book activation tests passed: %d assertions; marker %s; rows before/after price_books %d/%d, price_rules %d/%d, active_HUF %d/%d, requests %d/%d, snapshots %d/%d, events %d/%d; legacy hash %s; activation lock %s.\n",
            $this->assertions,
            $marker,
            $before[Schema::PRICE_BOOKS], $after[Schema::PRICE_BOOKS],
            $before[Schema::PRICE_RULES], $after[Schema::PRICE_RULES],
            $activeBefore, $activeAfter,
            $before[Schema::REQUESTS], $after[Schema::REQUESTS],
            $before[Schema::SNAPSHOTS], $after[Schema::SNAPSHOTS],
            $before[Schema::EVENTS], $after[Schema::EVENTS],
            $legacyBefore === $legacyAfter ? 'unchanged' : 'changed',
            $lockFree ? 'released' : 'held'
        );
        exit(0);
    }
}

final class ActivationFixedClock implements Clock
{
    public function __construct(private DateTimeImmutable $time) {}
    public function now(): DateTimeImmutable { return $this->time; }
    public function set(DateTimeImmutable $time): void { $this->time = $time; }
}

final class ActivationCatalog implements DeviceCatalogReader
{
    public function iPhoneModels(): array
    {
        return [new DeviceCatalogItem('iphone-13-pro', 'iPhone 13 Pro'), new DeviceCatalogItem('iphone_xr', 'iPhone XR')];
    }
}

final class AlwaysBusyActivationLock implements PriceBookActivationLock
{
    public function acquire(CurrencyCode $currency, int $timeoutSeconds): void { throw new PriceBookActivationBusyException('QA busy lock.'); }
    public function release(CurrencyCode $currency): void {}
}

final class FailingActivationRepository implements PriceBookRepository
{
    public function __construct(private readonly PriceBookRepository $delegate) {}
    public function createDraft(PriceBook $priceBook): PriceBook { return $this->delegate->createDraft($priceBook); }
    public function getById(PriceBookId $id): ?PriceBook { return $this->delegate->getById($id); }
    public function getByIdForUpdate(PriceBookId $id): ?PriceBook { return $this->delegate->getByIdForUpdate($id); }
    public function getByVersionNumber(PriceBookVersionNumber $number): ?PriceBook { return $this->delegate->getByVersionNumber($number); }
    public function list(int $page, int $perPage, ?PriceBookStatus $status = null): PriceBookPage { return $this->delegate->list($page, $perPage, $status); }
    public function saveDraft(PriceBook $priceBook, AggregateVersion $expectedVersion): void { $this->delegate->saveDraft($priceBook, $expectedVersion); }
    public function saveActivated(PriceBook $priceBook, AggregateVersion $expectedVersion): void { throw new PersistenceException('Forced QA target activation save failure.'); }
    public function saveRetired(PriceBook $priceBook, AggregateVersion $expectedVersion): void { $this->delegate->saveRetired($priceBook, $expectedVersion); }
    public function nextAvailableVersionNumber(): PriceBookVersionNumber { return $this->delegate->nextAvailableVersionNumber(); }
    public function hasActiveBook(): bool { return $this->delegate->hasActiveBook(); }
    public function findCurrentActiveForCurrencyAt(CurrencyCode $currency, DateTimeImmutable $at): array { return $this->delegate->findCurrentActiveForCurrencyAt($currency, $at); }
    public function findCurrentActiveForCurrencyAtForUpdate(CurrencyCode $currency, DateTimeImmutable $at): array { return $this->delegate->findCurrentActiveForCurrencyAtForUpdate($currency, $at); }
    public function countCurrentActiveForCurrencyAt(CurrencyCode $currency, DateTimeImmutable $at): int { return $this->delegate->countCurrentActiveForCurrencyAt($currency, $at); }
}

/** @return array<string,int> */
function activationCounts(wpdb $database, array $tables): array
{
    $counts = [];
    foreach ($tables as $key => $table) {
        $counts[$key] = (int) $database->get_var("SELECT COUNT(*) FROM `{$table}`");
    }
    return $counts;
}

function activationLegacyHash(wpdb $database): string
{
    $rows = $database->get_results($database->prepare(
        "SELECT umeta_id, user_id, meta_key, meta_value FROM {$database->usermeta} WHERE meta_key = %s ORDER BY umeta_id ASC",
        AK_ACTIVATION_LEGACY_META
    ), ARRAY_A);
    return hash('sha256', serialize(is_array($rows) ? $rows : []));
}

function activationRealBookHash(wpdb $database, array $tables, string $marker): string
{
    $books = $database->get_results($database->prepare(
        "SELECT * FROM `{$tables[Schema::PRICE_BOOKS]}` WHERE label NOT LIKE %s ORDER BY id ASC",
        $database->esc_like($marker) . '%'
    ), ARRAY_A);
    $rules = $database->get_results($database->prepare(
        "SELECT r.* FROM `{$tables[Schema::PRICE_RULES]}` r INNER JOIN `{$tables[Schema::PRICE_BOOKS]}` b ON b.id = r.price_book_id WHERE b.label NOT LIKE %s ORDER BY r.id ASC",
        $database->esc_like($marker) . '%'
    ), ARRAY_A);
    return hash('sha256', serialize([$books, $rules]));
}

function activationCleanup(wpdb $database, array $tables, string $marker): void
{
    $ids = $database->get_col($database->prepare(
        "SELECT id FROM `{$tables[Schema::PRICE_BOOKS]}` WHERE label LIKE %s",
        $database->esc_like($marker) . '%'
    ));
    $ids = array_map('intval', is_array($ids) ? $ids : []);
    if ($ids === []) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $database->query($database->prepare("DELETE FROM `{$tables[Schema::PRICE_RULES]}` WHERE price_book_id IN ({$placeholders})", ...$ids));
    $database->query($database->prepare("DELETE FROM `{$tables[Schema::PRICE_BOOKS]}` WHERE id IN ({$placeholders})", ...$ids));
}

/** @return array<string,mixed> */
function activationBookState(PriceBook $book): array
{
    return [
        $book->status()->code(), $book->version()->value(), $book->updatedAt()->format(DATE_ATOM),
        $book->effectiveFrom()?->format(DATE_ATOM), $book->effectiveTo()?->format(DATE_ATOM),
        $book->activatedBy()?->value(), $book->retiredBy()?->value(),
        $book->activatedAt()?->format(DATE_ATOM), $book->retiredAt()?->format(DATE_ATOM),
    ];
}

function activationDraft(int $id = 9001): PriceBook
{
    $at = new DateTimeImmutable('2026-07-16T09:00:00+00:00');
    return PriceBook::reconstitute(
        new PriceBookId($id), new PriceBookVersionNumber($id), 'QA pure', new PriceBookStatus(PriceBookStatus::DRAFT),
        new CurrencyCode('HUF'), new Money(1000, 'HUF'), 1000, new MinimumOfferPolicy(MinimumOfferPolicy::MANUAL_REVIEW),
        new PricingActorId(1), new AggregateVersion(0), $at, $at
    );
}

function activationBaseDefinition(string $code, string $model = 'iphone-13-pro', int $storage = 128, bool $enabled = true, int $priority = 100): PricingRuleDefinition
{
    return new PricingRuleDefinition(
        new PricingRuleCode($code), new AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind(PricingRuleKind::BASE_PRICE),
        'iphone', $model, new StorageCapacity($storage), null, null, null, null,
        new Money(200000, 'HUF'), null, new RulePriority($priority), $enabled, null, 'QA activation base'
    );
}

function activationModeDefinition(string $code, string $mode, int $priority = 50): PricingRuleDefinition
{
    return new PricingRuleDefinition(
        new PricingRuleCode($code), new AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind(PricingRuleKind::MODE_ADJUSTMENT),
        'iphone', null, null, $mode, null, null, null,
        new Money(1000, 'HUF'), null, new RulePriority($priority), true, null, 'QA mode'
    );
}

/** @param array<string,mixed> $overrides */
function activationUnsafeDefinition(array $overrides): PricingRuleDefinition
{
    $values = [
        'code' => new PricingRuleCode('unsafe-' . substr(bin2hex(random_bytes(4)), 0, 8)),
        'kind' => new AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind(PricingRuleKind::BASE_PRICE),
        'category' => 'iphone', 'modelKey' => 'iphone-13-pro', 'storage' => new StorageCapacity(128),
        'serviceMode' => null, 'conditionKey' => null, 'operator' => null, 'comparisonValue' => null,
        'amount' => new Money(1000, 'HUF'), 'multiplier' => null, 'priority' => new RulePriority(100),
        'enabled' => true, 'publicLabel' => null, 'internalNote' => null,
    ];
    $definition = (new ReflectionClass(PricingRuleDefinition::class))->newInstanceWithoutConstructor();
    foreach (array_replace($values, $overrides) as $property => $value) {
        (new ReflectionProperty(PricingRuleDefinition::class, $property))->setValue($definition, $value);
    }
    return $definition;
}

function activationRule(PriceBookId $bookId, PricingRuleDefinition $definition, int $id): PricingRule
{
    $at = new DateTimeImmutable('2026-07-16T09:00:00+00:00');
    return PricingRule::reconstitute(new PricingRuleId($id), $bookId, $definition, new AggregateVersion(0), $at, $at);
}

function activationCreateBook(CreateDraftPriceBookHandler $handler, string $label, int $actorId): PriceBook
{
    return $handler->handle(new CreateDraftPriceBook($label, 1000, 1000, MinimumOfferPolicy::MANUAL_REVIEW, $actorId));
}

function activationAddRule(AddDraftPricingRuleHandler $handler, WordPressPriceBookRepository $books, PriceBook $book, PricingRuleDefinition $definition): PricingRule
{
    $current = $books->getById($book->id());
    if ($current === null) {
        throw new RuntimeException('QA price book disappeared.');
    }
    return $handler->handle(new AddDraftPricingRule($current->id()->toInt(), $current->version()->value(), $definition));
}

/** @return array{0:int,1:bool} */
function activationUser(string $role, string $token): array
{
    $ids = get_users(['role' => $role, 'number' => 1, 'fields' => 'ID']);
    if (is_array($ids) && $ids !== []) {
        return [(int) $ids[0], false];
    }
    $id = wp_insert_user([
        'user_login' => 'qa-activation-' . $role . '-' . strtolower($token),
        'user_pass' => wp_generate_password(24, true, true),
        'user_email' => 'qa-activation-' . $role . '-' . strtolower($token) . '@example.invalid',
        'role' => $role,
    ]);
    if (is_wp_error($id)) {
        throw new RuntimeException($id->get_error_message());
    }
    return [(int) $id, true];
}

global $wpdb;
$test = new ActivationTestRunner();
$tables = Schema::tableNames($wpdb);
$token = gmdate('mdHis') . substr(bin2hex(random_bytes(3)), 0, 6);
$marker = 'QA-ACTIVATION-' . $token;
activationCleanup($wpdb, $tables, $marker);
$before = activationCounts($wpdb, $tables);
$activeBefore = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$tables[Schema::PRICE_BOOKS]}` WHERE status = %s AND currency = %s", PriceBookStatus::ACTIVE, 'HUF'));
$legacyBefore = activationLegacyHash($wpdb);
$realBookHashBefore = activationRealBookHash($wpdb, $tables, $marker);
$originalUser = get_current_user_id();
$createdUsers = [];
$secondDatabase = null;

$clock = new ActivationFixedClock(new DateTimeImmutable('2026-07-16T12:00:00+00:00'));
$transactions = new WordPressTransactionManager($wpdb);
$books = new WordPressPriceBookRepository($wpdb, $transactions);
$rules = new WordPressPricingRuleRepository($wpdb);
$catalog = new ActivationCatalog();
$readiness = new PriceBookActivationReadinessService($catalog, new PriceBookActivationReadinessEvaluator());
$resolver = new RepositoryActivePriceBookResolver($books, $rules);
$realLock = new MySqlPriceBookActivationLock($wpdb);
$create = new CreateDraftPriceBookHandler($books, $transactions, $clock);
$add = new AddDraftPricingRuleHandler($books, $rules, $transactions, $clock);
$updateRuleHandler = new UpdateDraftPricingRuleHandler($books, $rules, $transactions, $clock);
$toggleRuleHandler = new ToggleDraftPricingRuleHandler($books, $rules, $transactions, $clock);
$deleteRuleHandler = new DeleteDraftPricingRuleHandler($books, $rules, $transactions, $clock);
$activate = new ActivateDraftPriceBookHandler($books, $rules, $readiness, $realLock, $transactions, $clock);

try {
    $test->assert(APPLEKLINIKA_BUYBACK_VERSION === '0.7.0', 'Plugin code version is 0.7.0');
    $test->assert(APPLEKLINIKA_BUYBACK_SCHEMA_VERSION === '1.1.0', 'Code schema remains 1.1.0');
    $test->assert((string) get_option(Schema::OPTION_SCHEMA_VERSION) === '1.1.0', 'Installed schema remains 1.1.0');
    $test->assert(is_plugin_active('appleklinika-buyback/appleklinika-buyback.php'), 'Buyback plugin is active');
    $test->assert($activeBefore === 0, 'Baseline contains no active HUF price book');

    $actor = new PricingActorId(1);
    $draft = PriceBook::createDraft(new PriceBookVersionNumber(9001), 'QA lifecycle', new CurrencyCode('HUF'), new Money(1000, 'HUF'), 1000, new MinimumOfferPolicy(MinimumOfferPolicy::MANUAL_REVIEW), $actor, new DateTimeImmutable('2026-07-16T09:00:00+00:00'));
    $draftCannotRetire = activationBookState($draft);
    $test->throws(fn () => $draft->retire($actor, new DateTimeImmutable('2026-07-16T10:00:00+00:00')), InvalidAggregateOperationException::class, 'Draft cannot retire directly');
    $test->assert(activationBookState($draft) === $draftCannotRetire, 'Failed draft retirement changes no lifecycle state');
    $draft->activate($actor, new DateTimeImmutable('2026-07-16T10:00:00+00:00'));
    $test->assert($draft->status()->isActive() && $draft->version()->value() === 1, 'Draft activates and increments version exactly once');
    $test->assert($draft->activatedBy()?->value() === 1 && $draft->effectiveFrom()?->format('H:i') === '10:00', 'Activation stores actor and effective timestamp');
    $activeState = activationBookState($draft);
    $test->throws(fn () => $draft->activate($actor, new DateTimeImmutable('2026-07-16T10:30:00+00:00')), InvalidAggregateOperationException::class, 'Active book cannot activate again');
    $test->assert(activationBookState($draft) === $activeState, 'Failed repeated activation changes no lifecycle state');
    $draft->retire($actor, new DateTimeImmutable('2026-07-16T11:00:00+00:00'));
    $test->assert($draft->status()->isRetired() && $draft->version()->value() === 2, 'Active book retires and increments version exactly once');
    $test->assert($draft->retiredBy()?->value() === 1 && $draft->effectiveTo()?->format('H:i') === '11:00', 'Retirement stores actor and effective-to timestamp');
    $retiredState = activationBookState($draft);
    $test->throws(fn () => $draft->activate($actor, new DateTimeImmutable('2026-07-16T12:00:00+00:00')), InvalidAggregateOperationException::class, 'Retired book cannot reactivate');
    $test->throws(fn () => $draft->updateSettings('No', new Money(1000, 'HUF'), 1000, new MinimumOfferPolicy(MinimumOfferPolicy::REJECT), new DateTimeImmutable('2026-07-16T12:00:00+00:00')), InvalidAggregateOperationException::class, 'Retired book settings are immutable');
    $test->assert(activationBookState($draft) === $retiredState, 'Invalid retired mutations preserve lifecycle state');

    $pureBook = activationDraft();
    $empty = (new PriceBookActivationReadinessEvaluator())->evaluate($pureBook, [], ['iphone-13-pro'], $clock->now());
    $test->assert(! $empty->ready && in_array('missing_base_price', $empty->blockingIssues, true), 'Empty book fails with missing_base_price');
    $validBase = activationRule($pureBook->id(), activationBaseDefinition('pure-base'), 1);
    $ready = (new PriceBookActivationReadinessEvaluator())->evaluate($pureBook, [$validBase], ['iphone-13-pro'], $clock->now());
    $test->assert($ready->ready && $ready->enabledBasePriceCount === 1 && $ready->supportedConfigurationCount() === 1, 'One valid iPhone base price is activation-ready');
    $test->assert(count(array_filter($ready->warnings, static fn (string $warning): bool => str_starts_with($warning, 'missing_mode_adjustment_'))) === 4, 'Missing mode adjustments are neutral warnings');
    $unknown = activationRule($pureBook->id(), activationBaseDefinition('unknown-base', 'iphone-unknown'), 2);
    $unknownReport = (new PriceBookActivationReadinessEvaluator())->evaluate($pureBook, [$unknown], ['iphone-13-pro'], $clock->now());
    $test->assert(! $unknownReport->ready && in_array('unknown_model_key', $unknownReport->blockingIssues, true), 'Unknown model blocks readiness');
    $nonIphone = activationRule($pureBook->id(), activationUnsafeDefinition(['category' => 'macbook']), 3);
    $nonIphoneReport = (new PriceBookActivationReadinessEvaluator())->evaluate($pureBook, [$nonIphone], ['iphone-13-pro'], $clock->now());
    $test->assert(! $nonIphoneReport->ready && in_array('unsupported_category', $nonIphoneReport->blockingIssues, true), 'Non-iPhone base rule blocks readiness');
    $badStorage = activationRule($pureBook->id(), activationBaseDefinition('bad-storage', 'iphone-13-pro', 16), 4);
    $badStorageReport = (new PriceBookActivationReadinessEvaluator())->evaluate($pureBook, [$badStorage], ['iphone-13-pro'], $clock->now());
    $test->assert(! $badStorageReport->ready && in_array('invalid_storage', $badStorageReport->blockingIssues, true), 'Unsupported storage blocks readiness');
    $malformed = activationRule($pureBook->id(), activationUnsafeDefinition(['amount' => null]), 5);
    $malformedReport = (new PriceBookActivationReadinessEvaluator())->evaluate($pureBook, [$malformed], ['iphone-13-pro'], $clock->now());
    $test->assert(! $malformedReport->ready && in_array('invalid_rule_shape', $malformedReport->blockingIssues, true), 'Malformed enabled rule blocks readiness');
    $unknownCondition = activationRule($pureBook->id(), activationUnsafeDefinition([
        'kind' => new AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind(PricingRuleKind::FIXED_DEDUCTION),
        'modelKey' => null, 'storage' => null, 'conditionKey' => 'unknown_condition',
        'operator' => new ComparisonOperator(ComparisonOperator::EQUALS), 'comparisonValue' => true,
    ]), 6);
    $unknownConditionReport = (new PriceBookActivationReadinessEvaluator())->evaluate($pureBook, [$validBase, $unknownCondition], ['iphone-13-pro'], $clock->now());
    $test->assert(! $unknownConditionReport->ready && in_array('unknown_condition_key', $unknownConditionReport->blockingIssues, true), 'Unknown condition key blocks readiness');
    $duplicate = activationRule($pureBook->id(), activationBaseDefinition('pure-base-duplicate'), 7);
    $duplicateReport = (new PriceBookActivationReadinessEvaluator())->evaluate($pureBook, [$validBase, $duplicate], ['iphone-13-pro'], $clock->now());
    $test->assert(! $duplicateReport->ready && in_array('duplicate_base_price', $duplicateReport->blockingIssues, true), 'Duplicate base configuration blocks readiness');
    $modeA = activationRule($pureBook->id(), activationModeDefinition('mode-a', 'fast_online'), 8);
    $modeB = activationRule($pureBook->id(), activationModeDefinition('mode-b', 'fast_online'), 9);
    $duplicateMode = (new PriceBookActivationReadinessEvaluator())->evaluate($pureBook, [$validBase, $modeA, $modeB], ['iphone-13-pro'], $clock->now());
    $test->assert(! $duplicateMode->ready && in_array('duplicate_mode_adjustment', $duplicateMode->blockingIssues, true), 'Duplicate mode adjustment blocks readiness');
    $disabledMalformed = activationRule($pureBook->id(), activationUnsafeDefinition(['amount' => null, 'enabled' => false]), 10);
    $disabledReport = (new PriceBookActivationReadinessEvaluator())->evaluate($pureBook, [$validBase, $disabledMalformed], ['iphone-13-pro'], $clock->now());
    $test->assert($disabledReport->ready, 'Disabled malformed rule is documented as non-blocking');

    $realDraftBefore = $wpdb->get_row("SELECT * FROM `{$tables[Schema::PRICE_BOOKS]}` WHERE id = 31", ARRAY_A);
    $realDraftRulesBefore = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$tables[Schema::PRICE_RULES]}` WHERE price_book_id = 31");
    $realDraft = $books->getById(new PriceBookId(31));
    $realReport = $readiness->evaluate($realDraft, $rules->listForPriceBook(new PriceBookId(31)), $clock->now());
    $test->assert(! $realReport->ready && in_array('missing_base_price', $realReport->blockingIssues, true), 'Real draft ID 31 is explicitly not ready');

    [$adminId, $adminCreated] = activationUser('administrator', $token); if ($adminCreated) { $createdUsers[] = $adminId; }
    [$managerId, $managerCreated] = activationUser('shop_manager', $token); if ($managerCreated) { $createdUsers[] = $managerId; }
    [$customerId, $customerCreated] = activationUser('customer', $token); if ($customerCreated) { $createdUsers[] = $customerId; }
    [$subscriberId, $subscriberCreated] = activationUser('subscriber', $token); if ($subscriberCreated) { $createdUsers[] = $subscriberId; }
    (new CapabilityManager())->grant();
    $authorization = new AdminAuthorization();
    wp_set_current_user($adminId); $authorization->assert(CapabilityManager::MANAGE_PRICE_BOOKS, wp_create_nonce(AdminAuthorization::NONCE_ACTION)); $test->assert(true, 'Administrator activation authorization succeeds');
    wp_set_current_user($managerId); $authorization->assert(CapabilityManager::MANAGE_PRICE_BOOKS, wp_create_nonce(AdminAuthorization::NONCE_ACTION)); $test->assert(true, 'Shop manager activation authorization succeeds');
    wp_set_current_user($customerId); $test->throws(fn () => $authorization->assert(CapabilityManager::MANAGE_PRICE_BOOKS, wp_create_nonce(AdminAuthorization::NONCE_ACTION)), RuntimeException::class, 'Customer activation authorization is denied');
    wp_set_current_user($subscriberId); $test->throws(fn () => $authorization->assert(CapabilityManager::MANAGE_PRICE_BOOKS, wp_create_nonce(AdminAuthorization::NONCE_ACTION)), RuntimeException::class, 'Subscriber activation authorization is denied');
    wp_set_current_user($adminId); $test->throws(fn () => $authorization->assert(CapabilityManager::MANAGE_PRICE_BOOKS, ''), RuntimeException::class, 'Missing nonce is rejected');
    $test->throws(fn () => $authorization->assert(CapabilityManager::MANAGE_PRICE_BOOKS, 'invalid'), RuntimeException::class, 'Invalid nonce is rejected');

    $first = activationCreateBook($create, $marker . '-FIRST', $adminId);
    activationAddRule($add, $books, $first, activationBaseDefinition($marker . '-base-first'));
    $first = $books->getById($first->id());
    $test->throws(fn () => $activate->handle(new ActivateDraftPriceBook($first->id()->toInt(), $first->version()->value(), $adminId, 'ROSSZ')), InvalidActivationConfirmationException::class, 'Wrong confirmation changes nothing');
    $test->assert($books->getById($first->id())->status()->isDraft(), 'Wrong confirmation leaves target draft');
    $busyHandler = new ActivateDraftPriceBookHandler($books, $rules, $readiness, new AlwaysBusyActivationLock(), $transactions, $clock);
    $test->throws(fn () => $busyHandler->handle(new ActivateDraftPriceBook($first->id()->toInt(), $first->version()->value(), $adminId, ActivateDraftPriceBook::CONFIRMATION)), PriceBookActivationBusyException::class, 'Lock acquisition failure is typed');
    $test->assert($books->getById($first->id())->status()->isDraft(), 'Lock acquisition failure changes nothing');
    $test->throws(fn () => $activate->handle(new ActivateDraftPriceBook($first->id()->toInt(), $first->version()->value() + 1, $adminId, ActivateDraftPriceBook::CONFIRMATION)), StaleAggregateVersionException::class, 'Stale expected version is rejected');
    $test->assert($books->getById($first->id())->status()->isDraft(), 'Stale version leaves target draft');
    $firstVersion = $first->version()->value();
    $activate->handle(new ActivateDraftPriceBook($first->id()->toInt(), $firstVersion, $adminId, ActivateDraftPriceBook::CONFIRMATION));
    $firstActive = $books->getById($first->id());
    $test->assert($firstActive->status()->isActive(), 'Activation with no previous active makes target active');
    $test->assert($firstActive->activatedBy()?->value() === $adminId && $firstActive->activatedAt()?->format(DATE_ATOM) === $clock->now()->format(DATE_ATOM), 'Activation actor and timestamp persist');
    $test->assert($books->countCurrentActiveForCurrencyAt(new CurrencyCode('HUF'), $clock->now()) === 1, 'Exactly one current active remains');
    $test->throws(fn () => $activate->handle(new ActivateDraftPriceBook($first->id()->toInt(), $firstVersion, $adminId, ActivateDraftPriceBook::CONFIRMATION)), StaleAggregateVersionException::class, 'POST replay cannot activate twice');
    $test->throws(fn () => $firstActive->updateSettings('No', new Money(1, 'HUF'), 1, new MinimumOfferPolicy(MinimumOfferPolicy::REJECT), $clock->now()), InvalidAggregateOperationException::class, 'Active settings are immutable');
    $test->throws(fn () => activationAddRule($add, $books, $firstActive, activationBaseDefinition($marker . '-active-forbidden')), InvalidAggregateOperationException::class, 'Active rules cannot be added');
    $firstActiveRule = $rules->listForPriceBook($first->id())[0];
    $test->throws(fn () => $updateRuleHandler->handle(new AppleKlinika\Buyback\Application\Command\UpdateDraftPricingRule($first->id()->toInt(), $firstActive->version()->value(), $firstActiveRule->id()->toInt(), $firstActiveRule->version()->value(), activationBaseDefinition($firstActiveRule->definition()->code->code(), 'iphone-13-pro', 128, true, 90))), InvalidAggregateOperationException::class, 'Active rules cannot be updated');
    $test->throws(fn () => $toggleRuleHandler->handle(new AppleKlinika\Buyback\Application\Command\ToggleDraftPricingRule($first->id()->toInt(), $firstActive->version()->value(), $firstActiveRule->id()->toInt(), $firstActiveRule->version()->value(), false)), InvalidAggregateOperationException::class, 'Active rules cannot be toggled');
    $test->throws(fn () => $deleteRuleHandler->handle(new AppleKlinika\Buyback\Application\Command\DeleteDraftPricingRule($first->id()->toInt(), $firstActive->version()->value(), $firstActiveRule->id()->toInt(), $firstActiveRule->version()->value())), InvalidAggregateOperationException::class, 'Active rules cannot be deleted');

    $resolvedFirst = $resolver->resolveForCurrencyAt(new CurrencyCode('HUF'), $clock->now());
    $test->assert($resolvedFirst->priceBook->id()->equals($first->id()) && count($resolvedFirst->supportedConfigurations) === 1, 'Resolver returns the one active book and supported configuration');
    $test->throws(fn () => $resolver->resolveForCurrencyAt(new CurrencyCode('HUF'), new DateTimeImmutable('2026-07-16T11:00:00+00:00')), NoActivePriceBookException::class, 'Active book outside effective range does not resolve');

    $clock->set(new DateTimeImmutable('2026-07-16T13:00:00+00:00'));
    $second = activationCreateBook($create, $marker . '-SECOND', $managerId);
    activationAddRule($add, $books, $second, activationBaseDefinition($marker . '-base-second', 'iphone-13-pro', 128, true, 100));
    activationAddRule($add, $books, $second, activationModeDefinition($marker . '-mode-second', 'fast_online', 20));
    $second = $books->getById($second->id());
    $activate->handle(new ActivateDraftPriceBook($second->id()->toInt(), $second->version()->value(), $managerId, ActivateDraftPriceBook::CONFIRMATION));
    $firstRetired = $books->getById($first->id());
    $secondActive = $books->getById($second->id());
    $test->assert($firstRetired->status()->isRetired() && $secondActive->status()->isActive(), 'Replacement retires previous and activates target atomically');
    $test->assert($firstRetired->retiredAt()?->format(DATE_ATOM) === $secondActive->activatedAt()?->format(DATE_ATOM), 'Retirement and activation share one UTC timestamp');
    $test->assert($firstRetired->retiredBy()?->value() === $managerId && $secondActive->activatedBy()?->value() === $managerId, 'Replacement persists the same actor');
    $test->throws(fn () => $firstRetired->updateSettings('No', new Money(1, 'HUF'), 1, new MinimumOfferPolicy(MinimumOfferPolicy::REJECT), $clock->now()), InvalidAggregateOperationException::class, 'Retired settings are immutable');
    $test->throws(fn () => activationAddRule($add, $books, $firstRetired, activationBaseDefinition($marker . '-retired-forbidden')), InvalidAggregateOperationException::class, 'Retired rules cannot be added');
    $test->throws(fn () => $updateRuleHandler->handle(new AppleKlinika\Buyback\Application\Command\UpdateDraftPricingRule($first->id()->toInt(), $firstRetired->version()->value(), $firstActiveRule->id()->toInt(), $firstActiveRule->version()->value(), activationBaseDefinition($firstActiveRule->definition()->code->code()))), InvalidAggregateOperationException::class, 'Retired rules cannot be updated');
    $test->throws(fn () => $toggleRuleHandler->handle(new AppleKlinika\Buyback\Application\Command\ToggleDraftPricingRule($first->id()->toInt(), $firstRetired->version()->value(), $firstActiveRule->id()->toInt(), $firstActiveRule->version()->value(), false)), InvalidAggregateOperationException::class, 'Retired rules cannot be toggled');
    $test->throws(fn () => $deleteRuleHandler->handle(new AppleKlinika\Buyback\Application\Command\DeleteDraftPricingRule($first->id()->toInt(), $firstRetired->version()->value(), $firstActiveRule->id()->toInt(), $firstActiveRule->version()->value())), InvalidAggregateOperationException::class, 'Retired rules cannot be deleted');
    $resolvedSecond = $resolver->resolveForCurrencyAt(new CurrencyCode('HUF'), $clock->now());
    $test->assert($resolvedSecond->priceBook->id()->equals($second->id()), 'Resolver returns replacement active book');
    $test->assert($resolvedSecond->enabledRules[0]->definition()->priority->value() === 20 && $resolvedSecond->enabledRules[1]->definition()->priority->value() === 100, 'Resolved active rules have deterministic repository order');

    $clock->set(new DateTimeImmutable('2026-07-16T14:00:00+00:00'));
    $unready = activationCreateBook($create, $marker . '-UNREADY', $adminId);
    $unready = $books->getById($unready->id());
    $test->throws(fn () => $activate->handle(new ActivateDraftPriceBook($unready->id()->toInt(), $unready->version()->value(), $adminId, ActivateDraftPriceBook::CONFIRMATION)), PriceBookNotReadyForActivationException::class, 'Unready target is rejected server-side');
    $test->assert($books->getById($unready->id())->status()->isDraft() && $books->getById($second->id())->status()->isActive(), 'Unready activation changes neither target nor previous active');

    $rollback = activationCreateBook($create, $marker . '-ROLLBACK', $adminId);
    activationAddRule($add, $books, $rollback, activationBaseDefinition($marker . '-base-rollback'));
    $rollback = $books->getById($rollback->id());
    $failingRepository = new FailingActivationRepository($books);
    $failingHandler = new ActivateDraftPriceBookHandler($failingRepository, $rules, $readiness, $realLock, $transactions, $clock);
    $test->throws(fn () => $failingHandler->handle(new ActivateDraftPriceBook($rollback->id()->toInt(), $rollback->version()->value(), $adminId, ActivateDraftPriceBook::CONFIRMATION)), PersistenceException::class, 'Forced target save failure rolls back transaction');
    $test->assert($books->getById($second->id())->status()->isActive(), 'Previous active remains active after rollback');
    $test->assert($books->getById($rollback->id())->status()->isDraft(), 'Target remains draft after rollback');

    $page = new PriceBooksPage(
        $books, $rules, $catalog, $create, new UpdateDraftPriceBookSettingsHandler($books, $clock), $add,
        $updateRuleHandler, $toggleRuleHandler, $deleteRuleHandler,
        new PricingRuleFormParser(), new PreviewDraftPriceBookCalculationHandler($books, $rules, $catalog, new PricingEngine()),
        new PreviewCalculationFormParser(), $readiness, $activate, $resolver, $clock, $authorization, new AdminSubmissionGuard()
    );
    $_GET = ['page' => PriceBooksPage::SLUG, 'book_id' => 31];
    ob_start(); $page->render(); $realDraftOutput = (string) ob_get_clean();
    $test->assert(str_contains($realDraftOutput, 'missing_base_price') && str_contains($realDraftOutput, 'Az árkönyv nem tartalmaz aktív alapárat.'), 'Admin readiness shows safe missing-base issue');
    $test->assert(! str_contains($realDraftOutput, 'Árkönyv aktiválása'), 'Unready real draft renders no activation submit');
    $test->assert(str_contains($realDraftOutput, 'Jelenleg nincs aktív HUF árkönyv') === false, 'List-level no-active notice is replaced while QA active exists');

    $_GET = ['page' => PriceBooksPage::SLUG, 'book_id' => $rollback->id()->toInt()];
    ob_start(); $page->render(); $readyOutput = (string) ob_get_clean();
    $test->assert(str_contains($readyOutput, 'Aktiválásra kész') && str_contains($readyOutput, 'AKTIVÁLOM') && str_contains($readyOutput, 'Árkönyv aktiválása'), 'Ready draft renders controlled activation form');
    $dispatch = new ReflectionMethod(PriceBooksPage::class, 'dispatch'); $dispatch->setAccessible(true);
    $test->throws(fn () => $dispatch->invoke($page, 'activate_price_book', ['price_book_id' => 31, 'expected_book_version' => (int) $realDraftBefore['version'], 'activation_confirmation' => ActivateDraftPriceBook::CONFIRMATION]), PriceBookNotReadyForActivationException::class, 'Forged activation POST is rejected by server readiness');

    $diagnosticsHandler = new GetDiagnosticsHandler(new SchemaInspector($wpdb, APPLEKLINIKA_BUYBACK_SCHEMA_VERSION), new WordPressEnvironmentDiagnosticsReader(), new LegacyBuybackDetector($wpdb), APPLEKLINIKA_BUYBACK_VERSION, APPLEKLINIKA_BUYBACK_SCHEMA_VERSION, $resolver, $clock);
    ob_start(); (new DiagnosticsPage($diagnosticsHandler))->render(); $diagnosticsOutput = (string) ob_get_clean();
    $test->assert(str_contains($diagnosticsOutput, 'Aktív HUF árkönyv') && str_contains($diagnosticsOutput, $secondActive->label()), 'Diagnostics renders current active book safely');

    $secondDatabase = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
    $firstLock = new MySqlPriceBookActivationLock($wpdb);
    $secondLock = new MySqlPriceBookActivationLock($secondDatabase);
    $lockCurrency = new CurrencyCode('HUF');
    $firstLock->acquire($lockCurrency, 0);
    $test->throws(fn () => $secondLock->acquire($lockCurrency, 0), PriceBookActivationBusyException::class, 'Concurrent advisory-lock acquisition fails without waiting indefinitely');
    $firstLock->release($lockCurrency);
    $secondLock->acquire($lockCurrency, 0); $test->assert(true, 'Released advisory lock can be acquired later'); $secondLock->release($lockCurrency);

    $clock->set(new DateTimeImmutable('2026-07-16T15:00:00+00:00'));
    $corrupt = activationCreateBook($create, $marker . '-CORRUPT', $adminId);
    activationAddRule($add, $books, $corrupt, activationBaseDefinition($marker . '-base-corrupt'));
    $corrupt = $books->getById($corrupt->id());
    $transactions->transactional(function () use ($books, $corrupt, $adminId, $clock): void {
        $locked = $books->getByIdForUpdate($corrupt->id());
        $expected = $locked->version();
        $locked->activate(new PricingActorId($adminId), $clock->now());
        $books->saveActivated($locked, $expected);
    });
    $test->throws(fn () => $resolver->resolveForCurrencyAt(new CurrencyCode('HUF'), $clock->now()), MultipleActivePriceBooksException::class, 'Multiple active books produce typed corruption failure');
    $test->assert($books->countCurrentActiveForCurrencyAt(new CurrencyCode('HUF'), $clock->now()) === 2, 'Corruption fixture contains two current active books before cleanup');

    $test->assert($wpdb->get_row("SELECT * FROM `{$tables[Schema::PRICE_BOOKS]}` WHERE id = 31", ARRAY_A) === $realDraftBefore, 'Real draft ID 31 remains byte/value equivalent');
    $test->assert((int) $wpdb->get_var("SELECT COUNT(*) FROM `{$tables[Schema::PRICE_RULES]}` WHERE price_book_id = 31") === $realDraftRulesBefore, 'Real draft ID 31 still has zero rules');
} catch (Throwable $exception) {
    $test->fail($exception);
} finally {
    try { (new MySqlPriceBookActivationLock($wpdb))->release(new CurrencyCode('HUF')); } catch (Throwable $ignored) {}
    activationCleanup($wpdb, $tables, $marker);
    foreach ($createdUsers as $userId) { wp_delete_user($userId); }
    wp_set_current_user($originalUser);
    $_GET = [];
    $_POST = [];
    if ($secondDatabase instanceof wpdb) { $secondDatabase->close(); }
}

$after = activationCounts($wpdb, $tables);
$activeAfter = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$tables[Schema::PRICE_BOOKS]}` WHERE status = %s AND currency = %s", PriceBookStatus::ACTIVE, 'HUF'));
$legacyAfter = activationLegacyHash($wpdb);
$lockFree = (int) $wpdb->get_var($wpdb->prepare('SELECT IS_FREE_LOCK(%s)', 'appleklinika_buyback_pricebook_activation_HUF')) === 1;
$test->assert($before === $after, 'All QA price-book and rule rows are removed');
$test->assert($activeBefore === $activeAfter, 'Original active-HUF count is restored');
$test->assert($legacyBefore === $legacyAfter, 'Legacy hash remains unchanged');
$test->assert($realBookHashBefore === activationRealBookHash($wpdb, $tables, $marker), 'All pre-existing price books and rules are restored exactly');
$test->assert($lockFree, 'No advisory activation lock remains held');
$test->assert(is_plugin_active('appleklinika-buyback/appleklinika-buyback.php'), 'Plugin remains active after activation suite');
$test->assert(! str_contains(implode("\n", array_keys($_GET)), $marker), 'No QA request state remains');
$test->finish($before, $after, $activeBefore, $activeAfter, $legacyBefore, $legacyAfter, $marker, $lockFree);
