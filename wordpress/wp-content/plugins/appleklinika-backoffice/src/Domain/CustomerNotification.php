<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Domain;

final class CustomerNotification
{
    public const PAID = 'paid_invoice';
    public const SHIPPED = 'carrier_handoff';

    public static function eligible(string $event, LifecycleOrder $order): bool
    {
        if (! $order->managed || ! $order->paid || in_array($order->status, ['cancelled', 'failed', 'refunded', 'checkout-draft'], true)) {
            return false;
        }

        return match ($event) {
            self::PAID => $order->invoiceNumber !== '' && $order->invoicePath !== '',
            self::SHIPPED => $order->handoffRecorded && $order->tracking !== [],
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
