<?php

declare(strict_types=1);

require_once __DIR__ . '/TestSupport.php';

use AppleKlinika\CustomerAddressBook\Application\Handler\AddressBookService;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\Address;
use AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\WordPressAddressRepository;
use AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\WordPressTransactionManager;
use AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce\WooAllowedCountries;
use AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce\WooUserMetaProjection;
use AppleKlinika\CustomerAddressBook\Interfaces\Privacy\AddressBookPrivacyController;

$support = new AddressBookTestSupport();
$before = $support->businessSnapshot();
$repository = new WordPressAddressRepository($wpdb);
$projection = new WooUserMetaProjection();
$service = new AddressBookService($repository, new WordPressTransactionManager($wpdb), $projection, new WooAllowedCountries());
$privacy = new AddressBookPrivacyController($service, $projection, $wpdb);

$ownerId = 0;
$otherCustomerId = 0;
$orderId = 0;
$ownerEmail = 'address-book-privacy-owner-' . uniqid('', true) . '@example.test';
$otherEmail = 'address-book-privacy-other-' . uniqid('', true) . '@example.test';
$sessionTable = $wpdb->prefix . 'woocommerce_sessions';

try {
    $ownerId = $support->createUser('privacy-owner');
    $otherCustomerId = $support->createUser('privacy-other');
    wp_update_user(array('ID' => $ownerId, 'user_email' => $ownerEmail));
    wp_update_user(array('ID' => $otherCustomerId, 'user_email' => $otherEmail));

    update_user_meta($ownerId, 'billing_phone', '+36 30 123 4567');
    update_user_meta($ownerId, 'billing_email', $ownerEmail);
    update_user_meta($ownerId, 'appleklinika_favorite_products', array(123));
    update_user_meta($ownerId, 'ak_customer_address_book_migration_version', '1');

    $billing = $service->create(
        $ownerId,
        $support->addressData(array(
            'label' => 'Iroda',
            'capabilities' => Address::BILLING,
            'last_name' => 'Tulajdonos',
            'company_name' => 'Apple Klinika Kft.',
            'tax_number' => '12345678-2-42',
            'postcode' => '1117',
            'address_1' => 'Teszt utca',
            'house_number' => '12',
            'staircase' => 'A',
            'floor' => '3',
            'door' => '4',
        ))
    );
    $shipping = $service->create(
        $ownerId,
        $support->addressData(array(
            'label' => 'Raktár',
            'capabilities' => Address::SHIPPING,
            'postcode' => '2040',
            'city' => 'Budaörs',
            'address_1' => 'Minta köz',
            'address_2' => '2.',
            'house_number' => '8',
        ))
    );
    $foreign = $service->create(
        $otherCustomerId,
        $support->addressData(array(
            'label' => 'Másik cím',
            'capabilities' => Address::BILLING,
            'first_name' => 'Másik',
            'last_name' => 'Vásárló',
            'postcode' => '6720',
            'city' => 'Szeged',
            'address_1' => 'Idegen utca',
        ))
    );

    $projection->project($ownerId, 'billing', $billing);
    $projection->project($ownerId, 'shipping', $shipping);

    $sessionPayload = array(
        'cart' => array('preserved' => 'value'),
        'appleklinika_address_book_checkout' => array(
            'billing' => array('mode' => 'saved', 'address_key' => $billing->key()),
            'shipping' => array('mode' => 'saved', 'address_key' => $shipping->key()),
        ),
    );
    $wpdb->replace(
        $sessionTable,
        array(
            'session_key' => (string) $ownerId,
            'session_value' => maybe_serialize($sessionPayload),
            'session_expiry' => time() + HOUR_IN_SECONDS,
        ),
        array('%s', '%s', '%d')
    );

    $order = wc_create_order(array('customer_id' => $ownerId));
    $order->set_billing_first_name('Teszt');
    $order->set_billing_last_name('Tulajdonos');
    $order->set_billing_email($ownerEmail);
    $order->set_billing_phone('+36 30 123 4567');
    $order->update_meta_data('_appleklinika_address_book_billing_key', $billing->key());
    $order->update_meta_data('_appleklinika_address_book_billing_version', $billing->version());
    $order->update_meta_data('_appleklinika_address_book_shipping_key', $shipping->key());
    $order->update_meta_data('_appleklinika_address_book_shipping_mode', 'saved');
    $order->update_meta_data('_unrelated_order_meta', 'preserve');
    $order->save();
    $orderId = $order->get_id();

    $exporterRegistry = $privacy->registerExporters(array());
    $eraserRegistry = $privacy->registerErasers(array());
    $support->assert(isset($exporterRegistry['appleklinika-customer-address-book']['callback']), 'privacy exporter registers with WordPress');
    $support->assert($exporterRegistry['appleklinika-customer-address-book']['exporter_friendly_name'] === 'Apple Klinika – Mentett címek', 'privacy exporter uses a Hungarian friendly group label');
    $support->assert(isset($eraserRegistry['appleklinika-customer-address-book']['callback']), 'privacy eraser registers with WordPress');
    $support->assert($eraserRegistry['appleklinika-customer-address-book']['eraser_friendly_name'] === 'Apple Klinika – Mentett címek', 'privacy eraser uses a Hungarian friendly group label');

    $export = $privacy->exporter($ownerEmail);
    $support->assert($export['done'] === true, 'privacy exporter completes its address data page');
    $support->assert(count($export['data']) === 2, 'privacy exporter includes each saved address for the matching customer');
    $exported = wp_json_encode($export['data']);
    $exportFields = array_merge(...array_map(static fn (array $item): array => $item['data'], $export['data']));
    $fieldNames = array_column($exportFields, 'name');
    $fieldValues = array_column($exportFields, 'value');
    $support->assert(in_array('Iroda', $fieldValues, true), 'privacy exporter includes the human-readable label');
    $support->assert(in_array('Számlázási cím', $fieldValues, true), 'privacy exporter includes the purpose using Hungarian labels');
    $support->assert(in_array('Alapértelmezett számlázási cím', $fieldNames, true), 'privacy exporter includes default-state information');
    $support->assert(in_array('Adószám', $fieldNames, true), 'privacy exporter includes company and tax details');
    $support->assert(in_array('Létrehozva', $fieldNames, true), 'privacy exporter includes creation time with a Hungarian label');
    $support->assert(! in_array('Másik cím', $fieldValues, true), 'privacy exporter never exposes another customer address');
    $support->assert(! str_contains((string) $exported, (string) $billing->id()), 'privacy exporter does not expose numeric address identifiers');
    $support->assert(! str_contains((string) $exported, $billing->key()), 'privacy exporter does not expose the canonical address key');
    $support->assert(count($privacy->exporter('nobody@example.test')['data']) === 0, 'privacy exporter returns no data for an unknown email');
    $support->assert($privacy->exporter($ownerEmail, 2)['done'] === true, 'privacy exporter stops after its single page');

    $privacy->anonymizeOrderReferences($order);
    $order->save();
    $reloadedOrder = wc_get_order($orderId);
    $support->assert((string) $reloadedOrder->get_meta('_appleklinika_address_book_billing_key', true) === '', 'order anonymization removes only plugin-owned billing address references');
    $support->assert((string) $reloadedOrder->get_meta('_appleklinika_address_book_shipping_key', true) === '', 'order anonymization removes only plugin-owned shipping address references');
    $support->assert((string) $reloadedOrder->get_meta('_unrelated_order_meta', true) === 'preserve', 'order anonymization preserves unrelated order metadata');
    $support->assert($reloadedOrder->get_billing_email() === $ownerEmail, 'order anonymization does not alter the historical order address snapshot itself');

    $erasure = $privacy->eraser($ownerEmail);
    $support->assert($erasure['items_removed'] === true, 'privacy eraser reports saved address data as removed');
    $support->assert($erasure['items_retained'] === false, 'privacy eraser reports no retained plugin address data');
    $support->assert($erasure['done'] === true, 'privacy eraser completes in one page');
    $support->assert(count($service->list($ownerId)) === 0, 'privacy eraser removes every canonical address owned by the customer');
    $support->assert($service->getDefault($ownerId, 'billing') === null, 'privacy eraser removes the billing default pointer');
    $support->assert($service->getDefault($ownerId, 'shipping') === null, 'privacy eraser removes the shipping default pointer');
    $support->assert((string) get_user_meta($ownerId, 'ak_customer_address_book_migration_version', true) === '', 'privacy eraser removes the legacy address migration marker');
    $support->assert((string) get_user_meta($ownerId, 'billing_address_1', true) === '', 'privacy eraser clears projected physical billing fields');
    $support->assert((string) get_user_meta($ownerId, 'shipping_city', true) === '', 'privacy eraser clears projected physical shipping fields');
    $support->assert((string) get_user_meta($ownerId, 'ak_billing_tax_number', true) === '', 'privacy eraser clears projected company tax fields');
    $support->assert((string) get_user_meta($ownerId, 'billing_email', true) === $ownerEmail, 'privacy eraser preserves account email');
    $support->assert((string) get_user_meta($ownerId, 'billing_phone', true) === '+36 30 123 4567', 'privacy eraser preserves account phone');
    $support->assert(get_user_meta($ownerId, 'appleklinika_favorite_products', true) === array(123), 'privacy eraser preserves favourites data');
    $support->assert(wc_get_order($orderId)->get_billing_email() === $ownerEmail, 'privacy eraser preserves historic order snapshots');
    $sessionAfterErasure = maybe_unserialize((string) $wpdb->get_var($wpdb->prepare("SELECT session_value FROM {$sessionTable} WHERE session_key = %s", (string) $ownerId)));
    $support->assert($sessionAfterErasure['cart'] === array('preserved' => 'value'), 'privacy eraser preserves unrelated WooCommerce session data');
    $support->assert(! isset($sessionAfterErasure['appleklinika_address_book_checkout']), 'privacy eraser removes the plugin checkout selection from the customer session');
    $support->assert(count($service->list($otherCustomerId)) === 1, 'privacy eraser never removes another customer address');
    $support->assert($service->list($otherCustomerId)[0]->key() === $foreign->key(), 'privacy eraser leaves the other customer address unchanged');

    $repeatErasure = $privacy->eraser($ownerEmail);
    $support->assert($repeatErasure['items_removed'] === false, 'privacy eraser is idempotent after all address data has already been removed');
    $support->assert($repeatErasure['items_retained'] === false, 'repeated privacy erasure has no retained plugin data');
    $support->assert($privacy->eraser('nobody@example.test')['items_removed'] === false, 'privacy eraser rejects an email that owns no customer account');
} finally {
    if ($orderId > 0) {
        $order = wc_get_order($orderId);
        if ($order instanceof WC_Order) {
            $order->delete(true);
        }
    }

    if ($ownerId > 0) {
        $wpdb->delete($sessionTable, array('session_key' => (string) $ownerId), array('%s'));
        $support->cleanupUser($ownerId);
    }

    if ($otherCustomerId > 0) {
        $support->cleanupUser($otherCustomerId);
    }

    wp_set_current_user(0);
}

$support->assert($before === $support->businessSnapshot(), 'privacy test cleanup leaves retained business rows unchanged');
echo 'Customer address book privacy: ' . $support->count() . " assertions\n";
