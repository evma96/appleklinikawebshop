<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce;

/** Read-only translation of the immutable Woo order snapshot to provider fields. */
final class OrderProviderMapping
{
    public function register(): void
    {
        add_filter('wc_szamlazz_xml_adoszam', [$this, 'invoiceTaxNumber'], 10, 2);
        add_filter('wc_szamlazz_xml', [$this, 'invoiceAddress'], 10, 2);
        add_filter('gls_shipping_for_woocommerce_api_get_delivery_address', [$this, 'deliveryAddress'], 10, 2);
    }

    public function invoiceTaxNumber(string $taxNumber, \WC_Order $order): string
    {
        $stored = trim((string) $order->get_meta('appleklinika_tax_number'));
        return $stored !== '' ? $stored : $taxNumber;
    }

    public function invoiceAddress(\SimpleXMLElement $xml, \WC_Order $order): \SimpleXMLElement
    {
        $address = $this->fullAddress($order, 'billing');
        if ($address !== null && isset($xml->vevo)) {
            $xml->vevo->cim = htmlspecialchars($address, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        }
        return $xml;
    }

    /** @param array<string, mixed> $address @return array<string, mixed> */
    public function deliveryAddress(array $address, \WC_Order $order): array
    {
        $fullAddress = $this->fullAddress($order, 'shipping');
        if ($fullAddress !== null) {
            $address['Street'] = $fullAddress;
        }
        return $address;
    }

    private function fullAddress(\WC_Order $order, string $purpose): ?string
    {
        $prefix = '_wc_' . $purpose . '/appleklinika/';
        $details = [];
        foreach (['house_number' => '', 'staircase' => ' lépcsőház', 'floor' => ' emelet', 'door' => ' ajtó'] as $key => $suffix) {
            $value = trim((string) $order->get_meta($prefix . $key));
            if ($value !== '') {
                $details[$key] = $value . $suffix;
            }
        }
        if ($details === []) {
            return null; // Preserve the provider's existing legacy-address handling.
        }
        $address = $order->get_address($purpose);
        $parts = [$address['address_1'], $details['house_number'] ?? '', $address['address_2']];
        unset($details['house_number']);
        return implode(' ', array_filter(array_merge($parts, array_values($details)), static fn (string $part): bool => $part !== ''));
    }
}
