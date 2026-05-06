<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Domain\DeviceCatalog;

final class DeviceType
{
    public const IPHONE = 'iphone';
    public const IPAD = 'ipad';
    public const MAC = 'mac';
    public const WATCH = 'watch';
    public const AIRPODS = 'airpods';
    public const ACCESSORY = 'accessory';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::IPHONE => 'iPhone',
            self::IPAD => 'iPad',
            self::MAC => 'Mac',
            self::WATCH => 'Apple Watch',
            self::AIRPODS => 'AirPods',
            self::ACCESSORY => 'Accessory',
        ];
    }

    public static function isAllowed(string $type): bool
    {
        return array_key_exists($type, self::options());
    }
}
