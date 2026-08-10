<?php

declare(strict_types=1);

final class CartCheckoutTest
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

        echo "Cart and checkout tests passed: {$this->assertions} assertions.\n";
        exit(0);
    }
}

$themeRoot = dirname(__DIR__);
$functions = file_get_contents($themeRoot . '/functions.php');
$script = file_get_contents($themeRoot . '/assets/js/frontend.js');
$css = file_get_contents($themeRoot . '/assets/css/frontend.css');
$summaryCss = file_get_contents($themeRoot . '/assets/css/checkout-sidebar.css');
$test = new CartCheckoutTest();

$test->assert(is_string($functions) && str_contains($functions, "['wp-data', 'wc-blocks-data-store']"), 'Checkout loads after the WooCommerce Blocks data store.');
$test->assert(is_string($script) && str_contains($script, "cartStore: 'wc/store/cart'") && str_contains($script, "validationStore: 'wc/store/validation'") && ! str_contains($script, 'window.wc.wcBlocksData'), 'Checkout uses supported store keys instead of directly accessing the WooCommerce global.');
$test->assert(is_string($functions) && ! str_contains($functions, 'appleklinikaCheckoutSummary'), 'Checkout no longer receives a static PHP summary snapshot.');
$test->assert(is_string($script) && str_contains($script, "blocksStore('cartStore')") && str_contains($script, "blocksSelector(cartStore, 'getCartData', null)"), 'Checkout summary reads the current WooCommerce Blocks cart state.');
$test->assert(is_string($script) && str_contains($script, 'window.wp.data.subscribe(scheduleCheckoutSummarySync)'), 'Checkout summary refreshes when WooCommerce Blocks state changes.');
$test->assert(is_string($script) && str_contains($script, 'function decodeHtmlEntities(value)') && str_contains($script, 'escapeHtml(decodeHtmlEntities(item.name))'), 'Checkout summary renders an encoded product name once as customer-facing text while preserving HTML escaping.');
$test->assert(is_string($script) && str_contains($script, 'Számlázási cím') && str_contains($script, 'Szállítási cím') && str_contains($script, 'Szállítási mód') && str_contains($script, 'Fizetési mód'), 'Checkout summary presents the selected fulfilment data.');
$test->assert(is_string($script) && str_contains($script, 'function selectedShippingMethod(cart)') && str_contains($script, "selectedOption.querySelector('.wc-block-components-radio-control__label')") && ! str_contains($script, "return 'Ingyenes szállítás'"), 'Checkout summary reads the selected shipping method primary label without hard-coding a carrier or duplicate secondary price label.');
$test->assert(is_string($script) && str_contains($script, 'discount > 0') && str_contains($script, 'total_shipping') && str_contains($script, 'total_tax') && str_contains($script, 'total_price'), 'Checkout summary displays authoritative discount, shipping, tax and total values.');
$test->assert(is_string($script) && str_contains($script, 'checkoutValidationApi') && str_contains($script, 'showAllValidationErrors') && str_contains($script, 'getValidationErrors'), 'Stepper uses WooCommerce Blocks validation state before moving forward.');
$test->assert(is_string($script) && str_contains($script, 'setActiveStep(Math.min(requestedStep, activeStep + 1))'), 'Stepper cannot skip an unchecked intermediate checkout step.');
$test->assert(is_string($script) && str_contains($script, 'focusValidationError') && str_contains($script, "document.getElementById(matching[0].key)"), 'Stepper returns focus to the first invalid Block field.');
$test->assert(is_string($script) && str_contains($script, 'Válassz fizetési módot') && ! str_contains($script, 'Nincs elérhető fizetési mód'), 'Payment summary never reports a false unavailable-payment state.');
$test->assert(is_string($script) && str_contains($script, 'initCheckoutPaymentAvailabilityGuard') && str_contains($script, "getAvailablePaymentMethods") && str_contains($script, 'clearOnlyStaleUnavailablePaymentAnnouncement'), 'Checkout removes only a stale unavailable-payment announcement when the WooCommerce payment state has an available method.');
$test->assert(is_string($script) && str_contains($script, "if (!hasAvailablePaymentMethod())") && str_contains($script, 'unavailableMessage'), 'Checkout retains WooCommerce\'s genuine unavailable-payment announcement when no method is available.');
$test->assert(is_string($script) && ! str_contains($script, "availablePaymentMethod() === 'bacs'"), 'Payment availability is not hard-coded to the local bank-transfer gateway.');
$test->assert(is_string($functions) && str_contains($functions, "add_filter('woocommerce_countries_shipping_countries', 'appleklinika_checkout_supported_shipping_countries')"), 'Checkout shipping countries are filtered from configured coverage.');
$test->assert(is_string($functions) && str_contains($functions, 'WC_Shipping_Zones::get_zones()') && str_contains($functions, 'get_shipping_methods(true)'), 'Shipping country filtering respects enabled zone methods including Rest of World.');
$test->assert(is_string($functions) && str_contains($functions, '$order->set_billing_company($companyName);'), 'Company checkout persists the standard WooCommerce billing company field on the order.');
$test->assert(is_string($functions) && str_contains($functions, '$order->set_billing_company(\'\');'), 'Personal checkout clears the standard order billing company field.');
$test->assert(is_string($functions) && str_contains($functions, '$maximumQuantity = $product->get_max_purchase_quantity();') && str_contains($functions, 'max="<?php echo esc_attr((string) $maximumQuantity); ?>"'), 'Cart quantity input is bounded by WooCommerce product stock rules.');
$test->assert(is_string($script) && str_contains($script, 'Math.min(max, current + step)'), 'Cart quantity controls do not propose a quantity beyond the available stock.');
$test->assert(is_string($css) && str_contains($css, '@media (min-width: 901px) and (max-width: 1180px)') && str_contains($css, 'minmax(300px, 360px)'), 'Cart layout has a bounded tablet-width grid before stacking.');
$test->assert(is_string($css) && str_contains($css, 'width: 44px;') && str_contains($css, 'min-height: 44px;'), 'Cart controls and checkout step controls meet the 44 pixel touch target.');
$test->assert(is_string($css) && str_contains($css, '#contact-fields .ak-checkout-profile-save label') && str_contains($css, 'grid-template-columns: 20px minmax(0, 1fr);') && str_contains($css, '#shipping-fields > .ak-checkout-address-selector + .wc-block-components-address-form') && str_contains($css, '.wc-block-components-sidebar-layout > .ak-checkout-summary-slot') && str_contains($css, 'order: 1;'), 'Checkout contact consent and the address form keep a compact, explicit visual relationship, while the mobile summary follows the active form.');
$test->assert(is_string($script) && str_contains($script, 'function syncCheckoutAddressGridForSection(section)') && str_contains($script, "form.classList.add('ak-checkout-address-grid')"), 'Checkout applies its field-grid presentation through the real Woo address-form wrappers.');
$test->assert(is_string($css) && str_contains($css, '.wc-block-checkout__form') && str_contains($css, 'ak-checkout-step-2 .wc-block-checkout__form') && str_contains($css, '#shipping-fields .ak-checkout-address-grid'), 'Checkout uses one active form surface with scoped internal sections and a responsive shipping-address grid.');
$test->assert(is_string($summaryCss) && str_contains($summaryCss, '.ak-checkout-summary__details'), 'Checkout sidebar has a dedicated layout for dynamic fulfilment details.');

$test->finish();
