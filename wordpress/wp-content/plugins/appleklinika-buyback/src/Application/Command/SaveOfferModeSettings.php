<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Command;

final class SaveOfferModeSettings
{
    /** @param array<string,mixed> $modes */
    public function __construct(public readonly array $modes) {}
}
