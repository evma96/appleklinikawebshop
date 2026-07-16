<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Benchmark;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class BenchmarkMath
{
    /** @param list<int> $values */
    public static function median(array $values): int|float
    {
        if ($values === []) {
            throw new InvalidValueObjectException('Benchmark median requires at least one value.');
        }

        sort($values, SORT_NUMERIC);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1
            ? $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    public static function roundHalfUp(int|float $value, int $increment): int
    {
        if ($increment < 1) {
            throw new InvalidValueObjectException('Benchmark rounding increment must be positive.');
        }

        return (int) (round($value / $increment, 0, PHP_ROUND_HALF_UP) * $increment);
    }

    /** @param list<array{mode_amount_minor: int, reference_amount_minor: int}> $observations */
    public static function medianRatioBasisPoints(array $observations): int
    {
        $ratios = [];
        foreach ($observations as $observation) {
            if ($observation['reference_amount_minor'] <= 0 || $observation['mode_amount_minor'] < 0) {
                throw new InvalidValueObjectException('Benchmark mode ratio amounts are invalid.');
            }
            $ratios[] = (int) round(
                ($observation['mode_amount_minor'] * 10000) / $observation['reference_amount_minor'],
                0,
                PHP_ROUND_HALF_UP
            );
        }

        return (int) round(self::median($ratios), 0, PHP_ROUND_HALF_UP);
    }
}
