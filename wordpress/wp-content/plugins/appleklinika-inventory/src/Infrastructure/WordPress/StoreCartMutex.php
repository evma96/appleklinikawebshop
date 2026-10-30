<?php

declare(strict_types=1);
namespace Appleklinika\Inventory\Infrastructure\WordPress;

/** Connection-scoped lock held through Woo session persistence at shutdown. */
final class StoreCartMutex
{
    private array $held = [];

    public function acquire(string $customer): bool
    {
        global $wpdb;
        $key = 'akcart:' . hash('sha256', $wpdb->prefix . ':' . $customer);
        $key = substr($key, 0, 64);
        if (isset($this->held[$key])) return true;
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $key)) !== 1) return false;
        $this->held[$key] = true;
        return true;
    }

    public function release(): void
    {
        global $wpdb;
        foreach (array_keys($this->held) as $key) $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $key));
        $this->held = [];
    }
}
