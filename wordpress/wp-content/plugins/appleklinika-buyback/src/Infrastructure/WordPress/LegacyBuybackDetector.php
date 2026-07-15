<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

use AppleKlinika\Buyback\Application\Port\LegacyDiagnosticsReader;

final class LegacyBuybackDetector implements LegacyDiagnosticsReader
{
    public const META_KEY = 'appleklinika_buyback_records';
    public const KNOWN_DEMO_RECORD_ID = 'ak-buyback-account-test-profile-v1';

    public function __construct(private readonly \wpdb $database)
    {
    }

    public function summary(): array
    {
        $userIds = $this->database->get_col(
            $this->database->prepare(
                "SELECT DISTINCT user_id FROM {$this->database->usermeta} WHERE meta_key = %s ORDER BY user_id ASC",
                self::META_KEY
            )
        );
        $userIds = is_array($userIds) ? $userIds : [];

        $records = [];

        foreach ($userIds as $userId) {
            foreach ($this->recordsForUser((int) $userId) as $record) {
                $records[] = [
                    'id' => $this->safeReference($record['id'] ?? ''),
                    'marker' => $this->safeReference($record['marker'] ?? ''),
                ];
            }
        }

        $knownDemoDetected = false;

        foreach ($records as $record) {
            if ($record['id'] === self::KNOWN_DEMO_RECORD_ID) {
                $knownDemoDetected = true;
                break;
            }
        }

        return [
            'meta_key_exists' => $userIds !== [],
            'user_count' => count($userIds),
            'record_count' => count($records),
            'records' => $records,
            'known_demo_detected' => $knownDemoDetected,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recordsForUser(int $userId): array
    {
        $stored = get_user_meta($userId, self::META_KEY, true);

        if (! is_array($stored) || $stored === []) {
            return [];
        }

        if (isset($stored['id']) || isset($stored['marker'])) {
            return [$stored];
        }

        return array_values(array_filter($stored, 'is_array'));
    }

    private function safeReference(mixed $value): string
    {
        return substr(sanitize_text_field((string) $value), 0, 191);
    }
}
