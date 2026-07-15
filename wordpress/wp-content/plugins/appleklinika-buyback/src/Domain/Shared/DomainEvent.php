<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Shared;

interface DomainEvent
{
    public function occurredAt(): \DateTimeImmutable;

    public function correlationId(): ?string;
}
