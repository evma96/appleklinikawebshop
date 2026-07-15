<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Legacy;

use AppleKlinika\Buyback\Application\Port\LegacyModelResolver;

final class LegacyBuybackParser
{
    public function __construct(
        private readonly LegacyFieldParser $fields,
        private readonly LegacyReferenceFactory $references,
        private readonly LegacyModelResolver $models
    ) {
    }

    public function parse(LegacyBuybackRecord $record): LegacyBuybackCandidate
    {
        $issues = array_map(
            static fn (string $code): LegacyValidationIssue =>
                new LegacyValidationIssue($code, LegacyValidationIssue::INVALID),
            $record->sourceIssueCodes
        );

        $recordId = $this->fields->recordId($record->recordId);
        $device = $this->fields->plainText($record->deviceDisplayName);
        $condition = $this->fields->plainText($record->condition);
        $marker = $record->marker === null ? null : $this->fields->plainText($record->marker);
        $battery = $this->fields->batteryPercentage($record->battery);
        $estimated = $this->fields->hufAmount($record->estimatedOffer);
        $final = $this->fields->hufAmount($record->finalOffer);
        $status = $this->fields->status($record->status);
        $createdAt = $this->fields->utcDate($record->createdDate);

        $this->require($issues, $recordId !== null, 'invalid_record_id');
        $this->require($issues, $device !== null, 'invalid_device_display_name');
        $this->require($issues, $condition !== null, 'invalid_condition');
        $this->require($issues, $battery !== null, 'invalid_battery');
        $this->require($issues, $estimated !== null, 'invalid_estimated_offer');
        $this->require($issues, $final !== null, 'invalid_final_offer');
        $this->require($issues, $createdAt !== null, 'invalid_created_date');

        if ($record->marker !== null && $marker === null) {
            $issues[] = new LegacyValidationIssue('invalid_marker', LegacyValidationIssue::INVALID);
        }

        if ($status === null) {
            $issues[] = new LegacyValidationIssue('unknown_status', LegacyValidationIssue::MANUAL);
        }

        if ($record->customerIdentityMismatch) {
            $issues[] = new LegacyValidationIssue('customer_identity_mismatch', LegacyValidationIssue::MANUAL);
        }

        $modelKey = $device === null ? null : $this->models->resolve($device);

        if ($device !== null && $modelKey === null) {
            $issues[] = new LegacyValidationIssue('model_key_unresolved', LegacyValidationIssue::MANUAL);
        }

        $reference = $recordId === null
            ? null
            : $this->references->fromUserMeta($record->ownerUserId, $recordId);

        return new LegacyBuybackCandidate(
            $record->ownerUserId,
            $record->sourceIndex,
            $recordId,
            $marker,
            $device,
            $modelKey,
            $condition,
            $battery,
            $estimated,
            $final,
            $status,
            $createdAt,
            $reference,
            $issues
        );
    }

    /** @param list<LegacyValidationIssue> $issues */
    private function require(array &$issues, bool $valid, string $code): void
    {
        if (! $valid) {
            $issues[] = new LegacyValidationIssue($code, LegacyValidationIssue::INVALID);
        }
    }
}
