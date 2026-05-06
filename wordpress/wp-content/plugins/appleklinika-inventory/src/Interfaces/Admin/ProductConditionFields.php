<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Interfaces\Admin;

use Appleklinika\Inventory\Application\ProductCondition\SaveProductConditionCommand;
use Appleklinika\Inventory\Application\ProductCondition\SaveProductConditionHandler;
use Appleklinika\Inventory\Domain\ProductCondition\Grade;
use Appleklinika\Inventory\Infrastructure\WordPress\DeviceCatalogRepository;
use Appleklinika\Inventory\Infrastructure\WordPress\WooProductConditionRepository;

final class ProductConditionFields
{
    private const NONCE_ACTION = 'appleklinika_save_product_condition';
    private const NONCE_NAME = 'appleklinika_product_condition_nonce';

    public function __construct(
        private readonly SaveProductConditionHandler $saveHandler,
        private readonly WooProductConditionRepository $repository,
        private readonly DeviceCatalogRepository $deviceCatalogRepository
    ) {
    }

    public function register(): void
    {
        add_action('woocommerce_product_options_general_product_data', [$this, 'render']);
        add_action('woocommerce_process_product_meta', [$this, 'save']);
    }

    public function render(): void
    {
        global $post;

        if (! $post instanceof \WP_Post) {
            return;
        }

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        echo '<div class="options_group">';

        woocommerce_wp_select([
            'id' => 'appleklinika_device_model',
            'label' => 'Apple modell',
            'options' => $this->deviceCatalogRepository->modelOptions(),
            'value' => $this->repository->get($post->ID, 'device_model'),
        ]);

        woocommerce_wp_text_input([
            'id' => 'appleklinika_battery_health',
            'label' => 'Akkumulátor állapot (%)',
            'type' => 'number',
            'custom_attributes' => [
                'min' => '0',
                'max' => '100',
                'step' => '1',
            ],
            'value' => $this->repository->get($post->ID, 'battery_health'),
        ]);

        woocommerce_wp_select([
            'id' => 'appleklinika_battery_option',
            'label' => 'Akkumulátor opció',
            'options' => [
                'standard' => 'Standard',
                'aftermarket_new' => 'Új utángyártott akkumulátor',
                'factory_new' => 'Új gyári akkumulátor',
            ],
            'value' => $this->repository->get($post->ID, 'battery_option') ?: 'standard',
        ]);

        woocommerce_wp_select([
            'id' => 'appleklinika_storage_capacity',
            'label' => 'Tárhely',
            'options' => [
                '' => 'Válassz tárhelyet',
                '64_gb' => '64 GB',
                '128_gb' => '128 GB',
                '256_gb' => '256 GB',
                '512_gb' => '512 GB',
                '1_tb' => '1 TB',
                '2_tb' => '2 TB',
            ],
            'value' => $this->repository->get($post->ID, 'storage_capacity'),
        ]);

        woocommerce_wp_select([
            'id' => 'appleklinika_color',
            'label' => 'Szín',
            'options' => $this->deviceCatalogRepository->colorOptions(),
            'value' => $this->repository->get($post->ID, 'color'),
        ]);
        $this->renderModelColorScript($post->ID);

        woocommerce_wp_select([
            'id' => 'appleklinika_sim_config',
            'label' => 'SIM konfiguráció',
            'options' => [
                '' => 'Válassz SIM konfigurációt',
                'dual_esim' => 'Dual eSIM',
                'physical_esim' => 'Fizikai + eSIM',
                'dual_physical' => 'Dual fizikai',
            ],
            'value' => $this->repository->get($post->ID, 'sim_config'),
        ]);

        woocommerce_wp_select([
            'id' => 'appleklinika_warranty_duration',
            'label' => 'Garancia',
            'options' => [
                '' => 'Válassz garanciát',
                '3_months' => '3 hónap',
                '6_months' => '6 hónap',
                '12_months' => '12 hónap',
                '24_months' => '24 hónap',
                '36_months' => '36 hónap',
            ],
            'value' => $this->repository->get($post->ID, 'warranty_duration'),
        ]);

        woocommerce_wp_textarea_input([
            'id' => 'appleklinika_accessories',
            'label' => 'Tartozékok',
            'value' => $this->repository->get($post->ID, 'accessories'),
        ]);

        woocommerce_wp_textarea_input([
            'id' => 'appleklinika_short_device_description',
            'label' => 'Rövid leírás',
            'value' => $this->repository->get($post->ID, 'short_device_description'),
        ]);

        woocommerce_wp_text_input([
            'id' => 'appleklinika_internal_identifier',
            'label' => 'Belső azonosító / IMEI',
            'description' => 'Csak admin használatra. Frontenden nem jelenhet meg.',
            'value' => $this->repository->get($post->ID, 'internal_identifier'),
        ]);

        $this->gradeSelect('body_grade', 'Ház állapota', $post->ID);
        $this->gradeSelect('camera_island_grade', 'Kamerasziget állapota', $post->ID);
        $this->gradeSelect('display_grade', 'Kijelző állapota', $post->ID);

        $this->gradeSelect('overall_grade', 'Összesített grade', $post->ID);

        echo '</div>';
    }

    public function save(int $productId): void
    {
        if (! current_user_can('edit_product', $productId)) {
            return;
        }

        if (
            ! isset($_POST[self::NONCE_NAME])
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)
        ) {
            return;
        }

        $this->saveHandler->handle(new SaveProductConditionCommand($productId, [
            'device_model' => $this->postedValue('appleklinika_device_model'),
            'battery_health' => $this->postedValue('appleklinika_battery_health'),
            'battery_option' => $this->postedValue('appleklinika_battery_option', 'standard'),
            'storage_capacity' => $this->postedValue('appleklinika_storage_capacity'),
            'color' => $this->postedValue('appleklinika_color'),
            'sim_config' => $this->postedValue('appleklinika_sim_config'),
            'warranty_duration' => $this->postedValue('appleklinika_warranty_duration'),
            'accessories' => $this->postedValue('appleklinika_accessories'),
            'short_device_description' => $this->postedValue('appleklinika_short_device_description'),
            'internal_identifier' => $this->postedValue('appleklinika_internal_identifier'),
            'body_grade' => $this->postedValue('appleklinika_body_grade', Grade::B),
            'camera_island_grade' => $this->postedValue('appleklinika_camera_island_grade', Grade::B),
            'display_grade' => $this->postedValue('appleklinika_display_grade', Grade::B),
            'overall_grade' => $this->postedValue('appleklinika_overall_grade', Grade::B),
        ]));
    }

    private function gradeSelect(string $field, string $label, int $productId): void
    {
        woocommerce_wp_select([
            'id' => 'appleklinika_' . $field,
            'label' => $label,
            'options' => Grade::options(),
            'value' => $this->repository->get($productId, $field) ?: Grade::B,
        ]);
    }

    private function postedValue(string $key, string $default = ''): string
    {
        return isset($_POST[$key]) ? (string) wp_unslash($_POST[$key]) : $default;
    }

    private function renderModelColorScript(int $productId): void
    {
        $catalog = [];

        foreach ($this->deviceCatalogRepository->all() as $device) {
            $catalog[$device['key']] = $device['colors'];
        }

        echo '<script>';
        echo '(function(){';
        echo 'const catalog=' . wp_json_encode($catalog) . ';';
        echo 'const selectedColor=' . wp_json_encode($this->repository->get($productId, 'color')) . ';';
        echo 'const modelSelect=document.getElementById("appleklinika_device_model");';
        echo 'const colorSelect=document.getElementById("appleklinika_color");';
        echo 'if(!modelSelect||!colorSelect){return;}';
        echo 'function option(value,label){const item=document.createElement("option");item.value=value;item.textContent=label;return item;}';
        echo 'function refreshColors(){';
        echo 'const current=colorSelect.value||selectedColor;';
        echo 'const colors=catalog[modelSelect.value]||{};';
        echo 'colorSelect.innerHTML="";';
        echo 'colorSelect.appendChild(option("","Válassz színt"));';
        echo 'Object.keys(colors).forEach(function(key){const item=option(key,colors[key]);if(key===current){item.selected=true;}colorSelect.appendChild(item);});';
        echo 'if(current&&colors[current]===undefined){const item=option(current,current);item.selected=true;colorSelect.appendChild(item);}';
        echo '}';
        echo 'modelSelect.addEventListener("change",refreshColors);';
        echo 'refreshColors();';
        echo '})();';
        echo '</script>';
    }
}
