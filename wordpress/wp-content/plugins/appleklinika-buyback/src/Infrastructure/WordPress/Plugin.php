<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

use AppleKlinika\Buyback\Application\Diagnostics\GetDiagnosticsHandler;
use AppleKlinika\Buyback\Application\Handler\AddDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Handler\ActivateDraftPriceBookHandler;
use AppleKlinika\Buyback\Application\Handler\CreateDraftPriceBookHandler;
use AppleKlinika\Buyback\Application\Handler\ClonePriceBookToDraftHandler;
use AppleKlinika\Buyback\Application\Handler\DiscardDraftPriceBookHandler;
use AppleKlinika\Buyback\Application\Handler\ProtectPriceBookHandler;
use AppleKlinika\Buyback\Application\Handler\SaveDraftBasePriceMatrixHandler;
use AppleKlinika\Buyback\Application\Handler\SaveDraftModelMinimumOfferHandler;
use AppleKlinika\Buyback\Application\Handler\SaveDraftQuestionnaireConditionsHandler;
use AppleKlinika\Buyback\Application\Handler\SaveDraftBatteryBandsHandler;
use AppleKlinika\Buyback\Application\Handler\SaveDraftOfferModeModifiersHandler;
use AppleKlinika\Buyback\Application\Handler\SaveOfferModeSettingsHandler;
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
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressDraftPriceBookDiscardRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPricingRuleRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressTransactionManager;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPriceBookLifecycleRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPublicBuybackRequestStore;
use AppleKlinika\Buyback\Infrastructure\Inventory\WordPressDeviceCatalogReader;
use AppleKlinika\Buyback\Infrastructure\Time\SystemClock;
use AppleKlinika\Buyback\Interfaces\Admin\AdminAuthorization;
use AppleKlinika\Buyback\Interfaces\Admin\AdminSubmissionGuard;
use AppleKlinika\Buyback\Interfaces\Admin\DiagnosticsPage;
use AppleKlinika\Buyback\Interfaces\Admin\PriceBooksPage;
use AppleKlinika\Buyback\Interfaces\Admin\BuybackRequestsPage;
use AppleKlinika\Buyback\Interfaces\Admin\PreviewCalculationFormParser;
use AppleKlinika\Buyback\Interfaces\Admin\OfferModeSettingsPage;
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
        private readonly BuybackRequestsPage $requestsPage,
        private readonly LocalDemoModule $localDemoModule,
        private readonly OfferModeSettingsPage $offerModeSettingsPage
    ) {
    }

    public static function create(): self
    {
        global $wpdb;

        $transactions = new WordPressTransactionManager($wpdb);
        $books = new WordPressPriceBookRepository($wpdb, $transactions);
        $lifecycle = new WordPressPriceBookLifecycleRepository($wpdb);
        $rules = new WordPressPricingRuleRepository($wpdb);
        $clock = new SystemClock();
        $catalog = new WordPressDeviceCatalogReader();
        $questionnaire = new LocalDemoQuestionnaire();
        $offerModeSettings = new WordPressOfferModeSettingsStore();
        $offerModes = $offerModeSettings->get();
        $readiness = new PriceBookActivationReadinessService($catalog, new PriceBookActivationReadinessEvaluator());
        $activeResolver = new RepositoryActivePriceBookResolver($books, $rules);
        $activationHandler = new ActivateDraftPriceBookHandler(
            $books,
            $rules,
            $readiness,
            new MySqlPriceBookActivationLock($wpdb),
            $transactions,
            $clock,
            $lifecycle
        );
        $handler = new GetDiagnosticsHandler(
            new SchemaInspector($wpdb, APPLEKLINIKA_BUYBACK_SCHEMA_VERSION),
            new WordPressEnvironmentDiagnosticsReader(),
            new LegacyBuybackDetector($wpdb),
            APPLEKLINIKA_BUYBACK_VERSION,
            APPLEKLINIKA_BUYBACK_SCHEMA_VERSION,
            $activeResolver,
            $clock,
            new WordPressBuybackMailDiagnosticsReader(BuybackSmtpConfiguration::fromEnvironment(), new WordPressPublicBuybackRequestStore($wpdb))
        );

        return new self(
            self::migrationRunner(),
            new DiagnosticsPage($handler),
            new PriceBooksPage(
                $books,
                $rules,
                $catalog,
                new CreateDraftPriceBookHandler($books, $transactions, $clock),
                new ClonePriceBookToDraftHandler($books, $rules, $transactions, $clock, $lifecycle),
                new DiscardDraftPriceBookHandler($books, new WordPressDraftPriceBookDiscardRepository($wpdb), $transactions, $lifecycle, $clock),
                new SaveDraftBasePriceMatrixHandler($books, $rules, $catalog, $transactions, $clock),
                new SaveDraftModelMinimumOfferHandler($books, $rules, $catalog, $transactions, $clock),
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
                $questionnaire,
                $lifecycle,
                new ProtectPriceBookHandler($books, $lifecycle, $transactions, $clock),
                $offerModes
            ),
            new BuybackRequestsPage(new WordPressPublicBuybackRequestStore($wpdb), $offerModes),
            LocalDemoModule::create($offerModes),
            new OfferModeSettingsPage($offerModeSettings, new SaveOfferModeSettingsHandler($offerModeSettings), new AdminAuthorization())
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
        (new RestrictedPriceEditorAdminAccess())->register();
        $this->priceBooksPage->register();
        $this->offerModeSettingsPage->register();
        $this->requestsPage->register();
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
