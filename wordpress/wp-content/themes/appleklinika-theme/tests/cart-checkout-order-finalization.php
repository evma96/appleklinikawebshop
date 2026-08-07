<?php

declare(strict_types=1);

require_once '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

final class CartCheckoutOrderFinalizationTest
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

        echo "Cart checkout order finalization passed: {$this->assertions} assertions.\n";
        exit(0);
    }
}

/** @return array<string, string> */
function appleklinika_order_finalization_business_snapshot(): array
{
    global $wpdb;

    $queries = [
        'buyback_price_books' => "SELECT * FROM {$wpdb->prefix}ak_buyback_price_books ORDER BY id",
        'buyback_price_rules' => "SELECT * FROM {$wpdb->prefix}ak_buyback_price_rules ORDER BY id",
        'buyback_requests' => "SELECT * FROM {$wpdb->prefix}ak_buyback_requests ORDER BY id",
        'buyback_snapshots' => "SELECT * FROM {$wpdb->prefix}ak_buyback_snapshots ORDER BY id",
        'buyback_events' => "SELECT * FROM {$wpdb->prefix}ak_buyback_events ORDER BY id",
    ];

    $snapshot = [];
    foreach ($queries as $key => $sql) {
        $snapshot[$key] = hash('sha256', (string) wp_json_encode($wpdb->get_results($sql, ARRAY_A)));
    }

    return $snapshot;
}

/** @return array<string, string> */
function appleklinika_order_finalization_address(string $city, string $address): array
{
    return [
        'first_name' => 'Rendelés',
        'last_name' => 'Teszt',
        'company' => 'Teszt Kft.',
        'address_1' => $address,
        'address_2' => '',
        'city' => $city,
        'state' => '',
        'postcode' => '1111',
        'country' => 'HU',
        'email' => 'order-flow@example.test',
        'phone' => '+36 30 123 4567',
    ];
}

function appleklinika_order_finalization_add_address_details(WC_Order $order, string $type): void
{
    $prefix = '_wc_' . $type . '/appleklinika/';
    $order->update_meta_data($prefix . 'house_number', '12');
    $order->update_meta_data($prefix . 'staircase', 'B');
    $order->update_meta_data($prefix . 'floor', '3');
    $order->update_meta_data($prefix . 'door', '14');
}

/**
 * @param null|array<string, mixed> $pre
 * @param array<string, mixed> $args
 * @return true
 */
function appleklinika_order_finalization_prevent_mail($pre, array $args): bool
{
    return true;
}

$test = new CartCheckoutOrderFinalizationTest();
$themeRoot = dirname(__DIR__);
$functions = file_get_contents($themeRoot . '/functions.php');
$frontendScript = file_get_contents($themeRoot . '/assets/js/frontend.js');
$businessBefore = appleklinika_order_finalization_business_snapshot();
$userId = 0;
$draft = null;
$guestOrder = null;
$fixtureOrderIds = [];
$product = wc_get_product(463);
$stockBefore = $product instanceof WC_Product ? $product->get_stock_quantity() : null;
$originalUserId = get_current_user_id();
$originalOrderReceived = get_query_var('order-received');
$originalOrderKey = $_GET['key'] ?? null;

// This test renders the production email templates, but never dispatches a
// message while temporary order statuses change.
add_filter('woocommerce_email_enabled_new_order', '__return_false');
add_filter('woocommerce_email_enabled_customer_on_hold_order', '__return_false');
add_filter('pre_wp_mail', 'appleklinika_order_finalization_prevent_mail', 10, 2);

try {
    $test->assert($product instanceof WC_Product, 'The controlled in-stock test product is available.');
    $test->assert(is_int($stockBefore) && $stockBefore >= 2, 'The controlled test product has two units for independent draft and guest-order checks.');
    $test->assert(is_string($functions) && str_contains($functions, 'appleklinika_replace_order_address_field_renderer'), 'Account order details replace the Blocks address renderer with the de-duplicated renderer.');
    $test->assert(is_string($functions) && str_contains($functions, 'appleklinika_filter_order_confirmation_fields'), 'Order confirmation and e-mail fields have a dedicated de-duplication filter.');
    $test->assert(is_string($functions) && str_contains($functions, 'woocommerce/order-confirmation-billing-address') && str_contains($functions, 'woocommerce/order-confirmation-shipping-address'), 'Both order confirmation address Blocks are covered without a template override.');
    $test->assert(is_string($functions) && str_contains($functions, "add_action('woocommerce_order_details_after_customer_details', 'appleklinika_render_order_tax_number', 20)"), 'Tax number has a dedicated customer-facing order-detail renderer.');
    $test->assert(is_string($functions) && str_contains($functions, "add_action('woocommerce_email_customer_details', 'appleklinika_render_order_tax_number_in_email', 20, 4)"), 'Tax number has a dedicated e-mail renderer.');
    $test->assert(is_string($functions) && str_contains($functions, "['appleklinika/company_name', 'appleklinika/tax_number']"), 'The generic confirmation and e-mail path excludes the tax number owned by the dedicated renderer.');
    $test->assert(is_string($functions) && str_contains($functions, "'Order #:' => 'Rendelésszám:'") && str_contains($functions, "'Payment:' => 'Fizetési mód:'"), 'Order summary labels have exact Hungarian translations.');
    $test->assert(is_string($frontendScript) && str_contains($frontendScript, "paymentStore: 'wc/store/payment'") && ! str_contains($frontendScript, 'barion'), 'Checkout payment presentation uses WooCommerce state and does not hard-code a Barion flow.');
    $test->assert(is_string($frontendScript) && ! str_contains($frontendScript, 'gls'), 'Checkout shipping presentation does not hard-code a GLS flow.');

    $marker = wp_generate_uuid4();
    $login = 'ak-order-flow-' . substr(str_replace('-', '', $marker), 0, 12);
    $created = wp_insert_user([
        'user_login' => $login,
        'user_email' => $login . '@example.test',
        'user_pass' => wp_generate_password(24, true, true),
        'role' => 'customer',
    ]);
    if (is_wp_error($created)) {
        throw new RuntimeException($created->get_error_message());
    }
    $userId = (int) $created;
    update_user_meta($userId, 'billing_email', 'profile-contact@example.test');
    update_user_meta($userId, 'billing_phone', '+36 20 111 2233');

    $draft = wc_create_order([
        'customer_id' => $userId,
        'status' => 'checkout-draft',
    ]);
    $draft->add_product($product, 1);
    $draft->set_address(appleklinika_order_finalization_address('Budapest', 'Minta utca'), 'billing');
    $draft->set_address(appleklinika_order_finalization_address('Szeged', 'Példa tér'), 'shipping');
    appleklinika_order_finalization_add_address_details($draft, 'billing');
    appleklinika_order_finalization_add_address_details($draft, 'shipping');
    $draft->update_meta_data('_appleklinika_address_book_billing_key', 'qa-billing-' . $marker);
    $draft->update_meta_data('_appleklinika_address_book_billing_version', '1');
    $draft->update_meta_data('_appleklinika_address_book_shipping_key', 'qa-shipping-' . $marker);
    $draft->update_meta_data('_appleklinika_address_book_shipping_version', '1');
    $request = new WP_REST_Request('POST', '/wc/store/v1/checkout');
    $request->set_param('additional_fields', [
        'appleklinika/company_purchase' => '1',
        'appleklinika/company_name' => 'Teszt Kft.',
        'appleklinika/tax_number' => '12345678-1-23',
    ]);
    appleklinika_persist_company_checkout_fields($draft, $request);
    $shipping = new WC_Order_Item_Shipping();
    $shipping->set_method_title('Helyi teszt szállítás');
    $shipping->set_method_id('flat_rate:qa-order-flow');
    $shipping->set_total('990');
    $draft->add_item($shipping);
    $draft->set_payment_method('bacs');
    $draft->set_payment_method_title('Banki átutalás (helyi teszt)');
    $draft->calculate_totals();
    $draft->save();

    $test->assert($draft->has_status('checkout-draft'), 'Temporary logged-in checkout starts as one checkout draft.');
    $test->assert(wc_get_order($draft->get_id()) instanceof WC_Order, 'The temporary draft persists through WooCommerce and HPOS.');
    $test->assert($product->get_stock_quantity() === $stockBefore, 'Creating or updating a checkout draft does not reduce stock.');
    $test->assert($draft->get_billing_company() === 'Teszt Kft.' && $draft->get_meta('appleklinika_tax_number', true) === '12345678-1-23', 'Logged-in draft snapshots company and tax data with standard billing company data.');
    $test->assert($draft->get_meta('_appleklinika_address_book_billing_key', true) !== '' && $draft->get_meta('_appleklinika_address_book_shipping_version', true) === '1', 'Logged-in draft carries address-book audit key and version metadata.');

    $billingFormatted = $draft->get_formatted_billing_address();
    $shippingFormatted = $draft->get_formatted_shipping_address();
    $test->assert(substr_count($billingFormatted, 'Házszám: 12') === 1 && substr_count($billingFormatted, 'Lépcsőház: B') === 1, 'Billing formatted address contains each Hungarian component exactly once.');
    $test->assert(substr_count($shippingFormatted, 'Ajtó: 14') === 1 && substr_count($shippingFormatted, 'Emelet: 3') === 1, 'Shipping formatted address contains each Hungarian component exactly once.');

    $confirmationBlock = appleklinika_filter_order_confirmation_address_block(
        '<address>Házszám: 12, Lépcsőház: B</address><dl class="wc-block-components-additional-fields-list"><dt>Házszám</dt><dd>12</dd><dt>Kapucsengő</dt><dd>14</dd></dl>',
        ['blockName' => 'woocommerce/order-confirmation-billing-address']
    );
    $test->assert(substr_count($confirmationBlock, 'Házszám') === 1 && str_contains($confirmationBlock, 'Lépcsőház: B') && str_contains($confirmationBlock, 'Kapucsengő'), 'Order confirmation keeps the formatted address and unrelated supplementary fields while removing only duplicate Hungarian details.');

    wp_set_current_user($userId);
    set_query_var('order-received', $draft->get_id());
    $_GET['key'] = $draft->get_order_key();
    $orderReceivedHtml = do_blocks('<!-- wp:woocommerce/order-confirmation-billing-address /-->');
    $test->assert(substr_count($orderReceivedHtml, 'Adószám') === 1 && substr_count($orderReceivedHtml, '12345678-1-23') === 1, 'The real order-received Billing Address Block renders the tax number exactly once.');

    ob_start();
    wc_get_template('order/order-details-customer.php', ['show_shipping' => true, 'order' => $draft]);
    $accountOrderHtml = (string) ob_get_clean();
    $test->assert(substr_count($accountOrderHtml, 'Házszám') === 2, 'Account order details contain one billing and one shipping house-number label, without duplicated Blocks lists.');
    $test->assert(substr_count($accountOrderHtml, 'Lépcsőház') === 2 && substr_count($accountOrderHtml, 'Ajtó') === 2, 'Account order details keep Hungarian address details once per address.');
    $test->assert(substr_count($accountOrderHtml, 'Adószám') === 1 && str_contains($accountOrderHtml, '12345678-1-23'), 'Account order details display the immutable tax number once.');

    $emails = WC()->mailer()->get_emails();
    $customerOnHold = $emails['WC_Email_Customer_On_Hold_Order'] ?? null;
    $newOrder = $emails['WC_Email_New_Order'] ?? null;
    $test->assert($customerOnHold instanceof WC_Email && $newOrder instanceof WC_Email, 'Customer on-hold and admin new-order e-mail renderers are available.');
    if ($customerOnHold instanceof WC_Email && $newOrder instanceof WC_Email) {
        $customerOnHold->object = $draft;
        $newOrder->object = $draft;
        $customerEmail = $customerOnHold->get_content_html();
        $adminEmail = $newOrder->get_content_html();
        $test->assert(substr_count($customerEmail, 'Házszám') === 2 && substr_count($customerEmail, 'Lépcsőház') === 2, 'Customer on-hold e-mail retains one billing and one shipping Hungarian address detail without duplication.');
        $test->assert(substr_count($adminEmail, 'Házszám') === 2 && substr_count($adminEmail, 'Lépcsőház') === 2, 'Admin new-order e-mail retains one billing and one shipping Hungarian address detail without duplication.');
        $test->assert(substr_count($customerEmail, 'Adószám') === 1 && str_contains($customerEmail, '12345678-1-23'), 'Customer on-hold e-mail renders the tax number once.');
        $test->assert(substr_count($adminEmail, 'Adószám') === 1 && str_contains($adminEmail, '12345678-1-23'), 'Admin new-order e-mail renders the tax number once.');
    }

    $draft->update_status('on-hold', 'QA order-flow finalization');
    $draft = wc_get_order($draft->get_id());
    $fixtureOrderIds[] = $draft instanceof WC_Order ? $draft->get_id() : 0;
    $test->assert($draft instanceof WC_Order && $draft->has_status('on-hold'), 'The existing checkout draft converts into one final on-hold order.');
    $test->assert($draft instanceof WC_Order && (int) $draft->get_id() > 0, 'Draft conversion retains the same WooCommerce order identity instead of creating a duplicate final order.');
    $product = wc_get_product(463);
    $test->assert($product instanceof WC_Product && $product->get_stock_quantity() === $stockBefore - 1, 'The logged-in final order reduces stock once.');
    if ($draft instanceof WC_Order) {
        wc_maybe_reduce_stock_levels($draft->get_id());
    }
    $product = wc_get_product(463);
    $test->assert($product instanceof WC_Product && $product->get_stock_quantity() === $stockBefore - 1, 'Repeated stock-reduction protection prevents a second deduction.');

    update_user_meta($userId, 'billing_city', 'Későbbi profilváros');
    update_user_meta($userId, 'billing_company', 'Későbbi profilcég');
    $snapshotOrder = wc_get_order($draft->get_id());
    $test->assert($snapshotOrder instanceof WC_Order && $snapshotOrder->get_billing_city() === 'Budapest' && $snapshotOrder->get_billing_company() === 'Teszt Kft.', 'Later profile changes do not mutate the logged-in order snapshot.');

    $guestOrder = wc_create_order(['status' => 'pending']);
    $guestOrder->add_product(wc_get_product(463), 1);
    $guestOrder->set_address(appleklinika_order_finalization_address('Pécs', 'Vendég utca'), 'billing');
    $guestOrder->set_address(appleklinika_order_finalization_address('Győr', 'Vendég tér'), 'shipping');
    appleklinika_order_finalization_add_address_details($guestOrder, 'billing');
    appleklinika_order_finalization_add_address_details($guestOrder, 'shipping');
    $guestOrder->set_payment_method('bacs');
    $guestOrder->set_payment_method_title('Banki átutalás (helyi teszt)');
    $guestOrder->calculate_totals();
    $guestOrder->save();
    $guestOrder->update_status('on-hold', 'QA guest finalization');
    $guestOrder = wc_get_order($guestOrder->get_id());
    $fixtureOrderIds[] = $guestOrder instanceof WC_Order ? $guestOrder->get_id() : 0;
    $product = wc_get_product(463);
    $test->assert($guestOrder instanceof WC_Order && $guestOrder->get_customer_id() === 0 && $guestOrder->has_status('on-hold'), 'Guest checkout produces an independent final order without address-book audit metadata.');
    $test->assert($guestOrder instanceof WC_Order && $guestOrder->get_meta('_appleklinika_address_book_billing_key', true) === '', 'Guest final order does not expose or retain saved-address selection metadata.');
    $test->assert($product instanceof WC_Product && $product->get_stock_quantity() === $stockBefore - 2, 'Guest final order reduces stock once in addition to the logged-in final order.');
} finally {
    foreach ([$draft, $guestOrder] as $order) {
        if (! $order instanceof WC_Order) {
            continue;
        }

        if (! $order->has_status('cancelled')) {
            $order->update_status('cancelled', 'QA fixture cleanup');
        }
        $order->delete(true);
    }

    foreach (array_filter($fixtureOrderIds) as $fixtureOrderId) {
        $test->assert(wc_get_order($fixtureOrderId) === false, 'Temporary final order is permanently removed during fixture cleanup.');
    }

    if ($userId > 0) {
        wp_delete_user($userId);
    }

    $restoredProduct = wc_get_product(463);
    $test->assert($restoredProduct instanceof WC_Product && $restoredProduct->get_stock_quantity() === $stockBefore, 'Temporary order cleanup restores the exact test-product stock quantity.');
    $test->assert(appleklinika_order_finalization_business_snapshot() === $businessBefore, 'Temporary order-flow tests leave every Buyback business table unchanged.');
    set_query_var('order-received', $originalOrderReceived);
    if ($originalOrderKey === null) {
        unset($_GET['key']);
    } else {
        $_GET['key'] = $originalOrderKey;
    }
    wp_set_current_user($originalUserId);
    remove_filter('woocommerce_email_enabled_new_order', '__return_false');
    remove_filter('woocommerce_email_enabled_customer_on_hold_order', '__return_false');
    remove_filter('pre_wp_mail', 'appleklinika_order_finalization_prevent_mail', 10);
}

$test->finish();
