<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Legacy;

use AppleKlinika\Buyback\Application\Port\BuybackRequestRepository;
use AppleKlinika\Buyback\Application\Port\LegacyBuybackRecordSource;

final class LegacyReportService
{
    public const KNOWN_DEMO_RECORD_ID = 'ak-buyback-account-test-profile-v1';
    public const KNOWN_DEMO_MARKER = 'account-test-profile-v1';

    public function __construct(
        private readonly LegacyBuybackRecordSource $source,
        private readonly LegacyBuybackParser $parser,
        private readonly BuybackRequestRepository $requests
    ) {
    }

    public function report(?int $userId = null): LegacyReport
    {
        $source = $this->source->read($userId);
        $candidates = array_map($this->parser->parse(...), $source->records);
        usort($candidates, static function (LegacyBuybackCandidate $left, LegacyBuybackCandidate $right): int {
            return [$left->ownerUserId, $left->recordId ?? '', $left->sourceIndex]
                <=> [$right->ownerUserId, $right->recordId ?? '', $right->sourceIndex];
        });

        $idCounts = [];
        $referenceCounts = [];

        foreach ($candidates as $candidate) {
            if ($candidate->recordId !== null) {
                $key = $candidate->ownerUserId . ':' . $candidate->recordId;
                $idCounts[$key] = ($idCounts[$key] ?? 0) + 1;
            }

            if ($candidate->legacyReference !== null) {
                $key = $candidate->legacyReference->value();
                $referenceCounts[$key] = ($referenceCounts[$key] ?? 0) + 1;
            }
        }

        $duplicateIds = count(array_filter($idCounts, static fn (int $count): bool => $count > 1));
        $duplicateReferences = count(array_filter($referenceCounts, static fn (int $count): bool => $count > 1));
        $items = [];

        foreach ($candidates as $candidate) {
            $issues = $candidate->issues;
            $idKey = $candidate->recordId === null
                ? null
                : $candidate->ownerUserId . ':' . $candidate->recordId;
            $referenceKey = $candidate->legacyReference?->value();

            if ($idKey !== null && ($idCounts[$idKey] ?? 0) > 1) {
                $issues[] = new LegacyValidationIssue(
                    'duplicate_legacy_id_within_user',
                    LegacyValidationIssue::INVALID
                );
            }

            if ($referenceKey !== null && ($referenceCounts[$referenceKey] ?? 0) > 1) {
                $issues[] = new LegacyValidationIssue(
                    'duplicate_deterministic_legacy_reference',
                    LegacyValidationIssue::INVALID
                );
            }

            $alreadyPresent = $candidate->legacyReference !== null
                && $this->requests->existsByLegacyReference($candidate->legacyReference);
            $classification = $this->classification($issues, $alreadyPresent);
            $issueCodes = array_values(array_unique(array_map(
                static fn (LegacyValidationIssue $issue): string => $issue->code,
                $issues
            )));
            sort($issueCodes, SORT_STRING);

            $items[] = new LegacyReportItem(
                $candidate->ownerUserId,
                $candidate->sourceIndex,
                $candidate->recordId,
                $candidate->marker,
                $candidate->deviceDisplayName,
                $candidate->batteryPercentage,
                $candidate->estimatedAmountHuf,
                $candidate->finalAmountHuf,
                $candidate->mappedStatus,
                $referenceKey,
                $classification,
                $issueCodes,
                $alreadyPresent
            );
        }

        return new LegacyReport(
            $source->usersScanned,
            $items,
            $this->count($items, LegacyClassification::IMPORTABLE),
            $this->count($items, LegacyClassification::NEEDS_MANUAL_MAPPING),
            $this->count($items, LegacyClassification::INVALID),
            $this->count($items, LegacyClassification::ALREADY_PRESENT),
            count(array_filter(
                $items,
                static fn (LegacyReportItem $item): bool =>
                    $item->legacyRecordId === self::KNOWN_DEMO_RECORD_ID
                    && $item->marker === self::KNOWN_DEMO_MARKER
            )),
            $duplicateIds,
            $duplicateReferences
        );
    }

    /** @param list<LegacyValidationIssue> $issues */
    private function classification(array $issues, bool $alreadyPresent): string
    {
        foreach ($issues as $issue) {
            if ($issue->severity === LegacyValidationIssue::INVALID) {
                return LegacyClassification::INVALID;
            }
        }

        if ($alreadyPresent) {
            return LegacyClassification::ALREADY_PRESENT;
        }

        return $issues === []
            ? LegacyClassification::IMPORTABLE
            : LegacyClassification::NEEDS_MANUAL_MAPPING;
    }

    /** @param list<LegacyReportItem> $items */
    private function count(array $items, string $classification): int
    {
        return count(array_filter(
            $items,
            static fn (LegacyReportItem $item): bool => $item->classification === $classification
        ));
    }
}
