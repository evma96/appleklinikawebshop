<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Domain;

final class CustomerNotification
{
    public const RECEIVED = 'order_received';
    public const PAID = 'paid_invoice';
    public const SHIPPED = 'carrier_handoff';

    public static function eligible(string $event, LifecycleOrder $order): bool
    {
        if ($event === self::RECEIVED) {
            return $order->submitted && ! in_array($order->status, ['cancelled', 'refunded', 'checkout-draft', 'trash', 'auto-draft'], true);
        }
        if (! $order->managed || in_array($order->status, ['cancelled', 'failed', 'refunded', 'checkout-draft'], true)) {
            return false;
        }

        return match ($event) {
            self::PAID => $order->cashPickup ? $order->cashAccepted : ($order->paid && $order->invoiceNumber !== '' && $order->invoicePath !== ''),
            self::SHIPPED => !$order->cashPickup && $order->paid && $order->handoffRecorded && $order->tracking !== [],
            default => false,
        };
    }

    /** A request interrupted during send is uncertain, never automatically resent. */
    public static function mayAttempt(array $record): bool
    {
        return in_array($record['state'] ?? 'pending', ['pending', 'failed'], true)
            && (int) ($record['attempts'] ?? 0) < 3;
    }
}
