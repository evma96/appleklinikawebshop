<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Domain\ProductCondition;

/** Canonical storage choices managed by the Apple Klinika Inventory plugin. */
final class StorageCapacityCatalog
{
    /** @return array<string,string> */
    public static function options(): array
    {
        return [
            '64_gb' => '64 GB',
            '128_gb' => '128 GB',
            '256_gb' => '256 GB',
            '512_gb' => '512 GB',
            '1_tb' => '1 TB',
            '2_tb' => '2 TB',
            '4_tb' => '4 TB',
            '8_tb' => '8 TB',
        ];
    }

    public static function gigabytes(string $key): ?int
    {
        if (preg_match('/^([1-9][0-9]*)_(gb|tb)$/', $key, $matches) !== 1 || ! array_key_exists($key, self::options())) {
            return null;
        }
        return $matches[2] === 'tb' ? (int) $matches[1] * 1024 : (int) $matches[1];
    }
}
