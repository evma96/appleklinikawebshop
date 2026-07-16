<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class PriceBookStatus
{
    public const DRAFT = 'draft';
    public const ACTIVE = 'active';
    public const RETIRED = 'retired';

    public function __construct(private readonly string $code)
    {
        if (! in_array($code, [self::DRAFT, self::ACTIVE, self::RETIRED], true)) {
            throw new InvalidValueObjectException('Unsupported price-book status.');
        }
    }

    public function code(): string { return $this->code; }
    public function isDraft(): bool { return $this->code === self::DRAFT; }
    public function isActive(): bool { return $this->code === self::ACTIVE; }
    public function isRetired(): bool { return $this->code === self::RETIRED; }
    public function equals(self $other): bool { return $this->code === $other->code; }
}
