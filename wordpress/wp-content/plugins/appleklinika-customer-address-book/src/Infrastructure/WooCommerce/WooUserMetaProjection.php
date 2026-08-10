<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce;

use AppleKlinika\CustomerAddressBook\Application\Port\AddressProjection;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\Address;

final class WooUserMetaProjection implements AddressProjection
{
    public function project(int $customerId, string $purpose, Address $address): void
    {
        if ($address->customerId() !== $customerId || ! $address->canBeDefault($purpose)) {
            throw new \RuntimeException('A címvetület tulajdonosa vagy célja érvénytelen.');
        }

        foreach ($this->mapping($purpose, $address) as $key => $value) {
            if (update_user_meta($customerId, $key, $value) === false && (string) get_user_meta($customerId, $key, true) !== $value) {
                throw new \RuntimeException('A WooCommerce címvetület nem menthető.');
            }
        }
    }

    public function clear(int $customerId, string $purpose): void
    {
        foreach (array_keys($this->mapping($purpose, Address::create($customerId, str_repeat('a', 24), [
            'label' => 'Projection clear',
            'capabilities' => $purpose === 'billing' ? Address::BILLING : Address::SHIPPING,
            'status' => Address::STATUS_NEEDS_REVIEW,
        ]))) as $key) {
            if (! delete_user_meta($customerId, $key) && metadata_exists('user', $customerId, $key)) {
                throw new \RuntimeException('A WooCommerce címvetület nem törölhető.');
            }
        }
    }

    /** @return array<string, string> */
    public function mapping(string $purpose, Address $address): array
    {
        $data = $address->toArray();
        $prefix = $purpose . '_';
        $isCompanyBilling = $purpose === 'billing' && $address->isCompanyBilling();
        $mapping = [
            $prefix . 'first_name' => $isCompanyBilling ? '' : (string) $data['first_name'],
            $prefix . 'last_name' => $isCompanyBilling ? '' : (string) $data['last_name'],
            $prefix . 'company' => $isCompanyBilling ? (string) $data['company_name'] : '',
            $prefix . 'country' => (string) $data['country'],
            $prefix . 'state' => (string) $data['state'],
            $prefix . 'postcode' => (string) $data['postcode'],
            $prefix . 'city' => (string) $data['city'],
            $prefix . 'address_1' => (string) $data['address_1'],
            $prefix . 'address_2' => (string) $data['address_2'],
            'ak_' . $prefix . 'house_number' => (string) $data['house_number'],
            'ak_' . $prefix . 'staircase' => (string) $data['staircase'],
            'ak_' . $prefix . 'floor' => (string) $data['floor'],
            'ak_' . $prefix . 'door' => (string) $data['door'],
        ];

        if ($purpose === 'billing') {
            $mapping['ak_billing_is_company'] = $isCompanyBilling ? '1' : '';
            $mapping['ak_billing_tax_number'] = $isCompanyBilling ? (string) $data['tax_number'] : '';
            $mapping['appleklinika_company_purchase'] = $isCompanyBilling ? '1' : '';
            $mapping['appleklinika_company_name'] = $isCompanyBilling ? (string) $data['company_name'] : '';
            $mapping['appleklinika_tax_number'] = $isCompanyBilling ? (string) $data['tax_number'] : '';
        }

        return $mapping;
    }
}
