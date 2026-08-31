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
$finalOrders = [];
$finalProducts = [];

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
    $companyShipping = $service->create($owner, $test->addressData([
        'label' => 'Céges telephely',
        'capabilities' => Address::BOTH,
        'company_name' => 'Apple Klinika Teszt Kft.',
        'tax_number' => '12345678-1-23',
        'first_name' => 'Szállítási',
        'last_name' => 'Címzett',
    ]), false, false);
    $review = $service->create($owner, $test->addressData(['label' => 'Ellenőrzés', 'status' => Address::STATUS_NEEDS_REVIEW]), false, false);
    $foreignAddress = $service->create($foreign, $test->addressData(['label' => 'Más ügyfél']), true, true);

    $options = $selector->options($owner, true);
    $test->assert($options['enabled'] === true, 'authenticated customer receives address options');
    $test->assert(count($options['billing']) === 3, 'billing includes valid personal and company owner addresses and excludes needs-review address');
    $test->assert(count($options['shipping']) === 2, 'shipping excludes billing-only and needs-review address');
    $billingKeys = array_column($options['billing'], 'key');
    $test->assert(in_array($billing->key(), $billingKeys, true) && in_array($personalBilling->key(), $billingKeys, true), 'only valid owner billing keys returned');
    $defaultBilling = array_values(array_filter($options['billing'], static fn (array $option): bool => $option['key'] === $billing->key()));
    $test->assert(count($defaultBilling) === 1 && $defaultBilling[0]['is_default'] === true, 'default billing marked');
    $defaultShipping = array_values(array_filter($options['shipping'], static fn (array $option): bool => $option['key'] === $shipping->key()));
    $test->assert(count($defaultShipping) === 1 && $defaultShipping[0]['is_default'] === true, 'default shipping marked');
    $test->assert(! array_key_exists('id', $options['billing'][0]), 'numeric address id not exposed');
    $test->assert(! array_key_exists('customer_id', $options['billing'][0]), 'customer id not exposed');
    $test->assert(! array_key_exists('created_at', $options['billing'][0]) && ! array_key_exists('source', $options['billing'][0]), 'non-checkout audit data not exposed');
    $checkoutScript = file_get_contents(dirname(__DIR__) . '/assets/js/checkout-address-book.js');
    $test->assert(is_string($checkoutScript) && str_contains($checkoutScript, "purpose + '-appleklinika-' + name"), 'checkout custom address fields use their Blocks ids');
    $test->assert(str_contains((string) $checkoutScript, "order-appleklinika-company_purchase") && str_contains($checkoutScript, "order-appleklinika-company_name") && str_contains((string) $checkoutScript, "order-appleklinika-tax_number"), 'checkout company controls use their registered Blocks ids');
    $test->assert(str_contains((string) $checkoutScript, 'setCheckoutControlChecked(companyPurchaseInput, option.fields') && str_contains((string) $checkoutScript, 'control.click()') && str_contains($checkoutScript, 'HTMLInputElement.prototype') && str_contains($checkoutScript, 'HTMLSelectElement.prototype') && str_contains($checkoutScript, 'setCheckoutControlValue'), 'checkout address fields update through the controlled Blocks input path');
    $test->assert(str_contains((string) $checkoutScript, 'setCustomFields(section, matchingOption());'), 'checkout applies custom address details to the initially selected default');
    $test->assert(str_contains((string) $checkoutScript, 'function clearSavedAddressProjection(section)') && str_contains($checkoutScript, "'country', 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode'") && str_contains($checkoutScript, "'house_number', 'staircase', 'floor', 'door'") && str_contains($checkoutScript, 'clearSavedAddressProjection(section);'), 'switching a saved address to one-off clears every saved physical-address projection, including hidden address_2 and Hungarian components');
    $test->assert(str_contains((string) $checkoutScript, "select('wc/store/cart')") && str_contains($checkoutScript, 'var blocksCheckout = window.wc') && ! str_contains($checkoutScript, 'window.wc.blocksCheckout.extensionCartUpdate'), 'checkout uses the declared Blocks store directly and captures the checkout API while its script identity is available');
    $test->assert(str_contains((string) $checkoutScript, "select.id = 'ak-checkout-address-selector-' + purpose") && str_contains($checkoutScript, 'caption.htmlFor = select.id'), 'saved-address selectors have an explicit programmatic label relationship');
    $test->assert(str_contains((string) $checkoutScript, 'Válassz mentett számlázási címet') && str_contains($checkoutScript, 'Válassz mentett szállítási címet') && ! str_contains($checkoutScript, "document.createElement('h3')"), 'saved-address selectors use descriptive labels instead of duplicate visible section headings');
    $test->assert(str_contains((string) $checkoutScript, "section.classList.toggle('is-one-off', isOneOff)") && str_contains($checkoutScript, "section.classList.toggle('has-saved-address', !isOneOff)") && str_contains($checkoutScript, 'save.checked = false;') && str_contains($checkoutScript, 'defaultControl.disabled = true;'), 'checkout keeps the saved-address and one-off address presentation states explicit and clears one-off save intent before a saved address is submitted');
    $test->assert(str_contains((string) $checkoutScript, 'function selectionData(root)') && str_contains($checkoutScript, 'data[purpose].fields = oneOffAddressFields(root, purpose) || {};') && str_contains($checkoutScript, 'function saveIntentData(root)') && str_contains($checkoutScript, 'function sendSaveIntent(root)') && ! str_contains($checkoutScript, 'function sendSelection(root)') && str_contains($checkoutScript, 'function installProgressFlush(root)') && str_contains($checkoutScript, 'data-ak-address-flushed'), 'checkout keeps the complete current one-off address and save intent local until the normal continue flow flushes them together');
    $test->assert(str_contains((string) $checkoutScript, "root.addEventListener('change'") && str_contains($checkoutScript, "A szállítási és számlázási cím megegyezik.") && str_contains($checkoutScript, 'window.setTimeout(function () {') && str_contains($checkoutScript, 'window.requestAnimationFrame(sync);'), 'switching off the shared-address control re-runs the existing address-selector sync both after the change and on the following rendered frame, so remounted billing fields receive exactly one selector without an observer, polling loop or DOM relocation');
    $test->assert(str_contains((string) $checkoutScript, 'function oneOffAddressFields(root, purpose)') && str_contains($checkoutScript, 'function waitForOneOffAddressSync(root)') && str_contains($checkoutScript, "cart[purpose + 'Address']") && str_contains($checkoutScript, 'sameAddressFields(expected[purpose]'), 'checkout blocks progression until the WooCommerce cart state contains the latest visible one-off physical address fields');
    $checkoutCss = file_get_contents(dirname(__DIR__) . '/assets/css/checkout-address-book.css');
    $test->assert(is_string($checkoutCss) && str_contains($checkoutCss, '[data-ak-address-save-details][hidden]') && str_contains($checkoutCss, 'display: none !important;') && str_contains($checkoutCss, '.has-saved-address .ak-checkout-address-selector__save'), 'checkout keeps collapsed saved-address details hidden and reserves address-save controls for one-off addresses');
    $test->assert(is_string($checkoutCss) && str_contains($checkoutCss, '.ak-checkout-address-selector') && str_contains($checkoutCss, 'background: transparent;') && str_contains($checkoutCss, 'border: 0;'), 'saved-address selection remains an integrated checkout control rather than a nested card');
    $checkoutController = file_get_contents(dirname(__DIR__) . '/src/Interfaces/Checkout/CheckoutAddressController.php');
    $test->assert(is_string($checkoutController) && str_contains($checkoutController, "'wc-blocks-data-store'"), 'checkout script declares the Woo Blocks data-store dependency');
    $themeFunctions = file_get_contents(dirname(__DIR__, 3) . '/themes/appleklinika-theme/functions.php');
    $test->assert(is_string($themeFunctions) && str_contains($themeFunctions, "'/cart/update-customer'") && str_contains($themeFunctions, 'appleklinika_capture_checkout_company_identity'), 'company identity is available during both Store API checkout and cart customer-address validation');
    $test->assert($selector->options(0, true)['enabled'] === false, 'unauthenticated customer receives no selector');
    $test->assert($selector->options($owner, false)['shipping'] === [], 'no-shipping checkout receives no shipping selector options');

    $storedSelection = ['billing' => ['mode' => 'saved', 'key' => $billing->key(), 'version' => $billing->version()], 'shipping' => ['mode' => 'one_off']];
    $restored = $selector->options($owner, true, $storedSelection);
    $test->assert($restored['selection'] === $storedSelection, 'valid checkout selection state is available for safe restoration');

    $fields = $selector->checkoutFields($billing);
    $test->assert($fields['first_name'] === '' && $fields['last_name'] === '' && $fields['postcode'] === '1111', 'company billing maps no personal invoice name into standard billing fields');
    $test->assert($fields['appleklinika/house_number'] === '1', 'Hungarian address component mapped');
    $test->assert($fields['appleklinika/company_purchase'] === '1' && $fields['appleklinika/company_name'] === 'Apple Klinika Teszt Kft.' && $fields['appleklinika/tax_number'] === '12345678-1-23', 'company and tax fields mapped');
    $personalFields = $selector->checkoutFields($personalBilling);
    $test->assert($personalFields['first_name'] === 'Teszt' && $personalFields['last_name'] === 'Vásárló' && $personalFields['appleklinika/company_purchase'] === '' && $personalFields['appleklinika/company_name'] === '' && $personalFields['appleklinika/tax_number'] === '', 'personal billing mapping restores personal identity and clears company-only values');
    $companyShippingFields = $selector->checkoutFields($companyShipping, 'shipping');
    $test->assert($companyShippingFields['first_name'] === 'Szállítási' && $companyShippingFields['last_name'] === 'Címzett' && $companyShippingFields['company'] === '', 'company billing plus shipping keeps the delivery recipient separate from invoice identity');
    $test->assert(! isset($fields['phone'], $fields['email']), 'profile contacts never mapped from address');
    $test->assert($selector->resolve($owner, 'billing', $billing->key(), $billing->version())->key() === $billing->key(), 'valid owner selection resolves');

    update_user_meta($owner, 'billing_email', 'profil@example.test');
    update_user_meta($owner, 'billing_phone', '+36 30 999 0000');
    wp_set_current_user($owner);
    WC()->customer = new WC_Customer($owner, true);
    $controller = new CheckoutAddressController($service, $selector, new WooUserMetaProjection());
    $extensionSchema = $controller->storeApiSchema();
    $expectedExtensionKeys = ['enabled', 'needs_shipping', 'billing', 'shipping', 'selection'];
    $test->assert(array_keys($extensionSchema) === $expectedExtensionKeys, 'Store API ARRAY_A schema returns only Apple Klinika extension properties, not a root schema wrapper.');
    foreach ($expectedExtensionKeys as $key) {
        $test->assert(is_array($extensionSchema[$key] ?? null), 'Store API extension schema top-level ' . $key . ' entry is a schema-property array.');
    }
    $test->assert(! isset($extensionSchema['description'], $extensionSchema['type'], $extensionSchema['properties']), 'Store API ARRAY_A schema cannot reintroduce root metadata as extension properties.');
    $test->assert(
        $extensionSchema['enabled']['type'] === 'boolean'
        && $extensionSchema['needs_shipping']['type'] === 'boolean'
        && $extensionSchema['billing']['type'] === 'array'
        && $extensionSchema['shipping']['type'] === 'array'
        && $extensionSchema['selection']['type'] === 'object',
        'Store API extension schema types match the checkout address-book data contract.'
    );
    $controller->registerStoreApi();
    $extendSchema = \Automattic\WooCommerce\StoreApi\StoreApi::container()->get(\Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema::class);
    $registeredSchema = $extendSchema->get_endpoint_schema(\Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER);
    $test->assert(isset($registeredSchema->{'appleklinika/address-book'}) && is_array($registeredSchema->{'appleklinika/address-book'}), 'Real WooCommerce Store API registers the Apple Klinika cart extension schema without an exception.');
    $cartSchema = \Automattic\WooCommerce\StoreApi\StoreApi::container()->get(\Automattic\WooCommerce\StoreApi\SchemaController::class)->get(\Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER);
    $publicCartSchema = $cartSchema->get_public_item_schema();
    $test->assert(isset($publicCartSchema['properties']['extensions']['properties']['appleklinika/address-book']['properties']['enabled']), 'Real WooCommerce CartSchema public schema contains the valid Apple Klinika extension properties without string-offset errors.');
    $storeApiData = $controller->storeApiData();
    $test->assert(
        isset($storeApiData['enabled'], $storeApiData['needs_shipping'], $storeApiData['billing'], $storeApiData['shipping'], $storeApiData['selection'])
        && is_bool($storeApiData['enabled'])
        && is_bool($storeApiData['needs_shipping'])
        && is_array($storeApiData['billing'])
        && is_array($storeApiData['shipping'])
        && is_array($storeApiData['selection']),
        'Store API data preserves the supported enabled, needs_shipping, billing, shipping and selection extension contract.'
    );
    $controller->updateSelection([
        'billing' => ['mode' => 'saved', 'key' => $billing->key(), 'version' => $billing->version()],
    ]);
    $selected = $controller->storeApiData()['selection'];
    $test->assert($selected['billing']['key'] === $billing->key() && ! isset($selected['shipping']), 'server stores owner-scoped selection only for the current checkout purpose');
    $test->assert(WC()->customer->get_billing_city() === 'Budapest', 'server applies selected physical fields to Woo checkout customer');
    $test->assert(WC()->customer->get_billing_first_name() === '' && WC()->customer->get_billing_last_name() === '' && WC()->customer->get_billing_company() === 'Apple Klinika Teszt Kft.', 'saved company selection projects legal company name without a fake personal billing identity');
    $test->assert(WC()->customer->get_billing_email() === 'profil@example.test' && WC()->customer->get_billing_phone() === '+36 30 999 0000', 'server selection never overwrites profile contacts');

    $storeApiSelectionResponse = static function (array $selection): array {
        $request = new WP_REST_Request('POST', '/wc/store/v1/cart/extensions');
        $request->set_header('Nonce', wp_create_nonce('wc_store_api'));
        $request->set_param('namespace', 'appleklinika/address-book');
        $request->set_param('data', [
            'selection' => ['billing' => $selection],
            'intent' => ['billing' => ['save' => false, 'set_default' => false, 'label' => '']],
        ]);
        $response = rest_ensure_response(rest_do_request($request));

        return ['status' => $response->get_status(), 'data' => $response->get_data()];
    };
    $unknownSelection = $storeApiSelectionResponse(['mode' => 'saved', 'key' => str_repeat('A', 20), 'version' => 1]);
    $foreignSelection = $storeApiSelectionResponse(['mode' => 'saved', 'key' => $foreignAddress->key(), 'version' => $foreignAddress->version()]);
    $staleSelection = $storeApiSelectionResponse(['mode' => 'saved', 'key' => $billing->key(), 'version' => $billing->version() + 1]);
    $malformedSelection = $storeApiSelectionResponse(['mode' => 'saved', 'key' => 'bad!', 'version' => 0]);
    $validSelection = $storeApiSelectionResponse(['mode' => 'saved', 'key' => $billing->key(), 'version' => $billing->version()]);
    $test->assert($unknownSelection['status'] === 404 && ($unknownSelection['data']['code'] ?? '') === 'appleklinika_address_book_selection_not_found', 'actual Store API returns a controlled 404 for an unknown opaque saved-address key instead of a 500');
    $test->assert($foreignSelection['status'] === 404 && $foreignSelection['data'] === $unknownSelection['data'], 'actual Store API gives a foreign saved-address key the same non-disclosing response as an unknown key');
    $test->assert($staleSelection['status'] === 409 && ($staleSelection['data']['code'] ?? '') === 'appleklinika_address_book_stale_selection', 'actual Store API returns a controlled conflict for a stale saved-address version');
    $test->assert($malformedSelection['status'] === 400 && ($malformedSelection['data']['code'] ?? '') === 'appleklinika_address_book_invalid_selection', 'actual Store API rejects malformed saved-address selection input as a client error');
    $test->assert($validSelection['status'] >= 200 && $validSelection['status'] < 300, 'actual Store API still accepts a valid owned saved-address selection');
    $controller->clearSession();
    $controller->updateSelection([
        'billing' => ['mode' => 'saved', 'key' => $billing->key(), 'version' => $billing->version()],
    ]);

    $draftOrder = wc_create_order(['customer_id' => $owner]);
    $controller->syncDraftMetadata($draftOrder, new WP_REST_Request());
    $test->assert($draftOrder->get_meta('_appleklinika_address_book_billing_key', true) === $billing->key() && $draftOrder->get_meta('_appleklinika_address_book_shipping_key', true) === '', 'draft keeps only current checkout opaque address selection audit metadata');

    WC()->customer->set_billing_first_name('Fast Final');
    WC()->customer->set_billing_last_name('User');
    WC()->customer->set_billing_city('Szeged');
    WC()->customer->set_billing_postcode('6728');
    WC()->customer->set_billing_address_1('Merge Gate utca');
    WC()->customer->set_billing_address_2('');
    WC()->customer->set_billing_company('');
    WC()->customer->update_meta_data('ak_billing_house_number', '987');
    WC()->customer->update_meta_data('ak_billing_staircase', 'MG');
    WC()->customer->update_meta_data('ak_billing_floor', '9');
    WC()->customer->update_meta_data('ak_billing_door', '87');
    WC()->customer->save();
    $controller->updateSelection([
        'selection' => ['billing' => ['mode' => 'one_off']],
        'intent' => ['billing' => ['save' => true, 'set_default' => false, 'label' => 'Gyors végleges cím']],
    ]);
    $oneOffIntent = $controller->storeApiData()['selection']['billing'];
    $test->assert($oneOffIntent['mode'] === 'one_off' && $oneOffIntent['save'] === true && $oneOffIntent['label'] === 'Gyors végleges cím', 'save intent envelope preserves the current one-off selection rather than reselecting the prior default');
    $test->assert([
        'first_name' => WC()->customer->get_billing_first_name(),
        'last_name' => WC()->customer->get_billing_last_name(),
        'country' => WC()->customer->get_billing_country(),
        'postcode' => WC()->customer->get_billing_postcode(),
        'city' => WC()->customer->get_billing_city(),
        'address_1' => WC()->customer->get_billing_address_1(),
        'address_2' => WC()->customer->get_billing_address_2(),
        'company' => WC()->customer->get_billing_company(),
        'house_number' => WC()->customer->get_meta('ak_billing_house_number'),
        'staircase' => WC()->customer->get_meta('ak_billing_staircase'),
        'floor' => WC()->customer->get_meta('ak_billing_floor'),
        'door' => WC()->customer->get_meta('ak_billing_door'),
    ] === [
        'first_name' => 'Fast Final', 'last_name' => 'User', 'country' => 'HU', 'postcode' => '6728', 'city' => 'Szeged', 'address_1' => 'Merge Gate utca', 'address_2' => '', 'company' => '',
        'house_number' => '987', 'staircase' => 'MG', 'floor' => '9', 'door' => '87',
    ], 'save-intent events never project the prior saved/default address over any active one-off Woo customer field');
    $controller->updateSelection([
        'selection' => ['billing' => ['mode' => 'one_off', 'fields' => [
            'first_name' => 'Fast Final', 'last_name' => 'User', 'company' => '', 'country' => 'HU', 'state' => '', 'postcode' => '6728', 'city' => 'Szeged', 'address_1' => 'Merge Gate utca', 'address_2' => '',
            'appleklinika/house_number' => '987', 'appleklinika/staircase' => 'MG', 'appleklinika/floor' => '9', 'appleklinika/door' => '87',
            'appleklinika/company_purchase' => '', 'appleklinika/company_name' => '', 'appleklinika/tax_number' => '',
        ]]],
        'intent' => ['billing' => ['save' => true, 'set_default' => false, 'label' => 'Gyors végleges cím']],
    ]);
    $test->assert(WC()->customer->get_billing_address_2() === '' && WC()->customer->get_billing_address_1() === 'Merge Gate utca' && WC()->customer->get_billing_city() === 'Szeged' && WC()->customer->get_billing_postcode() === '6728' && WC()->customer->get_billing_company() === '' && WC()->customer->get_meta('ak_billing_staircase') === 'MG' && WC()->customer->get_meta('ak_billing_floor') === '9' && WC()->customer->get_meta('ak_billing_door') === '87', 'one-off checkout flush replaces the full saved-address physical projection, including hidden address_2, with the current empty-or-filled B values');
    $controller->updateSelection([
        'selection' => ['billing' => ['mode' => 'one_off', 'fields' => [
            'first_name' => 'Fast Final', 'last_name' => 'User', 'company' => '', 'country' => 'HU', 'state' => '', 'postcode' => '6728', 'city' => 'Szeged', 'address_1' => 'Merge Gate utca', 'address_2' => 'B épület',
            'appleklinika/house_number' => '987', 'appleklinika/staircase' => 'MG', 'appleklinika/floor' => '9', 'appleklinika/door' => '87',
            'appleklinika/company_purchase' => '', 'appleklinika/company_name' => '', 'appleklinika/tax_number' => '',
        ]]],
        'intent' => ['billing' => ['save' => true, 'set_default' => false, 'label' => 'Gyors végleges cím']],
    ]);
    $test->assert(WC()->customer->get_billing_address_2() === 'B épület', 'one-off checkout flush maps an explicitly entered B address_2 rather than retaining A or clearing B');
    WC()->customer->set_billing_address_2('');
    $controller->updateSelection([
        'selection' => ['billing' => ['mode' => 'one_off']],
        'intent' => ['billing' => ['save' => true, 'set_default' => true, 'label' => 'Gyors végleges cím új neve']],
    ]);
    $latestOneOffIntent = $controller->storeApiData()['selection']['billing'];
    $test->assert($latestOneOffIntent['mode'] === 'one_off' && $latestOneOffIntent['save'] === true && $latestOneOffIntent['set_default'] === true && $latestOneOffIntent['label'] === 'Gyors végleges cím új neve' && WC()->customer->get_billing_address_1() === 'Merge Gate utca' && WC()->customer->get_billing_address_2() === '' && WC()->customer->get_billing_company() === '' && WC()->customer->get_meta('ak_billing_staircase') === 'MG' && WC()->customer->get_meta('ak_billing_floor') === '9' && WC()->customer->get_meta('ak_billing_door') === '87', 'repeated save, label and default intent updates preserve the complete live one-off address without restoring any saved-address residue');

    $controller->updateSelection([
        'selection' => ['billing' => ['mode' => 'one_off', 'fields' => [
            'first_name' => '', 'last_name' => '', 'company' => 'Egyedi cég', 'country' => 'HU', 'state' => '', 'postcode' => '6728', 'city' => 'Szeged', 'address_1' => 'Merge Gate utca', 'address_2' => '',
            'appleklinika/house_number' => '987', 'appleklinika/staircase' => 'MG', 'appleklinika/floor' => '9', 'appleklinika/door' => '87',
            'appleklinika/company_purchase' => '1', 'appleklinika/company_name' => 'Egyedi cég', 'appleklinika/tax_number' => '12345678-1-23',
        ]]],
        'intent' => ['billing' => ['save' => false, 'set_default' => false, 'label' => '']],
    ]);
    $oneOffCompanyIdentity = WC()->session?->get('appleklinika_address_book_company_identity');
    $test->assert($oneOffCompanyIdentity === ['purchase' => true, 'name' => 'Egyedi cég', 'tax_number' => '12345678-1-23'], 'one-off company identity is kept only in the active checkout session so the next Store API request can use its authoritative additional-fields contract without saving profile data');
    $controller->clearSession();
    $test->assert(WC()->session?->get('appleklinika_address_book_company_identity') === null, 'checkout-session cleanup removes the transient one-off company identity');

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

    $service->delete($owner, $shipping->key(), $shipping->version(), ['shipping' => $companyShipping->key()]);
    $deletedRejected = false;
    try { $selector->resolve($owner, 'shipping', $shipping->key(), $shipping->version()); } catch (Throwable) { $deletedRejected = true; }
    $test->assert($deletedRejected, 'deleted address invalidates stored selection');
    $test->assert($review->status() === Address::STATUS_NEEDS_REVIEW && $foreignAddress->customerId() === $foreign, 'fixtures remain isolated');
    $controller->clearSession();
    $test->assert($controller->storeApiData()['selection'] === [], 'logout-compatible session cleanup clears saved selection state');

    $oneOffPersonal = wc_create_order(['customer_id' => $owner]);
    $finalOrders[] = $oneOffPersonal;
    $oneOffPersonal->set_address([
        'first_name' => 'Egyedi', 'last_name' => 'Személy', 'company' => '', 'country' => 'HU', 'postcode' => '1117',
        'state' => 'BU', 'city' => 'Budapest', 'address_1' => 'Mentés utca', 'address_2' => 'A épület', 'email' => 'rendeles@example.test', 'phone' => '+36 30 111 2222',
    ], 'billing');
    $oneOffPersonal->update_meta_data('_wc_billing/appleklinika/house_number', '19');
    $oneOffPersonal->update_meta_data('_wc_billing/appleklinika/staircase', 'A');
    $oneOffPersonal->update_meta_data('_wc_billing/appleklinika/floor', '2');
    $oneOffPersonal->update_meta_data('_wc_billing/appleklinika/door', '5');
    $oneOffPersonal->save();
    $countBeforePersonalSave = count($service->list($owner));
    $controller->updateSelection(['billing' => ['mode' => 'one_off', 'save' => false, 'set_default' => false, 'label' => '']]);
    $controller->updateSelection(['billing' => ['mode' => 'one_off', 'save' => true, 'set_default' => true, 'label' => 'Egyedi személyes']]);
    $latestIntent = $controller->storeApiData()['selection']['billing'];
    $test->assert($latestIntent['save'] === true && $latestIntent['set_default'] === true && $latestIntent['label'] === 'Egyedi személyes', 'latest one-off save intent replaces an earlier incomplete local intent before order progression');
    $controller->syncCheckoutOrderIntent($oneOffPersonal, new WP_REST_Request());
    $test->assert($oneOffPersonal->get_meta('_appleklinika_address_book_billing_label', true) === 'Egyedi személyes' && $oneOffPersonal->get_meta('_appleklinika_address_book_billing_save', true) === '1', 'checkout order update stores the minimal one-off save intent including its label');
    $controller->clearSession();
    $controller->finalizeOrder($oneOffPersonal);
    $savedPersonalAddresses = $service->list($owner);
    $test->assert(count($savedPersonalAddresses) === $countBeforePersonalSave + 1, 'successful personal checkout saves exactly one one-off address after the session is unavailable');
    $savedPersonal = $savedPersonalAddresses[0] ?? null;
    $savedPersonalData = $savedPersonal instanceof Address ? $savedPersonal->toArray() : [];
    $test->assert($savedPersonal instanceof Address && $savedPersonalData['label'] === 'Egyedi személyes' && $savedPersonalData['first_name'] === 'Egyedi' && $savedPersonalData['last_name'] === 'Személy', 'saved personal checkout address keeps its real identity and label');
    $test->assert(array_intersect_key($savedPersonalData, array_flip(['first_name', 'last_name', 'company_name', 'tax_number', 'country', 'state', 'postcode', 'city', 'address_1', 'address_2', 'house_number', 'staircase', 'floor', 'door'])) === [
        'first_name' => 'Egyedi', 'last_name' => 'Személy', 'company_name' => '', 'tax_number' => '', 'country' => 'HU', 'state' => 'BU', 'postcode' => '1117', 'city' => 'Budapest', 'address_1' => 'Mentés utca', 'address_2' => 'A épület', 'house_number' => '19', 'staircase' => 'A', 'floor' => '2', 'door' => '5',
    ], 'saved personal checkout address uses every final order snapshot identity and physical field rather than prior customer defaults');
    $test->assert($service->getDefault($owner, 'billing')?->key() === $savedPersonal->key(), 'explicit personal checkout default changes only the billing default');
    $controller->finalizeOrder($oneOffPersonal);
    $test->assert(count($service->list($owner)) === $countBeforePersonalSave + 1 && $oneOffPersonal->get_meta('_appleklinika_address_book_billing_consumed', true) === '1', 'repeated finalization is idempotent after the address intent is consumed');
    $test->assert($oneOffPersonal->get_meta('_appleklinika_address_book_billing_label', true) === '' && $oneOffPersonal->get_meta('_appleklinika_address_book_billing_save', true) === '', 'successful finalization removes transient one-off save intent from the order');

    $oneOffCompany = wc_create_order(['customer_id' => $owner]);
    $finalOrders[] = $oneOffCompany;
    $oneOffCompany->set_address([
        'first_name' => '', 'last_name' => '', 'company' => 'Checkout Minta Kft.', 'country' => 'HU', 'postcode' => '6721',
        'city' => 'Szeged', 'address_1' => 'Cég utca', 'address_2' => '', 'email' => 'rendeles@example.test', 'phone' => '+36 30 111 2222',
    ], 'billing');
    $oneOffCompany->update_meta_data('appleklinika_tax_number', '12345678-1-23');
    $oneOffCompany->save();
    $countBeforeCompanySave = count($service->list($owner));
    $controller->updateSelection(['billing' => ['mode' => 'one_off', 'save' => true, 'set_default' => false, 'label' => 'Egyedi céges']]);
    $controller->syncCheckoutOrderIntent($oneOffCompany, new WP_REST_Request());
    $controller->clearSession();
    $controller->finalizeOrder($oneOffCompany);
    $savedCompanyAddresses = $service->list($owner);
    $savedCompany = $savedCompanyAddresses[0] ?? null;
    $savedCompanyData = $savedCompany instanceof Address ? $savedCompany->toArray() : [];
    $test->assert(count($savedCompanyAddresses) === $countBeforeCompanySave + 1 && $savedCompany instanceof Address, 'successful company checkout saves exactly one one-off address');
    $test->assert($savedCompanyData['label'] === 'Egyedi céges' && $savedCompanyData['company_name'] === 'Checkout Minta Kft.' && $savedCompanyData['tax_number'] === '12345678-1-23' && $savedCompanyData['first_name'] === '' && $savedCompanyData['last_name'] === '', 'saved company checkout address preserves company identity without a fake person name');
    $test->assert($savedCompanyData['country'] === 'HU' && $savedCompanyData['postcode'] === '6721' && $savedCompanyData['city'] === 'Szeged' && $savedCompanyData['address_1'] === 'Cég utca', 'saved company checkout address uses the final company order snapshot physical fields');
    $test->assert($service->getDefault($owner, 'billing')?->key() === $savedPersonal->key(), 'saving a company address without default does not change the existing billing default');

    $shippingProduct = new WC_Product_Simple();
    $shippingProduct->set_name('Address-book shipping fixture');
    $shippingProduct->set_regular_price('1000');
    $shippingProduct->set_price('1000');
    $shippingProduct->set_virtual(false);
    $shippingProduct->save();
    $finalProducts[] = $shippingProduct;
    $oneOffShipping = wc_create_order(['customer_id' => $owner]);
    $finalOrders[] = $oneOffShipping;
    $oneOffShipping->add_product($shippingProduct, 1);
    $oneOffShipping->set_address([
        'first_name' => 'Szállított', 'last_name' => 'Címzett', 'company' => '', 'country' => 'HU', 'postcode' => '7630',
        'city' => 'Pécs', 'address_1' => 'Küldemény utca', 'address_2' => 'Raktár',
    ], 'shipping');
    $oneOffShipping->update_meta_data('_wc_shipping/appleklinika/house_number', '7');
    $oneOffShipping->update_meta_data('_wc_shipping/appleklinika/staircase', 'B');
    $oneOffShipping->update_meta_data('_wc_shipping/appleklinika/floor', '3');
    $oneOffShipping->update_meta_data('_wc_shipping/appleklinika/door', '8');
    $oneOffShipping->update_meta_data('_appleklinika_address_book_shipping_mode', 'one_off');
    $oneOffShipping->update_meta_data('_appleklinika_address_book_shipping_save', '1');
    $oneOffShipping->update_meta_data('_appleklinika_address_book_shipping_default', '1');
    $oneOffShipping->update_meta_data('_appleklinika_address_book_shipping_label', 'Egyedi szállítás');
    $oneOffShipping->save();
    $countBeforeShippingSave = count($service->list($owner));
    $controller->finalizeOrder($oneOffShipping);
    $savedShipping = $service->getDefault($owner, 'shipping');
    $savedShippingData = $savedShipping?->toArray() ?? [];
    $test->assert(count($service->list($owner)) === $countBeforeShippingSave + 1 && $savedShipping instanceof Address, 'successful one-off shipping checkout saves exactly one shipping-purpose address');
    $test->assert(array_intersect_key($savedShippingData, array_flip(['first_name', 'last_name', 'country', 'postcode', 'city', 'address_1', 'address_2', 'house_number', 'staircase', 'floor', 'door'])) === [
        'first_name' => 'Szállított', 'last_name' => 'Címzett', 'country' => 'HU', 'postcode' => '7630', 'city' => 'Pécs', 'address_1' => 'Küldemény utca', 'address_2' => 'Raktár', 'house_number' => '7', 'staircase' => 'B', 'floor' => '3', 'door' => '8',
    ], 'shipping-purpose save uses only the final order shipping snapshot, without billing or default-address leakage');
    $controller->finalizeOrder($oneOffShipping);
    $test->assert(count($service->list($owner)) === $countBeforeShippingSave + 1, 'repeated shipping finalization remains idempotent after the one-off save intent is consumed');

    $oneOffNoSave = wc_create_order(['customer_id' => $owner]);
    $finalOrders[] = $oneOffNoSave;
    $oneOffNoSave->set_address([
        'first_name' => 'Nem', 'last_name' => 'Mentett', 'company' => '', 'country' => 'HU', 'postcode' => '1118',
        'city' => 'Budapest', 'address_1' => 'Egyszeri utca', 'address_2' => '', 'email' => 'rendeles@example.test', 'phone' => '+36 30 111 2222',
    ], 'billing');
    $oneOffNoSave->save();
    $countBeforeNoSave = count($service->list($owner));
    $controller->updateSelection(['billing' => ['mode' => 'one_off', 'save' => false, 'set_default' => false, 'label' => '']]);
    $controller->syncCheckoutOrderIntent($oneOffNoSave, new WP_REST_Request());
    $controller->clearSession();
    $controller->finalizeOrder($oneOffNoSave);
    $test->assert(count($service->list($owner)) === $countBeforeNoSave && $oneOffNoSave->get_meta('_appleklinika_address_book_billing_mode', true) === '', 'no-save checkout creates no address and removes stale save intent');
    $test->assert(get_user_meta($owner, 'billing_email', true) === 'profil@example.test' && get_user_meta($owner, 'billing_phone', true) === '+36 30 999 0000', 'checkout address saving never changes profile contact details');

    echo 'Customer address book checkout: ' . $test->count() . " assertions\n";
} finally {
    if ($draftOrder instanceof WC_Order) {
        $draftOrder->delete(true);
    }
    foreach ($finalOrders as $finalOrder) {
        $finalOrder->delete(true);
    }
    foreach ($finalProducts as $finalProduct) {
        wp_delete_post($finalProduct->get_id(), true);
    }
    wp_set_current_user(0);
    $test->cleanupUser($owner);
    $test->cleanupUser($foreign);
}
