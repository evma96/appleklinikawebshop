<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Pricing;

final class PriceBookValidationResult
{
    /** @param list<string> $issues */
    public function __construct(public readonly array $issues)
    {
    }

    public function isValid(): bool { return $this->issues === []; }
}
