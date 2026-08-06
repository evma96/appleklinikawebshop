<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress;

final class Schema
{
    public const ADDRESSES = 'ak_customer_addresses';
    public const DEFAULTS = 'ak_customer_address_defaults';
    public const OPTION_SCHEMA_VERSION = 'appleklinika_customer_address_book_schema_version';
    public const OPTION_PLUGIN_VERSION = 'appleklinika_customer_address_book_plugin_version';

    /** @return array{addresses: string, defaults: string} */
    public static function tables(\wpdb $database): array
    {
        return [
            'addresses' => $database->prefix . self::ADDRESSES,
            'defaults' => $database->prefix . self::DEFAULTS,
        ];
    }
}
