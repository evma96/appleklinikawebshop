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
    public const PRICE_BOOKS = 'ak_buyback_price_books';
    public const PRICE_RULES = 'ak_buyback_price_rules';
    public const PRICE_BOOK_REFERENCES = 'ak_buyback_price_book_references';
    public const PRICE_BOOK_LIFECYCLE_EVENTS = 'ak_buyback_price_book_lifecycle_events';

    /**
     * @return array<string, string>
     */
    public static function tableNames(\wpdb $database): array
    {
        return [
            self::REQUESTS => $database->prefix . self::REQUESTS,
            self::SNAPSHOTS => $database->prefix . self::SNAPSHOTS,
            self::EVENTS => $database->prefix . self::EVENTS,
            self::PRICE_BOOKS => $database->prefix . self::PRICE_BOOKS,
            self::PRICE_RULES => $database->prefix . self::PRICE_RULES,
            self::PRICE_BOOK_REFERENCES => $database->prefix . self::PRICE_BOOK_REFERENCES,
            self::PRICE_BOOK_LIFECYCLE_EVENTS => $database->prefix . self::PRICE_BOOK_LIFECYCLE_EVENTS,
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
                'submission_token_hash',
                'customer_name',
                'customer_email',
                'customer_phone',
                'customer_note',
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
            self::PRICE_BOOKS => [
                'id',
                'version_number',
                'label',
                'status',
                'currency',
                'effective_from',
                'effective_to',
                'minimum_offer_minor',
                'rounding_increment_minor',
                'minimum_policy',
                'created_by',
                'activated_by',
                'retired_by',
                'version',
                'created_at',
                'updated_at',
                'activated_at',
                'retired_at',
            ],
            self::PRICE_RULES => [
                'id',
                'price_book_id',
                'rule_code',
                'rule_kind',
                'category',
                'model_key',
                'storage_gb',
                'service_mode',
                'condition_key',
                'affected_component_key',
                'comparison_operator',
                'comparison_value_json',
                'amount_minor',
                'multiplier_bps',
                'priority',
                'is_enabled',
                'public_label',
                'internal_note',
                'version',
                'created_at',
                'updated_at',
            ],
            self::PRICE_BOOK_REFERENCES => [
                'currency', 'price_book_id', 'version', 'changed_by', 'changed_at',
            ],
            self::PRICE_BOOK_LIFECYCLE_EVENTS => [
                'id', 'price_book_id', 'event_type', 'actor_id', 'payload_json', 'created_at',
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
                'submission_token_hash',
                'customer_status',
                'status_updated_at',
                'model_status',
                'woo_order_id',
                'public_contact',
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
            self::PRICE_BOOKS => [
                'PRIMARY',
                'version_number',
                'status_effective_from',
                'status_updated_at',
            ],
            self::PRICE_RULES => [
                'PRIMARY',
                'book_rule_code',
                'book_kind',
                'book_model_storage',
                'book_priority',
                'category_model',
            ],
            self::PRICE_BOOK_REFERENCES => ['PRIMARY', 'price_book_id'],
            self::PRICE_BOOK_LIFECYCLE_EVENTS => ['PRIMARY', 'price_book_created_at', 'event_type'],
        ];
    }
}
