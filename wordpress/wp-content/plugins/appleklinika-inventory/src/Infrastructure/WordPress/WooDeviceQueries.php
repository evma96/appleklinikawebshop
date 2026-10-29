<?php

declare(strict_types=1);
namespace Appleklinika\Inventory\Infrastructure\WordPress;

/** CPT query adapter: retain Woo's stock, visibility, ordering and cache invalidation. */
final class WooDeviceQueries
{
    public const AVAILABLE = 'appleklinika_available_devices';
    public const FAMILY = 'appleklinika_recommendation_scope';

    public function productQuery(array $query, array $vars): array
    {
        if (!empty($vars[self::AVAILABLE])) $query[self::AVAILABLE] = true;
        unset($query[self::FAMILY]);
        $scope = $vars[self::FAMILY] ?? null;
        if (is_array($scope)) {
            $family = ['relation' => 'OR'];
            foreach (['types' => 'device_type', 'models' => 'device_model'] as $field => $key) {
                $values = array_values(array_filter((array) ($scope[$field] ?? []), 'is_string'));
                if ($values !== []) $family[] = ['key' => WooProductConditionRepository::META_PREFIX . $key, 'value' => $values, 'compare' => 'IN'];
            }
            if (count($family) === 1) {
                $query['post__in'] = [0]; // An explicitly empty scope must not become an unrestricted lookup.
            } else {
                $query['meta_query'] = ['relation' => 'AND', (array) ($query['meta_query'] ?? []), $family];
            }
        }
        return $query;
    }

    public function searchOrder(string $order, \WP_Query $query): string
    {
        if (!$query->get(self::AVAILABLE) || !$query->is_main_query() || !$query->is_search() || $order === '') return $order;
        global $wpdb;
        // Preserve every existing ranking/sort expression. Legacy joins left equal
        // relevance/date rows unordered, so plan changes could reshuffle pagination.
        if (preg_match('/(?:\\b|`)' . preg_quote($wpdb->posts, '/') . '`?\\.`?ID\\b/i', $order)) return $order;
        return $order . ', ' . $wpdb->posts . '.ID DESC';
    }

    public function availableWhere(string $where, \WP_Query $query): string
    {
        if (!$query->get(self::AVAILABLE)) return $where;
        global $wpdb;
        // Equivalent to DeviceAvailability::constraint(), including missing/empty model
        // and unmanaged-stock cases. Correlated key probes avoid broad postmeta joins.
        $exists = static fn(string $key, string $comparison = ''): string =>
            'EXISTS (SELECT 1 FROM ' . $wpdb->postmeta . ' ak_available_meta WHERE ak_available_meta.post_id = '
            . $wpdb->posts . '.ID AND ak_available_meta.meta_key = ' . $wpdb->prepare('%s', $key) . $comparison . ')';
        return $where . ' AND (NOT ' . $exists('_appleklinika_device_model')
            . ' OR ' . $exists('_appleklinika_device_model', " AND ak_available_meta.meta_value = ''")
            . ' OR (' . $exists('_stock_status', " AND ak_available_meta.meta_value = 'instock'")
            . ' AND (' . $exists('_manage_stock', " AND ak_available_meta.meta_value != 'yes'")
            . ' OR ' . $exists('_stock', ' AND CAST(ak_available_meta.meta_value AS SIGNED) > 0') . ')))';
    }
}
