<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Infrastructure;

/** Explicit TEST rollout. Existing orders and other environments keep their mail flow. */
final class LifecycleConfiguration
{
    public static function enabled(): bool
    {
        return self::isTestEnvironment() && (int) get_option('appleklinika_lifecycle_enabled_at', 0) > 0;
    }

    public static function isTestEnvironment(): bool
    {
        // The dedicated staff host rewrites home_url(); rollout belongs to the
        // canonical installation, not the host used to perform an order action.
        $host = strtolower((string) wp_parse_url((string) get_option('home', ''), PHP_URL_HOST));
        return in_array($host, ['teszt.appleklinika.com', 'localhost', '127.0.0.1'], true)
            && wp_get_environment_type() !== 'production';
    }

    public static function submitted(\WC_Order $order): bool
    {
        return self::isTestEnvironment() && is_array($record = $order->get_meta('_appleklinika_lifecycle_submitted', true))
            && ($record['source'] ?? '') === 'validated_checkout';
    }

    public static function canSubmit(\WC_Order $order): bool
    {
        // Cash is currently disabled and has no agreed second-stage rule. Do not
        // silently enrol it or alter its legacy acceptance behavior.
        return self::enabled() && in_array($order->get_payment_method(), ['barion', 'bacs'], true)
            && in_array($order->get_created_via(), ['checkout', 'store-api'], true)
            && ! in_array($order->get_status(), ['checkout-draft', 'auto-draft', 'trash', 'cancelled', 'refunded'], true)
            && $order->get_date_created() !== null
            && $order->get_date_created()->getTimestamp() >= (int) get_option('appleklinika_lifecycle_enabled_at', 0);
    }

    public static function manages(\WC_Order $order): bool
    {
        if (! self::isTestEnvironment()) { return false; }
        if ((int) $order->get_meta('_appleklinika_lifecycle_version', true) === 1) { return true; }
        if (self::submitted($order) && $order->get_payment_method() === 'bacs') { return true; }
        return self::enabled() && $order->get_payment_method() === 'barion'
            && $order->get_date_created() !== null
            && $order->get_date_created()->getTimestamp() >= (int) get_option('appleklinika_lifecycle_enabled_at', 0);
    }
}
