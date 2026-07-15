<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Buyback;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class RequestNumber
{
    private const MAX_LENGTH = 32;

    private readonly string $value;

    public function __construct(string $value)
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($value));

        if (! is_string($normalized) || $normalized === '') {
            throw new InvalidValueObjectException('Request number cannot be empty.');
        }

        if (strlen($normalized) > self::MAX_LENGTH) {
            throw new InvalidValueObjectException('Request number is too long.');
        }

        if (preg_match('/^[\x20-\x7E]+$/', $normalized) !== 1) {
            throw new InvalidValueObjectException('Request number contains unsupported characters.');
        }

        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
