<?php

declare(strict_types=1);

require_once __DIR__ . '/TestSupport.php';

use AppleKlinika\CustomerAddressBook\Application\Handler\AddressBookService;
use AppleKlinika\CustomerAddressBook\Application\Handler\CheckoutAddressSelection;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\Address;
use AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\WordPressAddressRepository;
use AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\WordPressTransactionManager;
use AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce\WooAllowedCountries;
use AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce\WooUserMetaProjection;
use AppleKlinika\CustomerAddressBook\Interfaces\Checkout\CheckoutAddressController;

$test = new AddressBookTestSupport();
global $wpdb;
$owner = $test->createUser('checkout-owner');
$foreign = $test->createUser('checkout-foreign');
$service = new AddressBookService(new WordPressAddressRepository($wpdb), new WordPressTransactionManager($wpdb), new WooUserMetaProjection(), new WooAllowedCountries());
$selector = new CheckoutAddressSelection($service, new WooAllowedCountries());
$draftOrder = null;

try {
    $billing = $service->create($owner, $test->addressData([
        'label' => 'Számla',
        'capabilities' => Address::BILLING,
        'company_name' => 'Apple Klinika Teszt Kft.',
        'tax_number' => '12345678-1-23',
    ]), true, false);
    $personalBilling = $service->create($owner, $test->addressData([
        'label' => 'Személyes számla',
        'capabilities' => Address::BILLING,
    ]), false, false);
    $shipping = $service->create($owner, $test->addressData(['label' => 'Szállítás', 'capabilities' => Address::SHIPPING, 'city' => 'Szeged']), false, true);
    $review = $service->create($owner, $test->addressData(['label' => 'Ellenőrzés', 'status' => Address::STATUS_NEEDS_REVIEW]), false, false);
    $foreignAddress = $service->create($foreign, $test->addressData(['label' => 'Más ügyfél']), true, true);

    $options = $selector->options($owner, true);
    $test->assert($options['enabled'] === true, 'authenticated customer receives address options');
    $test->assert(count($options['billing']) === 2, 'billing includes both valid owner billing addresses and excludes needs-review address');
    $test->assert(count($options['shipping']) === 1, 'shipping excludes billing-only and needs-review address');
    $billingKeys = array_column($options['billing'], 'key');
    $test->assert(in_array($billing->key(), $billingKeys, true) && in_array($personalBilling->key(), $billingKeys, true), 'only valid owner billing keys returned');
    $defaultBilling = array_values(array_filter($options['billing'], static fn (array $option): bool => $option['key'] === $billing->key()));
    $test->assert(count($defaultBilling) === 1 && $defaultBilling[0]['is_default'] === true, 'default billing marked');
    $test->assert($options['shipping'][0]['is_default'] === true, 'default shipping marked');
    $test->assert(! array_key_exists('id', $options['billing'][0]), 'numeric address id not exposed');
    $test->assert(! array_key_exists('customer_id', $options['billing'][0]), 'customer id not exposed');
    $test->assert(! array_key_exists('created_at', $options['billing'][0]) && ! array_key_exists('source', $options['billing'][0]), 'non-checkout audit data not exposed');
    $checkoutScript = file_get_contents(dirname(__DIR__) . '/assets/js/checkout-address-book.js');
    $test->assert(is_string($checkoutScript) && str_contains($checkoutScript, "purpose + '-appleklinika-' + name"), 'checkout custom address fields use their Blocks ids');
    $test->assert(str_contains((string) $checkoutScript, "order-appleklinika-company_purchase") && str_contains($checkoutScript, "order-appleklinika-company_name") && str_contains((string) $checkoutScript, "order-appleklinika-tax_number"), 'checkout company controls use their registered Blocks ids');
    $test->assert(str_contains((string) $checkoutScript, 'setCheckoutControlChecked(companyPurchaseInput, option.fields') && str_contains((string) $checkoutScript, 'control.click()') && str_contains($checkoutScript, 'HTMLInputElement.prototype') && str_contains($checkoutScript, 'HTMLSelectElement.prototype') && str_contains($checkoutScript, 'setCheckoutControlValue'), 'checkout address fields update through the controlled Blocks input path');
    $test->assert(str_contains((string) $checkoutScript, 'setCustomFields(section, matchingOption());'), 'checkout applies custom address details to the initially selected default');
    $test->assert(str_contains((string) $checkoutScript, "select('wc/store/cart')") && str_contains($checkoutScript, 'var blocksCheckout = window.wc') && ! str_contains($checkoutScript, 'window.wc.blocksCheckout.extensionCartUpdate'), 'checkout uses the declared Blocks store directly and captures the checkout API while its script identity is available');
    $test->assert(str_contains((string) $checkoutScript, "select.id = 'ak-checkout-address-selector-' + purpose") && str_contains($checkoutScript, 'caption.htmlFor = select.id'), 'saved-address selectors have an explicit programmatic label relationship');
    $test->assert(str_contains((string) $checkoutScript, 'Válassz mentett számlázási címet') && str_contains($checkoutScript, 'Válassz mentett szállítási címet') && ! str_contains($checkoutScript, "document.createElement('h3')"), 'saved-address selectors use descriptive labels instead of duplicate visible section headings');
    $test->assert(str_contains((string) $checkoutScript, "section.classList.toggle('is-one-off', isOneOff)") && str_contains($checkoutScript, "section.classList.toggle('has-saved-address', !isOneOff)") && str_contains($checkoutScript, 'save.checked = false;') && str_contains($checkoutScript, 'defaultControl.disabled = true;'), 'checkout keeps the saved-address and one-off address presentation states explicit and clears one-off save intent before a saved address is submitted');
    $checkoutCss = file_get_contents(dirname(__DIR__) . '/assets/css/checkout-address-book.css');
    $test->assert(is_string($checkoutCss) && str_contains($checkoutCss, '[data-ak-address-save-details][hidden]') && str_contains($checkoutCss, 'display: none !important;') && str_contains($checkoutCss, '.has-saved-address .ak-checkout-address-selector__save'), 'checkout keeps collapsed saved-address details hidden and reserves address-save controls for one-off addresses');
    $test->assert(is_string($checkoutCss) && str_contains($checkoutCss, '.ak-checkout-address-selector') && str_contains($checkoutCss, 'background: transparent;') && str_contains($checkoutCss, 'border: 0;'), 'saved-address selection remains an integrated checkout control rather than a nested card');
    $checkoutController = file_get_contents(dirname(__DIR__) . '/src/Interfaces/Checkout/CheckoutAddressController.php');
    $test->assert(is_string($checkoutController) && str_contains($checkoutController, "'wc-blocks-data-store'"), 'checkout script declares the Woo Blocks data-store dependency');
    $test->assert($selector->options(0, true)['enabled'] === false, 'unauthenticated customer receives no selector');
    $test->assert($selector->options($owner, false)['shipping'] === [], 'no-shipping checkout receives no shipping selector options');

    $storedSelection = ['billing' => ['mode' => 'saved', 'key' => $billing->key(), 'version' => $billing->version()], 'shipping' => ['mode' => 'one_off']];
    $restored = $selector->options($owner, true, $storedSelection);
    $test->assert($restored['selection'] === $storedSelection, 'valid checkout selection state is available for safe restoration');

    $fields = $selector->checkoutFields($billing);
    $test->assert($fields['first_name'] === 'Teszt' && $fields['postcode'] === '1111', 'standard billing fields mapped');
    $test->assert($fields['appleklinika/house_number'] === '1', 'Hungarian address component mapped');
    $test->assert($fields['appleklinika/company_purchase'] === '1' && $fields['appleklinika/company_name'] === 'Apple Klinika Teszt Kft.' && $fields['appleklinika/tax_number'] === '12345678-1-23', 'company and tax fields mapped');
    $personalFields = $selector->checkoutFields($personalBilling);
    $test->assert($personalFields['appleklinika/company_purchase'] === '' && $personalFields['appleklinika/company_name'] === '' && $personalFields['appleklinika/tax_number'] === '', 'personal billing mapping clears company mode and company-only values');
    $test->assert(! isset($fields['phone'], $fields['email']), 'profile contacts never mapped from address');
    $test->assert($selector->resolve($owner, 'billing', $billing->key(), $billing->version())->key() === $billing->key(), 'valid owner selection resolves');

    update_user_meta($owner, 'billing_email', 'profil@example.test');
    update_user_meta($owner, 'billing_phone', '+36 30 999 0000');
    wp_set_current_user($owner);
    WC()->customer = new WC_Customer($owner, true);
    $controller = new CheckoutAddressController($service, $selector, new WooUserMetaProjection());
    $controller->updateSelection([
        'billing' => ['mode' => 'saved', 'key' => $billing->key(), 'version' => $billing->version()],
    ]);
    $selected = $controller->storeApiData()['selection'];
    $test->assert($selected['billing']['key'] === $billing->key() && ! isset($selected['shipping']), 'server stores owner-scoped selection only for the current checkout purpose');
    $test->assert(WC()->customer->get_billing_city() === 'Budapest', 'server applies selected physical fields to Woo checkout customer');
    $test->assert(WC()->customer->get_billing_email() === 'profil@example.test' && WC()->customer->get_billing_phone() === '+36 30 999 0000', 'server selection never overwrites profile contacts');

    $draftOrder = wc_create_order(['customer_id' => $owner]);
    $controller->syncDraftMetadata($draftOrder, new WP_REST_Request());
    $test->assert($draftOrder->get_meta('_appleklinika_address_book_billing_key', true) === $billing->key() && $draftOrder->get_meta('_appleklinika_address_book_shipping_key', true) === '', 'draft keeps only current checkout opaque address selection audit metadata');

    $addressCountBeforeIntent = count($service->list($owner));
    $controller->updateSelection([
        'billing' => ['mode' => 'saved', 'key' => $billing->key(), 'version' => $billing->version(), 'save' => true, 'set_default' => true, 'label' => 'Módosított számlázási cím'],
    ]);
    $intent = $controller->storeApiData()['selection']['billing'];
    $test->assert($intent['save'] === true && $intent['set_default'] === true && count($service->list($owner)) === $addressCountBeforeIntent, 'modified saved address intent is session-only until final order');

    $craftedIntentRejected = false;
    try {
        $controller->updateSelection([
            'billing' => ['mode' => 'saved', 'key' => $billing->key(), 'version' => $billing->version(), 'save' => false, 'set_default' => true, 'label' => ''],
        ]);
    } catch (Throwable) {
        $craftedIntentRejected = true;
    }
    $test->assert($craftedIntentRejected, 'crafted default intent without an explicit save is rejected');

    foreach ([
        static fn () => $selector->resolve($foreign, 'billing', $billing->key(), $billing->version()),
        static fn () => $selector->resolve($owner, 'billing', $billing->key(), $billing->version() + 1),
        static fn () => $selector->resolve($owner, 'shipping', $billing->key(), $billing->version()),
        static fn () => $selector->resolve(0, 'billing', $billing->key(), $billing->version()),
    ] as $invalid) {
        $rejected = false;
        try { $invalid(); } catch (Throwable) { $rejected = true; }
        $test->assert($rejected, 'foreign, stale, unsupported and unauthenticated selections are rejected');
    }

    $service->delete($owner, $shipping->key(), $shipping->version());
    $deletedRejected = false;
    try { $selector->resolve($owner, 'shipping', $shipping->key(), $shipping->version()); } catch (Throwable) { $deletedRejected = true; }
    $test->assert($deletedRejected, 'deleted address invalidates stored selection');
    $test->assert($review->status() === Address::STATUS_NEEDS_REVIEW && $foreignAddress->customerId() === $foreign, 'fixtures remain isolated');
    $controller->clearSession();
    $test->assert($controller->storeApiData()['selection'] === [], 'logout-compatible session cleanup clears saved selection state');

    echo 'Customer address book checkout: ' . $test->count() . " assertions\n";
} finally {
    if ($draftOrder instanceof WC_Order) {
        $draftOrder->delete(true);
    }
    wp_set_current_user(0);
    $test->cleanupUser($owner);
    $test->cleanupUser($foreign);
}
