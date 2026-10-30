<?php

declare(strict_types=1);
namespace Appleklinika\Inventory\Application\Checkout;

use Appleklinika\Inventory\Infrastructure\WordPress\WooProtectionAssociation as Association;

/** Reconcile only an unpaid Store API draft against its authoritative native cart. */
final class ReconcileProtectionDraft
{
    public function execute(\WC_Order $order, \WC_Cart $cart): bool
    {
        if ($order->get_status() !== 'checkout-draft' || $order->get_created_via() !== 'store-api'
            || $order->get_date_paid() || $order->get_transaction_id() !== ''
            || $order->get_meta('_order_stock_reduced') || $order->get_meta('_wc_szamlazz')) return false;
        $contents = $cart->get_cart();
        $parents = [];
        foreach ($contents as $line) if (!empty($line[Association::PARENT])) $parents[$line[Association::PARENT]] = true;
        $hasExtras = $parents !== [];
        $actual = [];
        foreach ($order->get_items('line_item') as $item) {
            $parent = (string)$item->get_meta(Association::PARENT);
            $hasExtras = $hasExtras || $parent !== '';
            $actual[] = $this->signature($item->get_product_id(), $item->get_variation_id(), $item->get_quantity(),
                (string)$item->get_meta(Association::LINE), $parent, $item->get_subtotal(), $item->get_total(),
                $item->get_subtotal_tax(), $item->get_total_tax());
        }
        if (!$hasExtras) return false;
        $expected = [];
        foreach ($contents as $key => $line) {
            $parent = (string)($line[Association::PARENT] ?? '');
            if ($parent !== '' && (!isset($contents[$parent]) || isset($contents[$parent][Association::PARENT]))) {
                throw new \RuntimeException('A kiegészítőhöz tartozó készülék hiányzik. Kérjük, ellenőrizd a kosarat.');
            }
            $expected[] = $this->signature($line['product_id'], $line['variation_id'], $line['quantity'],
                $parent !== '' || isset($parents[$key]) ? $key : '', $parent,
                $line['line_subtotal'], $line['line_total'], $line['line_subtotal_tax'], $line['line_tax']);
        }
        sort($actual); sort($expected);
        if ($actual === $expected) return false;
        // Native Woo APIs rebuild the whole draft line set, never SKU-based deduplication.
        $order->remove_order_items('line_item');
        \WC()->checkout()->create_order_line_items($order, $cart);
        $order->set_cart_hash($cart->get_cart_hash());
        $order->calculate_totals();
        return true;
    }

    private function signature($product, $variation, $quantity, string $key, string $parent, ...$amounts): string
    {
        return json_encode([(int)$product, (int)$variation, (float)$quantity, $key, $parent,
            array_map(static fn($n): string => number_format((float)$n, 6, '.', ''), $amounts)], JSON_THROW_ON_ERROR);
    }
}
