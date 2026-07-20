<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

use AppleKlinika\Buyback\Infrastructure\Inventory\WordPressDeviceCatalogReader;

final class WordPressLocalDemoProductReader
{
    /** @return list<array{model_key:string,model_label:string,storage_gb:int,grade:string,price:int}> */
    public function publishedIphones(): array
    {
        if (! function_exists('wc_get_products')) {
            return [];
        }

        $labels = [];
        foreach ((new WordPressDeviceCatalogReader())->iPhoneModels() as $item) {
            $labels[$item->modelKey] = $item->label;
        }

        $records = [];
        $products = wc_get_products([
            'status' => 'publish',
            'limit' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'return' => 'objects',
        ]);
        foreach ($products as $product) {
            if (! $product instanceof \WC_Product) {
                continue;
            }
            $id = $product->get_id();
            if ((string) get_post_meta($id, '_appleklinika_device_type', true) !== 'iphone') {
                continue;
            }

            $modelKey = sanitize_key((string) get_post_meta($id, '_appleklinika_device_model', true));
            $storageGb = $this->storageGb((string) get_post_meta($id, '_appleklinika_storage_capacity', true));
            $price = $this->integerHuf((string) $product->get_price('edit'));
            if (! isset($labels[$modelKey]) || $storageGb === null || $price === null || $price < 1) {
                continue;
            }

            $records[] = [
                'model_key' => $modelKey,
                'model_label' => $labels[$modelKey],
                'storage_gb' => $storageGb,
                'grade' => (string) get_post_meta($id, '_appleklinika_overall_grade', true),
                'price' => $price,
            ];
        }

        return $records;
    }

    /** @return array<string,array{label:string,image_url:string,product_url:string,storages:list<int>,colors:array<int,array<string,string>}>} */
    public function frontendModels(): array
    {
        if (! function_exists('wc_get_products')) {
            return [];
        }

        $catalogReader = new WordPressDeviceCatalogReader();
        $labels = [];
        foreach ($catalogReader->iPhoneModels() as $item) {
            $labels[$item->modelKey] = $item->label;
        }
        $catalogColors = array_map(static fn (array $item): array => $item['colors'], $catalogReader->iPhoneCatalog());

        $models = [];
        $products = wc_get_products([
            'status' => 'publish',
            'limit' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'return' => 'objects',
        ]);
        foreach ($products as $product) {
            if (! $product instanceof \WC_Product) {
                continue;
            }
            $id = $product->get_id();
            if ((string) get_post_meta($id, '_appleklinika_device_type', true) !== 'iphone') {
                continue;
            }

            $modelKey = sanitize_key((string) get_post_meta($id, '_appleklinika_device_model', true));
            $storageGb = $this->storageGb((string) get_post_meta($id, '_appleklinika_storage_capacity', true));
            if (! isset($labels[$modelKey]) || $storageGb === null) {
                continue;
            }

            if (! isset($models[$modelKey])) {
                $imageUrl = '';
                $imageId = $product->get_image_id();
                if ($imageId > 0) {
                    $imageUrl = (string) wp_get_attachment_image_url($imageId, 'woocommerce_single');
                }
                $models[$modelKey] = [
                    'label' => $labels[$modelKey],
                    'image_url' => $imageUrl,
                    'product_url' => $product->get_permalink(),
                    'storages' => [],
                    'colors' => [],
                ];
            } elseif ($models[$modelKey]['image_url'] === '' && $product->get_image_id() > 0) {
                $models[$modelKey]['image_url'] = (string) wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_single');
            }

            $models[$modelKey]['storages'][$storageGb] = $storageGb;
            $colorKey = sanitize_key((string) get_post_meta($id, '_appleklinika_color', true));
            if ($colorKey !== '' && ($catalogColors[$modelKey] ?? []) === []) {
                $models[$modelKey]['colors'][$storageGb][$colorKey] = $this->colorLabel($modelKey, $colorKey);
            }
        }

        foreach ($models as $modelKey => &$model) {
            $model['storages'] = array_values($model['storages']);
            sort($model['storages'], SORT_NUMERIC);
            foreach ($model['storages'] as $storage) {
                $model['colors'][$storage] = self::resolveColors(
                    $catalogColors[$modelKey] ?? [],
                    $model['colors'][$storage] ?? []
                );
            }
        }
        unset($model);

        uasort($models, static function (array $left, array $right): int {
            preg_match('/(\d+)/', $left['label'], $leftVersion);
            preg_match('/(\d+)/', $right['label'], $rightVersion);
            return ((int) ($rightVersion[1] ?? 0)) <=> ((int) ($leftVersion[1] ?? 0));
        });

        return $models;
    }

    /** @param array<string,string> $catalogColors @param array<string,string> $productColors @return array<string,string> */
    public static function resolveColors(array $catalogColors, array $productColors): array
    {
        return $catalogColors !== [] ? $catalogColors : $productColors;
    }

    private function storageGb(string $value): ?int
    {
        return match ($value) {
            '64_gb' => 64,
            '128_gb' => 128,
            '256_gb' => 256,
            '512_gb' => 512,
            '1_tb' => 1024,
            default => null,
        };
    }

    private function integerHuf(string $value): ?int
    {
        return preg_match('/^[1-9]\d*$/', $value) === 1 ? (int) $value : null;
    }

    private function colorLabel(string $modelKey, string $colorKey): string
    {
        $catalog = get_option('appleklinika_device_catalog', []);
        foreach (is_array($catalog) ? $catalog : [] as $device) {
            if (is_array($device) && ($device['key'] ?? '') === $modelKey) {
                return (string) (($device['colors'][$colorKey] ?? '') ?: $colorKey);
            }
        }
        return $colorKey;
    }
}
