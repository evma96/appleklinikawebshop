<?php

declare(strict_types=1);

final class BuybackCliHalt extends RuntimeException
{
    public function __construct(public readonly int $exitCode)
    {
        parent::__construct('WP-CLI halted with exit code ' . $exitCode);
    }
}

final class WP_CLI
{
    /** @var array<string, callable> */
    public static array $commands = [];

    /** @var list<string> */
    public static array $lines = [];

    public static function add_command(string $name, callable $command): void
    {
        self::$commands[$name] = $command;
    }

    public static function line(string $message): void
    {
        self::$lines[] = $message;
    }

    public static function error(string $message): never
    {
        throw new RuntimeException($message);
    }

    public static function halt(int $exitCode): never
    {
        throw new BuybackCliHalt($exitCode);
    }

    public static function resetOutput(): void
    {
        self::$lines = [];
    }
}

$wordpressRoot = dirname(__DIR__, 4);
require_once $wordpressRoot . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

define('WP_CLI', true);
AppleKlinika\Buyback\Infrastructure\WordPress\Plugin::create()->register();

use AppleKlinika\Buyback\Application\Command\NewBuybackRequest;
use AppleKlinika\Buyback\Application\Legacy\LegacyBuybackParser;
use AppleKlinika\Buyback\Application\Legacy\LegacyBuybackRecord;
use AppleKlinika\Buyback\Application\Legacy\LegacyClassification;
use AppleKlinika\Buyback\Application\Legacy\LegacyFieldParser;
use AppleKlinika\Buyback\Application\Legacy\LegacyReferenceFactory;
use AppleKlinika\Buyback\Application\Legacy\LegacyReport;
use AppleKlinika\Buyback\Application\Legacy\LegacyReportExitPolicy;
use AppleKlinika\Buyback\Application\Legacy\LegacyReportService;
use AppleKlinika\Buyback\Application\Legacy\LegacySourceResult;
use AppleKlinika\Buyback\Application\Port\BuybackRequestRepository;
use AppleKlinika\Buyback\Application\Port\LegacyBuybackRecordSource;
use AppleKlinika\Buyback\Application\Port\LegacyModelResolver;
use AppleKlinika\Buyback\Application\Query\BuybackRequestPage;
use AppleKlinika\Buyback\Application\Query\PageRequest;
use AppleKlinika\Buyback\Domain\Buyback\BuybackRequest;
use AppleKlinika\Buyback\Domain\Buyback\BuybackRequestId;
use AppleKlinika\Buyback\Domain\Buyback\BuybackStatus;
use AppleKlinika\Buyback\Domain\Buyback\CustomerId;
use AppleKlinika\Buyback\Domain\Buyback\LegacyReference;
use AppleKlinika\Buyback\Domain\Buyback\RequestNumber;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\Schema;

final class LegacyTestRunner
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

    public function finish(array $before, array $after, string $legacyHashBefore, string $legacyHashAfter): never
    {
        if ($this->failures !== []) {
            foreach ($this->failures as $failure) {
                fwrite(STDERR, "FAIL: {$failure}\n");
            }

            fwrite(STDERR, sprintf(
                "%d assertion(s), %d failure(s).\n",
                $this->assertions,
                count($this->failures)
            ));
            exit(1);
        }

        echo sprintf(
            "Buyback legacy dry-run tests passed: %d assertions; rows before/after requests %d/%d, snapshots %d/%d, events %d/%d; legacy hash unchanged: %s.\n",
            $this->assertions,
            $before[Schema::REQUESTS],
            $after[Schema::REQUESTS],
            $before[Schema::SNAPSHOTS],
            $after[Schema::SNAPSHOTS],
            $before[Schema::EVENTS],
            $after[Schema::EVENTS],
            $legacyHashBefore === $legacyHashAfter ? 'yes' : 'no'
        );
        exit(0);
    }
}

final class FixtureLegacySource implements LegacyBuybackRecordSource
{
    /** @param list<LegacyBuybackRecord> $records */
    public function __construct(private readonly array $records)
    {
    }

    public function read(?int $userId = null): LegacySourceResult
    {
        $records = $userId === null
            ? $this->records
            : array_values(array_filter(
                $this->records,
                static fn (LegacyBuybackRecord $record): bool => $record->ownerUserId === $userId
            ));

        return new LegacySourceResult(count(array_unique(array_map(
            static fn (LegacyBuybackRecord $record): int => $record->ownerUserId,
            $records
        ))), $records);
    }
}

final class FixtureModelResolver implements LegacyModelResolver
{
    /** @param array<string, string> $models */
    public function __construct(private readonly array $models)
    {
    }

    public function resolve(string $deviceDisplayName): ?string
    {
        return $this->models[$deviceDisplayName] ?? null;
    }
}

final class ReadOnlyFixtureRepository implements BuybackRequestRepository
{
    /** @param list<string> $legacyReferences */
    public function __construct(private readonly array $legacyReferences = [])
    {
    }

    public function insert(NewBuybackRequest $request): BuybackRequest
    {
        throw new LogicException('Fixture repository is read-only.');
    }

    public function getById(BuybackRequestId $id): ?BuybackRequest
    {
        return null;
    }

    public function getByRequestNumber(RequestNumber $requestNumber): ?BuybackRequest
    {
        return null;
    }

    public function save(BuybackRequest $request, AggregateVersion $expectedVersion): void
    {
        throw new LogicException('Fixture repository is read-only.');
    }

    public function findByCustomer(CustomerId $customerId, PageRequest $page): BuybackRequestPage
    {
        return new BuybackRequestPage([], 0);
    }

    public function findByStatus(BuybackStatus $status, PageRequest $page): BuybackRequestPage
    {
        return new BuybackRequestPage([], 0);
    }

    public function existsByRequestNumber(RequestNumber $requestNumber): bool
    {
        return false;
    }

    public function existsByLegacyReference(LegacyReference $legacyReference): bool
    {
        return in_array($legacyReference->value(), $this->legacyReferences, true);
    }
}

/** @return array<string, int> */
function legacyRowCounts(wpdb $database): array
{
    $counts = [];

    foreach (Schema::tableNames($database) as $key => $table) {
        $counts[$key] = (int) $database->get_var("SELECT COUNT(*) FROM `{$table}`");
    }

    return $counts;
}

function legacyMetaHash(wpdb $database): string
{
    $rows = $database->get_results(
        $database->prepare(
            "SELECT umeta_id, user_id, meta_key, meta_value
             FROM {$database->usermeta}
             WHERE meta_key = %s
             ORDER BY umeta_id ASC",
            'appleklinika_buyback_records'
        ),
        ARRAY_A
    );

    return hash('sha256', serialize(is_array($rows) ? $rows : []));
}

function legacyOptionHash(wpdb $database): string
{
    $rows = $database->get_results(
        "SELECT option_name, option_value, autoload
         FROM {$database->options}
         WHERE option_name LIKE 'appleklinika_buyback_%'
            OR option_name = 'active_plugins'
         ORDER BY option_name ASC",
        ARRAY_A
    );

    return hash('sha256', serialize(is_array($rows) ? $rows : []));
}

function validLegacyRecord(int $userId = 10, string $id = 'fixture-1'): LegacyBuybackRecord
{
    return new LegacyBuybackRecord(
        $userId,
        0,
        $id,
        'iPhone 13 Pro 128 GB Grafit',
        'Jó állapot',
        '87%',
        '145 000 Ft',
        '138 000 Ft',
        'Bevizsgálás alatt',
        '2026-07-15',
        'fixture-marker',
        false
    );
}

function fixtureReport(
    LegacyBuybackRecord $record,
    array $models = ['iPhone 13 Pro 128 GB Grafit' => 'iphone-13-pro'],
    array $existing = []
): LegacyReport {
    $parser = new LegacyBuybackParser(
        new LegacyFieldParser(),
        new LegacyReferenceFactory(),
        new FixtureModelResolver($models)
    );

    return (new LegacyReportService(
        new FixtureLegacySource([$record]),
        $parser,
        new ReadOnlyFixtureRepository($existing)
    ))->report();
}

global $wpdb;

$test = new LegacyTestRunner();
$countsBefore = legacyRowCounts($wpdb);
$legacyHashBefore = legacyMetaHash($wpdb);
$optionHashBefore = legacyOptionHash($wpdb);
$fields = new LegacyFieldParser();
$references = new LegacyReferenceFactory();

$test->assert($fields->recordId('safe-id_1.2') === 'safe-id_1.2', 'Valid record ID is accepted');
$test->assert($fields->recordId('') === null, 'Missing record ID is rejected');
$test->assert($fields->recordId('unsafe id') === null, 'Unsafe record ID is rejected');
$test->assert($fields->batteryPercentage('87%') === 87, 'Valid battery is parsed');
$test->assert($fields->batteryPercentage('101%') === null, 'Out-of-range battery is rejected');
$test->assert($fields->batteryPercentage('battery') === null, 'Malformed battery is rejected');
$test->assert($fields->hufAmount('145 000 Ft') === 145000, 'HUF amount with spaces is parsed');
$test->assert($fields->hufAmount("145\u{00A0}000 Ft") === 145000, 'HUF amount with NBSP is parsed');
$test->assert($fields->hufAmount('-1 000 Ft') === null, 'Negative HUF amount is rejected');
$test->assert($fields->hufAmount('145 EUR') === null, 'Malformed HUF amount is rejected');
$test->assert($fields->status('Bevizsgálás alatt') === 'inspecting', 'Known status is mapped');
$test->assert($fields->status('Ismeretlen') === null, 'Unknown status is not guessed');
$test->assert($fields->utcDate('2026-07-15')?->format('Y-m-d') === '2026-07-15', 'Valid date is parsed');
$test->assert($fields->utcDate('2026-02-31') === null, 'Invalid date is rejected');

$referenceA = $references->fromUserMeta(10, 'record-1')->value();
$referenceAAgain = $references->fromUserMeta(10, 'record-1')->value();
$referenceB = $references->fromUserMeta(11, 'record-1')->value();
$test->assert($referenceA === 'user-meta:10:record-1', 'Deterministic legacy reference uses source, user and record');
$test->assert($referenceA === $referenceAAgain, 'Same source record produces the same reference');
$test->assert($referenceA !== $referenceB, 'Same record ID on different users does not collide');

$importable = fixtureReport(validLegacyRecord());
$test->assert($importable->items[0]->classification === LegacyClassification::IMPORTABLE, 'Importable fixture is classified');
$manual = fixtureReport(validLegacyRecord(), []);
$test->assert($manual->items[0]->classification === LegacyClassification::NEEDS_MANUAL_MAPPING, 'Unresolved model requires manual mapping');
$unknownStatus = validLegacyRecord();
$unknownStatus = new LegacyBuybackRecord(
    $unknownStatus->ownerUserId,
    $unknownStatus->sourceIndex,
    $unknownStatus->recordId,
    $unknownStatus->deviceDisplayName,
    $unknownStatus->condition,
    $unknownStatus->battery,
    $unknownStatus->estimatedOffer,
    $unknownStatus->finalOffer,
    'Ismeretlen',
    $unknownStatus->createdDate,
    $unknownStatus->marker,
    false
);
$test->assert(
    fixtureReport($unknownStatus)->items[0]->classification === LegacyClassification::NEEDS_MANUAL_MAPPING,
    'Unknown status requires manual mapping'
);
$invalid = validLegacyRecord();
$invalid = new LegacyBuybackRecord(
    $invalid->ownerUserId,
    0,
    null,
    $invalid->deviceDisplayName,
    $invalid->condition,
    $invalid->battery,
    $invalid->estimatedOffer,
    $invalid->finalOffer,
    $invalid->status,
    $invalid->createdDate,
    $invalid->marker,
    false
);
$invalidReport = fixtureReport($invalid);
$test->assert(count($invalidReport->items) === 1, 'Invalid fixture report is generated');
$test->assert($invalidReport->items[0]->classification === LegacyClassification::INVALID, 'Invalid fixture is classified');
$fixtureReference = $references->fromUserMeta(10, 'fixture-1')->value();
$already = fixtureReport(
    validLegacyRecord(),
    ['iPhone 13 Pro 128 GB Grafit' => 'iphone-13-pro'],
    [$fixtureReference]
);
$test->assert($already->items[0]->classification === LegacyClassification::ALREADY_PRESENT, 'Existing reference is classified');

$exitPolicy = new LegacyReportExitPolicy();
$test->assert($exitPolicy->exitCode($importable, true) === 0, 'Strict clean report exits zero');
$test->assert($exitPolicy->exitCode($invalidReport, true) === 1, 'Strict invalid report exits non-zero');
$test->assert($exitPolicy->exitCode($manual, true) === 1, 'Strict manual-mapping report exits non-zero');
$test->assert($exitPolicy->exitCode($manual, false) === 0, 'Normal report exits zero');

$test->assert(isset(WP_CLI::$commands['ak-buyback legacy-report']), 'WP-CLI legacy-report command is registered');
$command = WP_CLI::$commands['ak-buyback legacy-report'];
WP_CLI::resetOutput();
$command([], ['format' => 'json']);
$firstJson = implode("\n", WP_CLI::$lines);
$firstPayload = json_decode($firstJson, true);
$test->assert(is_array($firstPayload), 'CLI JSON output is valid JSON');
$test->assert(($firstPayload['summary']['new_requests_written'] ?? null) === 0, 'CLI reports zero request writes');
$test->assert(($firstPayload['summary']['source_records_modified'] ?? null) === 0, 'CLI reports zero source modifications');
$test->assert(($firstPayload['summary']['demo_record_count'] ?? null) === 1, 'Known demo record is detected');
$test->assert(strpos($firstJson, '@') === false, 'CLI report contains no email address');
$test->assert(strpos($firstJson, 'customer') === false, 'CLI report exposes no customer field');
$test->assert(strpos($firstJson, 'phone') === false, 'CLI report exposes no phone field');
$test->assert(strpos($firstJson, 'address') === false, 'CLI report exposes no address field');

$demoRows = array_values(array_filter(
    $firstPayload['records'] ?? [],
    static fn (array $row): bool => ($row['legacy_record_id'] ?? '') === LegacyReportService::KNOWN_DEMO_RECORD_ID
));
$test->assert(count($demoRows) === 1, 'Known demo record appears exactly once');
$test->assert(($demoRows[0]['marker'] ?? '') === LegacyReportService::KNOWN_DEMO_MARKER, 'Known demo marker appears');
$test->assert(
    ($demoRows[0]['classification'] ?? '') === LegacyClassification::NEEDS_MANUAL_MAPPING,
    'Known demo record is not forced importable without a model resolver'
);
$test->assert(($demoRows[0]['already_present'] ?? true) === false, 'Known demo reference is not present in new requests');

WP_CLI::resetOutput();
$command([], ['format' => 'json']);
$secondJson = implode("\n", WP_CLI::$lines);
$test->assert($firstJson === $secondJson, 'Consecutive CLI JSON reports are byte-for-byte deterministic');

$filteredUserId = (int) ($demoRows[0]['owner_user_id'] ?? 0);
WP_CLI::resetOutput();
$command([], ['format' => 'json', 'user-id' => $filteredUserId]);
$filteredPayload = json_decode(implode("\n", WP_CLI::$lines), true);
$test->assert(($filteredPayload['summary']['users_scanned'] ?? 0) === 1, 'Positive user filter limits the report');

$strictExit = null;

try {
    WP_CLI::resetOutput();
    $command([], ['format' => 'json', 'strict' => true]);
} catch (BuybackCliHalt $halt) {
    $strictExit = $halt->exitCode;
}

$test->assert($strictExit === 1, 'Strict CLI exits non-zero for the real manual-mapping record');
$test->assert(is_plugin_active('appleklinika-buyback/appleklinika-buyback.php'), 'Plugin remains active');
$test->assert(APPLEKLINIKA_BUYBACK_VERSION === '0.8.0', 'Plugin code version is 0.8.0');
$test->assert(APPLEKLINIKA_BUYBACK_SCHEMA_VERSION === '1.1.0', 'Code schema is 1.1.0');
$test->assert(get_option(Schema::OPTION_SCHEMA_VERSION) === '1.1.0', 'Installed schema is 1.1.0');

$countsAfter = legacyRowCounts($wpdb);
$legacyHashAfter = legacyMetaHash($wpdb);
$optionHashAfter = legacyOptionHash($wpdb);
$test->assert($countsAfter === $countsBefore, 'All buyback table row counts remain unchanged');
$test->assert($legacyHashAfter === $legacyHashBefore, 'Legacy user-meta hash remains unchanged');
$test->assert($optionHashAfter === $optionHashBefore, 'Plugin and active-plugin options remain unchanged');

$test->finish($countsBefore, $countsAfter, $legacyHashBefore, $legacyHashAfter);
