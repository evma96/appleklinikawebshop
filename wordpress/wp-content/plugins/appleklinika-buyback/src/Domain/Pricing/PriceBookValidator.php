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

        $modelMinimumKeys = [];
        $modelOfferModeKeys = [];
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

            if ($definition->kind->code() === PricingRuleKind::MINIMUM_OFFER && $definition->modelKey !== null) {
                $key = $definition->category . '|' . $definition->modelKey;
                $modelMinimumKeys[$key] = ($modelMinimumKeys[$key] ?? 0) + 1;
            }
            if ($definition->kind->code() === PricingRuleKind::MODE_ADJUSTMENT && $definition->serviceMode !== null) {
                $key = $definition->category . '|' . ($definition->modelKey ?? 'global') . '|' . $definition->serviceMode;
                $modelOfferModeKeys[$key] = ($modelOfferModeKeys[$key] ?? 0) + 1;
            }
        }
        foreach ($modelMinimumKeys as $count) {
            if ($count > 1) {
                $issues[] = 'duplicate_model_minimum_offer';
                break;
            }
        }
        foreach ($modelOfferModeKeys as $count) {
            if ($count > 1) {
                $issues[] = 'duplicate_mode_adjustment';
                break;
            }
        }

        return new PriceBookValidationResult(array_values(array_unique($issues)));
    }

    /** @param list<PricingRule> $rules */
    public function validate(PriceBook $book, array $rules, PricingCalculationInput $input): PriceBookValidationResult
    {
        $issues = $this->validateConfiguration($book, $rules)->issues;
        if ($book->status()->isActive()) {
            $issues = array_values(array_diff($issues, ['price_book_not_draft']));
        }

        $baseMatches = 0;
        $globalModeMatches = 0;
        $modelModeMatches = 0;
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
                if ($definition->modelKey === null) {
                    ++$globalModeMatches;
                } elseif ($definition->modelKey === $input->modelKey->value()) {
                    ++$modelModeMatches;
                }
            }
        }

        if ($baseMatches === 0) {
            $issues[] = 'missing_base_price';
        } elseif ($baseMatches > 1) {
            $issues[] = 'duplicate_base_price';
        }
        if (($modelModeMatches > 0 ? $modelModeMatches : $globalModeMatches) > 1) {
            $issues[] = 'duplicate_mode_adjustment';
        }

        return new PriceBookValidationResult(array_values(array_unique($issues)));
    }
}
