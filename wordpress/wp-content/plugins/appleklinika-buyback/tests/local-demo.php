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
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PricingCalculationInput;
use AppleKlinika\Buyback\Domain\Pricing\PricingEngine;
use AppleKlinika\Buyback\Domain\Pricing\PricingModelKey;
use AppleKlinika\Buyback\Domain\Pricing\PricingOutcome;
use AppleKlinika\Buyback\Domain\Pricing\StorageCapacity;
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
function localDemoProtectedCounts(wpdb $database): array
{
    $tables = Schema::tableNames($database);
    return [
        'requests' => (int) $database->get_var("SELECT COUNT(*) FROM `{$tables[Schema::REQUESTS]}`"),
        'snapshots' => (int) $database->get_var("SELECT COUNT(*) FROM `{$tables[Schema::SNAPSHOTS]}`"),
        'events' => (int) $database->get_var("SELECT COUNT(*) FROM `{$tables[Schema::EVENTS]}`"),
    ];
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
        'charging_functional' => true,
        'liquid_damage' => false,
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
localDemoAssert(WordPressLocalDemoProductReader::resolveColors(['graphite' => 'Grafit'], ['graphite' => 'Más címke', 'extra' => 'Extra']) === ['graphite' => 'Grafit'], 'Catalogue colors override and exclude conflicting Woo product colors');
localDemoAssert(WordPressLocalDemoProductReader::resolveColors([], ['graphite' => 'Grafit']) === ['graphite' => 'Grafit'], 'Woo product colors are used only when the catalogue has no colors');
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
localDemoAssert($questionnaire->manualReviewReasons($unknownRepairQuestionnaire) !== [], 'Unknown repair produces an explicit manual-review reason');

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
localDemoAssert($questionnaire->eligibilityError($lockedQuestionnaire) !== null, 'Network-locked device is blocked by an explicit eligibility message');

$manualQuestionnaire = $healthyQuestionnaire;
$manualQuestionnaire['display_defects'] = ['pixels'];
$manualQuestionnaire['other_defects'] = ['audio'];
localDemoAssert(count($questionnaire->manualReviewReasons($manualQuestionnaire)) === 2, 'Unsupported display and sound states produce explicit manual-review reasons');

$builder = new LocalDemoPriceMatrixBuilder();
$synthetic = $builder->build([
    ['model_key' => 'iphone_test', 'model_label' => 'iPhone Test', 'storage_gb' => 128, 'grade' => 'a_plus', 'price' => 220000],
    ['model_key' => 'iphone_test', 'model_label' => 'iPhone Test', 'storage_gb' => 128, 'grade' => 'a', 'price' => 240000],
    ['model_key' => 'iphone_test', 'model_label' => 'iPhone Test', 'storage_gb' => 128, 'grade' => 'b', 'price' => 100000],
]);
localDemoAssert(count($synthetic) === 1, 'Price matrix groups model and storage deterministically');
localDemoAssert($synthetic[0]->representativePrice === 230000, 'Representative median prefers Grade A+ and Grade A');
localDemoAssert($synthetic[0]->basePrice === 115000, 'Local demo base uses integer 50 percent and 1000 HUF rounding');

$countsBefore = localDemoProtectedCounts($wpdb);
$module = LocalDemoModule::create();
$first = $module->seeder()->seed();
$second = $module->seeder()->seed();
localDemoAssert($first->priceBookId === $second->priceBookId, 'Repeated seed reuses the same price book');
localDemoAssert($first->pageId === $second->pageId, 'Repeated seed reuses the same page');
localDemoAssert($second->modelCount === 1, 'Published catalog generates one iPhone model');
localDemoAssert($second->configurationCount === 4, 'Published catalog generates four model/storage configurations');
localDemoAssert($second->ruleCount === 30, 'Demo price book contains the exact thirty configured rules');

$books = new WordPressPriceBookRepository($wpdb);
$rulesRepository = new WordPressPricingRuleRepository($wpdb);
$resolver = new RepositoryActivePriceBookResolver($books, $rulesRepository);
$resolved = $resolver->resolveForCurrencyAt(new CurrencyCode('HUF'), new DateTimeImmutable('now', new DateTimeZone('UTC')));
localDemoAssert($resolved->priceBook->label() === LocalDemoSeeder::LABEL, 'Exactly the local demo book resolves as active HUF book');
localDemoAssert($books->countCurrentActiveForCurrencyAt(new CurrencyCode('HUF'), new DateTimeImmutable('now', new DateTimeZone('UTC'))) === 1, 'Exactly one active HUF price book exists');
localDemoAssert(count($resolved->enabledRules) === 30, 'Active demo book exposes thirty enabled rules');

$protected = $books->getById(new PriceBookId(31));
localDemoAssert($protected !== null && $protected->label() === 'noj' && $protected->status()->code() === 'draft' && $protected->version()->value() === 0, 'Protected price book 31 remains noj draft version zero');
localDemoAssert($rulesRepository->countForPriceBook(new PriceBookId(31)) === 0, 'Protected price book 31 still has zero rules');

$page = get_page_by_path(WordPressLocalDemoPageGateway::SLUG, OBJECT, 'page');
localDemoAssert($page instanceof WP_Post && (int) $page->ID === $second->pageId, 'Local demo page exists idempotently at the expected slug');

$reference = localDemoCalculateAll($resolved->priceBook, $resolved->enabledRules, localDemoAnswers(['battery_health' => 85, 'screen_condition' => 'good']));
$expected = [
    ServiceMode::IN_STORE_INSTANT => 100000,
    ServiceMode::FAST_ONLINE => 95000,
    ServiceMode::HIGHER_OFFER => 105000,
    ServiceMode::TRADE_IN => 110000,
];
foreach ($expected as $mode => $amount) {
    localDemoAssert($reference[$mode]->outcome->code() === PricingOutcome::OFFERED, "{$mode} produces an offered outcome");
    localDemoAssert($reference[$mode]->finalAmount?->amount() === $amount, "{$mode} produces the expected local demo amount {$amount}");
}

$manual = localDemoCalculateAll($resolved->priceBook, $resolved->enabledRules, localDemoAnswers(['display_functional' => false]));
foreach ($manual as $mode => $result) {
    localDemoAssert($result->outcome->code() === PricingOutcome::MANUAL_REVIEW, "{$mode} requires manual review when the display does not work");
}

localDemoAssert(localDemoProtectedCounts($wpdb) === $countsBefore, 'Seed and calculation create no request, snapshot or event rows');

echo sprintf(
    "Local demo tests passed: book %d, page %d, models %d, configurations %d, rules %d; reference amounts %s.\n",
    $second->priceBookId,
    $second->pageId,
    $second->modelCount,
    $second->configurationCount,
    $second->ruleCount,
    wp_json_encode($expected)
);
