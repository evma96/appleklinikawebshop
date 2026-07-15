<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Buyback;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class ModelKey
{
    private const MAX_LENGTH = 100;

    private readonly string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[\s_]+/', '-', $normalized);
        $normalized = is_string($normalized) ? trim($normalized, '-') : '';

        if (
            $normalized === ''
            || strlen($normalized) > self::MAX_LENGTH
            || preg_match('/^[a-z0-9][a-z0-9.-]*$/', $normalized) !== 1
        ) {
            throw new InvalidValueObjectException('Model key must be a normalized identifier of at most 100 bytes.');
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
}
