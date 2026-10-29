<?php

declare(strict_types=1);
namespace Appleklinika\Inventory\Infrastructure\WordPress;

use Appleklinika\Inventory\Application\Protection\ProtectionCatalog;
use Appleklinika\Inventory\Domain\Protection\ProtectionSelection;

final class WooProtectionCatalog implements ProtectionCatalog
{
    public const GROUP = '_appleklinika_protection_group';
    public const TYPES = '_appleklinika_protection_types';
    public const MODELS = '_appleklinika_protection_models';

    /** Render-only request snapshot. Validation always uses forDevice() afresh. */
    private ?array $displayCandidates = null;

    public function forDevice(int $deviceId): array
    {
        $device = wc_get_product($deviceId);
        return $device ? $this->options($device, null) : [];
    }

    public function forDisplay(\WC_Product $device): array
    {
        if ($device->get_meta(self::GROUP) !== ''
            || !isset(ProtectionSelection::DEVICE_TYPES[(string) $device->get_meta('_appleklinika_device_type')])) return [];
        $this->displayCandidates ??= $this->candidates();
        return $this->options($device, $this->displayCandidates);
    }

    private function options(\WC_Product $device, ?array $candidates): array
    {
        if (!$device || $device->get_meta(self::GROUP) !== '') return [];
        $type = (string) $device->get_meta('_appleklinika_device_type');
        $model = (string) $device->get_meta('_appleklinika_device_model');
        if (!isset(ProtectionSelection::DEVICE_TYPES[$type])) return [];
        $options = [];
        foreach ($candidates ?? $this->candidates() as $product) {
            $id = $product->get_id();
            if (!$product || !$product->is_type('simple') || $product->get_catalog_visibility() !== 'hidden' || $product->get_meta('_appleklinika_device_model') !== '') continue;
            $types = (array) $product->get_meta(self::TYPES);
            $models = (array) $product->get_meta(self::MODELS);
            if (!ProtectionSelection::eligible($type, $model, $types, $models)) continue;
            $options[$id] = ['id' => $id, 'group' => (string) $product->get_meta(self::GROUP), 'name' => $product->get_name(),
                'available' => $product->is_purchasable() && $product->is_in_stock() && (float) $product->get_price() > 0,
                'price_html' => $product->get_price() !== '' ? wc_price(wc_get_price_to_display($product)) : ''];
        }
        // Independent checkbox slots must resolve unambiguously for this device.
        foreach (['camera', 'adapter'] as $group) {
            $matches = array_keys(array_filter($options, static fn(array $item): bool => $item['group'] === $group));
            if (count($matches) > 1) {
                foreach ($matches as $id) $options[$id]['available'] = false;
            }
        }
        return $options;
    }
    /** @return list<\WC_Product> */
    private function candidates(): array
    {
        // Hidden native Woo products are deliberately queried here, never as devices.
        $ids = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'numberposts' => -1,
            'fields' => 'ids', 'orderby' => 'menu_order ID', 'order' => 'ASC',
            'meta_query' => [['key' => self::GROUP, 'value' => array_keys(ProtectionSelection::GROUPS), 'compare' => 'IN']]]);
        $products = array_values(array_filter(array_map('wc_get_product', $ids)));
        WooProductMetadata::prime($products);
        return $products;
    }
}
