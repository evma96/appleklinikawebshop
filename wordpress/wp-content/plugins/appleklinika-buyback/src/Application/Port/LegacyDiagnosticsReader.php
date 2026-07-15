<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

interface LegacyDiagnosticsReader
{
    /**
     * @return array{
     *     meta_key_exists: bool,
     *     user_count: int,
     *     record_count: int,
     *     records: array<int, array{id: string, marker: string}>,
     *     known_demo_detected: bool
     * }
     */
    public function summary(): array;
}
