<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

use AppleKlinika\Buyback\Application\Pricing\ResolvedActivePriceBook;
use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;

interface ActivePriceBookResolver
{
    public function resolveForCurrencyAt(CurrencyCode $currency, \DateTimeImmutable $at): ResolvedActivePriceBook;
}
