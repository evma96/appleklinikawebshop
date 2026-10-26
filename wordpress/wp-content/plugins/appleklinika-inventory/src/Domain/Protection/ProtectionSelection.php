<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Domain\Protection;

final class ProtectionSelection
{
    public const GROUPS = ['glass' => 'Üvegfólia', 'case' => 'Tok', 'camera' => 'Kamera védő', 'adapter' => '20 W gyorstöltő adapter'];
    public const DEVICE_TYPES = ['iphone' => 'iPhone', 'ipad' => 'iPad', 'apple_watch' => 'Apple Watch', 'macbook' => 'MacBook'];

    /** One product per slot; independent slots; no implied selections or client prices. */
    public static function parse(array $input): array
    {
        $selected = [];
        foreach ($input as $group => $value) {
            if (!isset(self::GROUPS[$group]) || !is_scalar($value) || !preg_match('/^\d+$/D', (string) $value)) {
                throw new \InvalidArgumentException('Érvénytelen kiegészítőválasztás.');
            }
            $id = (int) $value;
            if ($id > 0) $selected[$group] = $id;
        }
        if (count(array_unique($selected)) !== count($selected)) {
            throw new \InvalidArgumentException('Egy kiegészítő csak egyszer választható.');
        }
        return $selected;
    }

    public static function eligible(string $type, string $model, array $types, array $models): bool
    {
        return isset(self::DEVICE_TYPES[$type]) && in_array($type, $types, true)
            && ($models === [] || ($model !== '' && in_array($model, $models, true)));
    }
}
