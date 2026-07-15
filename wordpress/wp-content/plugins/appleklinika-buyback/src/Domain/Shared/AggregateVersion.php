<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Shared;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class AggregateVersion
{
    public function __construct(private readonly int $value)
    {
        if ($value < 0) {
            throw new InvalidValueObjectException('Aggregate version cannot be negative.');
        }
    }

    public function value(): int
    {
        return $this->value;
    }

    public function next(): self
    {
        return new self($this->value + 1);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
