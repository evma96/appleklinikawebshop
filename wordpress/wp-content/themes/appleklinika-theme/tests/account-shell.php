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
$functions = file_get_contents($themeRoot . '/functions.php');
$orders = file_get_contents($themeRoot . '/woocommerce/myaccount/orders.php');
$accountForm = file_get_contents($themeRoot . '/woocommerce/myaccount/form-edit-account.php');
$frontendCss = file_get_contents($themeRoot . '/assets/css/frontend.css');
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
$test->assert(
    is_string($orders) && str_contains($orders, 'appleklinika_account_view_order_aria_label'),
    'Order-list view actions use the customer-facing Hungarian accessible label.'
);
$test->assert(
    is_string($functions) && str_contains($functions, "'on-hold' => 'Fizetés egyeztetés alatt'"),
    'The on-hold status has one consistent customer-facing Hungarian label.'
);
$test->assert(
    is_string($functions) && str_contains($functions, "add_filter('woocommerce_order_details_status', 'appleklinika_account_order_details_status'"),
    'Order-detail status copy is sourced from the shared account-status label.'
);
$test->assert(
    is_string($functions) && str_contains($functions, "add_filter('woocommerce_order_shipping_to_display', 'appleklinika_account_order_shipping_to_display'"),
    'Historical free-shipping labels are normalized only in the customer account.'
);
$test->assert(
    is_string($functions) && str_contains($functions, 'appleklinika_account_device_icon()'),
    'The Buyback account card uses a neutral device icon rather than abbreviated text.'
);
$test->assert(
    is_string($functions) && str_contains($functions, 'ak-account-record-card--without-thumb'),
    'Return cards explicitly use the no-thumbnail card variant.'
);
$test->assert(
    is_string($accountForm) && str_contains($accountForm, "'describedby' => 'account_display_name_description'"),
    'The display-name helper is associated with its form control.'
);
$test->assert(
    is_string($accountForm) && str_contains($accountForm, 'aria-describedby="'),
    'Account form fields render the supplied accessible helper reference.'
);
$test->assert(
    is_string($frontendCss) && str_contains($frontendCss, '.ak-account-record-card--without-thumb'),
    'The no-thumbnail return-card layout has a dedicated responsive grid rule.'
);
$test->assert(
    is_string($frontendCss) && str_contains($frontendCss, '.show-password-input:focus-visible'),
    'Password visibility controls retain a visible keyboard focus state.'
);

$test->finish();
