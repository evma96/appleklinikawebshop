<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

final class Requirements
{
    public static function assertSatisfied(): void
    {
        if (version_compare(PHP_VERSION, APPLEKLINIKA_BUYBACK_MINIMUM_PHP_VERSION, '<')) {
            throw new \RuntimeException(sprintf(
                'Apple Klinika Buyback requires PHP %s or newer.',
                APPLEKLINIKA_BUYBACK_MINIMUM_PHP_VERSION
            ));
        }

        if (! self::isWooCommerceAvailable()) {
            throw new \RuntimeException('Apple Klinika Buyback requires WooCommerce to be active.');
        }
    }

    public static function isWooCommerceAvailable(): bool
    {
        return class_exists('WooCommerce') || defined('WC_VERSION');
    }
}
