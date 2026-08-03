<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Infrastructure\WordPress;

final class Requirements
{
    public static function isSatisfied(): bool
    {
        return version_compare(PHP_VERSION, '8.1', '>=') && class_exists('WooCommerce');
    }

    public static function assertSatisfied(): void
    {
        if (! self::isSatisfied()) {
            throw new \RuntimeException('Az Apple Klinika Címjegyzék PHP 8.1-et és aktív WooCommerce-t igényel.');
        }
    }

    public static function renderNotice(): void
    {
        echo '<div class="notice notice-error"><p>';
        echo esc_html('Az Apple Klinika Címjegyzék PHP 8.1-et és aktív WooCommerce-t igényel.');
        echo '</p></div>';
    }
}
