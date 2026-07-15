<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

use AppleKlinika\Buyback\Application\Diagnostics\GetDiagnosticsHandler;
use AppleKlinika\Buyback\Application\Legacy\LegacyBuybackParser;
use AppleKlinika\Buyback\Application\Legacy\LegacyFieldParser;
use AppleKlinika\Buyback\Application\Legacy\LegacyReferenceFactory;
use AppleKlinika\Buyback\Application\Legacy\LegacyReportExitPolicy;
use AppleKlinika\Buyback\Application\Legacy\LegacyReportService;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\MigrationRunner;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\Schema;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\SchemaInspector;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressBuybackRequestMapper;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressBuybackRequestRepository;
use AppleKlinika\Buyback\Interfaces\Admin\DiagnosticsPage;
use AppleKlinika\Buyback\Interfaces\Cli\LegacyReportCommand;

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
        $this->registerCli();
    }

    private function registerCli(): void
    {
        if (! defined('WP_CLI') || ! WP_CLI || ! class_exists('WP_CLI')) {
            return;
        }

        global $wpdb;

        $source = new WordPressLegacyBuybackRecordSource($wpdb);
        $parser = new LegacyBuybackParser(
            new LegacyFieldParser(),
            new LegacyReferenceFactory(),
            new NullLegacyModelResolver()
        );
        $repository = new WordPressBuybackRequestRepository(
            $wpdb,
            new WordPressBuybackRequestMapper()
        );

        \WP_CLI::add_command(
            'ak-buyback legacy-report',
            new LegacyReportCommand(
                new LegacyReportService($source, $parser, $repository),
                new LegacyReportExitPolicy()
            )
        );
    }
}
