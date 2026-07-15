<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Legacy;

final class LegacyBuybackRecord
{
    /**
     * @param list<string> $sourceIssueCodes
     */
    public function __construct(
        public readonly int $ownerUserId,
        public readonly int $sourceIndex,
        public readonly ?string $recordId,
        public readonly ?string $deviceDisplayName,
        public readonly ?string $condition,
        public readonly ?string $battery,
        public readonly ?string $estimatedOffer,
        public readonly ?string $finalOffer,
        public readonly ?string $status,
        public readonly ?string $createdDate,
        public readonly ?string $marker,
        public readonly bool $customerIdentityMismatch,
        public readonly array $sourceIssueCodes = []
    ) {
    }
}
