<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Domain;

/** Standard domestic MPL parcel limits; dimensions are packed centimetres. */
final class MplParcelRules
{
    public static function price(float $kg, array $rates): ?float
    {
        if ($kg <= 0 || $kg > 40) { return null; }
        $key = $kg <= 10 ? 'home_10' : ($kg <= 20 ? 'home_20' : 'home_40');
        return isset($rates[$key]) && is_numeric($rates[$key]) && (float)$rates[$key] >= 0 ? (float)$rates[$key] : null;
    }

    public static function eligible(string $service, float $kg, array $dimensions = [], float $value = 0): bool
    {
        $limits = ['home' => 40, 'postapont_posta' => 30, 'postapont_postapont' => 20, 'postapont_automata' => 20];
        if (!isset($limits[$service]) || $kg <= 0 || $kg > $limits[$service]) { return false; }
        if ($service === 'postapont_automata' && $value > 500000) { return false; }
        if (count($dimensions) !== 3 || min($dimensions) <= 0) { return false; }
        sort($dimensions, SORT_NUMERIC);
        $max = $service === 'postapont_automata' ? [31,35,50] : [60,60,120];
        return $dimensions[0] <= $max[0] && $dimensions[1] <= $max[1] && $dimensions[2] <= $max[2];
    }

    /** Owner-approved manual dispatch: unknown measurements require staff review, not invented values. */
    public static function eligibleForManualDispatch(string $service, float $knownKg, array $knownDimensions = [], float $value = 0): bool
    {
        $limits = ['home'=>40, 'postapont_posta'=>30, 'postapont_postapont'=>20, 'postapont_automata'=>20];
        if (!isset($limits[$service]) || $knownKg < 0 || $knownKg > $limits[$service]) { return false; }
        if ($service === 'postapont_automata' && $value > 500000) { return false; }
        if (!$knownDimensions) { return true; }
        if (count($knownDimensions) !== 3 || min($knownDimensions) < 0) { return false; }
        sort($knownDimensions, SORT_NUMERIC);
        $max = $service === 'postapont_automata' ? [31,35,50] : [60,60,120];
        return $knownDimensions[0] <= $max[0] && $knownDimensions[1] <= $max[1] && $knownDimensions[2] <= $max[2];
    }
}
