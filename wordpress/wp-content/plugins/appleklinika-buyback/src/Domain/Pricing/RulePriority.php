<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class RulePriority
{
    public function __construct(private readonly int $value)
    {
        if ($value < -100000 || $value > 100000) {
            throw new InvalidValueObjectException('Rule priority is outside the supported range.');
        }
    }

    public function value(): int { return $this->value; }
}
