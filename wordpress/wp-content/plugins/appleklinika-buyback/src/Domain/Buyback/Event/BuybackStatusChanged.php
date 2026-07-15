<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Buyback\Event;

use AppleKlinika\Buyback\Domain\Buyback\BuybackRequestId;
use AppleKlinika\Buyback\Domain\Buyback\BuybackStatus;
use AppleKlinika\Buyback\Domain\Buyback\RequestNumber;
use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;
use AppleKlinika\Buyback\Domain\Shared\ActorType;
use AppleKlinika\Buyback\Domain\Shared\DomainEvent;

final class BuybackStatusChanged implements DomainEvent
{
    /** @var array<string, bool|float|int|string|null> */
    private readonly array $metadata;

    /**
     * @param array<string, bool|float|int|string|null> $metadata
     */
    public function __construct(
        private readonly BuybackRequestId $requestId,
        private readonly RequestNumber $requestNumber,
        private readonly BuybackStatus $fromStatus,
        private readonly BuybackStatus $toStatus,
        private readonly ActorType $actorType,
        private readonly \DateTimeImmutable $occurredAt,
        private readonly ?string $correlationId = null,
        array $metadata = []
    ) {
        foreach ($metadata as $key => $value) {
            if (! is_string($key) || $key === '') {
                throw new InvalidValueObjectException('Domain-event metadata keys must be non-empty strings.');
            }

            if (! is_scalar($value) && $value !== null) {
                throw new InvalidValueObjectException('Domain-event metadata values must be scalar or null.');
            }
        }

        $this->metadata = $metadata;
    }

    public function requestId(): BuybackRequestId
    {
        return $this->requestId;
    }

    public function requestNumber(): RequestNumber
    {
        return $this->requestNumber;
    }

    public function fromStatus(): BuybackStatus
    {
        return $this->fromStatus;
    }

    public function toStatus(): BuybackStatus
    {
        return $this->toStatus;
    }

    public function actorType(): ActorType
    {
        return $this->actorType;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function correlationId(): ?string
    {
        return $this->correlationId;
    }

    /** @return array<string, bool|float|int|string|null> */
    public function metadata(): array
    {
        return $this->metadata;
    }
}
