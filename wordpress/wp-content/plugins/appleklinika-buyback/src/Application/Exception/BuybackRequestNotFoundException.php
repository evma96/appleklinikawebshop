<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Exception;

use AppleKlinika\Buyback\Domain\Buyback\BuybackRequestId;

final class BuybackRequestNotFoundException extends \RuntimeException
{
    public static function forId(BuybackRequestId $id): self
    {
        return new self(sprintf('Buyback request %s was not found.', $id->toString()));
    }
}
