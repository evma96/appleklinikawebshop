<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Persistence\WordPress;

use AppleKlinika\Buyback\Application\Migration\Migration;
use AppleKlinika\Buyback\Domain\SchemaVersion;

/** Allows a public inspection request to have no selected offer mode. */
final class ManualReviewRequestSchemaMigration implements Migration
{
    public function __construct(private readonly \wpdb $database)
    {
    }

    public function version(): SchemaVersion
    {
        return new SchemaVersion('1.3.0');
    }

    public function up(): void
    {
        $table = Schema::tableNames($this->database)[Schema::REQUESTS];
        $result = $this->database->query("ALTER TABLE `{$table}` MODIFY service_mode varchar(40) NULL");

        if ($result === false) {
            throw new \RuntimeException('Could not allow a missing service mode for manual-review requests.');
        }
    }
}
