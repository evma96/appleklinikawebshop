<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Domain;

/** Webshop checkout eligibility only; staff cash recording is a separate workflow. */
final class CheckoutPaymentPolicy
{
    public static function allows(string $payment): bool
    {
        return $payment === 'barion';
    }
}
