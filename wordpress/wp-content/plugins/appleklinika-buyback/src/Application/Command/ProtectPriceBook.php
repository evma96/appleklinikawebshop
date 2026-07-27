<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Command;

final class ProtectPriceBook
{
    public function __construct(public readonly int $priceBookId, public readonly int $actorId, public readonly string $confirmationName) {}
}
