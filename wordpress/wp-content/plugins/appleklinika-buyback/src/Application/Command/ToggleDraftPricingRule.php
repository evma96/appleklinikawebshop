<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Command;

final class ToggleDraftPricingRule
{
    public function __construct(
        public readonly int $priceBookId,
        public readonly int $expectedBookVersion,
        public readonly int $ruleId,
        public readonly int $expectedRuleVersion,
        public readonly bool $enabled
    ) {
    }
}
