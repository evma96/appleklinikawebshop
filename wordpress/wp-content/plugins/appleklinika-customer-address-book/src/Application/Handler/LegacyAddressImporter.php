<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Application\Handler;

use AppleKlinika\CustomerAddressBook\Application\Port\AddressRepository;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\Address;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\AddressException;

final class LegacyAddressImporter
{
    public const MIGRATION_VERSION = '1';
    public const USER_META_VERSION = 'ak_customer_address_book_migration_version';

    public function __construct(
        private readonly AddressBookService $service,
        private readonly AddressRepository $addresses
    ) {
    }

    /** @return array{imported:int,merged:int,needs_review:int,skipped:int,already_migrated:int,invalid:int} */
    public function import(int $customerId, bool $dryRun = false): array
    {
        $summary = ['imported' => 0, 'merged' => 0, 'needs_review' => 0, 'skipped' => 0, 'already_migrated' => 0, 'invalid' => 0];
        if ($customerId <= 0 || get_user_by('id', $customerId) === false) {
            $summary['invalid']++;
            return $summary;
        }
        if ((string) get_user_meta($customerId, self::USER_META_VERSION, true) === self::MIGRATION_VERSION) {
            $summary['already_migrated']++;
            return $summary;
        }

        $billing = $this->read($customerId, 'billing');
        $shipping = $this->read($customerId, 'shipping');
        $candidates = [];
        if ($this->hasMeaningfulData($billing)) {
            $billing['label'] = 'Számlázási cím';
            $billing['capabilities'] = Address::BILLING;
            $candidates[] = $billing;
        }
        if ($this->hasMeaningfulData($shipping)) {
            $shipping['label'] = 'Szállítási cím';
            $shipping['capabilities'] = Address::SHIPPING;
            $candidates[] = $shipping;
        }
        if (count($candidates) === 2 && $this->comparable($billing) === $this->comparable($shipping)) {
            $merged = array_merge($billing, [
                'label' => 'Mentett cím',
                'capabilities' => Address::BOTH,
            ]);
            $candidates = [$merged];
            $summary['merged']++;
        }

        foreach ($candidates as $candidate) {
            $candidate['source'] = Address::SOURCE_LEGACY;
            $candidate['status'] = $this->isActiveCandidate($customerId, $candidate)
                ? Address::STATUS_ACTIVE
                : Address::STATUS_NEEDS_REVIEW;
            $candidate['legacy_fingerprint'] = $this->fingerprint($candidate);
            if ($this->matchesCanonicalAddress($customerId, $candidate)) {
                $summary['skipped']++;
                continue;
            }
            if ($this->addresses->findByLegacyFingerprint($customerId, $candidate['legacy_fingerprint']) !== null) {
                $summary['skipped']++;
                continue;
            }
            if ($candidate['status'] === Address::STATUS_NEEDS_REVIEW) {
                $summary['needs_review']++;
            }
            if (! $dryRun) {
                try {
                    $this->service->create($customerId, $candidate, false, false, false);
                } catch (AddressException) {
                    $summary['invalid']++;
                    continue;
                }
            }
            $summary['imported']++;
        }

        if (! $dryRun && $summary['invalid'] === 0) {
            update_user_meta($customerId, self::USER_META_VERSION, self::MIGRATION_VERSION);
        }

        return $summary;
    }

    /** @return array<string, mixed> */
    private function read(int $customerId, string $purpose): array
    {
        $meta = static function (string $key, string $fallback = '') use ($customerId): string {
            $value = (string) get_user_meta($customerId, $key, true);
            if ($value !== '' || $fallback === '') {
                return $value;
            }

            return (string) get_user_meta($customerId, $fallback, true);
        };
        $prefix = $purpose . '_';
        $ak = 'ak_' . $prefix;
        $company = $purpose === 'billing'
            ? $meta('appleklinika_company_name', 'billing_company')
            : $meta($prefix . 'company');

        return $this->normalize([
            'first_name' => $meta($prefix . 'first_name'),
            'last_name' => $meta($prefix . 'last_name'),
            'company_name' => $company,
            'tax_number' => $purpose === 'billing' ? $meta('appleklinika_tax_number', 'ak_billing_tax_number') : '',
            'country' => $meta($prefix . 'country') ?: 'HU',
            'state' => $meta($prefix . 'state'),
            'postcode' => $meta($prefix . 'postcode'),
            'city' => $meta($prefix . 'city'),
            'address_1' => $meta($prefix . 'address_1'),
            'address_2' => $meta($prefix . 'address_2'),
            'house_number' => $meta($ak . 'house_number'),
            'staircase' => $meta($ak . 'staircase'),
            'floor' => $meta($ak . 'floor'),
            'door' => $meta($ak . 'door'),
        ]);
    }

    /** @param array<string, mixed> $candidate */
    private function isActiveCandidate(int $customerId, array $candidate): bool
    {
        try {
            Address::create($customerId, str_repeat('x', 24), array_merge($candidate, ['status' => Address::STATUS_ACTIVE]));
            return true;
        } catch (AddressException) {
            return false;
        }
    }

    /** @param array<string, string> $data @return array<string, string> */
    private function normalize(array $data): array
    {
        foreach ($data as $key => $value) {
            $value = trim((string) preg_replace('/\s+/u', ' ', (string) $value));
            if (class_exists('Normalizer')) {
                $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);
                $value = is_string($normalized) ? $normalized : $value;
            }
            $data[$key] = $key === 'country' ? strtoupper($value) : $value;
        }
        return $data;
    }

    /** @param array<string, mixed> $data */
    private function hasMeaningfulData(array $data): bool
    {
        foreach (['first_name', 'last_name', 'company_name', 'postcode', 'city', 'address_1'] as $field) {
            if (($data[$field] ?? '') !== '') {
                return true;
            }
        }
        return false;
    }

    /** @param array<string, mixed> $data */
    private function comparable(array $data): string
    {
        unset($data['tax_number'], $data['label'], $data['capabilities']);
        ksort($data);
        return wp_json_encode($data) ?: '';
    }

    /** @param array<string, mixed> $candidate */
    private function matchesCanonicalAddress(int $customerId, array $candidate): bool
    {
        $identity = $this->identity($candidate);
        foreach ($this->addresses->listForCustomer($customerId) as $address) {
            $canonical = $address->toArray();
            if ($this->identity($canonical) === $identity) {
                return true;
            }
            if (($candidate['capabilities'] ?? 0) === Address::BILLING
                && ($candidate['company_name'] ?? '') !== ''
                && $this->companyBillingProjectionIdentity($canonical) === $this->companyBillingProjectionIdentity($candidate)) {
                return true;
            }
            if (($candidate['capabilities'] ?? 0) === Address::SHIPPING
                && ($candidate['company_name'] ?? '') === ''
                && ($candidate['tax_number'] ?? '') === ''
                && $this->shippingProjectionIdentity($canonical) === $this->shippingProjectionIdentity($candidate)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $data */
    private function identity(array $data): string
    {
        $normalized = $this->normalize([
            'first_name' => (string) ($data['first_name'] ?? ''),
            'last_name' => (string) ($data['last_name'] ?? ''),
            'company_name' => (string) ($data['company_name'] ?? ''),
            'tax_number' => (string) ($data['tax_number'] ?? ''),
            'country' => (string) ($data['country'] ?? ''),
            'state' => (string) ($data['state'] ?? ''),
            'postcode' => (string) ($data['postcode'] ?? ''),
            'city' => (string) ($data['city'] ?? ''),
            'address_1' => (string) ($data['address_1'] ?? ''),
            'address_2' => (string) ($data['address_2'] ?? ''),
            'house_number' => (string) ($data['house_number'] ?? ''),
            'staircase' => (string) ($data['staircase'] ?? ''),
            'floor' => (string) ($data['floor'] ?? ''),
            'door' => (string) ($data['door'] ?? ''),
        ]);
        ksort($normalized);
        return wp_json_encode($normalized) ?: '';
    }

    /** @param array<string, mixed> $data */
    private function companyBillingProjectionIdentity(array $data): string
    {
        $normalized = $this->normalize([
            'company_name' => (string) ($data['company_name'] ?? ''),
            'tax_number' => (string) ($data['tax_number'] ?? ''),
            'country' => (string) ($data['country'] ?? ''),
            'state' => (string) ($data['state'] ?? ''),
            'postcode' => (string) ($data['postcode'] ?? ''),
            'city' => (string) ($data['city'] ?? ''),
            'address_1' => (string) ($data['address_1'] ?? ''),
            'address_2' => (string) ($data['address_2'] ?? ''),
            'house_number' => (string) ($data['house_number'] ?? ''),
            'staircase' => (string) ($data['staircase'] ?? ''),
            'floor' => (string) ($data['floor'] ?? ''),
            'door' => (string) ($data['door'] ?? ''),
        ]);
        ksort($normalized);
        return wp_json_encode($normalized) ?: '';
    }

    /** @param array<string, mixed> $data */
    private function shippingProjectionIdentity(array $data): string
    {
        $normalized = $this->normalize([
            'first_name' => (string) ($data['first_name'] ?? ''),
            'last_name' => (string) ($data['last_name'] ?? ''),
            'country' => (string) ($data['country'] ?? ''),
            'state' => (string) ($data['state'] ?? ''),
            'postcode' => (string) ($data['postcode'] ?? ''),
            'city' => (string) ($data['city'] ?? ''),
            'address_1' => (string) ($data['address_1'] ?? ''),
            'address_2' => (string) ($data['address_2'] ?? ''),
            'house_number' => (string) ($data['house_number'] ?? ''),
            'staircase' => (string) ($data['staircase'] ?? ''),
            'floor' => (string) ($data['floor'] ?? ''),
            'door' => (string) ($data['door'] ?? ''),
        ]);
        ksort($normalized);
        return wp_json_encode($normalized) ?: '';
    }

    /** @param array<string, mixed> $data */
    private function fingerprint(array $data): string
    {
        unset($data['label'], $data['capabilities'], $data['source'], $data['status'], $data['legacy_fingerprint']);
        ksort($data);
        return hash_hmac('sha256', wp_json_encode($data) ?: '', wp_salt('auth'));
    }
}
