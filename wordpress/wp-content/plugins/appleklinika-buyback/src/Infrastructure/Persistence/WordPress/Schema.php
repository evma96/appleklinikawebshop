<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Persistence\WordPress;

final class Schema
{
    public const OPTION_PLUGIN_VERSION = 'appleklinika_buyback_plugin_version';
    public const OPTION_SCHEMA_VERSION = 'appleklinika_buyback_schema_version';
    public const OPTION_MIGRATION_ERROR = 'appleklinika_buyback_migration_error';
    public const OPTION_MIGRATION_LOCK = 'appleklinika_buyback_migration_lock';

    public const REQUESTS = 'ak_buyback_requests';
    public const SNAPSHOTS = 'ak_buyback_snapshots';
    public const EVENTS = 'ak_buyback_events';

    /**
     * @return array<string, string>
     */
    public static function tableNames(\wpdb $database): array
    {
        return [
            self::REQUESTS => $database->prefix . self::REQUESTS,
            self::SNAPSHOTS => $database->prefix . self::SNAPSHOTS,
            self::EVENTS => $database->prefix . self::EVENTS,
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function requiredColumns(): array
    {
        return [
            self::REQUESTS => [
                'id',
                'request_number',
                'customer_id',
                'guest_draft_token_hash',
                'category',
                'model_key',
                'device_display_name',
                'service_mode',
                'handover_method',
                'status',
                'current_assessment_id',
                'selected_preliminary_offer_id',
                'current_final_offer_id',
                'payout_status',
                'trade_in_credit_id',
                'woo_order_id',
                'source',
                'legacy_reference',
                'demo_marker',
                'version',
                'created_at',
                'updated_at',
                'submitted_at',
                'closed_at',
            ],
            self::SNAPSHOTS => [
                'id',
                'request_id',
                'snapshot_type',
                'schema_version',
                'payload_json',
                'created_by_type',
                'created_by_id',
                'checksum',
                'created_at',
            ],
            self::EVENTS => [
                'id',
                'request_id',
                'event_type',
                'from_status',
                'to_status',
                'actor_type',
                'actor_id',
                'public_summary',
                'private_payload_json',
                'correlation_id',
                'idempotency_key',
                'created_at',
            ],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function requiredIndexes(): array
    {
        return [
            self::REQUESTS => [
                'PRIMARY',
                'request_number',
                'legacy_reference',
                'customer_status',
                'status_updated_at',
                'model_status',
                'woo_order_id',
            ],
            self::SNAPSHOTS => [
                'PRIMARY',
                'request_id',
                'request_type',
                'checksum',
            ],
            self::EVENTS => [
                'PRIMARY',
                'request_created_at',
                'event_type',
                'idempotency_key',
            ],
        ];
    }
}
