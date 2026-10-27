<?php

declare(strict_types=1);
namespace Appleklinika\Inventory\Interfaces\Admin;

use Appleklinika\Inventory\Domain\Protection\ProtectionSelection;
use Appleklinika\Inventory\Infrastructure\WordPress\WooProtectionCatalog;

final class ProductProtectionFields
{
    public function register(): void
    {
        add_action('woocommerce_product_options_general_product_data', [$this, 'render']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save']);
    }

    public function render(): void
    {
        global $product_object;
        if (!$product_object instanceof \WC_Product) return;
        echo '<div class="options_group show_if_simple">';
        if ($product_object->get_meta('_appleklinika_device_model') !== '' && $product_object->get_meta(WooProtectionCatalog::GROUP) !== '') {
            echo '<p class="form-field"><strong>A készülék és a kiegészítő besorolás ütközik.</strong> Válaszd a „Nem kiegészítő” értéket, majd ellenőrizd a készülék adatait mentés előtt.</p>';
        }
        woocommerce_wp_select(['id' => WooProtectionCatalog::GROUP, 'label' => 'Extra védelem csoport', 'options' => ['' => 'Nem kiegészítő'] + ProtectionSelection::GROUPS,
            'description' => 'A kiegészítő rejtett Woo-termék. Árát és készletét a szokásos Woo mezőkben kezeld.']);
        $types = (array) $product_object->get_meta(WooProtectionCatalog::TYPES);
        foreach (ProtectionSelection::DEVICE_TYPES as $key => $label) {
            woocommerce_wp_checkbox(['id' => 'ak_protection_type_' . $key, 'label' => 'Kiegészítő ehhez: ' . $label, 'value' => in_array($key, $types, true) ? 'yes' : 'no']);
        }
        woocommerce_wp_text_input(['id' => WooProtectionCatalog::MODELS, 'label' => 'Kompatibilis modellkulcsok', 'value' => implode(', ', (array) $product_object->get_meta(WooProtectionCatalog::MODELS)),
            'description' => 'Opcionális szűkítés: az Apple Klinika katalógus meglévő modellkulcsai vesszővel elválasztva (pl. iphone_13_pro). Üresen a megjelölt készüléktípusok minden modellje. Csak igazolt kompatibilitást jelölj.']);
        echo '</div>';
    }

    public function save(\WC_Product $product): void
    {
        if (!current_user_can('edit_post', $product->get_id()) || !isset($_POST[WooProtectionCatalog::GROUP])) return;
        $group = is_string($_POST[WooProtectionCatalog::GROUP]) ? sanitize_key(wp_unslash($_POST[WooProtectionCatalog::GROUP])) : '';
        $group = isset(ProtectionSelection::GROUPS[$group]) && $product->is_type('simple') ? $group : '';
        $model = isset($_POST['appleklinika_device_model']) && is_scalar($_POST['appleklinika_device_model']) ? sanitize_key(wp_unslash($_POST['appleklinika_device_model'])) : (string) $product->get_meta('_appleklinika_device_model');
        if ($model === '') $model = (string) $product->get_meta('_appleklinika_device_model');
        try { \Appleklinika\Inventory\Domain\DeviceProduct\PhysicalDevice::assertNotAccessory($model, $group); }
        catch (\InvalidArgumentException $error) { \WC_Admin_Meta_Boxes::add_error($error->getMessage()); $product->set_status('draft'); return; }
        $types = array_values(array_filter(array_keys(ProtectionSelection::DEVICE_TYPES), static fn(string $key): bool => isset($_POST['ak_protection_type_' . $key])));
        $raw = isset($_POST[WooProtectionCatalog::MODELS]) && is_string($_POST[WooProtectionCatalog::MODELS]) ? wp_unslash($_POST[WooProtectionCatalog::MODELS]) : '';
        $models = array_values(array_unique(array_filter(array_map('sanitize_key', preg_split('/[\s,]+/', $raw) ?: []))));
        $product->update_meta_data(WooProtectionCatalog::GROUP, $group);
        $product->update_meta_data(WooProtectionCatalog::TYPES, $types);
        $product->update_meta_data(WooProtectionCatalog::MODELS, $models);
        if ($group !== '') $product->set_catalog_visibility('hidden');
    }
}
