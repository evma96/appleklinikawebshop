<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Application\Handler;

use AppleKlinika\CustomerAddressBook\Application\Command\CreateAddress;
use AppleKlinika\CustomerAddressBook\Application\Command\DeleteAddress;
use AppleKlinika\CustomerAddressBook\Application\Command\SetDefaultAddress;
use AppleKlinika\CustomerAddressBook\Application\Command\UpdateAddress;
use AppleKlinika\CustomerAddressBook\Application\Port\AddressProjection;
use AppleKlinika\CustomerAddressBook\Application\Port\AddressRepository;
use AppleKlinika\CustomerAddressBook\Application\Port\AllowedCountries;
use AppleKlinika\CustomerAddressBook\Application\Port\TransactionManager;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\Address;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\AddressException;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\VersionConflict;
use AppleKlinika\CustomerAddressBook\Application\Query\GetCustomerAddress;
use AppleKlinika\CustomerAddressBook\Application\Query\ListCustomerAddresses;

final class AddressBookService
{
    public const MAX_ACTIVE_ADDRESSES = 20;

    public function __construct(
        private readonly AddressRepository $addresses,
        private readonly TransactionManager $transactions,
        private readonly AddressProjection $projection,
        private readonly AllowedCountries $countries
    ) {
    }

    public function handleCreate(CreateAddress $command): Address
    {
        return $this->create($command->customerId, $command->data, $command->defaultBilling, $command->defaultShipping);
    }

    public function handleUpdate(UpdateAddress $command): Address
    {
        return $this->update(
            $command->customerId,
            $command->addressKey,
            $command->expectedVersion,
            $command->changes,
            $command->defaultBilling,
            $command->defaultShipping
        );
    }

    public function handleDelete(DeleteAddress $command): void
    {
        $this->delete($command->customerId, $command->addressKey, $command->expectedVersion, $command->successorKeys);
    }

    public function handleSetDefault(SetDefaultAddress $command): void
    {
        $this->setDefault($command->customerId, $command->addressKey, $command->purpose, $command->expectedVersion);
    }

    /** @return array<int, Address> */
    public function handleList(ListCustomerAddresses $query): array
    {
        return $this->list($query->customerId);
    }

    public function handleGet(GetCustomerAddress $query): Address
    {
        return $this->get($query->customerId, $query->addressKey);
    }

    /** @param array<string, mixed> $data */
    public function create(int $customerId, array $data, bool $defaultBilling = false, bool $defaultShipping = false, bool $projectDefaults = true): Address
    {
        $this->assertCountryAllowed((string) ($data['country'] ?? ''));
        $address = Address::create($customerId, self::generateKey(), $data);

        return $this->transactions->transactional(function () use ($address, $defaultBilling, $defaultShipping, $projectDefaults): Address {
            $this->addresses->lockCustomer($address->customerId());
            if ($address->status() === Address::STATUS_ACTIVE
                && $this->addresses->countActiveForCustomer($address->customerId()) >= self::MAX_ACTIVE_ADDRESSES) {
                throw new AddressException('Legfeljebb 20 aktív cím menthető.');
            }
            $this->assertNotDuplicate($address->customerId(), $address);
            $created = $this->addresses->create($address);
            if (($defaultBilling && ! $created->canBeDefault('billing'))
                || ($defaultShipping && ! $created->canBeDefault('shipping'))) {
                throw new AddressException('A cím a kiválasztott felhasználással nem lehet alapértelmezett.');
            }
            foreach (['billing', 'shipping'] as $purpose) {
                if (! $created->canBeDefault($purpose)) {
                    continue;
                }
                $requested = $purpose === 'billing' ? $defaultBilling : $defaultShipping;
                if ($requested || $this->addresses->getDefault($created->customerId(), $purpose, true) === null) {
                    $this->addresses->setDefault($created->customerId(), $purpose, $created);
                    if ($projectDefaults) {
                        $this->projection->project($created->customerId(), $purpose, $created);
                    }
                }
            }

            return $created;
        });
    }

    /** @param array<string, mixed> $changes */
    public function update(
        int $customerId,
        string $key,
        int $expectedVersion,
        array $changes,
        bool $defaultBilling = false,
        bool $defaultShipping = false
    ): Address
    {
        $this->assertCountryAllowed((string) ($changes['country'] ?? ''));

        return $this->transactions->transactional(function () use ($customerId, $key, $expectedVersion, $changes, $defaultBilling, $defaultShipping): Address {
            $this->addresses->lockCustomer($customerId);
            $current = $this->requireOwned($customerId, $key, true);
            $candidate = $current->updated($changes);
            if ($current->status() !== Address::STATUS_ACTIVE && $candidate->status() === Address::STATUS_ACTIVE
                && $this->addresses->countActiveForCustomer($customerId) >= self::MAX_ACTIVE_ADDRESSES) {
                throw new AddressException('Legfeljebb 20 aktív cím menthető.');
            }
            $this->assertNotDuplicate($customerId, $candidate, $current->id());
            $updated = $this->addresses->update($candidate, $expectedVersion);

            foreach (['billing', 'shipping'] as $purpose) {
                $default = $this->addresses->getDefault($customerId, $purpose, true);
                if ($default === null || $default->id() !== $current->id()) {
                    if ($default === null && $updated->canBeDefault($purpose)) {
                        $this->addresses->setDefault($customerId, $purpose, $updated);
                        $this->projection->project($customerId, $purpose, $updated);
                    }
                    continue;
                }
                if (! $updated->canBeDefault($purpose)) {
                    throw new AddressException('Alapértelmezett cím nem tehető hiányossá vagy más célúvá. Előbb válassz másik alapértelmezettet.');
                }
                $this->projection->project($customerId, $purpose, $updated);
            }

            foreach (['billing' => $defaultBilling, 'shipping' => $defaultShipping] as $purpose => $requested) {
                if (! $requested) {
                    continue;
                }
                if (! $updated->canBeDefault($purpose)) {
                    throw new AddressException('A cím a kiválasztott felhasználással nem lehet alapértelmezett.');
                }
                $this->addresses->setDefault($customerId, $purpose, $updated);
                $this->projection->project($customerId, $purpose, $updated);
            }

            return $updated;
        });
    }

    /** @param array<string, string> $successorKeys */
    public function delete(int $customerId, string $key, int $expectedVersion, array $successorKeys = []): void
    {
        $this->transactions->transactional(function () use ($customerId, $key, $expectedVersion, $successorKeys): void {
            $this->addresses->lockCustomer($customerId);
            $address = $this->requireOwned($customerId, $key, true);
            if ($address->version() !== $expectedVersion) {
                throw new \AppleKlinika\CustomerAddressBook\Domain\AddressBook\VersionConflict('A cím időközben megváltozott.');
            }

            $all = $this->addresses->listForCustomer($customerId, true);
            foreach (['billing', 'shipping'] as $purpose) {
                $default = $this->addresses->getDefault($customerId, $purpose, true);
                if ($default === null || $default->id() !== $address->id()) {
                    continue;
                }

                $alternatives = array_values(array_filter(
                    $all,
                    static fn (Address $candidate): bool => $candidate->id() !== $address->id() && $candidate->canBeDefault($purpose)
                ));
                if ($alternatives === []) {
                    $this->addresses->clearDefault($customerId, $purpose);
                    $this->projection->clear($customerId, $purpose);
                    continue;
                }

                $successorKey = $successorKeys[$purpose] ?? '';
                $successor = null;
                foreach ($alternatives as $candidate) {
                    if ($candidate->key() === $successorKey) {
                        $successor = $candidate;
                        break;
                    }
                }
                if ($successor === null) {
                    throw new AddressException('Az alapértelmezett cím törléséhez válassz utódcímet.');
                }
                $this->addresses->setDefault($customerId, $purpose, $successor);
                $this->projection->project($customerId, $purpose, $successor);
            }

            $this->addresses->delete($address);
        });
    }

    public function setDefault(int $customerId, string $key, string $purpose, ?int $expectedVersion = null): void
    {
        if (! in_array($purpose, ['billing', 'shipping'], true)) {
            throw new AddressException('Ismeretlen címcél.');
        }

        $this->transactions->transactional(function () use ($customerId, $key, $purpose, $expectedVersion): void {
            $this->addresses->lockCustomer($customerId);
            $address = $this->requireOwned($customerId, $key, true);
            if ($expectedVersion !== null && $address->version() !== $expectedVersion) {
                throw new VersionConflict('A cím időközben megváltozott.');
            }
            if (! $address->canBeDefault($purpose)) {
                throw new AddressException('Ez a cím nem választható alapértelmezettként ehhez a célhoz.');
            }
            $this->addresses->getDefault($customerId, $purpose, true);
            $this->addresses->setDefault($customerId, $purpose, $address);
            $this->projection->project($customerId, $purpose, $address);
        });
    }

    /** Removes every canonical address and default pointer for one existing customer. */
    public function eraseForCustomer(int $customerId): int
    {
        if ($customerId <= 0) {
            return 0;
        }

        return $this->transactions->transactional(function () use ($customerId): int {
            $this->addresses->lockCustomer($customerId);
            $addresses = $this->addresses->listForCustomer($customerId, true);
            foreach (['billing', 'shipping'] as $purpose) {
                $this->addresses->clearDefault($customerId, $purpose);
            }
            foreach ($addresses as $address) {
                $this->addresses->delete($address);
            }

            return count($addresses);
        });
    }

    /** @return array<int, Address> */
    public function list(int $customerId): array { return $this->addresses->listForCustomer($customerId); }
    public function get(int $customerId, string $key): Address { return $this->requireOwned($customerId, $key); }
    public function getDefault(int $customerId, string $purpose): ?Address { return $this->addresses->getDefault($customerId, $purpose); }

    private function requireOwned(int $customerId, string $key, bool $forUpdate = false): Address
    {
        $address = $this->addresses->getByKeyForCustomer($key, $customerId, $forUpdate);
        if ($address === null) {
            throw new AddressException('A cím nem található.');
        }
        return $address;
    }

    private function assertCountryAllowed(string $country): void
    {
        if (! $this->countries->contains(strtoupper(trim($country)))) {
            throw new AddressException('A kiválasztott ország nem engedélyezett.');
        }
    }

    private static function generateKey(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }

    private function assertNotDuplicate(int $customerId, Address $candidate, int $ignoredId = 0): void
    {
        $signature = $this->contentSignature($candidate);
        foreach ($this->addresses->listForCustomer($customerId, true) as $existing) {
            if ($existing->id() !== $ignoredId && hash_equals($signature, $this->contentSignature($existing))) {
                throw new AddressException('Ez a cím már szerepel a címjegyzékben.');
            }
        }
    }

    private function contentSignature(Address $address): string
    {
        $data = $address->toArray();
        foreach (['id','address_key','customer_id','label','version','source','legacy_fingerprint','created_at','updated_at','last_used_at'] as $key) {
            unset($data[$key]);
        }
        foreach ($data as $key => $value) {
            $data[$key] = trim((string) ($value ?? ''));
        }
        ksort($data);
        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }
}
