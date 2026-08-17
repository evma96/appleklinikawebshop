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

/**
 * Extract one ordinary JavaScript function with a balanced-brace scan.
 *
 * This deliberately verifies ownership boundaries rather than merely looking
 * for an identifier: a helper nested in another initializer is not callable
 * from the checkout stepper initializer.
 *
 * @return array{start:int,end:int,body:string}|null
 */
function cartCheckoutJavaScriptFunction(string $source, string $name): ?array
{
    $match = [];

    if (preg_match('/function\\s+' . preg_quote($name, '/') . '\\s*\\([^)]*\\)\\s*\\{/', $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
        return null;
    }

    $start = $match[0][1];
    $openingBrace = $start + strlen($match[0][0]) - 1;
    $depth = 0;
    $length = strlen($source);

    for ($index = $openingBrace; $index < $length; ++$index) {
        if ($source[$index] === '{') {
            ++$depth;
        } elseif ($source[$index] === '}') {
            --$depth;

            if ($depth === 0) {
                return [
                    'start' => $start,
                    'end' => $index,
                    'body' => substr($source, $start, $index - $start + 1),
                ];
            }
        }
    }

    return null;
}

$themeRoot = dirname(__DIR__);
$functions = file_get_contents($themeRoot . '/functions.php');
$script = file_get_contents($themeRoot . '/assets/js/frontend.js');
$commerceLocalization = file_get_contents($themeRoot . '/assets/js/commerce-localization.js');
$css = file_get_contents($themeRoot . '/assets/css/frontend.css');
$summaryCss = file_get_contents($themeRoot . '/assets/css/checkout-sidebar.css');
$test = new CartCheckoutTest();
$sharedBillingDecision = is_string($script) ? cartCheckoutJavaScriptFunction($script, 'checkoutUsesShippingAsBilling') : null;
$checkoutSummaryInitializer = is_string($script) ? cartCheckoutJavaScriptFunction($script, 'initCheckoutSummary') : null;
$checkoutStepperInitializer = is_string($script) ? cartCheckoutJavaScriptFunction($script, 'initCheckoutStepper') : null;
$effectiveBillingReview = is_string($script) ? cartCheckoutJavaScriptFunction($script, 'effectiveBillingReview') : null;
$companyBillingReviewState = is_string($script) ? cartCheckoutJavaScriptFunction($script, 'checkoutCompanyBillingReviewState') : null;
$checkoutAddressSummary = is_string($script) ? cartCheckoutJavaScriptFunction($script, 'addressSummary') : null;
$checkoutAddressReview = is_string($script) ? cartCheckoutJavaScriptFunction($script, 'addressReview') : null;

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
$test->assert(is_string($script) && str_contains($script, "'order-appleklinika-company_name'") && str_contains($script, "'Adószám: ' + taxNumber") && str_contains($script, 'var recipient = companyName ||'), 'Step 4 presents company billing identity and tax number without inventing personal billing names.');
$test->assert(is_string($script) && str_contains($script, 'function currentShippingReview()') && str_contains($script, 'function currentPaymentReview()') && str_contains($script, 'function shippingReviewLines(shippingMethod)') && str_contains($script, "{ label: 'Cím módosítása', step: 2 }") && str_contains($script, "{ label: 'Szállítás módosítása', step: 3 }") && ! str_contains($script, "currentPaymentReview() {\n      return 'Banki átutalás'"), 'Step 4 keeps delivery together, maps both delivery changes to their existing steps, and reads current native shipping and payment selections.');
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
$test->assert(is_string($script) && str_contains($script, 'Math.min(max, current + step)'), 'Cart quantity controls do not propose a quantity beyond the available stock.');
$test->assert(is_string($css) && str_contains($css, '@media (min-width: 901px) and (max-width: 1180px)') && str_contains($css, 'minmax(300px, 360px)'), 'Cart layout has a bounded tablet-width grid before stacking.');
$test->assert(is_string($css) && str_contains($css, 'width: 44px;') && str_contains($css, 'min-height: 44px;'), 'Cart controls and checkout step controls meet the 44 pixel touch target.');
$test->assert(is_string($css) && str_contains($css, '#contact-fields .ak-checkout-profile-save label') && str_contains($css, 'grid-template-columns: 20px minmax(0, 1fr);') && str_contains($css, '#shipping-fields > .ak-checkout-address-selector + .wc-block-components-address-form') && str_contains($css, '.wc-block-components-sidebar-layout > .ak-checkout-summary-slot') && str_contains($css, 'order: 1;'), 'Checkout contact consent and the address form keep a compact, explicit visual relationship, while the mobile summary follows the active form.');
$test->assert(is_string($script) && str_contains($script, 'function syncCheckoutAddressGridForSection(section)') && str_contains($script, "form.classList.add('ak-checkout-address-grid')"), 'Checkout applies its field-grid presentation through the real Woo address-form wrappers.');
$test->assert(is_string($css) && str_contains($css, '.wc-block-checkout__form') && str_contains($css, 'ak-checkout-step-2 .wc-block-checkout__form') && str_contains($css, '#shipping-fields .ak-checkout-address-grid'), 'Checkout uses one active form surface with scoped internal sections and a responsive shipping-address grid.');
$test->assert(is_string($css) && str_contains($css, '.wc-block-components-text-input label') && str_contains($css, 'color: #667085;'), 'Checkout preserves native WooCommerce field labels while applying one shared label treatment.');
$test->assert(is_string($summaryCss) && str_contains($summaryCss, 'top: 96px;') && str_contains($summaryCss, 'max-height: calc(100vh - 120px);'), 'Desktop checkout summary remains visible with a bounded sticky viewport treatment.');
$test->assert(is_string($summaryCss) && str_contains($summaryCss, '.ak-checkout-summary__details'), 'Checkout sidebar has a dedicated layout for dynamic fulfilment details.');
$test->assert(is_string($functions) && str_contains($functions, "'appleklinika-commerce-localization'") && str_contains($functions, "['wp-i18n']"), 'WooCommerce Blocks localization loads through the supported WordPress i18n dependency before customer-facing components render.');
$test->assert(is_string($commerceLocalization) && str_contains($commerceLocalization, "'i18n.gettext'") && str_contains($commerceLocalization, "'i18n.gettext_with_context'") && str_contains($commerceLocalization, "domain !== 'woocommerce'"), 'Commerce localization uses scoped WordPress i18n filters instead of replacing arbitrary rendered DOM text.');
$test->assert(is_string($commerceLocalization) && str_contains($commerceLocalization, "'Place Order': 'Megrendelés'") && str_contains($commerceLocalization, "'Payment options': 'Fizetési mód'") && str_contains($commerceLocalization, "'Select a %s': 'Válassz: %s'"), 'Critical checkout labels, the native final CTA and select placeholder resolve to Hungarian.');
$test->assert(is_string($script) && str_contains($script, 'function createCheckoutHeading(checkoutBlock)') && str_contains($script, "heading.textContent = 'Pénztár'") && str_contains($script, "document.querySelector('.ak-checkout-title')"), 'Checkout creates exactly one reusable, meaningful page-level H1.');
$test->assert(is_string($script) && str_contains($script, "2: 'Adatok'") && str_contains($script, "3: 'Szállítás és fizetés'") && str_contains($script, "control.setAttribute('aria-current', 'step')") && str_contains($script, 'stateLabel'), 'Checkout keeps the complete four-step semantic journey and exposes current, completed and pending state.');
$test->assert(is_string($script) && str_contains($script, 'ak-checkout-stepper__mobile-status') && str_contains($script, "String(activeStep) + ' / 4'") && str_contains($css, 'grid-template-columns: repeat(4, minmax(0, 1fr));'), 'Mobile checkout shows a compact four-marker progress rail with a labelled current-step summary.');
$test->assert(is_string($script) && str_contains($script, 'function effectiveBillingAddress(billing, shipping)') && str_contains($script, 'checkoutUsesShippingAsBilling()') && str_contains($script, 'return shipping;'), 'Step 3 uses the shipping address as the effective personal billing address when the same-address contract is selected.');
$test->assert(is_string($script) && str_contains($script, 'companyBilling.company') && str_contains($script, 'Object.assign({}, shipping') && str_contains($script, "checkoutFormAddress('billing')"), 'Step 3 keeps company billing identity while reusing only the selected shipping physical address.');
$test->assert(is_string($script) && str_contains($script, 'function effectiveBillingReview()') && str_contains($script, "addressReview('billing', 'shipping')") && str_contains($script, "addressReview('shipping')"), 'Step 4 applies the same effective-billing contract for company and personal states.');
$test->assert(
    $sharedBillingDecision !== null
    && $checkoutSummaryInitializer !== null
    && $checkoutStepperInitializer !== null
    && $sharedBillingDecision['start'] < $checkoutSummaryInitializer['start']
    && $sharedBillingDecision['start'] < $checkoutStepperInitializer['start']
    && ! str_contains($checkoutSummaryInitializer['body'], 'function checkoutUsesShippingAsBilling')
    && ! str_contains($checkoutStepperInitializer['body'], 'function checkoutUsesShippingAsBilling'),
    'The same-address decision is one shared module helper, not a nested initializer helper that can abort the checkout stepper.'
);
$test->assert(
    $sharedBillingDecision !== null
    && $effectiveBillingReview !== null
    && str_contains($effectiveBillingReview['body'], 'checkoutUsesShippingAsBilling()')
    && str_contains($sharedBillingDecision['body'], "checkoutFieldByLabel('A szállítási és számlázási cím megegyezik.')"),
    'The Step 4 effective-billing review executes the shared current same-address decision rather than a duplicated billing rule.'
);
$test->assert(
    $companyBillingReviewState !== null
    && $checkoutSummaryInitializer !== null
    && $checkoutStepperInitializer !== null
    && $companyBillingReviewState['start'] < $checkoutSummaryInitializer['start']
    && $companyBillingReviewState['start'] < $checkoutStepperInitializer['start']
    && str_contains($companyBillingReviewState['body'], "'order-appleklinika-tax_number'")
    && str_contains($companyBillingReviewState['body'], 'checkoutCompanyBillingState')
    && str_contains($companyBillingReviewState['body'], 'companyToggle && !companyToggle.checked')
    && str_contains($companyBillingReviewState['body'], 'companyToggle && companyToggle.checked && companyName && taxNumber'),
    'One shared checkout company-review state retains the current company name and tax number while same-address mode temporarily unmounts the billing fields.'
);
$test->assert(
    $checkoutAddressSummary !== null
    && $checkoutAddressReview !== null
    && str_contains($checkoutSummaryInitializer['body'], 'addressSummary(billing, companyBilling)')
    && str_contains($checkoutAddressSummary['body'], "'Adószám: ' + companyBilling.taxNumber")
    && str_contains($checkoutAddressReview['body'], 'checkoutCompanyBillingReviewState()')
    && str_contains($checkoutAddressReview['body'], "'Adószám: ' + taxNumber"),
    'Step 3 and Step 4 render the same current company name, tax number and physical billing address without leaking company identity into personal billing.'
);
$test->assert(is_string($css) && str_contains($css, '.ak-checkout-stepper__control:focus-visible') && str_contains($css, 'outline: 3px solid rgba(214, 0, 28, .26);'), 'Checkout step controls retain a visible brand-consistent keyboard focus indicator.');

$test->finish();
