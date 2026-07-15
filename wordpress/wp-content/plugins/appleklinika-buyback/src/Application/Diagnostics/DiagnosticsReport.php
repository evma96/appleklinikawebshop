<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Diagnostics;

final class DiagnosticsReport
{
    /**
     * @param array<string, array{
     *     name: string,
     *     exists: bool,
     *     row_count: int,
     *     missing_columns: array<int, string>,
     *     missing_indexes: array<int, string>
     * }> $tables
     * @param array{active_theme: string, woocommerce_active: bool} $environment
     * @param array{
     *     meta_key_exists: bool,
     *     user_count: int,
     *     record_count: int,
     *     records: array<int, array{id: string, marker: string}>,
     *     known_demo_detected: bool
     * } $legacy
     */
    public function __construct(
        public readonly string $pluginVersion,
        public readonly string $codeSchemaVersion,
        public readonly string $installedSchemaVersion,
        public readonly string $migrationStatus,
        public readonly array $tables,
        public readonly array $environment,
        public readonly array $legacy
    ) {
    }
}
