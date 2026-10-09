<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

/** Resolve the existing private invoice only for its authenticated cash-pickup owner. */
final class CustomerInvoice
{
    public function path(\WC_Order $order, int $userId): ?string
    {
        if ($userId <= 0 || $order->get_customer_id() !== $userId || !LifecycleConfiguration::manages($order)
            || !CashPickup::matches($order) || !CashPickup::paymentRecorded($order) || !$order->is_paid()) {
            return null;
        }
        return (new OrderDocuments())->filePath($order, 'invoice');
    }
}
