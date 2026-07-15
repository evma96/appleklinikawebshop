<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Legacy;

final class LegacyReportItem
{
    /** @param list<string> $issueCodes */
    public function __construct(
        public readonly int $ownerUserId,
        public readonly int $sourceIndex,
        public readonly ?string $legacyRecordId,
        public readonly ?string $marker,
        public readonly ?string $deviceDisplayName,
        public readonly ?int $batteryPercentage,
        public readonly ?int $estimatedAmountHuf,
        public readonly ?int $finalAmountHuf,
        public readonly ?string $mappedStatus,
        public readonly ?string $legacyReference,
        public readonly string $classification,
        public readonly array $issueCodes,
        public readonly bool $alreadyPresent
    ) {
    }

    /** @return array<string, int|string|bool|null|list<string>> */
    public function toArray(): array
    {
        return [
            'owner_user_id' => $this->ownerUserId,
            'legacy_record_id' => $this->legacyRecordId,
            'marker' => $this->marker,
            'device_display_name' => $this->deviceDisplayName,
            'battery_percentage' => $this->batteryPercentage,
            'estimated_amount_huf' => $this->estimatedAmountHuf,
            'final_amount_huf' => $this->finalAmountHuf,
            'mapped_status' => $this->mappedStatus,
            'legacy_reference' => $this->legacyReference,
            'classification' => $this->classification,
            'validation_issue_codes' => $this->issueCodes,
            'already_present' => $this->alreadyPresent,
        ];
    }
}
