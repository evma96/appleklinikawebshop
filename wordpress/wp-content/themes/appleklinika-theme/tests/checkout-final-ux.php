<?php

declare(strict_types=1);

require dirname(__DIR__) . '/inc/checkout-presentation.php';

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    ++$checks;
    if (! $ok) {
        throw new RuntimeException($message);
    }
};
$names = ['contact-information', 'shipping-address', 'billing-address', 'shipping-methods', 'payment', 'additional-information', 'order-note', 'terms', 'actions'];
$children = array_map(static fn (string $name): array => [
    'blockName' => 'woocommerce/checkout-' . $name . '-block',
    'attrs' => ['unchanged' => $name],
    'innerBlocks' => [],
    'innerContent' => ['<div></div>'],
], $names);
$block = ['blockName' => 'woocommerce/checkout-fields-block', 'innerBlocks' => $children, 'innerContent' => ['<div>', ...array_fill(0, count($children), null), '</div>']];
$ordered = appleklinika_checkout_billing_block_order($block);
$assert(array_column($ordered['innerBlocks'], 'blockName') === array_map(static fn (string $name): string => 'woocommerce/checkout-' . $name . '-block', ['contact-information', 'shipping-address', 'additional-information', 'billing-address', 'shipping-methods', 'payment', 'order-note', 'terms', 'actions']), 'Billing identity precedes its address before React renders.');
foreach ($children as $child) {
    $assert(in_array($child, $ordered['innerBlocks'], true), 'Every native block and its attributes are preserved.');
}
$assert($ordered['innerContent'] === $block['innerContent'], 'No new wrapper or saved-page mutation.');
$assert(appleklinika_checkout_billing_block_order($ordered) === $ordered, 'Block ordering is idempotent.');
$assert(appleklinika_checkout_billing_block_order(['blockName' => 'core/group', 'innerBlocks' => $children]) === ['blockName' => 'core/group', 'innerBlocks' => $children], 'Unrelated blocks are untouched.');
$assert(appleklinika_checkout_billing_block_order(['blockName' => 'woocommerce/checkout-fields-block', 'innerBlocks' => []])['innerBlocks'] === [], 'Missing optional blocks are safe.');
$extension = ['blockName' => 'vendor/checkout-field', 'attrs' => ['id' => 'custom']];
$block['innerBlocks'][] = $extension;
$assert(in_array($extension, appleklinika_checkout_billing_block_order($block)['innerBlocks'], true), 'Third-party Blocks are preserved.');
$css = file_get_contents(dirname(__DIR__) . '/assets/css/frontend.css');
$js = file_get_contents(dirname(__DIR__) . '/assets/js/frontend.js');
$assert(str_contains($css, 'ak-checkout-step-4 #contact > :not(.wc-block-components-address-form__appleklinika-marketing_consent)'), 'The final step exposes only the original marketing field from contact.');
$assert(str_contains($css, 'ak-checkout-step-2 #contact .wc-block-components-address-form__appleklinika-marketing_consent'), 'Marketing is deferred to final declarations.');
$assert(str_contains($js, "image.alt") && str_contains($js, "paymentImage.alt"), 'Image-only gateway labels remain readable in both summaries.');
$assert(str_contains($js, "shipping.push('Telefon: ' + shippingPhone)"), 'Review includes the actual delivery phone.');
$assert(str_contains($js, "var companyStep = closestCheckoutStep('#order-fields');"), 'Step visibility uses the stable Woo fieldset, not an ephemeral custom field class.');
$summaryCss = file_get_contents(dirname(__DIR__) . '/assets/css/checkout-sidebar.css');
$assert(str_contains($js, 'Kiválasztás a 3. lépésben') && str_contains($summaryCss, ':not(.ak-checkout-step-4) .ak-checkout-summary__method-chosen'), 'Existing step classes defer default-method presentation until final review, without altering Woo state.');
$assert(str_contains($js, 'Számlázási címként a szállítási címet használjuk.') && str_contains($js, 'sameAddressHelp.hidden = !sameAddress || !sameAddress.checked;'), 'Same-address copy follows the single native checkbox.');
$assert(substr_count($js, 'var addressPrefix = checkoutAddressFieldPrefix(prefix);') === 2, 'Sidebar and final review read the shared shipping address for billing without dropping company identity.');
$assert(str_contains($js, "['Számlázási cím', addressSummary(billing)]") && ! str_contains($js, 'var effectiveBilling = billing && billing.address_1 ? billing : shipping;'), 'An empty independent billing address is not replaced with shipping in the sidebar.');
echo "Checkout final UX: {$checks} assertions passed.\n";
