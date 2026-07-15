<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Buyback;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class DeviceDisplayName
{
    private const MAX_LENGTH = 191;

    private readonly string $value;

    public function __construct(string $value)
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($value));

        if (
            ! is_string($normalized)
            || $normalized === ''
            || strlen($normalized) > self::MAX_LENGTH
            || $normalized !== strip_tags($normalized)
        ) {
            throw new InvalidValueObjectException('Device display name must be plain text of at most 191 bytes.');
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
