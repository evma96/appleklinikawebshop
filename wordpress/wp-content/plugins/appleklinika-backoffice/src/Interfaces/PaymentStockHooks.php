<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Interfaces;
use Appleklinika\BackOffice\Application\AdmitDevicePayment;
use Appleklinika\BackOffice\Infrastructure\WooPaymentStockStore;

final class PaymentStockHooks
{
    public function register(): void
    {
        add_action('woocommerce_pre_payment_complete',[$this,'admit'],-1000,2);
        add_filter('woocommerce_valid_order_statuses_for_payment_complete',[$this,'refresh'],PHP_INT_MAX,2);
        add_filter('woocommerce_barion_should_update_order_status',[$this,'gatewayStatus'],PHP_INT_MAX,2);
    }
    public function admit(int $id,string $transaction=''): void
    {
        (new AdmitDevicePayment(new WooPaymentStockStore()))->execute($id,$transaction);
    }
    public function refresh(array $statuses,\WC_Order $order): array
    {
        if($order->get_payment_method()==='barion')$order->get_data_store()->read($order);
        return $statuses;
    }
    public function gatewayStatus(bool $allowed,\WC_Order $order): bool
    {
        $fresh=wc_get_order($order->get_id());
        return $fresh && $fresh->get_meta(WooPaymentStockStore::EXCEPTION) ? false : $allowed;
    }
}
