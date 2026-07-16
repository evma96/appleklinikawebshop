<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class PricingModelKey
{
    public function __construct(private readonly string $value)
    {
        if ($value === '' || strlen($value) > 64 || preg_match('/^[a-z0-9_-]+$/', $value) !== 1) {
            throw new InvalidValueObjectException('Pricing model key must preserve a valid inventory identifier.');
        }
    }

    public function value(): string { return $this->value; }
    public function equals(self $other): bool { return $this->value === $other->value; }
}
