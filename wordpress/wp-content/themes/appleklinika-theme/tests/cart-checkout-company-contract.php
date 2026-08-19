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

$missingCompanyErrors = new WP_Error();
appleklinika_validate_company_checkout_fields($missingCompanyErrors, [
    'appleklinika/company_purchase' => '1',
    'appleklinika/company_name' => '',
    'appleklinika/tax_number' => '',
], 'other');
$test->assert(
    in_array('appleklinika_company_name_required', $missingCompanyErrors->get_error_codes(), true)
    && in_array('appleklinika_tax_number_required', $missingCompanyErrors->get_error_codes(), true),
    'The authoritative server validation rejects an active company checkout without its name and tax number.'
);

$validCompanyErrors = new WP_Error();
appleklinika_validate_company_checkout_fields($validCompanyErrors, [
    'appleklinika/company_purchase' => '1',
    'appleklinika/company_name' => 'Kosár QA Kft.',
    'appleklinika/tax_number' => '12345678-1-23',
], 'other');
$test->assert($validCompanyErrors->has_errors() === false, 'The authoritative server validation accepts a corrected active company checkout.');

try {
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
