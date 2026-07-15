<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

use AppleKlinika\Buyback\Application\Legacy\LegacyBuybackRecord;
use AppleKlinika\Buyback\Application\Legacy\LegacySourceResult;
use AppleKlinika\Buyback\Application\Port\LegacyBuybackRecordSource;

final class WordPressLegacyBuybackRecordSource implements LegacyBuybackRecordSource
{
    public const META_KEY = 'appleklinika_buyback_records';

    public function __construct(private readonly \wpdb $database)
    {
    }

    public function read(?int $userId = null): LegacySourceResult
    {
        if ($userId !== null && $userId <= 0) {
            throw new \InvalidArgumentException('Legacy report user ID must be positive.');
        }

        $userIds = $userId === null ? $this->userIds() : [$userId];
        $records = [];
        $scanned = 0;

        foreach ($userIds as $ownerUserId) {
            if (! metadata_exists('user', $ownerUserId, self::META_KEY)) {
                continue;
            }

            ++$scanned;
            $stored = get_user_meta($ownerUserId, self::META_KEY, true);

            foreach ($this->normalizeRecords($stored) as $index => $rawRecord) {
                $records[] = $this->record($ownerUserId, $index, $rawRecord);
            }
        }

        return new LegacySourceResult($scanned, $records);
    }

    /** @return list<int> */
    private function userIds(): array
    {
        $ids = $this->database->get_col(
            $this->database->prepare(
                "SELECT DISTINCT user_id FROM {$this->database->usermeta} WHERE meta_key = %s ORDER BY user_id ASC",
                self::META_KEY
            )
        );

        return array_map('intval', is_array($ids) ? $ids : []);
    }

    /** @return list<mixed> */
    private function normalizeRecords(mixed $stored): array
    {
        if (! is_array($stored) || $stored === []) {
            return [$stored];
        }

        if (array_key_exists('id', $stored) || array_key_exists('marker', $stored)) {
            return [$stored];
        }

        return array_values($stored);
    }

    private function record(int $userId, int $index, mixed $raw): LegacyBuybackRecord
    {
        if (! is_array($raw)) {
            return new LegacyBuybackRecord(
                $userId,
                $index,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                false,
                ['malformed_record']
            );
        }

        $issues = [];
        $values = [];

        foreach (
            ['id', 'device', 'condition', 'battery', 'estimated_offer', 'final_offer', 'status', 'created_date', 'marker']
            as $field
        ) {
            $value = $raw[$field] ?? null;

            if ($value !== null && ! is_scalar($value)) {
                $issues[] = 'non_scalar_' . $field;
                $values[$field] = null;
                continue;
            }

            $values[$field] = $value === null ? null : sanitize_text_field((string) $value);
        }

        $customer = $raw['customer'] ?? null;
        $customerMismatch = false;

        if ($customer !== null && ! is_scalar($customer)) {
            $issues[] = 'non_scalar_customer';
        } elseif (is_scalar($customer) && trim((string) $customer) !== '') {
            $owner = get_userdata($userId);
            $customerMismatch = ! $owner instanceof \WP_User
                || strtolower(trim((string) $customer)) !== strtolower((string) $owner->user_email);
        }

        return new LegacyBuybackRecord(
            $userId,
            $index,
            $values['id'],
            $values['device'],
            $values['condition'],
            $values['battery'],
            $values['estimated_offer'],
            $values['final_offer'],
            $values['status'],
            $values['created_date'],
            $values['marker'],
            $customerMismatch,
            array_values(array_unique($issues))
        );
    }
}
