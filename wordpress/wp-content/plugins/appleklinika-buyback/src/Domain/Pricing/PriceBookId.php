<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class PriceBookId
{
    public function __construct(private readonly int $value)
    {
        if ($value < 1) {
            throw new InvalidValueObjectException('Price-book ID must be positive.');
        }
    }

    public function value(): int { return $this->value; }
    public function toInt(): int { return $this->value; }
    public function equals(self $other): bool { return $this->value === $other->value; }
}
