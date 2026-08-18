<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Interfaces\Checkout;

use AppleKlinika\CustomerAddressBook\Application\Command\CreateAddress;
use AppleKlinika\CustomerAddressBook\Application\Handler\AddressBookService;
use AppleKlinika\CustomerAddressBook\Application\Handler\CheckoutAddressSelection;
use AppleKlinika\CustomerAddressBook\Application\Port\AddressProjection;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\Address;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\AddressException;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\AddressNotFound;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\VersionConflict;

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
        add_action('woocommerce_store_api_checkout_update_order_from_request', [$this, 'syncCheckoutOrderIntent'], 20, 2);
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
            // ARRAY_A endpoint extensions are already wrapped by WooCommerce as
            // object properties. Returning another root schema wrapper here makes
            // its scalar metadata look like a property and breaks schema cleanup.
            'enabled' => [
                'description' => 'Whether saved address selection is available for the current customer.',
                'type' => 'boolean',
                'context' => ['view', 'edit'],
                'readonly' => true,
            ],
            'needs_shipping' => [
                'description' => 'Whether the current cart requires a shipping address.',
                'type' => 'boolean',
                'context' => ['view', 'edit'],
                'readonly' => true,
            ],
            'billing' => [
                'description' => 'Saved billing addresses available for checkout selection.',
                'type' => 'array',
                'context' => ['view', 'edit'],
                'readonly' => true,
                'items' => [
                    'type' => 'object',
                    'properties' => $this->storeApiAddressOptionSchema(),
                ],
            ],
            'shipping' => [
                'description' => 'Saved shipping addresses available for checkout selection.',
                'type' => 'array',
                'context' => ['view', 'edit'],
                'readonly' => true,
                'items' => [
                    'type' => 'object',
                    'properties' => $this->storeApiAddressOptionSchema(),
                ],
            ],
            'selection' => [
                'description' => 'Current saved-address or one-off checkout selection.',
                'type' => 'object',
                'context' => ['view', 'edit'],
                'readonly' => true,
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function storeApiAddressOptionSchema(): array
    {
        return [
            'key' => ['type' => 'string', 'readonly' => true],
            'version' => ['type' => 'integer', 'readonly' => true],
            'label' => ['type' => 'string', 'readonly' => true],
            'name' => ['type' => 'string', 'readonly' => true],
            'preview' => ['type' => 'string', 'readonly' => true],
            'is_default' => ['type' => 'boolean', 'readonly' => true],
            // The fields object contains only checkout-safe data and follows the
            // address form's WooCommerce/custom-field keys.
            'fields' => ['type' => 'object', 'readonly' => true],
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
        $isEnvelope = isset($data['selection']) || isset($data['intent']);
        $selectionData = is_array($data['selection'] ?? null) ? $data['selection'] : $data;
        $intentData = is_array($data['intent'] ?? null) ? $data['intent'] : [];
        $next = [];
        try {
            foreach ($this->purposesForCurrentCart() as $purpose) {
                $candidate = is_array($selectionData[$purpose] ?? null) ? $selectionData[$purpose] : ($current[$purpose] ?? ['mode' => 'one_off']);
                $next[$purpose] = $this->normalizeAddressSelection($customerId, $purpose, $candidate);
                $intent = $isEnvelope
                    ? (is_array($intentData[$purpose] ?? null) ? $intentData[$purpose] : [])
                    : $candidate;
                $next[$purpose] = array_merge($next[$purpose], $this->normalizeSaveIntent($intent));
                if ($next[$purpose]['mode'] === 'saved') {
                    $this->applyAddressToCustomer($purpose, $this->selection->resolve(
                        $customerId,
                        $purpose,
                        $next[$purpose]['key'],
                        $next[$purpose]['version']
                    ));
                } elseif ($isEnvelope && is_array($candidate['fields'] ?? null)) {
                    $this->applyOneOffFieldsToCustomer($purpose, $candidate['fields']);
                }
            }
        } catch (VersionConflict) {
            throw new \WC_REST_Exception(
                'appleklinika_address_book_stale_selection',
                'A kiválasztott mentett cím időközben megváltozott. Kérjük, válaszd ki újra a címet.',
                409
            );
        } catch (AddressNotFound) {
            throw new \WC_REST_Exception(
                'appleklinika_address_book_selection_not_found',
                'A kiválasztott mentett cím nem elérhető. Kérjük, válassz másik címet.',
                404
            );
        } catch (AddressException) {
            throw new \WC_REST_Exception(
                'appleklinika_address_book_invalid_selection',
                'A kiválasztott mentett cím nem használható. Kérjük, ellenőrizd a választást.',
                400
            );
        }
        $this->storeSessionSelection($next);
    }

    public function syncDraftMetadata(\WC_Order $draftOrder, \WP_REST_Request $request): void
    {
        $stored = $this->sessionSelection();
        foreach ($this->purposesForCurrentCart() as $purpose) {
            $selection = $stored[$purpose] ?? ['mode' => 'one_off'];
            $this->storeOrderIntent($draftOrder, $purpose, $selection);
        }
        if (! $this->needsShipping()) {
            $this->clearOrderIntent($draftOrder, 'shipping');
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

    public function syncCheckoutOrderIntent(\WC_Order $order, \WP_REST_Request $request): void
    {
        $customerId = $order->get_user_id();
        $currentCustomerId = get_current_user_id();
        if ($customerId <= 0 || ($currentCustomerId > 0 && $currentCustomerId !== $customerId)) {
            return;
        }

        $stored = $this->sessionSelection();
        foreach ($this->purposesForOrder($order) as $purpose) {
            if (isset($stored[$purpose]) && is_array($stored[$purpose])) {
                $this->storeOrderIntent($order, $purpose, $stored[$purpose]);
            }
        }
        $order->save();
    }

    public function finalizeOrder(\WC_Order $order): void
    {
        $customerId = $order->get_user_id();
        $currentCustomerId = get_current_user_id();
        if ($customerId <= 0 || ($currentCustomerId > 0 && $currentCustomerId !== $customerId)) {
            return;
        }
        $stored = $this->sessionSelection();

        foreach ($this->purposesForOrder($order) as $purpose) {
            if ($order->get_meta(self::ORDER_META_PREFIX . $purpose . '_consumed', true) === '1') {
                continue;
            }
            $item = $this->orderIntent($order, $purpose) ?? ($stored[$purpose] ?? ['mode' => 'one_off']);
            if (($item['mode'] ?? 'one_off') === 'saved') {
                $order->update_meta_data(self::ORDER_META_PREFIX . $purpose . '_key', (string) $item['key']);
                $order->update_meta_data(self::ORDER_META_PREFIX . $purpose . '_version', (string) ((int) $item['version']));
                $this->clearOrderSaveIntent($order, $purpose);
                continue;
            }
            if (empty($item['save'])) {
                $this->clearOrderIntent($order, $purpose);
                continue;
            }
            try {
                $this->saveOneOffAddress($customerId, $order, $purpose, $item);
                $order->update_meta_data(self::ORDER_META_PREFIX . $purpose . '_consumed', '1');
                $this->clearOrderIntent($order, $purpose, false);
            } catch (\Throwable $exception) {
                $order->add_order_note('A cím mentése a Címeim közé nem sikerült: ' . $exception->getMessage());
            }
        }
        $order->save();

        // WooCommerce persists checkout fields to user meta. Canonical defaults win after an order.
        foreach ($this->purposesForOrder($order) as $purpose) {
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
        $stylePath = APPLEKLINIKA_ADDRESS_BOOK_PATH . '/assets/css/checkout-address-book.css';
        $scriptPath = APPLEKLINIKA_ADDRESS_BOOK_PATH . '/assets/js/checkout-address-book.js';
        wp_enqueue_style(
            'appleklinika-customer-address-book-checkout',
            APPLEKLINIKA_ADDRESS_BOOK_URL . 'assets/css/checkout-address-book.css',
            [],
            md5_file($stylePath) ?: APPLEKLINIKA_ADDRESS_BOOK_VERSION
        );
        wp_enqueue_script(
            'appleklinika-customer-address-book-checkout',
            APPLEKLINIKA_ADDRESS_BOOK_URL . 'assets/js/checkout-address-book.js',
            ['wc-blocks-checkout', 'wc-blocks-data-store', 'wp-data'],
            md5_file($scriptPath) ?: APPLEKLINIKA_ADDRESS_BOOK_VERSION,
            true
        );
    }

    /** @param array<string, mixed> $candidate @return array<string, mixed> */
    private function normalizeAddressSelection(int $customerId, string $purpose, array $candidate): array
    {
        $mode = ($candidate['mode'] ?? '') === 'saved' ? 'saved' : 'one_off';
        if ($mode === 'one_off') {
            return ['mode' => 'one_off'];
        }
        $key = sanitize_text_field((string) ($candidate['key'] ?? ''));
        $version = absint($candidate['version'] ?? 0);
        if (! preg_match('/^[A-Za-z0-9_-]{20,64}$/', $key) || $version < 1) {
            throw new AddressException('A kiválasztott cím nem használható.');
        }
        $this->selection->resolve($customerId, $purpose, $key, $version);
        return ['mode' => 'saved', 'key' => $key, 'version' => $version];
    }

    /** @param array<string, mixed> $candidate @return array<string, mixed> */
    private function normalizeSaveIntent(array $candidate): array
    {
        $save = ! empty($candidate['save']);
        $setDefault = $save && ! empty($candidate['set_default']);
        $label = sanitize_text_field((string) ($candidate['label'] ?? ''));
        if (! $save && ! empty($candidate['set_default'])) {
            throw new \WC_REST_Exception('appleklinika_address_book_default_requires_save', 'Alapértelmezetté csak mentett címet tehetsz.', 400);
        }
        if ($save && $label === '') {
            throw new \WC_REST_Exception('appleklinika_address_book_label', 'A mentett címhez adj meg egy címelnevezést.', 400);
        }
        return ['save' => $save, 'set_default' => $setDefault, 'label' => $label];
    }

    private function applyAddressToCustomer(string $purpose, Address $address): void
    {
        if (! function_exists('WC') || WC()->customer === null) {
            return;
        }
        $fields = $this->selection->checkoutFields($address, $purpose);
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

    /** @param array<string, mixed> $fields */
    private function applyOneOffFieldsToCustomer(string $purpose, array $fields): void
    {
        if (! function_exists('WC') || WC()->customer === null) {
            return;
        }

        $customer = WC()->customer;
        foreach (['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country'] as $field) {
            $setter = 'set_' . $purpose . '_' . $field;
            if (is_callable([$customer, $setter])) {
                $customer->{$setter}(sanitize_text_field((string) ($fields[$field] ?? '')));
            }
        }
        foreach (['house_number', 'staircase', 'floor', 'door'] as $field) {
            $customer->update_meta_data(
                'ak_' . $purpose . '_' . $field,
                sanitize_text_field((string) ($fields['appleklinika/' . $field] ?? ''))
            );
        }
        if ($purpose === 'billing') {
            $companyPurchase = ! empty($fields['appleklinika/company_purchase']) ? '1' : '';
            $companyName = sanitize_text_field((string) ($fields['appleklinika/company_name'] ?? ''));
            $taxNumber = sanitize_text_field((string) ($fields['appleklinika/tax_number'] ?? ''));
            $customer->update_meta_data('appleklinika_company_purchase', $companyPurchase);
            $customer->update_meta_data('appleklinika_company_name', $companyName);
            $customer->update_meta_data('appleklinika_tax_number', $taxNumber);
            $customer->update_meta_data('ak_billing_tax_number', $taxNumber);
        }
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

    /** @param array<string, mixed> $selection */
    private function storeOrderIntent(\WC_Order $order, string $purpose, array $selection): void
    {
        $order->update_meta_data(self::ORDER_META_PREFIX . $purpose . '_mode', (string) ($selection['mode'] ?? 'one_off'));
        $order->update_meta_data(self::ORDER_META_PREFIX . $purpose . '_key', (string) ($selection['key'] ?? ''));
        $order->update_meta_data(self::ORDER_META_PREFIX . $purpose . '_version', (string) ((int) ($selection['version'] ?? 0)));
        $order->update_meta_data(self::ORDER_META_PREFIX . $purpose . '_save', ! empty($selection['save']) ? '1' : '');
        $order->update_meta_data(self::ORDER_META_PREFIX . $purpose . '_default', ! empty($selection['set_default']) ? '1' : '');
        $order->update_meta_data(self::ORDER_META_PREFIX . $purpose . '_label', sanitize_text_field((string) ($selection['label'] ?? '')));
    }

    /** @return array<string, mixed>|null */
    private function orderIntent(\WC_Order $order, string $purpose): ?array
    {
        $mode = (string) $order->get_meta(self::ORDER_META_PREFIX . $purpose . '_mode', true);
        if (! in_array($mode, ['one_off', 'saved'], true)) {
            return null;
        }

        return [
            'mode' => $mode,
            'key' => (string) $order->get_meta(self::ORDER_META_PREFIX . $purpose . '_key', true),
            'version' => (int) $order->get_meta(self::ORDER_META_PREFIX . $purpose . '_version', true),
            'save' => $order->get_meta(self::ORDER_META_PREFIX . $purpose . '_save', true) === '1',
            'set_default' => $order->get_meta(self::ORDER_META_PREFIX . $purpose . '_default', true) === '1',
            'label' => sanitize_text_field((string) $order->get_meta(self::ORDER_META_PREFIX . $purpose . '_label', true)),
        ];
    }

    private function clearOrderIntent(\WC_Order $order, string $purpose, bool $clearConsumed = true): void
    {
        foreach (['mode', 'key', 'version', 'save', 'default', 'label'] as $suffix) {
            $order->delete_meta_data(self::ORDER_META_PREFIX . $purpose . '_' . $suffix);
        }
        if ($clearConsumed) {
            $order->delete_meta_data(self::ORDER_META_PREFIX . $purpose . '_consumed');
        }
    }

    private function clearOrderSaveIntent(\WC_Order $order, string $purpose): void
    {
        foreach (['mode', 'save', 'default', 'label', 'consumed'] as $suffix) {
            $order->delete_meta_data(self::ORDER_META_PREFIX . $purpose . '_' . $suffix);
        }
    }

    /** @return array<int, string> */
    private function purposesForOrder(\WC_Order $order): array
    {
        return $order->needs_shipping() ? ['billing', 'shipping'] : ['billing'];
    }
}
