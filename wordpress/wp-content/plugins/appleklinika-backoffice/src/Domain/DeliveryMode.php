<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Domain;

final class DeliveryMode
{
    public const GLS = 'gls';
    public const PICKUP = 'pickup';
    public const UNKNOWN = 'unknown';

    /** @var list<string> */
    private const GLS_METHOD_IDS = [
        'gls_shipping_method',
        'gls_shipping_method_zones',
        'gls_shipping_method_parcel_shop',
        'gls_shipping_method_parcel_locker',
        'gls_shipping_method_parcel_shop_zones',
        'gls_shipping_method_parcel_locker_zones',
    ];

    /** @param list<string> $methodIds */
    public static function fromShippingMethodIds(array $methodIds): string
    {
        $methodIds = array_values(array_unique(array_filter(array_map('strval', $methodIds))));
        if (count($methodIds) !== 1) {
            return self::UNKNOWN;
        }

        if ($methodIds[0] === 'local_pickup') {
            return self::PICKUP;
        }

        return in_array($methodIds[0], self::GLS_METHOD_IDS, true) ? self::GLS : self::UNKNOWN;
    }

    public static function label(string $mode): string
    {
        return match ($mode) {
            self::GLS => 'GLS házhozszállítás',
            self::PICKUP => 'Személyes átvétel az üzletben',
            default => 'Ismeretlen átvételi mód – ellenőrzés szükséges',
        };
    }

    public static function isSupported(string $mode): bool
    {
        return in_array($mode, [self::GLS, self::PICKUP], true);
    }
}
