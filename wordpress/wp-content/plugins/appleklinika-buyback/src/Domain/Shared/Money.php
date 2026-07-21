<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Shared;

use AppleKlinika\Buyback\Domain\Exception\CurrencyMismatchException;
use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class Money
{
    private readonly int $amount;

    private readonly string $currency;

    public function __construct(mixed $amount, string $currency)
    {
        if (! is_int($amount)) {
            throw new InvalidValueObjectException('Money amount must be an integer.');
        }

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidValueObjectException('Money currency must be a three-letter uppercase code.');
        }

        $this->amount = $amount;
        $this->currency = $currency;
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function compare(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->amount <=> $other->amount;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        $result = $this->amount - $other->amount;

        if ($result < 0) {
            throw new InvalidValueObjectException('Money subtraction cannot produce a negative offer amount.');
        }

        return new self($result, $this->currency);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new CurrencyMismatchException($this->currency, $other->currency);
        }
    }
}
