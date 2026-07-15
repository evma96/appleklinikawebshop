<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Buyback\ServiceMode;
use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class RuleShapeValidator
{
    public static function assertValid(PricingRuleDefinition $rule): void
    {
        $kind = $rule->kind->code();

        if ($kind === PricingRuleKind::MINIMUM_OFFER) {
            throw new InvalidValueObjectException('Rule-level minimum offers are not editable in Phase 2A.');
        }

        if ($kind === PricingRuleKind::BASE_PRICE) {
            self::required($rule->modelKey !== null && $rule->storage !== null && $rule->amount !== null, 'Base price requires model, storage and amount.');
            self::required($rule->serviceMode === null && $rule->conditionKey === null && $rule->operator === null && $rule->comparisonValue === null && $rule->multiplier === null, 'Base price contains conflicting fields.');
            return;
        }

        if ($kind === PricingRuleKind::MODE_ADJUSTMENT) {
            self::required(in_array($rule->serviceMode, [ServiceMode::IN_STORE_INSTANT, ServiceMode::FAST_ONLINE, ServiceMode::HIGHER_OFFER, ServiceMode::TRADE_IN], true), 'Mode adjustment requires a supported service mode.');
            self::required(($rule->amount === null) !== ($rule->multiplier === null), 'Mode adjustment requires exactly one amount or multiplier.');
            self::required($rule->modelKey === null && $rule->storage === null && $rule->conditionKey === null && $rule->operator === null && $rule->comparisonValue === null, 'Mode adjustment contains conflicting fields.');
            return;
        }

        self::required($rule->conditionKey !== null && $rule->operator !== null && $rule->comparisonValue !== null, 'Conditional rule requires condition, operator and value.');
        self::required($rule->modelKey === null && $rule->storage === null && $rule->serviceMode === null, 'Conditional rule contains conflicting target fields.');
        ConditionDefinition::assertValid($rule->conditionKey, $rule->operator, $rule->comparisonValue);

        if ($kind === PricingRuleKind::FIXED_DEDUCTION) {
            self::required($rule->amount !== null && $rule->multiplier === null, 'Fixed deduction requires only an amount.');
            return;
        }

        if ($kind === PricingRuleKind::MULTIPLIER) {
            self::required($rule->multiplier !== null && $rule->amount === null, 'Multiplier rule requires only a basis-points multiplier.');
            return;
        }

        if (in_array($kind, [PricingRuleKind::HARD_REJECT, PricingRuleKind::MANUAL_REVIEW], true)) {
            self::required($rule->amount === null && $rule->multiplier === null, 'Review/reject rules cannot contain financial values.');
            self::required($rule->publicLabel !== null && trim($rule->publicLabel) !== '', 'Review/reject rules require a public label.');
            return;
        }

        throw new InvalidValueObjectException('Unsupported Phase 2A pricing-rule shape.');
    }

    private static function required(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new InvalidValueObjectException($message);
        }
    }
}
