<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Interfaces;

use Appleklinika\Inventory\Infrastructure\WordPress\DeviceAvailability;
use Appleklinika\Inventory\Infrastructure\WordPress\ProductPhotoProcessor;
use Appleklinika\Inventory\Infrastructure\WordPress\WooDeviceProductSafety;

final class DeviceProductHooks
{
    public function register(): void
    {
        $safety = new WooDeviceProductSafety();
        add_action('woocommerce_before_product_object_save', [$safety, 'validate']);
        add_action('woocommerce_after_product_object_save', [$safety, 'attachPhotos']);
        add_action('woocommerce_product_duplicate_before_save', [$safety, 'prepareDuplicate'], 10, 2);
        add_action('pre_get_posts', [$this, 'catalog'], 30);
        add_filter('woocommerce_product_object_query_args', [$this, 'markPublicQuery']);
        add_filter('woocommerce_product_data_store_cpt_get_products_query', [$this, 'productQuery'], 20, 2);
        add_filter('wp_generate_attachment_metadata', [$this, 'photoMetadata'], 20, 2);
    }

    public function catalog(\WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query() || $query->is_singular()) {
            return;
        }
        if (!$query->is_post_type_archive('product') && !$query->is_tax(get_object_taxonomies('product'))
            && $query->get('post_type') !== 'product') {
            return;
        }
        $meta = (array) $query->get('meta_query');
        $meta[] = DeviceAvailability::constraint();
        $query->set('meta_query', $meta);
    }

    public function markPublicQuery(array $vars): array
    {
        // Woo removes "visibility" before the later CPT query filter runs.
        if (in_array($vars['visibility'] ?? '', ['catalog', 'visible', 'search'], true)) {
            $vars['appleklinika_available_devices'] = true;
        }
        return $vars;
    }

    public function productQuery(array $query, array $vars): array
    {
        if (!empty($vars['appleklinika_available_devices'])) {
            $query['meta_query'][] = DeviceAvailability::constraint();
        }
        unset($query['appleklinika_available_devices']);
        return $query;
    }

    public function photoMetadata(array $metadata, int $id): array
    {
        if (get_post_type((int) wp_get_post_parent_id($id)) !== 'product') {
            return $metadata;
        }
        return (new ProductPhotoProcessor())->sanitizeMetadata($metadata, $id);
    }
}
