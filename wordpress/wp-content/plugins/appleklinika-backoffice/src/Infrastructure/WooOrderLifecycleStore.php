<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Infrastructure;

use Appleklinika\BackOffice\Application\Port\OrderLifecycleStore;
use Appleklinika\BackOffice\Domain\LifecycleOrder;

final class WooOrderLifecycleStore implements OrderLifecycleStore
{
    public const INVOICE = '_appleklinika_lifecycle_invoice';
    public const EMAIL = '_appleklinika_lifecycle_email_';

    public function order(int $id): ?LifecycleOrder
    {
        $order = wc_get_order($id);
        if (! $order instanceof \WC_Order) {
            return null;
        }
        $documents = new OrderDocuments();
        $history = (new WooOrderBackOfficeRepository())->history($order);
        $handoff = false;
        foreach ($history as $entry) {
            if (($entry['action'] ?? '') === 'handed_to_gls' && ($entry['to'] ?? '') === 'handed_to_gls') {
                $handoff = true;
            }
        }
        // A correction before dispatch can withdraw an accidental handoff before sending.
        $state = (new WooOrderBackOfficeRepository())->state($order);
        return new LifecycleOrder(
            $id, LifecycleConfiguration::manages($order), $order->is_paid(), $order->get_status(),
            (string) $order->get_meta('_wc_szamlazz_invoice', true), $documents->filePath($order, 'invoice') ?? '',
            $handoff && in_array($state, ['handed_to_gls', 'delivered'], true), $documents->trackingLinks($order),
            LifecycleConfiguration::submitted($order), LifecycleConfiguration::canSubmit($order),
            CashPickup::matches($order), CashPickup::matches($order) && CashPickup::accepted($order)
        );
    }

    public function recordSubmission(int $id): void
    {
        $this->save($id, '_appleklinika_lifecycle_submitted', ['source' => 'validated_checkout']);
    }

    public function notification(int $id, string $event): array
    {
        return $this->meta($id, self::EMAIL . $event);
    }

    public function invoiceRecord(int $id): array
    {
        return $this->meta($id, self::INVOICE);
    }

    public function recordNotification(int $id, string $event, array $record): void
    {
        $this->save($id, self::EMAIL . $event, $record);
    }

    public function invoiceState(int $id, string $state, string $reason = ''): void
    {
        $this->save($id, self::INVOICE, ['state' => $state, 'reason' => $reason]);
    }

    public function issue(int $id, string $code): void
    {
        $order = wc_get_order($id);
        if (! $order instanceof \WC_Order) {
            return;
        }
        $previous = (string) $order->get_meta('_appleklinika_lifecycle_last_issue', true);
        if ($previous === $code) {
            return;
        }
        $order->update_meta_data('_appleklinika_lifecycle_last_issue', $code);
        $order->save_meta_data();
        $order->add_order_note('Apple Klinika számla / értesítés: ellenőrzés szükséges (' . $code . ').', false);
        wc_get_logger()->error($code, ['source' => 'appleklinika-order-lifecycle', 'order_id' => $id]);
    }

    private function meta(int $id, string $key): array
    {
        $order = wc_get_order($id);
        $value = $order instanceof \WC_Order ? $order->get_meta($key, true) : [];
        return is_array($value) ? $value : [];
    }

    private function save(int $id, string $key, array $record): void
    {
        $order = wc_get_order($id);
        if (! $order instanceof \WC_Order) {
            throw new \RuntimeException('Order not found.');
        }
        $record['updated_at'] = gmdate('c');
        $order->update_meta_data($key, $record);
        $order->save_meta_data();
    }
}
