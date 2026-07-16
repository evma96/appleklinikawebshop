<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Benchmark;

final class BenchmarkEvidencePolicy
{
    /** @param array<string, mixed> $evidence */
    public static function basePriceEligible(array $evidence): bool
    {
        return self::observationCount($evidence) >= 2
            && count(self::uniqueStrings($evidence['sources'] ?? [])) >= 2
            && in_array((string) ($evidence['confidence'] ?? ''), ['high', 'medium'], true);
    }

    /** @param array<string, mixed> $evidence */
    public static function modeAdjustmentEligible(array $evidence): bool
    {
        return self::observationCount($evidence) >= 2
            && in_array((string) ($evidence['confidence'] ?? ''), ['high', 'medium'], true);
    }

    /** @param array<string, mixed> $evidence */
    public static function monetaryConditionEligible(array $evidence): bool
    {
        $confidence = (string) ($evidence['confidence'] ?? '');
        $observations = self::observationCount($evidence);
        $sources = count(self::uniqueStrings($evidence['sources'] ?? []));
        $models = count(self::uniqueStrings($evidence['models'] ?? []));

        return ($confidence === 'high' && $observations >= 2 && $sources >= 2 && $models >= 2)
            || ($confidence === 'medium' && $observations >= 2 && ($sources >= 2 || $models >= 2));
    }

    /** @param array<string, mixed> $evidence */
    public static function reviewOrRejectEligible(array $evidence, bool $hardReject): bool
    {
        $minimum = $hardReject ? 2 : 1;
        return self::observationCount($evidence) >= $minimum
            && ($hardReject ? (string) ($evidence['confidence'] ?? '') !== 'low' : true);
    }

    /** @param array<string, mixed> $evidence */
    public static function observationCount(array $evidence): int
    {
        $observations = $evidence['observations'] ?? [];
        return is_array($observations) ? count(array_unique(array_map('strval', $observations))) : 0;
    }

    /** @return list<string> */
    private static function uniqueStrings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $values
        ))));
    }
}
