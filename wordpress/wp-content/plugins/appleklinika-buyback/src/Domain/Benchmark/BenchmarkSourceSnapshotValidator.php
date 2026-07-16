<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Domain\Benchmark;

use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class BenchmarkSourceSnapshotValidator
{
    /** @var list<string> */
    private const REQUIRED_KEYS = [
        'source',
        'source_urls',
        'captured_at',
        'capture_method',
        'source_page_marker',
        'supported_device_categories',
        'iphone_models',
        'configuration_options',
        'condition_question_tree',
        'payout_modes',
        'handover_modes',
        'offer_semantics',
        'raw_reference_observations',
        'unsupported_or_inaccessible_paths',
        'evidence_and_confidence_notes',
    ];

    /** @var list<string> */
    private const FORBIDDEN_KEYS = [
        'cookie', 'cookies', 'session_token', 'token', 'authorization', 'auth_header',
        'email', 'phone', 'address', 'account_id', 'user_id',
    ];

    /** @param array<string, mixed> $snapshot */
    public function validate(array $snapshot): void
    {
        foreach (self::REQUIRED_KEYS as $key) {
            if (! array_key_exists($key, $snapshot)) {
                throw new InvalidValueObjectException("Benchmark source snapshot is missing {$key}.");
            }
        }

        if (! is_string($snapshot['source']) || trim($snapshot['source']) === '') {
            throw new InvalidValueObjectException('Benchmark source name is required.');
        }
        if (! is_array($snapshot['source_urls']) || $snapshot['source_urls'] === []) {
            throw new InvalidValueObjectException('Benchmark source URLs are required.');
        }
        $capturedAt = new \DateTimeImmutable((string) $snapshot['captured_at']);
        if ($capturedAt->getOffset() !== 0) {
            throw new InvalidValueObjectException('Benchmark snapshot timestamp must be UTC.');
        }
        foreach (['iphone_models', 'condition_question_tree', 'raw_reference_observations'] as $arrayKey) {
            if (! is_array($snapshot[$arrayKey])) {
                throw new InvalidValueObjectException("Benchmark snapshot {$arrayKey} must be an array.");
            }
        }

        $this->assertNoSensitiveKeys($snapshot);
    }

    private function assertNoSensitiveKeys(mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $item) {
            if (is_string($key) && in_array(strtolower($key), self::FORBIDDEN_KEYS, true)) {
                throw new InvalidValueObjectException("Benchmark snapshot contains forbidden field {$key}.");
            }
            $this->assertNoSensitiveKeys($item);
        }
    }
}
