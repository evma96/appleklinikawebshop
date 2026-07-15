<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Buyback;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;
use AppleKlinika\Buyback\Domain\Shared\ActorType;

final class TransitionContext
{
    private readonly ?string $correlationId;

    public function __construct(
        private readonly ActorType $actorType,
        private readonly \DateTimeImmutable $now,
        private readonly ?\DateTimeImmutable $finalOfferExpiresAt = null,
        private readonly bool $acceptanceEvidencePresent = false,
        private readonly bool $settlementReferencePresent = false,
        private readonly bool $tradeInCreditReferencePresent = false,
        private readonly bool $linkedWooOrderReferencePresent = false,
        ?string $correlationId = null
    ) {
        $normalizedCorrelationId = $correlationId === null ? null : trim($correlationId);

        if ($normalizedCorrelationId === '') {
            $normalizedCorrelationId = null;
        }

        if ($normalizedCorrelationId !== null && strlen($normalizedCorrelationId) > 128) {
            throw new InvalidValueObjectException('Correlation ID cannot exceed 128 bytes.');
        }

        $this->correlationId = $normalizedCorrelationId;
    }

    public function actorType(): ActorType
    {
        return $this->actorType;
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function isFinalOfferExpired(): bool
    {
        return $this->finalOfferExpiresAt !== null && $this->now >= $this->finalOfferExpiresAt;
    }

    public function acceptanceEvidencePresent(): bool
    {
        return $this->acceptanceEvidencePresent;
    }

    public function settlementReferencePresent(): bool
    {
        return $this->settlementReferencePresent;
    }

    public function tradeInCreditReferencePresent(): bool
    {
        return $this->tradeInCreditReferencePresent;
    }

    public function linkedWooOrderReferencePresent(): bool
    {
        return $this->linkedWooOrderReferencePresent;
    }

    public function correlationId(): ?string
    {
        return $this->correlationId;
    }
}
