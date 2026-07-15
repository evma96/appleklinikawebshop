<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

interface SchemaDiagnosticsReader
{
    public function installedVersion(): string;

    public function migrationStatus(): string;

    /**
     * @return array<string, array{
     *     name: string,
     *     exists: bool,
     *     row_count: int,
     *     missing_columns: array<int, string>,
     *     missing_indexes: array<int, string>
     * }>
     */
    public function tables(): array;
}
