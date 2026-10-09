<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

use Appleklinika\BackOffice\Application\Port\FulfilmentStore;

final class WooFulfilmentStore implements FulfilmentStore
{
    public function __construct(private readonly WooOrderBackOfficeRepository $orders) {}

    public function snapshot(int $id): array
    {
        $order = $this->order($id);
        return ['state' => $this->orders->state($order), 'mode' => $this->orders->deliveryMode($order),
            'blocked' => $this->orders->fulfilmentBlockReason($order), 'label' => MplCarrier::matches($order) ? MplCarrier::hasLabel($order) : $this->orders->hasGlsLabel($order),
            'tracking' => (new OrderDocuments())->trackingLinks($order) !== [],
            'manifest' => !MplCarrier::matches($order) || $order->get_meta('_vp_woo_pont_mpl_closed', true) === 'yes',
            'cash' => CashPickup::matches($order) && LifecycleConfiguration::submitted($order),
            'cash_accepted' => CashPickup::matches($order) && CashPickup::accepted($order)];
    }

    public function verifyCashReservation(int $id): void
    {
        (new CashPickup())->verifyReservation($this->order($id));
    }

    public function recordCashPayment(int $id, int $actor): void
    {
        (new CashPickup())->recordPayment($this->order($id), $actor);
    }

    public function createLabel(int $id): void
    {
        if (MplCarrier::matches($this->order($id))) { MplCarrier::createLabel($this->order($id)); return; }
        if (! LifecycleConfiguration::isTestEnvironment() || ! class_exists('GLS_Shipping_Account_Helper')
            || (\GLS_Shipping_Account_Helper::get_active_account()['mode'] ?? '') !== 'sandbox') {
            throw new \InvalidArgumentException('Ebben a folyamatban kizárólag TEST / GLS Sandbox címke készíthető.');
        }
        $this->orders->createGlsLabel($this->order($id));
    }

    public function record(int $id, string $action, string $to, int $actor, string $reason): void
    {
        $this->orders->recordTransition($this->order($id), $action, $to, $actor, $reason);
        do_action('appleklinika_fulfilment_transitioned', $id, $action);
    }

    private function order(int $id): \WC_Order
    {
        if (! LifecycleConfiguration::isTestEnvironment()) {
            throw new \InvalidArgumentException('Az új teljesítési folyamat csak TEST környezetben érhető el.');
        }
        $order = wc_get_order($id);
        if (! $order instanceof \WC_Order) {
            throw new \InvalidArgumentException('A rendelés nem található.');
        }
        return $order;
    }
}
