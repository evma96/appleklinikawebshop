<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Exception;

use AppleKlinika\Buyback\Domain\Pricing\PricingRuleId;

final class PricingRuleNotFoundException extends \RuntimeException
{
    public static function forId(PricingRuleId $id): self
    {
        return new self('Pricing rule not found: ' . $id->toInt());
    }
}
