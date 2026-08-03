<?php

declare(strict_types=1);

use AppleKlinika\CustomerAddressBook\Application\Handler\AddressBookService;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\AddressException;
use AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\WordPressAddressRepository;
use AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\WordPressTransactionManager;
use AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce\WooAllowedCountries;
use AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce\WooUserMetaProjection;

require_once __DIR__ . '/TestSupport.php';

$test = new AddressBookTestSupport();
$before = $test->businessSnapshot();
$userId = $test->createUser('account');
global $wpdb;
$service = new AddressBookService(
    new WordPressAddressRepository($wpdb),
    new WordPressTransactionManager($wpdb),
    new WooUserMetaProjection(),
    new WooAllowedCountries()
);

try {
    $created = $service->create($userId, $test->addressData(['capabilities' => 1]));
    $test->assert($created->supports('billing'), 'account create billing');
    $test->assert(! $created->supports('shipping'), 'account purpose isolation');
    $updated = $service->update($userId, $created->key(), 1, $test->addressData(['capabilities' => 1, 'label' => 'Számla']));
    $test->assert($updated->toArray()['label'] === 'Számla', 'account edit');
    $test->assert($updated->version() === 2, 'edit version');

    $invalidCountry = false;
    try { $service->create($userId, $test->addressData(['country' => 'ZZ'])); } catch (AddressException) { $invalidCountry = true; }
    $test->assert($invalidCountry, 'invalid country rejected');

    $duplicate = false;
    try { $service->create($userId, $test->addressData(['capabilities' => 1, 'label' => 'Ugyanaz'])); } catch (AddressException) { $duplicate = true; }
    $test->assert($duplicate, 'duplicate address rejected with warning');

    $invalidDefault = false;
    try {
        $service->create($userId, $test->addressData([
            'capabilities' => 2,
            'label' => 'Csak szállítás',
            'city' => 'Győr',
        ]), true, false);
    } catch (AddressException) {
        $invalidDefault = true;
    }
    $test->assert($invalidDefault, 'unsupported default purpose rejected');
    $test->assert(count($service->list($userId)) === 1, 'unsupported default creation rolled back');

    $other = $test->createUser('account-owner');
    $ownership = false;
    try { $service->setDefault($other, $created->key(), 'billing'); } catch (AddressException) { $ownership = true; }
    $test->assert($ownership, 'ownership protected');
    $test->cleanupUser($other);

    for ($index = 2; $index <= 20; $index++) {
        $service->create($userId, $test->addressData(['label' => 'Cím ' . $index, 'city' => 'Város ' . $index]));
    }
    $test->assert(count($service->list($userId)) === 20, 'twenty addresses allowed');
    $limit = false;
    try { $service->create($userId, $test->addressData(['label' => 'Cím 21'])); } catch (AddressException) { $limit = true; }
    $test->assert($limit, 'address limit enforced');

    $controllerSource = file_get_contents(dirname(__DIR__) . '/src/Interfaces/Account/AccountController.php');
    $test->assert(is_string($controllerSource) && str_contains($controllerSource, "wp_verify_nonce"), 'nonce validation present');
    $test->assert(str_contains((string) $controllerSource, 'get_current_user_id()'), 'authenticated owner context present');
    $test->assert(str_contains((string) $controllerSource, "admin_post_nopriv_"), 'anonymous action denied');
    $test->assert(str_contains((string) $controllerSource, "wp_safe_redirect"), 'PRG redirect present');
    $test->assert(! str_contains((string) $controllerSource, 'name="customer_id"'), 'client customer id absent');
    $test->assert(! str_contains((string) $controllerSource, 'name="address_id"'), 'numeric address id absent');
    $test->assert(! str_contains((string) $controllerSource, "'phone','email'"), 'account address payload ignores profile contact fields');
    $test->assert(! str_contains((string) $controllerSource, "field('phone'"), 'address form has no telephone input');
    $test->assert(! str_contains((string) $controllerSource, "field('email'"), 'address form has no email input');
    $test->assert(str_contains((string) $controllerSource, 'ak-address-form__required'), 'required marker uses inline markup');
    $test->assert(str_contains((string) $controllerSource, 'data-address-default'), 'default controls use bound labelled rows');
    $test->assert(str_contains((string) $controllerSource, 'purpose_billing'), 'billing capability control retained');
    $test->assert(str_contains((string) $controllerSource, 'purpose_shipping'), 'shipping capability control retained');
} finally {
    $test->cleanupUser($userId);
}

$test->assert($before === $test->businessSnapshot(), 'business rows unchanged');
echo 'Customer address book account: ' . $test->count() . " assertions\n";
