<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

final class PricingMatchedRule
{
    public function __construct(
        public readonly string $ruleCode,
        public readonly string $ruleKind,
        public readonly int $priority,
        public readonly ?string $publicLabel,
        public readonly string $source,
        public readonly ?string $conditionKey = null,
        public readonly int|bool|string|null $comparisonValue = null,
        public readonly ?string $affectedComponentKey = null
    ) {
    }

    /** @return array<string, int|bool|string|null> */
    public function toArray(): array
    {
        return [
            'rule_code' => $this->ruleCode,
            'rule_kind' => $this->ruleKind,
            'priority' => $this->priority,
            'public_label' => $this->publicLabel,
            'source' => $this->source,
            'condition_key' => $this->conditionKey,
            'comparison_value' => $this->comparisonValue,
            'affected_component_key' => $this->affectedComponentKey,
        ];
    }
}
