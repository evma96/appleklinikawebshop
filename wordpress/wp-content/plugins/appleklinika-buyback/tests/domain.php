<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap-domain.php';

use AppleKlinika\Buyback\Application\Port\BuybackRequestRepository;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\DomainEventPublisher;
use AppleKlinika\Buyback\Application\Port\RequestNumberGenerator;
use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Domain\Buyback\BuybackRequest;
use AppleKlinika\Buyback\Domain\Buyback\BuybackRequestId;
use AppleKlinika\Buyback\Domain\Buyback\BuybackStatus;
use AppleKlinika\Buyback\Domain\Buyback\CustomerId;
use AppleKlinika\Buyback\Domain\Buyback\DeviceCategory;
use AppleKlinika\Buyback\Domain\Buyback\DeviceDisplayName;
use AppleKlinika\Buyback\Domain\Buyback\Event\BuybackStatusChanged;
use AppleKlinika\Buyback\Domain\Buyback\HandoverMethod;
use AppleKlinika\Buyback\Domain\Buyback\HandoverMethodPolicy;
use AppleKlinika\Buyback\Domain\Buyback\ModelKey;
use AppleKlinika\Buyback\Domain\Buyback\RequestNumber;
use AppleKlinika\Buyback\Domain\Buyback\RequestSource;
use AppleKlinika\Buyback\Domain\Buyback\ServiceMode;
use AppleKlinika\Buyback\Domain\Buyback\StatusTransitionPolicy;
use AppleKlinika\Buyback\Domain\Buyback\TransitionContext;
use AppleKlinika\Buyback\Domain\Exception\CurrencyMismatchException;
use AppleKlinika\Buyback\Domain\Exception\InvalidStatusTransitionException;
use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;
use AppleKlinika\Buyback\Domain\Shared\ActorType;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Domain\Shared\Money;

final class DomainTestRunner
{
    private int $assertions = 0;

    /** @var list<string> */
    private array $failures = [];

    public int $allowedTransitionCases = 0;

    public int $rejectedTransitionCases = 0;

    public int $actorRestrictionCases = 0;

    public int $serviceRestrictionCases = 0;

    public int $conditionalGuardCases = 0;

    public function assert(bool $condition, string $message): void
    {
        ++$this->assertions;

        if (! $condition) {
            $this->failures[] = $message;
        }
    }

    /**
     * @param class-string<\Throwable> $expected
     */
    public function throws(callable $operation, string $expected, string $message): void
    {
        ++$this->assertions;

        try {
            $operation();
            $this->failures[] = $message . ' (no exception thrown)';
        } catch (\Throwable $exception) {
            if (! $exception instanceof $expected) {
                $this->failures[] = sprintf(
                    '%s (expected %s, received %s: %s)',
                    $message,
                    $expected,
                    $exception::class,
                    $exception->getMessage()
                );
            }
        }
    }

    public function finish(): never
    {
        if ($this->failures !== []) {
            foreach ($this->failures as $failure) {
                fwrite(STDERR, "FAIL: {$failure}\n");
            }

            fwrite(STDERR, sprintf("%d assertion(s), %d failure(s).\n", $this->assertions, count($this->failures)));
            exit(1);
        }

        echo sprintf(
            "Buyback domain tests passed: %d assertions; %d allowed transition cases; %d rejected matrix cases; %d actor cases; %d service-mode cases; %d conditional-guard cases.\n",
            $this->assertions,
            $this->allowedTransitionCases,
            $this->rejectedTransitionCases,
            $this->actorRestrictionCases,
            $this->serviceRestrictionCases,
            $this->conditionalGuardCases
        );
        exit(0);
    }
}

/**
 * @param array{
 *   expires?: DateTimeImmutable|null,
 *   evidence?: bool,
 *   settlement?: bool,
 *   credit?: bool,
 *   order?: bool,
 *   correlation?: string|null
 * } $options
 */
function context(string $actor, array $options = []): TransitionContext
{
    return new TransitionContext(
        new ActorType($actor),
        new DateTimeImmutable('2026-07-15T12:00:00+00:00'),
        $options['expires'] ?? new DateTimeImmutable('2026-07-16T12:00:00+00:00'),
        $options['evidence'] ?? false,
        $options['settlement'] ?? false,
        $options['credit'] ?? false,
        $options['order'] ?? false,
        $options['correlation'] ?? null
    );
}

/** @return array{0: ServiceMode, 1: TransitionContext} */
function validCaseContext(string $to, string $actor): array
{
    $mode = $to === BuybackStatus::TRADE_IN_PENDING || $to === BuybackStatus::TRADE_IN_APPLIED
        ? new ServiceMode(ServiceMode::TRADE_IN)
        : new ServiceMode(ServiceMode::FAST_ONLINE);

    return [$mode, context($actor, [
        'evidence' => true,
        'settlement' => true,
        'credit' => true,
        'order' => true,
    ])];
}

$test = new DomainTestRunner();
$policy = new StatusTransitionPolicy();

// Value objects.
$requestId = new BuybackRequestId(42);
$test->assert($requestId->toInt() === 42 && (string) $requestId === '42', 'BuybackRequestId conversions work');
$test->assert($requestId->equals(new BuybackRequestId(42)), 'BuybackRequestId equality works');
$test->throws(fn () => new BuybackRequestId(0), InvalidValueObjectException::class, 'BuybackRequestId rejects zero');
$test->throws(fn () => new BuybackRequestId(-1), InvalidValueObjectException::class, 'BuybackRequestId rejects negatives');

$customerId = new CustomerId(7);
$test->assert($customerId->toInt() === 7 && $customerId->equals(new CustomerId(7)), 'CustomerId accepts positive values');
$test->throws(fn () => new CustomerId(0), InvalidValueObjectException::class, 'CustomerId rejects zero');
$test->throws(fn () => new CustomerId(-2), InvalidValueObjectException::class, 'CustomerId rejects negatives');

$requestNumber = new RequestNumber('  AK-2026-001  ');
$test->assert($requestNumber->value() === 'AK-2026-001', 'RequestNumber trims input');
$test->assert((new RequestNumber('AK   2026'))->value() === 'AK 2026', 'RequestNumber normalizes whitespace');
$maximumRequestNumber = str_repeat('A', 32);
$test->assert((new RequestNumber($maximumRequestNumber))->value() === $maximumRequestNumber, 'RequestNumber accepts and preserves exactly 32 ASCII characters');
$test->assert((new RequestNumber("  {$maximumRequestNumber}  "))->value() === $maximumRequestNumber, 'RequestNumber trims before validating the 32-character boundary');
$test->throws(fn () => new RequestNumber('   '), InvalidValueObjectException::class, 'RequestNumber rejects empty values');
$test->throws(fn () => new RequestNumber(str_repeat('A', 33)), InvalidValueObjectException::class, 'RequestNumber rejects exactly 33 ASCII characters');

$test->assert((new DeviceCategory(DeviceCategory::IPHONE))->code() === 'iphone', 'DeviceCategory accepts the V1 iPhone code');
$test->throws(fn () => new DeviceCategory('ipad'), InvalidValueObjectException::class, 'DeviceCategory rejects unsupported V1 categories');
$test->assert((new ModelKey('  iPhone_13 Pro  '))->value() === 'iphone-13-pro', 'ModelKey normalizes identifiers');
$test->throws(fn () => new ModelKey('<b>iphone</b>'), InvalidValueObjectException::class, 'ModelKey rejects markup and unsupported characters');
$test->throws(fn () => new ModelKey(str_repeat('a', 101)), InvalidValueObjectException::class, 'ModelKey enforces the schema length');
$test->assert((new DeviceDisplayName('  iPhone 13   Pro  '))->value() === 'iPhone 13 Pro', 'DeviceDisplayName normalizes whitespace');
$test->throws(fn () => new DeviceDisplayName('<b>iPhone</b>'), InvalidValueObjectException::class, 'DeviceDisplayName rejects HTML');
$test->throws(fn () => new DeviceDisplayName(str_repeat('a', 192)), InvalidValueObjectException::class, 'DeviceDisplayName enforces the schema length');
foreach (RequestSource::supportedCodes() as $sourceCode) {
    $test->assert((new RequestSource($sourceCode))->code() === $sourceCode, "RequestSource accepts {$sourceCode}");
}
$test->throws(fn () => new RequestSource('implicit_test'), InvalidValueObjectException::class, 'RequestSource rejects unknown sources');

$money = new Money(10000, 'HUF');
$test->assert($money->equals(new Money(10000, 'HUF')), 'Money equality works');
$test->assert($money->add(new Money(500, 'HUF'))->amount() === 10500, 'Money addition works');
$test->assert($money->subtract(new Money(500, 'HUF'))->amount() === 9500, 'Money subtraction works');
$test->assert($money->compare(new Money(9999, 'HUF')) === 1, 'Money comparison works');
$test->throws(fn () => new Money(1.5, 'HUF'), InvalidValueObjectException::class, 'Money rejects floats');
$test->assert((new Money(-1, 'HUF'))->amount() === -1, 'Money preserves signed rule adjustments');
$test->throws(fn () => new Money(1, 'huf'), InvalidValueObjectException::class, 'Money rejects lowercase currency');
$test->throws(fn () => $money->add(new Money(1, 'EUR')), CurrencyMismatchException::class, 'Money rejects currency mismatch');
$test->throws(fn () => (new Money(10, 'HUF'))->subtract(new Money(11, 'HUF')), InvalidValueObjectException::class, 'Money prevents negative subtraction result');

foreach (ServiceMode::supportedCodes() as $modeCode) {
    $test->assert((new ServiceMode($modeCode))->code() === $modeCode, "ServiceMode accepts {$modeCode}");
}
$test->throws(fn () => new ServiceMode('unknown'), InvalidValueObjectException::class, 'ServiceMode rejects unknown code');
$test->assert((new ServiceMode(ServiceMode::TRADE_IN))->isTradeIn(), 'Trade-in mode is identified');
$test->assert((new ServiceMode(ServiceMode::FAST_ONLINE))->requiresPayout(), 'Non-trade-in mode requires payout');
$test->assert((new ServiceMode(ServiceMode::HIGHER_OFFER))->allowsCourier(), 'Higher-offer mode permits courier');

foreach (HandoverMethod::supportedCodes() as $methodCode) {
    $test->assert((new HandoverMethod($methodCode))->code() === $methodCode, "HandoverMethod accepts {$methodCode}");
}
$test->throws(fn () => new HandoverMethod('locker'), InvalidValueObjectException::class, 'HandoverMethod rejects unknown code');

foreach (BuybackStatus::supportedCodes() as $statusCode) {
    $test->assert((new BuybackStatus($statusCode))->code() === $statusCode, "BuybackStatus accepts {$statusCode}");
}
$test->throws(fn () => new BuybackStatus('unknown'), InvalidValueObjectException::class, 'BuybackStatus rejects unknown code');
$test->assert((new BuybackStatus(BuybackStatus::CLOSED))->isTerminal(), 'Closed status is terminal');
$test->assert((new BuybackStatus(BuybackStatus::DRAFT))->isCustomerEditable(), 'Draft is customer editable');

foreach (ActorType::supportedCodes() as $actorCode) {
    $test->assert((new ActorType($actorCode))->code() === $actorCode, "ActorType accepts {$actorCode}");
}
$test->throws(fn () => new ActorType('administrator'), InvalidValueObjectException::class, 'ActorType rejects WordPress roles');

$version = new AggregateVersion(0);
$test->assert($version->next()->value() === 1, 'AggregateVersion increments');
$test->throws(fn () => new AggregateVersion(-1), InvalidValueObjectException::class, 'AggregateVersion rejects negatives');

// Every allowed transition edge, with one valid actor/mode/context per edge.
$allowedCases = [
    [BuybackStatus::DRAFT, BuybackStatus::SUBMITTED, ActorType::CUSTOMER],
    [BuybackStatus::DRAFT, BuybackStatus::CANCELLED, ActorType::CUSTOMER],
    [BuybackStatus::SUBMITTED, BuybackStatus::AWAITING_HANDOVER, ActorType::STAFF],
    [BuybackStatus::SUBMITTED, BuybackStatus::COURIER_REQUESTED, ActorType::CUSTOMER],
    [BuybackStatus::SUBMITTED, BuybackStatus::CANCELLED, ActorType::STAFF],
    [BuybackStatus::AWAITING_HANDOVER, BuybackStatus::COURIER_REQUESTED, ActorType::STAFF],
    [BuybackStatus::AWAITING_HANDOVER, BuybackStatus::RECEIVED, ActorType::STAFF],
    [BuybackStatus::AWAITING_HANDOVER, BuybackStatus::CANCELLED, ActorType::CUSTOMER],
    [BuybackStatus::COURIER_REQUESTED, BuybackStatus::COURIER_BOOKED, ActorType::SYSTEM],
    [BuybackStatus::COURIER_REQUESTED, BuybackStatus::AWAITING_HANDOVER, ActorType::STAFF],
    [BuybackStatus::COURIER_REQUESTED, BuybackStatus::CANCELLED, ActorType::STAFF],
    [BuybackStatus::COURIER_BOOKED, BuybackStatus::RECEIVED, ActorType::SYSTEM],
    [BuybackStatus::COURIER_BOOKED, BuybackStatus::CANCELLED, ActorType::CUSTOMER],
    [BuybackStatus::RECEIVED, BuybackStatus::INSPECTION_PENDING, ActorType::SYSTEM],
    [BuybackStatus::INSPECTION_PENDING, BuybackStatus::INSPECTING, ActorType::STAFF],
    [BuybackStatus::INSPECTING, BuybackStatus::PRELIMINARY_MISMATCH, ActorType::SYSTEM],
    [BuybackStatus::INSPECTING, BuybackStatus::FINAL_OFFER_READY, ActorType::STAFF],
    [BuybackStatus::PRELIMINARY_MISMATCH, BuybackStatus::FINAL_OFFER_READY, ActorType::SYSTEM],
    [BuybackStatus::FINAL_OFFER_READY, BuybackStatus::FINAL_OFFER_SENT, ActorType::STAFF],
    [BuybackStatus::FINAL_OFFER_SENT, BuybackStatus::FINAL_OFFER_ACCEPTED, ActorType::CUSTOMER],
    [BuybackStatus::FINAL_OFFER_SENT, BuybackStatus::FINAL_OFFER_REJECTED, ActorType::CUSTOMER],
    [BuybackStatus::FINAL_OFFER_ACCEPTED, BuybackStatus::PAYOUT_PENDING, ActorType::SYSTEM],
    [BuybackStatus::FINAL_OFFER_ACCEPTED, BuybackStatus::TRADE_IN_PENDING, ActorType::STAFF],
    [BuybackStatus::FINAL_OFFER_REJECTED, BuybackStatus::RETURN_REQUESTED, ActorType::CUSTOMER],
    [BuybackStatus::RETURN_REQUESTED, BuybackStatus::RETURNING_DEVICE, ActorType::SYSTEM],
    [BuybackStatus::PAYOUT_PENDING, BuybackStatus::PAID, ActorType::STAFF],
    [BuybackStatus::TRADE_IN_PENDING, BuybackStatus::TRADE_IN_APPLIED, ActorType::SYSTEM],
    [BuybackStatus::PAID, BuybackStatus::CLOSED, ActorType::STAFF],
    [BuybackStatus::TRADE_IN_APPLIED, BuybackStatus::CLOSED, ActorType::SYSTEM],
    [BuybackStatus::RETURNING_DEVICE, BuybackStatus::CLOSED, ActorType::STAFF],
    [BuybackStatus::CANCELLED, BuybackStatus::CLOSED, ActorType::SYSTEM],
];

foreach ($allowedCases as [$from, $to, $actor]) {
    [$mode, $transitionContext] = validCaseContext($to, $actor);
    try {
        $policy->assertAllowed(new BuybackStatus($from), new BuybackStatus($to), $mode, $transitionContext);
        ++$test->allowedTransitionCases;
        $test->assert(true, "Allowed transition {$from} -> {$to}");
    } catch (Throwable $exception) {
        $test->assert(false, "Allowed transition {$from} -> {$to} failed: {$exception->getMessage()}");
    }
}

// Every unsupported status pair, including all same-status transitions.
$matrix = $policy->transitionMatrix();
foreach (BuybackStatus::supportedCodes() as $from) {
    foreach (BuybackStatus::supportedCodes() as $to) {
        if (isset($matrix[$from][$to])) {
            continue;
        }

        ++$test->rejectedTransitionCases;
        $test->throws(
            fn () => $policy->assertAllowed(
                new BuybackStatus($from),
                new BuybackStatus($to),
                new ServiceMode(ServiceMode::FAST_ONLINE),
                context(ActorType::STAFF, ['evidence' => true, 'settlement' => true, 'credit' => true, 'order' => true])
            ),
            InvalidStatusTransitionException::class,
            "Unsupported transition {$from} -> {$to} is rejected"
        );
    }
}

// Actor restrictions for every transition edge and every actor type.
foreach ($matrix as $from => $targets) {
    foreach ($targets as $to => $allowedActors) {
        foreach (ActorType::supportedCodes() as $actor) {
            ++$test->actorRestrictionCases;
            [$mode, $transitionContext] = validCaseContext($to, $actor);
            $operation = fn () => $policy->assertAllowed(
                new BuybackStatus($from),
                new BuybackStatus($to),
                $mode,
                $transitionContext
            );

            if (in_array($actor, $allowedActors, true)) {
                try {
                    $operation();
                    $test->assert(true, "Actor {$actor} may perform {$from} -> {$to}");
                } catch (Throwable $exception) {
                    $test->assert(false, "Actor {$actor} should perform {$from} -> {$to}: {$exception->getMessage()}");
                }
            } else {
                $test->throws($operation, InvalidStatusTransitionException::class, "Actor {$actor} cannot perform {$from} -> {$to}");
            }
        }
    }
}

// Service-mode restrictions.
$serviceRestrictions = [
    [BuybackStatus::SUBMITTED, BuybackStatus::COURIER_REQUESTED, ServiceMode::IN_STORE_INSTANT],
    [BuybackStatus::SUBMITTED, BuybackStatus::COURIER_REQUESTED, ServiceMode::TRADE_IN],
    [BuybackStatus::FINAL_OFFER_ACCEPTED, BuybackStatus::TRADE_IN_PENDING, ServiceMode::FAST_ONLINE],
    [BuybackStatus::FINAL_OFFER_ACCEPTED, BuybackStatus::PAYOUT_PENDING, ServiceMode::TRADE_IN],
];
foreach ($serviceRestrictions as [$from, $to, $mode]) {
    ++$test->serviceRestrictionCases;
    $actor = $to === BuybackStatus::COURIER_REQUESTED ? ActorType::CUSTOMER : ActorType::STAFF;
    $test->throws(
        fn () => $policy->assertAllowed(
            new BuybackStatus($from),
            new BuybackStatus($to),
            new ServiceMode($mode),
            context($actor, ['evidence' => true])
        ),
        InvalidStatusTransitionException::class,
        "Service-mode restriction rejects {$mode} for {$from} -> {$to}"
    );
}

// Conditional guards.
$expired = new DateTimeImmutable('2026-07-15T11:59:59+00:00');
$guardCases = [
    [BuybackStatus::FINAL_OFFER_SENT, BuybackStatus::FINAL_OFFER_ACCEPTED, ServiceMode::FAST_ONLINE, ActorType::CUSTOMER, ['expires' => $expired]],
    [BuybackStatus::FINAL_OFFER_SENT, BuybackStatus::FINAL_OFFER_REJECTED, ServiceMode::FAST_ONLINE, ActorType::CUSTOMER, ['expires' => $expired]],
    [BuybackStatus::FINAL_OFFER_SENT, BuybackStatus::FINAL_OFFER_ACCEPTED, ServiceMode::FAST_ONLINE, ActorType::STAFF, ['evidence' => false]],
    [BuybackStatus::FINAL_OFFER_SENT, BuybackStatus::FINAL_OFFER_REJECTED, ServiceMode::FAST_ONLINE, ActorType::STAFF, ['evidence' => false]],
    [BuybackStatus::PAYOUT_PENDING, BuybackStatus::PAID, ServiceMode::FAST_ONLINE, ActorType::STAFF, ['settlement' => false]],
    [BuybackStatus::TRADE_IN_PENDING, BuybackStatus::TRADE_IN_APPLIED, ServiceMode::TRADE_IN, ActorType::STAFF, ['credit' => false, 'order' => true]],
    [BuybackStatus::TRADE_IN_PENDING, BuybackStatus::TRADE_IN_APPLIED, ServiceMode::TRADE_IN, ActorType::STAFF, ['credit' => true, 'order' => false]],
];
foreach ($guardCases as [$from, $to, $mode, $actor, $options]) {
    ++$test->conditionalGuardCases;
    $test->throws(
        fn () => $policy->assertAllowed(
            new BuybackStatus($from),
            new BuybackStatus($to),
            new ServiceMode($mode),
            context($actor, $options)
        ),
        InvalidStatusTransitionException::class,
        "Conditional guard rejects {$from} -> {$to}"
    );
}

// Explicit regression examples.
$test->throws(
    fn () => $policy->assertAllowed(new BuybackStatus(BuybackStatus::DRAFT), new BuybackStatus(BuybackStatus::PAID), new ServiceMode(ServiceMode::FAST_ONLINE), context(ActorType::STAFF, ['settlement' => true])),
    InvalidStatusTransitionException::class,
    'Draft cannot jump directly to paid'
);
$test->throws(
    fn () => $policy->assertAllowed(new BuybackStatus(BuybackStatus::RECEIVED), new BuybackStatus(BuybackStatus::CANCELLED), new ServiceMode(ServiceMode::FAST_ONLINE), context(ActorType::STAFF)),
    InvalidStatusTransitionException::class,
    'Received request cannot jump to cancelled'
);
$test->throws(
    fn () => $policy->assertAllowed(new BuybackStatus(BuybackStatus::CLOSED), new BuybackStatus(BuybackStatus::DRAFT), new ServiceMode(ServiceMode::FAST_ONLINE), context(ActorType::SYSTEM)),
    InvalidStatusTransitionException::class,
    'Closed is terminal'
);

// Aggregate behavior and events.
$createdAt = new DateTimeImmutable('2026-07-15T10:00:00+00:00');
$aggregate = BuybackRequest::createDraft(
    new BuybackRequestId(100),
    new RequestNumber('AK-TEST-100'),
    new DeviceCategory(DeviceCategory::IPHONE),
    new ModelKey('iphone-13-pro'),
    new DeviceDisplayName('iPhone 13 Pro'),
    new ServiceMode(ServiceMode::FAST_ONLINE),
    new RequestSource(RequestSource::NATIVE),
    $createdAt
);
$test->assert($aggregate->status()->code() === BuybackStatus::DRAFT, 'Aggregate starts in draft');
$test->assert($aggregate->version()->value() === 0, 'Draft aggregate starts at version zero');
$test->assert(
    $aggregate->category()->code() === DeviceCategory::IPHONE
    && $aggregate->modelKey()->value() === 'iphone-13-pro'
    && $aggregate->deviceDisplayName()->value() === 'iPhone 13 Pro'
    && $aggregate->source()->code() === RequestSource::NATIVE,
    'Aggregate preserves schema-compatible device and source identity'
);
$aggregate->attachCustomer(new CustomerId(2), new DateTimeImmutable('2026-07-15T10:01:00+00:00'));
$test->assert($aggregate->customerId()?->toInt() === 2 && $aggregate->version()->value() === 1, 'Customer attachment increments version');
$aggregate->selectHandoverMethod(
    new HandoverMethod(HandoverMethod::COURIER),
    new HandoverMethodPolicy(),
    new DateTimeImmutable('2026-07-15T10:02:00+00:00')
);
$test->assert($aggregate->handoverMethod()?->code() === HandoverMethod::COURIER, 'Compatible handover method is selected');
$versionBeforeTransition = $aggregate->version()->value();
$aggregate->transitionTo(
    new BuybackStatus(BuybackStatus::SUBMITTED),
    $policy,
    new TransitionContext(
        new ActorType(ActorType::CUSTOMER),
        new DateTimeImmutable('2026-07-15T10:03:00+00:00'),
        null,
        false,
        false,
        false,
        false,
        'correlation-test-100'
    )
);
$test->assert($aggregate->version()->value() === $versionBeforeTransition + 1, 'Accepted transition increments aggregate version');
$test->assert(count($aggregate->pendingEvents()) === 1, 'Accepted transition records one event');
$event = $aggregate->pendingEvents()[0] ?? null;
$test->assert(
    $event instanceof BuybackStatusChanged
    && $event->fromStatus()->code() === BuybackStatus::DRAFT
    && $event->toStatus()->code() === BuybackStatus::SUBMITTED
    && $event->correlationId() === 'correlation-test-100',
    'Status-change event carries safe transition data'
);
$versionBeforeInvalidTransition = $aggregate->version()->value();
$test->throws(
    fn () => $aggregate->transitionTo(new BuybackStatus(BuybackStatus::PAID), $policy, context(ActorType::STAFF, ['settlement' => true])),
    InvalidStatusTransitionException::class,
    'Aggregate rejects invalid transition'
);
$test->assert($aggregate->version()->value() === $versionBeforeInvalidTransition, 'Invalid transition leaves aggregate version unchanged');
$released = $aggregate->releasePendingEvents();
$test->assert(count($released) === 1 && $aggregate->pendingEvents() === [], 'Released events clear aggregate queue');
$test->assert(! (new ReflectionClass(BuybackRequest::class))->hasMethod('setStatus'), 'Aggregate exposes no arbitrary setStatus method');

$test->throws(
    fn () => (new HandoverMethodPolicy())->assertCompatible(new ServiceMode(ServiceMode::TRADE_IN), new HandoverMethod(HandoverMethod::COURIER)),
    \AppleKlinika\Buyback\Domain\Exception\InvalidAggregateOperationException::class,
    'Trade-in courier is not silently enabled in V1'
);

// Port contracts load and remain independent from WordPress object types.
$ports = [
    BuybackRequestRepository::class,
    RequestNumberGenerator::class,
    Clock::class,
    TransactionManager::class,
    DomainEventPublisher::class,
];
foreach ($ports as $port) {
    $reflection = new ReflectionClass($port);
    $test->assert($reflection->isInterface(), "{$port} is an interface");

    foreach ($reflection->getMethods() as $method) {
        $signature = (string) $method->getReturnType();
        foreach ($method->getParameters() as $parameter) {
            $signature .= ' ' . (string) $parameter->getType();
        }
        $test->assert(! str_contains($signature, 'WP_') && ! str_contains($signature, 'wpdb'), "{$port} has no WordPress object types");
    }
}

$test->finish();
