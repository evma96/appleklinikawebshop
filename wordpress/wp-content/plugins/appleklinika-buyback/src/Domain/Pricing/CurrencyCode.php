<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class CurrencyCode
{
    public function __construct(private readonly string $code)
    {
        if (preg_match('/^[A-Z]{3}$/', $code) !== 1) {
            throw new InvalidValueObjectException('Currency must be a three-letter uppercase code.');
        }
    }

    public function code(): string { return $this->code; }
    public function equals(self $other): bool { return $this->code === $other->code; }
}
