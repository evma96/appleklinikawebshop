<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Shared\Money;

final class PricingEngine
{
    public const VERSION = '2B1-1';

    public function __construct(
        private readonly PriceBookValidator $validator = new PriceBookValidator(),
        private readonly ConditionMatcher $matcher = new ConditionMatcher()
    ) {
    }

    /** @param list<PricingRule> $rules */
    public function calculate(PriceBook $book, array $rules, PricingCalculationInput $input): PricingCalculationResult
    {
        $rules = $this->sortRules($rules);
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

        $hardRejects = $this->matchingConditional($enabled, PricingRuleKind::HARD_REJECT, $input);
        if ($hardRejects !== []) {
            return PricingCalculationResult::rejected(
                $book,
                $input->serviceMode,
                array_map(fn (PricingRule $rule): string => $rule->definition()->code->code(), $hardRejects),
                array_map(fn (PricingRule $rule): PricingMatchedRule => $this->matched($rule), $hardRejects),
                $breakdown
            );
        }

        $manualReviews = $this->matchingConditional($enabled, PricingRuleKind::MANUAL_REVIEW, $input);
        if ($manualReviews !== []) {
            return PricingCalculationResult::manualReview(
                $book,
                $input->serviceMode,
                array_map(fn (PricingRule $rule): string => $rule->definition()->code->code(), $manualReviews),
                array_map(fn (PricingRule $rule): PricingMatchedRule => $this->matched($rule), $manualReviews),
                $breakdown
            );
        }

        foreach ($this->matchingConditional($enabled, PricingRuleKind::FIXED_DEDUCTION, $input) as $rule) {
            $before = $amount;
            $deduction = $rule->definition()->amount->amount();
            $amount = max(0, $amount - $deduction);
            $breakdown[] = $this->amountLine('fixed_deduction', $rule, $before, -min($before, $deduction), $amount);
            $matchedRules[] = $this->matched($rule);
        }
        $afterDeductions = new Money($amount, $book->currency()->code());

        foreach ($this->matchingConditional($enabled, PricingRuleKind::MULTIPLIER, $input) as $rule) {
            $before = $amount;
            $basisPoints = $rule->definition()->multiplier->value();
            $amount = intdiv($amount * $basisPoints, BasisPointsMultiplier::ONE);
            $breakdown[] = new PricingBreakdownLine('multiplier', $rule->definition()->code->code(), PricingRuleKind::MULTIPLIER, $before, null, $basisPoints, $amount, $rule->definition()->publicLabel, $rule->definition()->internalNote, $rule->definition()->priority->value());
            $matchedRules[] = $this->matched($rule);
        }
        $afterMultipliers = new Money($amount, $book->currency()->code());

        $modeRules = array_values(array_filter($enabled, static fn (PricingRule $rule): bool => $rule->definition()->kind->code() === PricingRuleKind::MODE_ADJUSTMENT && $rule->definition()->serviceMode === $input->serviceMode->code()));
        if ($modeRules !== []) {
            $modeRule = $modeRules[0];
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

    /** @param list<PricingRule> $rules @return list<PricingRule> */
    private function matchingConditional(array $rules, string $kind, PricingCalculationInput $input): array
    {
        $matching = array_values(array_filter($rules, fn (PricingRule $rule): bool => $this->isMatchingConditional($rule, $input)));
        $specificTargets = [];
        foreach ($matching as $rule) {
            if ($rule->definition()->modelKey === $input->modelKey->value()) {
                $specificTargets[$this->conditionTargetKey($rule)] = true;
            }
        }
        return array_values(array_filter($matching, function (PricingRule $rule) use ($kind, $specificTargets): bool {
            $definition = $rule->definition();
            if ($definition->kind->code() !== $kind) {
                return false;
            }
            return $definition->modelKey !== null || ! isset($specificTargets[$this->conditionTargetKey($rule)]);
        }));
    }

    private function isMatchingConditional(PricingRule $rule, PricingCalculationInput $input): bool
    {
        $definition = $rule->definition();
        return $definition->conditionKey !== null
            && ($definition->modelKey === null || $definition->modelKey === $input->modelKey->value())
            && $this->matcher->matches($definition, $input->conditionAnswers);
    }

    private function conditionTargetKey(PricingRule $rule): string
    {
        $definition = $rule->definition();
        return $definition->conditionKey . '|' . $definition->operator?->code() . '|' . json_encode($definition->comparisonValue, JSON_THROW_ON_ERROR);
    }

    private function matched(PricingRule $rule): PricingMatchedRule
    {
        $definition = $rule->definition();
        return new PricingMatchedRule($definition->code->code(), $definition->kind->code(), $definition->priority->value(), $definition->publicLabel);
    }

    private function amountLine(string $type, PricingRule $rule, int $before, int $adjustment, int $after): PricingBreakdownLine
    {
        $definition = $rule->definition();
        return new PricingBreakdownLine($type, $definition->code->code(), $definition->kind->code(), $before, $adjustment, null, $after, $definition->publicLabel, $definition->internalNote, $definition->priority->value());
    }
}
