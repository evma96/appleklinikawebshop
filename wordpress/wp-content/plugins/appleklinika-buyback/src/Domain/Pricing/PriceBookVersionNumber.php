<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class PriceBookVersionNumber
{
    public function __construct(private readonly int $value)
    {
        if ($value < 1) {
            throw new InvalidValueObjectException('Price-book version number must be positive.');
        }
    }

    public function value(): int { return $this->value; }
    public function next(): self { return new self($this->value + 1); }
    public function equals(self $other): bool { return $this->value === $other->value; }
}
