<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

/** Packed data remains unknown when absent; manual dispatch retains known limits and value. */
final class MplPackage
{
    public static function fromContents(array $contents): array
    {
        $kg = 0.0; $length = 0.0; $width = 0.0; $height = 0.0; $value = 0.0;
        $missingWeight = false; $missingDimensions = false;
        foreach ($contents as $item) {
            $p = $item['data'] ?? null; $quantity = (int)($item['quantity'] ?? 0);
            if (!$p instanceof \WC_Product || $quantity < 1 || $p->is_virtual()) { continue; }
            $weight = (float)wc_get_weight($p->get_weight(), 'kg');
            $dimensions = array_map(static fn($v) => (float)wc_get_dimension($v, 'cm'), [$p->get_length(), $p->get_width(), $p->get_height()]);
            $missingWeight = $missingWeight || $weight <= 0;
            $missingDimensions = $missingDimensions || min($dimensions) <= 0;
            rsort($dimensions, SORT_NUMERIC);
            $kg += $weight * $quantity;
            $length = max($length, $dimensions[0]); $width = max($width, $dimensions[1]); $height += $dimensions[2] * $quantity;
            $value += (float)wc_get_price_including_tax($p, ['qty'=>$quantity]);
        }
        return ['kg'=>$missingWeight ? 0.0 : $kg, 'dimensions'=>$missingDimensions ? [] : [$length,$width,$height],
            'known_kg'=>$kg, 'known_dimensions'=>[$length,$width,$height], 'value'=>$value];
    }

    public static function manualDispatch(): bool { return get_option('appleklinika_mpl_manual_fulfilment','no') === 'yes'; }

    public static function eligible(string $service, array $parcel): bool
    {
        if (self::manualDispatch()) {
            return \Appleklinika\BackOffice\Domain\MplParcelRules::eligibleForManualDispatch($service,
                $parcel['known_kg'] ?? $parcel['kg'], $parcel['known_dimensions'] ?? $parcel['dimensions'], $parcel['value']);
        }
        return \Appleklinika\BackOffice\Domain\MplParcelRules::eligible($service,$parcel['kg'],$parcel['dimensions'],$parcel['value']);
    }
}
