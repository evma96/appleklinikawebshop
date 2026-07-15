<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;
use AppleKlinika\Buyback\Domain\Shared\Money;

final class PricingRuleDefinition
{
    public function __construct(
        public readonly PricingRuleCode $code,
        public readonly PricingRuleKind $kind,
        public readonly string $category,
        public readonly ?string $modelKey,
        public readonly ?StorageCapacity $storage,
        public readonly ?string $serviceMode,
        public readonly ?string $conditionKey,
        public readonly ?ComparisonOperator $operator,
        public readonly mixed $comparisonValue,
        public readonly ?Money $amount,
        public readonly ?BasisPointsMultiplier $multiplier,
        public readonly RulePriority $priority,
        public readonly bool $enabled,
        public readonly ?string $publicLabel,
        public readonly ?string $internalNote
    ) {
        if ($category !== 'iphone') {
            throw new InvalidValueObjectException('Phase 2A pricing rules are restricted to iPhone.');
        }

        if ($modelKey !== null && ($modelKey === '' || strlen($modelKey) > 64 || preg_match('/^[a-z0-9_-]+$/', $modelKey) !== 1)) {
            throw new InvalidValueObjectException('Pricing model key is invalid.');
        }

        if ($publicLabel !== null && strlen($publicLabel) > 160) {
            throw new InvalidValueObjectException('Public pricing label is too long.');
        }

        if ($amount !== null && $amount->currency() !== 'HUF') {
            throw new InvalidValueObjectException('Phase 2A pricing amounts must use HUF.');
        }

        RuleShapeValidator::assertValid($this);
    }
}
