<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Persistence\WordPress;

use AppleKlinika\Buyback\Application\Migration\Migration;
use AppleKlinika\Buyback\Domain\SchemaVersion;

final class CoreSchemaMigration implements Migration
{
    public function __construct(private readonly \wpdb $database)
    {
    }

    public function version(): SchemaVersion
    {
        return new SchemaVersion('1.0.0');
    }

    public function up(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $tables = Schema::tableNames($this->database);
        $charsetCollate = $this->database->get_charset_collate();

        $requests = $tables[Schema::REQUESTS];
        $snapshots = $tables[Schema::SNAPSHOTS];
        $events = $tables[Schema::EVENTS];

        dbDelta("CREATE TABLE {$requests} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            request_number varchar(32) NOT NULL,
            customer_id bigint(20) unsigned NULL,
            guest_draft_token_hash char(64) NULL,
            category varchar(32) NOT NULL,
            model_key varchar(100) NOT NULL,
            device_display_name varchar(191) NOT NULL,
            service_mode varchar(40) NOT NULL,
            handover_method varchar(40) NULL,
            status varchar(40) NOT NULL,
            current_assessment_id bigint(20) unsigned NULL,
            selected_preliminary_offer_id bigint(20) unsigned NULL,
            current_final_offer_id bigint(20) unsigned NULL,
            payout_status varchar(40) NULL,
            trade_in_credit_id bigint(20) unsigned NULL,
            woo_order_id bigint(20) unsigned NULL,
            source varchar(40) NOT NULL,
            legacy_reference varchar(191) NULL,
            demo_marker varchar(100) NULL,
            version bigint(20) unsigned NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            submitted_at datetime NULL,
            closed_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY request_number (request_number),
            UNIQUE KEY legacy_reference (legacy_reference),
            KEY customer_status (customer_id,status),
            KEY status_updated_at (status,updated_at),
            KEY model_status (model_key,status),
            KEY woo_order_id (woo_order_id)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$snapshots} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            request_id bigint(20) unsigned NOT NULL,
            snapshot_type varchar(50) NOT NULL,
            schema_version varchar(20) NOT NULL,
            payload_json longtext NOT NULL,
            created_by_type varchar(32) NOT NULL,
            created_by_id bigint(20) unsigned NULL,
            checksum char(64) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY request_id (request_id),
            KEY request_type (request_id,snapshot_type),
            KEY checksum (checksum)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$events} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            request_id bigint(20) unsigned NOT NULL,
            event_type varchar(80) NOT NULL,
            from_status varchar(40) NULL,
            to_status varchar(40) NULL,
            actor_type varchar(32) NOT NULL,
            actor_id bigint(20) unsigned NULL,
            public_summary text NULL,
            private_payload_json longtext NULL,
            correlation_id varchar(100) NULL,
            idempotency_key varchar(191) NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY request_created_at (request_id,created_at),
            KEY event_type (event_type),
            UNIQUE KEY idempotency_key (idempotency_key)
        ) {$charsetCollate};");

        (new SchemaInspector($this->database, $this->version()->value()))->assertRequiredSchema();
    }
}
