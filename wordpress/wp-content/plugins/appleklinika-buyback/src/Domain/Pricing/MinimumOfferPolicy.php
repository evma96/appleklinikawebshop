<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class MinimumOfferPolicy
{
    public const MANUAL_REVIEW = 'manual_review';
    public const REJECT = 'reject';

    public function __construct(private readonly string $code)
    {
        if (! in_array($code, [self::MANUAL_REVIEW, self::REJECT], true)) {
            throw new InvalidValueObjectException('Unsupported minimum-offer policy.');
        }
    }

    public function code(): string { return $this->code; }
}
