<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

use AppleKlinika\Buyback\Application\Diagnostics\GetDiagnosticsHandler;
use AppleKlinika\Buyback\Application\Handler\AddDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Handler\ActivateDraftPriceBookHandler;
use AppleKlinika\Buyback\Application\Handler\CreateDraftPriceBookHandler;
use AppleKlinika\Buyback\Application\Handler\ClonePriceBookToDraftHandler;
use AppleKlinika\Buyback\Application\Handler\SaveDraftBasePriceMatrixHandler;
use AppleKlinika\Buyback\Application\Handler\SaveDraftQuestionnaireConditionsHandler;
use AppleKlinika\Buyback\Application\Handler\SaveDraftBatteryBandsHandler;
use AppleKlinika\Buyback\Application\Handler\SaveDraftOfferModeModifiersHandler;
use AppleKlinika\Buyback\Application\Pricing\OfferModeExampleCalculator;
use AppleKlinika\Buyback\Application\Handler\DeleteDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Handler\PreviewDraftPriceBookCalculationHandler;
use AppleKlinika\Buyback\Application\Handler\ToggleDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Handler\UpdateDraftPriceBookSettingsHandler;
use AppleKlinika\Buyback\Application\Handler\UpdateDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Legacy\LegacyBuybackParser;
use AppleKlinika\Buyback\Application\Legacy\LegacyFieldParser;
use AppleKlinika\Buyback\Application\Legacy\LegacyReferenceFactory;
use AppleKlinika\Buyback\Application\Legacy\LegacyReportExitPolicy;
use AppleKlinika\Buyback\Application\Legacy\LegacyReportService;
use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoQuestionnaire;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\MigrationRunner;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\MySqlPriceBookActivationLock;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\Schema;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\SchemaInspector;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressBuybackRequestMapper;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressBuybackRequestRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPriceBookRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPricingRuleRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressTransactionManager;
use AppleKlinika\Buyback\Infrastructure\Inventory\WordPressDeviceCatalogReader;
use AppleKlinika\Buyback\Infrastructure\Time\SystemClock;
use AppleKlinika\Buyback\Interfaces\Admin\AdminAuthorization;
use AppleKlinika\Buyback\Interfaces\Admin\AdminSubmissionGuard;
use AppleKlinika\Buyback\Interfaces\Admin\DiagnosticsPage;
use AppleKlinika\Buyback\Interfaces\Admin\PriceBooksPage;
use AppleKlinika\Buyback\Interfaces\Admin\PreviewCalculationFormParser;
use AppleKlinika\Buyback\Interfaces\Admin\PricingRuleFormParser;
use AppleKlinika\Buyback\Domain\Pricing\PricingEngine;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookActivationReadinessEvaluator;
use AppleKlinika\Buyback\Application\Pricing\PriceBookActivationReadinessService;
use AppleKlinika\Buyback\Application\Pricing\RepositoryActivePriceBookResolver;
use AppleKlinika\Buyback\Interfaces\Cli\LegacyReportCommand;

final class Plugin
{
    public function __construct(
        private readonly MigrationRunner $migrationRunner,
        private readonly DiagnosticsPage $diagnosticsPage,
        private readonly PriceBooksPage $priceBooksPage,
        private readonly LocalDemoModule $localDemoModule
    ) {
    }

    public static function create(): self
    {
        global $wpdb;

        $transactions = new WordPressTransactionManager($wpdb);
        $books = new WordPressPriceBookRepository($wpdb, $transactions);
        $rules = new WordPressPricingRuleRepository($wpdb);
        $clock = new SystemClock();
        $catalog = new WordPressDeviceCatalogReader();
        $questionnaire = new LocalDemoQuestionnaire();
        $readiness = new PriceBookActivationReadinessService($catalog, new PriceBookActivationReadinessEvaluator());
        $activeResolver = new RepositoryActivePriceBookResolver($books, $rules);
        $activationHandler = new ActivateDraftPriceBookHandler(
            $books,
            $rules,
            $readiness,
            new MySqlPriceBookActivationLock($wpdb),
            $transactions,
            $clock
        );
        $handler = new GetDiagnosticsHandler(
            new SchemaInspector($wpdb, APPLEKLINIKA_BUYBACK_SCHEMA_VERSION),
            new WordPressEnvironmentDiagnosticsReader(),
            new LegacyBuybackDetector($wpdb),
            APPLEKLINIKA_BUYBACK_VERSION,
            APPLEKLINIKA_BUYBACK_SCHEMA_VERSION,
            $activeResolver,
            $clock
        );

        return new self(
            self::migrationRunner(),
            new DiagnosticsPage($handler),
            new PriceBooksPage(
                $books,
                $rules,
                $catalog,
                new CreateDraftPriceBookHandler($books, $transactions, $clock),
                new ClonePriceBookToDraftHandler($books, $rules, $transactions, $clock),
                new SaveDraftBasePriceMatrixHandler($books, $rules, $catalog, $transactions, $clock),
                new SaveDraftQuestionnaireConditionsHandler($books, $rules, $transactions, $clock, $questionnaire, $catalog),
                new SaveDraftBatteryBandsHandler($books, $rules, $transactions, $clock, $questionnaire, $catalog),
                new SaveDraftOfferModeModifiersHandler($books, $rules, $transactions, $clock),
                new OfferModeExampleCalculator(new PricingEngine()),
                new UpdateDraftPriceBookSettingsHandler($books, $clock),
                new AddDraftPricingRuleHandler($books, $rules, $transactions, $clock),
                new UpdateDraftPricingRuleHandler($books, $rules, $transactions, $clock),
                new ToggleDraftPricingRuleHandler($books, $rules, $transactions, $clock),
                new DeleteDraftPricingRuleHandler($books, $rules, $transactions, $clock),
                new PricingRuleFormParser(),
                new PreviewDraftPriceBookCalculationHandler($books, $rules, $catalog, new PricingEngine(), $questionnaire),
                new PreviewCalculationFormParser($questionnaire),
                $readiness,
                $activationHandler,
                $activeResolver,
                $clock,
                new AdminAuthorization(),
                new AdminSubmissionGuard(),
                $questionnaire
            ),
            LocalDemoModule::create()
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
            (new CapabilityManager())->grant();
        } catch (\Throwable $exception) {
            error_log('Apple Klinika Buyback migration failed: ' . $exception->getMessage());
            add_action('admin_notices', static function () use ($exception): void {
                echo '<div class="notice notice-error"><p>';
                echo esc_html('Apple Klinika Buyback migration failed: ' . $exception->getMessage());
                echo '</p></div>';
            });
        }

        $this->diagnosticsPage->register();
        $this->priceBooksPage->register();
        $this->localDemoModule->register();
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
