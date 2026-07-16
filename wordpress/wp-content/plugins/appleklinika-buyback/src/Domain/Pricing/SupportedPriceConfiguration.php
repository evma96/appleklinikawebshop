<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

final class SupportedPriceConfiguration
{
    public function __construct(
        public readonly string $category,
        public readonly string $modelKey,
        public readonly int $storageGb
    ) {
    }

    public function key(): string
    {
        return $this->category . '|' . $this->modelKey . '|' . $this->storageGb;
    }

    /** @param list<PricingRule> $rules @return list<self> */
    public static function fromEnabledBaseRules(array $rules): array
    {
        $configurations = [];
        foreach ($rules as $rule) {
            $definition = $rule->definition();
            if (! $definition->enabled || $definition->kind->code() !== PricingRuleKind::BASE_PRICE || $definition->modelKey === null || $definition->storage === null) {
                continue;
            }
            $configuration = new self($definition->category, $definition->modelKey, $definition->storage->gigabytes());
            $configurations[$configuration->key()] = $configuration;
        }

        ksort($configurations, SORT_STRING);
        return array_values($configurations);
    }
}
