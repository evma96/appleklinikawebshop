<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Persistence\WordPress;

use AppleKlinika\Buyback\Application\Migration\Migration;
use AppleKlinika\Buyback\Domain\SchemaVersion;

final class PriceBookLifecycleSchemaMigration implements Migration
{
    public function __construct(private readonly \wpdb $database) {}

    public function version(): SchemaVersion { return new SchemaVersion('1.4.0'); }

    public function up(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $tables = Schema::tableNames($this->database);
        $collation = $this->database->get_charset_collate();
        dbDelta("CREATE TABLE {$tables[Schema::PRICE_BOOK_REFERENCES]} (
            currency char(3) NOT NULL,
            price_book_id bigint(20) unsigned NOT NULL,
            version int(10) unsigned NOT NULL DEFAULT 1,
            changed_by bigint(20) unsigned NOT NULL,
            changed_at datetime NOT NULL,
            PRIMARY KEY (currency),
            KEY price_book_id (price_book_id)
        ) {$collation};");
        dbDelta("CREATE TABLE {$tables[Schema::PRICE_BOOK_LIFECYCLE_EVENTS]} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            price_book_id bigint(20) unsigned NOT NULL,
            event_type varchar(64) NOT NULL,
            actor_id bigint(20) unsigned NOT NULL,
            payload_json longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY price_book_created_at (price_book_id,created_at),
            KEY event_type (event_type)
        ) {$collation};");
    }
}
