<?php

declare(strict_types=1);

final class AccountShellTest
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

        echo "Theme account-shell tests passed: {$this->assertions} assertions.\n";
        exit(0);
    }
}

$themeRoot = dirname(__DIR__);
$navigation = file_get_contents($themeRoot . '/woocommerce/myaccount/navigation.php');
$account = file_get_contents($themeRoot . '/woocommerce/myaccount/my-account.php');
$orderCustomerTemplate = file_get_contents(dirname($themeRoot, 2) . '/plugins/woocommerce/templates/order/order-details-customer.php');
$test = new AccountShellTest();

$test->assert(
    is_string($navigation) && str_contains($navigation, 'woocommerce-MyAccount-navigation ak-account-sidebar'),
    'The account navigation retains the shared sidebar shell hook.'
);
$test->assert(
    is_string($navigation) && str_contains($navigation, 'ak-account-sidebar__profile-text'),
    'The sidebar profile exposes a dedicated shrinkable text wrapper.'
);
$test->assert(
    is_string($account) && str_contains($account, 'ak-account-layout'),
    'The account template retains the common account layout wrapper.'
);
$test->assert(
    is_string($account) && str_contains($account, 'woocommerce-MyAccount-content ak-account-content ak-account-card'),
    'The account template retains the common account content wrapper.'
);
$test->assert(
    is_string($orderCustomerTemplate) && str_contains($orderCustomerTemplate, 'woocommerce-columns--addresses'),
    'The WooCommerce order-details template retains the shared address wrapper used by the responsive layout.'
);
$test->assert(
    is_string($orderCustomerTemplate) && str_contains($orderCustomerTemplate, 'woocommerce-column--billing-address'),
    'The shared order-details template retains its billing-address column.'
);
$test->assert(
    is_string($orderCustomerTemplate) && str_contains($orderCustomerTemplate, 'woocommerce-column--shipping-address'),
    'The shared order-details template retains its shipping-address column.'
);

$test->finish();
