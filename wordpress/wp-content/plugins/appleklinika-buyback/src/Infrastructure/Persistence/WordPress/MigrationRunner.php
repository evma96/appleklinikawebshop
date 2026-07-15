<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Persistence\WordPress;

use AppleKlinika\Buyback\Application\Migration\Migration;
use AppleKlinika\Buyback\Domain\SchemaVersion;

final class MigrationRunner
{
    private const LOCK_TTL_SECONDS = 300;

    public function __construct(
        private readonly \wpdb $database,
        private readonly string $migrationConfigFile,
        private readonly string $codeSchemaVersion
    ) {
    }

    public function maybeMigrate(): bool
    {
        $installed = new SchemaVersion((string) get_option(Schema::OPTION_SCHEMA_VERSION, '0.0.0'));
        $code = new SchemaVersion($this->codeSchemaVersion);

        if (! $code->isNewerThan($installed)) {
            return false;
        }

        $this->run();

        return true;
    }

    public function run(): void
    {
        $this->acquireLock();

        try {
            $installed = new SchemaVersion((string) get_option(Schema::OPTION_SCHEMA_VERSION, '0.0.0'));
            $code = new SchemaVersion($this->codeSchemaVersion);

            if ($installed->isNewerThan($code)) {
                throw new \RuntimeException('Installed buyback schema is newer than the running plugin code.');
            }

            foreach ($this->migrations() as $migration) {
                if (! $migration->version()->isNewerThan($installed)) {
                    continue;
                }

                if ($migration->version()->isNewerThan($code)) {
                    throw new \RuntimeException('A configured migration is newer than the code schema version.');
                }

                $migration->up();
                update_option(Schema::OPTION_SCHEMA_VERSION, $migration->version()->value(), false);
                $installed = $migration->version();
            }

            if ($installed->value() !== $code->value()) {
                throw new \RuntimeException('No migration produced the declared code schema version.');
            }

            delete_option(Schema::OPTION_MIGRATION_ERROR);
        } catch (\Throwable $exception) {
            update_option(
                Schema::OPTION_MIGRATION_ERROR,
                [
                    'message' => sanitize_text_field($exception->getMessage()),
                    'occurred_at' => current_time('mysql', true),
                ],
                false
            );

            throw $exception;
        } finally {
            delete_option(Schema::OPTION_MIGRATION_LOCK);
        }
    }

    /**
     * @return array<int, Migration>
     */
    private function migrations(): array
    {
        $migrationClasses = require $this->migrationConfigFile;

        if (! is_array($migrationClasses)) {
            throw new \RuntimeException('Buyback migration configuration must return an array.');
        }

        $migrations = [];

        foreach ($migrationClasses as $migrationClass) {
            if (! is_string($migrationClass) || ! is_a($migrationClass, Migration::class, true)) {
                throw new \RuntimeException('Invalid buyback migration class configured.');
            }

            $migrations[] = new $migrationClass($this->database);
        }

        usort(
            $migrations,
            static fn (Migration $left, Migration $right): int => version_compare(
                $left->version()->value(),
                $right->version()->value()
            )
        );

        return $migrations;
    }

    private function acquireLock(): void
    {
        $now = time();

        if (add_option(Schema::OPTION_MIGRATION_LOCK, $now, '', false)) {
            return;
        }

        $existingLock = (int) get_option(Schema::OPTION_MIGRATION_LOCK, 0);

        if ($existingLock > 0 && ($now - $existingLock) < self::LOCK_TTL_SECONDS) {
            throw new \RuntimeException('A buyback schema migration is already running.');
        }

        delete_option(Schema::OPTION_MIGRATION_LOCK);

        if (! add_option(Schema::OPTION_MIGRATION_LOCK, $now, '', false)) {
            throw new \RuntimeException('Unable to acquire the buyback schema migration lock.');
        }
    }
}
