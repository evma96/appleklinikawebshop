<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\LocalDemo;

final class LocalDemoPriceMatrixBuilder
{
    private const PREFERRED_GRADES = ['a_plus', 'a'];

    /**
     * @param list<array{model_key:string,model_label:string,storage_gb:int,grade:string,price:int}> $products
     * @return list<LocalDemoPricePoint>
     */
    public function build(array $products): array
    {
        $groups = [];
        foreach ($products as $product) {
            $modelKey = (string) ($product['model_key'] ?? '');
            $storageGb = (int) ($product['storage_gb'] ?? 0);
            $price = (int) ($product['price'] ?? 0);
            if ($modelKey === '' || preg_match('/^[a-z0-9_-]+$/', $modelKey) !== 1 || $storageGb < 1 || $price < 1) {
                continue;
            }

            $key = $modelKey . '|' . $storageGb;
            $groups[$key]['model_key'] = $modelKey;
            $groups[$key]['model_label'] = trim((string) ($product['model_label'] ?? $modelKey));
            $groups[$key]['storage_gb'] = $storageGb;
            $groups[$key]['all'][] = $price;
            if (in_array((string) ($product['grade'] ?? ''), self::PREFERRED_GRADES, true)) {
                $groups[$key]['preferred'][] = $price;
            }
        }

        ksort($groups, SORT_STRING);
        $matrix = [];
        foreach ($groups as $group) {
            $prices = $group['preferred'] ?? [];
            if ($prices === []) {
                $prices = $group['all'] ?? [];
            }
            if ($prices === []) {
                continue;
            }

            $representative = $this->median($prices);
            $half = intdiv($representative * 50, 100);
            $base = max(10000, $this->roundHalfUp($half, 1000));
            $matrix[] = new LocalDemoPricePoint(
                $group['model_key'],
                $group['model_label'],
                $group['storage_gb'],
                $representative,
                $base
            );
        }

        return $matrix;
    }

    /** @param list<int> $values */
    public function median(array $values): int
    {
        if ($values === []) {
            throw new \InvalidArgumentException('Median requires at least one value.');
        }
        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);
        return $count % 2 === 1
            ? $values[$middle]
            : intdiv($values[$middle - 1] + $values[$middle], 2);
    }

    public function roundHalfUp(int $amount, int $increment): int
    {
        if ($amount < 0 || $increment < 1) {
            throw new \InvalidArgumentException('Amount and increment must be positive.');
        }
        $quotient = intdiv($amount, $increment);
        return ($quotient + (($amount % $increment) * 2 >= $increment ? 1 : 0)) * $increment;
    }
}
