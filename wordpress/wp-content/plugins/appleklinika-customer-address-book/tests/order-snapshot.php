<?php

declare(strict_types=1);

require_once __DIR__ . '/TestSupport.php';

use AppleKlinika\CustomerAddressBook\Application\Handler\AddressBookService;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\Address;
use AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\WordPressAddressRepository;
use AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\WordPressTransactionManager;
use AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce\WooAllowedCountries;
use AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce\WooUserMetaProjection;
use AppleKlinika\CustomerAddressBook\Application\Handler\CheckoutAddressSelection;
use AppleKlinika\CustomerAddressBook\Interfaces\Checkout\CheckoutAddressController;

$test = new AddressBookTestSupport();
global $wpdb;
$userId = $test->createUser('order-snapshot');
$service = new AddressBookService(new WordPressAddressRepository($wpdb), new WordPressTransactionManager($wpdb), new WooUserMetaProjection(), new WooAllowedCountries());
$order = null;
$oneOffOrder = null;

try {
    update_user_meta($userId, 'billing_email', 'profil@example.test');
    update_user_meta($userId, 'billing_phone', '+36 30 999 0000');
    $billing = $service->create($userId, $test->addressData(['label' => 'Számla', 'capabilities' => Address::BILLING]), true, false);
    $shipping = $service->create($userId, $test->addressData(['label' => 'Szállítás', 'capabilities' => Address::SHIPPING, 'city' => 'Szeged']), false, true);

    $order = wc_create_order(['customer_id' => $userId]);
    $order->set_address([
        'first_name' => 'Teszt', 'last_name' => 'Vásárló', 'address_1' => 'Teszt utca', 'postcode' => '1111', 'city' => 'Budapest', 'country' => 'HU',
        'email' => 'profil@example.test', 'phone' => '+36 30 999 0000',
    ], 'billing');
    $order->set_address([
        'first_name' => 'Teszt', 'last_name' => 'Vásárló', 'address_1' => 'Teszt utca', 'postcode' => '6720', 'city' => 'Szeged', 'country' => 'HU',
    ], 'shipping');
    $order->update_meta_data('_appleklinika_address_book_billing_key', $billing->key());
    $order->update_meta_data('_appleklinika_address_book_billing_version', (string) $billing->version());
    $order->update_meta_data('_appleklinika_address_book_shipping_key', $shipping->key());
    $order->update_meta_data('_appleklinika_address_book_shipping_version', (string) $shipping->version());
    $order->save();
    $orderId = $order->get_id();

    $test->assert((string) wc_get_order($orderId)->get_billing_city() === 'Budapest', 'saved billing snapshot copied to temporary order');
    $test->assert((string) wc_get_order($orderId)->get_shipping_city() === 'Szeged', 'saved shipping snapshot copied to temporary order');
    $test->assert(wc_get_order($orderId)->get_meta('_appleklinika_address_book_billing_key', true) === $billing->key(), 'billing audit key preserved');
    $test->assert((int) wc_get_order($orderId)->get_meta('_appleklinika_address_book_shipping_version', true) === $shipping->version(), 'shipping audit version preserved');
    $test->assert(wc_get_order($orderId)->get_billing_email() === 'profil@example.test', 'order snapshots normal checkout profile email');
    $test->assert(wc_get_order($orderId)->get_billing_phone() === '+36 30 999 0000', 'order snapshots normal checkout profile phone');

    $oneOffOrder = wc_create_order(['customer_id' => $userId]);
    $oneOffOrder->set_address([
        'first_name' => 'Egyszeri', 'last_name' => 'Cím', 'address_1' => 'Minta tér', 'postcode' => '4024', 'city' => 'Debrecen', 'country' => 'HU',
        'email' => 'profil@example.test', 'phone' => '+36 30 999 0000',
    ], 'billing');
    $oneOffOrder->save();
    $test->assert(wc_get_order($oneOffOrder->get_id())->get_billing_city() === 'Debrecen', 'one-off checkout address is retained only in its order snapshot');
    $test->assert(wc_get_order($oneOffOrder->get_id())->get_billing_email() === 'profil@example.test', 'one-off order uses normal profile contact snapshot');

    wp_set_current_user($userId);
    WC()->customer = new WC_Customer($userId, true);
    $controller = new CheckoutAddressController($service, new CheckoutAddressSelection($service, new WooAllowedCountries()), new WooUserMetaProjection());
    $addressCountBeforeSave = count($service->list($userId));
    $controller->updateSelection(['billing' => ['mode' => 'one_off', 'save' => true, 'set_default' => true, 'label' => 'Debreceni cím']]);
    $test->assert(count($service->list($userId)) === $addressCountBeforeSave, 'one-off save intent does not write an address during checkout draft editing');
    $oneOffOrder->update_meta_data('_wc_billing/appleklinika/house_number', '12');
    $oneOffOrder->save();
    $controller->finalizeOrder($oneOffOrder);
    $savedFromCheckout = $service->getDefault($userId, 'billing');
    $savedData = $savedFromCheckout?->toArray() ?? [];
    $test->assert($savedFromCheckout !== null && $savedData['label'] === 'Debreceni cím' && $savedData['source'] === Address::SOURCE_CHECKOUT, 'one-off address is saved only after final order processing');
    $test->assert($savedData['house_number'] === '12' && $savedData['phone'] === '' && $savedData['email'] === '', 'checkout saved address contains physical fields only and keeps contacts profile-owned');
    $test->assert(get_user_meta($userId, 'billing_city', true) === 'Debrecen', 'explicit checkout default change projects its new canonical billing address');
    $test->assert(get_user_meta($userId, 'billing_email', true) === 'profil@example.test' && get_user_meta($userId, 'billing_phone', true) === '+36 30 999 0000', 'checkout finalization preserves profile contacts');

    $service->update($userId, $billing->key(), $billing->version(), $test->addressData(['label' => 'Számla', 'capabilities' => Address::BILLING, 'city' => 'Pécs']));
    $service->delete($userId, $shipping->key(), $shipping->version());
    $unchanged = wc_get_order($orderId);
    $test->assert($unchanged->get_billing_city() === 'Budapest', 'later saved-address update leaves order snapshot unchanged');
    $test->assert($unchanged->get_shipping_city() === 'Szeged', 'later saved-address delete leaves order snapshot unchanged');
    $test->assert(get_user_meta($userId, 'billing_email', true) === 'profil@example.test', 'profile email preserved by address operations');
    $test->assert(get_user_meta($userId, 'billing_phone', true) === '+36 30 999 0000', 'profile phone preserved by address operations');

    update_user_meta($userId, 'billing_city', 'Egyszeri checkout cím');
    $defaultBilling = $service->getDefault($userId, 'billing');
    (new WooUserMetaProjection())->project($userId, 'billing', $defaultBilling);
    $test->assert(get_user_meta($userId, 'billing_city', true) === 'Debrecen', 'current canonical billing default restores physical profile fields after checkout');
    $test->assert(get_user_meta($userId, 'billing_email', true) === 'profil@example.test' && get_user_meta($userId, 'billing_phone', true) === '+36 30 999 0000', 'profile contacts survive canonical default restoration');

    echo 'Customer address book order snapshot: ' . $test->count() . " assertions\n";
} finally {
    if ($order instanceof WC_Order) {
        $order->delete(true);
    }
    if ($oneOffOrder instanceof WC_Order) {
        $oneOffOrder->delete(true);
    }
    wp_set_current_user(0);
    $test->cleanupUser($userId);
}
