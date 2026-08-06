<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce;

use AppleKlinika\CustomerAddressBook\Application\Port\AllowedCountries;

final class WooAllowedCountries implements AllowedCountries
{
    public function contains(string $countryCode): bool
    {
        return array_key_exists($countryCode, $this->all());
    }

    public function all(): array
    {
        if (function_exists('WC') && WC()->countries) {
            $countries = WC()->countries->get_allowed_countries();
            if (is_array($countries) && $countries !== []) {
                return array_map('strval', $countries);
            }
        }
        return ['HU' => 'Magyarország'];
    }
}
