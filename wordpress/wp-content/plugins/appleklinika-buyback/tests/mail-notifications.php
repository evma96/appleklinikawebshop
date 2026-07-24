<?php

declare(strict_types=1);

$wordpressRoot = dirname(__DIR__, 4);
require_once $wordpressRoot . '/wp-load.php';

use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoQuestionnaire;
use AppleKlinika\Buyback\Application\Port\BuybackRequestMailer;
use AppleKlinika\Buyback\Application\Pricing\RepositoryActivePriceBookResolver;
use AppleKlinika\Buyback\Application\PublicRequest\DispatchBuybackRequestNotifications;
use AppleKlinika\Buyback\Application\PublicRequest\PublicBuybackRequestSubmission;
use AppleKlinika\Buyback\Application\PublicRequest\PublicBuybackSubmissionResult;
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
use AppleKlinika\Buyback\Infrastructure\WordPress\BuybackSmtpConfiguration;
use AppleKlinika\Buyback\Infrastructure\WordPress\WordPressBuybackRequestMailer;

final class BuybackMailNotificationTest
{
    private int $assertions = 0;
    /** @var list<string> */ private array $failures = [];
    public function assert(bool $condition, string $message): void { ++$this->assertions; if (! $condition) { $this->failures[] = $message; } }
    public function finish(): never { if ($this->failures !== []) { fwrite(STDERR, implode("\n", array_map(static fn (string $message): string => 'FAIL: ' . $message, $this->failures)) . "\n"); exit(1); } echo "Buyback mail-notification tests passed: {$this->assertions} assertions.\n"; exit(0); }
}

final class FakeBuybackRequestMailer implements BuybackRequestMailer
{
    public int $customerCalls = 0;
    public int $adminCalls = 0;
    public function __construct(private readonly bool $result) {}
    public function sendCustomer(PublicBuybackSubmissionResult $result, array $input): bool { ++$this->customerCalls; return $this->result; }
    public function sendAdmin(PublicBuybackSubmissionResult $result, array $input): bool { ++$this->adminCalls; return $this->result; }
}

global $wpdb;
$test = new BuybackMailNotificationTest();
$syntheticPassword = str_repeat('x', 24);
$complete = new BuybackSmtpConfiguration([
    'BUYBACK_SMTP_HOST' => 'smtp.example.test', 'BUYBACK_SMTP_PORT' => '587', 'BUYBACK_SMTP_ENCRYPTION' => 'tls', 'BUYBACK_SMTP_USERNAME' => 'mailer@example.test', 'BUYBACK_SMTP_PASSWORD' => $syntheticPassword, 'BUYBACK_MAIL_FROM' => 'buyback@example.test', 'BUYBACK_MAIL_FROM_NAME' => 'Apple Klinika', 'BUYBACK_ADMIN_EMAIL' => 'admin@example.test',
]);
$test->assert($complete->isConfigured(), 'Complete environment-only SMTP configuration is accepted');
$diagnostics = $complete->diagnostics();
$test->assert(! str_contains((string) wp_json_encode($diagnostics), $syntheticPassword), 'SMTP password is never exposed by diagnostics');
$test->assert($diagnostics['username'] !== 'mailer@example.test' && str_contains($diagnostics['username'], '•'), 'SMTP username is masked in diagnostics');
$missing = new BuybackSmtpConfiguration([]);
$test->assert(! $missing->isConfigured() && in_array('BUYBACK_SMTP_PASSWORD', $missing->missing(), true), 'Missing SMTP configuration is detected without a fallback transport');

$captured = [];
$capture = static function ($pre, array $args) use (&$captured): bool { $captured[] = $args; return true; };
add_filter('pre_wp_mail', $capture, 10, 2);
$mailer = new WordPressBuybackRequestMailer($complete);
$notificationResult = new PublicBuybackSubmissionResult('AKB-TEST-UTF8', 'iPhone 11 · 128 GB · Fekete', 'fast_online', 59000, false);
$input = ['idempotency_token' => str_repeat('a', 64), 'full_name' => 'Teszt Elek', 'email' => 'customer@example.test', 'phone' => '+36 30 123 4567'];
$test->assert($mailer->sendCustomer($notificationResult, $input), 'Customer payload is accepted through wp_mail interception');
$test->assert($mailer->sendAdmin($notificationResult, $input), 'Admin payload is accepted through wp_mail interception');
remove_filter('pre_wp_mail', $capture, 10);
$test->assert(count($captured) === 2, 'Automated mail test sends no real email');
$customer = $captured[0] ?? [];
$admin = $captured[1] ?? [];
$test->assert(($customer['to'] ?? '') === 'customer@example.test' && str_contains((string) ($customer['subject'] ?? ''), 'AKB-TEST-UTF8') && str_contains((string) ($customer['message'] ?? ''), 'Megkaptuk felvásárlási igényedet'), 'Customer payload has Hungarian content and request reference');
$test->assert(($admin['to'] ?? '') === 'admin@example.test' && str_contains((string) ($admin['subject'] ?? ''), 'AKB-TEST-UTF8') && str_contains((string) ($admin['message'] ?? ''), 'Teszt Elek'), 'Admin payload has the configured recipient and request context');
$customerHeaders = implode("\n", (array) ($customer['headers'] ?? []));
$adminHeaders = implode("\n", (array) ($admin['headers'] ?? []));
$test->assert(str_contains($customerHeaders, 'From: Apple Klinika <buyback@example.test>') && str_contains($customerHeaders, 'Reply-To: admin@example.test') && str_contains($customerHeaders, 'charset=UTF-8'), 'Customer From, Reply-To and UTF-8 headers are correct');
$test->assert(str_contains($adminHeaders, 'From: Apple Klinika <buyback@example.test>') && str_contains($adminHeaders, 'Reply-To: Teszt Elek <customer@example.test>'), 'Admin From and customer Reply-To headers are correct');

$manualCaptured = [];
$manualCapture = static function ($pre, array $args) use (&$manualCaptured): bool { $manualCaptured[] = $args; return true; };
add_filter('pre_wp_mail', $manualCapture, 10, 2);
$manualResult = new PublicBuybackSubmissionResult('AKB-TEST-MANUAL', 'iPhone 11 · 128 GB · Fekete', null, null, true, false, ['Folyadékkár gyanúja', 'Sérült kijelző']);
$test->assert($mailer->sendCustomer($manualResult, $input) && $mailer->sendAdmin($manualResult, $input), 'Manual-review notification payloads are accepted through wp_mail interception');
remove_filter('pre_wp_mail', $manualCapture, 10);
$manualCustomer = $manualCaptured[0] ?? [];
$manualAdmin = $manualCaptured[1] ?? [];
$test->assert(str_contains((string) ($manualCustomer['message'] ?? ''), 'Személyes bevizsgálás szükséges') && ! str_contains((string) ($manualCustomer['message'] ?? ''), 'Választott lehetőség:'), 'Manual customer email has no selected offer mode or amount');
$test->assert(str_contains((string) ($manualAdmin['message'] ?? ''), 'Előzetes összeg: nincs') && str_contains((string) ($manualAdmin['message'] ?? ''), 'Sérült kijelző'), 'Manual admin email clearly shows the inspection intent and reasons');

$tables = Schema::tableNames($wpdb);
$transactions = new WordPressTransactionManager($wpdb);
$books = new WordPressPriceBookRepository($wpdb, $transactions);
$rules = new WordPressPricingRuleRepository($wpdb);
$requests = new WordPressBuybackRequestRepository($wpdb, new WordPressBuybackRequestMapper());
$store = new WordPressPublicBuybackRequestStore($wpdb);
$clock = new SystemClock();
$questionnaire = new LocalDemoQuestionnaire();
$submission = new PublicBuybackRequestSubmission(new RepositoryActivePriceBookResolver($books, $rules), new PricingEngine(), $questionnaire, $requests, $store, new WordPressDomainEventStore($wpdb, new WordPressBuybackRequestMapper()), new WordPressRequestNumberGenerator($requests, $clock), $transactions, $clock);
$catalog = (new WordPressDeviceCatalogReader())->iPhoneCatalog();
$resolved = (new RepositoryActivePriceBookResolver($books, $rules))->resolveForCurrencyAt(new AppleKlinika\Buyback\Domain\Pricing\CurrencyCode('HUF'), $clock->now());
$beforeOrders = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order'");
$createdIds = [];
$newInput = static function (string $token) use ($questionnaire, $resolved): array { return ['idempotency_token' => $token, 'full_name' => 'QA MAIL', 'email' => 'qa-mail@local.invalid', 'phone' => '+36 11 111 1111', 'customer_note' => '', 'privacy_acknowledged' => true, 'model_key' => 'iphone_11', 'storage_gb' => 128, 'color_key' => 'black', 'selected_offer_mode' => 'fast_online', 'price_book_id' => $resolved->priceBook->id()?->toInt(), 'price_book_version' => $resolved->priceBook->versionNumber()->value(), 'questionnaire' => $questionnaire->defaults(), 'privacy_url' => 'http://localhost/privacy-policy/', 'privacy_marker' => 'qa']; };
try {
    $successInput = $newInput(bin2hex(random_bytes(32)));
    $success = $submission->submit($successInput, $catalog);
    $successRow = $store->findBySubmissionToken(hash('sha256', $successInput['idempotency_token']));
    $createdIds[] = (int) $successRow['id'];
    $successMailer = new FakeBuybackRequestMailer(true);
    $successDispatcher = new DispatchBuybackRequestNotifications($successMailer, $store, $clock);
    $successDispatcher->dispatch($success, $successInput);
    $successDispatcher->dispatch($submission->submit($successInput, $catalog), $successInput);
    $successEvents = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$tables[Schema::EVENTS]}` WHERE request_id = %d AND event_type IN ('mail_customer_sent', 'mail_admin_sent')", (int) $successRow['id']));
    $test->assert($successEvents === 2 && $successMailer->customerCalls === 1 && $successMailer->adminCalls === 1, 'Repeated handling creates neither duplicate mail deliveries nor duplicate event rows');

    $failedInput = $newInput(bin2hex(random_bytes(32)));
    $failed = $submission->submit($failedInput, $catalog);
    $failedRow = $store->findBySubmissionToken(hash('sha256', $failedInput['idempotency_token']));
    $createdIds[] = (int) $failedRow['id'];
    (new DispatchBuybackRequestNotifications(new FakeBuybackRequestMailer(false), $store, $clock))->dispatch($failed, $failedInput);
    $failedEvents = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$tables[Schema::EVENTS]}` WHERE request_id = %d AND event_type IN ('mail_customer_failed', 'mail_admin_failed')", (int) $failedRow['id']));
    $snapshotCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$tables[Schema::SNAPSHOTS]}` WHERE request_id = %d", (int) $failedRow['id']));
    $test->assert($failedEvents === 2 && $snapshotCount === 1, 'Failed delivery records failures and preserves the request snapshot');
    $test->assert((int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order'") === $beforeOrders, 'Mail handling creates no WooCommerce order');
} finally {
    foreach ($createdIds as $id) { $wpdb->delete($tables[Schema::EVENTS], ['request_id' => $id], ['%d']); $wpdb->delete($tables[Schema::SNAPSHOTS], ['request_id' => $id], ['%d']); $wpdb->delete($tables[Schema::REQUESTS], ['id' => $id], ['%d']); }
}
$test->finish();
