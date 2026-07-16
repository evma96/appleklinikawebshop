<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class PricingOutcome
{
    public const OFFERED = 'offered';
    public const MANUAL_REVIEW = 'manual_review';
    public const REJECTED = 'rejected';
    public const CONFIGURATION_ERROR = 'configuration_error';

    public function __construct(private readonly string $code)
    {
        if (! in_array($code, [self::OFFERED, self::MANUAL_REVIEW, self::REJECTED, self::CONFIGURATION_ERROR], true)) {
            throw new InvalidValueObjectException('Unsupported pricing outcome.');
        }
    }

    public function code(): string { return $this->code; }
}
