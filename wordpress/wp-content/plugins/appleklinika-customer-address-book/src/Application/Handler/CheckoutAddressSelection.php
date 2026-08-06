<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Application\Handler;

use AppleKlinika\CustomerAddressBook\Application\Port\AllowedCountries;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\Address;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\AddressException;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\VersionConflict;

/** Resolves checkout-safe address selections for the authenticated customer. */
final class CheckoutAddressSelection
{
    public function __construct(
        private readonly AddressBookService $addresses,
        private readonly AllowedCountries $countries
    ) {
    }

    /** @return array<string, mixed> */
    public function options(int $customerId, bool $needsShipping, array $selection = []): array
    {
        if ($customerId <= 0) {
            return ['enabled' => false, 'billing' => [], 'shipping' => [], 'selection' => []];
        }

        $billingDefault = $this->addresses->getDefault($customerId, 'billing');
        $shippingDefault = $needsShipping ? $this->addresses->getDefault($customerId, 'shipping') : null;

        return [
            'enabled' => true,
            'billing' => $this->optionsForPurpose($customerId, 'billing', $billingDefault),
            'shipping' => $needsShipping ? $this->optionsForPurpose($customerId, 'shipping', $shippingDefault) : [],
            'selection' => $selection,
            'needs_shipping' => $needsShipping,
        ];
    }

    public function resolve(int $customerId, string $purpose, string $key, int $expectedVersion): Address
    {
        if ($customerId <= 0 || ! in_array($purpose, ['billing', 'shipping'], true) || $key === '' || $expectedVersion < 1) {
            throw new AddressException('A kiválasztott cím nem használható.');
        }

        $address = $this->addresses->get($customerId, $key);
        if (! $address->canBeDefault($purpose) || ! $this->countries->contains((string) $address->toArray()['country'])) {
            throw new AddressException('A kiválasztott cím nem használható.');
        }
        if ($address->version() !== $expectedVersion) {
            throw new VersionConflict('A kiválasztott cím időközben megváltozott. Ellenőrizd újra a címet.');
        }

        return $address;
    }

    /** @return array<string, string> */
    public function checkoutFields(Address $address): array
    {
        $data = $address->toArray();

        return [
            'first_name' => (string) $data['first_name'],
            'last_name' => (string) $data['last_name'],
            'company' => (string) $data['company_name'],
            'address_1' => (string) $data['address_1'],
            'address_2' => (string) $data['address_2'],
            'city' => (string) $data['city'],
            'state' => (string) $data['state'],
            'postcode' => (string) $data['postcode'],
            'country' => (string) $data['country'],
            'appleklinika/house_number' => (string) $data['house_number'],
            'appleklinika/staircase' => (string) $data['staircase'],
            'appleklinika/floor' => (string) $data['floor'],
            'appleklinika/door' => (string) $data['door'],
            'appleklinika/company_purchase' => (string) $data['company_name'] !== '' ? '1' : '',
            'appleklinika/company_name' => (string) $data['company_name'],
            'appleklinika/tax_number' => (string) $data['tax_number'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function optionsForPurpose(int $customerId, string $purpose, ?Address $default): array
    {
        $result = [];
        foreach ($this->addresses->list($customerId) as $address) {
            if (! $address->canBeDefault($purpose)) {
                continue;
            }
            $data = $address->toArray();
            $result[] = [
                'key' => $address->key(),
                'version' => $address->version(),
                'label' => (string) $data['label'],
                'name' => trim((string) ($data['company_name'] !== '' ? $data['company_name'] : $data['first_name'] . ' ' . $data['last_name'])),
                'preview' => trim((string) $data['postcode'] . ' ' . (string) $data['city'] . ', ' . (string) $data['address_1'] . ' ' . (string) $data['house_number']),
                'is_default' => $default !== null && $default->key() === $address->key(),
                'fields' => $this->checkoutFields($address),
            ];
        }
        return $result;
    }
}
