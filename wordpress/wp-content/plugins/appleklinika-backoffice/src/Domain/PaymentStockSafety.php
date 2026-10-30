<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Domain;

final class PaymentStockSafety
{
    /** Quantities are aggregated by native Woo stock owner, excluding this order's hold. */
    public static function conflict(array $lines): bool
    {
        foreach ($lines as $line) {
            if (!$line['exists'] || !$line['managed'] || $line['backorders'] || $line['required'] <= 0
                || $line['stock'] - $line['held_elsewhere'] < $line['required']) return true;
        }
        return false;
    }
}
