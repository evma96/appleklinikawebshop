<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class PricingActorId
{
    public function __construct(private readonly int $value)
    {
        if ($value < 1) {
            throw new InvalidValueObjectException('Pricing actor ID must be positive.');
        }
    }

    public function value(): int { return $this->value; }
}
