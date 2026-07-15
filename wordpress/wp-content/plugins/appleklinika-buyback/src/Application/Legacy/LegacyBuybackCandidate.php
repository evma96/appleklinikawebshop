<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Legacy;

use AppleKlinika\Buyback\Domain\Buyback\LegacyReference;

final class LegacyBuybackCandidate
{
    /** @param list<LegacyValidationIssue> $issues */
    public function __construct(
        public readonly int $ownerUserId,
        public readonly int $sourceIndex,
        public readonly ?string $recordId,
        public readonly ?string $marker,
        public readonly ?string $deviceDisplayName,
        public readonly ?string $modelKey,
        public readonly ?string $condition,
        public readonly ?int $batteryPercentage,
        public readonly ?int $estimatedAmountHuf,
        public readonly ?int $finalAmountHuf,
        public readonly ?string $mappedStatus,
        public readonly ?\DateTimeImmutable $createdAt,
        public readonly ?LegacyReference $legacyReference,
        public readonly array $issues
    ) {
    }
}
