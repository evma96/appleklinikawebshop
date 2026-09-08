<?php

declare(strict_types=1);

/**
 * Set the initial Blocks order, before React owns the checkout controls.
 * Keep every block, its attributes and the stored checkout page untouched.
 */
function appleklinika_checkout_billing_block_order(array $block): array
{
    if (($block['blockName'] ?? '') !== 'woocommerce/checkout-fields-block') {
        return $block;
    }

    $children = $block['innerBlocks'] ?? [];
    $names = array_column($children, 'blockName');
    $company = array_search('woocommerce/checkout-additional-information-block', $names, true);
    $billing = array_search('woocommerce/checkout-billing-address-block', $names, true);

    if ($company === false || $billing === false || $company < $billing) {
        return $block;
    }

    $companyBlock = array_splice($children, $company, 1);
    array_splice($children, $billing, 0, $companyBlock);
    $block['innerBlocks'] = $children;

    return $block;
}
