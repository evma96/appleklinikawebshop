<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Infrastructure\WordPress;

/** Read-only public configuration; deliberately excludes serial/IMEI and private metadata. */
final class WooSelectorConfiguration
{
    private const FIELDS = [
        'sim_config' => 'SIM', 'connectivity' => 'Kapcsolat', 'screen_size' => 'Kijelzőméret',
        'processor_chip' => 'Chip', 'ram_size' => 'RAM', 'case_size' => 'Tokméret',
        'case_material' => 'Tok anyaga', 'keyboard_layout' => 'Billentyűzet',
    ];
    private const LABELS = [
        'wifi' => 'Wi-Fi', 'wifi_cellular' => 'Wi-Fi + Cellular', 'gps' => 'GPS', 'gps_cellular' => 'GPS + Cellular',
        'dual_esim' => 'Dual eSIM', 'physical_esim' => 'Fizikai SIM + eSIM', 'dual_physical' => 'Két fizikai SIM',
        'aluminium' => 'Alumínium', 'stainless_steel' => 'Rozsdamentes acél', 'titanium' => 'Titán',
    ];

    public function __construct(private readonly WooProductConditionRepository $repository) {}

    /** Batch native taxonomy attributes so a model with many devices does not cause N+1 reads. */
    public static function prime(array $products): void
    {
        $ids = [];
        foreach ($products as $product) {
            foreach ($product->get_attributes() as $attribute) {
                if ($attribute instanceof \WC_Product_Attribute && $attribute->get_visible() && $attribute->is_taxonomy()) {
                    $ids[] = $product->get_id();
                    break;
                }
            }
        }
        if ($ids !== []) update_object_term_cache(array_values(array_unique($ids)), 'product');
    }

    /** @return array<string, array{label: string, value: string, display: string}> */
    public function forProduct(\WC_Product $product): array
    {
        $result = [];
        foreach (self::FIELDS as $key => $label) {
            $value = $this->repository->get($product->get_id(), $key);
            if ($value === '') continue;
            $display = self::LABELS[$value] ?? str_replace(['_inch', '_mm', '_gb', '_'], ['″', ' mm', ' GB', ' '], $value);
            if ($key === 'processor_chip') $display = strtoupper($display);
            $result[$key] = ['label' => $label, 'value' => $value, 'display' => $display];
        }
        // Only visible native technical fields supplement the canonical public configuration.
        foreach ($product->get_attributes() as $attribute) {
            if (!$attribute instanceof \WC_Product_Attribute || !$attribute->get_visible()) continue;
            $name = wc_attribute_label($attribute->get_name());
            if (!preg_match('/^(RAM|Memória|Chip|Processzor|Kijelzőméret|Tokméret|Kapcsolat|Hálózat|Connectivity|Billentyűzet|Keyboard|GPU|CPU)$/ui', $name)) continue;
            if ($attribute->is_taxonomy()) {
                $terms = get_the_terms($product->get_id(), $attribute->get_name());
                if (is_wp_error($terms)) continue;
                $values = array_map(static fn ($term): string => $term->name, $terms ?: []);
            } else {
                $values = array_map('strval', $attribute->get_options());
            }
            sort($values, SORT_STRING);
            $value = implode(', ', $values);
            if ($value !== '') $result['attribute:' . $attribute->get_name()] = ['label' => $name, 'value' => $value, 'display' => $value];
        }
        ksort($result);
        return $result;
    }
}
