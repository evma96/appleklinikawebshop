<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Command;

use AppleKlinika\Buyback\Domain\Buyback\CustomerId;
use AppleKlinika\Buyback\Domain\Buyback\DeviceCategory;
use AppleKlinika\Buyback\Domain\Buyback\DeviceDisplayName;
use AppleKlinika\Buyback\Domain\Buyback\HandoverMethod;
use AppleKlinika\Buyback\Domain\Buyback\LegacyReference;
use AppleKlinika\Buyback\Domain\Buyback\ModelKey;
use AppleKlinika\Buyback\Domain\Buyback\RequestSource;
use AppleKlinika\Buyback\Domain\Buyback\ServiceMode;

final class CreateDraftBuybackRequest
{
    public function __construct(
        public readonly DeviceCategory $category,
        public readonly ModelKey $modelKey,
        public readonly DeviceDisplayName $deviceDisplayName,
        public readonly ServiceMode $serviceMode,
        public readonly RequestSource $source,
        public readonly ?CustomerId $customerId = null,
        public readonly ?HandoverMethod $handoverMethod = null,
        public readonly ?LegacyReference $legacyReference = null,
        public readonly ?string $demoMarker = null
    ) {
    }
}
