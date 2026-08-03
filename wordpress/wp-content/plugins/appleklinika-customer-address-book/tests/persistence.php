<?php

declare(strict_types=1);

use AppleKlinika\CustomerAddressBook\Application\Handler\AddressBookService;
use AppleKlinika\CustomerAddressBook\Application\Port\AddressProjection;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\Address;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\VersionConflict;
use AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\WordPressAddressRepository;
use AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\WordPressTransactionManager;
use AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce\WooAllowedCountries;
use AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce\WooUserMetaProjection;

require_once __DIR__ . '/TestSupport.php';

$test = new AddressBookTestSupport();
$before = $test->businessSnapshot();
$userId = $test->createUser('persistence');
global $wpdb;
$repo = new WordPressAddressRepository($wpdb);
$transactions = new WordPressTransactionManager($wpdb);
$projection = new WooUserMetaProjection();
$service = new AddressBookService($repo, $transactions, $projection, new WooAllowedCountries());

try {
    $first = $service->create($userId, $test->addressData());
    $test->assert($first->id() > 0, 'create returns internal id');
    $test->assert($repo->countActiveForCustomer($userId) === 1, 'active count');
    $test->assert(count($service->list($userId)) === 1, 'list owner');
    $test->assert($service->get($userId, $first->key())->id() === $first->id(), 'get opaque key');
    $test->assert($service->getDefault($userId, 'billing')?->id() === $first->id(), 'first billing default');
    $test->assert($service->getDefault($userId, 'shipping')?->id() === $first->id(), 'first shipping default');
    $test->assert(get_user_meta($userId, 'billing_city', true) === 'Budapest', 'billing projection');
    $test->assert(get_user_meta($userId, 'shipping_city', true) === 'Budapest', 'shipping projection');
    $test->assert(get_user_meta($userId, 'ak_billing_house_number', true) === '1', 'AK billing component');
    $test->assert(get_user_meta($userId, 'ak_shipping_house_number', true) === '1', 'AK shipping component');

    $second = $service->create($userId, $test->addressData(['label' => 'Munkahely', 'city' => 'Győr']), true, false);
    $test->assert(count($service->list($userId)) === 2, 'second address');
    $test->assert($service->getDefault($userId, 'billing')?->id() === $second->id(), 'billing switched');
    $test->assert($service->getDefault($userId, 'shipping')?->id() === $first->id(), 'shipping isolated');
    $test->assert(get_user_meta($userId, 'billing_city', true) === 'Győr', 'billing projection switched');

    $duplicate = false;
    try { $service->create($userId, $test->addressData(['label' => 'Más név', 'city' => 'Győr'])); } catch (Throwable) { $duplicate = true; }
    $test->assert($duplicate, 'duplicate content warning');

    $changed = $service->update($userId, $second->key(), 1, $test->addressData(['label' => 'Iroda', 'city' => 'Pécs']), false, true);
    $test->assert($changed->version() === 2, 'repository update version');
    $test->assert(get_user_meta($userId, 'billing_city', true) === 'Pécs', 'default edit reprojected');
    $test->assert($service->getDefault($userId, 'shipping')?->id() === $changed->id(), 'edit may atomically select shipping default');
    $conflict = false;
    try { $service->update($userId, $second->key(), 1, $test->addressData(['city' => 'Miskolc'])); } catch (VersionConflict) { $conflict = true; }
    $test->assert($conflict, 'stale version conflict');

    $company = $service->create($userId, $test->addressData([
        'label' => 'Céges',
        'capabilities' => Address::BILLING,
        'company_name' => 'Teszt Kft.',
        'tax_number' => '12345678-1-23',
        'city' => 'Debrecen',
    ]), true, false);
    $test->assert(get_user_meta($userId, 'billing_company', true) === 'Teszt Kft.', 'billing company projection');
    $test->assert(get_user_meta($userId, 'ak_billing_is_company', true) === '1', 'AK company flag');
    $test->assert(get_user_meta($userId, 'ak_billing_tax_number', true) === '12345678-1-23', 'AK tax alias');
    $test->assert(get_user_meta($userId, 'appleklinika_company_name', true) === 'Teszt Kft.', 'company compatibility alias');
    $test->assert(get_user_meta($userId, 'appleklinika_tax_number', true) === '12345678-1-23', 'tax compatibility alias');
    $service->delete($userId, $company->key(), 1, ['billing' => $changed->key()]);
    $test->assert($service->getDefault($userId, 'billing')?->id() === $changed->id(), 'company default successor');

    $otherUser = $test->createUser('owner');
    $ownerBlocked = false;
    try { $service->get($otherUser, $first->key()); } catch (Throwable) { $ownerBlocked = true; }
    $test->assert($ownerBlocked, 'cross-customer read blocked');
    $test->cleanupUser($otherUser);

    $service->delete($userId, $second->key(), 2, ['billing' => $first->key(), 'shipping' => $first->key()]);
    $test->assert(count($service->list($userId)) === 1, 'default delete');
    $test->assert($service->getDefault($userId, 'billing')?->id() === $first->id(), 'successor selected');
    $service->delete($userId, $first->key(), 1);
    $test->assert($service->list($userId) === [], 'last address deleted');
    $test->assert($service->getDefault($userId, 'billing') === null, 'billing pointer cleared');
    $test->assert($service->getDefault($userId, 'shipping') === null, 'shipping pointer cleared');
    $test->assert(! metadata_exists('user', $userId, 'billing_city'), 'billing projection cleared');
    $test->assert(! metadata_exists('user', $userId, 'shipping_city'), 'shipping projection cleared');
    $test->assert(! metadata_exists('user', $userId, 'appleklinika_tax_number'), 'company projection aliases cleared');

    $failingProjection = new class implements AddressProjection {
        public function project(int $customerId, string $purpose, Address $address): void { throw new RuntimeException('projection failure'); }
        public function clear(int $customerId, string $purpose): void { throw new RuntimeException('projection failure'); }
    };
    $failingService = new AddressBookService($repo, $transactions, $failingProjection, new WooAllowedCountries());
    $rolledBack = false;
    try { $failingService->create($userId, $test->addressData()); } catch (RuntimeException) { $rolledBack = true; }
    $test->assert($rolledBack, 'projection failure surfaced');
    $test->assert($service->list($userId) === [], 'projection failure rolled back create');
} finally {
    $test->cleanupUser($userId);
}

$test->assert($before === $test->businessSnapshot(), 'retained business rows unchanged');
echo 'Customer address book persistence: ' . $test->count() . " assertions\n";
