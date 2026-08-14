<?php

declare(strict_types=1);

$wordpressRoot = dirname(__DIR__, 4);
require_once $wordpressRoot . '/wp-load.php';

final class CatalogSearchDiscoverabilityTest
{
    private int $assertions = 0;

    /** @var list<string> */
    private array $failures = [];

    public function assert(bool $condition, string $message): void
    {
        ++$this->assertions;

        if (! $condition) {
            $this->failures[] = $message;
        }
    }

    public function finish(): never
    {
        if ($this->failures !== []) {
            foreach ($this->failures as $failure) {
                fwrite(STDERR, "FAIL: {$failure}\n");
            }

            exit(1);
        }

        echo "Catalogue/search discoverability tests passed: {$this->assertions} assertions.\n";
        exit(0);
    }
}

/**
 * @param array<string, mixed> $queryVars
 * @param array<string, string|array<string>> $request
 * @return array{ids: list<int>, found: int, pages: int}
 */
function appleklinika_test_discovery_query(array $queryVars, array $request = []): array
{
    $originalGet = $_GET;
    $originalQuery = $GLOBALS['wp_query'] ?? null;
    $originalMainQuery = $GLOBALS['wp_the_query'] ?? null;
    $_GET = $request;

    try {
        $query = new WP_Query();
        $query->parse_query($queryVars);
        $GLOBALS['wp_query'] = $query;
        $GLOBALS['wp_the_query'] = $query;
        $query->get_posts();

        return [
            'ids' => array_map(static fn (WP_Post $post): int => $post->ID, $query->posts),
            'found' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
        ];
    } finally {
        $_GET = $originalGet;
        $GLOBALS['wp_query'] = $originalQuery;
        $GLOBALS['wp_the_query'] = $originalMainQuery;
    }
}

/** @return list<int> */
function appleklinika_test_native_category_ids(string $slug): array
{
    $term = get_term_by('slug', $slug, 'product_cat');

    if (! $term instanceof WP_Term) {
        return [];
    }

    $query = new WP_Query([
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => [[
            'taxonomy' => 'product_cat',
            'field' => 'term_id',
            'terms' => [$term->term_id],
        ]],
        'orderby' => 'ID',
        'order' => 'ASC',
    ]);

    return array_map('intval', $query->posts);
}

/** @return list<int> */
function appleklinika_test_product_ids_for_type(string $type): array
{
    $query = new WP_Query([
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [[
            'key' => '_appleklinika_device_type',
            'value' => $type,
        ]],
        'orderby' => 'ID',
        'order' => 'ASC',
    ]);

    return array_map('intval', $query->posts);
}

function appleklinika_test_product_type(int $productId): string
{
    return (string) get_post_meta($productId, '_appleklinika_device_type', true);
}

function appleklinika_test_first_product_id(string $type): int
{
    $ids = appleklinika_test_product_ids_for_type($type);

    return $ids[0] ?? 0;
}

$test = new CatalogSearchDiscoverabilityTest();
$families = [
    'iphone' => 'iphone',
    'ipad' => 'ipad',
    'macbook' => 'macbook',
    'apple-watch' => 'apple_watch',
];

foreach ($families as $category => $type) {
    $expectedIds = appleklinika_test_native_category_ids($category);
    $actual = appleklinika_test_discovery_query([
        'post_type' => 'product',
        'product_cat' => $category,
        'posts_per_page' => -1,
        'orderby' => 'ID',
        'order' => 'ASC',
    ]);

    sort($expectedIds);
    $actualIds = $actual['ids'];
    sort($actualIds);

    $test->assert($expectedIds !== [], "The native {$category} category has published fixture products.");
    $test->assert($actualIds === $expectedIds, "The native {$category} category query returns every matching published product without a hidden iPhone filter.");
    $test->assert($actual['found'] === count($expectedIds), "The native {$category} category result count matches its published product count.");
}

$decisionCases = [
    ['vars' => ['post_type' => 'product'], 'request' => [], 'expected' => 'iphone', 'label' => 'The generic product archive keeps the intentional iPhone default.'],
    ['vars' => ['post_type' => 'product', 'product_cat' => 'ipad'], 'request' => [], 'expected' => 'ipad', 'label' => 'The native iPad taxonomy owns its device context.'],
    ['vars' => ['post_type' => 'product', 'product_cat' => 'macbook'], 'request' => [], 'expected' => 'macbook', 'label' => 'The native MacBook taxonomy owns its device context.'],
    ['vars' => ['post_type' => 'product', 'product_cat' => 'ipad', 's' => 'iPad'], 'request' => [], 'expected' => 'ipad', 'label' => 'A native iPad category search keeps the taxonomy device context.'],
    ['vars' => ['post_type' => 'product', 'product_cat' => 'ipad'], 'request' => ['ak_type' => 'iphone'], 'expected' => 'ipad', 'label' => 'A native iPad taxonomy cannot be overridden by a contradictory hidden device parameter.'],
    ['vars' => ['post_type' => 'product', 's' => 'MacBook'], 'request' => [], 'expected' => null, 'label' => 'An unscoped product search receives no default device context.'],
    ['vars' => ['post_type' => 'product', 's' => 'MacBook'], 'request' => ['ak_type' => 'macbook'], 'expected' => 'macbook', 'label' => 'An explicit search device context overrides the generic default.'],
];

foreach ($decisionCases as $case) {
    $originalGet = $_GET;
    $_GET = $case['request'];
    $query = new WP_Query();
    $query->parse_query($case['vars']);
    $test->assert(appleklinika_shop_query_device_type($query) === $case['expected'], $case['label']);
    $_GET = $originalGet;
}

$nativeIpadQuery = new WP_Query();
$nativeIpadQuery->parse_query(['post_type' => 'product', 'product_cat' => 'ipad']);
$test->assert(! appleklinika_shop_query_uses_device_model_scope($nativeIpadQuery), 'A native iPad category does not receive the canonical ak_type model list as an additional hidden scope.');

$genericCatalogueQuery = new WP_Query();
$genericCatalogueQuery->parse_query(['post_type' => 'product']);
$test->assert(appleklinika_shop_query_uses_device_model_scope($genericCatalogueQuery), 'The generic catalogue retains its intentional default device model scope.');

$globalSearchQuery = new WP_Query();
$globalSearchQuery->parse_query(['post_type' => 'product', 's' => 'MacBook']);
$test->assert(! appleklinika_shop_query_uses_device_model_scope($globalSearchQuery), 'An unscoped product search cannot receive an implicit iPhone model scope.');

foreach (['iphone', 'ipad', 'macbook', 'apple_watch'] as $type) {
    $actual = appleklinika_test_discovery_query([
        'post_type' => 'product',
        'posts_per_page' => -1,
        'orderby' => 'ID',
        'order' => 'ASC',
    ], ['ak_type' => $type]);

    $test->assert($actual['ids'] !== [], "The explicit {$type} catalogue route returns products.");
    $test->assert(
        array_reduce($actual['ids'], static fn (bool $valid, int $productId): bool => $valid && appleklinika_test_product_type($productId) === $type, true),
        "The explicit {$type} catalogue route keeps its requested device family."
    );
}

foreach (['iphone', 'ipad', 'macbook', 'apple_watch'] as $type) {
    $productId = appleklinika_test_first_product_id($type);
    $title = $productId > 0 ? (string) get_post_field('post_title', $productId) : '';
    $actual = appleklinika_test_discovery_query([
        'post_type' => 'product',
        's' => $title,
        'posts_per_page' => -1,
    ]);

    $test->assert($productId > 0 && $title !== '', "A published {$type} product is available for global search coverage.");
    $test->assert(in_array($productId, $actual['ids'], true), "A global exact-title search discovers the representative {$type} product.");
}

$ipadProductId = appleklinika_test_first_product_id('ipad');
$ipadTitle = (string) get_post_field('post_title', $ipadProductId);
$ipadStorage = (string) get_post_meta($ipadProductId, '_appleklinika_storage_capacity', true);
$filteredIpad = appleklinika_test_discovery_query([
    'post_type' => 'product',
    'product_cat' => 'ipad',
    's' => $ipadTitle,
    'posts_per_page' => -1,
], ['ak_storage' => $ipadStorage]);
$test->assert(in_array($ipadProductId, $filteredIpad['ids'], true), 'A native iPad taxonomy search keeps both its category and supported storage filter.');

$macBookProductId = appleklinika_test_first_product_id('macbook');
$macBookSearch = appleklinika_test_discovery_query([
    'post_type' => 'product',
    's' => 'MacBookAir',
    'posts_per_page' => -1,
]);
$test->assert(in_array($macBookProductId, $macBookSearch['ids'], true), 'A common MacBook spacing variation remains globally discoverable.');

$appleWatchProductId = appleklinika_test_first_product_id('apple_watch');
$appleWatchSearch = appleklinika_test_discovery_query([
    'post_type' => 'product',
    's' => 'AppleWatch',
    'posts_per_page' => -1,
]);
$test->assert(in_array($appleWatchProductId, $appleWatchSearch['ids'], true), 'A common Apple Watch spacing variation remains globally discoverable.');

$nonsense = appleklinika_test_discovery_query([
    'post_type' => 'product',
    's' => 'AK-001-no-such-product-9a0bb3be',
    'posts_per_page' => -1,
]);
$test->assert($nonsense['found'] === 0 && $nonsense['ids'] === [], 'A genuine zero-result global search remains empty.');

$emptyCategory = appleklinika_test_discovery_query([
    'post_type' => 'product',
    'product_cat' => 'egyeb',
    'posts_per_page' => -1,
]);
$test->assert($emptyCategory['found'] === 0 && $emptyCategory['ids'] === [], 'The genuinely empty Egyéb category remains empty.');

$ipadPageOne = appleklinika_test_discovery_query([
    'post_type' => 'product',
    'product_cat' => 'ipad',
    'posts_per_page' => 5,
    'paged' => 1,
    'orderby' => 'ID',
    'order' => 'ASC',
]);
$ipadPageTwo = appleklinika_test_discovery_query([
    'post_type' => 'product',
    'product_cat' => 'ipad',
    'posts_per_page' => 5,
    'paged' => 2,
    'orderby' => 'ID',
    'order' => 'ASC',
]);
$test->assert($ipadPageOne['pages'] > 1, 'A populated native iPad category exposes pagination when its page size is limited.');
$test->assert(array_intersect($ipadPageOne['ids'], $ipadPageTwo['ids']) === [], 'Adjacent native iPad category pages do not duplicate product IDs.');

$test->assert(apply_filters('gettext_woocommerce', 'Shop', 'Shop', 'woocommerce') === 'Termékek', 'The WooCommerce product archive title resolves to Hungarian.');
$test->assert(apply_filters('woocommerce_page_title', 'Shop') === 'Termékek', 'The rendered WooCommerce shop archive heading resolves to Hungarian at its owning page-title filter.');
$test->assert(apply_filters('gettext_woocommerce', 'Showing %1$d–%2$d of %3$d results', 'Showing %1$d–%2$d of %3$d results', 'woocommerce') === '%1$d–%2$d termék, összesen %3$d db', 'The dynamic catalogue result-count format resolves to Hungarian without losing its range placeholders.');
$test->assert(apply_filters('gettext_woocommerce', '%s in stock', '%s in stock', 'woocommerce') === '%s készleten', 'The dynamic stock label retains its quantity placeholder in Hungarian.');
$test->assert(apply_filters('gettext_woocommerce', 'Free!', 'Free!', 'woocommerce') === 'Ingyenes', 'The generic zero-price commerce label resolves to Hungarian.');
$test->assert(apply_filters('gettext_woocommerce', 'MacBook Air', 'MacBook Air', 'woocommerce') === 'MacBook Air', 'Commerce localization leaves legitimate product and model names unchanged.');

$test->finish();
