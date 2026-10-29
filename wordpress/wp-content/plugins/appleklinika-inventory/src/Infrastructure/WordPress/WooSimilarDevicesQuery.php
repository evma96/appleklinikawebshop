<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Infrastructure\WordPress;

/** Read-only product-page recommendations; never expands the selector's model pool. */
final class WooSimilarDevicesQuery
{
    private const LIMIT = 6;

    public function __construct(
        private readonly WooProductConditionRepository $conditions,
        private readonly DeviceCatalogRepository $catalog
    ) {
    }

    /** @param list<\WC_Product> $sameModel @return list<\WC_Product> */
    public function get(\WC_Product $current, array $sameModel): array
    {
        $models = [];
        foreach ($this->catalog->all() as $model) {
            $models[$model['key']] = $this->family($model['type'] ?? '');
        }
        $family = $this->productFamily($current, $models);
        if (! in_array($family, ['iphone', 'ipad', 'mac', 'watch'], true)) {
            return [];
        }

        $items = [];
        $append = function (array $candidates) use (&$items, $current, $family, $models): void {
            foreach ($candidates as $candidate) {
                if (count($items) >= self::LIMIT) break;
                if (! $candidate instanceof \WC_Product || $candidate->get_id() === $current->get_id()
                    || ! DeviceAvailability::active($candidate)
                    || ! in_array($candidate->get_catalog_visibility(), ['visible', 'catalog'], true)
                    || $this->productFamily($candidate, $models) !== $family) continue;
                $items[$candidate->get_id()] = $candidate;
            }
        };
        $append($sameModel);
        if (count($items) >= self::LIMIT) return array_values($items);

        $familyKeys = match ($family) {
            'mac' => ['mac', 'macbook'],
            'watch' => ['watch', 'apple_watch'],
            default => [$family],
        };
        $familyModels = array_keys(array_filter($models, static fn (string $type): bool => $type === $family));
        // Canonical model metadata also covers newly uploaded devices without category tags.
        // Bounded pages avoid loading the whole inventory when only a few cards are needed.
        $page = 1;
        do {
            $batch = wc_get_products([
                'status' => 'publish', 'limit' => 24, 'page' => $page++,
                'stock_status' => 'instock', 'visibility' => 'visible',
                'orderby' => ['date' => 'DESC', 'ID' => 'DESC'],
                'exclude' => [$current->get_id()],
                'appleklinika_recommendation_scope' => ['types' => $familyKeys, 'models' => $familyModels],
            ]);
            $append($batch);
        } while (count($items) < self::LIMIT && count($batch) === 24);

        return array_values($items);
    }

    /** @param array<string,string> $models */
    private function productFamily(\WC_Product $product, array $models): string
    {
        $type = $models[$this->conditions->get($product->get_id(), 'device_model')] ?? '';
        return $type !== '' ? $type : $this->family($this->conditions->get($product->get_id(), 'device_type'));
    }

    private function family(string $type): string
    {
        return match ($type) {
            'macbook' => 'mac',
            'apple_watch' => 'watch',
            default => $type,
        };
    }
}
