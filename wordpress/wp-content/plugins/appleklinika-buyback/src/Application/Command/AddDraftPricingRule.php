<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Command;

use AppleKlinika\Buyback\Domain\Pricing\PricingRuleDefinition;

final class AddDraftPricingRule
{
    public function __construct(
        public readonly int $priceBookId,
        public readonly int $expectedBookVersion,
        public readonly PricingRuleDefinition $definition
    ) {
    }
}
