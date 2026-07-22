<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Persistence\WordPress;

use AppleKlinika\Buyback\Application\Migration\Migration;
use AppleKlinika\Buyback\Domain\SchemaVersion;

/** Adds the minimum operational contact fields to the existing request aggregate table. */
final class PublicRequestSchemaMigration implements Migration
{
    public function __construct(private readonly \wpdb $database)
    {
    }

    public function version(): SchemaVersion
    {
        return new SchemaVersion('1.2.0');
    }

    public function up(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = Schema::tableNames($this->database)[Schema::REQUESTS];
        $charsetCollate = $this->database->get_charset_collate();

        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            request_number varchar(32) NOT NULL,
            customer_id bigint(20) unsigned NULL,
            guest_draft_token_hash char(64) NULL,
            submission_token_hash char(64) NULL,
            customer_name varchar(191) NULL,
            customer_email varchar(191) NULL,
            customer_phone varchar(64) NULL,
            customer_note text NULL,
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
            UNIQUE KEY submission_token_hash (submission_token_hash),
            KEY customer_status (customer_id,status),
            KEY status_updated_at (status,updated_at),
            KEY model_status (model_key,status),
            KEY woo_order_id (woo_order_id),
            KEY public_contact (customer_email,customer_phone)
        ) {$charsetCollate};");

        (new SchemaInspector($this->database, $this->version()->value()))->assertTables([Schema::REQUESTS]);
    }
}
