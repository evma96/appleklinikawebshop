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
     * @param array{
     *     status: string,
     *     book_id: int|null,
     *     version_number: int|null,
     *     label: string|null,
     *     active_rule_count: int,
     *     supported_configuration_count: int,
     *     effective_from: string|null
     * } $pricing
     * @param array{configured:bool,host:string,port:string,encryption:string,username:string,from:string,admin:string,missing:list<string>,last_customer:string,last_admin:string} $mail
     */
    public function __construct(
        public readonly string $pluginVersion,
        public readonly string $codeSchemaVersion,
        public readonly string $installedSchemaVersion,
        public readonly string $migrationStatus,
        public readonly array $tables,
        public readonly array $environment,
        public readonly array $legacy,
        public readonly array $pricing,
        public readonly array $mail
    ) {
    }
}
