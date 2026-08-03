<?php

declare(strict_types=1);

namespace AppleKlinika\CustomerAddressBook\Infrastructure\Persistence\WordPress;

final class CoreSchemaMigration
{
    public function __construct(private readonly \wpdb $database) {}

    public function up(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $tables = Schema::tables($this->database);
        $charset = $this->database->get_charset_collate();
        dbDelta("CREATE TABLE {$tables['addresses']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            address_key VARCHAR(64) NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            label VARCHAR(80) NOT NULL,
            capabilities TINYINT UNSIGNED NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            company_name VARCHAR(200) NOT NULL DEFAULT '',
            tax_number VARCHAR(32) NOT NULL DEFAULT '',
            country CHAR(2) NOT NULL,
            state VARCHAR(100) NOT NULL DEFAULT '',
            postcode VARCHAR(32) NOT NULL,
            city VARCHAR(100) NOT NULL,
            address_1 VARCHAR(255) NOT NULL,
            address_2 VARCHAR(255) NOT NULL DEFAULT '',
            house_number VARCHAR(50) NOT NULL DEFAULT '',
            staircase VARCHAR(50) NOT NULL DEFAULT '',
            floor VARCHAR(50) NOT NULL DEFAULT '',
            door VARCHAR(50) NOT NULL DEFAULT '',
            phone VARCHAR(50) NOT NULL DEFAULT '',
            email VARCHAR(190) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL,
            version INT UNSIGNED NOT NULL,
            source VARCHAR(20) NOT NULL,
            legacy_fingerprint CHAR(64) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            last_used_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY address_key (address_key),
            KEY customer_status (customer_id,status),
            KEY customer_capabilities (customer_id,capabilities),
            KEY customer_legacy_fingerprint (customer_id,legacy_fingerprint)
        ) {$charset};");

        dbDelta("CREATE TABLE {$tables['defaults']} (
            customer_id BIGINT UNSIGNED NOT NULL,
            purpose VARCHAR(16) NOT NULL,
            address_id BIGINT UNSIGNED NOT NULL,
            version INT UNSIGNED NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (customer_id,purpose),
            KEY address_id (address_id)
        ) {$charset};");

        $this->assertComplete();
    }

    public function assertComplete(): void
    {
        $tables = Schema::tables($this->database);
        $requirements = [
            $tables['addresses'] => [
                'columns' => [
                    'id', 'address_key', 'customer_id', 'label', 'capabilities', 'first_name', 'last_name',
                    'company_name', 'tax_number', 'country', 'state', 'postcode', 'city', 'address_1',
                    'address_2', 'house_number', 'staircase', 'floor', 'door', 'phone', 'email', 'status',
                    'version', 'source', 'legacy_fingerprint', 'created_at', 'updated_at', 'last_used_at',
                ],
                'indexes' => ['PRIMARY', 'address_key', 'customer_status', 'customer_capabilities', 'customer_legacy_fingerprint'],
            ],
            $tables['defaults'] => [
                'columns' => ['customer_id', 'purpose', 'address_id', 'version', 'updated_at'],
                'indexes' => ['PRIMARY', 'address_id'],
            ],
        ];

        foreach ($requirements as $table => $required) {
            $exists = $this->database->get_var($this->database->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists !== $table) {
                throw new \RuntimeException('A címjegyzék adatbázistáblája hiányzik: ' . $table);
            }

            $columns = $this->database->get_col("SHOW COLUMNS FROM `{$table}`", 0);
            $indexes = $this->database->get_col("SHOW INDEX FROM `{$table}`", 2);
            $missingColumns = array_diff($required['columns'], array_map('strval', $columns));
            $missingIndexes = array_diff($required['indexes'], array_unique(array_map('strval', $indexes)));
            if ($missingColumns !== [] || $missingIndexes !== []) {
                throw new \RuntimeException(sprintf(
                    'A címjegyzék adatbázissémája hiányos (%s): oszlopok [%s], indexek [%s].',
                    $table,
                    implode(', ', $missingColumns),
                    implode(', ', $missingIndexes)
                ));
            }
        }
    }
}
