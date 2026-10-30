<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

use Appleklinika\BackOffice\Domain\{DeliveryMode, PaymentState};

/** Read model only: no provider calls, payment flags, or checkout-configuration changes. */
final class WooOrderPayment
{
    public static function describe(\WC_Order $order, string $mode): array
    {
        if ($order->get_meta('_appleklinika_payment_stock_exception')) {
            return ['state'=>'stock_conflict','label'=>'Barion-fizetés: készletütközés',
                'block'=>'A pénz beérkezhetett, de a készülék nem biztosítható. Egyeztesd a Barion-tranzakciót és a készletet; szükség esetén visszatérítés szükséges. Automatikus teljesítés tiltva.', 'review'=>true];
        }
        $codBlock = null;
        $cashRecorded = false;
        if ($order->get_payment_method() === 'cod') {
            $methods = $order->get_shipping_methods();
            if (!$methods || !self::codMethodsAllowed($methods)) {
                $codBlock = 'A WooCommerce utánvét nincs engedélyezve ehhez a szállítási módhoz. A fizetési mód beállítását és az eredeti rendelést kell egyeztetni; előre fizetettnek jelölés nem megoldás.';
            } elseif ($mode === DeliveryMode::PICKUP) {
                if (!class_exists(CashPickup::class) || !class_exists(LifecycleConfiguration::class) || !LifecycleConfiguration::submitted($order)) {
                    $codBlock = 'Ehhez a régi készpénzes rendeléshez nincs igazolt beadási és átvételi fizetésrögzítési folyamat. A készletfoglalást és az eredeti rendelést kell egyeztetni.';
                } else { $cashRecorded = CashPickup::paymentRecorded($order); }
            } else {
                $codBlock = 'A futáros utánvét nem része az engedélyezett fizetési folyamatnak. A rendelés fizetési módját egyeztetni kell; a Barion-fizetést nem helyettesíti kézi igazolás.';
            }
        }
        $barion = $order->get_meta('_appleklinika_barion_review', true);
        return PaymentState::describe($order->get_payment_method(), $order->get_status(), $order->is_paid(), $mode,
            $codBlock, $cashRecorded, is_array($barion) ? (string)($barion['state'] ?? '') : '');
    }

    /** Match Woo COD's configured method IDs/rate IDs, independent of the viewing employee's cart. */
    public static function codMethodAllowed(string $method, int $instance): bool
    {
        $settings = get_option('woocommerce_cod_settings', []);
        if (!is_array($settings) || ($settings['enabled'] ?? 'no') !== 'yes') { return false; }
        $allowed = $settings['enable_for_methods'] ?? [];
        return is_array($allowed) && ($allowed === [] || in_array($method, $allowed, true) || in_array($method . ':' . $instance, $allowed, true));
    }

    private static function codMethodsAllowed(array $methods): bool
    {
        foreach ($methods as $method) {
            if (!self::codMethodAllowed($method->get_method_id(), (int)$method->get_instance_id())) { return false; }
        }
        return true;
    }
}
