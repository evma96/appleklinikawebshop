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

        if (! empty($productData['category_slug']) && ! empty($productData['category_name'])) {
            $categoryId = $this->ensureProductCategory($productData['category_slug'], $productData['category_name']);
            if ($categoryId > 0) {
                $product->set_category_ids([$categoryId]);
                $product->save();
            }
        }

        $saveData = [
            'device_model' => $productData['device_model'] ?? 'iphone_13_pro',
            'storage_capacity' => $productData['storage_capacity'] ?? '',
            'color' => $productData['color'] ?? '',
            'sim_config' => $productData['sim_config'] ?? '',
            'connectivity' => $productData['connectivity'] ?? '',
            'screen_size' => $productData['screen_size'] ?? '',
            'processor_chip' => $productData['processor_chip'] ?? '',
            'ram_size' => $productData['ram_size'] ?? '',
            'case_size' => $productData['case_size'] ?? '',
            'case_material' => $productData['case_material'] ?? '',
            'strap' => $productData['strap'] ?? '',
            'battery_health' => $productData['battery_health'] ?? '',
            'battery_option' => $productData['battery_option'] ?? 'standard',
            'warranty_duration' => $productData['warranty_duration'] ?? '12_months',
            'accessories' => $productData['accessories'] ?? '',
            'short_device_description' => $productData['short_description'] ?? '',
            'internal_identifier' => $productData['internal_identifier'] ?? '',
            'body_grade' => $productData['overall_grade'] ?? '',
            'camera_island_grade' => $productData['overall_grade'] ?? '',
            'display_grade' => $productData['overall_grade'] ?? '',
            'overall_grade' => $productData['overall_grade'] ?? '',
        ];

        if (isset($productData['device_type'])) {
            $saveData['device_type'] = $productData['device_type'];
        }

        $this->repository->save($productId, $saveData);

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

        return array_merge($products, $this->ipadDemoProducts(), $this->macbookDemoProducts(), $this->watchDemoProducts());
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function ipadDemoProducts(): array
    {
        return [
            [
                'sku' => 'ak-demo-ipad-air-5-64gb-blue-wifi-a',
                'name' => 'Demo - iPad Air 5 64 GB Kék Wi-Fi A',
                'regular_price' => '219990',
                'sale_price' => '199990',
                'device_type' => 'ipad',
                'device_model' => 'ipad_air_5th_generation',
                'storage_capacity' => '64_gb',
                'color' => 'blue',
                'connectivity' => 'wifi',
                'battery_health' => '94',
                'warranty_duration' => '12_months',
                'accessories' => 'Töltőkábel',
                'overall_grade' => 'a',
                'internal_identifier' => 'AK-DEMO-IPAD-001',
                'image' => 'iphone-13-pro-2.jpg',
                'category_slug' => 'ipad',
                'category_name' => 'iPad',
                'short_description' => 'Valós WooCommerce iPad demo termék kategória-specifikus chipekhez és szűrőkhöz.',
                'description' => 'Helyi iPad demo termék a tárhely, szín, kapcsolat, állapot és akkumulátor szűrők teszteléséhez.',
            ],
            [
                'sku' => 'ak-demo-ipad-air-5-256gb-starlight-cellular-b',
                'name' => 'Demo - iPad Air 5 256 GB Csillagfény Cellular B',
                'regular_price' => '259990',
                'sale_price' => '',
                'device_type' => 'ipad',
                'device_model' => 'ipad_air_5th_generation',
                'storage_capacity' => '256_gb',
                'color' => 'starlight',
                'connectivity' => 'wifi_cellular',
                'battery_health' => '89',
                'warranty_duration' => '12_months',
                'accessories' => 'Töltőkábel, SIM tű',
                'overall_grade' => 'b',
                'internal_identifier' => 'AK-DEMO-IPAD-002',
                'image' => 'iphone-13-pro-3.jpg',
                'category_slug' => 'ipad',
                'category_name' => 'iPad',
                'short_description' => 'Valós WooCommerce iPad demo termék mobilhálózatos változattal.',
                'description' => 'Helyi iPad demo termék a Wi-Fi + Cellular kapcsolat és állapot filter teszteléséhez.',
            ],
            [
                'sku' => 'ak-demo-ipad-pro-11-m2-512gb-space-gray-cellular-a-plus',
                'name' => 'Demo - iPad Pro 11 M2 512 GB Asztroszürke Cellular A+',
                'regular_price' => '419990',
                'sale_price' => '379990',
                'device_type' => 'ipad',
                'device_model' => 'ipad_pro_11_inch_m2',
                'storage_capacity' => '512_gb',
                'color' => 'space_gray',
                'connectivity' => 'wifi_cellular',
                'battery_health' => '97',
                'warranty_duration' => '24_months',
                'accessories' => 'Töltőkábel, SIM tű',
                'overall_grade' => 'a_plus',
                'internal_identifier' => 'AK-DEMO-IPAD-003',
                'image' => 'iphone-13-pro-4.jpg',
                'category_slug' => 'ipad',
                'category_name' => 'iPad',
                'short_description' => 'Valós WooCommerce iPad Pro demo termék akciós árral.',
                'description' => 'Helyi iPad Pro demo termék a Pro modell, nagy tárhely és akciós kártya teszteléséhez.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function macbookDemoProducts(): array
    {
        return [
            [
                'sku' => 'ak-demo-macbook-air-m2-13-8gb-256gb-starlight-a',
                'name' => 'Demo - MacBook Air M2 13" 8 GB 256 GB Csillagfény A',
                'regular_price' => '389990',
                'sale_price' => '349990',
                'device_type' => 'macbook',
                'device_model' => 'macbook_air_13_inch_m2',
                'screen_size' => '13_inch',
                'processor_chip' => 'm2',
                'ram_size' => '8_gb',
                'storage_capacity' => '256_gb',
                'color' => 'starlight',
                'warranty_duration' => '12_months',
                'accessories' => 'USB-C töltő, kábel',
                'overall_grade' => 'a',
                'internal_identifier' => 'AK-DEMO-MAC-001',
                'image' => 'iphone-13-pro-1.jpg',
                'category_slug' => 'macbook',
                'category_name' => 'MacBook',
                'short_description' => 'Valós WooCommerce MacBook demo termék RAM, chip és kijelző filterhez.',
                'description' => 'Helyi MacBook demo termék a MacBook-specifikus chipek és szűrők teszteléséhez.',
            ],
            [
                'sku' => 'ak-demo-macbook-pro-14-m3-16gb-512gb-space-black-a-plus',
                'name' => 'Demo - MacBook Pro 14" M3 Pro 16 GB 512 GB Asztrofekete A+',
                'regular_price' => '689990',
                'sale_price' => '',
                'device_type' => 'macbook',
                'device_model' => 'macbook_pro_14_inch_m3',
                'screen_size' => '14_inch',
                'processor_chip' => 'm3_pro',
                'ram_size' => '16_gb',
                'storage_capacity' => '512_gb',
                'color' => 'space_black',
                'warranty_duration' => '24_months',
                'accessories' => 'USB-C töltő, kábel, doboz',
                'overall_grade' => 'a_plus',
                'internal_identifier' => 'AK-DEMO-MAC-002',
                'image' => 'iphone-13-pro-2.jpg',
                'category_slug' => 'macbook',
                'category_name' => 'MacBook',
                'short_description' => 'Valós WooCommerce MacBook Pro demo termék nem akciós árral.',
                'description' => 'Helyi MacBook Pro demo termék az M3 Pro, RAM és kijelzőméret szűrés ellenőrzéséhez.',
            ],
            [
                'sku' => 'ak-demo-macbook-pro-16-m3-32gb-1tb-silver-b',
                'name' => 'Demo - MacBook Pro 16" M3 Max 32 GB 1 TB Ezüst B',
                'regular_price' => '879990',
                'sale_price' => '819990',
                'device_type' => 'macbook',
                'device_model' => 'macbook_pro_16_inch_m3_max',
                'screen_size' => '16_inch',
                'processor_chip' => 'm3_max',
                'ram_size' => '32_gb',
                'storage_capacity' => '1_tb',
                'color' => 'silver',
                'warranty_duration' => '12_months',
                'accessories' => 'USB-C töltő, kábel',
                'overall_grade' => 'b',
                'internal_identifier' => 'AK-DEMO-MAC-003',
                'image' => 'iphone-13-pro-3.jpg',
                'category_slug' => 'macbook',
                'category_name' => 'MacBook',
                'short_description' => 'Valós WooCommerce MacBook Pro demo termék nagy tárhellyel.',
                'description' => 'Helyi MacBook Pro demo termék a nagy RAM, nagy tárhely és M Max szűrés teszteléséhez.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function watchDemoProducts(): array
    {
        return [
            [
                'sku' => 'ak-demo-watch-series-9-45mm-aluminium-midnight-gps-a',
                'name' => 'Demo - Apple Watch Series 9 45 mm Alumínium Éjfekete GPS A',
                'regular_price' => '139990',
                'sale_price' => '124990',
                'device_type' => 'apple_watch',
                'device_model' => 'apple_watch_series_9',
                'case_size' => '45_mm',
                'case_material' => 'aluminium',
                'color' => 'midnight',
                'connectivity' => 'gps',
                'strap' => 'Sport szíj',
                'battery_health' => '92',
                'warranty_duration' => '12_months',
                'accessories' => 'Töltőkábel, sport szíj',
                'overall_grade' => 'a',
                'internal_identifier' => 'AK-DEMO-WATCH-001',
                'image' => 'iphone-13-pro-4.jpg',
                'category_slug' => 'apple-watch',
                'category_name' => 'Apple Watch',
                'short_description' => 'Valós WooCommerce Apple Watch demo termék GPS kapcsolattal.',
                'description' => 'Helyi Apple Watch demo termék a tokméret, kapcsolat, szíj és akku chipek teszteléséhez.',
            ],
            [
                'sku' => 'ak-demo-watch-series-8-41mm-steel-silver-cellular-b',
                'name' => 'Demo - Apple Watch Series 8 41 mm Acél Ezüst Cellular B',
                'regular_price' => '169990',
                'sale_price' => '',
                'device_type' => 'apple_watch',
                'device_model' => 'apple_watch_series_8',
                'case_size' => '41_mm',
                'case_material' => 'stainless_steel',
                'color' => 'silver',
                'connectivity' => 'gps_cellular',
                'strap' => 'Milánói szíj',
                'battery_health' => '86',
                'warranty_duration' => '12_months',
                'accessories' => 'Töltőkábel, milánói szíj',
                'overall_grade' => 'b',
                'internal_identifier' => 'AK-DEMO-WATCH-002',
                'image' => 'iphone-13-pro-1.jpg',
                'category_slug' => 'apple-watch',
                'category_name' => 'Apple Watch',
                'short_description' => 'Valós WooCommerce Apple Watch demo termék Cellular kapcsolattal.',
                'description' => 'Helyi Apple Watch demo termék GPS + Cellular kapcsolat és acél tok teszteléséhez.',
            ],
            [
                'sku' => 'ak-demo-watch-ultra-2-49mm-titanium-cellular-a-plus',
                'name' => 'Demo - Apple Watch Ultra 2 49 mm Titán Cellular A+',
                'regular_price' => '269990',
                'sale_price' => '239990',
                'device_type' => 'apple_watch',
                'device_model' => 'apple_watch_ultra_2',
                'case_size' => '49_mm',
                'case_material' => 'titanium',
                'color' => 'natural_titanium',
                'connectivity' => 'gps_cellular',
                'strap' => 'Trail szíj',
                'battery_health' => '95',
                'warranty_duration' => '24_months',
                'accessories' => 'Töltőkábel, trail szíj',
                'overall_grade' => 'a_plus',
                'internal_identifier' => 'AK-DEMO-WATCH-003',
                'image' => 'iphone-13-pro-2.jpg',
                'category_slug' => 'apple-watch',
                'category_name' => 'Apple Watch',
                'short_description' => 'Valós WooCommerce Apple Watch Ultra demo termék.',
                'description' => 'Helyi Apple Watch Ultra demo termék a titán tok, 49 mm tokméret és akciós ár teszteléséhez.',
            ],
        ];
    }

    private function ensureProductCategory(string $slug, string $name): int
    {
        $term = get_term_by('slug', $slug, 'product_cat');

        if ($term instanceof \WP_Term) {
            return (int) $term->term_id;
        }

        $created = wp_insert_term($name, 'product_cat', ['slug' => $slug]);

        if (is_wp_error($created) || ! isset($created['term_id'])) {
            return 0;
        }

        return (int) $created['term_id'];
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
