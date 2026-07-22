<?php

declare(strict_types=1);

$wordpressRoot = dirname(__DIR__, 4);
require_once $wordpressRoot . '/wp-load.php';

use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoQuestionnaire;
use AppleKlinika\Buyback\Application\Pricing\RepositoryActivePriceBookResolver;
use AppleKlinika\Buyback\Application\PublicRequest\PublicBuybackRequestSubmission;
use AppleKlinika\Buyback\Application\PublicRequest\PublicBuybackSubmissionException;
use AppleKlinika\Buyback\Domain\Pricing\PricingEngine;
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
    $test->assert(is_array($payload) && isset($payload['offers']['fast_online'], $payload['questionnaire']['canonical_answers'], $payload['price_book']['rules_hash']), 'Immutable snapshot contains recalculated offers, canonical answers and rule hash');
    $test->assert((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$tables[Schema::EVENTS]}` WHERE request_id = %d", $requestId)) === 1, 'Exactly one initial event is persisted before notifications');
    $test->assert((int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order'") === $before[3], 'Submitting a request creates no WooCommerce order');
} finally {
    $row = $store->findBySubmissionToken(hash('sha256', $token));
    if ($row !== null) {
        $requestId = (int) $row['id'];
        $wpdb->delete($tables[Schema::EVENTS], ['request_id' => $requestId], ['%d']);
        $wpdb->delete($tables[Schema::SNAPSHOTS], ['request_id' => $requestId], ['%d']);
        $wpdb->delete($tables[Schema::REQUESTS], ['id' => $requestId], ['%d']);
    }
}
$after = [(int) $wpdb->get_var("SELECT COUNT(*) FROM `{$tables[Schema::REQUESTS]}`"), (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$tables[Schema::SNAPSHOTS]}`"), (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$tables[Schema::EVENTS]}`")];
$test->assert($before[0] === $after[0] && $before[1] === $after[1] && $before[2] === $after[2], 'Isolated fixtures are fully cleaned up');
$test->finish();
