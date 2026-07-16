<?php

declare(strict_types=1);

$wordpressRoot = dirname(__DIR__, 4);
require_once $wordpressRoot . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use AppleKlinika\Buyback\Application\Handler\PreviewDraftPriceBookCalculationHandler;
use AppleKlinika\Buyback\Application\Query\PreviewDraftPriceBookCalculation;
use AppleKlinika\Buyback\Domain\Buyback\DeviceCategory;
use AppleKlinika\Buyback\Domain\Buyback\ServiceMode;
use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;
use AppleKlinika\Buyback\Domain\Pricing\BasisPointsMultiplier;
use AppleKlinika\Buyback\Domain\Pricing\ComparisonOperator;
use AppleKlinika\Buyback\Domain\Pricing\ConditionAnswer;
use AppleKlinika\Buyback\Domain\Pricing\ConditionAnswerCollection;
use AppleKlinika\Buyback\Domain\Pricing\ConditionDefinition;
use AppleKlinika\Buyback\Domain\Pricing\ConditionMatcher;
use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;
use AppleKlinika\Buyback\Domain\Pricing\MinimumOfferPolicy;
use AppleKlinika\Buyback\Domain\Pricing\PriceBook;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookStatus;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookVersionNumber;
use AppleKlinika\Buyback\Domain\Pricing\PricingActorId;
use AppleKlinika\Buyback\Domain\Pricing\PricingCalculationInput;
use AppleKlinika\Buyback\Domain\Pricing\PricingEngine;
use AppleKlinika\Buyback\Domain\Pricing\PricingOutcome;
use AppleKlinika\Buyback\Domain\Pricing\PricingModelKey;
use AppleKlinika\Buyback\Domain\Pricing\PricingRule;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleCode;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleDefinition;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleId;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind;
use AppleKlinika\Buyback\Domain\Pricing\RulePriority;
use AppleKlinika\Buyback\Domain\Pricing\StorageCapacity;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Domain\Shared\Money;
use AppleKlinika\Buyback\Infrastructure\Inventory\WordPressDeviceCatalogReader;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\Schema;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPriceBookRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPricingRuleRepository;
use AppleKlinika\Buyback\Infrastructure\WordPress\CapabilityManager;
use AppleKlinika\Buyback\Interfaces\Admin\AdminAuthorization;

final class PricingEngineTestRunner
{
    private int $assertions = 0;
    /** @var list<string> */
    private array $failures = [];

    public function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (! $condition) { $this->failures[] = $message; }
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
                $this->failures[] = $message . ' (received ' . $exception::class . ': ' . $exception->getMessage() . ')';
            }
        }
    }

    public function fail(Throwable $exception): void { $this->failures[] = $exception::class . ': ' . $exception->getMessage(); }

    /** @param array<string, int> $before @param array<string, int> $after */
    public function finish(array $before, array $after, string $marker, string $legacyBefore, string $legacyAfter): never
    {
        if ($this->failures !== []) {
            foreach ($this->failures as $failure) { fwrite(STDERR, "FAIL: {$failure}\n"); }
            fwrite(STDERR, sprintf("%d assertion(s), %d failure(s); marker %s.\n", $this->assertions, count($this->failures), $marker));
            exit(1);
        }
        echo sprintf(
            "Buyback pricing-engine tests passed: %d assertions; marker %s; rows before/after price_books %d/%d, price_rules %d/%d, requests %d/%d, snapshots %d/%d, events %d/%d; legacy hash %s.\n",
            $this->assertions, $marker,
            $before[Schema::PRICE_BOOKS], $after[Schema::PRICE_BOOKS],
            $before[Schema::PRICE_RULES], $after[Schema::PRICE_RULES],
            $before[Schema::REQUESTS], $after[Schema::REQUESTS],
            $before[Schema::SNAPSHOTS], $after[Schema::SNAPSHOTS],
            $before[Schema::EVENTS], $after[Schema::EVENTS],
            $legacyBefore === $legacyAfter ? 'unchanged' : 'changed'
        );
        exit(0);
    }
}

/** @return array<string, int|bool|string> */
function engineAnswers(array $overrides = []): array
{
    return array_replace([
        'battery_health' => 78,
        'powers_on' => true,
        'display_functional' => true,
        'touch_functional' => true,
        'face_id_functional' => true,
        'camera_functional' => true,
        'charging_functional' => true,
        'liquid_damage' => false,
        'motherboard_issue' => false,
        'screen_condition' => 'good',
        'frame_condition' => 'very_good',
        'back_glass_condition' => 'excellent',
        'camera_lens_condition' => 'like_new',
        'bent_or_dented' => false,
        'replacement_parts' => 'none_known',
    ], $overrides);
}

function engineBook(int $id = 700001, int $minimum = 10000, int $rounding = 1000, string $policy = MinimumOfferPolicy::MANUAL_REVIEW): PriceBook
{
    $at = new DateTimeImmutable('2026-07-16T08:00:00+00:00');
    return PriceBook::reconstitute(new PriceBookId($id), new PriceBookVersionNumber($id), 'QA engine', new PriceBookStatus(PriceBookStatus::DRAFT), new CurrencyCode('HUF'), new Money($minimum, 'HUF'), $rounding, new MinimumOfferPolicy($policy), new PricingActorId(1), new AggregateVersion(0), $at, $at);
}

function engineRule(int $id, PriceBookId $bookId, string $kind, string $code, int $priority = 100, bool $enabled = true, array $values = []): PricingRule
{
    $definition = new PricingRuleDefinition(
        new PricingRuleCode($code),
        new PricingRuleKind($kind),
        'iphone',
        $values['model_key'] ?? ($kind === PricingRuleKind::BASE_PRICE ? 'iphone-13-pro' : null),
        isset($values['storage_gb']) ? new StorageCapacity($values['storage_gb']) : ($kind === PricingRuleKind::BASE_PRICE ? new StorageCapacity(128) : null),
        $values['service_mode'] ?? null,
        $values['condition_key'] ?? null,
        isset($values['operator']) ? new ComparisonOperator($values['operator']) : null,
        $values['comparison'] ?? null,
        isset($values['amount']) ? new Money($values['amount'], 'HUF') : null,
        isset($values['multiplier']) ? new BasisPointsMultiplier($values['multiplier']) : null,
        new RulePriority($priority),
        $enabled,
        $values['label'] ?? null,
        'QA pricing-engine rule'
    );
    $at = new DateTimeImmutable('2026-07-16T08:00:00+00:00');
    return PricingRule::reconstitute(new PricingRuleId($id), $bookId, $definition, new AggregateVersion(0), $at, $at);
}

/** @return list<PricingRule> */
function engineOfferRules(PriceBookId $bookId): array
{
    return [
        engineRule(1, $bookId, PricingRuleKind::BASE_PRICE, 'base', 10, true, ['amount' => 200000]),
        engineRule(2, $bookId, PricingRuleKind::FIXED_DEDUCTION, 'battery-low', 20, true, ['condition_key' => 'battery_health', 'operator' => ComparisonOperator::LESS_THAN, 'comparison' => 80, 'amount' => 15000]),
        engineRule(3, $bookId, PricingRuleKind::MULTIPLIER, 'screen-good', 30, true, ['condition_key' => 'screen_condition', 'operator' => ComparisonOperator::EQUALS, 'comparison' => 'good', 'multiplier' => 9000]),
        engineRule(4, $bookId, PricingRuleKind::MODE_ADJUSTMENT, 'fast-bonus', 40, true, ['service_mode' => ServiceMode::FAST_ONLINE, 'amount' => 5000]),
    ];
}

function engineInput(array $answers = [], string $mode = ServiceMode::FAST_ONLINE, string $model = 'iphone-13-pro', int $storage = 128): PricingCalculationInput
{
    return new PricingCalculationInput(new DeviceCategory(DeviceCategory::IPHONE), new PricingModelKey($model), new StorageCapacity($storage), ConditionAnswerCollection::fromAssociative(engineAnswers($answers)), new ServiceMode($mode));
}

/** @return array<string, int> */
function engineCounts(wpdb $database, array $tables): array
{
    $counts = [];
    foreach ($tables as $key => $table) { $counts[$key] = (int) $database->get_var("SELECT COUNT(*) FROM `{$table}`"); }
    return $counts;
}

function engineLegacyHash(wpdb $database): string
{
    $rows = $database->get_results($database->prepare("SELECT umeta_id, user_id, meta_key, meta_value FROM {$database->usermeta} WHERE meta_key = %s ORDER BY umeta_id", 'appleklinika_buyback_records'), ARRAY_A);
    return hash('sha256', serialize(is_array($rows) ? $rows : []));
}

function engineCleanup(wpdb $database, array $tables, string $marker): void
{
    $ids = $database->get_col($database->prepare("SELECT id FROM `{$tables[Schema::PRICE_BOOKS]}` WHERE label LIKE %s", $database->esc_like($marker) . '%'));
    $ids = array_map('intval', is_array($ids) ? $ids : []);
    if ($ids === []) { return; }
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $database->query($database->prepare("DELETE FROM `{$tables[Schema::PRICE_RULES]}` WHERE price_book_id IN ({$placeholders})", ...$ids));
    $database->query($database->prepare("DELETE FROM `{$tables[Schema::PRICE_BOOKS]}` WHERE id IN ({$placeholders})", ...$ids));
}

/** @return array{0:int,1:bool} */
function engineUser(string $role, string $token): array
{
    $ids = get_users(['role' => $role, 'number' => 1, 'fields' => 'ID']);
    if (is_array($ids) && $ids !== []) { return [(int) $ids[0], false]; }
    $id = wp_insert_user(['user_login' => 'qa-engine-' . $role . '-' . $token, 'user_pass' => wp_generate_password(24), 'user_email' => 'qa-engine-' . $role . '-' . $token . '@example.invalid', 'role' => $role]);
    if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
    return [(int) $id, true];
}

global $wpdb;
$test = new PricingEngineTestRunner();
$tables = Schema::tableNames($wpdb);
$token = gmdate('mdHis') . bin2hex(random_bytes(3));
$marker = 'QA-PRICE-ENGINE-' . $token;
$before = engineCounts($wpdb, $tables);
$legacyBefore = engineLegacyHash($wpdb);
$originalUser = get_current_user_id();
$createdUsers = [];
$authorizedUser = 0;
$engine = new PricingEngine();

try {
    $test->assert(APPLEKLINIKA_BUYBACK_VERSION === '0.7.0', 'Plugin version is 0.7.0');
    $test->assert(APPLEKLINIKA_BUYBACK_SCHEMA_VERSION === '1.1.0', 'Schema code remains 1.1.0');
    $test->assert((string) get_option(Schema::OPTION_SCHEMA_VERSION) === '1.1.0', 'Installed schema remains 1.1.0');

    $validAnswers = ConditionAnswerCollection::fromAssociative(engineAnswers());
    $test->assert(count($validAnswers->all()) === count(ConditionDefinition::keys()), 'Valid full condition input is accepted');
    $missing = engineAnswers(); unset($missing['powers_on']);
    $test->throws(fn () => ConditionAnswerCollection::fromAssociative($missing), InvalidValueObjectException::class, 'Missing required condition is rejected');
    $test->throws(fn () => new ConditionAnswerCollection([new ConditionAnswer('powers_on', true), new ConditionAnswer('powers_on', false)]), InvalidValueObjectException::class, 'Duplicate condition key is rejected');
    $test->throws(fn () => new ConditionAnswer('unknown', true), InvalidValueObjectException::class, 'Unknown condition key is rejected');
    $test->throws(fn () => new ConditionAnswer('battery_health', -1), InvalidValueObjectException::class, 'Battery below zero is rejected');
    $test->throws(fn () => new ConditionAnswer('battery_health', 101), InvalidValueObjectException::class, 'Battery above 100 is rejected');
    $test->throws(fn () => new ConditionAnswer('powers_on', 'yes'), InvalidValueObjectException::class, 'Invalid boolean is rejected');
    $test->throws(fn () => new ConditionAnswer('screen_condition', 'scratched'), InvalidValueObjectException::class, 'Invalid cosmetic value is rejected');
    $test->throws(fn () => new ConditionAnswer('replacement_parts', 'maybe'), InvalidValueObjectException::class, 'Invalid replacement-parts value is rejected');

    $matcher = new ConditionMatcher();
    $book = engineBook();
    $bookId = $book->id();
    $operatorCases = [
        ['battery_health', ComparisonOperator::EQUALS, 78, true],
        ['screen_condition', ComparisonOperator::NOT_EQUALS, 'excellent', true],
        ['battery_health', ComparisonOperator::LESS_THAN, 80, true],
        ['battery_health', ComparisonOperator::LESS_OR_EQUAL, 78, true],
        ['battery_health', ComparisonOperator::GREATER_THAN, 70, true],
        ['battery_health', ComparisonOperator::GREATER_OR_EQUAL, 78, true],
        ['battery_health', ComparisonOperator::BETWEEN, [70, 80], true],
        ['battery_health', ComparisonOperator::IN, [77, 78], true],
    ];
    foreach ($operatorCases as $index => [$conditionKey, $operator, $comparison, $expected]) {
        $rule = engineRule(100 + $index, $bookId, PricingRuleKind::FIXED_DEDUCTION, 'operator-' . $index, 10, true, ['condition_key' => $conditionKey, 'operator' => $operator, 'comparison' => $comparison, 'amount' => 1]);
        $test->assert($matcher->matches($rule->definition(), $validAnswers) === $expected, $operator . ' comparison matches deterministically');
    }

    $rules = engineOfferRules($bookId);
    $offered = $engine->calculate($book, $rules, engineInput());
    $test->assert($offered->outcome->code() === PricingOutcome::OFFERED, 'Exact base match produces offered result');
    $test->assert($offered->baseAmount?->amount() === 200000, 'Base amount is exact');
    $test->assert($offered->amountAfterFixedDeductions?->amount() === 185000, 'Fixed deduction is applied');
    $test->assert($offered->amountAfterConditionMultipliers?->amount() === 166500, 'Basis-point multiplier uses integer floor');
    $test->assert($offered->amountAfterModeAdjustment?->amount() === 171500, 'Fixed mode adjustment is applied after condition rules');
    $test->assert($offered->finalAmount?->amount() === 172000, 'Final amount uses half-up rounding');
    $test->assert(array_column($offered->toArray()['breakdown'], 'type') === ['base_price', 'fixed_deduction', 'multiplier', 'mode_fixed_adjustment', 'rounding'], 'Breakdown order matches execution order');

    $missingBase = $engine->calculate($book, $rules, engineInput([], ServiceMode::FAST_ONLINE, 'iphone-14'));
    $test->assert($missingBase->outcome->code() === PricingOutcome::CONFIGURATION_ERROR && in_array('missing_base_price', $missingBase->reasonCodes, true), 'Missing base is a configuration error');
    $duplicateBase = $engine->calculate($book, array_merge($rules, [engineRule(9, $bookId, PricingRuleKind::BASE_PRICE, 'base-copy', 11, true, ['amount' => 190000])]), engineInput());
    $test->assert($duplicateBase->outcome->code() === PricingOutcome::CONFIGURATION_ERROR && in_array('duplicate_base_price', $duplicateBase->reasonCodes, true), 'Duplicate enabled base is a configuration error');
    $disabledDuplicate = $engine->calculate($book, array_merge($rules, [engineRule(9, $bookId, PricingRuleKind::BASE_PRICE, 'base-copy', 11, false, ['amount' => 190000])]), engineInput());
    $test->assert($disabledDuplicate->outcome->code() === PricingOutcome::OFFERED, 'Disabled duplicate base is ignored');
    $wrongStorage = $engine->calculate($book, $rules, engineInput([], ServiceMode::FAST_ONLINE, 'iphone-13-pro', 256));
    $test->assert(in_array('missing_base_price', $wrongStorage->reasonCodes, true), 'Wrong storage does not match base price');

    $rejectRule = engineRule(20, $bookId, PricingRuleKind::HARD_REJECT, 'liquid-reject', 5, true, ['condition_key' => 'liquid_damage', 'operator' => ComparisonOperator::EQUALS, 'comparison' => true, 'label' => 'Folyadékkár']);
    $reviewRule = engineRule(21, $bookId, PricingRuleKind::MANUAL_REVIEW, 'parts-review', 6, true, ['condition_key' => 'replacement_parts', 'operator' => ComparisonOperator::EQUALS, 'comparison' => 'non_original', 'label' => 'Alkatrész-ellenőrzés']);
    $both = $engine->calculate($book, array_merge($rules, [$rejectRule, $reviewRule]), engineInput(['liquid_damage' => true, 'replacement_parts' => 'non_original']));
    $test->assert($both->outcome->code() === PricingOutcome::REJECTED && $both->finalAmount === null, 'Hard reject wins and has no offer amount');
    $review = $engine->calculate($book, array_merge($rules, [$reviewRule]), engineInput(['replacement_parts' => 'non_original']));
    $test->assert($review->outcome->code() === PricingOutcome::MANUAL_REVIEW && in_array('parts-review', $review->reasonCodes, true), 'Manual review is returned without a hard reject');

    $zeroBook = engineBook(700002, 0, 1);
    $zeroRules = [engineRule(30, $zeroBook->id(), PricingRuleKind::BASE_PRICE, 'zero-base', 1, true, ['amount' => 10000]), engineRule(31, $zeroBook->id(), PricingRuleKind::FIXED_DEDUCTION, 'large-deduction', 2, true, ['condition_key' => 'powers_on', 'operator' => ComparisonOperator::EQUALS, 'comparison' => true, 'amount' => 15000])];
    $zero = $engine->calculate($zeroBook, $zeroRules, engineInput());
    $test->assert($zero->finalAmount?->amount() === 0, 'Deductions clamp at zero');
    $disabledDeduction = $engine->calculate($zeroBook, [$zeroRules[0], engineRule(31, $zeroBook->id(), PricingRuleKind::FIXED_DEDUCTION, 'large-deduction', 2, false, ['condition_key' => 'powers_on', 'operator' => ComparisonOperator::EQUALS, 'comparison' => true, 'amount' => 15000])], engineInput());
    $test->assert($disabledDeduction->finalAmount?->amount() === 10000, 'Disabled deduction is ignored');

    $multiRules = [engineRule(40, $zeroBook->id(), PricingRuleKind::BASE_PRICE, 'multi-base', 1, true, ['amount' => 101]), engineRule(41, $zeroBook->id(), PricingRuleKind::MULTIPLIER, 'multi-a', 2, true, ['condition_key' => 'powers_on', 'operator' => ComparisonOperator::EQUALS, 'comparison' => true, 'multiplier' => 9000]), engineRule(42, $zeroBook->id(), PricingRuleKind::MULTIPLIER, 'multi-b', 3, true, ['condition_key' => 'powers_on', 'operator' => ComparisonOperator::EQUALS, 'comparison' => true, 'multiplier' => 5000])];
    $multi = $engine->calculate($zeroBook, $multiRules, engineInput());
    $test->assert($multi->finalAmount?->amount() === 45, 'Ordered multipliers floor each integer step');

    $modeMultiplier = engineRule(50, $bookId, PricingRuleKind::MODE_ADJUSTMENT, 'mode-multiplier', 40, true, ['service_mode' => ServiceMode::TRADE_IN, 'multiplier' => 11000]);
    $modeResult = $engine->calculate($book, array_merge(array_slice($rules, 0, 3), [$modeMultiplier]), engineInput([], ServiceMode::TRADE_IN));
    $test->assert($modeResult->amountAfterModeAdjustment?->amount() === 183150, 'Mode multiplier uses integer basis points');
    $neutralMode = $engine->calculate($book, array_slice($rules, 0, 3), engineInput([], ServiceMode::HIGHER_OFFER));
    $test->assert($neutralMode->amountAfterModeAdjustment?->amount() === 166500, 'Missing mode adjustment is neutral');
    $duplicateMode = $engine->calculate($book, array_merge($rules, [engineRule(51, $bookId, PricingRuleKind::MODE_ADJUSTMENT, 'fast-copy', 41, true, ['service_mode' => ServiceMode::FAST_ONLINE, 'amount' => 1])]), engineInput());
    $test->assert(in_array('duplicate_mode_adjustment', $duplicateMode->reasonCodes, true), 'Duplicate mode adjustment is a configuration error');
    foreach (ServiceMode::supportedCodes() as $mode) {
        $test->assert($engine->calculate($book, $rules, engineInput([], $mode))->serviceMode->code() === $mode, "{$mode} calculates independently");
    }

    $manualMinimumBook = engineBook(700003, 300000, 1000, MinimumOfferPolicy::MANUAL_REVIEW);
    $minimumRules = [engineRule(60, $manualMinimumBook->id(), PricingRuleKind::BASE_PRICE, 'minimum-base', 1, true, ['amount' => 200000])];
    $minimumReview = $engine->calculate($manualMinimumBook, $minimumRules, engineInput());
    $test->assert($minimumReview->outcome->code() === PricingOutcome::MANUAL_REVIEW && $minimumReview->finalAmount === null, 'Below minimum manual policy does not invent an offer');
    $rejectMinimumBook = engineBook(700004, 300000, 1000, MinimumOfferPolicy::REJECT);
    $minimumReject = $engine->calculate($rejectMinimumBook, [engineRule(60, $rejectMinimumBook->id(), PricingRuleKind::BASE_PRICE, 'minimum-base', 1, true, ['amount' => 200000])], engineInput());
    $test->assert($minimumReject->outcome->code() === PricingOutcome::REJECTED && $minimumReject->finalAmount === null, 'Below minimum reject policy has no offer');
    $test->assert($engine->roundHalfUp(138499, 1000) === 138000, 'Rounding below half goes down');
    $test->assert($engine->roundHalfUp(138500, 1000) === 139000, 'Rounding at half goes up');
    $test->assert($engine->roundHalfUp(138501, 1000) === 139000, 'Rounding above half goes up');
    $test->assert($engine->roundHalfUp(138501, 1) === 138501, 'Increment one is exact');
    $test->throws(fn () => $engine->roundHalfUp(1000, 0), InvalidArgumentException::class, 'Invalid rounding increment is rejected');

    $bookVersionBefore = $book->version()->value();
    $ruleVersionsBefore = array_map(static fn (PricingRule $rule): int => $rule->version()->value(), $rules);
    $first = $engine->calculate($book, $rules, engineInput())->toArray();
    $shuffled = $rules; shuffle($shuffled);
    $second = $engine->calculate($book, $shuffled, engineInput())->toArray();
    $test->assert($first === $second, 'Shuffled repository order produces byte/value-equivalent result');
    $test->assert($book->version()->value() === $bookVersionBefore, 'Calculation does not mutate price book');
    $test->assert(array_map(static fn (PricingRule $rule): int => $rule->version()->value(), $rules) === $ruleVersionsBefore, 'Calculation does not mutate rules');

    (new CapabilityManager())->grant();
    foreach (['administrator' => true, 'shop_manager' => true, 'customer' => false, 'subscriber' => false] as $role => $allowed) {
        [$userId, $created] = engineUser($role, strtolower($token));
        if ($created) { $createdUsers[] = $userId; }
        wp_set_current_user($userId);
        $nonce = wp_create_nonce(AdminAuthorization::NONCE_ACTION);
        if ($allowed) {
            if ($role === 'administrator') { $authorizedUser = $userId; }
            (new AdminAuthorization())->assert(CapabilityManager::MANAGE_PRICE_BOOKS, $nonce);
            $test->assert(true, "{$role} may preview");
        } else {
            $test->throws(fn () => (new AdminAuthorization())->assert(CapabilityManager::MANAGE_PRICE_BOOKS, $nonce), RuntimeException::class, "{$role} may not preview");
        }
    }
    wp_set_current_user($authorizedUser);
    $test->throws(fn () => (new AdminAuthorization())->assert(CapabilityManager::MANAGE_PRICE_BOOKS, ''), RuntimeException::class, 'Missing nonce is rejected');
    $test->throws(fn () => (new AdminAuthorization())->assert(CapabilityManager::MANAGE_PRICE_BOOKS, 'invalid'), RuntimeException::class, 'Invalid nonce is rejected');

    $catalog = new WordPressDeviceCatalogReader();
    $models = $catalog->iPhoneModels();
    $test->assert($models !== [], 'Read-only catalog returns iPhone model identities');
    $modelKey = $models[0]->modelKey;
    $books = new WordPressPriceBookRepository($wpdb);
    $ruleRepository = new WordPressPricingRuleRepository($wpdb);
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $persistedBook = $books->createDraft(PriceBook::createDraft($books->nextAvailableVersionNumber(), $marker, new CurrencyCode('HUF'), new Money(0, 'HUF'), 1000, new MinimumOfferPolicy(MinimumOfferPolicy::MANUAL_REVIEW), new PricingActorId(max(1, $originalUser)), $now));
    $persistedRule = $ruleRepository->insert(PricingRule::create($persistedBook->id(), new PricingRuleDefinition(new PricingRuleCode('qa-preview-base'), new PricingRuleKind(PricingRuleKind::BASE_PRICE), 'iphone', $modelKey, new StorageCapacity(128), null, null, null, null, new Money(200000, 'HUF'), null, new RulePriority(1), true, null, 'QA preview base'), $now));
    $bookVersionPersisted = $books->getById($persistedBook->id())->version()->value();
    $ruleVersionPersisted = $ruleRepository->getById($persistedRule->id())->version()->value();
    $handler = new PreviewDraftPriceBookCalculationHandler($books, $ruleRepository, $catalog, new PricingEngine());
    $preview = $handler->handle(new PreviewDraftPriceBookCalculation($persistedBook->id()->toInt(), $modelKey, 128, engineAnswers()));
    $test->assert(count($preview->modeResults) === 4, 'Preview handler returns four mode results');
    foreach ($preview->modeResults as $mode => $result) {
        $test->assert($result->outcome->code() === PricingOutcome::OFFERED && $result->serviceMode->code() === $mode, "Preview returns offered result for {$mode}");
    }
    $test->assert($books->getById($persistedBook->id())->version()->value() === $bookVersionPersisted, 'Preview does not modify persisted price book');
    $test->assert($ruleRepository->getById($persistedRule->id())->version()->value() === $ruleVersionPersisted, 'Preview does not modify persisted rule');
    $during = engineCounts($wpdb, $tables);
    $test->assert($during[Schema::REQUESTS] === $before[Schema::REQUESTS], 'Preview creates no request');
    $test->assert($during[Schema::SNAPSHOTS] === $before[Schema::SNAPSHOTS], 'Preview creates no snapshot');
    $test->assert($during[Schema::EVENTS] === $before[Schema::EVENTS], 'Preview creates no event');
} catch (Throwable $exception) {
    $test->fail($exception);
} finally {
    engineCleanup($wpdb, $tables, $marker);
    wp_set_current_user($originalUser);
    foreach ($createdUsers as $userId) { wp_delete_user($userId); }
}

$after = engineCounts($wpdb, $tables);
$legacyAfter = engineLegacyHash($wpdb);
$test->assert($before === $after, 'All QA rows are removed and all tracked table counts return to baseline');
$test->assert($legacyBefore === $legacyAfter, 'Legacy user-meta hash remains unchanged');
$test->finish($before, $after, $marker, $legacyBefore, $legacyAfter);
