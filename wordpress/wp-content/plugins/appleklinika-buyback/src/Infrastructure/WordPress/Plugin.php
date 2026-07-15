<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

use AppleKlinika\Buyback\Application\Diagnostics\GetDiagnosticsHandler;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\MigrationRunner;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\Schema;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\SchemaInspector;
use AppleKlinika\Buyback\Interfaces\Admin\DiagnosticsPage;

final class Plugin
{
    public function __construct(
        private readonly MigrationRunner $migrationRunner,
        private readonly DiagnosticsPage $diagnosticsPage
    ) {
    }

    public static function create(): self
    {
        global $wpdb;

        $schemaInspector = new SchemaInspector($wpdb, APPLEKLINIKA_BUYBACK_SCHEMA_VERSION);
        $handler = new GetDiagnosticsHandler(
            $schemaInspector,
            new WordPressEnvironmentDiagnosticsReader(),
            new LegacyBuybackDetector($wpdb),
            APPLEKLINIKA_BUYBACK_VERSION,
            APPLEKLINIKA_BUYBACK_SCHEMA_VERSION
        );

        return new self(
            self::migrationRunner(),
            new DiagnosticsPage($handler)
        );
    }

    public static function migrationRunner(): MigrationRunner
    {
        global $wpdb;

        return new MigrationRunner(
            $wpdb,
            APPLEKLINIKA_BUYBACK_PATH . '/migrations/versions.php',
            APPLEKLINIKA_BUYBACK_SCHEMA_VERSION
        );
    }

    public function register(): void
    {
        try {
            $this->migrationRunner->maybeMigrate();
            update_option(Schema::OPTION_PLUGIN_VERSION, APPLEKLINIKA_BUYBACK_VERSION, false);
        } catch (\Throwable $exception) {
            error_log('Apple Klinika Buyback migration failed: ' . $exception->getMessage());
            add_action('admin_notices', static function () use ($exception): void {
                echo '<div class="notice notice-error"><p>';
                echo esc_html('Apple Klinika Buyback migration failed: ' . $exception->getMessage());
                echo '</p></div>';
            });
        }

        $this->diagnosticsPage->register();
    }
}
