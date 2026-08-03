<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Application\Port;

interface AllowedCountries
{
    public function contains(string $countryCode): bool;
    /** @return array<string, string> */
    public function all(): array;
}
