<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Time;

use AppleKlinika\Buyback\Application\Port\Clock;

final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
