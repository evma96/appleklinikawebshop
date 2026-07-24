<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Command;

use AppleKlinika\Buyback\Domain\Buyback\CustomerId;
use AppleKlinika\Buyback\Domain\Buyback\DeviceCategory;
use AppleKlinika\Buyback\Domain\Buyback\DeviceDisplayName;
use AppleKlinika\Buyback\Domain\Buyback\HandoverMethod;
use AppleKlinika\Buyback\Domain\Buyback\HandoverMethodPolicy;
use AppleKlinika\Buyback\Domain\Buyback\LegacyReference;
use AppleKlinika\Buyback\Domain\Buyback\ModelKey;
use AppleKlinika\Buyback\Domain\Buyback\RequestNumber;
use AppleKlinika\Buyback\Domain\Buyback\RequestSource;
use AppleKlinika\Buyback\Domain\Buyback\ServiceMode;
use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class NewBuybackRequest
{
    private readonly ?string $demoMarker;

    public function __construct(
        public readonly RequestNumber $requestNumber,
        public readonly ?CustomerId $customerId,
        public readonly DeviceCategory $category,
        public readonly ModelKey $modelKey,
        public readonly DeviceDisplayName $deviceDisplayName,
        public readonly ?ServiceMode $serviceMode,
        public readonly ?HandoverMethod $handoverMethod,
        public readonly RequestSource $source,
        public readonly ?LegacyReference $legacyReference,
        public readonly \DateTimeImmutable $createdAt,
        ?string $demoMarker = null
    ) {
        if ($handoverMethod !== null && $serviceMode !== null) {
            (new HandoverMethodPolicy())->assertCompatible($serviceMode, $handoverMethod);
        }

        if ($handoverMethod !== null && $serviceMode === null) {
            throw new InvalidValueObjectException('A manual-review request cannot select a handover method.');
        }

        $normalized = $demoMarker === null ? null : trim($demoMarker);

        if ($normalized === '') {
            $normalized = null;
        }

        if (
            $normalized !== null
            && (strlen($normalized) > 100 || preg_match('/^[A-Za-z0-9._:-]+$/', $normalized) !== 1)
        ) {
            throw new InvalidValueObjectException('Demo marker must be a safe identifier of at most 100 bytes.');
        }

        $this->demoMarker = $normalized;
    }

    public function demoMarker(): ?string
    {
        return $this->demoMarker;
    }
}
