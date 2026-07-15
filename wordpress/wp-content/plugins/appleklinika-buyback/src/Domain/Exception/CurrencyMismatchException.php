<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Exception;

final class CurrencyMismatchException extends BuybackDomainException
{
    public function __construct(string $leftCurrency, string $rightCurrency)
    {
        parent::__construct(sprintf(
            'Money currencies do not match: %s and %s.',
            $leftCurrency,
            $rightCurrency
        ));
    }
}
