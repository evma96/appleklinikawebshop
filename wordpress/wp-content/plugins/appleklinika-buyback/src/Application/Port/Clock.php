<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
