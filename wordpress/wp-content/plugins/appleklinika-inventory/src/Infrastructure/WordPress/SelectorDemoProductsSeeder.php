<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Infrastructure\WordPress;

final class SelectorDemoProductsSeeder
{
    private const TRIGGER = 'appleklinika_seed_selector_demo';
    private const TRIGGER_VALUE = 'confirm';

    public function __construct(private readonly WooProductConditionRepository $repository)
    {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'maybeSeed']);
    }

    public function maybeSeed(): void
    {
        if (! isset($_GET[self::TRIGGER]) || (string) $_GET[self::TRIGGER] !== self::TRIGGER_VALUE) {
            return;
        }

        if (! current_user_can('edit_products') || ! class_exists('\WC_Product_Simple')) {
            wp_die('Appleklinika selector demo products require WooCommerce product editing permission.');
        }

        $firstProductId = $this->seedProducts();
        $redirectUrl = $firstProductId > 0 ? get_permalink($firstProductId) : admin_url('edit.php?post_type=product');

        wp_safe_redirect($redirectUrl);
        exit;
    }

    private function seedProducts(): int
    {
        $firstProductId = 0;

        $this->deleteDeprecatedBatteryVariantProducts();

        foreach ($this->demoProducts() as $productData) {
            $productId = $this->upsertProduct($productData);

            if ($firstProductId === 0) {
                $firstProductId = $productId;
            }
        }

        return $firstProductId;
    }

    /**
     * @param array<string, string> $productData
     */
    private function upsertProduct(array $productData): int
    {
        $productId = wc_get_product_id_by_sku($productData['sku']);
        $product = $productId > 0 ? wc_get_product($productId) : new \WC_Product_Simple();

        if (! $product instanceof \WC_Product_Simple) {
            $product = new \WC_Product_Simple();
        }

        $product->set_name($productData['name']);
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_sku($productData['sku']);
        $product->set_regular_price($productData['regular_price']);
        $product->set_sale_price($productData['sale_price']);
        $product->set_manage_stock(true);
        $product->set_stock_quantity(1);
        $product->set_stock_status('instock');
        $product->set_description($productData['description']);
        $product->set_short_description($productData['short_description']);

        $productId = $product->save();
        $imageId = $this->imageIdForAsset($productData['image']);

        if ($imageId > 0) {
            $product->set_image_id($imageId);
            $product->save();
        }

        $this->repository->save($productId, [
            'device_model' => 'iphone_13_pro',
            'storage_capacity' => $productData['storage_capacity'],
            'color' => $productData['color'],
            'sim_config' => $productData['sim_config'],
            'battery_health' => $productData['battery_health'],
            'battery_option' => 'standard',
            'warranty_duration' => $productData['warranty_duration'],
            'accessories' => $productData['accessories'],
            'short_device_description' => $productData['short_description'],
            'internal_identifier' => $productData['internal_identifier'],
            'body_grade' => $productData['overall_grade'],
            'camera_island_grade' => $productData['overall_grade'],
            'display_grade' => $productData['overall_grade'],
            'overall_grade' => $productData['overall_grade'],
        ]);

        return $productId;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function demoProducts(): array
    {
        $colors = [
            'graphite' => ['label' => 'Grafit', 'image' => 'iphone-13-pro-1.jpg'],
            'silver' => ['label' => 'Ezüst', 'image' => 'iphone-13-pro-2.jpg'],
            'gold' => ['label' => 'Arany', 'image' => 'iphone-13-pro-3.jpg'],
            'sierra_blue' => ['label' => 'Hegyi kék', 'image' => 'iphone-13-pro-4.jpg'],
            'alpine_green' => ['label' => 'Alpesi zöld', 'image' => 'iphone-13-pro-1.jpg'],
        ];
        $storages = [
            '128_gb' => ['label' => '128 GB', 'price' => 0],
            '256_gb' => ['label' => '256 GB', 'price' => 37000],
            '512_gb' => ['label' => '512 GB', 'price' => 79000],
            '1_tb' => ['label' => '1 TB', 'price' => 126000],
        ];
        $grades = [
            'a_plus' => ['label' => 'A+', 'price' => 42000],
            'a' => ['label' => 'A', 'price' => 21000],
            'b' => ['label' => 'B', 'price' => 0],
            'c' => ['label' => 'C', 'price' => -36000],
        ];
        $products = [];
        $index = 0;

        foreach ($colors as $colorKey => $colorData) {
            foreach ($storages as $storageKey => $storageData) {
                foreach ($grades as $gradeKey => $gradeData) {
                    $basePrice = 184990 + $storageData['price'] + $gradeData['price'] + (($index % 5) * 3500);
                    $regularPrice = $basePrice + 42000;
                    $health = max(80, 92 - (($index + strlen($colorKey)) % 12));

                    $products[] = [
                        'sku' => sprintf('ak-selector-demo-iphone-13-pro-%s-%s-%s', str_replace('_', '-', $storageKey), str_replace('_', '-', $colorKey), str_replace('_', '-', $gradeKey)),
                        'name' => sprintf('Selector teszt - iPhone 13 Pro %s %s %s', $storageData['label'], $colorData['label'], $gradeData['label']),
                        'regular_price' => (string) $regularPrice,
                        'sale_price' => (string) $basePrice,
                        'storage_capacity' => $storageKey,
                        'color' => $colorKey,
                        'sim_config' => $this->simConfigFor($storageKey, $colorKey, $gradeKey),
                        'battery_health' => (string) $health,
                        'warranty_duration' => $gradeKey === 'a_plus' ? '24_months' : '12_months',
                        'accessories' => $index % 3 === 0 ? 'Doboz, töltőkábel, SIM tű' : 'Töltőkábel, SIM tű',
                        'overall_grade' => $gradeKey,
                        'internal_identifier' => sprintf('AK-DEMO-13PRO-%03d', $index + 1),
                        'image' => $colorData['image'],
                        'short_description' => 'Helyi selector teszttermék, valós WooCommerce termékként létrehozva.',
                        'description' => 'Ez a helyi fejlesztési termék a szín, tárhely és állapot selector sima, oldalfrissítés nélküli termékváltását teszteli. Az akkumulátor külön feláras extra, nem külön készüléktermék.',
                    ];
                    $index++;
                }
            }
        }

        return $products;
    }

    private function simConfigFor(string $storageKey, string $colorKey, string $gradeKey): string
    {
        if ($storageKey === '256_gb' && $colorKey === 'silver') {
            return 'dual_esim';
        }

        if ($storageKey === '512_gb' && $colorKey === 'gold') {
            return 'physical_esim';
        }

        if ($gradeKey === 'a_plus') {
            return 'dual_esim';
        }

        if ($gradeKey === 'c') {
            return 'dual_physical';
        }

        return 'physical_esim';
    }

    private function deleteDeprecatedBatteryVariantProducts(): void
    {
        foreach (['aftermarket-new', 'factory-new', 'standard'] as $batterySuffix) {
            $products = get_posts([
                'post_type' => 'product',
                'post_status' => ['publish', 'draft', 'pending', 'private'],
                'posts_per_page' => 300,
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => '_sku',
                        'value' => 'ak-selector-demo-iphone-13-pro-',
                        'compare' => 'LIKE',
                    ],
                    [
                        'key' => '_sku',
                        'value' => '-' . $batterySuffix,
                        'compare' => 'LIKE',
                    ],
                ],
            ]);

            foreach ($products as $productId) {
                wp_delete_post((int) $productId, true);
            }
        }
    }

    private function imageIdForAsset(string $filename): int
    {
        $source = dirname(__DIR__, 3) . '/assets/demo/' . $filename;

        if (! is_readable($source)) {
            return 0;
        }

        $existing = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'meta_key' => '_appleklinika_demo_asset',
            'meta_value' => $filename,
        ]);

        if (is_array($existing) && isset($existing[0])) {
            return (int) $existing[0];
        }

        $upload = wp_upload_bits('appleklinika-' . $filename, null, (string) file_get_contents($source));

        if (! empty($upload['error']) || empty($upload['file'])) {
            return 0;
        }

        $attachmentId = wp_insert_attachment([
            'post_mime_type' => $upload['type'] ?? 'image/jpeg',
            'post_title' => 'Appleklinika demo ' . pathinfo($filename, PATHINFO_FILENAME),
            'post_content' => '',
            'post_status' => 'inherit',
        ], $upload['file']);

        if (! is_int($attachmentId) || $attachmentId <= 0) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachmentId, $upload['file']);
        wp_update_attachment_metadata($attachmentId, $metadata);
        update_post_meta($attachmentId, '_appleklinika_demo_asset', $filename);

        return $attachmentId;
    }
}
