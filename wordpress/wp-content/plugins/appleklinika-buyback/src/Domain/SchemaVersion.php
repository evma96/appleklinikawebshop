<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain;

final class SchemaVersion
{
    public function __construct(private readonly string $value)
    {
        if (preg_match('/^\d+\.\d+\.\d+$/', $value) !== 1) {
            throw new \InvalidArgumentException('Schema version must use semantic version format.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isNewerThan(self $other): bool
    {
        return version_compare($this->value, $other->value, '>');
    }
}
