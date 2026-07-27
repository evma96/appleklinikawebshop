<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Handler;

use AppleKlinika\Buyback\Application\Command\SaveDraftQuestionnaireConditions;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException;
use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoQuestionnaire;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\DeviceCatalogReader;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\PricingRuleRepository;
use AppleKlinika\Buyback\Application\Port\TransactionManager;
use AppleKlinika\Buyback\Domain\Pricing\BasisPointsMultiplier;
use AppleKlinika\Buyback\Domain\Pricing\ComparisonOperator;
use AppleKlinika\Buyback\Domain\Pricing\PricingRule;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleCode;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleDefinition;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\RulePriority;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Domain\Shared\Money;

/** Reconciles only the draft's deterministic public-questionnaire condition rules. */
final class SaveDraftQuestionnaireConditionsHandler
{
    public const ACTION_SYSTEM_DEFAULT = 'system_default';
    public const ACTION_NONE = 'none';
    public const ACTION_FIXED = 'fixed';
    public const ACTION_PERCENTAGE = 'percentage';
    public const ACTION_MANUAL_REVIEW = 'manual_review';
    public const ACTION_HARD_REJECT = 'hard_reject';
    public const ACTION_INHERIT = 'inherit';

    public function __construct(
        private readonly PriceBookRepository $books,
        private readonly PricingRuleRepository $rules,
        private readonly TransactionManager $transactions,
        private readonly Clock $clock,
        private readonly LocalDemoQuestionnaire $questionnaire,
        private readonly DeviceCatalogReader $catalog
    ) {
    }

    public function handle(SaveDraftQuestionnaireConditions $command): void
    {
        $this->transactions->transactional(function () use ($command): void {
            $bookId = new PriceBookId($command->priceBookId);
            $book = $this->books->getById($bookId);
            if ($book === null) {
                throw PriceBookNotFoundException::forId($bookId);
            }
            $book->assertDraftMutation();
            $this->assertSupportedModel($command->modelKey);

            $submitted = $this->validatedSubmission($command->conditions);
            $componentSubmitted = $command->serviceHistoryComponents === []
                ? []
                : $this->validatedComponentSubmission($command->serviceHistoryComponents);
            $existing = $this->existingRules($bookId, $this->rules->listForPriceBook($bookId));
            $existingComponents = $this->existingComponentRules($bookId, $this->rules->listForPriceBook($bookId));
            $changed = false;
            $at = $this->clock->now();

            foreach ($submitted as $item) {
                $identity = self::ruleCode($bookId->toInt(), $command->modelKey, $item['question_key'], $item['answer_key']);
                $rule = $existing[$identity] ?? null;
                if ($item['action'] === self::ACTION_SYSTEM_DEFAULT) {
                    if ($rule !== null && $rule->id() !== null) {
                        $this->rules->deleteDraftRule($bookId, $rule->id(), $rule->version());
                        $changed = true;
                    }
                    continue;
                }

                $definition = $this->definition($bookId->toInt(), $command->modelKey, $item);
                if ($rule === null) {
                    if (! $this->rules->isCodeUnique($bookId, $definition->code)) {
                        throw new \InvalidArgumentException('Az állapotválaszhoz tartozó szabályazonosító már foglalt. Frissítsd az oldalt és próbáld újra.');
                    }
                    $this->rules->insert(PricingRule::create($bookId, $definition, $at));
                    $changed = true;
                    continue;
                }
                if ($this->sameDefinition($rule->definition(), $definition)) {
                    continue;
                }
                $expectedRuleVersion = $rule->version();
                $rule->update($definition, $at);
                $this->rules->update($rule, $expectedRuleVersion);
                $changed = true;
            }

            foreach ($componentSubmitted as $item) {
                $identity = self::componentRuleCode($bookId->toInt(), $command->modelKey, $item['service_history_key'], $item['component_key']);
                $rule = $existingComponents[$identity] ?? null;
                if ($item['action'] === self::ACTION_INHERIT) {
                    if ($rule !== null && $rule->id() !== null) {
                        $this->rules->deleteDraftRule($bookId, $rule->id(), $rule->version());
                        $changed = true;
                    }
                    continue;
                }

                $definition = $this->componentDefinition($bookId->toInt(), $command->modelKey, $item);
                if ($rule === null) {
                    if (! $this->rules->isCodeUnique($bookId, $definition->code)) {
                        throw new \InvalidArgumentException('Az alkatrészhez tartozó szervizelőzmény-szabályazonosító már foglalt. Frissítsd az oldalt és próbáld újra.');
                    }
                    $this->rules->insert(PricingRule::create($bookId, $definition, $at));
                    $changed = true;
                    continue;
                }
                if ($this->sameDefinition($rule->definition(), $definition)) {
                    continue;
                }
                $expectedRuleVersion = $rule->version();
                $rule->update($definition, $at);
                $this->rules->update($rule, $expectedRuleVersion);
                $changed = true;
            }

            if ($changed) {
                $book->recordRuleMutation($at);
                $this->books->saveDraft($book, new AggregateVersion($command->expectedBookVersion));
            }
        });
    }

    public static function ruleCode(int $priceBookId, string $modelKey, string $questionKey, string $answerKey): string
    {
        return 'questionnaire-condition-' . $priceBookId . '-' . substr(hash('sha256', $modelKey . "\0" . $questionKey . "\0" . $answerKey), 0, 16);
    }

    public static function legacyRuleCode(int $priceBookId, string $questionKey, string $answerKey): string
    {
        return 'questionnaire-condition-' . $priceBookId . '-' . substr(hash('sha256', $questionKey . "\0" . $answerKey), 0, 16);
    }

    public static function componentRuleCode(int $priceBookId, string $modelKey, string $serviceHistoryKey, string $componentKey): string
    {
        return 'service-history-component-' . $priceBookId . '-' . substr(hash('sha256', $modelKey . "\0" . $serviceHistoryKey . "\0" . $componentKey), 0, 16);
    }

    /** @param list<PricingRule> $rules @return array<string,PricingRule> */
    private function existingRules(PriceBookId $bookId, array $rules): array
    {
        $existing = [];
        foreach ($rules as $rule) {
            $code = $rule->definition()->code->code();
            if (! str_starts_with($code, 'questionnaire-condition-' . $bookId->toInt() . '-')) {
                continue;
            }
            if (isset($existing[$code])) {
                throw new \RuntimeException('Egy kérdőívválaszhoz több állapotlevonási szabály tartozik. A mentés biztonsági okból leállt.');
            }
            $existing[$code] = $rule;
        }
        return $existing;
    }

    /** @param list<PricingRule> $rules @return array<string,PricingRule> */
    private function existingComponentRules(PriceBookId $bookId, array $rules): array
    {
        $existing = [];
        foreach ($rules as $rule) {
            $code = $rule->definition()->code->code();
            if (! str_starts_with($code, 'service-history-component-' . $bookId->toInt() . '-')) {
                continue;
            }
            if (isset($existing[$code])) {
                throw new \RuntimeException('Egy szervizelőzmény–alkatrész párhoz több szabály tartozik. A mentés biztonsági okból leállt.');
            }
            $existing[$code] = $rule;
        }
        return $existing;
    }

    /**
     * @param array<string,mixed> $raw
     * @return list<array{question_key:string,answer_key:string,label:string,condition_key:string,comparison_value:int|bool|string,action:string,value:?int,priority:int}>
     */
    private function validatedSubmission(array $raw): array
    {
        $expected = [];
        $priority = 5000;
        foreach ($this->questionnaire->conditionEditorQuestions() as $question) {
            foreach ($question['options'] as $option) {
                if (! $option['configurable']) {
                    continue;
                }
                $questionKey = $question['question_key'];
                $answerKey = $option['answer_key'];
                $expected[$questionKey][$answerKey] = [
                    'question_key' => $questionKey,
                    'answer_key' => $answerKey,
                    'label' => $question['label'] . ': ' . $option['label'],
                    'condition_key' => $option['condition_key'],
                    'comparison_value' => $option['comparison_value'],
                    'priority' => $priority++,
                ];
            }
        }

        foreach ($raw as $questionKey => $answers) {
            if (! is_array($answers) || ! isset($expected[$questionKey])) {
                throw new \InvalidArgumentException('Ismeretlen kérdőívválasz nem menthető.');
            }
            foreach ($answers as $answerKey => $row) {
                if (! is_array($row) || ! isset($expected[$questionKey][$answerKey])) {
                    throw new \InvalidArgumentException('Ismeretlen kérdőívválasz nem menthető.');
                }
            }
        }

        $validated = [];
        foreach ($expected as $questionKey => $answers) {
            foreach ($answers as $answerKey => $definition) {
                $row = $raw[$questionKey][$answerKey] ?? null;
                if (! is_array($row)) {
                    throw new \InvalidArgumentException('Minden szerkeszthető kérdőívválaszhoz válassz műveletet.');
                }
                $action = $row['action'] ?? null;
                if (! is_string($action) || ! in_array($action, self::actions(), true)) {
                    throw new \InvalidArgumentException('Érvénytelen állapotlevonási művelet.');
                }
                $value = $this->valueForAction($action, $row['value'] ?? null);
                $definition['action'] = $action;
                $definition['value'] = $value;
                $validated[] = $definition;
            }
        }
        return $validated;
    }

    /** @return list<string> */
    private static function actions(): array
    {
        return [self::ACTION_SYSTEM_DEFAULT, self::ACTION_NONE, self::ACTION_FIXED, self::ACTION_PERCENTAGE, self::ACTION_MANUAL_REVIEW, self::ACTION_HARD_REJECT];
    }

    /** @return list<string> */
    public static function componentActions(): array
    {
        return [self::ACTION_INHERIT, self::ACTION_NONE, self::ACTION_FIXED, self::ACTION_PERCENTAGE, self::ACTION_MANUAL_REVIEW, self::ACTION_HARD_REJECT];
    }

    /**
     * @param array<string,mixed> $raw
     * @return list<array{service_history_key:string,component_key:string,label:string,action:string,value:?int,priority:int}>
     */
    private function validatedComponentSubmission(array $raw): array
    {
        $metadata = $this->questionnaire->serviceHistoryComponentRuleMetadata();
        $expected = [];
        $priority = 6000;
        foreach ($metadata['service_history'] as $history) {
            foreach ($metadata['components'] as $component) {
                $expected[$history['answer_key']][$component['component_key']] = [
                    'service_history_key' => $history['answer_key'],
                    'component_key' => $component['component_key'],
                    'label' => $history['label'] . ' · ' . $component['label'],
                    'allows_monetary' => $component['allows_monetary'],
                    'priority' => $priority++,
                ];
            }
        }

        foreach ($raw as $historyKey => $components) {
            if (! is_array($components) || ! isset($expected[$historyKey])) {
                throw new \InvalidArgumentException('Ismeretlen szervizelőzményhez tartozó alkatrészszabály nem menthető.');
            }
            foreach ($components as $componentKey => $row) {
                if (! is_array($row) || ! isset($expected[$historyKey][$componentKey])) {
                    throw new \InvalidArgumentException('Ismeretlen szervizelőzményhez tartozó alkatrészszabály nem menthető.');
                }
            }
        }

        $validated = [];
        foreach ($expected as $historyKey => $components) {
            foreach ($components as $componentKey => $definition) {
                $row = $raw[$historyKey][$componentKey] ?? null;
                if (! is_array($row)) {
                    throw new \InvalidArgumentException('Minden szervizelőzmény–alkatrész párhoz válassz műveletet.');
                }
                $action = $row['action'] ?? null;
                if (! is_string($action) || ! in_array($action, self::componentActions(), true)) {
                    throw new \InvalidArgumentException('Érvénytelen alkatrészhez tartozó szervizelőzmény-művelet.');
                }
                if (! $definition['allows_monetary'] && in_array($action, [self::ACTION_FIXED, self::ACTION_PERCENTAGE], true)) {
                    throw new \InvalidArgumentException('Az Egyéb alkatrészhez automatikus pénzbeli levonás nem állítható be.');
                }
                $definition['action'] = $action;
                $definition['value'] = $this->valueForAction($action, $row['value'] ?? null);
                unset($definition['allows_monetary']);
                $validated[] = $definition;
            }
        }

        return $validated;
    }

    private function valueForAction(string $action, mixed $raw): ?int
    {
        if (in_array($action, [self::ACTION_SYSTEM_DEFAULT, self::ACTION_INHERIT, self::ACTION_NONE, self::ACTION_MANUAL_REVIEW, self::ACTION_HARD_REJECT], true)) {
            if ($raw !== null && $raw !== '') {
                throw new \InvalidArgumentException('Ehhez a művelethez nem tartozhat összeg vagy százalékos érték.');
            }
            return null;
        }
        if (! is_string($raw) && ! is_int($raw)) {
            throw new \InvalidArgumentException('A levonás értéke nemnegatív egész szám legyen.');
        }
        $value = (string) $raw;
        if (preg_match('/^[1-9][0-9]*$/', $value) !== 1 || (int) $value > PHP_INT_MAX) {
            throw new \InvalidArgumentException('A levonás értéke pozitív egész szám legyen.');
        }
        $number = (int) $value;
        if ($action === self::ACTION_PERCENTAGE && $number > 100) {
            throw new \InvalidArgumentException('A százalékos levonás 1 és 100 közötti egész érték legyen.');
        }
        return $number;
    }

    /** @param array{question_key:string,answer_key:string,label:string,condition_key:string,comparison_value:int|bool|string,action:string,value:?int,priority:int} $item */
    private function definition(int $priceBookId, string $modelKey, array $item): PricingRuleDefinition
    {
        $kind = match ($item['action']) {
            self::ACTION_NONE => PricingRuleKind::NO_CHANGE,
            self::ACTION_FIXED => PricingRuleKind::FIXED_DEDUCTION,
            self::ACTION_PERCENTAGE => PricingRuleKind::MULTIPLIER,
            self::ACTION_MANUAL_REVIEW => PricingRuleKind::MANUAL_REVIEW,
            self::ACTION_HARD_REJECT => PricingRuleKind::HARD_REJECT,
        };
        $amount = $item['action'] === self::ACTION_FIXED ? new Money($item['value'] ?? 0, 'HUF') : null;
        $multiplier = $item['action'] === self::ACTION_PERCENTAGE
            ? new BasisPointsMultiplier((100 - ($item['value'] ?? 0)) * 100)
            : null;

        return new PricingRuleDefinition(
            new PricingRuleCode(self::ruleCode($priceBookId, $modelKey, $item['question_key'], $item['answer_key'])),
            new PricingRuleKind($kind),
            'iphone',
            $modelKey,
            null,
            null,
            $item['condition_key'],
            new ComparisonOperator(ComparisonOperator::EQUALS),
            $item['comparison_value'],
            $amount,
            $multiplier,
            new RulePriority($item['priority']),
            true,
            in_array($kind, [PricingRuleKind::MANUAL_REVIEW, PricingRuleKind::HARD_REJECT], true) ? $item['label'] : null,
            null
        );
    }

    /** @param array{service_history_key:string,component_key:string,label:string,action:string,value:?int,priority:int} $item */
    private function componentDefinition(int $priceBookId, string $modelKey, array $item): PricingRuleDefinition
    {
        $kind = match ($item['action']) {
            self::ACTION_NONE => PricingRuleKind::NO_CHANGE,
            self::ACTION_FIXED => PricingRuleKind::FIXED_DEDUCTION,
            self::ACTION_PERCENTAGE => PricingRuleKind::MULTIPLIER,
            self::ACTION_MANUAL_REVIEW => PricingRuleKind::MANUAL_REVIEW,
            self::ACTION_HARD_REJECT => PricingRuleKind::HARD_REJECT,
        };
        $amount = $item['action'] === self::ACTION_FIXED ? new Money($item['value'] ?? 0, 'HUF') : null;
        $multiplier = $item['action'] === self::ACTION_PERCENTAGE
            ? new BasisPointsMultiplier((100 - ($item['value'] ?? 0)) * 100)
            : null;

        return new PricingRuleDefinition(
            new PricingRuleCode(self::componentRuleCode($priceBookId, $modelKey, $item['service_history_key'], $item['component_key'])),
            new PricingRuleKind($kind),
            'iphone',
            $modelKey,
            null,
            null,
            'replacement_parts',
            new ComparisonOperator(ComparisonOperator::EQUALS),
            $item['service_history_key'],
            $amount,
            $multiplier,
            new RulePriority($item['priority']),
            true,
            in_array($kind, [PricingRuleKind::MANUAL_REVIEW, PricingRuleKind::HARD_REJECT], true) ? $item['label'] : null,
            null,
            $item['component_key']
        );
    }

    private function sameDefinition(PricingRuleDefinition $left, PricingRuleDefinition $right): bool
    {
        return $left->kind->code() === $right->kind->code()
            && $left->conditionKey === $right->conditionKey
            && $left->operator?->code() === $right->operator?->code()
            && $left->comparisonValue === $right->comparisonValue
            && $left->amount?->amount() === $right->amount?->amount()
            && $left->multiplier?->value() === $right->multiplier?->value()
            && $left->priority->value() === $right->priority->value()
            && $left->enabled === $right->enabled
            && $left->publicLabel === $right->publicLabel
            && $left->affectedComponentKey === $right->affectedComponentKey;
    }

    private function assertSupportedModel(string $modelKey): void
    {
        foreach ($this->catalog->iPhoneModels() as $model) {
            if ($model->modelKey === $modelKey) {
                return;
            }
        }
        throw new \InvalidArgumentException('A kiválasztott iPhone modell nem szerepel az inventory készülékkatalógusban.');
    }
}
