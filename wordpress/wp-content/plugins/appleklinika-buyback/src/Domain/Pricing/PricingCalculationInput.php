<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Buyback\DeviceCategory;
use AppleKlinika\Buyback\Domain\Buyback\ServiceMode;

final class PricingCalculationInput
{
    public function __construct(
        public readonly DeviceCategory $category,
        public readonly PricingModelKey $modelKey,
        public readonly StorageCapacity $storage,
        public readonly ConditionAnswerCollection $conditionAnswers,
        public readonly ServiceMode $serviceMode
    ) {
    }
}
