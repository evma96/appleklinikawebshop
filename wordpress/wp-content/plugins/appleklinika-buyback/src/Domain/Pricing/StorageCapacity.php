<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class StorageCapacity
{
    public const MAX_GB = 8192;

    public function __construct(private readonly int $gigabytes)
    {
        if ($gigabytes < 1 || $gigabytes > self::MAX_GB) {
            throw new InvalidValueObjectException('Storage capacity must be between 1 and 8192 GB.');
        }
    }

    public function gigabytes(): int { return $this->gigabytes; }
}
