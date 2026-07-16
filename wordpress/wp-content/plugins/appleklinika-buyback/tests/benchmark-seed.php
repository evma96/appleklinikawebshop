<?php

declare(strict_types=1);

$wordpressRoot = dirname(__DIR__, 4);
require_once $wordpressRoot . '/wp-load.php';

use AppleKlinika\Buyback\Application\Benchmark\BenchmarkPriceBookSeedService;
use AppleKlinika\Buyback\Application\Exception\BenchmarkSeedConflictException;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\DeviceCatalogReader;
use AppleKlinika\Buyback\Application\Port\PricingRuleRepository;
use AppleKlinika\Buyback\Application\Pricing\DeviceCatalogItem;
use AppleKlinika\Buyback\Domain\Benchmark\BenchmarkEvidencePolicy;
use AppleKlinika\Buyback\Domain\Benchmark\BenchmarkManifest;
use AppleKlinika\Buyback\Domain\Benchmark\BenchmarkMath;
use AppleKlinika\Buyback\Domain\Benchmark\BenchmarkModelAliasNormalizer;
use AppleKlinika\Buyback\Domain\Benchmark\BenchmarkSourceSnapshotValidator;
use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PricingRule;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleCode;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleId;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Infrastructure\Benchmark\FileBenchmarkManifestLoader;
use AppleKlinika\Buyback\Infrastructure\Benchmark\WordPressBenchmarkSeedRegistry;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\Schema;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPriceBookRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPricingRuleRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressTransactionManager;

final class BenchmarkSeedTestRunner
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
    public function throws(callable $operation, string $expected, string $message): void
    {
        ++$this->assertions;
        try {
            $operation();
            $this->failures[] = $message . ' (no exception thrown)';
        } catch (Throwable $exception) {
            if (! $exception instanceof $expected) {
                $this->failures[] = sprintf('%s (expected %s, received %s: %s)', $message, $expected, $exception::class, $exception->getMessage());
            }
        }
    }

    public function fail(Throwable $exception): void
    {
        $this->failures[] = $exception::class . ': ' . $exception->getMessage();
    }

    /** @param array<string, int> $before @param array<string, int> $after */
    public function finish(array $before, array $after, int $activeBefore, int $activeAfter, string $book31Before, string $book31After): never
    {
        if ($this->failures !== []) {
            foreach ($this->failures as $failure) {
                fwrite(STDERR, "FAIL: {$failure}\n");
            }
            fwrite(STDERR, sprintf("%d assertion(s), %d failure(s).\n", $this->assertions, count($this->failures)));
            exit(1);
        }

        echo sprintf(
            "Buyback benchmark-seed tests passed: %d assertions; rows before/after books %d/%d, rules %d/%d, requests %d/%d, snapshots %d/%d, events %d/%d; active HUF %d/%d; price book 31 %s.\n",
            $this->assertions,
            $before[Schema::PRICE_BOOKS], $after[Schema::PRICE_BOOKS],
            $before[Schema::PRICE_RULES], $after[Schema::PRICE_RULES],
            $before[Schema::REQUESTS], $after[Schema::REQUESTS],
            $before[Schema::SNAPSHOTS], $after[Schema::SNAPSHOTS],
            $before[Schema::EVENTS], $after[Schema::EVENTS],
            $activeBefore, $activeAfter,
            $book31Before === $book31After ? 'unchanged' : 'changed'
        );
        exit(0);
    }
}

final class BenchmarkSeedFixedClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-16T12:00:00Z');
    }
}

final class BenchmarkSeedCatalog implements DeviceCatalogReader
{
    public function iPhoneModels(): array
    {
        return [
            new DeviceCatalogItem('iphone_13_pro', 'iPhone 13 Pro'),
            new DeviceCatalogItem('iphone_xr', 'iPhone XR'),
        ];
    }
}

final class BenchmarkSeedFailingRuleRepository implements PricingRuleRepository
{
    private int $insertCount = 0;

    public function __construct(private readonly PricingRuleRepository $delegate)
    {
    }

    public function insert(PricingRule $rule): PricingRule
    {
        ++$this->insertCount;
        if ($this->insertCount === 2) {
            throw new RuntimeException('Forced benchmark transaction failure.');
        }
        return $this->delegate->insert($rule);
    }

    public function getById(PricingRuleId $id): ?PricingRule { return $this->delegate->getById($id); }
    public function listForPriceBook(PriceBookId $priceBookId): array { return $this->delegate->listForPriceBook($priceBookId); }
    public function update(PricingRule $rule, AggregateVersion $expectedVersion): void { $this->delegate->update($rule, $expectedVersion); }
    public function deleteDraftRule(PriceBookId $priceBookId, PricingRuleId $ruleId, AggregateVersion $expectedVersion): void { $this->delegate->deleteDraftRule($priceBookId, $ruleId, $expectedVersion); }
    public function isCodeUnique(PriceBookId $priceBookId, PricingRuleCode $code, ?PricingRuleId $except = null): bool { return $this->delegate->isCodeUnique($priceBookId, $code, $except); }
    public function countForPriceBook(PriceBookId $priceBookId): int { return $this->delegate->countForPriceBook($priceBookId); }
}

/** @return array<string, mixed> */
function benchmarkSeedSourceSnapshot(): array
{
    return [
        'source' => 'QA source',
        'source_urls' => ['https://example.invalid/public-buyback'],
        'captured_at' => '2026-07-16T10:00:00Z',
        'capture_method' => 'local_fixture',
        'source_page_marker' => ['marker' => 'qa'],
        'supported_device_categories' => ['iphone'],
        'iphone_models' => [],
        'configuration_options' => [],
        'condition_question_tree' => [],
        'payout_modes' => [],
        'handover_modes' => [],
        'offer_semantics' => [],
        'raw_reference_observations' => [],
        'unsupported_or_inaccessible_paths' => [],
        'evidence_and_confidence_notes' => ['fixture' => true],
    ];
}

/** @return array<string, mixed> */
function benchmarkSeedManifestData(string $seedKey, string $label, string $sourceHash, int $baseAmount = 200000): array
{
    $baseEvidence = [
        'confidence' => 'high',
        'observations' => ['rejoy-reference', 'showme-reference'],
        'sources' => ['Rejoy', 'ShowMe'],
        'models' => ['iphone_13_pro'],
    ];
    return [
        'schema_version' => BenchmarkManifest::SCHEMA_VERSION,
        'manifest_version' => 'qa-benchmark-1.0.0',
        'generated_at' => '2026-07-16T11:00:00Z',
        'generator_methodology_version' => 'qa-methodology-1',
        'source_snapshots' => [
            ['path' => 'sources/source.json', 'sha256' => $sourceHash],
        ],
        'price_book' => [
            'seed_key' => $seedKey,
            'label' => $label,
            'currency' => 'HUF',
            'status' => 'draft',
            'rounding_increment_minor' => 1000,
            'minimum_offer_minor' => 10000,
            'minimum_policy' => 'manual_review',
        ],
        'rules' => [
            [
                'rule_code' => 'qa-base-iphone-13-pro-128', 'rule_kind' => 'base_price', 'category' => 'iphone',
                'model_key' => 'iphone_13_pro', 'storage_gb' => 128, 'amount_minor' => $baseAmount,
                'priority' => 10, 'enabled' => true, 'evidence' => $baseEvidence,
            ],
            [
                'rule_code' => 'qa-base-iphone-xr-64', 'rule_kind' => 'base_price', 'category' => 'iphone',
                'model_key' => 'iphone_xr', 'storage_gb' => 64, 'amount_minor' => 60000,
                'priority' => 11, 'enabled' => true, 'evidence' => array_replace($baseEvidence, ['models' => ['iphone_xr']]),
            ],
            [
                'rule_code' => 'qa-mode-higher', 'rule_kind' => 'mode_adjustment', 'category' => 'iphone',
                'service_mode' => 'higher_offer', 'multiplier_bps' => 10000,
                'priority' => 20, 'enabled' => true,
                'evidence' => ['confidence' => 'high', 'observations' => ['mode-1', 'mode-2'], 'sources' => ['Rejoy']],
            ],
            [
                'rule_code' => 'qa-battery-low', 'rule_kind' => 'fixed_deduction', 'category' => 'iphone',
                'condition_key' => 'battery_health', 'comparison_operator' => 'less_than', 'comparison_value' => 80,
                'amount_minor' => 15000, 'priority' => 30, 'enabled' => true,
                'evidence' => ['confidence' => 'high', 'observations' => ['battery-1', 'battery-2'], 'sources' => ['Rejoy', 'ShowMe'], 'models' => ['iphone_13_pro', 'iphone_xr']],
            ],
            [
                'rule_code' => 'qa-liquid-review', 'rule_kind' => 'manual_review', 'category' => 'iphone',
                'condition_key' => 'liquid_damage', 'comparison_operator' => 'equals', 'comparison_value' => true,
                'priority' => 40, 'enabled' => true, 'public_label' => 'Folyadékkár ellenőrzése',
                'evidence' => ['confidence' => 'low', 'observations' => ['manual-1'], 'sources' => ['Rejoy']],
            ],
        ],
    ];
}

/** @param array<string, mixed> $data */
function benchmarkSeedWriteJson(string $path, array $data): void
{
    $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($path, $json . "\n") === false) {
        throw new RuntimeException("Could not write QA fixture {$path}.");
    }
}

/** @return array<string, int> */
function benchmarkSeedCounts(wpdb $database, array $tables): array
{
    $counts = [];
    foreach ($tables as $key => $table) {
        $counts[$key] = (int) $database->get_var("SELECT COUNT(*) FROM `{$table}`");
    }
    return $counts;
}

function benchmarkSeedActiveHufCount(wpdb $database, array $tables): int
{
    return (int) $database->get_var($database->prepare(
        "SELECT COUNT(*) FROM `{$tables[Schema::PRICE_BOOKS]}` WHERE status = %s AND currency = %s",
        'active',
        'HUF'
    ));
}

function benchmarkSeedBook31Hash(wpdb $database, array $tables): string
{
    $book = $database->get_row($database->prepare("SELECT * FROM `{$tables[Schema::PRICE_BOOKS]}` WHERE id = %d", 31), ARRAY_A);
    $rules = $database->get_results($database->prepare("SELECT * FROM `{$tables[Schema::PRICE_RULES]}` WHERE price_book_id = %d ORDER BY id", 31), ARRAY_A);
    return hash('sha256', serialize([$book, $rules]));
}

function benchmarkSeedOptionName(string $seedKey): string
{
    return 'appleklinika_buyback_seed_' . hash('sha256', $seedKey);
}

function benchmarkSeedCleanup(wpdb $database, array $tables, string $labelPrefix, array $seedKeys): void
{
    $ids = $database->get_col($database->prepare(
        "SELECT id FROM `{$tables[Schema::PRICE_BOOKS]}` WHERE label LIKE %s",
        $database->esc_like($labelPrefix) . '%'
    ));
    $ids = array_map('intval', is_array($ids) ? $ids : []);
    if ($ids !== []) {
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $database->query($database->prepare("DELETE FROM `{$tables[Schema::PRICE_RULES]}` WHERE price_book_id IN ({$placeholders})", ...$ids));
        $database->query($database->prepare("DELETE FROM `{$tables[Schema::PRICE_BOOKS]}` WHERE id IN ({$placeholders})", ...$ids));
    }
    foreach ($seedKeys as $seedKey) {
        $database->delete($database->options, ['option_name' => benchmarkSeedOptionName($seedKey)], ['%s']);
    }
}

function benchmarkSeedRemoveFixtureDirectory(string $directory): void
{
    foreach ([$directory . '/manifest.json', $directory . '/conflict.json', $directory . '/failure.json', $directory . '/sources/source.json'] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    if (is_dir($directory . '/sources')) {
        rmdir($directory . '/sources');
    }
    if (is_dir($directory)) {
        rmdir($directory);
    }
}

global $wpdb;
$test = new BenchmarkSeedTestRunner();
$tables = Schema::tableNames($wpdb);
$token = strtolower(bin2hex(random_bytes(4)));
$labelPrefix = 'QA-BENCHMARK-SEED-' . $token;
$seedKey = 'qa-benchmark-' . $token;
$failureSeedKey = $seedKey . '-rollback';
$directory = sys_get_temp_dir() . '/ak-benchmark-seed-' . $token;
$sourceDirectory = $directory . '/sources';
if (! mkdir($sourceDirectory, 0700, true) && ! is_dir($sourceDirectory)) {
    throw new RuntimeException('Could not create benchmark fixture directory.');
}

$sourcePath = $sourceDirectory . '/source.json';
$manifestPath = $directory . '/manifest.json';
$conflictPath = $directory . '/conflict.json';
$failurePath = $directory . '/failure.json';
benchmarkSeedWriteJson($sourcePath, benchmarkSeedSourceSnapshot());
$sourceHash = hash_file('sha256', $sourcePath);
$manifestData = benchmarkSeedManifestData($seedKey, $labelPrefix, $sourceHash);
benchmarkSeedWriteJson($manifestPath, $manifestData);
$conflictData = benchmarkSeedManifestData($seedKey, $labelPrefix, $sourceHash, 201000);
benchmarkSeedWriteJson($conflictPath, $conflictData);
$failureData = benchmarkSeedManifestData($failureSeedKey, $labelPrefix . '-ROLLBACK', $sourceHash);
benchmarkSeedWriteJson($failurePath, $failureData);

$before = benchmarkSeedCounts($wpdb, $tables);
$activeBefore = benchmarkSeedActiveHufCount($wpdb, $tables);
$book31Before = benchmarkSeedBook31Hash($wpdb, $tables);
$transactions = new WordPressTransactionManager($wpdb);
$books = new WordPressPriceBookRepository($wpdb, $transactions);
$rules = new WordPressPricingRuleRepository($wpdb);
$registry = new WordPressBenchmarkSeedRegistry($wpdb);
$loader = new FileBenchmarkManifestLoader(new BenchmarkSourceSnapshotValidator());
$service = new BenchmarkPriceBookSeedService($books, $rules, new BenchmarkSeedCatalog(), $registry, $transactions, new BenchmarkSeedFixedClock());
$createdBookId = null;

try {
    $test->assert(APPLEKLINIKA_BUYBACK_VERSION === '0.8.0', 'Plugin version is 0.8.0');
    $test->assert(APPLEKLINIKA_BUYBACK_SCHEMA_VERSION === '1.1.0', 'Schema code remains 1.1.0');
    $test->assert((string) get_option(Schema::OPTION_SCHEMA_VERSION) === '1.1.0', 'Installed schema remains 1.1.0');
    $test->assert($wpdb->get_var($wpdb->prepare("SELECT label FROM `{$tables[Schema::PRICE_BOOKS]}` WHERE id = %d", 31)) === 'noj', 'Protected price book 31 exists with the expected label');

    $validator = new BenchmarkSourceSnapshotValidator();
    $validator->validate(benchmarkSeedSourceSnapshot());
    $sensitive = benchmarkSeedSourceSnapshot();
    $sensitive['token'] = 'forbidden';
    $test->throws(fn () => $validator->validate($sensitive), InvalidValueObjectException::class, 'Sensitive source snapshot keys are rejected');

    $aliases = new BenchmarkModelAliasNormalizer([
        'iphone_13_pro' => ['Apple iPhone 13 Pro', 'iPhone 13 Pro 128 GB'],
        'iphone_xr' => ['Apple iPhone XR'],
    ]);
    $test->assert($aliases->resolve('Apple iPhone 13 Pro 128 GB') === 'iphone_13_pro', 'Known model alias resolves');
    $test->assert($aliases->resolve('Unknown iPhone') === null, 'Unknown model alias remains unmapped');
    $test->throws(fn () => new BenchmarkModelAliasNormalizer(['a' => ['Same'], 'b' => ['Same']]), InvalidValueObjectException::class, 'Ambiguous aliases are rejected');

    $test->assert(BenchmarkMath::median([100000, 120000, 110000]) === 110000, 'Odd median is deterministic');
    $test->assert(BenchmarkMath::median([100000, 120000]) === 110000.0, 'Even median is deterministic');
    $test->assert(BenchmarkMath::roundHalfUp(110500, 1000) === 111000, 'Benchmark amount rounds half up');
    $test->assert(BenchmarkMath::medianRatioBasisPoints([
        ['mode_amount_minor' => 90000, 'reference_amount_minor' => 100000],
        ['mode_amount_minor' => 85000, 'reference_amount_minor' => 100000],
    ]) === 8750, 'Mode-ratio median is deterministic');

    $test->assert(BenchmarkEvidencePolicy::basePriceEligible(['confidence' => 'high', 'observations' => ['a', 'b'], 'sources' => ['A', 'B']]), 'Two independent observations pass base-price evidence');
    $test->assert(! BenchmarkEvidencePolicy::basePriceEligible(['confidence' => 'high', 'observations' => ['a'], 'sources' => ['A']]), 'Single-source observation fails base-price evidence');
    $test->assert(! BenchmarkEvidencePolicy::monetaryConditionEligible(['confidence' => 'low', 'observations' => ['a', 'b'], 'sources' => ['A', 'B'], 'models' => ['x', 'y']]), 'Low-confidence monetary rule is excluded');

    $duplicate = $manifestData;
    $duplicateRule = $duplicate['rules'][0];
    $duplicateRule['rule_code'] = 'qa-duplicate-base';
    $duplicate['rules'][] = $duplicateRule;
    $test->throws(fn () => BenchmarkManifest::fromArray($duplicate), InvalidValueObjectException::class, 'Duplicate model/storage base is rejected');
    $lowConfidence = $manifestData;
    $lowConfidence['rules'][3]['evidence']['confidence'] = 'low';
    $test->throws(fn () => BenchmarkManifest::fromArray($lowConfidence), InvalidValueObjectException::class, 'Low-confidence monetary condition is rejected');

    $manifest = $loader->load($manifestPath);
    $test->assert(count($manifest->configurationKeys()) === 2, 'Manifest contains two unique base configurations');
    $sourceOriginal = file_get_contents($sourcePath);
    file_put_contents($sourcePath, $sourceOriginal . "\n");
    $test->throws(fn () => $loader->load($manifestPath), InvalidValueObjectException::class, 'Source checksum mismatch is rejected');
    file_put_contents($sourcePath, $sourceOriginal);

    $countsBeforePlan = benchmarkSeedCounts($wpdb, $tables);
    $plan = $service->plan($manifest);
    $test->assert($plan->existingPriceBookId === null && $plan->totalRuleCount === 5, 'Dry-run plan reports exact writes without creating a draft');
    $test->assert(benchmarkSeedCounts($wpdb, $tables) === $countsBeforePlan, 'Dry-run plan performs no database writes');

    $adminIds = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $actorId = is_array($adminIds) && $adminIds !== [] ? (int) $adminIds[0] : 0;
    $test->assert($actorId > 0, 'An existing administrator is available for the QA draft actor');
    $created = $service->createDraft($manifest, $actorId);
    $createdBookId = $created->priceBookId;
    $test->assert($created->created && $createdBookId > 31, 'Transactional seed creates one new QA draft');
    $createdBook = $books->getById(new PriceBookId($createdBookId));
    $test->assert($createdBook !== null && $createdBook->status()->isDraft(), 'Seeded price book remains draft');
    $test->assert($rules->countForPriceBook(new PriceBookId($createdBookId)) === 5, 'All manifest rules are created transactionally');
    $test->assert(benchmarkSeedActiveHufCount($wpdb, $tables) === $activeBefore, 'Seed creation does not activate a price book');

    $repeat = $service->createDraft($manifest, $actorId);
    $test->assert(! $repeat->created && $repeat->priceBookId === $createdBookId, 'Repeated seed is idempotent and returns the existing draft');
    $conflictingManifest = $loader->load($conflictPath);
    $test->throws(fn () => $service->plan($conflictingManifest), BenchmarkSeedConflictException::class, 'Changed manifest under the same seed key is rejected');

    $failureManifest = $loader->load($failurePath);
    $failureService = new BenchmarkPriceBookSeedService(
        $books,
        new BenchmarkSeedFailingRuleRepository($rules),
        new BenchmarkSeedCatalog(),
        $registry,
        $transactions,
        new BenchmarkSeedFixedClock()
    );
    $countsBeforeFailure = benchmarkSeedCounts($wpdb, $tables);
    $test->throws(fn () => $failureService->createDraft($failureManifest, $actorId), RuntimeException::class, 'Partial rule insert failure is propagated');
    $test->assert(benchmarkSeedCounts($wpdb, $tables) === $countsBeforeFailure, 'Partial seed insert rolls back the price book and rules');
    $test->assert($registry->find($failureSeedKey) === null, 'Rolled-back seed reservation does not remain registered');

    $test->assert(benchmarkSeedBook31Hash($wpdb, $tables) === $book31Before, 'Protected price book 31 and its rules are unchanged during the test');
    $test->assert(benchmarkSeedCounts($wpdb, $tables)[Schema::REQUESTS] === $before[Schema::REQUESTS], 'Benchmark seed creates no buyback request');
    $test->assert(benchmarkSeedCounts($wpdb, $tables)[Schema::SNAPSHOTS] === $before[Schema::SNAPSHOTS], 'Benchmark seed creates no snapshot');
    $test->assert(benchmarkSeedCounts($wpdb, $tables)[Schema::EVENTS] === $before[Schema::EVENTS], 'Benchmark seed creates no event');
} catch (Throwable $exception) {
    $test->fail($exception);
} finally {
    benchmarkSeedCleanup($wpdb, $tables, $labelPrefix, [$seedKey, $failureSeedKey]);
    benchmarkSeedRemoveFixtureDirectory($directory);
}

$after = benchmarkSeedCounts($wpdb, $tables);
$activeAfter = benchmarkSeedActiveHufCount($wpdb, $tables);
$book31After = benchmarkSeedBook31Hash($wpdb, $tables);
$test->finish($before, $after, $activeBefore, $activeAfter, $book31Before, $book31After);
