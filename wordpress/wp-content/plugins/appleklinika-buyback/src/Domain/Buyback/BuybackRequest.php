<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Buyback;

use AppleKlinika\Buyback\Domain\Buyback\Event\BuybackStatusChanged;
use AppleKlinika\Buyback\Domain\Exception\InvalidAggregateOperationException;
use AppleKlinika\Buyback\Domain\Shared\AggregateVersion;
use AppleKlinika\Buyback\Domain\Shared\DomainEvent;

final class BuybackRequest
{
    /** @var list<DomainEvent> */
    private array $pendingEvents = [];

    private function __construct(
        private readonly BuybackRequestId $id,
        private readonly RequestNumber $requestNumber,
        private ?CustomerId $customerId,
        private readonly DeviceCategory $category,
        private readonly ModelKey $modelKey,
        private readonly DeviceDisplayName $deviceDisplayName,
        private ServiceMode $serviceMode,
        private ?HandoverMethod $handoverMethod,
        private BuybackStatus $status,
        private readonly RequestSource $source,
        private readonly ?LegacyReference $legacyReference,
        private AggregateVersion $version,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
        if ($updatedAt < $createdAt) {
            throw new InvalidAggregateOperationException('Buyback updated-at timestamp cannot precede created-at.');
        }
    }

    public static function createDraft(
        BuybackRequestId $id,
        RequestNumber $requestNumber,
        DeviceCategory $category,
        ModelKey $modelKey,
        DeviceDisplayName $deviceDisplayName,
        ServiceMode $serviceMode,
        RequestSource $source,
        \DateTimeImmutable $createdAt,
        ?CustomerId $customerId = null,
        ?HandoverMethod $handoverMethod = null,
        ?LegacyReference $legacyReference = null
    ): self {
        return new self(
            $id,
            $requestNumber,
            $customerId,
            $category,
            $modelKey,
            $deviceDisplayName,
            $serviceMode,
            $handoverMethod,
            new BuybackStatus(BuybackStatus::DRAFT),
            $source,
            $legacyReference,
            new AggregateVersion(0),
            $createdAt,
            $createdAt
        );
    }

    public static function reconstitute(
        BuybackRequestId $id,
        RequestNumber $requestNumber,
        ?CustomerId $customerId,
        DeviceCategory $category,
        ModelKey $modelKey,
        DeviceDisplayName $deviceDisplayName,
        ServiceMode $serviceMode,
        ?HandoverMethod $handoverMethod,
        BuybackStatus $status,
        RequestSource $source,
        ?LegacyReference $legacyReference,
        AggregateVersion $version,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ): self {
        return new self(
            $id,
            $requestNumber,
            $customerId,
            $category,
            $modelKey,
            $deviceDisplayName,
            $serviceMode,
            $handoverMethod,
            $status,
            $source,
            $legacyReference,
            $version,
            $createdAt,
            $updatedAt
        );
    }

    public function attachCustomer(CustomerId $customerId, \DateTimeImmutable $at): void
    {
        $this->assertDraftMutation('attach customer');

        if ($this->customerId !== null) {
            if ($this->customerId->equals($customerId)) {
                return;
            }

            throw new InvalidAggregateOperationException('Buyback request is already attached to another customer.');
        }

        $this->customerId = $customerId;
        $this->recordMutation($at);
    }

    public function selectServiceMode(ServiceMode $serviceMode, \DateTimeImmutable $at): void
    {
        $this->assertDraftMutation('select service mode');

        if ($this->serviceMode->equals($serviceMode)) {
            return;
        }

        if ($this->handoverMethod !== null) {
            (new HandoverMethodPolicy())->assertCompatible($serviceMode, $this->handoverMethod);
        }

        $this->serviceMode = $serviceMode;
        $this->recordMutation($at);
    }

    public function selectHandoverMethod(
        HandoverMethod $handoverMethod,
        HandoverMethodPolicy $policy,
        \DateTimeImmutable $at
    ): void {
        $this->assertDraftMutation('select handover method');
        $policy->assertCompatible($this->serviceMode, $handoverMethod);

        if ($this->handoverMethod !== null && $this->handoverMethod->equals($handoverMethod)) {
            return;
        }

        $this->handoverMethod = $handoverMethod;
        $this->recordMutation($at);
    }

    public function transitionTo(
        BuybackStatus $target,
        StatusTransitionPolicy $policy,
        TransitionContext $context
    ): void {
        $policy->assertAllowed($this->status, $target, $this->serviceMode, $context);
        $this->assertMutationTime($context->now());

        $from = $this->status;
        $this->status = $target;
        $this->updatedAt = $context->now();
        $this->version = $this->version->next();
        $this->pendingEvents[] = new BuybackStatusChanged(
            $this->id,
            $this->requestNumber,
            $from,
            $target,
            $context->actorType(),
            $context->now(),
            $context->correlationId(),
            ['aggregate_version' => $this->version->value()]
        );
    }

    public function id(): BuybackRequestId
    {
        return $this->id;
    }

    public function requestNumber(): RequestNumber
    {
        return $this->requestNumber;
    }

    public function customerId(): ?CustomerId
    {
        return $this->customerId;
    }

    public function category(): DeviceCategory
    {
        return $this->category;
    }

    public function modelKey(): ModelKey
    {
        return $this->modelKey;
    }

    public function deviceDisplayName(): DeviceDisplayName
    {
        return $this->deviceDisplayName;
    }

    public function serviceMode(): ServiceMode
    {
        return $this->serviceMode;
    }

    public function handoverMethod(): ?HandoverMethod
    {
        return $this->handoverMethod;
    }

    public function status(): BuybackStatus
    {
        return $this->status;
    }

    public function source(): RequestSource
    {
        return $this->source;
    }

    public function legacyReference(): ?LegacyReference
    {
        return $this->legacyReference;
    }

    public function version(): AggregateVersion
    {
        return $this->version;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return list<DomainEvent> */
    public function pendingEvents(): array
    {
        return $this->pendingEvents;
    }

    /** @return list<DomainEvent> */
    public function releasePendingEvents(): array
    {
        $events = $this->pendingEvents;
        $this->pendingEvents = [];

        return $events;
    }

    private function assertDraftMutation(string $operation): void
    {
        if (! $this->status->isCustomerEditable()) {
            throw new InvalidAggregateOperationException(sprintf(
                'Cannot %s after the buyback request leaves draft status.',
                $operation
            ));
        }
    }

    private function recordMutation(\DateTimeImmutable $at): void
    {
        $this->assertMutationTime($at);
        $this->updatedAt = $at;
        $this->version = $this->version->next();
    }

    private function assertMutationTime(\DateTimeImmutable $at): void
    {
        if ($at < $this->updatedAt) {
            throw new InvalidAggregateOperationException('Buyback mutation timestamp cannot move backwards.');
        }
    }
}
