<?php

declare(strict_types=1);

$wordpressRoot = dirname(__DIR__, 4);
require_once $wordpressRoot . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

use AppleKlinika\Buyback\Application\Command\CreateDraftBuybackRequest;
use AppleKlinika\Buyback\Application\Command\NewBuybackRequest;
use AppleKlinika\Buyback\Application\Command\TransitionBuybackRequest;
use AppleKlinika\Buyback\Application\Exception\BuybackRequestNotFoundException;
use AppleKlinika\Buyback\Application\Exception\DuplicateBuybackRequestException;
use AppleKlinika\Buyback\Application\Exception\RequestNumberGenerationException;
use AppleKlinika\Buyback\Application\Handler\CreateDraftBuybackRequestHandler;
use AppleKlinika\Buyback\Application\Handler\TransitionBuybackRequestHandler;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\DomainEventPublisher;
use AppleKlinika\Buyback\Application\Port\RequestNumberGenerator;
use AppleKlinika\Buyback\Application\Query\PageRequest;
use AppleKlinika\Buyback\Domain\Buyback\BuybackRequest;
use AppleKlinika\Buyback\Domain\Buyback\BuybackRequestId;
use AppleKlinika\Buyback\Domain\Buyback\BuybackStatus;
use AppleKlinika\Buyback\Domain\Buyback\CustomerId;
use AppleKlinika\Buyback\Domain\Buyback\DeviceCategory;
use AppleKlinika\Buyback\Domain\Buyback\DeviceDisplayName;
use AppleKlinika\Buyback\Domain\Buyback\Event\BuybackStatusChanged;
use AppleKlinika\Buyback\Domain\Buyback\ModelKey;
use AppleKlinika\Buyback\Domain\Buyback\RequestNumber;
use AppleKlinika\Buyback\Domain\Buyback\RequestSource;
use AppleKlinika\Buyback\Domain\Buyback\ServiceMode;
use AppleKlinika\Buyback\Domain\Buyback\StatusTransitionPolicy;
use AppleKlinika\Buyback\Domain\Buyback\TransitionContext;
use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;
use AppleKlinika\Buyback\Domain\Exception\StaleAggregateVersionException;
use AppleKlinika\Buyback\Domain\Shared\ActorType;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Domain\Shared\DomainEvent;
use AppleKlinika\Buyback\Infrastructure\Identifier\WordPressRequestNumberGenerator;
use AppleKlinika\Buyback\Infrastructure\Persistence\Exception\PersistenceException;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\Schema;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressBuybackRequestMapper;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressBuybackRequestRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressDomainEventStore;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressTransactionManager;
use AppleKlinika\Buyback\Infrastructure\Time\SystemClock;

const AK_BUYBACK_PERSISTENCE_PLUGIN = 'appleklinika-buyback/appleklinika-buyback.php';
const AK_BUYBACK_PERSISTENCE_LEGACY_META_KEY = 'appleklinika_buyback_records';

final class PersistenceTestRunner
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
                $this->failures[] = sprintf(
                    '%s (expected %s, received %s: %s)',
                    $message,
                    $expected,
                    $exception::class,
                    $exception->getMessage()
                );
            }

            return $exception;
        }

        return null;
    }

    public function fail(Throwable $exception): void
    {
        $this->failures[] = sprintf('%s: %s', $exception::class, $exception->getMessage());
    }

    /** @param array<string, int> $before @param array<string, int> $after */
    public function finish(array $before, array $after, string $marker): never
    {
        if ($this->failures !== []) {
            foreach ($this->failures as $failure) {
                fwrite(STDERR, "FAIL: {$failure}\n");
            }

            fwrite(STDERR, sprintf(
                "%d assertion(s), %d failure(s); marker: %s.\n",
                $this->assertions,
                count($this->failures),
                $marker
            ));
            exit(1);
        }

        echo sprintf(
            "Buyback persistence integration tests passed: %d assertions; marker %s; rows before/after requests %d/%d, snapshots %d/%d, events %d/%d.\n",
            $this->assertions,
            $marker,
            $before[Schema::REQUESTS],
            $after[Schema::REQUESTS],
            $before[Schema::SNAPSHOTS],
            $after[Schema::SNAPSHOTS],
            $before[Schema::EVENTS],
            $after[Schema::EVENTS]
        );
        exit(0);
    }
}

final class FixedClock implements Clock
{
    public function __construct(private readonly DateTimeImmutable $time)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }
}

final class FailingDomainEventPublisher implements DomainEventPublisher
{
    public function publish(DomainEvent ...$events): void
    {
        throw new RuntimeException('Controlled event persistence failure.');
    }
}

final class FixedRequestNumberGenerator implements RequestNumberGenerator
{
    public function __construct(private readonly RequestNumber $requestNumber)
    {
    }

    public function generate(): RequestNumber
    {
        return $this->requestNumber;
    }
}

/** @return array<string, int> */
function persistenceRowCounts(wpdb $database, array $tables): array
{
    return [
        Schema::REQUESTS => (int) $database->get_var("SELECT COUNT(*) FROM `{$tables[Schema::REQUESTS]}`"),
        Schema::SNAPSHOTS => (int) $database->get_var("SELECT COUNT(*) FROM `{$tables[Schema::SNAPSHOTS]}`"),
        Schema::EVENTS => (int) $database->get_var("SELECT COUNT(*) FROM `{$tables[Schema::EVENTS]}`"),
    ];
}

function persistenceLegacyHash(wpdb $database): string
{
    $rows = $database->get_results(
        $database->prepare(
            "SELECT umeta_id, user_id, meta_key, meta_value
             FROM {$database->usermeta}
             WHERE meta_key = %s
             ORDER BY umeta_id ASC",
            AK_BUYBACK_PERSISTENCE_LEGACY_META_KEY
        ),
        ARRAY_A
    );

    return hash('sha256', serialize(is_array($rows) ? $rows : []));
}

/** @return list<int> */
function persistenceMarkerIds(wpdb $database, string $requestsTable, string $marker): array
{
    $ids = $database->get_col(
        $database->prepare(
            "SELECT id FROM `{$requestsTable}` WHERE demo_marker = %s ORDER BY id ASC",
            $marker
        )
    );

    return array_map('intval', is_array($ids) ? $ids : []);
}

function cleanupPersistenceFixtures(wpdb $database, array $tables, string $marker): void
{
    $ids = persistenceMarkerIds($database, $tables[Schema::REQUESTS], $marker);

    if ($ids === []) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $database->query($database->prepare(
        "DELETE FROM `{$tables[Schema::EVENTS]}` WHERE request_id IN ({$placeholders})",
        ...$ids
    ));
    $database->query($database->prepare(
        "DELETE FROM `{$tables[Schema::SNAPSHOTS]}` WHERE request_id IN ({$placeholders})",
        ...$ids
    ));
    $database->query($database->prepare(
        "DELETE FROM `{$tables[Schema::REQUESTS]}` WHERE id IN ({$placeholders})",
        ...$ids
    ));
}

function qaRequestNumber(string $runToken, string $suffix): RequestNumber
{
    return new RequestNumber(sprintf('QAB-%s-%s', $runToken, $suffix));
}

function qaDraft(
    RequestNumber $number,
    DateTimeImmutable $createdAt,
    string $marker,
    ?CustomerId $customerId = null
): NewBuybackRequest {
    return new NewBuybackRequest(
        $number,
        $customerId,
        new DeviceCategory(DeviceCategory::IPHONE),
        new ModelKey('iphone-13-pro'),
        new DeviceDisplayName('iPhone 13 Pro 128 GB'),
        new ServiceMode(ServiceMode::FAST_ONLINE),
        null,
        new RequestSource(RequestSource::QA_FIXTURE),
        null,
        $createdAt,
        $marker
    );
}

function customerTransitionContext(DateTimeImmutable $time, string $correlation): TransitionContext
{
    return new TransitionContext(
        new ActorType(ActorType::CUSTOMER),
        $time,
        null,
        false,
        false,
        false,
        false,
        $correlation
    );
}

function staffTransitionContext(DateTimeImmutable $time, string $correlation): TransitionContext
{
    return new TransitionContext(
        new ActorType(ActorType::STAFF),
        $time,
        null,
        false,
        false,
        false,
        false,
        $correlation
    );
}

global $wpdb;

$test = new PersistenceTestRunner();
$tables = Schema::tableNames($wpdb);
$runToken = gmdate('mdHis') . substr(bin2hex(random_bytes(3)), 0, 6);
$marker = 'qa-b1b1-' . $runToken;
$countsBefore = persistenceRowCounts($wpdb, $tables);
$legacyHashBefore = persistenceLegacyHash($wpdb);
$schemaVersionBefore = get_option(Schema::OPTION_SCHEMA_VERSION);
$pluginVersionBefore = get_option(Schema::OPTION_PLUGIN_VERSION);
$mapper = new WordPressBuybackRequestMapper();
$repository = new WordPressBuybackRequestRepository($wpdb, $mapper);
$transactions = new WordPressTransactionManager($wpdb);
$eventStore = new WordPressDomainEventStore($wpdb, $mapper);
$policy = new StatusTransitionPolicy();

try {
    $test->assert(is_plugin_active(AK_BUYBACK_PERSISTENCE_PLUGIN), 'Buyback plugin is active');
    $test->assert(APPLEKLINIKA_BUYBACK_SCHEMA_VERSION === '1.2.0', 'Code schema version is 1.2.0');
    $test->assert($schemaVersionBefore === '1.2.0', 'Installed schema version is 1.2.0');

    foreach ($tables as $table) {
        $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table));
        $test->assert(strtoupper((string) $engine) === 'INNODB', "{$table} uses a transactional engine");
    }

    $systemNow = (new SystemClock())->now();
    $test->assert($systemNow->getTimezone()->getName() === 'UTC', 'Production clock returns explicit UTC immutable time');

    $test->throws(
        fn () => $mapper->toDomain(['id' => 'broken']),
        PersistenceException::class,
        'Mapper rejects corrupt partial rows'
    );

    // Insert, generated identity, and complete round-trip through the internal create handler.
    $createTime = new DateTimeImmutable('2026-07-15T12:00:00+00:00');
    $randomSequence = ["\x01\x02\x03"];
    $randomSource = static function (int $length) use (&$randomSequence): string {
        return array_shift($randomSequence) ?? str_repeat("\x09", $length);
    };
    $generator = new WordPressRequestNumberGenerator(
        $repository,
        new FixedClock($createTime),
        3,
        $randomSource
    );
    $createHandler = new CreateDraftBuybackRequestHandler(
        $repository,
        $generator,
        new FixedClock($createTime),
        $transactions
    );
    $created = $createHandler->handle(new CreateDraftBuybackRequest(
        new DeviceCategory(DeviceCategory::IPHONE),
        new ModelKey('iphone-13-pro'),
        new DeviceDisplayName('iPhone 13 Pro 128 GB'),
        new ServiceMode(ServiceMode::FAST_ONLINE),
        new RequestSource(RequestSource::QA_FIXTURE),
        new CustomerId(880001001),
        null,
        null,
        $marker
    ));
    $test->assert($created->id()->toInt() > 0, 'Database-generated request identity is returned');
    $test->assert($created->requestNumber()->value() === 'AKB-20260715-010203', 'Request-number format uses UTC date and secure-byte encoding');
    $test->assert($created->version()->value() === 0 && $created->status()->code() === BuybackStatus::DRAFT, 'Inserted draft starts at version zero');

    $byId = $repository->getById($created->id());
    $byNumber = $repository->getByRequestNumber($created->requestNumber());
    $test->assert($byId instanceof BuybackRequest && $byNumber instanceof BuybackRequest, 'Request reloads by ID and unique request number');
    $test->assert(
        $byId?->category()->code() === DeviceCategory::IPHONE
        && $byId?->modelKey()->value() === 'iphone-13-pro'
        && $byId?->deviceDisplayName()->value() === 'iPhone 13 Pro 128 GB'
        && $byId?->serviceMode()->code() === ServiceMode::FAST_ONLINE
        && $byId?->source()->code() === RequestSource::QA_FIXTURE
        && $byId?->customerId()?->toInt() === 880001001,
        'All owned domain fields survive insert and reconstitution'
    );
    $test->assert($byNumber?->id()->equals($created->id()) === true, 'ID and request-number lookups identify the same aggregate');

    // UTC normalization from a non-UTC immutable timestamp.
    $localTimeRequest = $transactions->transactional(fn (): BuybackRequest => $repository->insert(qaDraft(
        qaRequestNumber($runToken, 'utc'),
        new DateTimeImmutable('2026-07-15T14:30:00+02:00'),
        $marker
    )));
    $test->assert(
        $localTimeRequest->createdAt()->getTimezone()->getName() === 'UTC'
        && $localTimeRequest->createdAt()->format('Y-m-d H:i:s') === '2026-07-15 12:30:00',
        'Database timestamps persist and reconstitute consistently in UTC'
    );

    // Duplicate request number and production-generator collision handling.
    $test->throws(
        fn () => $transactions->transactional(fn (): BuybackRequest => $repository->insert(qaDraft(
            $created->requestNumber(),
            $createTime,
            $marker
        ))),
        DuplicateBuybackRequestException::class,
        'Duplicate request numbers are rejected'
    );
    $raceHandler = new CreateDraftBuybackRequestHandler(
        $repository,
        new FixedRequestNumberGenerator($created->requestNumber()),
        new FixedClock($createTime),
        $transactions
    );
    $test->throws(
        fn () => $raceHandler->handle(new CreateDraftBuybackRequest(
            new DeviceCategory(DeviceCategory::IPHONE),
            new ModelKey('iphone-13-pro'),
            new DeviceDisplayName('iPhone 13 Pro 128 GB'),
            new ServiceMode(ServiceMode::FAST_ONLINE),
            new RequestSource(RequestSource::QA_FIXTURE),
            null,
            null,
            null,
            $marker
        )),
        DuplicateBuybackRequestException::class,
        'Insert-time request-number race returns a typed duplicate failure'
    );

    $collisionNumber = new RequestNumber('AKB-20260715-414243');
    $transactions->transactional(fn (): BuybackRequest => $repository->insert(qaDraft($collisionNumber, $createTime, $marker)));
    $collisionSequence = ['ABC', 'DEF'];
    $collisionGenerator = new WordPressRequestNumberGenerator(
        $repository,
        new FixedClock($createTime),
        3,
        static function () use (&$collisionSequence): string {
            return array_shift($collisionSequence) ?? 'XYZ';
        }
    );
    $retryNumber = $collisionGenerator->generate();
    $test->assert($retryNumber->value() === 'AKB-20260715-444546', 'Request-number collision retries with fresh secure bytes');
    $test->assert((new RequestNumber($retryNumber->value()))->equals($retryNumber), 'Generated request number passes domain validation');

    $exhaustionGenerator = new WordPressRequestNumberGenerator(
        $repository,
        new FixedClock($createTime),
        2,
        static fn (): string => 'ABC'
    );
    $test->throws(
        fn () => $exhaustionGenerator->generate(),
        RequestNumberGenerationException::class,
        'Request-number retry exhaustion fails safely'
    );

    // Optimistic locking with two independently loaded aggregates.
    $lockRequest = $transactions->transactional(fn (): BuybackRequest => $repository->insert(qaDraft(
        qaRequestNumber($runToken, 'lock'),
        $createTime,
        $marker
    )));
    $firstCopy = $repository->getById($lockRequest->id());
    $secondCopy = $repository->getById($lockRequest->id());
    if (! $firstCopy instanceof BuybackRequest || ! $secondCopy instanceof BuybackRequest) {
        throw new RuntimeException('Optimistic-lock fixture could not be loaded twice.');
    }
    $firstCopy->attachCustomer(new CustomerId(880001101), $createTime->modify('+1 minute'));
    $secondCopy->attachCustomer(new CustomerId(880001102), $createTime->modify('+1 minute'));
    $transactions->transactional(function () use ($repository, $firstCopy): void {
        $repository->save($firstCopy, new AggregateVersion(0));
    });
    $test->throws(
        fn () => $transactions->transactional(function () use ($repository, $secondCopy): void {
            $repository->save($secondCopy, new AggregateVersion(0));
        }),
        StaleAggregateVersionException::class,
        'Second concurrent save is rejected as stale'
    );
    $acceptedLockState = $repository->getById($lockRequest->id());
    $test->assert(
        $acceptedLockState?->customerId()?->toInt() === 880001101
        && $acceptedLockState?->version()->value() === 1,
        'Only the first optimistic-lock mutation reaches the database'
    );

    // Transactional status update and event persistence.
    $eventRequest = $transactions->transactional(fn (): BuybackRequest => $repository->insert(qaDraft(
        qaRequestNumber($runToken, 'event'),
        $createTime,
        $marker
    )));
    $transitionHandler = new TransitionBuybackRequestHandler($repository, $transactions, $eventStore, $policy);
    $submittedAt = $createTime->modify('+2 minutes');
    $submitted = $transitionHandler->handle(new TransitionBuybackRequest(
        $eventRequest->id(),
        new BuybackStatus(BuybackStatus::SUBMITTED),
        new AggregateVersion(0),
        customerTransitionContext($submittedAt, 'qa-submit-' . $runToken)
    ));
    $test->assert($submitted->status()->code() === BuybackStatus::SUBMITTED && $submitted->version()->value() === 1, 'Valid transition persists status and version');
    $eventRows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `{$tables[Schema::EVENTS]}` WHERE request_id = %d ORDER BY id ASC",
        $eventRequest->id()->toInt()
    ), ARRAY_A);
    $test->assert(is_array($eventRows) && count($eventRows) === 1, 'Valid transition writes exactly one event');
    $firstEventRow = $eventRows[0] ?? [];
    $test->assert(
        ($firstEventRow['event_type'] ?? null) === WordPressDomainEventStore::STATUS_CHANGED
        && ($firstEventRow['from_status'] ?? null) === BuybackStatus::DRAFT
        && ($firstEventRow['to_status'] ?? null) === BuybackStatus::SUBMITTED
        && ($firstEventRow['actor_type'] ?? null) === ActorType::CUSTOMER,
        'Persisted event contains the correct request transition and actor'
    );
    $firstPayload = json_decode((string) ($firstEventRow['private_payload_json'] ?? ''), true);
    $test->assert(
        is_array($firstPayload) && ($firstPayload['aggregate_version'] ?? null) === 1,
        'Status event persists the accepted aggregate version without customer payload'
    );

    // Exact event replay is idempotent; the next valid event is distinct.
    $exactReplay = new BuybackStatusChanged(
        $eventRequest->id(),
        $eventRequest->requestNumber(),
        new BuybackStatus(BuybackStatus::DRAFT),
        new BuybackStatus(BuybackStatus::SUBMITTED),
        new ActorType(ActorType::CUSTOMER),
        $submittedAt,
        'qa-submit-' . $runToken,
        ['aggregate_version' => 1]
    );
    $transactions->transactional(function () use ($eventStore, $exactReplay): void {
        $eventStore->publish($exactReplay);
    });
    $eventCountAfterReplay = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `{$tables[Schema::EVENTS]}` WHERE request_id = %d",
        $eventRequest->id()->toInt()
    ));
    $test->assert($eventCountAfterReplay === 1, 'Exact event retry does not create a duplicate row');

    $awaitingAt = $createTime->modify('+3 minutes');
    $awaiting = $transitionHandler->handle(new TransitionBuybackRequest(
        $eventRequest->id(),
        new BuybackStatus(BuybackStatus::AWAITING_HANDOVER),
        new AggregateVersion(1),
        staffTransitionContext($awaitingAt, 'qa-awaiting-' . $runToken)
    ));
    $test->assert($awaiting->version()->value() === 2, 'A different valid transition advances the aggregate again');
    $eventCountAfterSecond = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `{$tables[Schema::EVENTS]}` WHERE request_id = %d",
        $eventRequest->id()->toInt()
    ));
    $test->assert($eventCountAfterSecond === 2, 'Different valid event is not incorrectly deduplicated');
    $eventIdentityRows = $wpdb->get_results($wpdb->prepare(
        "SELECT idempotency_key, private_payload_json FROM `{$tables[Schema::EVENTS]}` WHERE request_id = %d ORDER BY id ASC",
        $eventRequest->id()->toInt()
    ), ARRAY_A);
    $secondPayload = json_decode((string) ($eventIdentityRows[1]['private_payload_json'] ?? ''), true);
    $test->assert(
        is_array($eventIdentityRows)
        && count($eventIdentityRows) === 2
        && ($eventIdentityRows[0]['idempotency_key'] ?? null) !== ($eventIdentityRows[1]['idempotency_key'] ?? null)
        && is_array($secondPayload)
        && ($secondPayload['aggregate_version'] ?? null) === 2,
        'A later aggregate version receives a distinct idempotency key'
    );

    $missingTableDatabase = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
    $missingTableDatabase->set_prefix($wpdb->prefix . 'qa_missing_' . $runToken . '_');
    $missingTableDatabase->suppress_errors(true);
    $unrelatedFailureStore = new WordPressDomainEventStore($missingTableDatabase, $mapper);
    $test->throws(
        fn () => $unrelatedFailureStore->publish($exactReplay),
        PersistenceException::class,
        'Unrelated event-store SQL failures are not treated as idempotent success'
    );
    $missingTableDatabase->close();

    // Event persistence failure rolls request state and version back.
    $rollbackRequest = $transactions->transactional(fn (): BuybackRequest => $repository->insert(qaDraft(
        qaRequestNumber($runToken, 'rollback'),
        $createTime,
        $marker
    )));
    $failingHandler = new TransitionBuybackRequestHandler(
        $repository,
        $transactions,
        new FailingDomainEventPublisher(),
        $policy
    );
    $rollbackFailure = $test->throws(
        fn () => $failingHandler->handle(new TransitionBuybackRequest(
            $rollbackRequest->id(),
            new BuybackStatus(BuybackStatus::SUBMITTED),
            new AggregateVersion(0),
            customerTransitionContext($submittedAt, 'qa-rollback-' . $runToken)
        )),
        RuntimeException::class,
        'Controlled event persistence failure remains observable'
    );
    $test->assert($rollbackFailure?->getMessage() === 'Controlled event persistence failure.', 'Transaction manager rethrows the original failure');
    $rolledBack = $repository->getById($rollbackRequest->id());
    $rolledBackEvents = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `{$tables[Schema::EVENTS]}` WHERE request_id = %d",
        $rollbackRequest->id()->toInt()
    ));
    $test->assert(
        $rolledBack?->status()->code() === BuybackStatus::DRAFT
        && $rolledBack?->version()->value() === 0
        && $rolledBackEvents === 0,
        'Event failure rolls request update and event rows back together'
    );

    // Explicit transaction behavior, including nested rejection and no residue.
    $commitRequest = $transactions->transactional(fn (): BuybackRequest => $repository->insert(qaDraft(
        qaRequestNumber($runToken, 'commit'),
        $createTime,
        $marker
    )));
    $test->assert($repository->getById($commitRequest->id()) instanceof BuybackRequest, 'Successful transaction commits');

    $rollbackNumber = qaRequestNumber($runToken, 'txfail');
    $test->throws(
        fn () => $transactions->transactional(function () use ($repository, $rollbackNumber, $createTime, $marker): void {
            $repository->insert(qaDraft($rollbackNumber, $createTime, $marker));
            throw new LogicException('Controlled request rollback.');
        }),
        LogicException::class,
        'Thrown transaction callback failure remains observable'
    );
    $test->assert(! $repository->existsByRequestNumber($rollbackNumber), 'Thrown callback rolls inserted request back');
    $test->throws(
        fn () => $transactions->transactional(fn () => $transactions->transactional(static fn (): bool => true)),
        PersistenceException::class,
        'Nested transactions are rejected explicitly'
    );
    $test->assert((int) $wpdb->get_var('SELECT @@in_transaction') === 0, 'No database transaction remains open after failures');

    // Customer isolation without requiring WordPress user records.
    $customerA = new CustomerId(880001201);
    $customerB = new CustomerId(880001202);
    $customerRequestA = $transactions->transactional(fn (): BuybackRequest => $repository->insert(qaDraft(
        qaRequestNumber($runToken, 'customer-a'),
        $createTime,
        $marker,
        $customerA
    )));
    $customerRequestB = $transactions->transactional(fn (): BuybackRequest => $repository->insert(qaDraft(
        qaRequestNumber($runToken, 'customer-b'),
        $createTime,
        $marker,
        $customerB
    )));
    $pageA = $repository->findByCustomer($customerA, new PageRequest(1, 20));
    $pageB = $repository->findByCustomer($customerB, new PageRequest(1, 20));
    $test->assert(
        $pageA->total === 1
        && count($pageA->items()) === 1
        && $pageA->items()[0]->id()->equals($customerRequestA->id()),
        'Customer query returns only customer A records'
    );
    $test->assert(
        $pageB->total === 1
        && count($pageB->items()) === 1
        && $pageB->items()[0]->id()->equals($customerRequestB->id()),
        'Customer query returns only customer B records'
    );

    // Status pagination uses deterministic newest-first ordering.
    $paginationIds = [];
    for ($index = 1; $index <= 5; ++$index) {
        $fixture = $transactions->transactional(fn (): BuybackRequest => $repository->insert(qaDraft(
            qaRequestNumber($runToken, 'page-' . $index),
            new DateTimeImmutable(sprintf('2099-01-01T00:00:%02d+00:00', $index)),
            $marker
        )));
        $paginationIds[] = $fixture->id()->toInt();
    }
    $expectedNewest = array_reverse($paginationIds);
    $statusPageOne = $repository->findByStatus(new BuybackStatus(BuybackStatus::DRAFT), new PageRequest(1, 2));
    $statusPageTwo = $repository->findByStatus(new BuybackStatus(BuybackStatus::DRAFT), new PageRequest(2, 2));
    $statusPageThree = $repository->findByStatus(new BuybackStatus(BuybackStatus::DRAFT), new PageRequest(3, 2));
    $actualPageIds = array_map(static fn (BuybackRequest $request): int => $request->id()->toInt(), [
        ...$statusPageOne->items(),
        ...$statusPageTwo->items(),
        ...$statusPageThree->items(),
    ]);
    $test->assert(array_slice($actualPageIds, 0, 5) === $expectedNewest, 'Status pages use deterministic updated/created/ID newest-first ordering');
    $test->assert(count(array_unique(array_slice($actualPageIds, 0, 5))) === 5, 'Adjacent status pages contain no duplicate fixture rows');
    $test->throws(fn () => new PageRequest(1, 101), InvalidValueObjectException::class, 'Pagination enforces the safe maximum page size');

    // Missing aggregate is distinct from stale version.
    $missingId = new BuybackRequestId(922337203);
    while ($repository->getById($missingId) !== null) {
        $missingId = new BuybackRequestId($missingId->toInt() + 1);
    }
    $test->throws(
        fn () => $transitionHandler->handle(new TransitionBuybackRequest(
            $missingId,
            new BuybackStatus(BuybackStatus::SUBMITTED),
            new AggregateVersion(0),
            customerTransitionContext($submittedAt, 'qa-missing-' . $runToken)
        )),
        BuybackRequestNotFoundException::class,
        'Missing request produces the typed not-found failure'
    );

    $test->assert(get_option(Schema::OPTION_SCHEMA_VERSION) === $schemaVersionBefore, 'Persistence tests do not change installed schema version');
    $test->assert(get_option(Schema::OPTION_PLUGIN_VERSION) === $pluginVersionBefore, 'Persistence tests do not change plugin-version option state');
} catch (Throwable $exception) {
    $test->fail($exception);
} finally {
    try {
        cleanupPersistenceFixtures($wpdb, $tables, $marker);
    } catch (Throwable $cleanupException) {
        $test->fail($cleanupException);
    }
}

$remainingIds = persistenceMarkerIds($wpdb, $tables[Schema::REQUESTS], $marker);
$countsAfter = persistenceRowCounts($wpdb, $tables);
$legacyHashAfter = persistenceLegacyHash($wpdb);
$test->assert($remainingIds === [], 'All rows for the exact QA marker are removed');
$test->assert($countsAfter === $countsBefore, 'Request, snapshot, and event counts return to pre-test values');
$test->assert($legacyHashAfter === $legacyHashBefore, 'Legacy buyback user-meta hash remains unchanged');
$test->assert((int) $wpdb->get_var('SELECT @@in_transaction') === 0, 'Cleanup leaves no database transaction open');

$test->finish($countsBefore, $countsAfter, $marker);
