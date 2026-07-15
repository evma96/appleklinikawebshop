<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class BasisPointsMultiplier
{
    public const ONE = 10000;
    public const MAX = 50000;

    public function __construct(private readonly int $value)
    {
        if ($value < 0 || $value > self::MAX) {
            throw new InvalidValueObjectException('Multiplier basis points must be between 0 and 50000.');
        }
    }

    public function value(): int { return $this->value; }
}
