<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Buyback\DeviceCategory;
use AppleKlinika\Buyback\Domain\Buyback\ServiceMode;

final class PricingCalculationInput
{
    /** @var list<string> */
    public readonly array $affectedComponentKeys;

    public function __construct(
        public readonly DeviceCategory $category,
        public readonly PricingModelKey $modelKey,
        public readonly StorageCapacity $storage,
        public readonly ConditionAnswerCollection $conditionAnswers,
        public readonly ServiceMode $serviceMode,
        array $affectedComponentKeys = []
    ) {
        $normalized = array_values(array_unique(array_map(static function (mixed $key): string {
            if (! is_string($key) || preg_match('/^[a-z0-9_]{1,64}$/', $key) !== 1) {
                throw new \InvalidArgumentException('Affected component key is invalid.');
            }
            return $key;
        }, $affectedComponentKeys)));
        sort($normalized, SORT_STRING);
        $this->affectedComponentKeys = $normalized;
    }
}
