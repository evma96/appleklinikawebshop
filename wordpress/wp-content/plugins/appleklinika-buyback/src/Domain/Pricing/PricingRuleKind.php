<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class PricingRuleKind
{
    public const BASE_PRICE = 'base_price';
    public const FIXED_DEDUCTION = 'fixed_deduction';
    public const MULTIPLIER = 'multiplier';
    public const MODE_ADJUSTMENT = 'mode_adjustment';
    public const MINIMUM_OFFER = 'minimum_offer';
    public const HARD_REJECT = 'hard_reject';
    public const MANUAL_REVIEW = 'manual_review';
    public const NO_CHANGE = 'no_change';

    /** @return list<string> */
    public static function supported(): array
    {
        return [self::BASE_PRICE, self::FIXED_DEDUCTION, self::MULTIPLIER, self::MODE_ADJUSTMENT, self::MINIMUM_OFFER, self::HARD_REJECT, self::MANUAL_REVIEW, self::NO_CHANGE];
    }

    public function __construct(private readonly string $code)
    {
        if (! in_array($code, self::supported(), true)) {
            throw new InvalidValueObjectException('Unsupported pricing-rule kind.');
        }
    }

    public function code(): string { return $this->code; }
}
