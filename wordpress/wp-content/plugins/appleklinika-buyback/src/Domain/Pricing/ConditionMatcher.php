<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

final class ConditionMatcher
{
    public function matches(PricingRuleDefinition $definition, ConditionAnswerCollection $answers): bool
    {
        if ($definition->conditionKey === null || $definition->operator === null) {
            return false;
        }

        $actual = $answers->get($definition->conditionKey);
        $expected = $definition->comparisonValue;

        return match ($definition->operator->code()) {
            ComparisonOperator::EQUALS => $actual === $expected,
            ComparisonOperator::NOT_EQUALS => $actual !== $expected,
            ComparisonOperator::LESS_THAN => is_int($actual) && is_int($expected) && $actual < $expected,
            ComparisonOperator::LESS_OR_EQUAL => is_int($actual) && is_int($expected) && $actual <= $expected,
            ComparisonOperator::GREATER_THAN => is_int($actual) && is_int($expected) && $actual > $expected,
            ComparisonOperator::GREATER_OR_EQUAL => is_int($actual) && is_int($expected) && $actual >= $expected,
            ComparisonOperator::BETWEEN => is_int($actual) && is_array($expected) && $actual >= $expected[0] && $actual <= $expected[1],
            ComparisonOperator::IN => is_array($expected) && in_array($actual, $expected, true),
            default => false,
        };
    }
}
