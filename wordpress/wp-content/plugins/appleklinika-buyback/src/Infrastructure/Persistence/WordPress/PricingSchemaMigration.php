<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Persistence\WordPress;

use AppleKlinika\Buyback\Application\Migration\Migration;
use AppleKlinika\Buyback\Domain\SchemaVersion;

final class PricingSchemaMigration implements Migration
{
    public function __construct(private readonly \wpdb $database)
    {
    }

    public function version(): SchemaVersion
    {
        return new SchemaVersion('1.1.0');
    }

    public function up(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $tables = Schema::tableNames($this->database);
        $charsetCollate = $this->database->get_charset_collate();
        $priceBooks = $tables[Schema::PRICE_BOOKS];
        $priceRules = $tables[Schema::PRICE_RULES];

        dbDelta("CREATE TABLE {$priceBooks} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            version_number int(10) unsigned NOT NULL,
            label varchar(120) NOT NULL,
            status varchar(20) NOT NULL,
            currency char(3) NOT NULL,
            effective_from datetime NULL,
            effective_to datetime NULL,
            minimum_offer_minor bigint(20) unsigned NOT NULL DEFAULT 0,
            rounding_increment_minor int(10) unsigned NOT NULL DEFAULT 1,
            minimum_policy varchar(24) NOT NULL,
            created_by bigint(20) unsigned NOT NULL,
            activated_by bigint(20) unsigned NULL,
            retired_by bigint(20) unsigned NULL,
            version int(10) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            activated_at datetime NULL,
            retired_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY version_number (version_number),
            KEY status_effective_from (status,effective_from),
            KEY status_updated_at (status,updated_at)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$priceRules} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            price_book_id bigint(20) unsigned NOT NULL,
            rule_code varchar(64) NOT NULL,
            rule_kind varchar(32) NOT NULL,
            category varchar(32) NOT NULL,
            model_key varchar(64) NULL,
            storage_gb int(10) unsigned NULL,
            service_mode varchar(32) NULL,
            condition_key varchar(64) NULL,
            comparison_operator varchar(16) NULL,
            comparison_value_json longtext NULL,
            amount_minor bigint(20) NULL,
            multiplier_bps int(10) unsigned NULL,
            priority int(11) NOT NULL DEFAULT 100,
            is_enabled tinyint(1) NOT NULL DEFAULT 1,
            public_label varchar(160) NULL,
            internal_note text NULL,
            version int(10) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY book_rule_code (price_book_id,rule_code),
            KEY book_kind (price_book_id,rule_kind),
            KEY book_model_storage (price_book_id,model_key,storage_gb),
            KEY book_priority (price_book_id,priority,id),
            KEY category_model (category,model_key)
        ) {$charsetCollate};");

        (new SchemaInspector($this->database, $this->version()->value()))->assertTables([
            Schema::PRICE_BOOKS,
            Schema::PRICE_RULES,
        ]);
    }
}
