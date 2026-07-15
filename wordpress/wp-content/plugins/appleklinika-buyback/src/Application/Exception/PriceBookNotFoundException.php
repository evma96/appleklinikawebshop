<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Exception;

use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;

final class PriceBookNotFoundException extends \RuntimeException
{
    public static function forId(PriceBookId $id): self
    {
        return new self('Price book not found: ' . $id->toInt());
    }
}
