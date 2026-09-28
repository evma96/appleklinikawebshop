<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Infrastructure;

use Appleklinika\BackOffice\Application\Port\OrderMutex;

final class WooOrderMutex implements OrderMutex
{
    private static array $held = [];

    public function synchronized(int $id, callable $operation): mixed
    {
        global $wpdb;
        $name = 'akbo:' . substr(hash('sha256', $wpdb->prefix), 0, 12) . ':' . $id;
        if (isset(self::$held[$name])) {
            return $operation();
        }
        if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $name)) !== '1') {
            throw new \RuntimeException('Order is busy; no operation was attempted.');
        }
        self::$held[$name] = true;
        try {
            return $operation();
        } finally {
            unset(self::$held[$name]);
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
        }
    }
}
