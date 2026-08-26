<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Shared\Money;

final class PricingEngine
{
    public const VERSION = '2B1-1';

    public function __construct(
        private readonly PriceBookValidator $validator = new PriceBookValidator(),
        private readonly ConditionMatcher $matcher = new ConditionMatcher(),
        private readonly SystemDefaultQuestionnairePolicy $questionnairePolicy = new SystemDefaultQuestionnairePolicy()
    ) {
    }

    /** @param list<PricingRule> $rules */
    public function calculate(PriceBook $book, array $rules, PricingCalculationInput $input): PricingCalculationResult
    {
        $rules = $this->sortRules(array_merge($rules, $book->id() === null ? [] : $this->questionnairePolicy->inheritedRules($book->id())));
        $validation = $this->validator->validate($book, $rules, $input);
        if (! $validation->isValid()) {
            return PricingCalculationResult::configurationError($book, $input->serviceMode, $validation->issues);
        }

        $enabled = array_values(array_filter($rules, static fn (PricingRule $rule): bool => $rule->definition()->enabled));
        $baseRule = array_values(array_filter($enabled, fn (PricingRule $rule): bool => $this->isMatchingBase($rule, $input)))[0];
        $base = $baseRule->definition()->amount;
        $amount = $base->amount();
        $breakdown = [new PricingBreakdownLine(
            'base_price',
            $baseRule->definition()->code->code(),
            PricingRuleKind::BASE_PRICE,
            0,
            $amount,
            null,
            $amount,
            $baseRule->definition()->publicLabel,
            $baseRule->definition()->internalNote,
            $baseRule->definition()->priority->value()
        )];
        $matchedRules = [$this->matched($baseRule)];

        $conditional = $this->effectiveConditionalRules(
            array_values(array_filter($enabled, fn (PricingRule $rule): bool => $this->isMatchingConditional($rule, $input))),
            $input
        );
        $componentTrace = $this->componentTrace($conditional);
        $matchedRules = $this->mergeMatchedRules(
            $matchedRules,
            array_values(array_filter($componentTrace, static fn (PricingMatchedRule $rule): bool => $rule->ruleKind === PricingRuleKind::NO_CHANGE))
        );
        $hardRejects = $this->rulesOfKind($conditional, PricingRuleKind::HARD_REJECT);
        if ($hardRejects !== []) {
            return PricingCalculationResult::rejected(
                $book,
                $input->serviceMode,
                array_map(fn (PricingRule $rule): string => $rule->definition()->code->code(), $hardRejects),
                $this->mergeMatchedRules($componentTrace, array_map(fn (PricingRule $rule): PricingMatchedRule => $this->matched($rule), $hardRejects)),
                $breakdown
            );
        }

        $manualReviews = $this->rulesOfKind($conditional, PricingRuleKind::MANUAL_REVIEW);
        if ($manualReviews !== []) {
            return PricingCalculationResult::manualReview(
                $book,
                $input->serviceMode,
                array_map(fn (PricingRule $rule): string => $rule->definition()->code->code(), $manualReviews),
                $this->mergeMatchedRules($componentTrace, array_map(fn (PricingRule $rule): PricingMatchedRule => $this->matched($rule), $manualReviews)),
                $breakdown
            );
        }

        foreach ($this->rulesOfKind($conditional, PricingRuleKind::FIXED_DEDUCTION) as $rule) {
            $before = $amount;
            $deduction = $rule->definition()->amount->amount();
            $amount = max(0, $amount - $deduction);
            $breakdown[] = $this->amountLine('fixed_deduction', $rule, $before, -min($before, $deduction), $amount);
            $matchedRules[] = $this->matched($rule);
        }
        $afterDeductions = new Money($amount, $book->currency()->code());

        foreach ($this->rulesOfKind($conditional, PricingRuleKind::MULTIPLIER) as $rule) {
            $before = $amount;
            $basisPoints = $rule->definition()->multiplier->value();
            $amount = intdiv($amount * $basisPoints, BasisPointsMultiplier::ONE);
            $breakdown[] = new PricingBreakdownLine('multiplier', $rule->definition()->code->code(), PricingRuleKind::MULTIPLIER, $before, null, $basisPoints, $amount, $rule->definition()->publicLabel, $rule->definition()->internalNote, $rule->definition()->priority->value());
            $matchedRules[] = $this->matched($rule);
        }
        $afterMultipliers = new Money($amount, $book->currency()->code());

        $modelMinimum = $this->modelMinimumOffer($enabled, $input);
        if ($modelMinimum !== null && $amount <= $modelMinimum->definition()->amount->amount()) {
            $breakdown[] = new PricingBreakdownLine(
                'model_minimum_policy',
                $modelMinimum->definition()->code->code(),
                PricingRuleKind::MINIMUM_OFFER,
                $amount,
                null,
                null,
                $amount,
                null,
                'manual_review',
                $modelMinimum->definition()->priority->value()
            );
            $matchedRules[] = $this->matched($modelMinimum);
            return PricingCalculationResult::manualReview(
                $book,
                $input->serviceMode,
                ['below_model_minimum_offer'],
                $matchedRules,
                $breakdown
            );
        }

        $modeRule = $this->effectiveModeAdjustment($enabled, $input);
        if ($modeRule !== null) {
            $before = $amount;
            if ($modeRule->definition()->amount !== null) {
                $adjustment = $modeRule->definition()->amount->amount();
                $amount += $adjustment;
                $breakdown[] = $this->amountLine('mode_fixed_adjustment', $modeRule, $before, $adjustment, $amount);
            } else {
                $basisPoints = $modeRule->definition()->multiplier->value();
                $amount = intdiv($amount * $basisPoints, BasisPointsMultiplier::ONE);
                $breakdown[] = new PricingBreakdownLine('mode_multiplier', $modeRule->definition()->code->code(), PricingRuleKind::MODE_ADJUSTMENT, $before, null, $basisPoints, $amount, $modeRule->definition()->publicLabel, $modeRule->definition()->internalNote, $modeRule->definition()->priority->value());
            }
            $matchedRules[] = $this->matched($modeRule);
        }
        $amount = max(0, $amount);
        $afterMode = new Money($amount, $book->currency()->code());
        $raw = new Money($amount, $book->currency()->code());

        if ($amount < $book->minimumOffer()->amount()) {
            $breakdown[] = new PricingBreakdownLine('minimum_policy', null, null, $amount, null, null, $amount, null, $book->minimumPolicy()->code(), PHP_INT_MAX - 1);
            if ($book->minimumPolicy()->code() === MinimumOfferPolicy::REJECT) {
                return PricingCalculationResult::rejected($book, $input->serviceMode, ['below_minimum_offer'], $matchedRules, $breakdown);
            }
            return PricingCalculationResult::manualReview($book, $input->serviceMode, ['below_minimum_offer'], $matchedRules, $breakdown);
        }

        $rounded = $this->roundHalfUp($amount, $book->roundingIncrementMinor());
        if ($rounded !== $amount) {
            $breakdown[] = new PricingBreakdownLine('rounding', null, null, $amount, $rounded - $amount, null, $rounded, null, 'half_up', PHP_INT_MAX);
        }

        return PricingCalculationResult::offered(
            $book,
            $input->serviceMode,
            $base,
            $afterDeductions,
            $afterMultipliers,
            $afterMode,
            $raw,
            new Money($rounded, $book->currency()->code()),
            $breakdown,
            $matchedRules,
            self::VERSION
        );
    }

    public function roundHalfUp(int $amount, int $increment): int
    {
        if ($increment < 1) {
            throw new \InvalidArgumentException('Rounding increment must be positive.');
        }
        $quotient = intdiv($amount, $increment);
        $remainder = $amount % $increment;
        return ($quotient + (($remainder * 2) >= $increment ? 1 : 0)) * $increment;
    }

    /** @param list<PricingRule> $rules @return list<PricingRule> */
    private function sortRules(array $rules): array
    {
        usort($rules, static function (PricingRule $left, PricingRule $right): int {
            $priority = $left->definition()->priority->value() <=> $right->definition()->priority->value();
            if ($priority !== 0) {
                return $priority;
            }
            $leftId = $left->id()?->toInt();
            $rightId = $right->id()?->toInt();
            if ($leftId !== null && $rightId !== null && $leftId !== $rightId) {
                return $leftId <=> $rightId;
            }
            return strcmp($left->definition()->code->code(), $right->definition()->code->code());
        });
        return $rules;
    }

    private function isMatchingBase(PricingRule $rule, PricingCalculationInput $input): bool
    {
        $definition = $rule->definition();
        return $definition->kind->code() === PricingRuleKind::BASE_PRICE
            && $definition->category === $input->category->code()
            && $definition->modelKey === $input->modelKey->value()
            && $definition->storage?->gigabytes() === $input->storage->gigabytes();
    }

    /** @param list<PricingRule> $rules */
    private function modelMinimumOffer(array $rules, PricingCalculationInput $input): ?PricingRule
    {
        foreach ($rules as $rule) {
            $definition = $rule->definition();
            if ($definition->kind->code() === PricingRuleKind::MINIMUM_OFFER
                && $definition->modelKey === $input->modelKey->value()) {
                return $rule;
            }
        }
        return null;
    }

    /**
     * An explicit model/mode adjustment replaces, rather than stacks with, the
     * price-book-wide adjustment. Keeping the fallback here makes the public
     * flow, draft preview and persisted request calculation share one rule.
     *
     * @param list<PricingRule> $rules
     */
    private function effectiveModeAdjustment(array $rules, PricingCalculationInput $input): ?PricingRule
    {
        $global = null;
        foreach ($rules as $rule) {
            $definition = $rule->definition();
            if ($definition->kind->code() !== PricingRuleKind::MODE_ADJUSTMENT
                || $definition->serviceMode !== $input->serviceMode->code()) {
                continue;
            }
            if ($definition->modelKey === $input->modelKey->value()) {
                return $rule;
            }
            if ($definition->modelKey === null) {
                $global = $rule;
            }
        }
        return $global;
    }

    /** @param list<PricingRule> $rules @return list<PricingRule> */
    private function rulesOfKind(array $rules, string $kind): array
    {
        return array_values(array_filter(
            $rules,
            static fn (PricingRule $rule): bool => $rule->definition()->kind->code() === $kind
        ));
    }

    /**
     * A global conditional rule is a legacy fallback only. It is removed before
     * outcome aggregation when the current model has an exact matching rule for
     * the same semantic slot. This keeps a fallback manual-review/rejection
     * rule from overriding a model-specific monetary rule later in calculation.
     *
     * @param list<PricingRule> $matching
     * @return list<PricingRule>
     */
    private function effectiveConditionalRules(array $matching, PricingCalculationInput $input): array
    {
        $modelTargets = [];
        $globalTargets = [];
        $hasMatchingModelBatteryRule = false;

        foreach ($matching as $rule) {
            $definition = $rule->definition();
            if ($definition->modelKey !== $input->modelKey->value()) {
                if ($definition->modelKey === null && ! SystemDefaultQuestionnairePolicy::isInheritedRule($rule)) {
                    $globalTargets[$this->conditionTargetKey($rule)] = true;
                }
                continue;
            }

            if ($this->isBatteryCondition($rule)) {
                $hasMatchingModelBatteryRule = true;
                continue;
            }

            $modelTargets[$this->conditionTargetKey($rule)] = true;
        }

        $effective = array_values(array_filter($matching, function (PricingRule $rule) use ($modelTargets, $globalTargets, $hasMatchingModelBatteryRule): bool {
            $definition = $rule->definition();
            if ($definition->modelKey !== null) {
                return true;
            }

            if (SystemDefaultQuestionnairePolicy::isInheritedRule($rule)) {
                $target = $this->conditionTargetKey($rule);
                return ! isset($modelTargets[$target]) && ! isset($globalTargets[$target]);
            }

            if ($this->isBatteryCondition($rule)) {
                return ! $hasMatchingModelBatteryRule;
            }

            return ! isset($modelTargets[$this->conditionTargetKey($rule)]);
        }));

        return $this->withoutReplacedServiceHistoryRules($effective, $input);
    }

    private function isBatteryCondition(PricingRule $rule): bool
    {
        return $rule->definition()->conditionKey === 'battery_health';
    }

    private function isMatchingConditional(PricingRule $rule, PricingCalculationInput $input): bool
    {
        $definition = $rule->definition();
        return $definition->conditionKey !== null
            && ($definition->modelKey === null || $definition->modelKey === $input->modelKey->value())
            && $this->matcher->matches($definition, $input->conditionAnswers, $input->affectedComponentKeys);
    }

    private function conditionTargetKey(PricingRule $rule): string
    {
        $definition = $rule->definition();
        return $definition->conditionKey . '|' . $definition->affectedComponentKey . '|' . $definition->operator?->code() . '|' . json_encode($definition->comparisonValue, JSON_THROW_ON_ERROR);
    }

    private function matched(PricingRule $rule): PricingMatchedRule
    {
        $definition = $rule->definition();
        $source = $definition->affectedComponentKey !== null
            ? 'service_history_component_override'
            : (str_starts_with($definition->code->code(), SystemDefaultQuestionnairePolicy::CODE_PREFIX)
            ? 'system_default'
            : ($definition->modelKey === null ? 'price_book_global' : 'model_specific'));

        return new PricingMatchedRule(
            $definition->code->code(),
            $definition->kind->code(),
            $definition->priority->value(),
            $definition->publicLabel,
            $source,
            $definition->conditionKey,
            is_int($definition->comparisonValue) || is_bool($definition->comparisonValue) || is_string($definition->comparisonValue) ? $definition->comparisonValue : null,
            $definition->affectedComponentKey
        );
    }

    /** @param list<PricingRule> $rules @return list<PricingMatchedRule> */
    private function componentTrace(array $rules): array
    {
        return array_map(
            fn (PricingRule $rule): PricingMatchedRule => $this->matched($rule),
            array_values(array_filter($rules, static fn (PricingRule $rule): bool => $rule->definition()->affectedComponentKey !== null))
        );
    }

    /** @param list<PricingMatchedRule> $left @param list<PricingMatchedRule> $right @return list<PricingMatchedRule> */
    private function mergeMatchedRules(array $left, array $right): array
    {
        $merged = [];
        foreach (array_merge($left, $right) as $rule) {
            $merged[$rule->ruleCode] = $rule;
        }
        return array_values($merged);
    }

    /**
     * A component override replaces the generic service-history consequence for
     * that selected component. When every selected component has an override,
     * no generic service-history rule remains to apply to the device.
     *
     * @param list<PricingRule> $rules
     * @return list<PricingRule>
     */
    private function withoutReplacedServiceHistoryRules(array $rules, PricingCalculationInput $input): array
    {
        if ($input->affectedComponentKeys === []) {
            return $rules;
        }

        $serviceHistory = $input->conditionAnswers->get('replacement_parts');
        if (! is_string($serviceHistory) || $serviceHistory === 'none_known') {
            return $rules;
        }

        $overridden = [];
        foreach ($rules as $rule) {
            $definition = $rule->definition();
            if ($definition->conditionKey === 'replacement_parts'
                && $definition->comparisonValue === $serviceHistory
                && $definition->affectedComponentKey !== null) {
                $overridden[$definition->affectedComponentKey] = true;
            }
        }
        if (array_diff($input->affectedComponentKeys, array_keys($overridden)) !== []) {
            return $rules;
        }

        return array_values(array_filter($rules, static function (PricingRule $rule) use ($serviceHistory): bool {
            $definition = $rule->definition();
            return ! ($definition->conditionKey === 'replacement_parts'
                && $definition->comparisonValue === $serviceHistory
                && $definition->affectedComponentKey === null);
        }));
    }

    private function amountLine(string $type, PricingRule $rule, int $before, int $adjustment, int $after): PricingBreakdownLine
    {
        $definition = $rule->definition();
        return new PricingBreakdownLine($type, $definition->code->code(), $definition->kind->code(), $before, $adjustment, null, $after, $definition->publicLabel, $definition->internalNote, $definition->priority->value());
    }
}
