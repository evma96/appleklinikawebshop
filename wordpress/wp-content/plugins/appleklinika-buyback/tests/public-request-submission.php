<?php

declare(strict_types=1);

$wordpressRoot = dirname(__DIR__, 4);
require_once $wordpressRoot . '/wp-load.php';

use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoQuestionnaire;
use AppleKlinika\Buyback\Application\Pricing\RepositoryActivePriceBookResolver;
use AppleKlinika\Buyback\Application\PublicRequest\PublicBuybackRequestSubmission;
use AppleKlinika\Buyback\Application\PublicRequest\PublicBuybackSubmissionException;
use AppleKlinika\Buyback\Domain\Pricing\PricingEngine;
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

global $wpdb;
$test = new PublicRequestSubmissionTest();
$tables = Schema::tableNames($wpdb);
$transactions = new WordPressTransactionManager($wpdb);
$books = new WordPressPriceBookRepository($wpdb, $transactions);
$rules = new WordPressPricingRuleRepository($wpdb);
$requests = new WordPressBuybackRequestRepository($wpdb, new WordPressBuybackRequestMapper());
$store = new WordPressPublicBuybackRequestStore($wpdb);
$clock = new SystemClock();
$questionnaire = new LocalDemoQuestionnaire();
$submission = new PublicBuybackRequestSubmission(
    new RepositoryActivePriceBookResolver($books, $rules), new PricingEngine(), $questionnaire, $requests, $store,
    new WordPressDomainEventStore($wpdb, new WordPressBuybackRequestMapper()), new WordPressRequestNumberGenerator($requests, $clock), $transactions, $clock
);
$catalog = (new WordPressDeviceCatalogReader())->iPhoneCatalog();
$model = 'iphone_11';
$storage = 128;
$color = 'black';
$test->assert(isset($catalog[$model]['colors'][$color]), 'The active inventory supplies the iPhone 11 Black color for the isolated request fixture');
$resolved = (new RepositoryActivePriceBookResolver($books, $rules))->resolveForCurrencyAt(new AppleKlinika\Buyback\Domain\Pricing\CurrencyCode('HUF'), $clock->now());
$token = bin2hex(random_bytes(32));
$manualToken = bin2hex(random_bytes(32));
$input = [
    'idempotency_token' => $token,
    'full_name' => 'QA PUBLIC REQUEST',
    'email' => 'qa-public-request@local.invalid',
    'phone' => '+36 11 111 1111',
    'customer_note' => 'isolated test',
    'privacy_acknowledged' => true,
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
    $test->assert(is_array($payload) && isset($payload['offers']['fast_online'], $payload['questionnaire']['canonical_answers'], $payload['price_book']['rules_hash'], $payload['effective_rule_sources']['fast_online']), 'Immutable snapshot contains recalculated offers, canonical answers, rule hash and effective rule sources');
    $test->assert((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$tables[Schema::EVENTS]}` WHERE request_id = %d", $requestId)) === 1, 'Exactly one initial event is persisted before notifications');

    $disabledFastInput = OfferModeConfiguration::defaults()->toStored()['modes'];
    $disabledFastInput['fast_online']['enabled'] = false;
    $disabledFast = OfferModeConfiguration::fromSubmitted($disabledFastInput);
    $disabledSubmission = new PublicBuybackRequestSubmission(
        new RepositoryActivePriceBookResolver($books, $rules), new PricingEngine(), $questionnaire, $requests, $store,
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
    $test->assert($manual->manualReviewReasons === ['Lehetséges folyadékérintkezés', 'Törött vagy repedt kijelző', 'Sérült vagy deformált keret'], 'The manual-review request preserves deduplicated customer-facing reasons');
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
    $test->assert((int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order'") === $before[3], 'Submitting a request creates no WooCommerce order');
} finally {
    foreach ([$token, $manualToken] as $cleanupToken) {
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
$test->finish();
