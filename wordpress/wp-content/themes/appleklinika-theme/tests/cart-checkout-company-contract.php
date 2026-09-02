<?php

declare(strict_types=1);

require_once '/var/www/html/wp-load.php';

final class CartCheckoutCompanyContractTest
{
    private int $assertions = 0;

    /** @var list<string> */
    private array $failures = [];

    public function assert(bool $condition, string $message): void
    {
        ++$this->assertions;

        if (! $condition) {
            $this->failures[] = $message;
        }
    }

    public function finish(): never
    {
        if ($this->failures !== []) {
            foreach ($this->failures as $failure) {
                fwrite(STDERR, "FAIL: {$failure}\n");
            }

            exit(1);
        }

        echo "Cart checkout company contract passed: {$this->assertions} assertions.\n";
        exit(0);
    }
}

$test = new CartCheckoutCompanyContractTest();
$frontendScript = file_get_contents(dirname(__DIR__) . '/assets/js/frontend.js');
$companyOrder = null;
$personalOrder = null;
$originalBillingCompany = WC()->customer?->get_billing_company();

try {
    $test->assert(
        apply_filters('pre_option_woocommerce_checkout_company_field', false) === 'optional',
        'The standard WooCommerce company address field remains available to Checkout Blocks as the canonical guest Store API billing identity signal.'
    );
    $test->assert(
        is_string($frontendScript)
        && str_contains($frontendScript, "window.wp.data.select('wc/store/cart').getCustomerData()")
        && str_contains($frontendScript, "window.wp.data.dispatch('wc/store/cart')")
        && str_contains($frontendScript, 'cartStore.setBillingAddress(nextBillingAddress);')
        && str_contains($frontendScript, 'cartStore.updateCustomerData({ billing_address: nextBillingAddress }, true, false)')
        && str_contains($frontendScript, "var value = enabled ? (hasUserInput ? normalizeText(inputValue) : (stateValue || visibleValue)) : '';")
        && str_contains($frontendScript, 'setCheckoutFieldValue(companyField.input, value);')
        && str_contains($frontendScript, 'companyField.input.dataset.akBillingCompanyNameSyncBound')
        && str_contains($frontendScript, 'syncBillingCompanyValue(billingSection, { input: event.currentTarget }, true, event.currentTarget.value);')
        && str_contains($frontendScript, 'syncBillingCompanyValue(billingSection, { input: event.currentTarget }, true, event.currentTarget.value, true);')
        && ! str_contains($frontendScript, 'billingSection.dataset.akBillingCompanyNameSyncBound'),
        'Guest company synchronization reads and updates the authoritative Woo Blocks billing state, restores a remounted visible field from that state, and binds the current remounted company input exactly once instead of treating its replaced parent section as already bound.'
    );

    WC()->session->set('appleklinika_address_book_company_identity', [
        'purchase' => true,
        'name' => 'Egyszeri QA Kft.',
        'tax_number' => '12345678-1-23',
    ]);
    $oneOffRequest = new WP_REST_Request('PUT', '/wc/store/v1/checkout');
    appleklinika_capture_checkout_company_identity(null, [], $oneOffRequest);
    $oneOffFields = (array) $oneOffRequest->get_param('additional_fields');
    $test->assert($oneOffFields === [
        'appleklinika/company_purchase' => true,
        'appleklinika/company_name' => 'Egyszeri QA Kft.',
        'appleklinika/tax_number' => '12345678-1-23',
    ], 'A one-off company identity from the active checkout session is projected into the authoritative Store API additional-fields request without relying on customer profile metadata.');
    WC()->session->set('appleklinika_address_book_company_identity', null);

    $validCompanyFields = [
        'appleklinika/company_purchase' => true,
        'appleklinika/company_name' => 'Egyszeri QA Kft.',
        'appleklinika/tax_number' => '12345678-1-23',
    ];
    $validCompanyErrors = new WP_Error();
    appleklinika_validate_company_checkout_fields($validCompanyErrors, $validCompanyFields, 'other');
    $GLOBALS['appleklinika_checkout_company_identity'] = true;
    appleklinika_validate_checkout_address_identity($validCompanyErrors, ['first_name' => '', 'last_name' => ''], 'billing');
    appleklinika_validate_checkout_address_identity($validCompanyErrors, ['first_name' => 'Átvevő', 'last_name' => 'Minta'], 'shipping');
    $test->assert($validCompanyErrors->get_error_codes() === [], 'A current one-off company identity with empty billing personal names and valid saved or one-off shipping recipient fields passes the server validation contract.');

    $stepThreeRevalidationRequest = new WP_REST_Request('POST', '/wc/store/v1/cart/update-customer');
    $stepThreeRevalidationRequest->set_param('billing_address', [
        'company' => 'Egyszeri QA Kft.',
        'first_name' => '',
        'last_name' => '',
    ]);
    appleklinika_capture_checkout_company_identity(null, [], $stepThreeRevalidationRequest);
    $stepThreeCompanyErrors = new WP_Error();
    appleklinika_validate_checkout_address_identity($stepThreeCompanyErrors, ['first_name' => '', 'last_name' => ''], 'billing');
    $test->assert($stepThreeCompanyErrors->get_error_codes() === [], 'A Step 3 cart customer revalidation keeps empty inactive personal billing names valid when the current billing address carries the active company identity.');

    $stepThreeRepeatRequest = new WP_REST_Request('POST', '/wc/store/v1/cart/update-customer');
    $stepThreeRepeatRequest->set_param('billing_address', [
        'company' => 'Egyszeri QA Kft.',
        'first_name' => '',
        'last_name' => '',
    ]);
    appleklinika_capture_checkout_company_identity(null, [], $stepThreeRepeatRequest);
    $stepThreeRepeatErrors = new WP_Error();
    appleklinika_validate_checkout_address_identity($stepThreeRepeatErrors, ['first_name' => '', 'last_name' => ''], 'billing');
    $test->assert($stepThreeRepeatErrors->get_error_codes() === [], 'A Step 3 → Step 2 → Step 3 revalidation does not recreate inactive personal billing-name errors.');

    WC()->session->set('appleklinika_address_book_company_identity', null);
    WC()->customer?->set_billing_company('');
    $guestMissingCompanySignalRequest = new WP_REST_Request('POST', '/wc/store/v1/cart/update-customer');
    $guestMissingCompanySignalRequest->set_param('billing_address', [
        'first_name' => '',
        'last_name' => '',
        'address_1' => 'Vendég QA utca 1.',
        'city' => 'Szeged',
        'postcode' => '6720',
        'country' => 'HU',
    ]);
    appleklinika_capture_checkout_company_identity(null, [], $guestMissingCompanySignalRequest);
    $guestMissingCompanySignalErrors = new WP_Error();
    appleklinika_validate_checkout_address_identity($guestMissingCompanySignalErrors, ['first_name' => '', 'last_name' => ''], 'billing');
    $test->assert(
        in_array('appleklinika_billing_first_name_required', $guestMissingCompanySignalErrors->get_error_codes(), true)
        && in_array('appleklinika_billing_last_name_required', $guestMissingCompanySignalErrors->get_error_codes(), true),
        'The historical guest request shape without a standard billing company signal still demonstrates the exact personal-name failure that Blocks produced while the field was hidden.'
    );

    $guestCompanyStoreApiRequest = new WP_REST_Request('POST', '/wc/store/v1/cart/update-customer');
    $guestCompanyStoreApiRequest->set_param('billing_address', [
        'first_name' => '',
        'last_name' => '',
        'company' => 'Vendég QA Kft.',
        'address_1' => 'Vendég QA utca 1.',
        'city' => 'Szeged',
        'postcode' => '6720',
        'country' => 'HU',
    ]);
    appleklinika_capture_checkout_company_identity(null, [], $guestCompanyStoreApiRequest);
    $guestCompanyStoreApiErrors = new WP_Error();
    appleklinika_validate_checkout_address_identity($guestCompanyStoreApiErrors, ['first_name' => '', 'last_name' => ''], 'billing');
    $test->assert($guestCompanyStoreApiErrors->get_error_codes() === [], 'A fresh guest company cart update with the canonical Woo billing company signal keeps inactive personal billing names valid without relying on saved customer or address-book session state.');

    $guestCompanySecondRequest = new WP_REST_Request('POST', '/wc/store/v1/cart/update-customer');
    $guestCompanySecondRequest->set_param('billing_address', [
        'first_name' => '',
        'last_name' => '',
        'company' => 'Vendég QA Kft.',
        'address_1' => 'Vendég QA utca 1.',
        'city' => 'Szeged',
        'postcode' => '6720',
        'country' => 'HU',
    ]);
    appleklinika_capture_checkout_company_identity(null, [], $guestCompanySecondRequest);
    $guestCompanySecondErrors = new WP_Error();
    appleklinika_validate_checkout_address_identity($guestCompanySecondErrors, ['first_name' => '', 'last_name' => ''], 'billing');
    $test->assert($guestCompanySecondErrors->get_error_codes() === [], 'A second consecutive guest company cart update retains the same canonical billing company signal after the simulated Blocks rerender.');

    $guestCompanyUpdatedRequest = new WP_REST_Request('POST', '/wc/store/v1/cart/update-customer');
    $guestCompanyUpdatedRequest->set_param('billing_address', [
        'first_name' => '',
        'last_name' => '',
        'company' => 'Vendég Frissített QA Kft.',
        'address_1' => 'Vendég QA utca 1.',
        'city' => 'Szeged',
        'postcode' => '6720',
        'country' => 'HU',
    ]);
    appleklinika_capture_checkout_company_identity(null, [], $guestCompanyUpdatedRequest);
    $guestCompanyUpdatedErrors = new WP_Error();
    appleklinika_validate_checkout_address_identity($guestCompanyUpdatedErrors, ['first_name' => '', 'last_name' => ''], 'billing');
    $test->assert($guestCompanyUpdatedErrors->get_error_codes() === [], 'A later guest company cart update accepts the newest company value rather than restoring a stale first value.');

    $guestPersonalStoreApiRequest = new WP_REST_Request('POST', '/wc/store/v1/cart/update-customer');
    $guestPersonalStoreApiRequest->set_param('billing_address', [
        'first_name' => '',
        'last_name' => '',
        'company' => '',
        'address_1' => 'Vendég QA utca 1.',
        'city' => 'Szeged',
        'postcode' => '6720',
        'country' => 'HU',
    ]);
    appleklinika_capture_checkout_company_identity(null, [], $guestPersonalStoreApiRequest);
    $guestPersonalStoreApiErrors = new WP_Error();
    appleklinika_validate_checkout_address_identity($guestPersonalStoreApiErrors, ['first_name' => '', 'last_name' => ''], 'billing');
    $test->assert(
        in_array('appleklinika_billing_first_name_required', $guestPersonalStoreApiErrors->get_error_codes(), true)
        && in_array('appleklinika_billing_last_name_required', $guestPersonalStoreApiErrors->get_error_codes(), true),
        'The same guest switching from company to personal billing must again supply first and last name.'
    );

    $guestCompanyAgainStoreApiRequest = new WP_REST_Request('POST', '/wc/store/v1/cart/update-customer');
    $guestCompanyAgainStoreApiRequest->set_param('billing_address', [
        'first_name' => '',
        'last_name' => '',
        'company' => 'Vendég QA Kft.',
        'address_1' => 'Vendég QA utca 1.',
        'city' => 'Szeged',
        'postcode' => '6720',
        'country' => 'HU',
    ]);
    appleklinika_capture_checkout_company_identity(null, [], $guestCompanyAgainStoreApiRequest);
    $guestCompanyAgainStoreApiErrors = new WP_Error();
    appleklinika_validate_checkout_address_identity($guestCompanyAgainStoreApiErrors, ['first_name' => '', 'last_name' => ''], 'billing');
    $test->assert($guestCompanyAgainStoreApiErrors->get_error_codes() === [], 'Switching the same guest back to company billing restores the canonical billing-company signal and clears personal-name validation.');

    WC()->session->set('appleklinika_address_book_company_identity', null);
    WC()->customer?->set_billing_company('Mentett QA Kft.');
    $savedCompanyStepThreeRequest = new WP_REST_Request('POST', '/wc/store/v1/cart/update-customer');
    appleklinika_capture_checkout_company_identity(null, [], $savedCompanyStepThreeRequest);
    $savedCompanyStepThreeErrors = new WP_Error();
    appleklinika_validate_checkout_address_identity($savedCompanyStepThreeErrors, ['first_name' => '', 'last_name' => ''], 'billing');
    $test->assert($savedCompanyStepThreeErrors->get_error_codes() === [], 'A selected saved company billing address keeps inactive personal names out of the Step 3 cart customer revalidation even when the update sends no order-level fields.');

    $missingCompanyErrors = new WP_Error();
    appleklinika_validate_company_checkout_fields($missingCompanyErrors, [
        'appleklinika/company_purchase' => true,
        'appleklinika/company_name' => '',
        'appleklinika/tax_number' => '12345678-1-23',
    ], 'other');
    $test->assert(in_array('appleklinika_company_name_required', $missingCompanyErrors->get_error_codes(), true), 'A company checkout without a legal company name remains rejected server-side.');

    $invalidTaxErrors = new WP_Error();
    appleklinika_validate_company_checkout_fields($invalidTaxErrors, [
        'appleklinika/company_purchase' => true,
        'appleklinika/company_name' => 'Egyszeri QA Kft.',
        'appleklinika/tax_number' => '12345678-1-2',
    ], 'other');
    $test->assert(in_array('appleklinika_tax_number_invalid', $invalidTaxErrors->get_error_codes(), true), 'A company checkout with an invalid Hungarian tax number remains rejected server-side.');

    $personalStepThreeRequest = new WP_REST_Request('POST', '/wc/store/v1/cart/update-customer');
    $personalStepThreeRequest->set_param('billing_address', [
        'company' => '',
        'first_name' => '',
        'last_name' => '',
    ]);
    appleklinika_capture_checkout_company_identity(null, [], $personalStepThreeRequest);
    $personalIdentityErrors = new WP_Error();
    appleklinika_validate_checkout_address_identity($personalIdentityErrors, ['first_name' => '', 'last_name' => ''], 'billing');
    $test->assert(in_array('appleklinika_billing_first_name_required', $personalIdentityErrors->get_error_codes(), true) && in_array('appleklinika_billing_last_name_required', $personalIdentityErrors->get_error_codes(), true), 'A personal one-off billing address without its active personal name fields remains rejected server-side.');
    unset($GLOBALS['appleklinika_checkout_company_identity']);

    $companyOrder = wc_create_order();
    $companyRequest = new WP_REST_Request('POST', '/wc/store/v1/checkout');
    $companyRequest->set_param('additional_fields', [
        'appleklinika/company_purchase' => '1',
        'appleklinika/company_name' => 'Kosár QA Kft.',
        'appleklinika/tax_number' => '12345678-1-23',
    ]);
    appleklinika_persist_company_checkout_fields($companyOrder, $companyRequest);
    $companyOrder->save();

    $storedCompanyOrder = wc_get_order($companyOrder->get_id());
    $test->assert($storedCompanyOrder instanceof WC_Order, 'The temporary company order is readable through WooCommerce.');
    $test->assert($storedCompanyOrder instanceof WC_Order && $storedCompanyOrder->get_billing_company() === 'Kosár QA Kft.', 'Company checkout persists the standard billing_company order field.');
    $test->assert($storedCompanyOrder instanceof WC_Order && $storedCompanyOrder->get_meta('appleklinika_company_name', true) === 'Kosár QA Kft.', 'Company checkout preserves the Apple Klinika company metadata.');

    $personalOrder = wc_create_order();
    $personalOrder->set_billing_company('Korábbi cég');
    $personalRequest = new WP_REST_Request('POST', '/wc/store/v1/checkout');
    $personalRequest->set_param('additional_fields', [
        'appleklinika/company_purchase' => '0',
    ]);
    appleklinika_persist_company_checkout_fields($personalOrder, $personalRequest);
    $personalOrder->save();

    $storedPersonalOrder = wc_get_order($personalOrder->get_id());
    $test->assert($storedPersonalOrder instanceof WC_Order && $storedPersonalOrder->get_billing_company() === '', 'Personal checkout clears a stale standard billing_company value.');
    $test->assert($storedPersonalOrder instanceof WC_Order && $storedPersonalOrder->get_meta('appleklinika_company_purchase', true) === '', 'Personal checkout removes company-only order metadata.');
} finally {
    if (WC()->customer !== null && $originalBillingCompany !== null) {
        WC()->customer->set_billing_company($originalBillingCompany);
    }

    if ($companyOrder instanceof WC_Order) {
        $companyOrder->delete(true);
    }

    if ($personalOrder instanceof WC_Order) {
        $personalOrder->delete(true);
    }
}

$test->finish();
