<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

final class PricingMatchedRule
{
    public function __construct(
        public readonly string $ruleCode,
        public readonly string $ruleKind,
        public readonly int $priority,
        public readonly ?string $publicLabel
    ) {
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return ['rule_code' => $this->ruleCode, 'rule_kind' => $this->ruleKind, 'priority' => $this->priority, 'public_label' => $this->publicLabel];
    }
}
