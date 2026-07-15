<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

use AppleKlinika\Buyback\Domain\Buyback\RequestNumber;

interface RequestNumberGenerator
{
    public function generate(): RequestNumber;
}
