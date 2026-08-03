<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Interfaces\Checkout;

use AppleKlinika\CustomerAddressBook\Application\Command\CreateAddress;
use AppleKlinika\CustomerAddressBook\Application\Handler\AddressBookService;
use AppleKlinika\CustomerAddressBook\Application\Handler\CheckoutAddressSelection;
use AppleKlinika\CustomerAddressBook\Application\Port\AddressProjection;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\Address;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\AddressException;

/** Supported WooCommerce Checkout Blocks adapter; all address resolution remains server-side. */
final class CheckoutAddressController
{
    private const NAMESPACE = 'appleklinika/address-book';
    private const SESSION_KEY = 'appleklinika_address_book_checkout';
    private const ORDER_META_PREFIX = '_appleklinika_address_book_';

    public function __construct(
        private readonly AddressBookService $service,
        private readonly CheckoutAddressSelection $selection,
        private readonly AddressProjection $projection
    ) {
    }

    public function register(): void
    {
        add_action('woocommerce_blocks_loaded', [$this, 'registerStoreApi']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('woocommerce_store_api_cart_update_order_from_request', [$this, 'syncDraftMetadata'], 20, 2);
        add_action('woocommerce_store_api_checkout_update_customer_from_request', [$this, 'validateCheckoutSelection'], 5, 2);
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'finalizeOrder'], 20, 1);
        add_action('wp_logout', [$this, 'clearSession']);
    }

    public function registerStoreApi(): void
    {
        if (! function_exists('woocommerce_store_api_register_endpoint_data') || ! class_exists('Automattic\\WooCommerce\\StoreApi\\Schemas\\V1\\CartSchema')) {
            return;
        }

        woocommerce_store_api_register_endpoint_data([
            'endpoint' => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
            'namespace' => self::NAMESPACE,
            'schema_callback' => [$this, 'storeApiSchema'],
            'data_callback' => [$this, 'storeApiData'],
            'schema_type' => ARRAY_A,
        ]);
        woocommerce_store_api_register_update_callback([
            'namespace' => self::NAMESPACE,
            'callback' => [$this, 'updateSelection'],
        ]);
    }

    /** @return array<string, mixed> */
    public function storeApiSchema(): array
    {
        return [
            'description' => 'Apple Klinika mentett cím választás',
            'type' => 'object',
            'context' => ['view', 'edit'],
            'readonly' => true,
            'properties' => [
                'enabled' => ['type' => 'boolean', 'readonly' => true],
                'needs_shipping' => ['type' => 'boolean', 'readonly' => true],
                'billing' => ['type' => 'array', 'readonly' => true, 'items' => ['type' => 'object']],
                'shipping' => ['type' => 'array', 'readonly' => true, 'items' => ['type' => 'object']],
                'selection' => ['type' => 'object', 'readonly' => true],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function storeApiData(): array
    {
        $customerId = get_current_user_id();
        $needsShipping = $this->needsShipping();
        return $this->selection->options($customerId, $needsShipping, $this->sessionSelection());
    }

    /** @param array<string, mixed> $data */
    public function updateSelection(array $data): void
    {
        $customerId = get_current_user_id();
        if ($customerId <= 0) {
            throw new \WC_REST_Exception('appleklinika_address_book_authentication', 'Mentett cím csak bejelentkezve választható.', 401);
        }

        $current = $this->sessionSelection();
        $next = [];
        foreach ($this->purposesForCurrentCart() as $purpose) {
            $candidate = is_array($data[$purpose] ?? null) ? $data[$purpose] : ($current[$purpose] ?? ['mode' => 'one_off']);
            $next[$purpose] = $this->normalizeSelection($customerId, $purpose, $candidate);
            if ($next[$purpose]['mode'] === 'saved') {
                $this->applyAddressToCustomer($purpose, $this->selection->resolve(
                    $customerId,
                    $purpose,
                    $next[$purpose]['key'],
                    $next[$purpose]['version']
                ));
            }
        }
        $this->storeSessionSelection($next);
    }

    public function syncDraftMetadata(\WC_Order $draftOrder, \WP_REST_Request $request): void
    {
        $stored = $this->sessionSelection();
        foreach ($this->purposesForCurrentCart() as $purpose) {
            $selection = $stored[$purpose] ?? ['mode' => 'one_off'];
            $draftOrder->update_meta_data(self::ORDER_META_PREFIX . $purpose . '_mode', (string) ($selection['mode'] ?? 'one_off'));
            $draftOrder->update_meta_data(self::ORDER_META_PREFIX . $purpose . '_key', (string) ($selection['key'] ?? ''));
            $draftOrder->update_meta_data(self::ORDER_META_PREFIX . $purpose . '_version', (string) ((int) ($selection['version'] ?? 0)));
            $draftOrder->update_meta_data(self::ORDER_META_PREFIX . $purpose . '_save', ! empty($selection['save']) ? '1' : '');
            $draftOrder->update_meta_data(self::ORDER_META_PREFIX . $purpose . '_default', ! empty($selection['set_default']) ? '1' : '');
        }
        if (! $this->needsShipping()) {
            foreach (['mode', 'key', 'version', 'save', 'default'] as $suffix) {
                $draftOrder->delete_meta_data(self::ORDER_META_PREFIX . 'shipping_' . $suffix);
            }
        }
        $draftOrder->save();
    }

    public function validateCheckoutSelection(\WC_Customer $customer, \WP_REST_Request $request): void
    {
        $customerId = get_current_user_id();
        if ($customerId <= 0) {
            return;
        }
        $stored = $this->sessionSelection();
        foreach ($this->purposesForCurrentCart() as $purpose) {
            $item = $stored[$purpose] ?? ['mode' => 'one_off'];
            if (($item['mode'] ?? 'one_off') !== 'saved') {
                continue;
            }
            try {
                $this->selection->resolve($customerId, $purpose, (string) $item['key'], (int) $item['version']);
            } catch (\Throwable) {
                throw new \WC_REST_Exception(
                    'appleklinika_address_book_reselect',
                    'A kiválasztott mentett cím megváltozott vagy már nem elérhető. Kérjük, válaszd ki újra a címet.',
                    400
                );
            }
        }
    }

    public function finalizeOrder(\WC_Order $order): void
    {
        $customerId = get_current_user_id();
        if ($customerId <= 0 || ($order->get_user_id() > 0 && $order->get_user_id() !== $customerId)) {
            return;
        }
        $stored = $this->sessionSelection();

        foreach ($this->purposesForCurrentCart() as $purpose) {
            $item = $stored[$purpose] ?? ['mode' => 'one_off'];
            if (($item['mode'] ?? 'one_off') === 'saved') {
                $order->update_meta_data(self::ORDER_META_PREFIX . $purpose . '_key', (string) $item['key']);
                $order->update_meta_data(self::ORDER_META_PREFIX . $purpose . '_version', (string) ((int) $item['version']));
            }
            if (empty($item['save'])) {
                continue;
            }
            try {
                $this->saveOneOffAddress($customerId, $order, $purpose, $item);
            } catch (\Throwable $exception) {
                $order->add_order_note('A cím mentése a Címeim közé nem sikerült: ' . $exception->getMessage());
            }
        }
        $order->save();

        // WooCommerce persists checkout fields to user meta. Canonical defaults win after an order.
        foreach ($this->purposesForCurrentCart() as $purpose) {
            $default = $this->service->getDefault($customerId, $purpose);
            if ($default !== null) {
                $this->projection->project($customerId, $purpose, $default);
            }
        }
        $this->clearSession();
    }

    public function clearSession(): void
    {
        if (function_exists('WC') && WC()->session !== null) {
            WC()->session->set(self::SESSION_KEY, null);
        }
    }

    public function assets(): void
    {
        if (! function_exists('is_checkout') || ! is_checkout() || ! is_user_logged_in()) {
            return;
        }
        wp_enqueue_style(
            'appleklinika-customer-address-book-checkout',
            APPLEKLINIKA_ADDRESS_BOOK_URL . 'assets/css/checkout-address-book.css',
            [],
            APPLEKLINIKA_ADDRESS_BOOK_VERSION
        );
        wp_enqueue_script(
            'appleklinika-customer-address-book-checkout',
            APPLEKLINIKA_ADDRESS_BOOK_URL . 'assets/js/checkout-address-book.js',
            ['wc-blocks-checkout', 'wp-data'],
            APPLEKLINIKA_ADDRESS_BOOK_VERSION,
            true
        );
    }

    /** @param array<string, mixed> $candidate @return array<string, mixed> */
    private function normalizeSelection(int $customerId, string $purpose, array $candidate): array
    {
        $mode = ($candidate['mode'] ?? '') === 'saved' ? 'saved' : 'one_off';
        $save = ! empty($candidate['save']);
        $setDefault = $save && ! empty($candidate['set_default']);
        $label = sanitize_text_field((string) ($candidate['label'] ?? ''));
        if (! $save && ! empty($candidate['set_default'])) {
            throw new \WC_REST_Exception('appleklinika_address_book_default_requires_save', 'Alapértelmezetté csak mentett címet tehetsz.', 400);
        }
        if ($save && $label === '') {
            throw new \WC_REST_Exception('appleklinika_address_book_label', 'A mentett címhez adj meg egy címelnevezést.', 400);
        }
        if ($mode === 'one_off') {
            return ['mode' => 'one_off', 'save' => $save, 'set_default' => $setDefault, 'label' => $label];
        }
        $key = sanitize_text_field((string) ($candidate['key'] ?? ''));
        $version = absint($candidate['version'] ?? 0);
        $this->selection->resolve($customerId, $purpose, $key, $version);
        return ['mode' => 'saved', 'key' => $key, 'version' => $version, 'save' => $save, 'set_default' => $setDefault, 'label' => $label];
    }

    private function applyAddressToCustomer(string $purpose, Address $address): void
    {
        if (! function_exists('WC') || WC()->customer === null) {
            return;
        }
        $fields = $this->selection->checkoutFields($address);
        $customer = WC()->customer;
        foreach (['first_name','last_name','company','address_1','address_2','city','state','postcode','country'] as $field) {
            $setter = 'set_' . $purpose . '_' . $field;
            if (is_callable([$customer, $setter])) {
                $customer->{$setter}($fields[$field]);
            }
        }
        foreach (['house_number', 'staircase', 'floor', 'door'] as $field) {
            $customer->update_meta_data('ak_' . $purpose . '_' . $field, $fields['appleklinika/' . $field]);
        }
        if ($purpose === 'billing') {
            $customer->update_meta_data('appleklinika_company_purchase', $fields['appleklinika/company_purchase']);
            $customer->update_meta_data('appleklinika_company_name', $fields['appleklinika/company_name']);
            $customer->update_meta_data('appleklinika_tax_number', $fields['appleklinika/tax_number']);
            $customer->update_meta_data('ak_billing_tax_number', $fields['appleklinika/tax_number']);
        }
        $customer->save();
    }

    /** @param array<string, mixed> $item */
    private function saveOneOffAddress(int $customerId, \WC_Order $order, string $purpose, array $item): void
    {
        $address = $purpose === 'billing' ? $order->get_address('billing') : $order->get_address('shipping');
        if ($purpose === 'shipping' && $address === []) {
            return;
        }
        $prefix = '_wc_' . $purpose . '/appleklinika/';
        $data = [
            'label' => (string) $item['label'],
            'capabilities' => $purpose === 'billing' ? Address::BILLING : Address::SHIPPING,
            'first_name' => (string) ($address['first_name'] ?? ''), 'last_name' => (string) ($address['last_name'] ?? ''),
            'company_name' => (string) ($address['company'] ?? ''),
            'tax_number' => (string) $order->get_meta('appleklinika_tax_number', true),
            'country' => (string) ($address['country'] ?? ''), 'state' => (string) ($address['state'] ?? ''),
            'postcode' => (string) ($address['postcode'] ?? ''), 'city' => (string) ($address['city'] ?? ''),
            'address_1' => (string) ($address['address_1'] ?? ''), 'address_2' => (string) ($address['address_2'] ?? ''),
            'house_number' => (string) $order->get_meta($prefix . 'house_number', true),
            'staircase' => (string) $order->get_meta($prefix . 'staircase', true),
            'floor' => (string) $order->get_meta($prefix . 'floor', true),
            'door' => (string) $order->get_meta($prefix . 'door', true),
            'phone' => '', 'email' => '', 'status' => Address::STATUS_ACTIVE, 'source' => Address::SOURCE_CHECKOUT,
        ];
        $this->service->handleCreate(new CreateAddress($customerId, $data, ! empty($item['set_default']) && $purpose === 'billing', ! empty($item['set_default']) && $purpose === 'shipping'));
    }

    /** @return array<string, mixed> */
    private function sessionSelection(): array
    {
        if (! function_exists('WC') || WC()->session === null) {
            return [];
        }
        $stored = WC()->session->get(self::SESSION_KEY, []);
        return is_array($stored) ? $stored : [];
    }

    /** @param array<string, mixed> $selection */
    private function storeSessionSelection(array $selection): void
    {
        if (function_exists('WC') && WC()->session !== null) {
            WC()->session->set(self::SESSION_KEY, $selection);
        }
    }

    private function needsShipping(): bool
    {
        return function_exists('WC') && WC()->cart !== null && WC()->cart->needs_shipping();
    }

    /** @return array<int, string> */
    private function purposesForCurrentCart(): array
    {
        return $this->needsShipping() ? ['billing', 'shipping'] : ['billing'];
    }
}
