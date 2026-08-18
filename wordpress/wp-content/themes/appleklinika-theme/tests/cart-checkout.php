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
$test->assert(is_string($script) && str_contains($script, "var addressDetailLabels = ['Házszám', 'Lépcsőház', 'Emelet', 'Ajtó'];"), 'Checkout keeps the Hungarian address-detail controls in the natural house number, staircase, floor and door order.');
$test->assert(is_string($script) && str_contains($script, 'class="ak-checkout-summary__qty" aria-label="Mennyiség"'), 'Checkout summary renders quantity as its own accessible product-row column.');
$test->assert(is_string($script) && str_contains($script, 'Számlázási cím') && str_contains($script, 'Szállítási cím') && str_contains($script, 'Szállítási mód') && str_contains($script, 'Fizetési mód'), 'Checkout summary presents the selected fulfilment data.');
$test->assert(is_string($script) && str_contains($script, 'function selectedShippingMethod(cart)') && str_contains($script, "selectedOption.querySelector('.wc-block-components-radio-control__label')") && ! str_contains($script, "return 'Ingyenes szállítás'"), 'Checkout summary reads the selected shipping method primary label without hard-coding a carrier or duplicate secondary price label.');
$test->assert(is_string($script) && str_contains($script, 'discount > 0') && str_contains($script, 'total_shipping') && str_contains($script, 'total_tax') && str_contains($script, 'total_price'), 'Checkout summary displays authoritative discount, shipping, tax and total values.');
$test->assert(is_string($script) && str_contains($script, 'checkoutValidationApi') && str_contains($script, 'showAllValidationErrors') && str_contains($script, 'getValidationErrors'), 'Stepper uses WooCommerce Blocks validation state before moving forward.');
$test->assert(is_string($script) && str_contains($script, 'clearInactiveBillingValidationErrors') && str_contains($script, 'clearValidationError') && str_contains($script, 'appleklinika:checkout-company-mode-changed'), 'Checkout clears inactive personal or company validation errors through the supported Blocks validation store.');
$test->assert(is_string($script) && str_contains($script, 'billingPersonalNameValidation') && str_contains($script, 'companyBillingValidation'), 'Checkout keeps personal-name and company-tax validation cleanup scoped to the currently inactive billing identity.');
$test->assert(is_string($script) && str_contains($script, 'setActiveStep(Math.min(requestedStep, activeStep + 1))'), 'Stepper cannot skip an unchecked intermediate checkout step.');
$test->assert(is_string($script) && str_contains($script, 'focusValidationError') && str_contains($script, "document.getElementById(matching[0].key)"), 'Stepper returns focus to the first invalid Block field.');
$test->assert(is_string($script) && str_contains($script, 'Válassz fizetési módot') && ! str_contains($script, 'Nincs elérhető fizetési mód'), 'Payment summary never reports a false unavailable-payment state.');
$test->assert(is_string($script) && str_contains($script, 'initCheckoutPaymentAvailabilityGuard') && str_contains($script, "getAvailablePaymentMethods") && str_contains($script, 'clearOnlyStaleUnavailablePaymentAnnouncement'), 'Checkout removes only a stale unavailable-payment announcement when the WooCommerce payment state has an available method.');
$test->assert(is_string($script) && str_contains($script, "if (!hasAvailablePaymentMethod())") && str_contains($script, 'unavailableMessage'), 'Checkout retains WooCommerce\'s genuine unavailable-payment announcement when no method is available.');
$test->assert(is_string($script) && ! str_contains($script, "availablePaymentMethod() === 'bacs'"), 'Payment availability is not hard-coded to the local bank-transfer gateway.');
$test->assert(is_string($script) && str_contains($script, 'function positionStep3Controls(targets)') && str_contains($script, 'lastMethodSection.parentNode.insertBefore(controls, lastMethodSection.nextSibling)'), 'Step 3 keeps its navigation footer after the last rendered shipping or payment section, even when WooCommerce mounts methods asynchronously.');
$test->assert(is_string($script) && str_contains($script, 'function syncCheckoutFinalReview()') && str_contains($script, 'Rendelés áttekintése') && str_contains($script, 'Ellenőrizd az adatokat a véglegesítés előtt.') && ! str_contains($script, 'ak-checkout-final-review__eyebrow'), 'Step 4 uses one concise final-review title without duplicating the step number.');
$test->assert(is_string($script) && str_contains($script, "terms.parentNode.children") && str_contains($script, "child.classList.contains('ak-checkout-final-review-slot')") && str_contains($script, 'reviewSlot.innerHTML = html') && ! str_contains($script, "document.querySelector('.ak-checkout-final-review')"), 'Step 4 always owns one dedicated mount slot and replaces its contents instead of selecting and nesting an existing review.');
$test->assert(is_string($script) && str_contains($script, 'function syncCheckoutFinalReview()') && str_contains($script, 'return reviewSlot;') && ! str_contains($script, 'return review;'), 'Step 4 returns its dedicated review slot so refreshes do not throw and interrupt the checkout step lifecycle.');
$test->assert(is_string($script) && str_contains($script, 'function finalReviewTimelineItem') && str_contains($script, "finalReviewTimelineItem('contact'") && str_contains($script, "finalReviewTimelineItem('delivery'") && str_contains($script, "finalReviewTimelineItem('billing'") && str_contains($script, "finalReviewTimelineItem('payment'") && str_contains($script, 'setActiveStep(Number(button.getAttribute'), 'Step 4 presents contact, delivery, billing and payment in one semantic review timeline with existing step navigation.');
$test->assert(is_string($script) && str_contains($script, "'order-appleklinika-company_name'") && str_contains($script, "'Adószám: ' + address.tax_number") && str_contains($script, 'var recipient = address.company'), 'Step 4 presents company billing identity and tax number without inventing personal billing names.');
$test->assert(is_string($script) && str_contains($script, 'function currentShippingReview()') && str_contains($script, 'function currentPaymentReview()') && str_contains($script, 'function shippingReviewLines(shippingMethod)') && str_contains($script, "{ label: 'Cím módosítása', step: 2 }") && str_contains($script, "{ label: 'Szállítás módosítása', step: 3 }") && ! str_contains($script, "currentPaymentReview() {\n      return 'Banki átutalás'"), 'Step 4 keeps delivery together, maps both delivery changes to their existing steps, and reads current native shipping and payment selections.');
$test->assert(is_string($script) && str_contains($script, 'function currentCheckoutAddress(prefix, cart)') && str_contains($script, 'checkoutUsesShippingForBilling()') && str_contains($script, "['first_name', 'last_name', 'company', 'country', 'state', 'postcode', 'city', 'address_1', 'address_2', 'house_number']"), 'The shared checkout-address resolver derives effective billing from shipping when Woo Blocks reuses the physical address.');
$test->assert(is_string($script) && str_contains($script, 'window.appleklinikaCheckoutBillingCompanyIdentity') && str_contains($script, 'sharedCompanyIdentity.enabled') && str_contains($script, 'currentAddress: currentCheckoutAddress') && str_contains($script, "addressReview(currentAddress('billing'))"), 'Step 3 and Step 4 use one effective-address resolver and retain company identity when Woo Blocks unmounts the raw billing form for a shared physical address.');
$test->assert(is_string($script) && str_contains($script, 'function finalReviewIcon(icon)') && str_contains($script, 'aria-hidden="true"') && str_contains($script, 'note && note.value.trim()') && str_contains($script, 'data-ak-checkout-review-step') && ! str_contains($script, "textContent = 'Megrendelés'"), 'Step 4 uses decorative local timeline icons, shows a note only when present, and does not add a second order action.');
$test->assert(is_string($script) && str_contains($script, 'function positionMobileFinalReviewSummary()') && str_contains($script, "terms.parentNode.insertBefore(summary, terms)") && str_contains($script, 'ak-checkout-summary-slot--final-review'), 'On mobile, Step 4 reuses the live order summary before legal acceptance and the native order action.');
$test->assert(is_string($css) && str_contains($css, '.ak-checkout-final-review__timeline::before') && str_contains($css, '.ak-checkout-final-review__marker') && str_contains($css, 'grid-template-columns: 38px minmax(0, 1fr);') && str_contains($css, '.ak-checkout-summary__details') && str_contains($css, 'ak-checkout-step-4 .ak-checkout-summary__details'), 'Step 4 has one connected timeline rather than review-card grids and hides only duplicated sidebar context on the final step.');
$test->assert(is_string($css) && str_contains($css, 'ak-checkout-step-3 #shipping-option') && str_contains($css, 'ak-checkout-step-3 #payment-method') && str_contains($css, 'wc-block-components-radio-control__option-checked'), 'Step 3 gives native WooCommerce shipping and payment radios one shared selected-card treatment.');
$test->assert(is_string($css) && str_contains($css, 'wc-block-components-payment-method-icons') && str_contains($css, 'max-width: 44px;') && ! str_contains($css, 'Barion') && ! str_contains($css, 'GLS'), 'Step 3 accepts optional future provider artwork at a bounded size without hard-coding a shipping carrier or payment gateway.');
$test->assert(is_string($functions) && str_contains($functions, "add_filter('woocommerce_countries_shipping_countries', 'appleklinika_checkout_supported_shipping_countries')"), 'Checkout shipping countries are filtered from configured coverage.');
$test->assert(is_string($functions) && str_contains($functions, 'WC_Shipping_Zones::get_zones()') && str_contains($functions, 'get_shipping_methods(true)'), 'Shipping country filtering respects enabled zone methods including Rest of World.');
$test->assert(is_string($functions) && str_contains($functions, '$order->set_billing_company($companyName);'), 'Company checkout persists the standard WooCommerce billing company field on the order.');
$test->assert(is_string($functions) && str_contains($functions, '$order->set_billing_company(\'\');'), 'Personal checkout clears the standard order billing company field.');
$test->assert(is_string($functions) && str_contains($functions, '$maximumQuantity = $product->get_max_purchase_quantity();') && str_contains($functions, 'max="<?php echo esc_attr((string) $maximumQuantity); ?>"'), 'Cart quantity input is bounded by WooCommerce product stock rules.');
$test->assert(is_string($functions) && str_contains($functions, '$atMaximumQuantity = $maximumQuantity > 0 && $quantity >= $maximumQuantity;') && str_contains($functions, 'aria-disabled="<?php echo $atMaximumQuantity ? \'true\' : \'false\'; ?>"') && str_contains($functions, '<?php disabled($atMaximumQuantity); ?>'), 'Cart renders the native increment control unavailable at WooCommerce\'s authoritative maximum purchasable quantity.');
$test->assert(is_string($script) && str_contains($script, 'function syncCartQuantityControl(control)') && str_contains($script, 'increase.disabled = atMaximum;') && str_contains($script, "increase.setAttribute('aria-disabled', atMaximum ? 'true' : 'false');") && str_contains($script, 'syncCartQuantityControl(control);'), 'Cart synchronizes native increment availability after each local quantity change and rerendered control initialization.');
$test->assert(is_string($script) && str_contains($script, 'Math.min(max, current + step)'), 'Cart quantity controls do not propose a quantity beyond the available stock.');
$test->assert(is_string($css) && str_contains($css, '.ak-cart-qty-control button:disabled') && str_contains($css, 'cursor: not-allowed;'), 'A disabled maximum-quantity increment has a clear, non-interactive visual treatment.');
$test->assert(is_string($css) && str_contains($css, '@media (min-width: 901px) and (max-width: 1180px)') && str_contains($css, 'minmax(300px, 360px)'), 'Cart layout has a bounded tablet-width grid before stacking.');
$test->assert(is_string($css) && str_contains($css, 'width: 44px;') && str_contains($css, 'min-height: 44px;'), 'Cart controls and checkout step controls meet the 44 pixel touch target.');
$test->assert(is_string($css) && str_contains($css, '#contact-fields .ak-checkout-profile-save label') && str_contains($css, 'grid-template-columns: 20px minmax(0, 1fr);') && str_contains($css, '#shipping-fields > .ak-checkout-address-selector + .wc-block-components-address-form') && str_contains($css, '.wc-block-components-sidebar-layout > .ak-checkout-summary-slot') && str_contains($css, 'order: 1;'), 'Checkout contact consent and the address form keep a compact, explicit visual relationship, while the mobile summary follows the active form.');
$test->assert(is_string($script) && str_contains($script, 'function syncCheckoutAddressGridForSection(section)') && str_contains($script, "form.classList.add('ak-checkout-address-grid')"), 'Checkout applies its field-grid presentation through the real Woo address-form wrappers.');
$test->assert(is_string($css) && str_contains($css, '.wc-block-checkout__form') && str_contains($css, 'ak-checkout-step-2 .wc-block-checkout__form') && str_contains($css, '#shipping-fields .ak-checkout-address-grid'), 'Checkout uses one active form surface with scoped internal sections and a responsive shipping-address grid.');
$test->assert(is_string($css) && str_contains($css, '.wc-block-components-text-input label') && str_contains($css, 'color: #667085;'), 'Checkout preserves native WooCommerce field labels while applying one shared label treatment.');
$test->assert(is_string($summaryCss) && str_contains($summaryCss, 'top: 96px;') && str_contains($summaryCss, 'max-height: calc(100vh - 120px);'), 'Desktop checkout summary remains visible with a bounded sticky viewport treatment.');
$test->assert(is_string($summaryCss) && str_contains($summaryCss, '.ak-checkout-summary__details'), 'Checkout sidebar has a dedicated layout for dynamic fulfilment details.');

$test->finish();
