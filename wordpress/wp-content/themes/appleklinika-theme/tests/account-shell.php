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
$accountOrderDisplay = file_get_contents($themeRoot . '/inc/account-order-display.php');
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
    is_string($functions) && str_contains($functions, 'appleklinika_account_order_item_image_html($record[\'first_item\'])'),
    'Return cards use the stored order item to resolve a product image or placeholder.'
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
    is_string($frontendCss) && str_contains($frontendCss, '.ak-account-order-price__row'),
    'Order-card price rows have a dedicated structured layout rule.'
);
$test->assert(
    is_string($frontendCss) && str_contains($frontendCss, '.show-password-input:focus-visible'),
    'Password visibility controls retain a visible keyboard focus state.'
);

$test->assert(
    is_string($orders) && ! str_contains($orders, 'get_formatted_order_total'),
    'Order cards do not render the ambiguous formatted-total blob.'
);
$test->assert(
    is_string($orders) && str_contains($orders, 'appleklinika_render_account_order_price_summary($order)'),
    'Order cards render the semantic price summary.'
);
$test->assert(
    is_string($functions) && str_contains($functions, 'Visszatérített összeg'),
    'Refund cards label the refunded amount explicitly.'
);
$test->assert(
    is_string($functions) && str_contains($functions, 'Visszatérítés dátuma'),
    'Refund cards label the actual refund date explicitly.'
);
$test->assert(
    is_string($functions) && str_contains($functions, "'extra_count' => max(0, count(\$items) - 1)") && str_contains($functions, "' további termék'"),
    'Refund cards preserve the first stored item and expose a Hungarian additional-item count.'
);
$test->assert(
    is_string($functions) && str_contains($functions, "(int) \$item->get_variation_id()") && str_contains($functions, "wc_placeholder_img('woocommerce_thumbnail', ['alt' => \$storedName])"),
    'Order-item image resolution prefers a variation and safely falls back to the WooCommerce placeholder.'
);
$test->assert(
    is_string($functions)
        && ! str_contains($functions, 'csomag beérkezett')
        && ! str_contains($functions, 'visszaküldés jóváhagyva')
        && ! str_contains($functions, 'bevizsgálás alatt'),
    'Refund cards do not invent an unrecorded physical-return workflow state.'
);
$test->assert(
    is_string($functions) && str_contains($functions, '$order->get_order_number()') && str_contains($functions, '$order->get_view_order_url()'),
    'Refund-card order numbers and details links remain data-driven per order.'
);

if (! is_string($accountOrderDisplay)) {
    $test->assert(false, 'The account order-display helper is available.');
} else {
    require $themeRoot . '/inc/account-order-display.php';

    $normal = appleklinika_account_order_price_rows(14990.0);
    $fullRefund = appleklinika_account_order_price_rows(1599990.0, 1599990.0);
    $partialRefund = appleklinika_account_order_price_rows(50000.0, 12500.0);
    $multipleRefunds = appleklinika_account_order_price_rows(50000.0, 30000.0);
    $discounted = appleklinika_account_order_price_rows(40000.0, 0.0, 50000.0);
    $zero = appleklinika_account_order_price_rows(0.0);

    $test->assert($normal['state'] === 'standard' && $normal['rows'][0]['label'] === 'Rendelés összege' && $normal['rows'][0]['amount'] === 14990.0, 'Normal orders expose one labelled final amount.');
    $test->assert($fullRefund['state'] === 'refunded' && $fullRefund['rows'][1]['amount'] === 1599990.0 && $fullRefund['rows'][1]['emphasis'] === 'primary' && $fullRefund['rows'][2]['amount'] === 0.0 && $fullRefund['rows'][2]['emphasis'] === 'secondary', 'Full refunds expose a prominent refunded amount and a labelled secondary zero remaining amount.');
    $test->assert($partialRefund['state'] === 'refunded' && $partialRefund['rows'][1]['amount'] === 12500.0 && $partialRefund['rows'][2]['amount'] === 37500.0, 'Partial refunds expose refunded and remaining amounts independently.');
    $test->assert($multipleRefunds['state'] === 'refunded' && $multipleRefunds['rows'][1]['amount'] === 30000.0 && $multipleRefunds['rows'][2]['amount'] === 20000.0, 'The display helper accepts the order-level total from multiple refunds.');
    $test->assert($discounted['state'] === 'discounted' && $discounted['rows'][0]['label'] === 'Eredeti rendelési összeg' && $discounted['rows'][1]['label'] === 'Fizetett rendelési összeg', 'Discounted orders distinguish original and paid totals.');
    $test->assert($zero['state'] === 'standard' && $zero['rows'][0]['amount'] === 0.0, 'A non-refunded zero-total order remains a normal zero-total order.');
}

$test->finish();
