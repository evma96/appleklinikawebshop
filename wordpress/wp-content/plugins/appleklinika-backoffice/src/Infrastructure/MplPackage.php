<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

/** Packed product dimensions; a conservative, single stacked parcel. Missing data fails closed. */
final class MplPackage
{
    public static function fromContents(array $contents): array
    {
        $kg = 0.0; $length = 0.0; $width = 0.0; $height = 0.0; $value = 0.0;
        foreach ($contents as $item) {
            $p = $item['data'] ?? null; $quantity = (int)($item['quantity'] ?? 0);
            if (!$p instanceof \WC_Product || $quantity < 1 || $p->is_virtual()) { continue; }
            $weight = (float)wc_get_weight($p->get_weight(), 'kg');
            $dimensions = array_map(static fn($v) => (float)wc_get_dimension($v, 'cm'), [$p->get_length(), $p->get_width(), $p->get_height()]);
            if ($weight <= 0 || min($dimensions) <= 0) { return ['kg'=>0.0, 'dimensions'=>[], 'value'=>0.0]; }
            rsort($dimensions, SORT_NUMERIC);
            $kg += $weight * $quantity;
            $length = max($length, $dimensions[0]); $width = max($width, $dimensions[1]); $height += $dimensions[2] * $quantity;
            $value += (float)wc_get_price_including_tax($p, ['qty'=>$quantity]);
        }
        return ['kg'=>$kg, 'dimensions'=>[$length,$width,$height], 'value'=>$value];
    }
}
