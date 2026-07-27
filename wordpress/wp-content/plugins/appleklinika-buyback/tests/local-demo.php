<?php

declare(strict_types=1);

$wordpressRoot = dirname(__DIR__, 4);
require_once $wordpressRoot . '/wp-load.php';

use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoHostGuard;
use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoQuestionnaire;
use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoPriceMatrixBuilder;
use AppleKlinika\Buyback\Application\Pricing\RepositoryActivePriceBookResolver;
use AppleKlinika\Buyback\Domain\Buyback\DeviceCategory;
use AppleKlinika\Buyback\Domain\Buyback\ServiceMode;
use AppleKlinika\Buyback\Domain\Pricing\ConditionAnswerCollection;
use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;
use AppleKlinika\Buyback\Domain\Pricing\MinimumOfferPolicy;
use AppleKlinika\Buyback\Domain\Pricing\PriceBook;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PricingActorId;
use AppleKlinika\Buyback\Domain\Pricing\PricingCalculationInput;
use AppleKlinika\Buyback\Domain\Pricing\PricingEngine;
use AppleKlinika\Buyback\Domain\Pricing\PricingModelKey;
use AppleKlinika\Buyback\Domain\Pricing\PricingOutcome;
use AppleKlinika\Buyback\Domain\Pricing\StorageCapacity;
use AppleKlinika\Buyback\Domain\Shared\Money;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\Schema;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPriceBookRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPricingRuleRepository;
use AppleKlinika\Buyback\Infrastructure\WordPress\LocalDemoModule;
use AppleKlinika\Buyback\Infrastructure\WordPress\LocalDemoSeeder;
use AppleKlinika\Buyback\Infrastructure\WordPress\WordPressLocalDemoPageGateway;
use AppleKlinika\Buyback\Infrastructure\WordPress\WordPressLocalDemoProductReader;

function localDemoAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

/** @return array<string,int> */
function localDemoPersistentCounts(wpdb $database): array
{
    $tables = Schema::tableNames($database);
    return [
        'price_books' => (int) $database->get_var("SELECT COUNT(*) FROM `{$tables[Schema::PRICE_BOOKS]}`"),
        'price_rules' => (int) $database->get_var("SELECT COUNT(*) FROM `{$tables[Schema::PRICE_RULES]}`"),
        'requests' => (int) $database->get_var("SELECT COUNT(*) FROM `{$tables[Schema::REQUESTS]}`"),
        'snapshots' => (int) $database->get_var("SELECT COUNT(*) FROM `{$tables[Schema::SNAPSHOTS]}`"),
        'events' => (int) $database->get_var("SELECT COUNT(*) FROM `{$tables[Schema::EVENTS]}`"),
    ];
}

/** @return array<int,array{status:string,version:int,rule_hash:string}> */
function localDemoPriceBookFingerprints(wpdb $database): array
{
    $tables = Schema::tableNames($database);
    $books = $database->get_results("SELECT id, status, version FROM `{$tables[Schema::PRICE_BOOKS]}` ORDER BY id", ARRAY_A);
    $fingerprints = [];
    foreach (is_array($books) ? $books : [] as $book) {
        $id = (int) $book['id'];
        $rules = $database->get_results($database->prepare("SELECT * FROM `{$tables[Schema::PRICE_RULES]}` WHERE price_book_id = %d ORDER BY id", $id), ARRAY_A);
        $fingerprints[$id] = [
            'status' => (string) $book['status'],
            'version' => (int) $book['version'],
            'rule_hash' => hash('sha256', wp_json_encode(is_array($rules) ? $rules : [])),
        ];
    }
    return $fingerprints;
}

function localDemoDeleteTemporaryFixture(wpdb $database, PriceBookId $id): void
{
    $tables = Schema::tableNames($database);
    $database->query($database->prepare("DELETE FROM `{$tables[Schema::PRICE_RULES]}` WHERE price_book_id = %d", $id->toInt()));
    $database->query($database->prepare("DELETE FROM `{$tables[Schema::PRICE_BOOKS]}` WHERE id = %d", $id->toInt()));
}

/** @param array<string,int|bool|string> $overrides @return array<string,int|bool|string> */
function localDemoAnswers(array $overrides = []): array
{
    return array_replace([
        'battery_health' => 90,
        'powers_on' => true,
        'display_functional' => true,
        'touch_functional' => true,
        'face_id_functional' => true,
        'camera_functional' => true,
        'front_camera_functional' => true,
        'rear_camera_functional' => true,
        'audio_functional' => true,
        'charging_functional' => true,
        'liquid_damage' => false,
        'network_unlocked' => true,
        'display_yellowing' => false,
        'display_deformed' => false,
        'display_dead_pixels' => false,
        'display_image_brightness_functional' => true,
        'motherboard_issue' => false,
        'screen_condition' => 'excellent',
        'frame_condition' => 'excellent',
        'back_glass_condition' => 'excellent',
        'camera_lens_condition' => 'excellent',
        'bent_or_dented' => false,
        'replacement_parts' => 'none_known',
    ], $overrides);
}

/** @return array<string,\AppleKlinika\Buyback\Domain\Pricing\PricingCalculationResult> */
function localDemoCalculateAll($book, array $rules, array $answers): array
{
    $results = [];
    $engine = new PricingEngine();
    foreach (ServiceMode::supportedCodes() as $mode) {
        $results[$mode] = $engine->calculate($book, $rules, new PricingCalculationInput(
            new DeviceCategory('iphone'),
            new PricingModelKey('iphone_13_pro'),
            new StorageCapacity(128),
            ConditionAnswerCollection::fromAssociative($answers),
            new ServiceMode($mode)
        ));
    }
    return $results;
}

global $wpdb;

$guard = new LocalDemoHostGuard();
$guard->assertLocal(site_url(), home_url());
localDemoAssert(true, 'Localhost guard accepts the configured local site and home URLs');
$blocked = false;
try {
    $guard->assertLocal('https://example.com', 'https://example.com');
} catch (RuntimeException) {
    $blocked = true;
}
localDemoAssert($blocked, 'Localhost guard rejects a non-local host');

$questionnaire = new LocalDemoQuestionnaire();
localDemoAssert(WordPressLocalDemoProductReader::resolveColors(['graphite' => 'Grafit']) === ['graphite' => 'Grafit'], 'Catalogue colors are used as the only public color source');
localDemoAssert(WordPressLocalDemoProductReader::resolveColors([]) === [], 'Missing catalogue colors do not fall back to Woo product metadata');
localDemoAssert(
    $questionnaire->panelOrder() === [
        'model',
        'configuration',
        'liquid_contact',
        'screen_cosmetic',
        'display_defects',
        'frame_cosmetic',
        'back_cosmetic',
        'battery',
        'service_history',
        'other_defects',
        'offers',
        'review',
    ],
    'Questionnaire exposes the verified iPhone flow with separate offer selection and review panels'
);
localDemoAssert(! array_key_exists('camera_lens_condition', $questionnaire->questions()), 'Camera-lens condition is not rendered as a separate questionnaire question');
localDemoAssert(! array_key_exists('powers_on', $questionnaire->questions()), 'Unverified power-on question is not exposed as a dedicated customer step');
localDemoAssert(array_key_exists('service_history', $questionnaire->questions()), 'Parts and service history is exposed as a dedicated customer step');
localDemoAssert(array_key_exists('affected_parts', $questionnaire->questions()), 'Affected parts are retained as conditional questionnaire state');
localDemoAssert(array_key_exists('camera_lens', $questionnaire->questions()['other_defects']['options']), 'Damaged camera lens is available under other defects');
localDemoAssert($questionnaire->questions()['network_status']['panel'] === 'configuration', 'Network eligibility is part of device configuration');

$healthyQuestionnaire = $questionnaire->defaults();
$noHistoryWithoutParts = $healthyQuestionnaire;
$noHistoryWithoutParts['affected_parts'] = [];
localDemoAssert($questionnaire->validate($noHistoryWithoutParts) === [], 'No-history without affected parts passes validation');
$unknownWithoutParts = $healthyQuestionnaire;
$unknownWithoutParts['service_history'] = 'unknown';
$unknownWithoutParts['affected_parts'] = [];
localDemoAssert(array_key_exists('affected_parts', $questionnaire->validate($unknownWithoutParts)), 'Unknown service history without parts fails affected-parts validation');
$unknownWithPart = $unknownWithoutParts;
$unknownWithPart['affected_parts'] = ['battery'];
localDemoAssert($questionnaire->validate($unknownWithPart) === [], 'Unknown service history with one part passes validation');
$clearedHistory = $questionnaire->sanitize(array_replace($unknownWithPart, ['service_history' => 'none_known']));
localDemoAssert($clearedHistory['affected_parts'] === [], 'Switching back to no-history clears affected parts');
$healthyConditions = $questionnaire->mapToConditions($healthyQuestionnaire);
localDemoAssert($healthyConditions['camera_lens_condition'] === 'excellent', 'Unselected camera-lens damage maps to the valid healthy canonical value');
localDemoAssert($healthyConditions['back_glass_condition'] === 'excellent', 'Back-glass answer maps to the canonical pricing key');
localDemoAssert($healthyConditions['powers_on'] === true && $healthyConditions['display_functional'] === true, 'Unverified dedicated operation questions retain safe healthy defaults');
localDemoAssert($healthyConditions['replacement_parts'] === 'none_known', 'Unverified repair history retains the existing healthy canonical default');

$originalRepairQuestionnaire = $healthyQuestionnaire;
$originalRepairQuestionnaire['service_history'] = 'original_repair';
$originalRepairQuestionnaire['affected_parts'] = ['display'];
localDemoAssert($questionnaire->mapToConditions($originalRepairQuestionnaire)['replacement_parts'] === 'original_repair', 'Genuine repair maps to the existing original_repair enum');

$unknownRepairQuestionnaire = $healthyQuestionnaire;
$unknownRepairQuestionnaire['service_history'] = 'unknown';
$unknownRepairQuestionnaire['affected_parts'] = ['battery'];
localDemoAssert($questionnaire->mapToConditions($unknownRepairQuestionnaire)['replacement_parts'] === 'unknown', 'Unknown repair maps to the existing unknown enum');
localDemoAssert($questionnaire->manualReviewReasons($unknownRepairQuestionnaire) === [], 'Unknown repair delegates its commercial outcome to the centralized price-book policy');

$damagedLensQuestionnaire = $healthyQuestionnaire;
$damagedLensQuestionnaire['other_defects'] = ['camera_lens'];
$damagedLensConditions = $questionnaire->mapToConditions($damagedLensQuestionnaire);
localDemoAssert($damagedLensConditions['camera_lens_condition'] === 'damaged', 'Damaged camera-lens option maps to camera_lens_condition=damaged');

$exclusiveQuestionnaire = $questionnaire->sanitize(array_replace($healthyQuestionnaire, [
    'display_defects' => ['none', 'touch', 'pixels'],
    'other_defects' => ['none', 'face_id'],
]));
localDemoAssert($exclusiveQuestionnaire['display_defects'] === ['none'], 'Exclusive healthy display option clears defect options during sanitization');
localDemoAssert($exclusiveQuestionnaire['other_defects'] === ['none'], 'Exclusive healthy operation option clears other defect options during sanitization');

$lockedQuestionnaire = $healthyQuestionnaire;
$lockedQuestionnaire['network_status'] = 'locked';
localDemoAssert($questionnaire->eligibilityError($lockedQuestionnaire) === null && $questionnaire->mapToConditions($lockedQuestionnaire)['network_unlocked'] === false, 'Network lock is delegated to the centralized price-book policy');

$manualQuestionnaire = $healthyQuestionnaire;
$manualQuestionnaire['display_defects'] = ['pixels'];
$manualQuestionnaire['other_defects'] = ['audio'];
localDemoAssert($questionnaire->manualReviewReasons($manualQuestionnaire) === [] && $questionnaire->mapToConditions($manualQuestionnaire)['display_dead_pixels'] === true && $questionnaire->mapToConditions($manualQuestionnaire)['audio_functional'] === false, 'Display and sound commercial outcomes are delegated as canonical price-book conditions');

$builder = new LocalDemoPriceMatrixBuilder();
$synthetic = $builder->build([
    ['model_key' => 'iphone_test', 'model_label' => 'iPhone Test', 'storage_gb' => 128, 'grade' => 'a_plus', 'price' => 220000],
    ['model_key' => 'iphone_test', 'model_label' => 'iPhone Test', 'storage_gb' => 128, 'grade' => 'a', 'price' => 240000],
    ['model_key' => 'iphone_test', 'model_label' => 'iPhone Test', 'storage_gb' => 128, 'grade' => 'b', 'price' => 100000],
]);
localDemoAssert(count($synthetic) === 1, 'Price matrix groups model and storage deterministically');
localDemoAssert($synthetic[0]->representativePrice === 230000, 'Representative median prefers Grade A+ and Grade A');
localDemoAssert($synthetic[0]->basePrice === 115000, 'Local demo base uses integer 50 percent and 1000 HUF rounding');

$countsBefore = localDemoPersistentCounts($wpdb);
$fingerprintsBefore = localDemoPriceBookFingerprints($wpdb);
$books = new WordPressPriceBookRepository($wpdb);
$rulesRepository = new WordPressPricingRuleRepository($wpdb);
$resolver = new RepositoryActivePriceBookResolver($books, $rulesRepository);
$activeBefore = $resolver->resolveForCurrencyAt(new CurrencyCode('HUF'), new DateTimeImmutable('now', new DateTimeZone('UTC')))->priceBook->id()?->toInt();
$module = LocalDemoModule::create();
$module->register();
localDemoAssert(true, 'Registering the public module does not invoke the legacy local-demo seeder');

$resolved = $resolver->resolveForCurrencyAt(new CurrencyCode('HUF'), new DateTimeImmutable('now', new DateTimeZone('UTC')));
localDemoAssert($resolved->priceBook->id()?->toInt() === $activeBefore, 'The already-active generic HUF price book remains the public source');
localDemoAssert($books->countCurrentActiveForCurrencyAt(new CurrencyCode('HUF'), new DateTimeImmutable('now', new DateTimeZone('UTC'))) === 1, 'Exactly one active HUF price book exists');
localDemoAssert($resolved->enabledRules !== [], 'The active HUF price book exposes enabled public pricing rules');

$page = get_page_by_path(WordPressLocalDemoPageGateway::SLUG, OBJECT, 'page');
localDemoAssert($page instanceof WP_Post, 'The public buyback page exists at the expected slug');
localDemoAssert(localDemoPersistentCounts($wpdb) === $countsBefore, 'Registering and resolving the public flow creates no persistent records');
localDemoAssert(localDemoPriceBookFingerprints($wpdb) === $fingerprintsBefore, 'All pre-existing price books and rule-content hashes remain unchanged');
$activeAfter = $resolver->resolveForCurrencyAt(new CurrencyCode('HUF'), new DateTimeImmutable('now', new DateTimeZone('UTC')))->priceBook->id()?->toInt();
localDemoAssert($activeAfter === $activeBefore, 'Active HUF price-book identity remains unchanged');

echo sprintf(
    "Local demo tests passed: active book %d, page %d; no seeding or persistent writes.\n",
    $activeBefore,
    $page instanceof WP_Post ? (int) $page->ID : 0
);
