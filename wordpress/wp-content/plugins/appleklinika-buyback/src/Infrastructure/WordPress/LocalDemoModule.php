<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

use AppleKlinika\Buyback\Application\Handler\ActivateDraftPriceBookHandler;
use AppleKlinika\Buyback\Application\Handler\AddDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Handler\CreateDraftPriceBookHandler;
use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoHostGuard;
use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoQuestionnaire;
use AppleKlinika\Buyback\Application\PublicRequest\PublicBuybackRequestSubmission;
use AppleKlinika\Buyback\Application\Pricing\PriceBookActivationReadinessService;
use AppleKlinika\Buyback\Application\Pricing\RepositoryActivePriceBookResolver;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookActivationReadinessEvaluator;
use AppleKlinika\Buyback\Domain\Pricing\PricingEngine;
use AppleKlinika\Buyback\Infrastructure\Inventory\WordPressDeviceCatalogReader;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\MySqlPriceBookActivationLock;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPriceBookRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPricingRuleRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressTransactionManager;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressBuybackRequestMapper;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressBuybackRequestRepository;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressDomainEventStore;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPublicBuybackRequestStore;
use AppleKlinika\Buyback\Infrastructure\Identifier\WordPressRequestNumberGenerator;
use AppleKlinika\Buyback\Infrastructure\Time\SystemClock;
use AppleKlinika\Buyback\Interfaces\Frontend\LocalDemoCalculatorPage;

final class LocalDemoModule
{
    public function __construct(
        private readonly LocalDemoSeeder $seeder,
        private readonly LocalDemoCalculatorPage $page,
        private readonly LocalDemoHostGuard $guard = new LocalDemoHostGuard()
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
        $readiness = new PriceBookActivationReadinessService($catalog, new PriceBookActivationReadinessEvaluator());
        $activation = new ActivateDraftPriceBookHandler($books, $rules, $readiness, new MySqlPriceBookActivationLock($wpdb), $transactions, $clock);
        $localProducts = new WordPressLocalDemoProductReader();
        $requests = new WordPressBuybackRequestRepository($wpdb, new WordPressBuybackRequestMapper());
        $publicStore = new WordPressPublicBuybackRequestStore($wpdb);
        $seeder = new LocalDemoSeeder(
            $books,
            $rules,
            new CreateDraftPriceBookHandler($books, $transactions, $clock),
            new AddDraftPricingRuleHandler($books, $rules, $transactions, $clock),
            $activation,
            $localProducts,
            new WordPressLocalDemoPageGateway()
        );
        $resolver = new RepositoryActivePriceBookResolver($books, $rules);
        $questionnaire = new LocalDemoQuestionnaire();
        $submission = new PublicBuybackRequestSubmission(
            $resolver,
            new PricingEngine(),
            $questionnaire,
            $requests,
            $publicStore,
            new WordPressDomainEventStore($wpdb, new WordPressBuybackRequestMapper()),
            new WordPressRequestNumberGenerator($requests, $clock),
            $transactions,
            $clock
        );
        return new self($seeder, new LocalDemoCalculatorPage($resolver, new PricingEngine(), $catalog, $localProducts, $questionnaire, $submission, $publicStore));
    }

    public function register(): void
    {
        $this->page->register();
    }

    public function seedIfLocal(): void
    {
        try {
            $this->guard->assertLocal(site_url(), home_url());
            $this->seeder->seed();
        } catch (\Throwable $exception) {
            error_log('Apple Klinika local buyback demo unavailable: ' . $exception->getMessage());
        }
    }

    public function seeder(): LocalDemoSeeder
    {
        return $this->seeder;
    }
}
