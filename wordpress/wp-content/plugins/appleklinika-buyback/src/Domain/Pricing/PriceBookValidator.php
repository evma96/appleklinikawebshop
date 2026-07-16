<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Buyback\ServiceMode;

final class PriceBookValidator
{
    /** @param list<PricingRule> $rules */
    public function validateConfiguration(PriceBook $book, array $rules): PriceBookValidationResult
    {
        $issues = [];

        if ($book->id() === null) {
            $issues[] = 'unpersisted_price_book';
        }
        if (! $book->status()->isDraft()) {
            $issues[] = 'price_book_not_draft';
        }
        if ($book->currency()->code() !== 'HUF') {
            $issues[] = 'invalid_currency';
        }
        if ($book->roundingIncrementMinor() < 1) {
            $issues[] = 'invalid_rounding_increment';
        }
        if (! in_array($book->minimumPolicy()->code(), [MinimumOfferPolicy::MANUAL_REVIEW, MinimumOfferPolicy::REJECT], true)) {
            $issues[] = 'invalid_minimum_policy';
        }

        foreach ($rules as $rule) {
            if ($book->id() === null || ! $rule->priceBookId()->equals($book->id())) {
                $issues[] = 'rule_price_book_mismatch';
                continue;
            }

            $definition = $rule->definition();
            if (! $definition->enabled) {
                continue;
            }
            if ($definition->conditionKey !== null && ! in_array($definition->conditionKey, ConditionDefinition::keys(), true)) {
                $issues[] = 'unknown_condition_key';
                continue;
            }
            if ($definition->serviceMode !== null && ! in_array($definition->serviceMode, ServiceMode::supportedCodes(), true)) {
                $issues[] = 'unsupported_service_mode';
                continue;
            }

            try {
                RuleShapeValidator::assertValid($definition);
            } catch (\Throwable $exception) {
                $issues[] = 'invalid_rule_shape';
            }
        }

        return new PriceBookValidationResult(array_values(array_unique($issues)));
    }

    /** @param list<PricingRule> $rules */
    public function validate(PriceBook $book, array $rules, PricingCalculationInput $input): PriceBookValidationResult
    {
        $issues = $this->validateConfiguration($book, $rules)->issues;

        $baseMatches = 0;
        $modeMatches = 0;
        foreach ($rules as $rule) {
            if ($book->id() === null || ! $rule->priceBookId()->equals($book->id())) {
                $issues[] = 'rule_price_book_mismatch';
                continue;
            }

            $definition = $rule->definition();
            if (! $definition->enabled) {
                continue;
            }
            if ($definition->kind->code() === PricingRuleKind::BASE_PRICE
                && $definition->category === $input->category->code()
                && $definition->modelKey === $input->modelKey->value()
                && $definition->storage?->gigabytes() === $input->storage->gigabytes()) {
                ++$baseMatches;
            }
            if ($definition->kind->code() === PricingRuleKind::MODE_ADJUSTMENT
                && $definition->serviceMode === $input->serviceMode->code()) {
                ++$modeMatches;
            }
        }

        if ($baseMatches === 0) {
            $issues[] = 'missing_base_price';
        } elseif ($baseMatches > 1) {
            $issues[] = 'duplicate_base_price';
        }
        if ($modeMatches > 1) {
            $issues[] = 'duplicate_mode_adjustment';
        }

        return new PriceBookValidationResult(array_values(array_unique($issues)));
    }
}
