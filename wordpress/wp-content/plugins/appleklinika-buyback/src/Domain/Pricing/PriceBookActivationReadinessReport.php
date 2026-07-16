<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

final class PriceBookActivationReadinessReport
{
    /**
     * @param list<string> $blockingIssues
     * @param list<string> $warnings
     * @param list<SupportedPriceConfiguration> $supportedConfigurations
     * @param list<string> $supportedServiceModes
     */
    public function __construct(
        public readonly PriceBookId $priceBookId,
        public readonly PriceBookVersionNumber $versionNumber,
        public readonly CurrencyCode $currency,
        public readonly bool $ready,
        public readonly array $blockingIssues,
        public readonly array $warnings,
        public readonly int $enabledBasePriceCount,
        public readonly array $supportedConfigurations,
        public readonly int $enabledAdjustmentCount,
        public readonly array $supportedServiceModes,
        public readonly \DateTimeImmutable $validatedAt
    ) {
    }

    public function supportedConfigurationCount(): int
    {
        return count($this->supportedConfigurations);
    }
}
