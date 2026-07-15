<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Legacy;

final class LegacyReport
{
    /** @param list<LegacyReportItem> $items */
    public function __construct(
        public readonly int $usersScanned,
        public readonly array $items,
        public readonly int $importableCount,
        public readonly int $needsManualMappingCount,
        public readonly int $invalidCount,
        public readonly int $alreadyPresentCount,
        public readonly int $demoRecordCount,
        public readonly int $duplicateLegacyIdsWithinUser,
        public readonly int $duplicateLegacyReferences
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'summary' => [
                'users_scanned' => $this->usersScanned,
                'legacy_records_found' => count($this->items),
                'importable_count' => $this->importableCount,
                'needs_manual_mapping_count' => $this->needsManualMappingCount,
                'invalid_count' => $this->invalidCount,
                'already_present_count' => $this->alreadyPresentCount,
                'demo_record_count' => $this->demoRecordCount,
                'duplicate_legacy_ids_within_user' => $this->duplicateLegacyIdsWithinUser,
                'duplicate_deterministic_legacy_references' => $this->duplicateLegacyReferences,
                'new_requests_written' => 0,
                'source_records_modified' => 0,
            ],
            'records' => array_map(
                static fn (LegacyReportItem $item): array => $item->toArray(),
                $this->items
            ),
        ];
    }
}
