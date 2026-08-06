<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Application\Port;

use AppleKlinika\CustomerAddressBook\Domain\AddressBook\Address;

interface AddressProjection
{
    public function project(int $customerId, string $purpose, Address $address): void;
    public function clear(int $customerId, string $purpose): void;
}
