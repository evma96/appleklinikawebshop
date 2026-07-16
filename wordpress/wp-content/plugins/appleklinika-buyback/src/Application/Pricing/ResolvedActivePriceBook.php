<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Pricing;

use AppleKlinika\Buyback\Domain\Pricing\PriceBook;
use AppleKlinika\Buyback\Domain\Pricing\PricingRule;
use AppleKlinika\Buyback\Domain\Pricing\SupportedPriceConfiguration;

final class ResolvedActivePriceBook
{
    /** @param list<PricingRule> $enabledRules @param list<SupportedPriceConfiguration> $supportedConfigurations */
    public function __construct(
        public readonly PriceBook $priceBook,
        public readonly array $enabledRules,
        public readonly array $supportedConfigurations,
        public readonly \DateTimeImmutable $resolvedAt
    ) {
    }
}
