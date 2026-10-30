<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Infrastructure\WordPress;

use Appleklinika\Inventory\Domain\DeviceProduct\PhysicalDevice;
use WC_Product;

/** Woo persistence safeguards; no parallel device storage. */
final class WooDeviceProductSafety
{
    public function validate(WC_Product $product): void
    {
        PhysicalDevice::assertNotAccessory(
            (string) $product->get_meta('_appleklinika_device_model'),
            (string) $product->get_meta('_appleklinika_protection_group')
        );
        (new ApprovedMixedTaxConfiguration())->defaultForNewAccessory($product);
        if ($product->get_meta(PhysicalDevice::MARKER) !== 'yes') {
            return; // Legacy/demo products are not silently migrated.
        }
        // Publication is validated separately from ordinary stock updates. Never
        // make payment/restock depend on image processing or editorial fields.
        if ($product->get_status() === 'publish' && (!$product->get_id() || isset($product->get_changes()['status']))) {
            $data = ['sku' => $product->get_sku(), 'regular_price' => $product->get_regular_price(),
                'photos' => array_filter([$product->get_image_id()])];
            foreach (['imei', 'serial_number', 'color', 'overall_grade', 'device_type', 'storage_capacity'] as $key) {
                $data[$key] = $product->get_meta('_appleklinika_' . $key);
            }
            PhysicalDevice::assertPublication($data);
        }
        (new ApprovedMixedTaxConfiguration())->defaultForNewDevice($product, (string)$product->get_meta('_appleklinika_device_type'));
        PhysicalDevice::quantity($product->get_stock_quantity());
        if (!$product->is_type('simple') || !$product->get_manage_stock()
            || !$product->is_sold_individually() || $product->get_backorders() !== 'no') {
            throw new \InvalidArgumentException('A fizikai készülék egyszerű, egyedi, kezelt készletű termék; utánrendelés nem engedélyezett.');
        }
    }

    public function attachPhotos(WC_Product $product): void
    {
        if ($product->get_meta(PhysicalDevice::MARKER) !== 'yes') {
            return;
        }
        $photos = array_filter(array_merge([$product->get_image_id()], $product->get_gallery_image_ids()));
        foreach ($photos as $id) {
            if (!(int) wp_get_post_parent_id($id)) {
                wp_update_post(['ID' => $id, 'post_parent' => $product->get_id()]);
            }
        }
    }

    public function prepareDuplicate(WC_Product $copy, WC_Product $source): void
    {
        if ($source->get_meta('_appleklinika_device_model') === '') {
            return;
        }
        $copy->set_status('draft');
        $copy->set_sku('');
        $copy->set_global_unique_id('');
        $copy->set_image_id(0);
        // WC_Data::apply_changes recursively merges arrays. Clear the loaded
        // collection too, so a second save cannot resurrect the source gallery.
        $wasRead = $copy->get_object_read();
        $copy->set_object_read(false);
        $copy->set_gallery_image_ids([]);
        $copy->set_object_read($wasRead);
        $copy->set_description('');
        $copy->set_short_description('');
        $copy->set_purchase_note('');
        $copy->set_manage_stock(true);
        $copy->set_stock_quantity(1);
        $copy->set_stock_status('instock');
        $copy->set_sold_individually(true);
        $copy->set_backorders('no');
        $copy->set_catalog_visibility('visible');

        // Only model-level Apple Klinika fields may survive a native duplicate.
        $modelFields = ['device_type', 'device_model', 'storage_capacity', 'color',
            'sim_config', 'connectivity', 'screen_size', 'processor_chip', 'ram_size',
            'case_size', 'case_material'];
        foreach ($copy->get_meta_data() as $meta) {
            $key = (string) $meta->key;
            if (str_starts_with($key, '_appleklinika_')
                && !in_array(substr($key, strlen('_appleklinika_')), $modelFields, true)) {
                $copy->delete_meta_data($key);
            }
        }
        foreach (['_ak_single_product_gallery_image_id', 'wc_szamlazz_megjegyzes'] as $key) {
            $copy->delete_meta_data($key);
        }
        foreach ((new DeviceCatalogRepository())->all() as $model) {
            if ($model['key'] === $copy->get_meta('_appleklinika_device_model')) {
                $copy->set_name(PhysicalDevice::title([
                    'storage_capacity' => $copy->get_meta('_appleklinika_storage_capacity'),
                ], $model));
                break;
            }
        }
        $copy->set_regular_price('');
        $copy->set_sale_price('');
        $copy->set_price('');
        $copy->set_slug('');
        $copy->update_meta_data('_appleklinika_custom_name', 'no');
        $copy->update_meta_data(PhysicalDevice::MARKER, 'yes');
    }
}
