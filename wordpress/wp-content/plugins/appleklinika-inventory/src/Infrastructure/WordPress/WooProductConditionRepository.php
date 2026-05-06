<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Infrastructure\WordPress;

final class WooProductConditionRepository
{
    public const META_PREFIX = '_appleklinika_';

    /**
     * @param array<string, string> $data
     */
    public function save(int $productId, array $data): void
    {
        foreach ($data as $key => $value) {
            update_post_meta($productId, self::META_PREFIX . $key, $value);
        }
    }

    public function get(int $productId, string $key): string
    {
        return (string) get_post_meta($productId, self::META_PREFIX . $key, true);
    }
}
