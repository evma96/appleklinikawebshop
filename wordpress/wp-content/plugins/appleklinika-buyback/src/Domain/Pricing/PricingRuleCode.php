<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class PricingRuleCode
{
    private readonly string $code;

    public function __construct(string $code)
    {
        $normalized = strtolower(trim($code));
        $normalized = preg_replace('/[^a-z0-9._-]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');

        if ($normalized === '' || strlen($normalized) > 64) {
            throw new InvalidValueObjectException('Pricing-rule code must be a safe identifier of at most 64 characters.');
        }

        $this->code = $normalized;
    }

    public function code(): string { return $this->code; }
}
