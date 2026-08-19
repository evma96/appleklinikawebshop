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
$companyOrder = null;
$personalOrder = null;

try {
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

    $GLOBALS['appleklinika_checkout_company_identity'] = false;
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
    if ($companyOrder instanceof WC_Order) {
        $companyOrder->delete(true);
    }

    if ($personalOrder instanceof WC_Order) {
        $personalOrder->delete(true);
    }
}

$test->finish();
