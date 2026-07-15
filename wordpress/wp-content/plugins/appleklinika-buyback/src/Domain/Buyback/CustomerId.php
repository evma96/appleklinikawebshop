<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Buyback;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class CustomerId
{
    public function __construct(private readonly int $value)
    {
        if ($value <= 0) {
            throw new InvalidValueObjectException('Customer ID must be a positive integer.');
        }
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
