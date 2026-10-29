<?php

declare(strict_types=1);
namespace Appleklinika\Inventory\Infrastructure\WordPress;

/** Batch the native Woo raw-meta reads for an already selected render pool. */
final class WooProductMetadata
{
    /** @param list<\WC_Product> $products */
    public static function prime(array $products): void
    {
        global $wpdb;
        $ids = [];
        foreach ($products as $product) {
            if (!$product instanceof \WC_Product || !$product->get_id()
                || !$product->get_data_store() || $product->get_data_store()->get_current_class_name() !== 'WC_Product_Data_Store_CPT') continue;
            // Do not replace a warm Woo cache or an object's loaded/unsaved data.
            if (wp_cache_get($product->get_meta_cache_key(), 'products') === false) $ids[] = $product->get_id();
        }
        $ids = array_values(array_unique(array_map('absint', $ids)));
        if ($ids === []) return;
        $rows = $wpdb->get_results('SELECT meta_id, post_id, meta_key, meta_value FROM ' . $wpdb->postmeta
            . ' WHERE post_id IN (' . implode(',', $ids) . ') ORDER BY meta_id');
        if (!is_array($rows)) return;
        $raw = array_fill_keys($ids, []);
        foreach ($rows as $row) $raw[(int) $row->post_id][] = $row;
        // Woo still filters internal keys, unserializes values and applies read filters.
        // Native save/stock operations invalidate these normal Woo cache keys.
        \WC_Data::prime_raw_meta_data_cache($raw, 'products');
    }
}
