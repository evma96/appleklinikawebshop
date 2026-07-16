<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;

interface PriceBookActivationLock
{
    public function acquire(CurrencyCode $currency, int $timeoutSeconds): void;

    public function release(CurrencyCode $currency): void;
}
