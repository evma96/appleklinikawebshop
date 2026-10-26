<?php

declare(strict_types=1);
namespace Appleklinika\Inventory\Infrastructure\WordPress;

/** Native cart keys are copied to order-item metadata; no parallel order state. */
final class WooProtectionAssociation
{
    public const PARENT = '_ak_protection_parent_key';
    public const LINE = '_ak_protection_line_key';
    public const LABEL = 'Készülék';
    private bool $pruning = false;

    public function afterRemovalOrRestore(string $key, \WC_Cart $cart): void
    {
        $this->pruneOrphans($cart);
    }

    public function pruneOrphans(\WC_Cart $cart): void
    {
        if ($this->pruning) return;
        $this->pruning = true;
        try {
            foreach ($cart->get_cart() as $key => $line) {
                if (!isset($line[self::PARENT])) continue;
                $parent = $cart->get_cart()[$line[self::PARENT]] ?? null;
                if (!$parent || isset($parent[self::PARENT])) $cart->remove_cart_item($key);
            }
        } finally {
            $this->pruning = false;
        }
    }

    public function cartItemData(array $data, array $line): array
    {
        if (!isset($line[self::PARENT]) || !WC()->cart instanceof \WC_Cart) return $data;
        $parent = WC()->cart->get_cart()[$line[self::PARENT]] ?? null;
        if ($parent && $parent['data'] instanceof \WC_Product) {
            $name = wp_strip_all_tags($parent['data']->get_name());
            $data[] = ['key' => self::LABEL, 'value' => $name, 'display' => esc_html($name)];
        }
        return $data;
    }

    public function copyToOrder(\WC_Order_Item_Product $item, string $key, array $line, \WC_Order $order): void
    {
        $cart = WC()->cart instanceof \WC_Cart ? WC()->cart->get_cart() : [];
        $parentKey = $line[self::PARENT] ?? '';
        if ($parentKey !== '') {
            $parent = $cart[$parentKey] ?? null;
            if (!$parent || isset($parent[self::PARENT]) || !$parent['data'] instanceof \WC_Product) {
                throw new \RuntimeException('A kiegészítőhöz tartozó készülék hiányzik. Kérjük, ellenőrizd a kosarat.');
            }
            $item->update_meta_data(self::PARENT, $parentKey);
            // Public native Woo item metadata appears in account/admin and invoice item notes.
            $item->update_meta_data(self::LABEL, wp_strip_all_tags($parent['data']->get_name()));
        }
        foreach ($cart as $cartLine) {
            if ($parentKey !== '' || ($cartLine[self::PARENT] ?? '') === $key) {
                $item->update_meta_data(self::LINE, $key);
                break;
            }
        }
    }
}
