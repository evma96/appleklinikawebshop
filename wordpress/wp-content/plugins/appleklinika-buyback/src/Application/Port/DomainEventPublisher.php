<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

use AppleKlinika\Buyback\Domain\Shared\DomainEvent;

interface DomainEventPublisher
{
    public function publish(DomainEvent ...$events): void;
}
