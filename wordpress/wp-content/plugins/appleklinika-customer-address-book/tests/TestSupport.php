<?php

declare(strict_types=1);

use AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress\Schema;

require_once '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

final class AddressBookTestSupport
{
    private int $assertions = 0;

    public function assert(bool $condition, string $message): void
    {
        $this->assertions++;
        if (! $condition) {
            throw new RuntimeException('FAIL: ' . $message);
        }
    }

    public function count(): int { return $this->assertions; }

    /** @return array<string, string> */
    public function businessSnapshot(): array
    {
        global $wpdb;
        $queries = [
            'orders' => "SELECT id,type,status FROM {$wpdb->prefix}wc_orders ORDER BY id",
            'buyback_requests' => "SELECT id,request_number,status,version FROM {$wpdb->prefix}ak_buyback_requests ORDER BY id",
            'buyback_snapshots' => "SELECT id,request_id,checksum FROM {$wpdb->prefix}ak_buyback_snapshots ORDER BY id",
            'buyback_events' => "SELECT id,request_id,event_type,created_at FROM {$wpdb->prefix}ak_buyback_events ORDER BY id",
            'price_books' => "SELECT * FROM {$wpdb->prefix}ak_buyback_price_books ORDER BY id",
            'price_rules' => "SELECT * FROM {$wpdb->prefix}ak_buyback_price_rules ORDER BY id",
        ];
        $snapshot = [];
        foreach ($queries as $key => $sql) {
            $snapshot[$key] = hash('sha256', wp_json_encode($wpdb->get_results($sql, ARRAY_A)) ?: '');
        }
        return $snapshot;
    }

    public function createUser(string $marker): int
    {
        $login = 'ak-address-' . sanitize_key($marker) . '-' . wp_generate_password(8, false, false);
        $id = wp_insert_user([
            'user_login' => $login,
            'user_email' => $login . '@example.test',
            'user_pass' => wp_generate_password(24, true, true),
            'role' => 'customer',
        ]);
        if (is_wp_error($id)) {
            throw new RuntimeException($id->get_error_message());
        }
        return (int) $id;
    }

    public function cleanupUser(int $userId): void
    {
        global $wpdb;
        $tables = Schema::tables($wpdb);
        $wpdb->delete($tables['defaults'], ['customer_id' => $userId]);
        $wpdb->delete($tables['addresses'], ['customer_id' => $userId]);
        wp_delete_user($userId);
    }

    /** @return array<string, mixed> */
    public function addressData(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Otthon',
            'capabilities' => 3,
            'first_name' => 'Teszt',
            'last_name' => 'Vásárló',
            'company_name' => '',
            'tax_number' => '',
            'country' => 'HU',
            'state' => 'Budapest',
            'postcode' => '1111',
            'city' => 'Budapest',
            'address_1' => 'Teszt utca',
            'address_2' => '',
            'house_number' => '1',
            'staircase' => '',
            'floor' => '',
            'door' => '',
            'phone' => '+36 30 123 4567',
            'email' => 'teszt@example.test',
            'status' => 'active',
            'source' => 'account',
        ], $overrides);
    }
}
