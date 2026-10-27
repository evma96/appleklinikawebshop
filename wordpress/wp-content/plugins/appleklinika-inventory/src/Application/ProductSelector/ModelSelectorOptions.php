<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Application\ProductSelector;

use Appleklinika\Inventory\Domain\ProductCondition\StorageCapacityCatalog;

/** Read-only projection of configured model options; never guesses a model matrix. */
final class ModelSelectorOptions
{
    /**
     * @param array<string,mixed> $model Exact model (existing aliases resolved by the adapter).
     * @param list<array{label:string,value:string}> $specRows Existing exact-model specification rows.
     * @param array<string,string> $colorLabels Existing catalogue labels, used only to resolve explicitly named colors.
     * @return array{colors:array<string,string>,storage:array<string,string>}
     */
    public function resolve(array $model, array $specRows, array $colorLabels = []): array
    {
        $colors = array_filter($model['colors'] ?? [], static fn ($label, $key): bool => is_string($key) && $key !== '' && is_string($label) && $label !== '', ARRAY_FILTER_USE_BOTH);
        if ($colors === []) {
            foreach ($specRows as $row) {
                if ($row['label'] !== 'Színek') continue;
                foreach (explode(',', $row['value']) as $label) {
                    $key = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($label))), '_');
                    if (isset($colorLabels[$key])) $colors[$key] = $colorLabels[$key];
                }
            }
        }
        $capacities = StorageCapacityCatalog::options();
        $storage = array_intersect_key($capacities, array_flip($model['storage_capacity_keys'] ?? []));
        if ($storage === []) {
            foreach ($specRows as $row) {
                // Never infer disk capacities from RAM, display sizes or other specs.
                if (!in_array($row['label'], ['Kapacitások', 'Tárhely'], true)) continue;
                preg_match_all('/(?<![\d.,])\b([1-9][0-9]*)\s*(GB|TB)\b/ui', $row['value'], $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $key = $match[1] . '_' . strtolower($match[2]);
                    if (isset($capacities[$key])) $storage[$key] = $capacities[$key];
                }
            }
        }
        return ['colors' => $colors, 'storage' => array_intersect_key($capacities, $storage)];
    }
}
