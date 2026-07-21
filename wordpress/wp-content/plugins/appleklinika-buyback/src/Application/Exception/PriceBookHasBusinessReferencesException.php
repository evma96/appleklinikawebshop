<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Exception;

use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;

final class PriceBookHasBusinessReferencesException extends \RuntimeException
{
    public static function forId(PriceBookId $id): self
    {
        return new self('Price book has business references: ' . $id->toInt());
    }
}
