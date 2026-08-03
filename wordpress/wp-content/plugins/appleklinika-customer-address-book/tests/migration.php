<?php

declare(strict_types=1);

use AppleKlinika\CustomerAddressBook\Application\Handler\AddressBookService;
use AppleKlinika\CustomerAddressBook\Application\Handler\LegacyAddressImporter;
use AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\WordPressAddressRepository;
use AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\WordPressTransactionManager;
use AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce\WooAllowedCountries;
use AppleKlinika\CustomerAddressBook\Infrastructure\WooCommerce\WooUserMetaProjection;

require_once __DIR__ . '/TestSupport.php';

$test = new AddressBookTestSupport();
$before = $test->businessSnapshot();
global $wpdb;
$repo = new WordPressAddressRepository($wpdb);
$service = new AddressBookService($repo, new WordPressTransactionManager($wpdb), new WooUserMetaProjection(), new WooAllowedCountries());
$importer = new LegacyAddressImporter($service, $repo);
$users = [];

$legacyMetaSnapshot = static function (int $userId): array {
    $meta = get_user_meta($userId);
    unset($meta[LegacyAddressImporter::USER_META_VERSION]);
    ksort($meta);
    return $meta;
};

$set = static function (int $userId, string $purpose, string $city, string $phone = '+36 30 111 2222'): void {
    foreach ([
        'first_name' => 'Régi', 'last_name' => 'Vásárló', 'country' => 'HU', 'postcode' => '1111',
        'city' => $city, 'address_1' => 'Örökség utca', 'phone' => $phone,
    ] as $field => $value) {
        update_user_meta($userId, $purpose . '_' . $field, $value);
    }
    update_user_meta($userId, 'ak_' . $purpose . '_house_number', '7');
    if ($purpose === 'billing') update_user_meta($userId, 'billing_email', 'legacy@example.test');
};

try {
    $mergedUser = $test->createUser('migration-merged');
    $users[] = $mergedUser;
    $set($mergedUser, 'billing', 'Budapest');
    $set($mergedUser, 'shipping', 'Budapest');
    $originalMeta = $legacyMetaSnapshot($mergedUser);
    $dry = $importer->import($mergedUser, true);
    $test->assert($dry['imported'] === 1, 'dry-run predicts merged import');
    $test->assert($dry['merged'] === 1, 'dry-run merged count');
    $test->assert($service->list($mergedUser) === [], 'dry-run no address');
    $test->assert(! metadata_exists('user', $mergedUser, LegacyAddressImporter::USER_META_VERSION), 'dry-run no version marker');
    $result = $importer->import($mergedUser);
    $test->assert($result['imported'] === 1, 'merged import created');
    $test->assert($result['merged'] === 1, 'merged import count');
    $test->assert(count($service->list($mergedUser)) === 1, 'one merged address');
    $test->assert($service->list($mergedUser)[0]->supports('billing'), 'merged billing capability');
    $test->assert($service->list($mergedUser)[0]->supports('shipping'), 'merged shipping capability');
    $test->assert($legacyMetaSnapshot($mergedUser) === $originalMeta, 'all original merged legacy meta preserved');
    $repeat = $importer->import($mergedUser);
    $test->assert($repeat['already_migrated'] === 1, 'repeat migration idempotent');
    $test->assert(count($service->list($mergedUser)) === 1, 'repeat creates no duplicate');

    $splitUser = $test->createUser('migration-split');
    $users[] = $splitUser;
    $set($splitUser, 'billing', 'Szeged');
    $set($splitUser, 'shipping', 'Győr');
    $splitOriginalMeta = $legacyMetaSnapshot($splitUser);
    $split = $importer->import($splitUser);
    $test->assert($split['imported'] === 2, 'different addresses imported separately');
    $test->assert($split['merged'] === 0, 'different addresses not merged');
    $test->assert(count($service->list($splitUser)) === 2, 'two split addresses');
    $test->assert($service->getDefault($splitUser, 'billing') !== null, 'valid billing default');
    $test->assert($service->getDefault($splitUser, 'shipping') !== null, 'valid shipping default');
    $test->assert($legacyMetaSnapshot($splitUser) === $splitOriginalMeta, 'all original split legacy meta preserved');

    $partialUser = $test->createUser('migration-partial');
    $users[] = $partialUser;
    update_user_meta($partialUser, 'shipping_city', 'Pécs');
    update_user_meta($partialUser, 'shipping_address_1', 'Hiányos utca');
    $partialOriginalMeta = $legacyMetaSnapshot($partialUser);
    $partial = $importer->import($partialUser);
    $test->assert($partial['imported'] === 1, 'partial data preserved');
    $test->assert($partial['needs_review'] === 1, 'partial marked needs review');
    $test->assert($service->list($partialUser)[0]->status() === 'needs_review', 'partial entity status');
    $test->assert($service->getDefault($partialUser, 'shipping') === null, 'partial not default');
    $test->assert($legacyMetaSnapshot($partialUser) === $partialOriginalMeta, 'all original partial legacy meta preserved');
} finally {
    foreach ($users as $userId) $test->cleanupUser($userId);
}

$test->assert($before === $test->businessSnapshot(), 'business persistence unchanged');
echo 'Customer address book migration: ' . $test->count() . " assertions\n";
