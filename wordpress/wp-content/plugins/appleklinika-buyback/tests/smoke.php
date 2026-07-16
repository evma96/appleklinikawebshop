<?php

declare(strict_types=1);

$wordpressRoot = dirname(__DIR__, 4);
require_once $wordpressRoot . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

const AK_BUYBACK_PLUGIN_BASENAME = 'appleklinika-buyback/appleklinika-buyback.php';
const AK_BUYBACK_LEGACY_META_KEY = 'appleklinika_buyback_records';

/**
 * @throws RuntimeException
 */
function akBuybackAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }

    echo "PASS: {$message}\n";
}

function akBuybackLegacyHash(wpdb $database): string
{
    $rows = $database->get_results(
        $database->prepare(
            "SELECT umeta_id, user_id, meta_key, meta_value
             FROM {$database->usermeta}
             WHERE meta_key = %s
             ORDER BY umeta_id ASC",
            AK_BUYBACK_LEGACY_META_KEY
        ),
        ARRAY_A
    );

    return hash('sha256', serialize(is_array($rows) ? $rows : []));
}

/**
 * @return array<string, int>
 */
function akBuybackRowCounts(array $reports): array
{
    $counts = [];

    foreach ($reports as $key => $report) {
        $counts[$key] = (int) $report['row_count'];
    }

    return $counts;
}

global $wpdb;

$legacyHashBefore = akBuybackLegacyHash($wpdb);
$originalUserId = get_current_user_id();
$failure = null;

try {
    if (is_plugin_active(AK_BUYBACK_PLUGIN_BASENAME)) {
        deactivate_plugins(AK_BUYBACK_PLUGIN_BASENAME);
    }

    $activationResult = activate_plugin(AK_BUYBACK_PLUGIN_BASENAME);
    akBuybackAssert(! is_wp_error($activationResult), 'Plugin activation succeeds');
    akBuybackAssert(is_plugin_active(AK_BUYBACK_PLUGIN_BASENAME), 'Plugin is active after activation');

    $runner = AppleKlinika\Buyback\Infrastructure\WordPress\Plugin::migrationRunner();
    $runner->run();

    $inspector = new AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\SchemaInspector(
        $wpdb,
        APPLEKLINIKA_BUYBACK_SCHEMA_VERSION
    );
    $firstReports = $inspector->tables();
    $firstCounts = akBuybackRowCounts($firstReports);

    akBuybackAssert($inspector->isHealthy(), 'All required tables, columns and indexes exist');
    akBuybackAssert(
        $inspector->installedVersion() === APPLEKLINIKA_BUYBACK_SCHEMA_VERSION,
        'Installed schema version matches code schema version'
    );

    $runner->run();
    $secondReports = $inspector->tables();

    akBuybackAssert($inspector->isHealthy(), 'Second migration run leaves schema healthy');
    akBuybackAssert(
        $firstCounts === akBuybackRowCounts($secondReports),
        'Second migration run does not create duplicate business rows'
    );

    $legacyDetector = new AppleKlinika\Buyback\Infrastructure\WordPress\LegacyBuybackDetector($wpdb);
    $legacySummary = $legacyDetector->summary();

    akBuybackAssert(
        $legacySummary['known_demo_detected'] === true,
        'Known legacy demo record is detected by sanitized ID'
    );

    $requestsTable = AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\Schema::tableNames($wpdb)[
        AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\Schema::REQUESTS
    ];
    $importedDemoCount = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$requestsTable}` WHERE legacy_reference = %s OR demo_marker = %s",
            AppleKlinika\Buyback\Infrastructure\WordPress\LegacyBuybackDetector::KNOWN_DEMO_RECORD_ID,
            'account-test-profile-v1'
        )
    );
    akBuybackAssert($importedDemoCount === 0, 'Known legacy demo record is not imported');

    $schemaReader = $inspector;
    $activePriceBookResolver = new AppleKlinika\Buyback\Application\Pricing\RepositoryActivePriceBookResolver(
        new AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPriceBookRepository($wpdb),
        new AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPricingRuleRepository($wpdb)
    );
    $handler = new AppleKlinika\Buyback\Application\Diagnostics\GetDiagnosticsHandler(
        $schemaReader,
        new AppleKlinika\Buyback\Infrastructure\WordPress\WordPressEnvironmentDiagnosticsReader(),
        $legacyDetector,
        APPLEKLINIKA_BUYBACK_VERSION,
        APPLEKLINIKA_BUYBACK_SCHEMA_VERSION,
        $activePriceBookResolver,
        new AppleKlinika\Buyback\Infrastructure\Time\SystemClock()
    );
    $diagnosticsPage = new AppleKlinika\Buyback\Interfaces\Admin\DiagnosticsPage($handler);

    wp_set_current_user(0);
    akBuybackAssert(! $diagnosticsPage->canView(), 'Logged-out visitor cannot access diagnostics');

    $customerIds = get_users([
        'role' => 'customer',
        'number' => 1,
        'fields' => 'ids',
    ]);
    akBuybackAssert($customerIds !== [], 'A customer exists for unauthorized capability verification');
    wp_set_current_user((int) $customerIds[0]);
    akBuybackAssert(! $diagnosticsPage->canView(), 'Customer cannot access diagnostics');

    $administratorIds = get_users([
        'role' => 'administrator',
        'number' => 1,
        'fields' => 'ids',
    ]);
    akBuybackAssert($administratorIds !== [], 'An administrator exists for capability verification');
    wp_set_current_user((int) $administratorIds[0]);
    akBuybackAssert($diagnosticsPage->canView(), 'Administrator capability can access diagnostics');

    $shopManager = get_role('shop_manager');
    if ($shopManager instanceof WP_Role) {
        akBuybackAssert(
            $shopManager->has_cap(
                AppleKlinika\Buyback\Infrastructure\WordPress\CapabilityManager::VIEW_DIAGNOSTICS
            ),
            'Shop manager receives the read-only diagnostics capability'
        );
    }

    $diagnosticsHashBefore = akBuybackLegacyHash($wpdb);
    $report = $handler->handle(new AppleKlinika\Buyback\Application\Diagnostics\GetDiagnosticsQuery());
    akBuybackAssert($report->migrationStatus === 'Rendben', 'Diagnostics reports a healthy migration state');

    ob_start();
    $diagnosticsPage->render();
    $diagnosticsHtml = (string) ob_get_clean();
    akBuybackAssert(
        str_contains($diagnosticsHtml, 'Apple Klinika Buyback diagnosztika')
        && str_contains($diagnosticsHtml, 'ak-buyback-account-test-profile-v1'),
        'Diagnostics page renders schema and sanitized legacy information'
    );
    akBuybackAssert(
        $diagnosticsHashBefore === akBuybackLegacyHash($wpdb),
        'Diagnostics query and rendering do not mutate legacy user meta'
    );

    deactivate_plugins(AK_BUYBACK_PLUGIN_BASENAME);
    akBuybackAssert(! is_plugin_active(AK_BUYBACK_PLUGIN_BASENAME), 'Plugin deactivation succeeds');
    $administratorRole = get_role('administrator');
    akBuybackAssert(
        $administratorRole instanceof WP_Role
        && ! $administratorRole->has_cap(
            AppleKlinika\Buyback\Infrastructure\WordPress\CapabilityManager::VIEW_DIAGNOSTICS
        ),
        'Plugin deactivation revokes the diagnostics capability'
    );
    akBuybackAssert($inspector->isHealthy(), 'All Phase 1A tables remain after deactivation');
    akBuybackAssert(
        function_exists('appleklinika_render_sell_account_endpoint')
        && has_action(
            'woocommerce_account_beszamitasaim_endpoint',
            'appleklinika_render_sell_account_endpoint'
        ) !== false,
        'Theme-owned Beszámítás account renderer remains registered after plugin deactivation'
    );
    akBuybackAssert(
        $legacyHashBefore === akBuybackLegacyHash($wpdb),
        'Legacy user meta remains byte/value-equivalent after deactivation'
    );

    $reactivationResult = activate_plugin(AK_BUYBACK_PLUGIN_BASENAME);
    akBuybackAssert(! is_wp_error($reactivationResult), 'Plugin reactivation succeeds');
    akBuybackAssert(is_plugin_active(AK_BUYBACK_PLUGIN_BASENAME), 'Plugin is active after final reactivation');
    $administratorRole = get_role('administrator');
    akBuybackAssert(
        $administratorRole instanceof WP_Role
        && $administratorRole->has_cap(
            AppleKlinika\Buyback\Infrastructure\WordPress\CapabilityManager::VIEW_DIAGNOSTICS
        ),
        'Plugin reactivation restores the diagnostics capability'
    );
    akBuybackAssert($inspector->isHealthy(), 'Schema remains healthy after final reactivation');
    akBuybackAssert(
        $legacyHashBefore === akBuybackLegacyHash($wpdb),
        'Legacy user meta remains byte/value-equivalent after the complete smoke test'
    );
} catch (Throwable $exception) {
    $failure = $exception;
} finally {
    wp_set_current_user($originalUserId);

    if (! is_plugin_active(AK_BUYBACK_PLUGIN_BASENAME)) {
        $recoveryResult = activate_plugin(AK_BUYBACK_PLUGIN_BASENAME);

        if (is_wp_error($recoveryResult) && $failure === null) {
            $failure = new RuntimeException($recoveryResult->get_error_message());
        }
    }
}

if ($failure instanceof Throwable) {
    fwrite(STDERR, 'FAIL: ' . $failure->getMessage() . "\n");
    exit(1);
}

echo "Buyback Phase 1A smoke test completed successfully.\n";
