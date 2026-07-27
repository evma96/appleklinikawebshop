<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Persistence\WordPress;

use AppleKlinika\Buyback\Application\Migration\Migration;
use AppleKlinika\Buyback\Domain\SchemaVersion;

/** Adds an explicit scope for service-history rules that target one component. */
final class ServiceHistoryComponentRulesSchemaMigration implements Migration
{
    public function __construct(private readonly \wpdb $database) {}

    public function version(): SchemaVersion
    {
        return new SchemaVersion('1.5.0');
    }

    public function up(): void
    {
        $table = Schema::tableNames($this->database)[Schema::PRICE_RULES];
        $column = $this->database->get_var($this->database->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE %s', 'affected_component_key'));
        if ($column !== null) {
            return;
        }

        $result = $this->database->query("ALTER TABLE `{$table}` ADD affected_component_key varchar(64) NULL AFTER condition_key");
        if ($result === false) {
            throw new \RuntimeException('Could not add affected component scope to pricing rules.');
        }
    }
}
