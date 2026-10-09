<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Interfaces;

use Appleklinika\BackOffice\Infrastructure\{CashPickup, LifecycleConfiguration};

final class CashPickupHooks
{
    public function register(): void
    {
        add_filter('woocommerce_cod_process_payment_order_status', [$this, 'pendingCash'], 30, 2);
        add_filter('woocommerce_order_is_paid', [$this, 'paid'], 30, 2);
        add_filter('woocommerce_payment_complete_order_status', [$this, 'paymentStatus'], 30, 3);
        add_filter('woocommerce_available_payment_gateways', [$this, 'gateways'], 30);
    }
    public function pendingCash(string $status, \WC_Order $order): string
    {
        return LifecycleConfiguration::manages($order) && CashPickup::matches($order) ? 'on-hold' : $status;
    }
    public function paid(bool $paid, \WC_Order $order): bool
    {
        return LifecycleConfiguration::manages($order) && CashPickup::matches($order) ? $paid && CashPickup::paymentRecorded($order) : $paid;
    }
    public function paymentStatus(string $status, int $id, \WC_Order $order): string
    {
        return LifecycleConfiguration::manages($order) && CashPickup::matches($order) ? 'processing' : $status;
    }
    public function gateways(array $gateways): array
    {
        if (isset($gateways['cod'])) {
            $chosen = WC()->session ? (array) WC()->session->get('chosen_shipping_methods', []) : [];
            $methods = array_map(static fn ($method) => explode(':', (string) $method)[0], $chosen);
            if (get_option('appleklinika_cash_pickup_enabled', 'no') !== 'yes'
                || \Appleklinika\BackOffice\Domain\DeliveryMode::fromShippingMethodIds($methods) !== 'pickup') {
                unset($gateways['cod']);
            }
        }
        return $gateways;
    }
}
