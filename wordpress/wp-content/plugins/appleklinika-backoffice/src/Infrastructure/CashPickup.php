<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

use Appleklinika\BackOffice\Domain\DeliveryMode;

/** Woo facts and supported persistence operations for the existing pickup workflow. */
final class CashPickup
{
    public const PAYMENT = '_appleklinika_cash_payment';

    public static function matches(\WC_Order $order): bool
    {
        if ($order->get_payment_method() !== 'cod') { return false; }
        $methods = [];
        foreach ($order->get_shipping_methods() as $method) { $methods[] = $method->get_method_id(); }
        return DeliveryMode::fromShippingMethodIds($methods) === DeliveryMode::PICKUP;
    }

    public static function accepted(\WC_Order $order): bool
    {
        foreach ((new WooOrderBackOfficeRepository())->history($order) as $entry) {
            if (($entry['action'] ?? '') === 'accept_cash_pickup' && (int) ($entry['user_id'] ?? 0) > 0) { return true; }
        }
        return false;
    }

    public static function paymentRecorded(\WC_Order $order): bool
    {
        $record = $order->get_meta(self::PAYMENT, true);
        return is_array($record) && (int) ($record['actor'] ?? 0) > 0 && !empty($record['at']);
    }

    public function verifyReservation(\WC_Order $order): void
    {
        // Native COD on-hold already reserves stock by reducing it once. Staff
        // confirms physical availability explicitly; never deduct a second time.
        if (!$order->has_status('on-hold') || $order->get_items() === []) {
            throw new \InvalidArgumentException('Elfogadáshoz beadott, fizetésre váró készpénzes rendelés szükséges.');
        }
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product || ($product->managing_stock() && (float) $item->get_meta('_reduced_stock', true) < (float) $item->get_quantity())) {
                throw new \InvalidArgumentException('A rendelés készletfoglalása nem igazolt. Ellenőrizd a terméket és a Woo készletnaplót; elfogadó levél nem készült.');
            }
        }
    }

    public function recordPayment(\WC_Order $order, int $actor): void
    {
        if ($actor <= 0 || !self::matches($order) || !self::accepted($order)) {
            throw new \InvalidArgumentException('Készpénzfizetés csak elfogadott személyes átvételhez rögzíthető.');
        }
        if (!self::paymentRecorded($order)) {
            $order->update_meta_data(self::PAYMENT, ['actor' => $actor, 'at' => gmdate('c'), 'amount' => $order->get_total(), 'currency' => $order->get_currency()]);
            $order->save_meta_data();
        }
        if (!$order->get_date_paid()) {
            $order->payment_complete();
        }
        $fresh = wc_get_order($order->get_id());
        if (!$fresh instanceof \WC_Order || !$fresh->get_date_paid() || !$fresh->is_paid()) {
            throw new \RuntimeException('Cash payment persistence requires reconciliation; no repeated invoice is attempted.');
        }
    }
}
