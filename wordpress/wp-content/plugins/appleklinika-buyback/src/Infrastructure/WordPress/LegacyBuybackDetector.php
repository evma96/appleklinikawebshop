<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

use AppleKlinika\Buyback\Application\Port\LegacyDiagnosticsReader;

final class LegacyBuybackDetector implements LegacyDiagnosticsReader
{
    public const META_KEY = WordPressLegacyBuybackRecordSource::META_KEY;
    public const KNOWN_DEMO_RECORD_ID = 'ak-buyback-account-test-profile-v1';

    private readonly WordPressLegacyBuybackRecordSource $source;

    public function __construct(private readonly \wpdb $database)
    {
        $this->source = new WordPressLegacyBuybackRecordSource($database);
    }

    public function summary(): array
    {
        $source = $this->source->read();
        $records = array_map(
            static fn ($record): array => [
                'id' => substr((string) $record->recordId, 0, 191),
                'marker' => substr((string) $record->marker, 0, 191),
            ],
            $source->records
        );

        $knownDemoDetected = false;

        foreach ($records as $record) {
            if ($record['id'] === self::KNOWN_DEMO_RECORD_ID) {
                $knownDemoDetected = true;
                break;
            }
        }

        return [
            'meta_key_exists' => $source->usersScanned > 0,
            'user_count' => $source->usersScanned,
            'record_count' => count($records),
            'records' => $records,
            'known_demo_detected' => $knownDemoDetected,
        ];
    }

}
