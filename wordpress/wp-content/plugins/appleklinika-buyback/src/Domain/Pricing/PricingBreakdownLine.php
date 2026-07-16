<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

final class PricingBreakdownLine
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $ruleCode,
        public readonly ?string $ruleKind,
        public readonly int $beforeAmountMinor,
        public readonly ?int $adjustmentAmountMinor,
        public readonly ?int $multiplierBps,
        public readonly int $afterAmountMinor,
        public readonly ?string $publicLabel,
        public readonly ?string $internalExplanation,
        public readonly int $priority
    ) {
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'rule_code' => $this->ruleCode,
            'rule_kind' => $this->ruleKind,
            'before_amount_minor' => $this->beforeAmountMinor,
            'adjustment_amount_minor' => $this->adjustmentAmountMinor,
            'multiplier_bps' => $this->multiplierBps,
            'after_amount_minor' => $this->afterAmountMinor,
            'public_label' => $this->publicLabel,
            'internal_explanation' => $this->internalExplanation,
            'priority' => $this->priority,
        ];
    }
}
