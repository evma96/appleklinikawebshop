<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Persistence\WordPress;

use AppleKlinika\Buyback\Application\Port\SchemaDiagnosticsReader;

final class SchemaInspector implements SchemaDiagnosticsReader
{
    public function __construct(
        private readonly \wpdb $database,
        private readonly string $codeSchemaVersion
    ) {
    }

    public function installedVersion(): string
    {
        return (string) get_option(Schema::OPTION_SCHEMA_VERSION, '0.0.0');
    }

    public function migrationStatus(): string
    {
        $error = get_option(Schema::OPTION_MIGRATION_ERROR, []);

        if (is_array($error) && isset($error['message']) && $error['message'] !== '') {
            return 'Hiba: ' . sanitize_text_field((string) $error['message']);
        }

        if ($this->installedVersion() !== $this->codeSchemaVersion) {
            return 'Migráció szükséges';
        }

        return $this->isHealthy() ? 'Rendben' : 'Hiányos adatbázisséma';
    }

    public function tables(): array
    {
        $reports = [];
        $tableNames = Schema::tableNames($this->database);
        $requiredColumns = Schema::requiredColumns();
        $requiredIndexes = Schema::requiredIndexes();

        foreach ($tableNames as $key => $tableName) {
            $exists = $this->tableExists($tableName);
            $columns = $exists ? $this->columnNames($tableName) : [];
            $indexes = $exists ? $this->indexNames($tableName) : [];

            $reports[$key] = [
                'name' => $tableName,
                'exists' => $exists,
                'row_count' => $exists ? $this->rowCount($tableName) : 0,
                'missing_columns' => array_values(array_diff($requiredColumns[$key], $columns)),
                'missing_indexes' => array_values(array_diff($requiredIndexes[$key], $indexes)),
            ];
        }

        return $reports;
    }

    public function isHealthy(): bool
    {
        foreach ($this->tables() as $table) {
            if (! $table['exists'] || $table['missing_columns'] !== [] || $table['missing_indexes'] !== []) {
                return false;
            }
        }

        return true;
    }

    public function assertRequiredSchema(): void
    {
        if (! $this->isHealthy()) {
            throw new \RuntimeException('Buyback core schema verification failed after migration.');
        }
    }

    private function tableExists(string $tableName): bool
    {
        $result = $this->database->get_var(
            $this->database->prepare('SHOW TABLES LIKE %s', $this->database->esc_like($tableName))
        );

        return $result === $tableName;
    }

    /**
     * @return array<int, string>
     */
    private function columnNames(string $tableName): array
    {
        $this->assertKnownTable($tableName);
        $rows = $this->database->get_results("SHOW COLUMNS FROM `{$tableName}`", ARRAY_A);

        return array_values(array_map(
            static fn (array $row): string => (string) $row['Field'],
            is_array($rows) ? $rows : []
        ));
    }

    /**
     * @return array<int, string>
     */
    private function indexNames(string $tableName): array
    {
        $this->assertKnownTable($tableName);
        $rows = $this->database->get_results("SHOW INDEX FROM `{$tableName}`", ARRAY_A);

        return array_values(array_unique(array_map(
            static fn (array $row): string => (string) $row['Key_name'],
            is_array($rows) ? $rows : []
        )));
    }

    private function rowCount(string $tableName): int
    {
        $this->assertKnownTable($tableName);

        return (int) $this->database->get_var("SELECT COUNT(*) FROM `{$tableName}`");
    }

    private function assertKnownTable(string $tableName): void
    {
        if (! in_array($tableName, Schema::tableNames($this->database), true)) {
            throw new \InvalidArgumentException('Unknown buyback table requested.');
        }
    }
}
