<?php

declare(strict_types=1);

use AppleKlinika\CustomerAddressBook\Domain\AddressBook\Address;
use AppleKlinika\CustomerAddressBook\Domain\AddressBook\AddressException;

require_once __DIR__ . '/TestSupport.php';

$test = new AddressBookTestSupport();
$data = $test->addressData();
$address = Address::create(42, rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '='), $data);
$test->assert($address->customerId() === 42, 'customer owner');
$test->assert($address->version() === 1, 'initial version');
$test->assert($address->id() === 0, 'new internal id');
$test->assert(strlen($address->key()) >= 20, 'opaque key length');
$test->assert(! ctype_digit($address->key()), 'opaque key is non-sequential');
$test->assert($address->supports('billing'), 'billing capability');
$test->assert($address->supports('shipping'), 'shipping capability');
$test->assert($address->canBeDefault('billing'), 'active default eligible');
$updated = $address->updated(['city' => 'Szeged']);
$test->assert($updated->version() === 2, 'version increments');
$test->assert($updated->customerId() === $address->customerId(), 'ownership immutable');
$test->assert($updated->key() === $address->key(), 'public key immutable');

$review = Address::create(42, str_repeat('r', 24), $test->addressData([
    'status' => Address::STATUS_NEEDS_REVIEW,
    'postcode' => '',
]));
$test->assert(! $review->canBeDefault('billing'), 'needs review cannot be default');

$contactFree = Address::create(42, str_repeat('c', 24), $test->addressData([
    'phone' => '',
    'email' => 'not-an-address-contact',
]));
$test->assert($contactFree->canBeDefault('billing'), 'active address does not require profile contact values');

$companyBilling = Address::create(42, str_repeat('b', 24), $test->addressData([
    'capabilities' => Address::BILLING,
    'first_name' => '',
    'last_name' => '',
    'company_name' => 'Teszt Kft.',
    'tax_number' => '12345678-1-23',
]));
$test->assert($companyBilling->isCompanyBilling(), 'billing company identity is derived from canonical company data');
$test->assert($companyBilling->toArray()['first_name'] === '' && $companyBilling->toArray()['last_name'] === '', 'billing-only company does not need a personal invoice name');

$companyShippingRejected = false;
try {
    Address::create(42, str_repeat('s', 24), $test->addressData([
        'capabilities' => Address::BOTH,
        'first_name' => '',
        'last_name' => '',
        'company_name' => 'Teszt Kft.',
        'tax_number' => '12345678-1-23',
    ]));
} catch (AddressException) {
    $companyShippingRejected = true;
}
$test->assert($companyShippingRejected, 'company billing plus shipping requires a separate delivery recipient');

$shippingCompanyRejected = false;
try {
    Address::create(42, str_repeat('h', 24), $test->addressData([
        'capabilities' => Address::SHIPPING,
        'company_name' => 'Teszt Kft.',
        'tax_number' => '12345678-1-23',
    ]));
} catch (AddressException) {
    $shippingCompanyRejected = true;
}
$test->assert($shippingCompanyRejected, 'shipping-only addresses cannot carry company invoice data');

$invalid = [
    ['label' => '', 'message' => 'empty label'],
    ['label' => str_repeat('a', 81), 'message' => 'label max length'],
    ['capabilities' => 0, 'message' => 'capabilities required'],
    ['country' => 'HUN', 'message' => 'two-character country'],
    ['postcode' => '', 'message' => 'active postcode required'],
    ['company_name' => 'Teszt Kft.', 'tax_number' => 'bad', 'message' => 'HU company tax format'],
];
foreach ($invalid as $case) {
    $failed = false;
    try {
        Address::create(42, str_repeat('i', 24), $test->addressData($case));
    } catch (AddressException) {
        $failed = true;
    }
    $test->assert($failed, $case['message']);
}

$badOwner = false;
try { Address::create(0, str_repeat('o', 24), $data); } catch (AddressException) { $badOwner = true; }
$test->assert($badOwner, 'customer id required');

echo 'Customer address book domain: ' . $test->count() . " assertions\n";
