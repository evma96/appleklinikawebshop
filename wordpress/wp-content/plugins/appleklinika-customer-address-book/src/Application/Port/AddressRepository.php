<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Application\Port;

use AppleKlinika\CustomerAddressBook\Domain\AddressBook\Address;

interface AddressRepository
{
    public function lockCustomer(int $customerId): void;
    public function create(Address $address): Address;
    public function getByKeyForCustomer(string $key, int $customerId, bool $forUpdate = false): ?Address;
    /** @return array<int, Address> */
    public function listForCustomer(int $customerId, bool $forUpdate = false): array;
    public function update(Address $address, int $expectedVersion): Address;
    public function delete(Address $address): void;
    public function countActiveForCustomer(int $customerId): int;
    public function findByLegacyFingerprint(int $customerId, string $fingerprint): ?Address;
    public function getDefault(int $customerId, string $purpose, bool $forUpdate = false): ?Address;
    public function setDefault(int $customerId, string $purpose, Address $address): void;
    public function clearDefault(int $customerId, string $purpose): void;
}
