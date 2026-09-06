<?php

declare(strict_types=1);

$wordpressRoot = dirname(__DIR__, 4);
require_once $wordpressRoot . '/wp-load.php';

use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoQuestionnaire;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\PricingRuleRepository;
use AppleKlinika\Buyback\Application\Pricing\PriceBookPage;
use AppleKlinika\Buyback\Application\Pricing\RepositoryActivePriceBookResolver;
use AppleKlinika\Buyback\Application\PublicRequest\PublicBuybackRequestSubmission;
use AppleKlinika\Buyback\Application\PublicRequest\PublicBuybackSubmissionException;
use AppleKlinika\Buyback\Domain\Pricing\PricingEngine;
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
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleId;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind;
use AppleKlinika\Buyback\Domain\Pricing\RulePriority;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Domain\Shared\Money;
use AppleKlinika\Buyback\Domain\Buyback\OfferModeConfiguration;
use AppleKlinika\Buyback\Infrastructure\Identifier\WordPressRequestNumberGenerator;
use AppleKlinika\Buyback\Infrastructure\Inventory\WordPressDeviceCatalogReader;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\Schema;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressBuybackRequestMapper;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressBuybackRequestRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressDomainEventStore;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPriceBookRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPricingRuleRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPublicBuybackRequestStore;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressTransactionManager;
use AppleKlinika\Buyback\Infrastructure\Time\SystemClock;

final class PublicRequestSubmissionTest
{
    private int $assertions = 0;
    /** @var list<string> */ private array $failures = [];
    public function assert(bool $condition, string $message): void { ++$this->assertions; if (! $condition) { $this->failures[] = $message; } }
    /** @param class-string<Throwable> $expected */
    public function throws(callable $operation, string $expected, string $message): void {
        ++$this->assertions;
        try {
            $operation();
            $this->failures[] = $message . ' (no exception thrown)';
        } catch (Throwable $exception) {
            if (! $exception instanceof $expected) {
                $this->failures[] = $message . ' (unexpected ' . $exception::class . ')';
            }
        }
    }
    public function finish(): never {
        if ($this->failures !== []) { fwrite(STDERR, implode("\n", array_map(static fn (string $message): string => 'FAIL: ' . $message, $this->failures)) . "\n"); exit(1); }
        echo "Buyback public request-submission tests passed: {$this->assertions} assertions.\n"; exit(0);
    }
}

/** Test-only active-pricebook double: public request persistence still uses the real adapters. */
final class InMemorySubmissionPriceBooks implements PriceBookRepository
{
    /** @param list<PriceBook> $books */
    public function __construct(private array $books) {}
    public function createDraft(PriceBook $priceBook): PriceBook { return $priceBook; }
    public function getById(PriceBookId $id): ?PriceBook { foreach ($this->books as $book) { if ($book->id()?->equals($id)) { return $book; } } return null; }
    public function getByIdForUpdate(PriceBookId $id): ?PriceBook { return $this->getById($id); }
    public function getByVersionNumber(PriceBookVersionNumber $number): ?PriceBook { foreach ($this->books as $book) { if ($book->versionNumber()->value() === $number->value()) { return $book; } } return null; }
    public function list(int $page, int $perPage, ?PriceBookStatus $status = null): PriceBookPage { return new PriceBookPage($this->books, count($this->books), $page, $perPage); }
    public function saveDraft(PriceBook $priceBook, AggregateVersion $expectedVersion): void {}
    public function saveActivated(PriceBook $priceBook, AggregateVersion $expectedVersion): void {}
    public function saveRetired(PriceBook $priceBook, AggregateVersion $expectedVersion): void {}
    public function nextAvailableVersionNumber(): PriceBookVersionNumber { return new PriceBookVersionNumber(990099); }
    public function hasActiveBook(): bool { return $this->findCurrentActiveForCurrencyAt(new CurrencyCode('HUF'), new DateTimeImmutable()) !== []; }
    public function findCurrentActiveForCurrencyAt(CurrencyCode $currency, DateTimeImmutable $at): array { return array_values(array_filter($this->books, static fn (PriceBook $book): bool => $book->status()->isActive() && $book->currency()->code() === $currency->code())); }
    public function findCurrentActiveForCurrencyAtForUpdate(CurrencyCode $currency, DateTimeImmutable $at): array { return $this->findCurrentActiveForCurrencyAt($currency, $at); }
    public function countCurrentActiveForCurrencyAt(CurrencyCode $currency, DateTimeImmutable $at): int { return count($this->findCurrentActiveForCurrencyAt($currency, $at)); }
}

final class InMemorySubmissionPricingRules implements PricingRuleRepository
{
    /** @param array<int, list<PricingRule>> $rules */
    public function __construct(private array $rules) {}
    public function insert(PricingRule $rule): PricingRule { return $rule; }
    public function getById(PricingRuleId $id): ?PricingRule { return null; }
    public function listForPriceBook(PriceBookId $priceBookId): array { return $this->rules[$priceBookId->toInt()] ?? []; }
    public function update(PricingRule $rule, AggregateVersion $expectedVersion): void {}
    public function deleteDraftRule(PriceBookId $priceBookId, PricingRuleId $ruleId, AggregateVersion $expectedVersion): void {}
    public function isCodeUnique(PriceBookId $priceBookId, PricingRuleCode $code, ?PricingRuleId $except = null): bool { return true; }
    public function countForPriceBook(PriceBookId $priceBookId): int { return count($this->listForPriceBook($priceBookId)); }
}

function submissionFixtureBook(int $id, int $version, int $minimumOffer): PriceBook
{
    $at = new DateTimeImmutable('2026-09-05T12:00:00+00:00');
    return PriceBook::reconstitute(new PriceBookId($id), new PriceBookVersionNumber($version), 'QA public submission fixture', new PriceBookStatus(PriceBookStatus::ACTIVE), new CurrencyCode('HUF'), new Money($minimumOffer, 'HUF'), 1000, new MinimumOfferPolicy(MinimumOfferPolicy::MANUAL_REVIEW), new PricingActorId(1), new AggregateVersion(1), $at, $at, $at, null, new PricingActorId(1), null, $at);
}

function submissionBaseRule(int $id, PriceBookId $bookId, int $amount): PricingRule
{
    $at = new DateTimeImmutable('2026-09-05T12:00:00+00:00');
    return PricingRule::reconstitute(new PricingRuleId($id), $bookId, new PricingRuleDefinition(new PricingRuleCode('qa-public-submission-base-' . $id), new PricingRuleKind(PricingRuleKind::BASE_PRICE), 'iphone', 'iphone_11', new \AppleKlinika\Buyback\Domain\Pricing\StorageCapacity(128), null, null, null, null, new Money($amount, 'HUF'), null, new RulePriority(10), true, null, 'Deterministic test-only fixture'), new AggregateVersion(1), $at, $at);
}

function submissionManualRule(int $id, PriceBookId $bookId, string $conditionKey): PricingRule
{
    $at = new DateTimeImmutable('2026-09-05T12:00:00+00:00');
    return PricingRule::reconstitute(new PricingRuleId($id), $bookId, new PricingRuleDefinition(new PricingRuleCode('qa-public-submission-manual-' . $id), new PricingRuleKind(PricingRuleKind::MANUAL_REVIEW), 'iphone', null, null, null, $conditionKey, new ComparisonOperator(ComparisonOperator::EQUALS), 'damaged', null, null, new RulePriority(20), true, 'Deterministic test-only manual review', 'Deterministic test-only fixture'), new AggregateVersion(1), $at, $at);
}

global $wpdb;
$test = new PublicRequestSubmissionTest();
$tables = Schema::tableNames($wpdb);
$transactions = new WordPressTransactionManager($wpdb);
$liveBooks = new WordPressPriceBookRepository($wpdb, $transactions);
$liveRules = new WordPressPricingRuleRepository($wpdb);
$requests = new WordPressBuybackRequestRepository($wpdb, new WordPressBuybackRequestMapper());
$store = new WordPressPublicBuybackRequestStore($wpdb);
$clock = new SystemClock();
$questionnaire = new LocalDemoQuestionnaire();
$live1999 = $liveBooks->getById(new PriceBookId(1999));
$live1999Rules = $live1999 === null ? [] : $liveRules->listForPriceBook($live1999->id());
$live1999State = $live1999 === null ? null : [
    'id' => $live1999->id()?->toInt(),
    'label' => $live1999->label(),
    'status' => $live1999->status()->code(),
    'version' => $live1999->versionNumber()->value(),
    'rules_hash' => hash('sha256', serialize($live1999Rules)),
];
$test->assert($live1999State === null || ($live1999State['id'] === 1999 && $live1999State['status'] === PriceBookStatus::ACTIVE), 'Existing #1999 business pricebook is only observed, never selected as the public-submission fixture');

$automaticBook = submissionFixtureBook(990001, 990001, 10000);
$automaticRules = [
    submissionBaseRule(991001, $automaticBook->id(), 80000),
    submissionManualRule(991003, $automaticBook->id(), 'screen_condition'),
    submissionManualRule(991004, $automaticBook->id(), 'frame_condition'),
];
$fixtureBooks = new InMemorySubmissionPriceBooks([$automaticBook]);
$fixtureRules = new InMemorySubmissionPricingRules([$automaticBook->id()->toInt() => $automaticRules]);
$fixtureResolver = new RepositoryActivePriceBookResolver($fixtureBooks, $fixtureRules);
$submission = new PublicBuybackRequestSubmission(
    $fixtureResolver, new PricingEngine(), $questionnaire, $requests, $store,
    new WordPressDomainEventStore($wpdb, new WordPressBuybackRequestMapper()), new WordPressRequestNumberGenerator($requests, $clock), $transactions, $clock
);
$catalog = (new WordPressDeviceCatalogReader())->iPhoneCatalog();
$model = 'iphone_11';
$storage = 128;
$color = 'black';
$test->assert(isset($catalog[$model]['colors'][$color]), 'The active inventory supplies the iPhone 11 Black color for the isolated request fixture');
$resolved = $fixtureResolver->resolveForCurrencyAt(new CurrencyCode('HUF'), $clock->now());
$token = bin2hex(random_bytes(32));
$manualToken = bin2hex(random_bytes(32));
$belowMinimumToken = bin2hex(random_bytes(32));
$input = [
    'idempotency_token' => $token,
    'full_name' => 'QA PUBLIC REQUEST',
    'email' => 'qa-public-request@local.invalid',
    'phone' => '+36 11 111 1111',
    'customer_note' => 'isolated test',
    'privacy_acknowledged' => true,
    'terms_acknowledged' => true,
    'model_key' => $model,
    'storage_gb' => $storage,
    'color_key' => $color,
    'selected_offer_mode' => 'fast_online',
    'price_book_id' => $resolved->priceBook->id()?->toInt(),
    'price_book_version' => $resolved->priceBook->versionNumber()->value(),
    'questionnaire' => $questionnaire->defaults(),
    'privacy_url' => 'http://localhost/privacy-policy/',
    'privacy_marker' => 'qa',
];
$before = [(int) $wpdb->get_var("SELECT COUNT(*) FROM `{$tables[Schema::REQUESTS]}`"), (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$tables[Schema::SNAPSHOTS]}`"), (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$tables[Schema::EVENTS]}`"), (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order'")];
try {
    $invalidPrivacy = $input; $invalidPrivacy['privacy_acknowledged'] = false;
    try { $submission->submit($invalidPrivacy, $catalog); $test->assert(false, 'Privacy acknowledgement is required'); } catch (PublicBuybackSubmissionException) { $test->assert(true, 'Privacy acknowledgement is required'); }
    $invalidTerms = $input; $invalidTerms['terms_acknowledged'] = false;
    try { $submission->submit($invalidTerms, $catalog); $test->assert(false, 'Buyback terms acknowledgement is required'); } catch (PublicBuybackSubmissionException) { $test->assert(true, 'Buyback terms acknowledgement is required'); }
    $stale = $input; $stale['price_book_version'] = 0;
    try { $submission->submit($stale, $catalog); $test->assert(false, 'Stale price-book version is rejected'); } catch (PublicBuybackSubmissionException) { $test->assert(true, 'Stale price-book version is rejected'); }
    $created = $submission->submit($input, $catalog);
    $test->assert(! $created->alreadySubmitted, 'A valid request is created once');
    $test->assert($created->amountMinor !== null, 'The selected offer amount comes from the server calculation');
    $duplicate = $submission->submit($input, $catalog);
    $test->assert($duplicate->alreadySubmitted, 'The idempotency token prevents a duplicate request');
    $row = $store->findBySubmissionToken(hash('sha256', $token));
    $test->assert($row !== null && $row['request_number'] === $created->requestNumber, 'The persisted request is addressable by its idempotency token');
    $requestId = (int) ($row['id'] ?? 0);
    $snapshot = $wpdb->get_var($wpdb->prepare("SELECT payload_json FROM `{$tables[Schema::SNAPSHOTS]}` WHERE request_id = %d AND snapshot_type = %s", $requestId, 'public_submission'));
    $payload = is_string($snapshot) ? json_decode($snapshot, true) : null;
    $test->assert(is_array($payload) && isset($payload['offers']['fast_online'], $payload['questionnaire']['canonical_answers'], $payload['price_book']['rules_hash'], $payload['effective_rule_sources']['fast_online']) && ($payload['marketing_consent'] ?? null) === false, 'Immutable snapshot contains recalculated offers, canonical answers, rule hash, effective rule sources and an optional unchecked marketing consent');
    $test->assert((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$tables[Schema::EVENTS]}` WHERE request_id = %d", $requestId)) === 1, 'Exactly one initial event is persisted before notifications');

    $disabledFastInput = OfferModeConfiguration::defaults()->toStored()['modes'];
    $disabledFastInput['fast_online']['enabled'] = false;
    $disabledFast = OfferModeConfiguration::fromSubmitted($disabledFastInput);
    $disabledSubmission = new PublicBuybackRequestSubmission(
        $fixtureResolver, new PricingEngine(), $questionnaire, $requests, $store,
        new WordPressDomainEventStore($wpdb, new WordPressBuybackRequestMapper()), new WordPressRequestNumberGenerator($requests, $clock), $transactions, $clock, $disabledFast
    );
    $disabledAttempt = $input;
    $disabledAttempt['idempotency_token'] = bin2hex(random_bytes(32));
    $test->throws(fn () => $disabledSubmission->submit($disabledAttempt, $catalog), PublicBuybackSubmissionException::class, 'A client-forged submission using a now-disabled offer mode is rejected server-side');
    $snapshotAfterDisabledAttempt = $wpdb->get_var($wpdb->prepare("SELECT payload_json FROM `{$tables[Schema::SNAPSHOTS]}` WHERE request_id = %d AND snapshot_type = %s", $requestId, 'public_submission'));
    $test->assert($snapshotAfterDisabledAttempt === $snapshot, 'A historical request using a now-disabled mode remains readable and byte-for-byte unchanged');

    $forcedManual = $input;
    $forcedManual['idempotency_token'] = bin2hex(random_bytes(32));
    $forcedManual['selected_offer_mode'] = '';
    $forcedManual['manual_review_requested'] = true;
    try { $submission->submit($forcedManual, $catalog); $test->assert(false, 'A calculated result cannot be forged into a manual-review request'); } catch (PublicBuybackSubmissionException) { $test->assert(true, 'A calculated result cannot be forged into a manual-review request'); }

    $manualInput = $input;
    $manualInput['idempotency_token'] = $manualToken;
    $manualInput['full_name'] = 'TESZT MANUÁLIS BEVIZSGÁLÁS';
    $manualInput['email'] = 'qa-manual-review@local.invalid';
    $manualInput['selected_offer_mode'] = '';
    $manualInput['manual_review_requested'] = true;
    $manualInput['questionnaire']['liquid_exposure'] = 'yes_unknown';
    $manualInput['questionnaire']['screen_condition'] = 'damaged';
    $manualInput['questionnaire']['frame_condition'] = 'damaged';
    $manual = $submission->submit($manualInput, $catalog);
    $test->assert($manual->manualReview && $manual->serviceMode === null && $manual->amountMinor === null, 'A manual-review request has no selected offer mode and no amount');
    $manualReasons = $manual->manualReviewReasons;
    $expectedManualReasons = ['Lehetséges folyadékérintkezés', 'Törött vagy repedt kijelző', 'Sérült vagy deformált keret'];
    sort($manualReasons);
    sort($expectedManualReasons);
    $test->assert($manualReasons === $expectedManualReasons, 'The manual-review request preserves deduplicated customer-facing reasons');
    $manualDuplicate = $submission->submit($manualInput, $catalog);
    $test->assert($manualDuplicate->alreadySubmitted && $manualDuplicate->serviceMode === null, 'The manual-review idempotency token cannot create a second request');
    $manualRow = $store->findBySubmissionToken(hash('sha256', $manualToken));
    $manualId = (int) ($manualRow['id'] ?? 0);
    $manualSnapshot = $wpdb->get_var($wpdb->prepare("SELECT payload_json FROM `{$tables[Schema::SNAPSHOTS]}` WHERE request_id = %d AND snapshot_type = %s", $manualId, 'public_submission'));
    $manualPayload = is_string($manualSnapshot) ? json_decode($manualSnapshot, true) : null;
    $test->assert(is_array($manualPayload) && ($manualPayload['calculation']['status'] ?? null) === 'manual_review' && ($manualPayload['calculation_status'] ?? null) === 'manual_review' && array_key_exists('selected_offer_mode', $manualPayload) && $manualPayload['selected_offer_mode'] === null && array_key_exists('selected_amount', $manualPayload) && $manualPayload['selected_amount'] === null && array_key_exists('selected_final_amount_minor', $manualPayload) && $manualPayload['selected_final_amount_minor'] === null && ($manualPayload['manual_review_requested'] ?? false) === true && is_string($manualPayload['manual_review_requested_at'] ?? null), 'Manual-review snapshot records explicit no-offer intent, no amount and preserved state');
    $sources = is_array($manualPayload['effective_rule_sources']['fast_online'] ?? null) ? $manualPayload['effective_rule_sources']['fast_online'] : [];
    $test->assert($sources !== [] && array_diff(array_column($sources, 'source'), ['system_default', 'price_book_global', 'model_specific']) === [], 'Manual-review snapshot records the effective source and customer-facing reason for every matched rule');
    $forged = $manualInput;
    $forged['manual_review_requested'] = false;
    $forged['idempotency_token'] = bin2hex(random_bytes(32));
    try { $submission->submit($forged, $catalog); $test->assert(false, 'A manual result cannot be submitted as a calculated offer'); } catch (PublicBuybackSubmissionException) { $test->assert(true, 'A manual result cannot be submitted as a calculated offer'); }

    $belowMinimumBook = submissionFixtureBook(990002, 990002, 10000);
    $belowMinimumRules = [submissionBaseRule(991002, $belowMinimumBook->id(), 9000)];
    $belowMinimumResolver = new RepositoryActivePriceBookResolver(
        new InMemorySubmissionPriceBooks([$belowMinimumBook]),
        new InMemorySubmissionPricingRules([$belowMinimumBook->id()->toInt() => $belowMinimumRules])
    );
    $belowMinimumSubmission = new PublicBuybackRequestSubmission(
        $belowMinimumResolver, new PricingEngine(), $questionnaire, $requests, $store,
        new WordPressDomainEventStore($wpdb, new WordPressBuybackRequestMapper()), new WordPressRequestNumberGenerator($requests, $clock), $transactions, $clock
    );
    $belowMinimumInput = $input;
    $belowMinimumInput['idempotency_token'] = $belowMinimumToken;
    $belowMinimumInput['email'] = 'qa-below-minimum@local.invalid';
    $belowMinimumInput['selected_offer_mode'] = '';
    $belowMinimumInput['manual_review_requested'] = true;
    $belowMinimumInput['price_book_id'] = $belowMinimumBook->id()?->toInt();
    $belowMinimumInput['price_book_version'] = $belowMinimumBook->versionNumber()->value();
    $belowMinimum = $belowMinimumSubmission->submit($belowMinimumInput, $catalog);
    $test->assert($belowMinimum->manualReview && $belowMinimum->serviceMode === null && $belowMinimum->amountMinor === null, 'Below-minimum deterministic pricing uses the manual-review public-submission path');
    $belowMinimumRow = $store->findBySubmissionToken(hash('sha256', $belowMinimumToken));
    $belowMinimumId = (int) ($belowMinimumRow['id'] ?? 0);
    $belowMinimumSnapshot = $wpdb->get_var($wpdb->prepare("SELECT payload_json FROM `{$tables[Schema::SNAPSHOTS]}` WHERE request_id = %d AND snapshot_type = %s", $belowMinimumId, 'public_submission'));
    $belowMinimumPayload = is_string($belowMinimumSnapshot) ? json_decode($belowMinimumSnapshot, true) : null;
    $test->assert(is_array($belowMinimumPayload) && ($belowMinimumPayload['calculation_status'] ?? null) === 'manual_review' && in_array('below_minimum_offer', (array) ($belowMinimumPayload['offers']['fast_online']['reason_codes'] ?? []), true), 'Below-minimum deterministic pricing preserves the below_minimum_offer reason code');
    $test->assert((int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order'") === $before[3], 'Submitting a request creates no WooCommerce order');
} finally {
    foreach ([$token, $manualToken, $belowMinimumToken] as $cleanupToken) {
        $row = $store->findBySubmissionToken(hash('sha256', $cleanupToken));
        if ($row === null) { continue; }
        $requestId = (int) $row['id'];
        $wpdb->delete($tables[Schema::EVENTS], ['request_id' => $requestId], ['%d']);
        $wpdb->delete($tables[Schema::SNAPSHOTS], ['request_id' => $requestId], ['%d']);
        $wpdb->delete($tables[Schema::REQUESTS], ['id' => $requestId], ['%d']);
    }
}
$after = [(int) $wpdb->get_var("SELECT COUNT(*) FROM `{$tables[Schema::REQUESTS]}`"), (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$tables[Schema::SNAPSHOTS]}`"), (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$tables[Schema::EVENTS]}`")];
$test->assert($before[0] === $after[0] && $before[1] === $after[1] && $before[2] === $after[2], 'Isolated fixtures are fully cleaned up');
$live1999After = $liveBooks->getById(new PriceBookId(1999));
$live1999AfterRules = $live1999After === null ? [] : $liveRules->listForPriceBook($live1999After->id());
$live1999AfterState = $live1999After === null ? null : [
    'id' => $live1999After->id()?->toInt(),
    'label' => $live1999After->label(),
    'status' => $live1999After->status()->code(),
    'version' => $live1999After->versionNumber()->value(),
    'rules_hash' => hash('sha256', serialize($live1999AfterRules)),
];
$test->assert($live1999State === $live1999AfterState, 'Existing #1999 business pricebook remains byte-for-byte unchanged by the isolated public-submission fixture');
$test->finish();
